import{describe,it,expect,afterEach}from'vitest'
import{t,appLocale,translatedOptions}from'./i18n'
import en from'./locales/en.json'
import fa from'./locales/fa.json'
import ps from'./locales/ps.json'
import ar from'./locales/ar.json'
afterEach(()=>appLocale.value='en')
describe('Admin translations',()=>{
 it('has translations for every catalogued interface message in all three RTL languages',()=>{for(const catalog of[fa,ps,ar])for(const key of Object.keys(en))expect((catalog as Record<string,string>)[key],key).toBeTruthy()})
 it('switches languages reactively without translating user data or enum values',()=>{appLocale.value='fa';expect(t('Products')).toBe('محصولات');expect(t('My custom website')).toBe('My custom website');expect(translatedOptions(['draft'])[0]).toEqual({label:'پیش‌نویس',value:'draft'});appLocale.value='ps';expect(t('Orders')).toBe('سپارښتنې');appLocale.value='ar';expect(t('Save product')).toBe('حفظ المنتج')})
 it('preserves interpolated product names in translated messages',()=>{appLocale.value='ar';expect(t('Insufficient stock for Care kit')).toBe('المخزون غير كافٍ للمنتج Care kit')})
})
