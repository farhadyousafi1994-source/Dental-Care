import { describe, expect, it, vi } from 'vitest'

vi.mock('quasar', () => {
  const names = ['QInput','QBtn','QSelect','QDialog','QCard','QList','QItem','QItemSection','QMenu','QSpinner','QIcon','QBadge','QTooltip','QToggle','QCheckbox','QTextarea','QChip','QBanner','QSeparator','QAvatar']
  return Object.fromEntries([...names.map(name => [name, { name, render: () => null }]), ['useQuasar', () => ({ notify: () => {} })]])
})
vi.stubGlobal('location', { pathname: '/', search: '' })
vi.stubGlobal('history', { replaceState: () => {} })
vi.stubGlobal('localStorage', { getItem: () => null, setItem: () => {} })
vi.stubGlobal('document', { documentElement: { dir: 'ltr', lang: 'en' }, addEventListener: () => {} })

import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createPinia, setActivePinia } from 'pinia'
import { t } from './i18n'
import App from './App.vue'
import { useCms } from './store'

const renderLoginCard = async (notice: string) => {
  const pinia = createPinia(); setActivePinia(pinia)
  const cms = useCms(); cms.ready = true; cms.user = null; cms.mode = ''; cms.notice = notice
  const app = createSSRApp(App); app.use(pinia); app.config.globalProperties.$t = t
  app.config.warnHandler = () => {}
  return await renderToString(app)
}

describe('Sign-in screen', () => {
  it('shows why the workspace cannot be reached', async () => {
    const html = await renderLoginCard('Cannot reach the CMS API. Start the backend on port 8000, then reload this page.')
    expect(html).toContain('error-note')
    expect(html).toContain('Cannot reach the CMS API')
  })
  it('hides the note when the API is answering', async () => {
    const html = await renderLoginCard('')
    expect(html).not.toContain('error-note')
    expect(html).toContain('login-card')
  })
})
