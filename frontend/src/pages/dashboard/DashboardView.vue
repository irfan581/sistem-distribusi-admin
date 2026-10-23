<template>
  <div class="dash">

    <div class="dash-header">
      <div class="dh-left">
        <div class="dh-logo-wrap">
          <i class="pi pi-building" style="font-size:1.2rem;color:#fff" />
        </div>
        <div class="dh-text">
          <div class="dh-greeting">
            <span class="dh-hi">{{ greeting }},</span>
            <span class="dh-name">{{ authStore.user?.name || 'Admin' }}</span>
            <span class="dh-wave">👋</span>
          </div>
          <p class="dh-date">{{ todayLabel }}</p>
        </div>
      </div>
      <div class="dh-right">
        <div class="dh-ws-status">
          <span class="ws-dot" />
          <span class="ws-label">Sistem aktif</span>
        </div>
      </div>
    </div>

    <DashKpiStrip v-if="kpiItems.length" :kpiItems="kpiItems" :loading="statsLoading" />

    <div class="bento" :class="{ 'bento--pengiriman-only': pengirimanOnly }">

      <!-- Chart Kehadiran (kiri, lebar) -->
      <div v-if="can('view_presensi')" class="bento-cell bento-chart">
        <div class="cell-head">
          <div class="cell-title-group">
            <span class="cell-title">Tren Kehadiran</span>
            <span class="cell-sub">30 hari terakhir</span>
          </div>
          <div class="chart-legend">
            <span class="leg"><span class="led" style="background:#22c55e" />Hadir</span>
            <span class="leg"><span class="led" style="background:#f59e0b" />Terlambat</span>
          </div>
        </div>
        <div class="cell-body">
          <div v-if="chartLoading" class="chart-ph">
            <div class="skeleton" style="height:220px;border-radius:10px" />
          </div>
          <AttendanceTrendChart v-else :data="chartData" />
        </div>
      </div>

      <!-- Chart Kunjungan (tengah) -->
      <DashVisitTrendChart v-if="can('view_kunjungan')" :data="chartData" :loading="chartLoading" />

      <!-- Donut (kanan) -->
      <div v-if="can('view_presensi')" class="bento-cell bento-donut">
        <div class="cell-head">
          <div class="cell-title-group">
            <span class="cell-title">Distribusi Hari Ini</span>
            <span class="cell-sub">Status kehadiran</span>
          </div>
        </div>
        <div class="cell-body center-body">
          <div v-if="statsLoading" class="skeleton" style="height:200px;border-radius:50%;width:200px;margin:auto" />
          <AttendancePieChart v-else
            :present="stats.hadir_hari_ini"
            :late="stats.terlambat_hari_ini"
            :absent="stats.tidak_hadir_hari_ini"
            :permit="stats.izin_hari_ini"
          />
        </div>
      </div>

      <DashActivityCell v-if="hasFullAccess()" :activities="activities" :loading="activitiesLoading" />

      <DashVisitChart
        v-if="can('view_kunjungan')"
        :items="visitChartData"
        :loading="visitChartLoading"
        :currentMonth="currentMonth"
      />

      <DashIzinCell
        v-if="can('view_izin')"
        :izinPending="izinPending"
        :izinPendingCount="stats.izin_pending"
        :loading="izinLoading"
        @approve="quickApprove"
        @reject="quickReject"
      />

      <DashQuickCell :quickLinks="quickLinks" />

      <DashMonthlyCell v-if="can('view_presensi')" :stats="stats" :loading="statsLoading" :currentMonth="currentMonth" />

      <DashPengirimanCell v-if="can('view_pengiriman')" :stats="pengirimanStats" :loading="pengirimanLoading" />

      <DashMuatanChartCell v-if="can('view_pengiriman')" :data="muatanTrendData" :loading="muatanTrendLoading" />

    </div>
  </div>
</template>

<script setup>
import '@/assets/css/dashboard.css'
import { ref, reactive, computed, onMounted } from 'vue'
import { usePullRefresh } from '@/composables/usePullRefresh'
import { usePermission }       from '@/composables/usePermission'
import { HAK_SALES }           from '@/utils/permissions'
import { dashboardService }    from '@/services/dashboard.service'
import { izinService }         from '@/services/izin.service'
import { useToast }            from '@/composables/useToast'
import { useAuthStore }        from '@/stores/auth.store'
import AttendanceTrendChart    from '@/components/charts/AttendanceTrendChart.vue'
import AttendancePieChart      from '@/components/charts/AttendancePieChart.vue'
import DashKpiStrip            from '@/components/dashboard/DashKpiStrip.vue'
import DashActivityCell        from '@/components/dashboard/DashActivityCell.vue'
import DashIzinCell            from '@/components/dashboard/DashIzinCell.vue'
import DashQuickCell           from '@/components/dashboard/DashQuickCell.vue'
import DashMonthlyCell         from '@/components/dashboard/DashMonthlyCell.vue'
import DashVisitChart          from '@/components/dashboard/DashVisitChart.vue'
import DashVisitTrendChart     from '@/components/dashboard/DashVisitTrendChart.vue'
import DashPengirimanCell      from '@/components/dashboard/DashPengirimanCell.vue'
import DashMuatanChartCell     from '@/components/dashboard/DashMuatanChartCell.vue'

const toast     = useToast()
const authStore = useAuthStore()
const { can, hasFullAccess } = usePermission()

const pengirimanOnly = computed(() =>
  can('view_pengiriman') && !can('view_presensi') && !can('view_sales') && !can('view_kunjungan')
)

const statsLoading        = ref(true)
const chartLoading        = ref(true)
const activitiesLoading   = ref(true)
const izinLoading         = ref(true)
const visitChartLoading   = ref(true)
const visitChartData      = ref([])
const pengirimanLoading   = ref(true)
const pengirimanStats     = ref({})
const muatanTrendLoading  = ref(true)
const muatanTrendData     = ref([])

const stats = reactive({
  total_sales_aktif: 0, hadir_hari_ini: 0, terlambat_hari_ini: 0,
  tidak_hadir_hari_ini: 0, izin_hari_ini: 0, kunjungan_hari_ini: 0,
  izin_pending: 0, total_absen_bulan_ini: 0,
})
const chartData   = ref({})
const activities  = ref([])
const izinPending = ref([])

const now  = new Date()
const hour = now.getHours()

const greeting = computed(() =>
  hour < 11 ? 'Selamat pagi' : hour < 15 ? 'Selamat siang' : hour < 18 ? 'Selamat sore' : 'Selamat malam'
)
const todayLabel = computed(() =>
  now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
)
const currentMonth = computed(() =>
  now.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' })
)

// Strip ini khusus angka HARI INI. Data bulanan ada di widget
// "Ringkasan Bulan Ini" — mencampur keduanya di satu baris kartu
// membuat angka besar seperti total absen sebulan terbaca seolah
// kejadian hari ini.
const kpiItems = computed(() => [
  can('view_sales')    && { label: 'Sales Aktif',     value: stats.total_sales_aktif,    icon: 'pi pi-users',        color: 'brand' },
  can('view_presensi') && { label: 'Hadir',           value: stats.hadir_hari_ini,       icon: 'pi pi-check-circle', color: 'success' },
  can('view_presensi') && { label: 'Terlambat',       value: stats.terlambat_hari_ini,   icon: 'pi pi-clock',        color: 'warning' },
  can('view_presensi') && { label: 'Tidak Hadir',     value: stats.tidak_hadir_hari_ini, icon: 'pi pi-times-circle', color: 'danger' },
  can('view_izin')     && { label: 'Izin Hari Ini',   value: stats.izin_hari_ini,        icon: 'pi pi-file-edit',    color: 'info' },
  can('view_kunjungan')&& { label: 'Kunjungan',       value: stats.kunjungan_hari_ini,   icon: 'pi pi-map-marker',   color: 'brand' },
  can('view_izin')     && { label: 'Izin Pending',    value: stats.izin_pending,         icon: 'pi pi-hourglass',    color: 'warning' },
].filter(Boolean))

// Admin pengiriman hanya punya satu tautan dari daftar umum, jadi diberi pintasan ke menu kerjanya sendiri.
const pengirimanLinks = [
  { to: '/pengiriman/muat-subsidi',     label: 'Muat Subsidi',     icon: 'pi pi-truck',     color: 'brand',   permission: 'muat_subsidi' },
  { to: '/pengiriman/muat-non-subsidi', label: 'Muat Non Subsidi', icon: 'pi pi-truck',     color: 'warning', permission: 'muat_non_subsidi' },
  { to: '/pengiriman/pengawasan',       label: 'Checker',          icon: 'pi pi-eye',       color: 'info',    permission: 'muat_non_subsidi' },
  { to: '/pengiriman/riwayat',          label: 'Riwayat',          icon: 'pi pi-history',   color: 'success', permission: 'view_pengiriman' },
  { to: '/pengiriman/statistik',        label: 'Statistik',        icon: 'pi pi-chart-bar', color: 'info',    permission: 'view_pengiriman' },
  { to: '/pengiriman/armada',           label: 'Armada & Sopir',   icon: 'pi pi-warehouse', color: 'brand',   permission: 'view_armada' },
]

const quickLinks = computed(() => pengirimanOnly.value ? pengirimanLinks.filter(q => can(q.permission)) : [
  { to: '/employees',         label: 'Karyawan',   icon: 'pi pi-users',          color: 'brand',   permission: 'view_sales' },
  { to: '/attendance',        label: 'Presensi',   icon: 'pi pi-calendar-clock', color: 'success', permission: 'view_presensi' },
  { to: '/izin',              label: 'Izin',       icon: 'pi pi-file-edit',      color: 'warning', permission: 'view_izin' },
  { to: '/maps',              label: 'Peta',       icon: 'pi pi-map',            color: 'info',    permission: 'view_maps_sales' },
  { to: '/pengiriman/riwayat',label: 'Riwayat',    icon: 'pi pi-history',        color: 'success', permission: 'view_pengiriman' },
  { to: '/chat',              label: 'Chat',       icon: 'pi pi-comments',       color: 'brand',   permission: HAK_SALES },
  { to: '/notifications',     label: 'Notifikasi', icon: 'pi pi-bell',           color: 'warning', permission: HAK_SALES },
].filter(q => !q.permission || can(q.permission)))

async function fetchStats() {
  statsLoading.value = true
  try { const r = await dashboardService.getStats(); Object.assign(stats, r.data) }
  catch (e) { toast.apiError(e) }
  finally { statsLoading.value = false }
}

async function fetchChart(totalSalesAktif = 0) {
  chartLoading.value = true
  try {
    const r = await dashboardService.getAttendanceChart(30)
    const rows = r.data || []
    const total = totalSalesAktif || stats.total_sales_aktif || 0
    chartData.value = {
      labels:  rows.map(r => new Date(r.tanggal).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' })),
      present: rows.map(r => parseInt(r.tepat_waktu) || 0),
      late:    rows.map(r => parseInt(r.terlambat) || 0),
      // Per hari, pembandingnya cukup jumlah sales aktif. Mode bulanan
      // memakai hari_kerja × sales — field itu tidak ada di mode harian.
      absent:  rows.map(r => Math.max(0, total - (parseInt(r.total) || 0))),
      visits:  rows.map(r => parseInt(r.total_kunjungan) || 0),
    }
  } catch { /* silent */ }
  finally { chartLoading.value = false }
}

async function fetchVisitChart() {
  visitChartLoading.value = true
  try {
    const r = await dashboardService.getVisitChart()
    visitChartData.value = r.data || []
  } catch { /* silent */ }
  finally { visitChartLoading.value = false }
}

async function fetchActivities() {
  activitiesLoading.value = true
  try { const r = await dashboardService.getRecentActivity(8); activities.value = r.data || [] }
  catch { /* silent */ }
  finally { activitiesLoading.value = false }
}

async function fetchIzin() {
  izinLoading.value = true
  try {
    const r = await izinService.getAll({ status: 'pending', per_page: 6 })
    izinPending.value = (r.data?.data || r.data || []).map(i => ({ ...i, _loading: false }))
  } catch { /* silent */ }
  finally { izinLoading.value = false }
}

async function quickApprove(id) {
  const izin = izinPending.value.find(i => i.id === id)
  if (!izin) return
  izin._loading = true
  try {
    await izinService.approve(id, 'approved')
    toast.success('Izin disetujui')
    izinPending.value = izinPending.value.filter(i => i.id !== id)
    stats.izin_pending = Math.max(0, stats.izin_pending - 1)
  } catch (e) { toast.apiError(e); izin._loading = false }
}

async function quickReject(id) {
  const izin = izinPending.value.find(i => i.id === id)
  if (!izin) return
  izin._loading = true
  try {
    await izinService.approve(id, 'rejected', 'Ditolak admin')
    toast.warn('Izin ditolak')
    izinPending.value = izinPending.value.filter(i => i.id !== id)
    stats.izin_pending = Math.max(0, stats.izin_pending - 1)
  } catch (e) { toast.apiError(e); izin._loading = false }
}

async function fetchPengiriman() {
  pengirimanLoading.value = true
  try { const r = await dashboardService.getPengirimanStats(); pengirimanStats.value = r.data || {} }
  catch { /* silent */ }
  finally { pengirimanLoading.value = false }
}

async function fetchMuatanTrend() {
  muatanTrendLoading.value = true
  try { const r = await dashboardService.getMuatanTrend(30); muatanTrendData.value = r.data || [] }
  catch { /* silent */ }
  finally { muatanTrendLoading.value = false }
}

async function reloadAll() {
  await fetchStats()
  fetchChart(stats.total_sales_aktif)
  fetchActivities()
  fetchIzin()
  fetchVisitChart()
  fetchPengiriman()
  fetchMuatanTrend()
}

onMounted(reloadAll)
usePullRefresh(reloadAll)
</script>
