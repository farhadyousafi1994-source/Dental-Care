import {appLocale,t} from './i18n'
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from './api'
import { directionFor } from './domain'
import type { Website } from './types'
export const useCms = defineStore('cms', () => {
  const user = ref<any>(null), websites = ref<Website[]>([]), activity = ref<any[]>([]), counts = ref<any>({}), selectedId = ref<number | null>(null), locale = appLocale, mode = ref(''), ready = ref(false), notice = ref('')
  const selected = computed(() => websites.value.find(s => s.id === selectedId.value))
  const rtl = computed(() => directionFor(locale.value) === 'rtl')
  async function load() { const data = await api('/dashboard'); websites.value = data.websites; activity.value = data.activity; counts.value = data.counts; mode.value = data.mode || 'Laravel API'; notice.value = '' }
  async function init() { try { user.value = await api('/auth/me'); await load() } catch (error: any) { user.value = null; notice.value = error?.unreachable ? t(error.message) : '' } finally { ready.value = true } } // A signed-out visitor is not an error worth a banner.
  return { user, websites, activity, counts, selectedId, selected, locale, rtl, mode, ready, notice, load, init }
})
