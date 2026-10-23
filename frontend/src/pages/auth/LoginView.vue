<template>
  <div class="lp" :class="{ 'lp-dark': appStore.darkMode }">

    <LoginCanvas :caps="caps" />

    <main class="lp-panel">

      <!-- ══ MOBILE LAYOUT ══ -->
      <div class="lp-mob-hero">
        <button class="lp-theme" type="button" @click="appStore.toggleDarkMode()"
          :aria-label="appStore.darkMode ? 'Mode terang' : 'Mode gelap'">
          <i :class="appStore.darkMode ? 'pi pi-sun' : 'pi pi-moon'" />
        </button>
        <div class="lp-mob-brand">
          <picture>
            <source srcset="/logo-icon-96.webp" type="image/webp" />
            <img src="/logo-icon.png" alt="TMK" class="lp-mob-icon" width="48" height="48"
              loading="eager" fetchpriority="high" />
          </picture>
          <span class="lp-mob-name">Tani Makmur</span>
          <span class="lp-mob-tagline">Sales &amp; Management Platform</span>
        </div>
      </div>

      <!-- ══ DESKTOP: dark mode button ══ -->
      <button class="lp-theme lp-theme-desktop" type="button" @click="appStore.toggleDarkMode()"
        :aria-label="appStore.darkMode ? 'Mode terang' : 'Mode gelap'">
        <i :class="appStore.darkMode ? 'pi pi-sun' : 'pi pi-moon'" />
      </button>

      <!-- LoginCard — satu instance, tampil di mobile (dalam lp-mob-body) dan desktop (lp-panel-inner--desk) -->
      <!-- Mobile wrapper -->
      <div class="lp-mob-body">
        <div class="lp-panel-inner lp-panel-inner--mob">
          <LoginCard />
        </div>
        <footer class="lp-foot">
          <span>© {{ year }} Tani Makmur</span>
          <span class="lp-foot-sep">·</span>
          <span>Sistem Presensi Sales</span>
        </footer>
      </div>

      <!-- Desktop wrapper -->
      <div class="lp-panel-inner lp-panel-inner--desk">
        <LoginCard />
      </div>
      <footer class="lp-foot lp-foot--desk">
        <span>© {{ year }} Tani Makmur</span>
        <span class="lp-foot-sep">·</span>
        <span>Sistem Presensi Sales</span>
      </footer>

    </main>

  </div>
</template>

<script setup>
import '@/assets/css/login.css'
import { computed, onMounted } from 'vue'
import { useAppStore }  from '@/stores/app.store'
import LoginCanvas from '@/components/auth/LoginCanvas.vue'
import LoginCard   from '@/components/auth/LoginCard.vue'

const appStore = useAppStore()
const year     = computed(() => new Date().getFullYear())

onMounted(() => { appStore.initTheme() })

const caps = [
  { icon: 'pi pi-chart-bar',  label: 'Realtime Dashboard',   desc: 'Pantau KPI dan presensi secara langsung' },
  { icon: 'pi pi-map-marker', label: 'GPS Tracking',          desc: 'Rekam kunjungan dan rute tim sales' },
  { icon: 'pi pi-comments',   label: 'Pesan Instan',          desc: 'Komunikasi lapangan ke kantor' },
  { icon: 'pi pi-shield',     label: 'Akses Berbasis Peran',  desc: 'Kontrol penuh dengan audit trail' },
]
</script>
