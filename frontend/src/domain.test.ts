import { describe, expect, it } from 'vitest'
import { directionFor, themeStyle, convertPrice } from './domain'
describe('Locale direction', () => {
  it('uses native RTL for Dari, Pashto and Arabic variants', () => { for (const lang of ['fa', 'ps', 'ar', 'fa-AF', 'ar-SA']) expect(directionFor(lang)).toBe('rtl') })
  it('defaults other languages to LTR', () => expect(directionFor('en-GB')).toBe('ltr'))
})
describe('Appearance tokens', () => {
  it('respects explicit light mode even on dark devices', () => expect(themeStyle({mode:'light',background:'#ffffff'},true)['--site-bg']).toBe('#ffffff'))
  it('resolves system mode', () => expect(themeStyle({mode:'system'},true)['--site-bg']).toBe('#162720'))
  it('clamps the radius', () => expect(themeStyle({radius:900})['--site-radius']).toBe('40px'))
})
describe('Currency conversion helper (not a checkout)', () => {
  it('converts via the configured base currency', () => expect(convertPrice(100,1,70)).toBe(7000))
  it('converts between two non-base currencies', () => expect(convertPrice(7000,70,0.9)).toBe(90))
  it('rejects invalid exchange rates', () => expect(()=>convertPrice(10,0,1)).toThrow(RangeError))
})
