import {test,before,after} from 'node:test';import assert from 'node:assert/strict';import {spawn} from 'node:child_process';import {mkdtempSync,rmSync} from 'node:fs';import {tmpdir} from 'node:os';import path from 'node:path';
const dir=mkdtempSync(path.join(tmpdir(),'atelier-test-'));let child,cookie,site,page,other;
async function request(url,method='GET',body,auth=true){const response=await fetch('http://127.0.0.1:8001/api'+url,{method,headers:{'Content-Type':'application/json',...(auth?{Cookie:cookie}: {})},body:body?JSON.stringify(body):undefined});return {status:response.status,data:await response.json(),response}}
before(async()=>{child=spawn(process.execPath,['--experimental-sqlite','server.mjs'],{cwd:import.meta.dirname,env:{...process.env,CMS_PREVIEW_DATA:dir,CMS_PREVIEW_PORT:'8001'},stdio:['ignore','pipe','pipe']});await new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(new Error('API startup timeout')),10000);child.stdout.on('data',x=>{if(x.toString().includes('listening')){clearTimeout(timer);resolve()}});child.on('exit',code=>reject(new Error('API exited '+code)))});const r=await request('/auth/me','GET',null,false);cookie=r.response.headers.get('set-cookie').split(';')[0]});
after(()=>{child?.kill();rmSync(dir,{recursive:true,force:true})});
test('private routes require a preview session',async()=>{assert.equal((await request('/dashboard','GET',null,false)).status,401)});
test('website provisioning creates complete initial configuration',async()=>{const r=await request('/websites','POST',{name:'Integration test',domain:'integration.example.com',type:'Business',theme:'Evergreen',default_language:'en',default_currency:'USD'});assert.equal(r.status,201);site=r.data;assert.equal(site.languages.length,4);assert.equal(site.currencies.length,7);assert.equal(site.pages_count,1);assert.equal(site.appearance.primary,'#286b54');for(const kind of ['templates','components','menus','currencies'])assert.ok((await request(`/websites/${site.id}/${kind}`)).data.length);page=(await request(`/websites/${site.id}/pages`)).data[0];other=(await request('/dashboard')).data.websites.find(s=>s.id!==site.id)});
test('duplicate domain rejected',async()=>assert.equal((await request('/websites','POST',{name:'Duplicate',domain:site.domain})).status,422));
test('draft website not exposed publicly',async()=>assert.equal((await request(`/public/websites/${site.id}/home`,'GET',null,false)).status,404));
test('page identifiers cannot cross website scopes',async()=>assert.equal((await request(`/websites/${other.id}/pages/${page.id}`,'DELETE')).status,404));
test('published changes persist and are returned by the public renderer API',async()=>{page=(await request(`/websites/${site.id}/pages/${page.id}`,'PUT',{...page,title:'Updated title',status:'published',blocks:[{id:'test-block',type:'Text',title:'Persisted heading',text:'Saved body'}]})).data;assert.equal(page.title,'Updated title');assert.equal((await request(`/websites/${site.id}`,'PUT',{status:'published'})).status,200);const r=await request(`/public/websites/${site.id}/home`,'GET',null,false);assert.equal(r.status,200);assert.equal(r.data.page.blocks[0].title,'Persisted heading')});
test('appearance is persisted and public output uses it',async()=>{const r=await request(`/websites/${site.id}/appearance`,'PUT',{theme:'Editorial',appearance:{...site.appearance,primary:'#855039'}});assert.equal(r.status,200);assert.equal((await request(`/public/websites/${site.id}/home`,'GET',null,false)).data.website.appearance.primary,'#855039')});
test('revision restoration preserves prior snapshot and restores as draft',async()=>{const revisions=(await request(`/websites/${site.id}/pages/${page.id}/revisions`)).data;assert.ok(revisions.length);const restored=await request(`/websites/${site.id}/pages/${page.id}/revisions/${revisions[0].id}/restore`,'POST',{version:page.version});assert.equal(restored.data.status,'draft');assert.equal(restored.data.title,'Home');assert.equal((await request(`/public/websites/${site.id}/home`,'GET',null,false)).status,404)});
test('independent translation, menus and currency records persist',async()=>{for(const [kind,body] of [['translations',{key:'home.title',language:'fa',value:'خوش آمدید'}],['menus',{name:'Main navigation',location:'header',language:'en',version:1,items:[{key:'start',label:'Start',url:'home',new_tab:false,children:[]}]}],['currencies',{currency_code:'AFN',exchange_rate:70.5,position:'after'}]]){assert.equal((await request(`/websites/${site.id}/${kind}`,'POST',body)).status,200);const list=(await request(`/websites/${site.id}/${kind}`)).data;assert.ok(list.some(x=>kind==='translations'?x.value===body.value:kind==='menus'?x.items[0].label==='Start':x.exchange_rate===70.5))}});
test('media upload, metadata update and deletion work',async()=>{const data=new FormData();data.append('files[]',new Blob([Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2mS8AAAAASUVORK5CYII=','base64')],{type:'image/png'}),'test.png');const res=await fetch(`http://127.0.0.1:8001/api/websites/${site.id}/media`,{method:'POST',headers:{Cookie:cookie},body:data});assert.equal(res.status,201);const [m]=await res.json();assert.equal((await request(`/websites/${site.id}/media/${m.id}`,'PUT',{name:'renamed.png',alt:'Accessible description',folder:'Tests'})).data.alt,'Accessible description');assert.equal((await request(`/websites/${other.id}/media/${m.id}`,'DELETE')).status,404);assert.equal((await request(`/websites/${site.id}/media/${m.id}`,'DELETE')).status,200)});

test('autosave persists privately without modifying the published page',async()=>{
  page=(await request(`/websites/${site.id}/pages`)).data.find(p=>p.id===page.id);
  page=(await request(`/websites/${site.id}/pages/${page.id}`,'PUT',{...page,status:'published'})).data;
  const draft={...page,title:'Private working title',autosave_version:0};
  const saved=await request(`/websites/${site.id}/pages/${page.id}/autosave`,'PUT',draft);assert.equal(saved.status,200);assert.equal(saved.data.autosave_version,1);
  assert.equal((await request(`/websites/${site.id}/pages/${page.id}/editor`)).data.autosave.snapshot.title,'Private working title');
  assert.equal((await request(`/public/websites/${site.id}/home`,'GET',null,false)).data.page.title,page.title);
  assert.equal((await request(`/websites/${other.id}/pages/${page.id}/editor`)).status,404);
});
test('concurrent working-copy writes and stale saves return 409',async()=>{
  assert.equal((await request(`/websites/${site.id}/pages/${page.id}/autosave`,'PUT',{...page,title:'Stale tab',autosave_version:0})).status,409);
  assert.equal((await request(`/websites/${site.id}/pages/${page.id}/autosave`,'DELETE',{autosave_version:0})).status,409);
  const saved=await request(`/websites/${site.id}/pages/${page.id}`,'PUT',{...page,title:'Fresh publication',autosave_version:1});assert.equal(saved.status,200);
  assert.equal((await request(`/websites/${site.id}/pages/${page.id}`,'PUT',{...page,title:'Outdated version'})).status,409);
  assert.equal((await request(`/websites/${site.id}/pages/${page.id}/autosave`,'PUT',{...page,title:'Outdated copy'})).status,409);
  const editor=(await request(`/websites/${site.id}/pages/${page.id}/editor`)).data;assert.equal(editor.autosave,null);assert.equal(editor.page.title,'Fresh publication');page=editor.page;
});
test('preview custom role, account and website membership persist without passwords',async()=>{
  const role=await request('/roles','POST',{name:'Media reviewer',permissions:['read','media']});assert.equal(role.status,200);
  const account=await request('/users','POST',{name:'Review User',email:'review@example.com',role_id:role.data.id,is_active:true,password:'TestingOnly123!',password_confirmation:'TestingOnly123!'});assert.equal(account.status,200);assert.equal(account.data.password,undefined);
  assert.equal((await request(`/websites/${site.id}/members`,'POST',{email:account.data.email,role_id:role.data.id})).status,200);
  assert.ok((await request(`/websites/${site.id}/members`)).data.members.some(m=>m.email===account.data.email));
  assert.equal((await request('/roles/'+role.data.id,'DELETE')).status,422);
  assert.equal((await request(`/websites/${other.id}/members/${account.data.id}`,'DELETE')).status,404);
  assert.equal((await request(`/websites/${site.id}/members/${account.data.id}`,'DELETE')).status,200);
  const superRole=(await request('/access')).data.roles.find(r=>r.name==='Super Admin');assert.equal((await request('/roles/'+superRole.id,'DELETE')).status,422);
});
test('scheduling validates future publication time',async()=>{
  assert.equal((await request(`/websites/${site.id}/pages/${page.id}`,'PUT',{...page,status:'scheduled',publish_at:'2020-01-01T00:00:00Z'})).status,422);
  const future=new Date(Date.now()+3600000).toISOString();assert.equal((await request(`/websites/${site.id}/pages/${page.id}`,'PUT',{...page,status:'scheduled',publish_at:future})).status,200);
  assert.equal((await request(`/public/websites/${site.id}/home`,'GET',null,false)).status,404);
});

test('shared components, translations and sitemap expose only published data',async()=>{
  page=(await request(`/websites/${site.id}/pages/${page.id}/editor`)).data.page;
  page=(await request(`/websites/${site.id}/pages/${page.id}`,'PUT',{...page,status:'published',publish_at:null})).data;
  assert.equal((await request(`/websites/${site.id}/components`,'POST',{name:'Header',content:{title:'Shared test brand'}})).status,200);
  await request(`/websites/${site.id}/translations`,'POST',{key:'components.Header.title',language:'en',value:'Translated brand'});
  const pub=(await request(`/public/websites/${site.id}/home`,'GET',null,false)).data;
  assert.equal(pub.components.find(c=>c.name==='Header').content.title,'Shared test brand');assert.equal(pub.translations['components.Header.title'],'Translated brand');assert.equal(pub.website.settings,undefined);assert.equal(pub.page.author_id,undefined);
  const sitemap=await fetch(`http://127.0.0.1:8001/api/public/websites/${site.id}/sitemap.xml`);assert.equal(sitemap.status,200);assert.ok((await sitemap.text()).includes('/home?lang=en'));
  assert.equal((await request(`/websites/${site.id}/components`,'POST',{name:'Global CTA',content:{link:'javascript:alert(1)'}})).status,422);
  await request(`/websites/${site.id}/pages/${page.id}`,'PUT',{...page,seo:{robots:'noindex,follow'}});
  assert.ok(!(await (await fetch(`http://127.0.0.1:8001/api/public/websites/${site.id}/sitemap.xml`)).text()).includes('/home?lang=en'));
});

test('nested multilingual menus persist independently with stale-write protection',async()=>{
 const payload={name:'Footer Dari',location:'footer',language:'fa',version:0,items:[{key:'parent',label:'درباره ما',url:'home',new_tab:false,children:[{key:'child',label:'Contact',url:'https://example.com',new_tab:true,children:[]}]}]};
 const saved=await request(`/websites/${site.id}/menus`,'POST',payload);assert.equal(saved.status,200);assert.equal(saved.data.version,1);assert.equal(saved.data.items[0].children[0].new_tab,true);
 assert.equal((await request(`/websites/${site.id}/menus`,'POST',payload)).status,409);
 const invalid={...payload,language:'en',items:[{key:'bad',label:'Bad',url:'javascript:alert(1)',children:[]}]};assert.equal((await request(`/websites/${site.id}/menus`,'POST',invalid)).status,422);
 const menus=(await request(`/websites/${site.id}/menus`)).data;assert.ok(menus.some(m=>m.language==='en'&&m.location==='header'));assert.ok(menus.some(m=>m.language==='fa'&&m.location==='footer'));
});
test('hierarchical media folders reject cycles and cross-site moves',async()=>{
 const parent=(await request(`/websites/${site.id}/media-folders`,'POST',{name:'Parent'})).data;
 const child=(await request(`/websites/${site.id}/media-folders`,'POST',{name:'Child',parent_id:parent.id})).data;
 assert.equal((await request(`/websites/${site.id}/media-folders/${parent.id}`,'PUT',{name:'Parent',parent_id:child.id})).status,422);
 assert.equal((await request(`/websites/${other.id}/media-folders`,'POST',{name:'Wrong parent',parent_id:parent.id})).status,422);
 assert.equal((await request(`/websites/${site.id}/media-folders/${parent.id}`,'DELETE')).status,422);
 assert.equal((await request(`/websites/${site.id}/media-folders/${child.id}`,'DELETE')).status,200);
 assert.equal((await request(`/websites/${site.id}/media-folders/${parent.id}`,'DELETE')).status,200);
});
test('raster crop resize and optimization create a new file while retaining the original',async()=>{
 const sharp=(await import('sharp')).default;
 const bytes=await sharp({create:{width:200,height:100,channels:3,background:'#286b54'}}).png().toBuffer();
 const data=new FormData();data.append('files[]',new Blob([bytes],{type:'image/png'}),'raster-test.png');
 const response=await fetch(`http://127.0.0.1:8001/api/websites/${site.id}/media`,{method:'POST',headers:{Cookie:cookie},body:data});assert.equal(response.status,201);const [original]=await response.json();
 const options={x:25,y:0,crop_width:50,crop_height:100,width:50,quality:80,format:'webp',name:'cropped'};
 const result=await request(`/websites/${site.id}/media/${original.id}/transform`,'POST',options);assert.equal(result.status,201);assert.equal(result.data.width,50);assert.equal(result.data.height,50);assert.notEqual(result.data.url,original.url);
 const output=await fetch('http://127.0.0.1:8001'+result.data.url);const meta=await sharp(Buffer.from(await output.arrayBuffer())).metadata();assert.equal(meta.format,'webp');assert.equal(meta.width,50);
 const originalFile=await fetch('http://127.0.0.1:8001'+original.url);assert.deepEqual(Buffer.from(await originalFile.arrayBuffer()),bytes);
 assert.equal((await request(`/websites/${other.id}/media/${original.id}/transform`,'POST',options)).status,404);
 assert.equal((await request(`/websites/${site.id}/media/${original.id}/transform`,'POST',{...options,x:90})).status,422);
});
test('appearance presets are private to a website and do not activate on save',async()=>{
 const before=(await request('/dashboard')).data.websites.find(w=>w.id===site.id).appearance;
 const saved=await request(`/websites/${site.id}/theme-presets`,'POST',{name:'Quiet dark',theme:'Studio',appearance:{...before,primary:'#123456',mode:'dark'}});assert.equal(saved.status,200);
 assert.equal((await request('/dashboard')).data.websites.find(w=>w.id===site.id).appearance.primary,before.primary);
 assert.equal((await request(`/websites/${other.id}/theme-presets/${saved.data.id}`,'DELETE')).status,404);
 assert.equal((await request(`/websites/${site.id}/theme-presets/${saved.data.id}`,'DELETE')).status,200);
});
test('nested blocks survive publication and duplicate ids are rejected across columns',async()=>{
 page=(await request(`/websites/${site.id}/pages/${page.id}/editor`)).data.page;
 const blocks=[{id:'columns-root',type:'Columns',title:'Our team',text:'',children:[[{id:'nested-one',type:'Text',title:'Column one',text:'Saved nested content'}],[{id:'nested-two',type:'Cards',title:'Features',text:'',items:[{id:'card1',title:'Feature',text:'Body',link:'#about'}]}]]}];
 const result=await request(`/websites/${site.id}/pages/${page.id}`,'PUT',{...page,blocks,status:'published'});assert.equal(result.status,200);page=result.data;
 const pub=(await request(`/public/websites/${site.id}/home`,'GET',null,false)).data;assert.equal(pub.page.blocks[0].children[0][0].text,'Saved nested content');
 blocks[0].children[1][0].id='nested-one';assert.equal((await request(`/websites/${site.id}/pages/${page.id}`,'PUT',{...page,blocks})).status,422);
});

test('contact messages are private, validated and scoped',async()=>{
 const body={name:'Visitor',email:'visitor@example.com',message:'Please send information about your services.',language:'en',consent:true,company:''};
 assert.equal((await request(`/public/websites/${site.id}/contact`,'POST',body,false)).status,201);
 assert.equal((await request(`/public/websites/${site.id}/contact`,'POST',{...body,consent:false},false)).status,422);
 assert.equal((await request(`/websites/${site.id}/inbox`,'GET',null,false)).status,401);
 const inbox=(await request(`/websites/${site.id}/inbox`)).data;assert.equal(inbox.messages.total,1);
 assert.equal((await request(`/websites/${other.id}/submissions/${inbox.messages.data[0].id}`,'PUT',{status:'read'})).status,404);
 assert.equal((await request(`/websites/${site.id}/submissions/${inbox.messages.data[0].id}`,'PUT',{status:'read'})).status,200);
});
test('newsletter requires consent and one-use email verification, then supports unsubscribe',async()=>{
 const payload={email:'subscriber@example.com',language:'fa',consent:true,company:''};
 let r=await request(`/public/websites/${site.id}/newsletter`,'POST',payload,false);assert.equal(r.status,202);assert.ok(!JSON.stringify(r.data).includes('token'));
 let subscriber=(await request(`/websites/${site.id}/inbox`)).data.subscribers.data[0];assert.equal(subscriber.status,'pending');assert.ok(!subscriber.token_hash);
 let url=new URL(subscriber.preview_email_url,'http://example.com'),token=url.searchParams.get('newsletter');
 assert.equal((await request(`/public/websites/${site.id}/subscription`,'POST',{email:payload.email,token,action:'confirm'},false)).status,200);
 assert.equal((await request(`/public/websites/${site.id}/subscription`,'POST',{email:payload.email,token,action:'confirm'},false)).status,422);
 await request(`/public/websites/${site.id}/newsletter`,'POST',payload,false);
 subscriber=(await request(`/websites/${site.id}/inbox`)).data.subscribers.data[0];url=new URL(subscriber.preview_email_url,'http://example.com');
 assert.equal((await request(`/public/websites/${site.id}/subscription`,'POST',{email:payload.email,token:url.searchParams.get('newsletter'),action:'unsubscribe'},false)).status,200);
 assert.equal((await request(`/websites/${site.id}/inbox`)).data.subscribers.data[0].status,'unsubscribed');
});
let product,order;
test('products persist with scoped SKU and optimistic stock editing',async()=>{
 const currency=(await request('/dashboard')).data.websites.find(w=>w.id===site.id).default_currency;
 const p={name:'Care kit',sku:'KIT-01',description:'Daily care',image:'',currency,price_minor:1250,stock:4,active:true};
 product=(await request(`/websites/${site.id}/products`,'POST',p)).data;assert.equal(product.version,1);
 assert.equal((await request(`/websites/${site.id}/products`,'POST',p)).status,422);
 assert.equal((await request(`/websites/${other.id}/products/${product.id}`,'PUT',{...product,version:1})).status,404);
 const catalog=(await request(`/public/websites/${site.id}/store/catalog`,'GET',null,false)).data;assert.equal(catalog.products.data[0].price_minor,1250);
});
test('checkout calculates server prices, reserves stock once and rejects changed idempotency payload',async()=>{
 const body={idempotency_key:crypto.randomUUID(),name:'Buyer',email:'buyer@example.com',phone:'0700000000',address:'Delivery address in Kabul',consent:true,items:[{product_id:product.id,quantity:2,price_minor:1}]};
 const first=await request(`/public/websites/${site.id}/store/checkout`,'POST',body,false);assert.equal(first.status,201);assert.equal(first.data.total_minor,2500);
 const repeat=await request(`/public/websites/${site.id}/store/checkout`,'POST',body,false);assert.equal(repeat.data.reference,first.data.reference);
 assert.equal((await request(`/public/websites/${site.id}/store/checkout`,'POST',{...body,name:'Changed'},false)).status,409);
 assert.equal((await request(`/websites/${site.id}/products`)).data.data[0].stock,2);
 assert.equal((await request(`/websites/${site.id}/products/${product.id}`,'PUT',product)).status,409);
 order=(await request(`/websites/${site.id}/orders`)).data.data[0];assert.ok(!order.request_hash&&!order.idempotency_key);
});
test('failed checkout rolls back stock and cancellation restores it once',async()=>{
 const body={idempotency_key:crypto.randomUUID(),name:'Buyer',email:'buyer@example.com',phone:'0700000000',address:'Delivery address in Kabul',consent:true,items:[{product_id:product.id,quantity:1},{product_id:9999999,quantity:1}]};
 assert.equal((await request(`/public/websites/${site.id}/store/checkout`,'POST',body,false)).status,422);
 assert.equal((await request(`/websites/${site.id}/products`)).data.data[0].stock,2);
 const cancel={status:'cancelled',payment_status:'unpaid',expected_status:'pending',expected_payment_status:'unpaid'};
 assert.equal((await request(`/websites/${other.id}/orders/${order.id}`,'PUT',cancel)).status,404);
 assert.equal((await request(`/websites/${site.id}/orders/${order.id}`,'PUT',cancel)).status,200);
 assert.equal((await request(`/websites/${site.id}/orders/${order.id}`,'PUT',cancel)).status,409);
 assert.equal((await request(`/websites/${site.id}/products`)).data.data[0].stock,4);
});
