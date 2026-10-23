import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'
import { usePermission } from '@/composables/usePermission'
import { HAK_SALES } from '@/utils/permissions'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/pages/auth/LoginView.vue'),
    meta: { guest: true },
  },
  {
    path: '/',
    component: () => import('@/layouts/AdminLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'dashboard',
        component: () => import('@/pages/dashboard/DashboardView.vue'),
        meta: { title: 'Dashboard' },
      },
      {
        path: 'employees',
        name: 'employees',
        component: () => import('@/pages/employees/EmployeesView.vue'),
        meta: { title: 'Karyawan', permission: 'view_sales' },
      },
      {
        path: 'employees/:id',
        name: 'employee-detail',
        component: () => import('@/pages/employees/EmployeeDetailView.vue'),
        meta: { title: 'Detail Karyawan', permission: 'view_sales' },
      },
      {
        path: 'attendance',
        name: 'attendance',
        component: () => import('@/pages/attendance/AttendanceView.vue'),
        meta: { title: 'Presensi', permission: 'view_presensi' },
      },
      {
        path: 'attendance/report',
        name: 'attendance-report',
        component: () => import('@/pages/attendance/AttendanceReportView.vue'),
        meta: { title: 'Laporan Presensi', permission: 'view_laporan' },
      },
      {
        path: 'attendance/:id',
        name: 'attendance-detail',
        component: () => import('@/pages/attendance/AttendanceDetailView.vue'),
        meta: { title: 'Detail Presensi', permission: 'view_presensi' },
      },
      {
        path: 'kunjungan',
        name: 'kunjungan',
        component: () => import('@/pages/kunjungan/KunjunganView.vue'),
        meta: { title: 'Kunjungan Toko', permission: 'view_kunjungan' },
      },
      {
        path: 'kunjungan/tambah',
        name: 'kunjungan-tambah',
        component: () => import('@/pages/kunjungan/KunjunganTambahView.vue'),
        meta: { title: 'Tambah Kunjungan Manual' },
      },
      {
        path: 'kunjungan/:id',
        name: 'kunjungan-detail',
        component: () => import('@/pages/kunjungan/KunjunganDetailView.vue'),
        meta: { title: 'Detail Kunjungan', permission: 'view_kunjungan' },
      },
      {
        path: 'maps',
        name: 'maps',
        component: () => import('@/pages/maps/MapsView.vue'),
        meta: { title: 'Peta Kunjungan', permission: 'view_maps_sales' },
      },
      {
        path: 'izin',
        name: 'izin',
        component: () => import('@/pages/izin/IzinView.vue'),
        meta: { title: 'Izin Karyawan', permission: 'view_izin' },
      },
      {
        path: 'izin/report',
        name: 'izin-report',
        component: () => import('@/pages/izin/IzinReportView.vue'),
        meta: { title: 'Laporan Izin', permission: 'view_laporan' },
      },
      {
        path: 'chat',
        name: 'chat',
        component: () => import('@/pages/chat/ChatView.vue'),
        meta: { title: 'Chat', permission: HAK_SALES },
      },
      {
        path: 'notifications',
        name: 'notifications',
        component: () => import('@/pages/NotificationsView.vue'),
        meta: { title: 'Notifikasi', permission: HAK_SALES },
      },
      {
        path: 'broadcast',
        name: 'broadcast',
        component: () => import('@/pages/BroadcastView.vue'),
        meta: { title: 'Pengumuman', permission: 'send_broadcast' },
      },
      {
        path: 'visit-targets',
        name: 'visit-targets',
        component: () => import('@/pages/visitTargets/VisitTargetView.vue'),
        meta: { title: 'Target Kunjungan', permission: 'view_target_kunjungan' },
      },
      {
        path: 'toko',
        name: 'toko',
        component: () => import('@/pages/toko/TokoView.vue'),
        meta: { title: 'Data Toko', permission: 'view_toko' },
      },
      {
        path: 'pengiriman/statistik',
        name: 'statistik-pengiriman',
        component: () => import('@/pages/pengiriman/StatistikPengirimanView.vue'),
        meta: { title: 'Statistik Pengiriman', permission: 'view_pengiriman' },
      },
      {
        path: 'pengiriman/muat-subsidi',
        name: 'muat-subsidi',
        component: () => import('@/pages/pengiriman/DaftarMuatView.vue'),
        props: { mode: 'subsidi' },
        meta: { title: 'Muat Subsidi', permission: 'muat_subsidi' },
      },
      {
        path: 'pengiriman/muat-non-subsidi',
        name: 'muat-non-subsidi',
        component: () => import('@/pages/pengiriman/DaftarMuatView.vue'),
        props: { mode: 'non_subsidi' },
        meta: { title: 'Muat Non Subsidi', permission: 'muat_non_subsidi' },
      },
      {
        path: 'pengiriman/daftar-muat',
        redirect: { name: 'muat-non-subsidi' },
      },
      {
        path: 'pengiriman/armada',
        name: 'master-armada',
        component: () => import('@/pages/pengiriman/MasterArmadaView.vue'),
        meta: { title: 'Armada & Sopir', permission: 'view_armada' },
      },
      // Driver, kendaraan, dan checker dulu tiga halaman/menu terpisah,
      // sekarang jadi tab di dalam satu halaman (lihat MasterArmadaView).
      // Redirect dipertahankan supaya bookmark/link lama staf lain tidak 404.
      { path: 'pengiriman/driver', redirect: { name: 'master-armada', query: { tab: 'driver' } } },
      { path: 'pengiriman/kendaraan', redirect: { name: 'master-armada', query: { tab: 'kendaraan' } } },
      { path: 'pengiriman/checker', redirect: { name: 'master-armada', query: { tab: 'checker' } } },
      {
        path: 'pengiriman/riwayat',
        name: 'riwayat-kiriman',
        component: () => import('@/pages/pengiriman/RiwayatKirimanView.vue'),
        meta: { title: 'Riwayat Pengiriman', permission: 'view_pengiriman' },
      },
      {
        path: 'pengiriman/pengawasan',
        name: 'pengawasan-muat',
        component: () => import('@/pages/pengiriman/PengawasanMuatView.vue'),
        meta: { title: 'Penugasan Checker', permission: 'muat_non_subsidi' },
      },
      {
        path: 'admin-users',
        name: 'admin-users',
        component: () => import('@/pages/adminUsers/AdminUsersView.vue'),
        meta: { title: 'Pengguna Admin', requiresFullAccess: true },
      },
      {
        path: 'settings',
        name: 'settings',
        component: () => import('@/pages/settings/SettingsView.vue'),
        meta: { title: 'Pengaturan', permission: 'manage_pengaturan' },
      },
    ],
  },
  {
    path: '/403',
    name: '403',
    component: () => import('@/pages/errors/ForbiddenView.vue'),
  },
  {
    path: '/:pathMatch(.*)*',
    name: '404',
    component: () => import('@/pages/errors/NotFoundView.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition
    return { top: 0 }
  },
})

router.beforeEach(async (to, from) => {
  const authStore = useAuthStore()

  document.title = to.meta.title
    ? `${to.meta.title} | ${import.meta.env.VITE_APP_NAME}`
    : import.meta.env.VITE_APP_NAME

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guest && authStore.isAuthenticated) {
    return { name: 'dashboard' }
  }

  // Cek permission — pastikan user data sudah resolved (ada role_nama)
  if ((to.meta.permission || to.meta.requiresFullAccess) && authStore.isAuthenticated) {
    await authStore.ensureResolved()
    const { can, hasFullAccess } = usePermission()
    if (to.meta.requiresFullAccess && !hasFullAccess()) {
      return { name: '403' }
    }
    // hasFullAccess() ikut diperiksa di sini, bukan hanya di sidebar. Sidebar
    // memberi owner/developer seluruh menu tanpa melihat permission pribadinya
    // (AppSidebar: `if (hasFullAccess()) return all`), sedangkan penjaga ini
    // dulu hanya melihat permission — jadi menunya tampil, diklik, lalu
    // dilempar ke 403. Dari sisi pemakai itu terbaca sebagai menu yang tidak
    // bisa diklik.
    if (to.meta.permission && !hasFullAccess() && !can(to.meta.permission)) {
      return { name: '403' }
    }
  }
})

export default router
