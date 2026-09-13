import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

const fetchMock = vi.fn()
beforeEach(() => { vi.resetModules(); fetchMock.mockReset(); vi.stubGlobal('fetch', fetchMock); setActivePinia(createPinia()) })
afterEach(async () => { const { appLocale } = await import('./i18n'); appLocale.value = 'en'; vi.unstubAllGlobals() })

describe('Workspace boot', () => {
  it('explains an offline backend on the sign-in screen instead of failing silently', async () => {
    fetchMock.mockResolvedValue(new Response('', { status: 500 })) // Vite proxy with no API on port 8000
    const { useCms } = await import('./store')
    const cms = useCms()
    await cms.init()
    expect(cms.ready).toBe(true)
    expect(cms.user).toBeNull()
    expect(cms.notice).toContain('Cannot reach the CMS API')
  })
  it('translates that explanation for RTL administrators', async () => {
    const { appLocale } = await import('./i18n') // the same instance the store reads
    appLocale.value = 'fa'
    fetchMock.mockRejectedValue(new TypeError('Failed to fetch'))
    const { useCms } = await import('./store')
    const cms = useCms()
    await cms.init()
    expect(cms.notice).toBe('به API سیستم مدیریت محتوا دسترسی نیست. سرور را روی پورت ۸۰۰۰ اجرا کنید و این صفحه را دوباره بار کنید.')
  })
  it('keeps the sign-in screen clean for a signed-out visitor', async () => {
    fetchMock.mockResolvedValue(new Response(JSON.stringify({ message: 'Please sign in to continue.' }), { status: 401, headers: { 'Content-Type': 'application/json' } }))
    const { useCms } = await import('./store')
    const cms = useCms()
    await cms.init()
    expect(cms.user).toBeNull()
    expect(cms.notice).toBe('')
  })
})
