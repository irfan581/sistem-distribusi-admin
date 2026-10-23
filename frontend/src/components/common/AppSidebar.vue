<template>
  <aside class="sidebar" :class="{ collapsed: !open, 'mobile-open': mobileOpen, 'sidebar-enter': appStore.freshLogin }">

    <div class="sidebar-brand">
      <RouterLink to="/" class="brand-link" @click="appStore.closeMobileSidebar()">
        <div class="brand-icon">
          <picture>
            <source srcset="/logo-icon-96.webp" type="image/webp" />
            <img src="/logo-icon.png" alt="TMK" width="32" height="32" />
          </picture>
        </div>
        <Transition name="label">
          <div v-show="open" class="brand-text">
            <span class="brand-name">Tani Makmur</span>
            <span class="brand-env">Admin Panel</span>
          </div>
        </Transition>
      </RouterLink>

      <button
        v-if="!isMobile"
        class="collapse-btn"
        :class="{ 'collapse-btn-closed': !open }"
        @click="appStore.toggleSidebar()"
        :title="open ? 'Ciutkan' : 'Perluas'"
      >
        <span class="menu-lines" aria-hidden="true">
          <span /><span /><span />
        </span>
      </button>
    </div>

    <nav class="sidebar-nav">
      <SidebarNavSection label="Utama"       :items="mainNav" :open="open" :isActive="isActive" @close="appStore.closeMobileSidebar()" />
      <SidebarNavSection v-if="opsNav.length" label="Operasional" :items="opsNav"  :open="open" :isActive="isActive" @close="appStore.closeMobileSidebar()" />
      <SidebarNavSection v-if="pengirimanNav.length" label="Pengiriman & Armada" :items="pengirimanNav" :open="open" :isActive="isActive" @close="appStore.closeMobileSidebar()" />
      <SidebarNavSection v-if="sysNav.length" label="Sistem"      :items="sysNav"  :open="open" :isActive="isActive" @close="appStore.closeMobileSidebar()" />
    </nav>

    <div class="sidebar-footer">
      <RouterLink
        to="/settings"
        class="user-row"
        @click="appStore.closeMobileSidebar()"
        v-tooltip.right="!open ? 'Pengaturan' : null"
      >
        <div class="user-avatar">
          <img v-if="authStore.user?.avatar" :src="authStore.user.avatar" :alt="authStore.userInitials" class="user-avatar-img" />
          <span v-else>{{ authStore.userInitials }}</span>
        </div>
        <Transition name="label">
          <div v-show="open" class="user-info">
            <span class="user-name">{{ authStore.user?.name || 'Admin' }}</span>
          </div>
        </Transition>
        <Transition name="label">
          <i v-show="open" class="pi pi-cog user-cog" />
        </Transition>
      </RouterLink>
    </div>

  </aside>
</template>

<script setup>
import '@/assets/css/sidebar.css'
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute }              from 'vue-router'
import { useAppStore }           from '@/stores/app.store'
import { useAuthStore }          from '@/stores/auth.store'
import { useNotificationStore }  from '@/stores/notification.store'
import { useChatGlobalStore }    from '@/stores/chatGlobal.store'
import { useTanggapanStore }     from '@/stores/tanggapan.store'
import { usePermission }         from '@/composables/usePermission'
import { izinService }           from '@/services/izin.service'
import { HAK_SALES }             from '@/utils/permissions'
import SidebarNavSection         from '@/components/common/SidebarNavSection.vue'

const appStore          = useAppStore()
const authStore         = useAuthStore()
const notificationStore = useNotificationStore()
const chatGlobalStore   = useChatGlobalStore()
const tanggapanStore    = useTanggapanStore()
const { can, hasFullAccess } = usePermission()
const route             = useRoute()

const open        = computed(() => appStore.sidebarOpen)
const mobileOpen  = computed(() => appStore.sidebarMobileOpen)
const isMobile    = ref(window.innerWidth < 992)
const izinPending = ref(0)

const onResize = () => { isMobile.value = window.innerWidth < 992 }

onMounted(async () => {
  try {
    const r = await izinService.getAll({ status: 'pending', per_page: 1 })
    izinPending.value = r.data?.total || 0
  } catch { /* silent */ }
  tanggapanStore.muat()
  window.addEventListener('resize', onResize)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', onResize)
})

const mainNav = computed(() => {
  const all = [
    { to: '/',           label: 'Dashboard',  icon: 'pi pi-home' },
    { to: '/chat',       label: 'Chat',       icon: 'pi pi-comments', permission: HAK_SALES,
      badge: chatGlobalStore.chatUnread > 0 ? chatGlobalStore.chatUnread : null },
    { to: '/notifications', label: 'Notifikasi', icon: 'pi pi-bell', permission: HAK_SALES,
      badge: notificationStore.unreadCount > 0 ? notificationStore.unreadCount : null },
    { to: '/broadcast', label: 'Pengumuman', icon: 'pi pi-megaphone', permission: 'send_broadcast' },
  ]
  if (hasFullAccess()) return all
  return all.filter(item => !item.permission || can(item.permission))
})

const opsNav = computed(() => {
  const all = [
    { to: '/employees',         label: 'Karyawan',         icon: 'pi pi-users',          permission: 'view_sales' },
    { to: '/attendance',        label: 'Presensi',          icon: 'pi pi-calendar-clock', permission: 'view_presensi' },
    { to: '/attendance/report', label: 'Laporan',           icon: 'pi pi-chart-bar',      permission: 'view_laporan' },
    { to: '/izin',              label: 'Izin',              icon: 'pi pi-file-edit',      permission: 'view_izin',
      badge: izinPending.value > 0 ? izinPending.value : null },
    { to: '/izin/report',       label: 'Laporan Izin',     icon: 'pi pi-chart-pie',      permission: 'view_laporan' },
    { to: '/kunjungan',         label: 'Kunjungan Toko',    icon: 'pi pi-shop',           permission: 'view_kunjungan',
      badge: tanggapanStore.kunjungan > 0 ? tanggapanStore.kunjungan : null },
    { to: '/maps',              label: 'Peta Kunjungan',    icon: 'pi pi-map',            permission: 'view_maps_sales' },
    { to: '/visit-targets',     label: 'Target Kunjungan',  icon: 'pi pi-sliders-h',     permission: 'view_target_kunjungan' },
    { to: '/toko',              label: 'Data Toko',         icon: 'pi pi-building',       permission: 'view_toko' },
  ]
  if (hasFullAccess()) return all
  return all.filter(item => can(item.permission))
})

const pengirimanNav = computed(() => {
  const all = [
    // Urutannya mengikuti alur kerja harian admin, bukan abjad: bikin muatan,
    // tugaskan checker, lihat hasilnya, baru menganalisis. Data master ditaruh
    // paling bawah karena cuma disentuh saat setup, bukan tiap hari.
    { to: '/pengiriman/muat-subsidi',     label: 'Muat Subsidi',        icon: 'pi pi-truck',      permission: 'muat_subsidi' },
    { to: '/pengiriman/muat-non-subsidi', label: 'Muat Non Subsidi',    icon: 'pi pi-truck',      permission: 'muat_non_subsidi' },
    // Dulu bernama "Pengawasan Muat", padahal halamannya tidak memantau apa
    // pun — isinya menugaskan checker ke truk per tanggal muat. Nama lamanya
    // membuat orang yang mencari jadwal checker tidak menemukannya, dan yang
    // mengkliknya karena mengira bisa memantau malah dapat layar penjadwalan.
    { to: '/pengiriman/pengawasan',       label: 'Penugasan Checker',   icon: 'pi pi-eye',        permission: 'muat_non_subsidi' },
    // "Riwayat" berisi kiriman satu per satu, "Statistik" berisi angka
    // agregatnya. Dua-duanya rekap per periode, jadi urutannya dari yang
    // rinci ke yang ringkas.
    { to: '/pengiriman/riwayat',          label: 'Riwayat Pengiriman',  icon: 'pi pi-history',    permission: 'view_pengiriman',
      badge: tanggapanStore.laporan > 0 ? tanggapanStore.laporan : null },
    { to: '/pengiriman/statistik',        label: 'Statistik Pengiriman', icon: 'pi pi-chart-bar', permission: 'view_pengiriman' },
    // Driver, kendaraan, dan checker digabung satu menu bertab — tiga-tiganya
    // data master yang sama-sama dicek berurutan pas setup awal, bukan alur
    // kerja harian, jadi tidak perlu tiga baris sidebar sendiri-sendiri.
    // Namanya menyebut sopir juga karena separuh isinya orang, bukan kendaraan.
    { to: '/pengiriman/armada',           label: 'Armada & Sopir',      icon: 'pi pi-warehouse',  permission: 'view_armada' },
  ]
  // owner/developer selalu dapat semua menu — tidak bergantung pada personal permissions
  if (hasFullAccess()) return all
  return all.filter(item => can(item.permission))
})

const sysNav = computed(() => {
  const all = []
  if (hasFullAccess()) {
    all.push({ to: '/admin-users', label: 'Pengguna Admin', icon: 'pi pi-shield' })
  }
  const settingsItem = { to: '/settings', label: 'Pengaturan', icon: 'pi pi-cog', permission: 'manage_pengaturan' }
  if (hasFullAccess() || can(settingsItem.permission)) {
    all.push(settingsItem)
  }
  return all
})

function isActive(path) {
  if (path === '/') return route.path === '/'
  // exact match dulu, baru cek prefix — cegah /attendance aktif saat di /attendance/report
  if (route.path === path) return true
  // pastikan tidak ada item lain yang lebih spesifik yang cocok
  const otherItems = [...mainNav.value, ...opsNav.value, ...pengirimanNav.value, ...sysNav.value]
  const moreSpecific = otherItems.some(item =>
    item.to !== path &&
    item.to.startsWith(path + '/') &&
    route.path.startsWith(item.to)
  )
  if (moreSpecific) return false
  return route.path.startsWith(path + '/')
}
</script>
