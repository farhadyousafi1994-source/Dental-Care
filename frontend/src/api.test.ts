import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const fetchMock = vi.fn()
const json = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
beforeEach(() => { vi.resetModules(); fetchMock.mockReset(); vi.stubGlobal('fetch', fetchMock) })
afterEach(() => vi.unstubAllGlobals())

describe('Session API client', () => {
  it('deduplicates concurrent CSRF requests and never stores bearer tokens', async () => {
    fetchMock.mockImplementation(async (url: string) => url === '/api/csrf' ? json({token:'csrf-value'}) : json({ok:true}))
    const {api,send} = await import('./api')
    await Promise.all([api('/a',send('POST',{})), api('/b',send('POST',{}))])
    expect(fetchMock.mock.calls.filter(c=>c[0]==='/api/csrf')).toHaveLength(1)
    const mutation = fetchMock.mock.calls.find(c=>c[0]==='/api/a')![1]
    expect(mutation.credentials).toBe('include')
    expect(mutation.headers['X-CSRF-TOKEN']).toBe('csrf-value')
    expect(mutation.headers.Authorization).toBeUndefined()
  })
  it('refreshes an expired CSRF token once and retries the rejected write', async () => {
    fetchMock.mockResolvedValueOnce(json({token:'old'})).mockResolvedValueOnce(json({message:'expired'},419)).mockResolvedValueOnce(json({token:'new'})).mockResolvedValueOnce(json({ok:true}))
    const {api,send}=await import('./api');expect(await api('/save',send('PUT',{}))).toEqual({ok:true})
    expect(fetchMock).toHaveBeenCalledTimes(4)
    expect(fetchMock.mock.calls[3][1].headers['X-CSRF-TOKEN']).toBe('new')
  })
  it('surfaces conflict status without automatically retrying destructive writes', async () => {
    fetchMock.mockResolvedValueOnce(json({token:'t'})).mockResolvedValueOnce(json({message:'Newer page exists'},409))
    const {api,send}=await import('./api');await expect(api('/page',send('PUT',{}))).rejects.toMatchObject({status:409,message:'Newer page exists'})
    expect(fetchMock).toHaveBeenCalledTimes(2)
  })
  it('reports a non-JSON backend failure cleanly', async () => {
    fetchMock.mockResolvedValueOnce(new Response('<html>Server failure</html>',{status:500}))
    const {api}=await import('./api');await expect(api('/dashboard')).rejects.toMatchObject({status:500,message:expect.stringContaining('unexpected response')})
  })
  it('does not retry a second CSRF failure endlessly', async () => {
    fetchMock.mockImplementation(async(url:string)=>url==='/api/csrf'?json({token:'t'}):json({message:'invalid session'},419))
    const {api,send}=await import('./api');await expect(api('/save',send('POST',{}))).rejects.toMatchObject({status:419})
    expect(fetchMock).toHaveBeenCalledTimes(4)
  })
})

describe('Unreachable backend', () => {
  it('reports a proxy 500 with no body as an offline API, not a session failure', async () => {
    fetchMock.mockResolvedValue(new Response('', { status: 500 })) // what the Vite proxy returns when nothing listens on port 8000
    const {api,send}=await import('./api')
    await expect(api('/auth/login',send('POST',{}))).rejects.toMatchObject({status:500,unreachable:true,message:expect.stringContaining('Cannot reach the CMS API')})
    expect(fetchMock.mock.calls.map(c=>c[0])).toEqual(['/api/csrf']) // the credentials were never sent anywhere
  })
  it('reports an empty 5xx on a read as an offline API', async () => {
    fetchMock.mockResolvedValue(new Response('', { status: 502 }))
    const {api}=await import('./api');await expect(api('/auth/me')).rejects.toMatchObject({status:502,unreachable:true,message:expect.stringContaining('Cannot reach the CMS API')})
  })
  it('reports a refused connection as an offline API', async () => {
    fetchMock.mockRejectedValue(new TypeError('Failed to fetch'))
    const {api}=await import('./api');await expect(api('/dashboard')).rejects.toMatchObject({status:0,unreachable:true,message:expect.stringContaining('Cannot reach the CMS API')})
  })
  it('reports an HTML answer on the CSRF route as an offline API', async () => {
    fetchMock.mockResolvedValue(new Response('<!doctype html><title>Wrong server</title>', { status: 200, headers: { 'Content-Type': 'text/html' } }))
    const {api,send}=await import('./api');await expect(api('/save',send('POST',{}))).rejects.toMatchObject({unreachable:true})
  })
  it('still reports a session failure when the API answers with a JSON error', async () => {
    fetchMock.mockResolvedValue(json({message:'CSRF token mismatch'},419))
    const {api,send}=await import('./api');await expect(api('/save',send('POST',{}))).rejects.toMatchObject({status:419,unreachable:false,message:'Unable to establish a secure session.'})
  })
})
