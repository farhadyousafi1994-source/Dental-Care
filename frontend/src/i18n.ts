import {ref} from 'vue'
import fa from './locales/fa.json'
import ps from './locales/ps.json'
import ar from './locales/ar.json'
export type Locale='en'|'fa'|'ps'|'ar'
const stored=typeof localStorage==='undefined'?'en':localStorage.getItem('cms.locale')
export const appLocale=ref<Locale>(['en','fa','ps','ar'].includes(stored||'')?stored as Locale:'en')
const catalogs:Record<string,Record<string,string>>={fa,ps,ar}
export function t(message:string|null|undefined):string{const catalog=catalogs[appLocale.value];if(!message)return '';if(!catalog)return message;if(catalog[message])return catalog[message];for(const key of Object.keys(catalog).filter(k=>k.includes('{name}'))){const [before,after]=key.split('{name}');if(message.startsWith(before)&&message.endsWith(after))return catalog[key].replace('{name}',message.slice(before.length,after?-after.length:undefined))}return message}

export function translatedOptions(options:any[]):any[]{return options.map(o=>typeof o==='string'?{label:t(o),value:o}:({...o,label:t(o.label||o.name||'')}))}
