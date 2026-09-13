import {t,appLocale} from './i18n'
let csrf: string | null = null
let tokenRequest: Promise<string> | null = null

export class ApiError extends Error {
  constructor(message: string, public status: number, public errors: Record<string, string[]> = {}) { super(message) }
}

async function token(): Promise<string> {
  if (csrf) return csrf
  if (!tokenRequest) tokenRequest = fetch('/api/csrf', { credentials: 'include', headers: { Accept: 'application/json' } })
    .then(async response => { if (!response.ok) throw new ApiError('Unable to establish a secure session.', response.status); return (await response.json()).token as string })
    .then(value => { csrf = value; return value }).finally(() => { tokenRequest = null })
  return tokenRequest
}

export async function api<T = any>(path: string, options: RequestInit = {}, retryCsrf = true): Promise<T> {
  const mutation = options.method && !['GET', 'HEAD'].includes(options.method)
  const response = await fetch('/api' + path, {
    credentials: 'include', ...options,
    headers: { Accept: 'application/json', 'Accept-Language':appLocale.value, ...(mutation ? { 'X-CSRF-TOKEN': await token() } : {}),
      ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }), ...options.headers },
  })
  if (response.status === 419 && retryCsrf) { csrf = null; return api(path, options, false) }
  if (path.startsWith('/auth/') || response.status === 401) csrf = null
  const result = response.status === 204 ? null : await response.json().catch(() => ({ message: 'The server returned an unexpected response. Check the backend connection.' }))
  if (!response.ok) {const raw=result?.message||'Unable to complete this request.';const translated=t(raw);const fallback:Record<number,string>={401:'Please sign in to continue.',403:'You do not have permission to perform this action.',409:'This record changed. Reload before saving.',422:'Check the form fields and try again.',429:'Too many requests. Try again later.'};throw new ApiError(appLocale.value!=='en'&&translated===raw&&fallback[response.status]?t(fallback[response.status]):translated,response.status,result?.errors||{})}
  return result
}
export const send = (method: string, body: unknown): RequestInit => ({ method, body: JSON.stringify(body) })
