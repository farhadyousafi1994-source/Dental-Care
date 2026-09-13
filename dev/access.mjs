// UI-development records only. This adapter does not implement production account login.
export function registerAccess(app, { all, one, insert, update, remove, log, user, db }) {
  const permissions = ['read','pages','media','appearance','menus','templates','translations','seo','settings','users','commerce','submissions'];
  if (!all('roles').length) for (const [name, grants] of Object.entries({'Super Admin':['*'],Administrator:['*'],Editor:['read','pages','media','templates'],'Content Manager':['read','pages','media','menus','templates','translations'],'Marketing Manager':['read','pages','seo','translations']})) insert('roles',null,{name,permissions:grants,is_system:true});
  user.role_id = all('roles').find(r=>r.name==='Super Admin').id; user.is_active=true; user.is_super_admin=true;
  const accounts=()=>[user,...all('accounts')];
  app.get('/api/access',(req,res)=>res.json({users:accounts(),roles:all('roles').reverse(),permissions}));
  function saveUser(req,res) {
    const d=req.body,id=+req.params.user;
    if(id===user.id)return res.status(422).json({message:'The demo owner is fixed. Use Laravel to manage real accounts.'});
    if(!d.name?.trim()||!/^\S+@\S+\.\S+$/.test(d.email)||!one('roles',d.role_id)||typeof d.is_active!=='boolean')return res.status(422).json({message:'Provide a name, valid email, role and account status.'});
    if(accounts().some(u=>u.id!==id&&u.email.toLowerCase()===d.email.toLowerCase()))return res.status(422).json({message:'This email address is already in use.'});
    if((!id||d.password)&&(!d.password||d.password.length<12||!/[A-Z]/.test(d.password)||!/[a-z]/.test(d.password)||!/[0-9]/.test(d.password)||d.password!==d.password_confirmation))return res.status(422).json({message:'Use matching passwords with 12+ characters, uppercase, lowercase and a number.'});
    const data={name:d.name,email:d.email.toLowerCase(),role_id:d.role_id,is_active:d.is_active}; // Never store demonstration passwords.
    const result=id?update('accounts',id,undefined,data):insert('accounts',null,data);log(null,id?'updated account':'created account',data.email);res.json(result);
  }
  app.post('/api/users',saveUser);app.put('/api/users/:user',saveUser);
  function saveRole(req,res) {
    const d=req.body,id=+req.params.role,old=id?one('roles',id):null;
    if(id&&!old)return res.status(404).json({message:'Role not found.'});
    if(old?.is_system)return res.status(422).json({message:'Built-in roles are protected.'});
    if(!d.name?.trim()||!Array.isArray(d.permissions)||!d.permissions.length||d.permissions.some(p=>!permissions.includes(p)))return res.status(422).json({message:'Choose a role name and valid permissions.'});
    if(all('roles').some(r=>r.id!==id&&r.name.toLowerCase()===d.name.toLowerCase()))return res.status(422).json({message:'Role name is already in use.'});
    const data={name:d.name,permissions:[...new Set(['read',...d.permissions])],is_system:false};res.json(id?update('roles',id,undefined,data):insert('roles',null,data));log(null,'saved role',data.name);
  }
  app.post('/api/roles',saveRole);app.put('/api/roles/:role',saveRole);
  app.delete('/api/roles/:role',(req,res)=>{const role=one('roles',req.params.role);if(!role)return res.status(404).json({message:'Role not found.'});if(role.is_system||accounts().some(u=>u.role_id===role.id)||all('memberships').some(m=>m.role_id===role.id))return res.status(422).json({message:'Protected or assigned roles cannot be deleted.'});db.prepare('DELETE FROM records WHERE id=? AND kind=?').run(role.id,'roles');log(null,'deleted role',role.name);res.json({ok:true})});
  app.put('/api/auth/password',(req,res)=>res.status(501).json({message:'Password changes require the Laravel backend. Preview records are not real login accounts.'}));
  const members=site=>[{...user,role_id:all('roles').find(r=>r.name==='Administrator').id},...all('memberships',site).map(m=>({...accounts().find(u=>u.id===m.user_id),role_id:m.role_id})).filter(m=>m.id)];
  app.get('/api/websites/:site/members',(req,res)=>res.json({members:members(req.siteId),roles:all('roles').filter(r=>r.name!=='Super Admin')}));
  app.post('/api/websites/:site/members',(req,res)=>{const target=accounts().find(u=>u.email===req.body.email&&u.is_active),role=one('roles',req.body.role_id);if(!target)return res.status(404).json({message:'An active account with this email was not found.'});if(!role||role.name==='Super Admin'||target.id===user.id)return res.status(422).json({message:'Choose a non-owner account and a website role.'});const old=all('memberships',req.siteId).find(m=>m.user_id===target.id);const data={user_id:target.id,role_id:role.id};if(old)update('memberships',old.id,req.siteId,data);else insert('memberships',req.siteId,data);log(req.siteId,'updated website membership',target.email);res.json({ok:true})});
  app.delete('/api/websites/:site/members/:user',(req,res)=>{if(+req.params.user===user.id)return res.status(422).json({message:'Cannot remove your own membership.'});const old=all('memberships',req.siteId).find(m=>m.user_id===+req.params.user);if(!old)return res.status(404).json({message:'Membership not found.'});remove('memberships',old.id,req.siteId);log(req.siteId,'removed website membership','User #'+req.params.user);res.json({ok:true})});
}
