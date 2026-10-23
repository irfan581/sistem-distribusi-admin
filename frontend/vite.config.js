import { fileURLToPath, URL } from 'node:url'
import { defineConfig }       from 'vite'
import vue                    from '@vitejs/plugin-vue'
import compression            from 'vite-plugin-compression'

// Plugin: hapus modulepreload chunk berat agar tidak bersaing bandwidth
// dengan CSS render-blocking dan aset kritis (logo, font) di halaman awal.
// vendor.js sengaja tidak dipreload — download setelah CSS & logo selesai.
function removeHeavyModulePreload() {
  return {
    name: 'remove-heavy-modulepreload',
    transformIndexHtml(html) {
      return html.replace(
        /<link rel="modulepreload"[^>]*href="\/assets\/(primevue-components|vendor|maps|firebase|realtime|chartjs|emoji|ChatView|DashboardView|VisitTarget|BroadcastView|AdminLayout|SettingsView|IzinReport|EmployeeDetail)[^"]*"[^>]*>\n?/g,
        ''
      )
    },
  }
}

export default defineConfig({
  plugins: [
    vue(),
    compression({ algorithm: 'gzip',          ext: '.gz' }),
    compression({ algorithm: 'brotliCompress', ext: '.br' }),
    removeHeavyModulePreload(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
  },
  build: {
    outDir:               'dist',
    sourcemap:            false,
    cssCodeSplit:         true,
    chunkSizeWarningLimit: 800,
    rollupOptions: {
      output: {
        // Tanpa ini chunk vendor dan http saling impor; helper belum siap → "e is not a function", layar blank
        strictExecutionOrder: true,
        // Font tidak di-hash agar URL di CSS tetap cocok dengan nama file
        assetFileNames: (assetInfo) => {
          const ext = assetInfo.name?.split('.').pop() || ''
          if (['woff', 'woff2', 'ttf', 'eot', 'svg'].includes(ext)) {
            return 'assets/[name][extname]'
          }
          return 'assets/[name]-[hash][extname]'
        },
        manualChunks(id) {
          // Hanya node_modules yang boleh masuk chunk terpisah
          if (!id.includes('node_modules')) return

          // PrimeVue dipecah dua:
          // - primevue-core: @primevue/core, @primevue/themes, @primeuix/* (shared runtime)
          // - primevue-essential: komponen yang diimport statik di main.js (Button, Toast, Services dll)
          // Komponen berat (DataTable, Dialog, dll) tidak di-assign ke chunk manual —
          // karena pakai defineAsyncComponent di main.js, Rollup akan lazy-load mereka
          // bersama route chunk yang memakainya (tidak load di login page)
          if (id.includes('@primevue/themes') || id.includes('@primevue/core') || id.includes('@primeuix')) return 'primevue-core'
          const essentials = ['primevue/button','primevue/inputtext','primevue/password',
            'primevue/checkbox','primevue/toast','primevue/confirmdialog','primevue/confirmpopup',
            'primevue/message','primevue/tooltip',
            // Services & deps (statically imported in main.js)
            'primevue/toastservice','primevue/toasteventbus','primevue/usetoast',
            'primevue/confirmationservice','primevue/confirmationeventbus','primevue/useconfirm',
            'primevue/dialogservice','primevue/dynamicdialogeventbus','primevue/usedialog']
          if (essentials.some(e => id.includes(e))) return 'primevue-essential'
          // Komponen PrimeVue berat (DataTable, Dialog, Select, dll) → return undefined
          // agar Rollup handle natural via dynamic imports (defineAsyncComponent)
          if (id.includes('primevue') || id.includes('@primevue')) return undefined

          // Chunk fitur berat — hanya load saat halaman terkait dibuka
          if (id.includes('chart.js') || id.includes('chartjs'))      return 'chartjs'
          if (id.includes('leaflet')  || id.includes('@mapbox'))      return 'maps'
          if (id.includes('emoji'))                                    return 'emoji'
          if (id.includes('firebase'))                                 return 'firebase'
          if (id.includes('pusher')   || id.includes('laravel-echo')) return 'realtime'

          // Semua node_modules lain (axios, pinia, vue-router, dll) → vendor
          return 'vendor'
        },
      },
    },
  },
})
