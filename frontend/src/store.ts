import {appLocale} from './i18n'
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from './api'
import { directionFor } from './domain'
import type { Website } from './types'
export const useCms = defineStore('cms', () => {
  const user = ref<any>(null), websites = ref<Website[]>([]), activity = ref<any[]>([]), counts = ref<any>({}), selectedId = ref<number | null>(null), locale = appLocale, mode = ref(''), ready = ref(false)
  const selected = computed(() => websites.value.find(s => s.id === selectedId.value))
  const rtl = computed(() => directionFor(locale.value) === 'rtl')
  async function load() { const data = await api('/dashboard'); websites.value = data.websites; activity.value = data.activity; counts.value = data.counts; mode.value = data.mode || 'Laravel API' }
  async function init() { try { user.value = await api('/auth/me'); await load() } catch { user.value = null } finally { ready.value = true } }
  return { user, websites, activity, counts, selectedId, selected, locale, rtl, mode, ready, load, init }
})
