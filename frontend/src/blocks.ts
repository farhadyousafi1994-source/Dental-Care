import type {Block} from './types'
export const blockTypes=['Hero','Text','Image','Image + Text','Video','Gallery','Slider','Banner','Cards','Services','Features','Testimonials','Team','Statistics','Pricing','FAQ','CTA','Contact','Newsletter','Logos','Events','Announcements','Social Media','Section','Columns']
export const itemTypes=['Gallery','Slider','Cards','Services','Features','Testimonials','Team','Statistics','Pricing','FAQ','Logos','Events','Announcements','Social Media']
export function newBlock(type:string):Block {
 const block:Block={id:crypto.randomUUID(),type,title:type==='Hero'?'A big idea starts here.':type==='Text'?'Tell your story.':type,text:'Make this section your own.',button:['Hero','CTA','Banner'].includes(type)?'Discover more':'',link:'#about'}
 if(itemTypes.includes(type))block.items=[{id:crypto.randomUUID(),title:type==='FAQ'?'How can we help?':'Your first item',text:'Add a meaningful description.',label:'Learn more',link:'#about'}]
 if(type==='Section'||type==='Columns'){block.children=type==='Section'?[[]]:[[],[]];block.text='';block.title=''}
 return block
}
export function walkBlocks(blocks:Block[]):Block[]{return blocks.flatMap(b=>[b,...(b.children||[]).flatMap(walkBlocks)])}
export function findBlock(blocks:Block[],id:string|null):Block|undefined{return walkBlocks(blocks).find(b=>b.id===id)}
export function containingBlocks(blocks:Block[],id:string):Block[]|undefined{if(blocks.some(b=>b.id===id))return blocks;for(const b of blocks)for(const column of b.children||[]){const list=containingBlocks(column,id);if(list)return list}}
export function cloneBlocks(blocks:Block[]):Block[]{return blocks.map(b=>({...JSON.parse(JSON.stringify(b)),id:crypto.randomUUID(),...(b.items?{items:b.items.map(i=>({...i,id:crypto.randomUUID()}))}:{}),...(b.children?{children:b.children.map(cloneBlocks)}:{})}))}
export function safeLink(url?:string):string{if(!url)return '#about';const value=url.trim();if(/^(https?:\/\/|mailto:|tel:|#)/i.test(value)||/^\/(?!\/)/.test(value))return value;return '#about'}
export function imageStyles(block:Block):Record<string,string>{return {objectFit:block.imageStyle?.fit||'cover',objectPosition:block.imageStyle?.position||'center',borderRadius:(block.imageStyle?.radius??12)+'px',opacity:String(block.imageStyle?.opacity??1)}}

export function blockDepth(blocks:Block[],id:string,level=1):number{for(const b of blocks){if(b.id===id)return level;for(const column of b.children||[]){const result=blockDepth(column,id,level+1);if(result)return result}}return 0}
