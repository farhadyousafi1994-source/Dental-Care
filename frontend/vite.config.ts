import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { quasar, transformAssetUrls } from '@quasar/vite-plugin'
export default defineConfig({ plugins: [vue({ template: { transformAssetUrls } }), quasar()], build: {rollupOptions: {output: {manualChunks(id) {if (/src\/locales\/(fa|ps|ar)\.json$/.test(id)) return 'admin-translations'}}}}, server: { host: '0.0.0.0', allowedHosts: true, proxy: { '/api': 'http://127.0.0.1:8000', '/storage': 'http://127.0.0.1:8000' } } })
