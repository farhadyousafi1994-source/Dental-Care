export function directionFor(language: string): 'ltr' | 'rtl' {
  return ['fa', 'ps', 'ar'].includes(language.split('-')[0]) ? 'rtl' : 'ltr'
}
export function themeStyle(tokens: Record<string, any>, systemDark = false): Record<string, string> {
  const dark = tokens.mode === 'dark' || (tokens.mode === 'system' && systemDark)
  return {
    '--site-primary': tokens.primary || '#286b54',
    '--site-bg': dark ? '#162720' : tokens.background || '#faf9f6',
    '--site-text': dark ? '#f2f4f0' : tokens.text || '#263d35',
    '--site-heading': dark ? '#f2f4f0' : tokens.heading || tokens.text || '#263d35',
    '--site-surface': dark ? '#21352b' : tokens.surface || '#ffffff',
    '--site-heading-font': tokens.headingFont === 'Inter' ? 'Inter Variable, Arial, sans-serif' : tokens.headingFont || 'Georgia, serif',
    '--site-font-size': `${tokens.fontSize || 14}px`,
    '--site-line-height': String(tokens.lineHeight || 1.7),
    '--site-spacing': `${tokens.spacing || 65}px`,
    '--site-shadow': tokens.shadow === 'raised' ? '0 12px 30px #10281722' : tokens.shadow === 'soft' ? '0 4px 14px #10281712' : 'none',
    '--site-radius': `${Math.max(0, Math.min(40, Number(tokens.radius) || 0))}px`,
    fontFamily: tokens.font === 'Inter' ? 'Inter Variable, Arial, sans-serif' : tokens.font || 'Arial, sans-serif',
  }
}
export function convertPrice(amount: number, fromRate: number, toRate: number): number {
  if (![amount, fromRate, toRate].every(Number.isFinite) || fromRate <= 0 || toRate <= 0) throw new RangeError('Prices require finite amounts and positive exchange rates.')
  return amount / fromRate * toRate
}
