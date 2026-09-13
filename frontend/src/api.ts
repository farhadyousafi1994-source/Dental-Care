import {t,appLocale} from './i18n'
let csrf: string | null = null
let tokenRequest: Promise<string> | null = null

export const BACKEND_UNREACHABLE = 'Cannot reach the CMS API. Start the backend on port 8000, then reload this page.'

export class ApiError extends Error {
  constructor(message: string, public status: number, public errors: Record<string, string[]> = {}, public unreachable = false) { super(message) }
}

const unreachable = (status: number) => new ApiError(BACKEND_UNREACHABLE, status, {}, true)

async function token(): Promise<string> {
  if (csrf) return csrf
  if (!tokenRequest) tokenRequest = fetch('/api/csrf', { credentials: 'include', headers: { Accept: 'application/json' } })
    .then(async response => {
      const body = await response.json().catch(() => null)
      const value = body?.token
      if (response.ok && typeof value === 'string' && value) return value
      // A stopped backend makes the dev proxy answer 5xx with an empty body, and a wrong
      // proxy target answers with HTML. Only a JSON message means the API itself refused
      // the session; everything else is "the API is not there", not a session problem.
      throw body?.message ? new ApiError('Unable to establish a secure session.', response.status) : unreachable(response.status)
    })
    .then(value => { csrf = value; return value }).finally(() => { tokenRequest = null })
  return tokenRequest
}

export async function api<T = any>(path: string, options: RequestInit = {}, retryCsrf = true): Promise<T> {
  const mutation = options.method && !['GET', 'HEAD'].includes(options.method)
  const response = await fetch('/api' + path, {
    credentials: 'include', ...options,
    headers: { Accept: 'application/json', 'Accept-Language':appLocale.value, ...(mutation ? { 'X-CSRF-TOKEN': await token() } : {}),
      ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }), ...options.headers },
  }).catch((error: unknown) => { if (error instanceof ApiError) throw error; throw unreachable(0) }) // fetch rejects when the API host is down.
  if (response.status === 419 && retryCsrf) { csrf = null; return api(path, options, false) }
  if (path.startsWith('/auth/') || response.status === 401) csrf = null
  const text = response.status === 204 ? '' : await response.text().catch(() => '')
  let parsed: any = null
  try { parsed = text ? JSON.parse(text) : null } catch { parsed = null }
  // An empty 5xx body means no API process answered at all — the dev proxy and reverse
  // gateways fail like this — so report the offline backend rather than a broken payload.
  if (!response.ok && response.status >= 500 && !text) throw unreachable(response.status)
  const result = response.status === 204 ? null : parsed ?? { message: 'The server returned an unexpected response. Check the backend connection.' }
  if (!response.ok) {const raw=result?.message||'Unable to complete this request.';const translated=t(raw);const fallback:Record<number,string>={401:'Please sign in to continue.',403:'You do not have permission to perform this action.',409:'This record changed. Reload before saving.',422:'Check the form fields and try again.',429:'Too many requests. Try again later.'};throw new ApiError(appLocale.value!=='en'&&translated===raw&&fallback[response.status]?t(fallback[response.status]):translated,response.status,result?.errors||{})}
  return result
}
export const send = (method: string, body: unknown): RequestInit => ({ method, body: JSON.stringify(body) })
