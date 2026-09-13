<script setup lang="ts">
import { ref, watch, provide, onMounted, onUnmounted, computed } from 'vue'
import { themeStyle, directionFor } from '../domain'
import { api, send } from '../api'
import BlockRenderer from './BlockRenderer.vue'
import Icon from './Icon.vue'
import Storefront from './Storefront.vue'
import { t, appLocale } from '../i18n'
import PublicMenu from './PublicMenu.vue'
const data = ref<any>(null), error = ref(''), language = ref(new URLSearchParams(location.search).get('lang') || '')
const [, , siteId, pathSlug] = location.pathname.split('/'), slug = pathSlug || 'home'
provide('publicSite',siteId);provide('publicLanguage',language)
const shop=ref(new URLSearchParams(location.search).has('shop')),subscriptionToken=ref(new URLSearchParams(location.search).get('newsletter')||''),subscriptionResult=ref('')
const subscriptionEmail=new URLSearchParams(location.search).get('email')
function syncLocation(){const query=new URLSearchParams({lang:language.value||'en'});if(shop.value)query.set('shop','1');history.replaceState(null,'',location.pathname+'?'+query)}
watch(shop,syncLocation)
async function subscription(action:string){try{await api('/public/websites/'+siteId+'/subscription',send('POST',{token:subscriptionToken.value,email:subscriptionEmail,action}));subscriptionResult.value=t('Subscription updated.');subscriptionToken.value='';history.replaceState(null,'',location.pathname)}catch(e:any){subscriptionResult.value=t(e.message)}}
const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)'), systemDark = ref(mediaQuery.matches)
const onModeChange = (event: MediaQueryListEvent) => systemDark.value = event.matches
mediaQuery.addEventListener('change', onModeChange)
onUnmounted(() => mediaQuery.removeEventListener('change', onModeChange))
const style = computed(() => ({ ...themeStyle(data.value?.website.appearance || {}, systemDark.value), maxWidth: (data.value?.website.appearance.container || 1200) + 'px' }))
function component(name: string): Record<string, any> {
  const content = { ...data.value?.components.find((c: any) => c.name === name)?.content }
  for (const field of ['title', 'text', 'button']) {
    const translated = data.value?.translations?.[`components.${name}.${field}`]
    if (typeof translated === 'string') content[field] = translated
  }
  return content
}
function metadata(key: string, value: string, property = false) {
  const attribute = property ? 'property' : 'name'
  let meta = document.head.querySelector(`meta[${attribute}="${key}"]`)
  if (!meta) { meta = document.createElement('meta'); meta.setAttribute(attribute, key); document.head.append(meta) }
  meta.setAttribute('content', value)
}
async function load() {
  error.value = ''
  try {
    const next = await api('/public/websites/' + siteId + '/' + slug + (language.value ? '?lang=' + encodeURIComponent(language.value) : ''))
    data.value = next; language.value = next.page.language; appLocale.value=language.value as any
    document.title = next.page.seo?.title || next.page.title
    const seo = next.page.seo || {}, canonical = seo.canonical || location.origin + '/site/' + siteId + '/' + slug + '?lang=' + language.value
    metadata('description', seo.description || ''); metadata('robots', seo.robots || 'index,follow')
    metadata('og:title', seo.og_title || document.title, true); metadata('og:description', seo.og_description || seo.description || '', true)
    metadata('og:url', canonical, true); metadata('og:type', 'website', true)
    metadata('og:image', seo.social_image ? new URL(seo.social_image, location.origin).href : '', true)
    let link = document.head.querySelector('link[rel="canonical"]')
    if (!link) { link = document.createElement('link'); link.setAttribute('rel', 'canonical'); document.head.append(link) }
    link.setAttribute('href', canonical)
    document.documentElement.lang = language.value; document.documentElement.dir = directionFor(language.value)
    syncLocation()
  } catch (e: any) { error.value = e.message; document.title = 'Page unavailable'; metadata('robots', 'noindex,nofollow') }
}
function href(url: string | undefined) {
  if (!url) return '#'
  if (/^(https?:\/\/|mailto:|tel:|#)/i.test(url)) return url
  if (!/^\/?[a-zA-Z0-9_/-]+$/.test(url) || url.startsWith('//')) return '#'
  return '/site/' + siteId + '/' + url.replace(/^\//, '') + '?lang=' + language.value
}
onMounted(load)
</script>
<template><section v-if="subscriptionToken||subscriptionResult" class="panel"><h2>{{ $t('Manage subscription') }}</h2><p role="status">{{ subscriptionResult }}</p><div v-if="subscriptionToken" class="action-row"><button class="btn primary" @click="subscription('confirm')">{{ $t('Confirm subscription') }}</button><button class="btn secondary" @click="subscription('unsubscribe')">{{ $t('Unsubscribe') }}</button></div></section><div v-if="error" class="public-error"><Icon name="PanelTop" :size="40"/><h1>This page is not live yet.</h1><p>{{ error }}</p><p>Publish the page and website in your CMS. Translations must be published independently.</p><select v-model="language" @change="load" aria-label="Choose another language"><option value="en">English</option><option value="fa">دری</option><option value="ps">پښتو</option><option value="ar">العربية</option></select><a class="btn primary" href="/">Back to workspace</a></div><div v-else-if="data" class="public-site" :style="style"><div v-if="component('Announcement Bar').enabled" class="public-announcement">{{ component('Announcement Bar').text }}<a v-if="component('Announcement Bar').button" :href="href(component('Announcement Bar').link)">{{ component('Announcement Bar').button }}</a></div><header class="public-header"><a :href="href('home')" class="public-brand"><img v-if="component('Header').logo" :src="component('Header').logo" :alt="component('Header').title || data.website.name"><Icon v-else name="Leaf" :size="25"/><span>{{ component('Header').title || data.website.name }}<small v-if="component('Header').text">{{ component('Header').text }}</small></span></a><nav class="desktop-public-nav" aria-label="Main navigation"><button class="text-link" @click="shop=!shop">{{ $t(shop?'Home':'Shop') }}</button><PublicMenu :items="data.menus" :resolve-link="href"/></nav><details class="mobile-public-nav"><summary :aria-label="$t('Menus')">☰</summary><nav aria-label="Mobile navigation"><button class="text-link" @click="shop=!shop">{{ $t(shop?'Home':'Shop') }}</button><PublicMenu :items="data.menu_locations?.mobile || data.menus" :resolve-link="href"/></nav></details><select v-model="language" @change="load" aria-label="Website language"><option v-for="lang in data.website.languages" :value="lang">{{ ({en:'English',fa:'دری',ps:'پښتو',ar:'العربية'} as Record<string,string>)[lang] || lang }}</option></select></header><nav v-if="data.menu_locations?.secondary?.length" class="secondary-public-nav" aria-label="Secondary navigation"><PublicMenu :items="data.menu_locations.secondary" :resolve-link="href"/></nav><Storefront v-if="shop" :site="siteId"/><BlockRenderer v-else :blocks="data.page.blocks"/><section v-if="component('Global CTA').enabled" class="public-global-cta"><h2>{{ component('Global CTA').title }}</h2><p>{{ component('Global CTA').text }}</p><a v-if="component('Global CTA').button" class="public-button" :href="href(component('Global CTA').link)">{{ component('Global CTA').button }} <Icon name="ArrowUpRight" :size="16"/></a></section><footer id="about" class="public-footer"><strong>{{ component('Header').title || data.website.name }}</strong><p>{{ component('Footer').text }}</p><nav v-if="data.menu_locations?.footer?.length" aria-label="Footer navigation"><PublicMenu :items="data.menu_locations.footer" :resolve-link="href"/></nav><a href="/">Made with atelier ↗</a></footer></div><div v-else class="loading-screen"><q-spinner color="primary" size="35px"/></div></template>
