import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { Quasar, Notify, Dialog } from 'quasar'
import 'quasar/src/css/index.sass'
import '@quasar/extras/material-icons/material-icons.css'
import '@fontsource-variable/dm-sans/wght.css'
import '@fontsource-variable/manrope/wght.css'
import '@fontsource-variable/inter/wght.css'
import './style.css'
import App from './App.vue'
import {t,translatedOptions,appLocale} from './i18n'
import {watch} from 'vue'
import faIR from 'quasar/lang/fa-IR'
import arabic from 'quasar/lang/ar'
import enUS from 'quasar/lang/en-US'
import pashto from './locales/quasar-ps'
const app=createApp(App)
app.config.globalProperties.$t=t
app.config.globalProperties.$translatedOptions=translatedOptions
app.use(createPinia()).use(Quasar, { plugins: { Notify, Dialog }, config: { brand: { primary: '#286b54', secondary: '#dce9d9', accent: '#d9edba' } } }).mount('#app')

watch(appLocale,locale=>Quasar.lang.set(locale==='en'?enUS:locale==='fa'?faIR:locale==='ps'?pashto:arabic),{immediate:true})
