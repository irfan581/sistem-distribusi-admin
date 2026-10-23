<template>
  <div class="maps-view">
    <PageHeader title="Peta Kunjungan Sales" subtitle="Visualisasi kunjungan dan presensi sales per hari.">
      <template #actions>
        <DatePicker
          v-model="filterDate"
          placeholder="Pilih tanggal"
          show-icon
          date-format="yy-mm-dd"
          @date-select="loadData"
          showClear
          @clear-click="loadData"
          style="width: 200px"
        />
        <Button icon="pi pi-refresh" text plain @click="loadData" :loading="loading" />
      </template>
    </PageHeader>

    <!-- Tab switcher -->
    <div class="map-tab-bar">
      <button class="map-tab-btn" :class="{ active: activeTab === 'kunjungan' }" @click="setTab('kunjungan')">
        <i class="pi pi-map-marker" /> Peta Kunjungan
      </button>
      <button v-if="isDeveloper" class="map-tab-btn" :class="{ active: activeTab === 'tracking' }" @click="setTab('tracking')">
        <i class="pi pi-send" /> Live Tracking
        <span v-if="liveData.length" class="live-badge">{{ liveData.length }}</span>
      </button>
    </div>

    <!-- ═══════════ TAB: LIVE TRACKING (developer only) ═══════════ -->
    <template v-if="activeTab === 'tracking' && isDeveloper">
      <div class="tracking-toolbar">
        <div class="tracking-info">
          <span class="live-dot" :class="{ active: liveData.length > 0 }" />
          <span><strong>{{ liveData.length }}</strong> sales aktif dalam 30 menit terakhir</span>
          <span v-if="liveLastUpdated" class="live-updated">
            · Update {{ liveLastUpdated }}
          </span>
        </div>
        <div class="tracking-actions">
          <span v-if="liveData.length" class="live-countdown" :class="{ urgent: liveCountdown <= 30 }">
            <i class="pi pi-clock" /> {{ liveCountdown }}s
          </span>
          <Select
            v-model="selectedSalesId"
            :options="salesAktifList"
            optionLabel="nama"
            optionValue="id"
            placeholder="Lihat rute sales..."
            style="min-width: 200px"
            showClear
            @change="loadRute"
          />
          <DatePicker
            v-model="ruteTanggal"
            placeholder="Tanggal rute"
            dateFormat="yy-mm-dd"
            style="min-width: 155px"
            showClear
            @date-select="loadRute"
            @clear-click="loadRute"
          />
          <Button icon="pi pi-refresh" text plain :loading="liveLoading" @click="loadLive" v-tooltip="'Refresh posisi'" />
        </div>
      </div>

      <div class="map-layout">
        <!-- Sidebar tracking -->
        <Card class="tracking-sidebar-card">
          <template #content>
            <div class="tracking-sidebar">
              <div class="ts-title"><i class="pi pi-users" /> Sales Online</div>
              <div v-if="!liveData.length" class="ts-empty">
                <i class="pi pi-moon" />
                <span>Belum ada sales yang aktif saat ini</span>
              </div>
              <div
                v-for="s in liveData"
                :key="s.user_id"
                class="ts-item"
                :class="{ selected: selectedSalesId === s.user_id }"
                @click="flyToSales(s)"
              >
                <div class="ts-avatar">{{ s.nama[0] }}</div>
                <div class="ts-info">
                  <span class="ts-nama">{{ s.nama }}</span>
                  <span class="ts-waktu">
                    <i class="pi pi-clock" />
                    {{ s.recorded_at }} · {{ s.menit_lalu }} menit lalu
                  </span>
                </div>
                <div class="ts-dot" :class="s.menit_lalu <= 5 ? 'online' : 'idle'" />
              </div>

              <template v-if="ruteData.length">
                <div class="ts-title mt"><i class="pi pi-map" /> Rute Hari Ini</div>
                <div class="ts-rute-info">
                  <span><b>{{ ruteData.length }}</b> titik lokasi</span>
                  <span>{{ ruteData[0]?.recorded_at }} sampai {{ ruteData[ruteData.length - 1]?.recorded_at }}</span>
                </div>
              </template>
            </div>
          </template>
        </Card>

        <!-- Peta tracking -->
        <Card class="map-card">
          <template #content>
            <div class="map-wrapper">
              <div ref="trackingMapContainer" class="map-container" />
              <div v-if="liveLoading" class="map-overlay-loading">
                <ProgressSpinner strokeWidth="3" />
                <span>Memuat tracking...</span>
              </div>
            </div>
          </template>
        </Card>
      </div>
    </template>

    <!-- Stats bar -->
    <div v-if="activeTab === 'kunjungan'" class="map-stats">
      <div class="stat-pill green">
        <i class="pi pi-map-marker" />
        <span><strong>{{ dalamToko }}</strong> Dalam Toko</span>
      </div>
      <div class="stat-pill red">
        <i class="pi pi-check-circle" />
        <span><strong>{{ selesai }}</strong> Selesai</span>
      </div>
      <div class="stat-pill blue">
        <i class="pi pi-map-marker" />
        <span><strong>{{ data.total || 0 }}</strong> Total Kunjungan</span>
      </div>
      <div class="stat-pill gray">
        <i class="pi pi-users" />
        <span><strong>{{ salesHadir }}</strong> Sales Hadir</span>
      </div>
      <div class="stat-pill gray">
        <i class="pi pi-calendar" />
        <span>{{ tanggalLabel }}</span>
      </div>
    </div>

    <!-- ═══════════ TAB: KUNJUNGAN ═══════════ -->
    <!-- Main layout: map + sidebar -->
    <div v-if="activeTab === 'kunjungan'" class="map-layout">

      <MapsSidebar
        :layers="layers"
        :routeMode="routeMode"
        :routingLoading="routingLoading"
        :activeSales="activeSales"
        :salesList="salesList"
        :filteredKunjungan="filteredKunjungan"
        @toggleLayer="toggleLayer"
        @setRouteMode="setRouteMode"
        @toggleSales="toggleSales"
        @toggleAllSales="toggleAllSales"
        @flyTo="flyTo"
      />

      <!-- Map -->
      <Card class="map-card">
        <template #content>
          <div class="map-wrapper">
            <div ref="mapContainer" class="map-container" />
            <div v-if="loading" class="map-overlay-loading">
              <ProgressSpinner strokeWidth="3" />
              <span>Memuat peta...</span>
            </div>
            <div v-else-if="routingLoading" class="map-overlay-routing">
              <div class="routing-badge">
                <i class="pi pi-spin pi-spinner" />
                <span>Menghitung rute jalan… {{ routingProgress }}</span>
              </div>
            </div>
          </div>

          <MapsLegend />
        </template>
      </Card>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import 'leaflet.markercluster/dist/MarkerCluster.css'
import 'leaflet.markercluster/dist/MarkerCluster.Default.css'
import 'leaflet.markercluster'
import { mapsService }     from '@/services/maps.service'
import { trackingService } from '@/services/tracking.service'
import { useToast }        from '@/composables/useToast'
import { useAppStore }     from '@/stores/app.store'
import { useAuthStore }    from '@/stores/auth.store'
import { useMapsRouting }  from '@/composables/useMapsRouting'
import PageHeader          from '@/components/common/PageHeader.vue'
import MapsSidebar         from '@/components/maps/MapsSidebar.vue'
import MapsLegend          from '@/components/maps/MapsLegend.vue'

const toast     = useToast()
const appStore  = useAppStore()
const authStore = useAuthStore()
const isDeveloper = computed(() => authStore.user?.role_nama === 'developer')

// ── Live Tracking state ───────────────────────────────────────
const activeTab         = ref('kunjungan')
const liveLoading       = ref(false)
const liveData          = ref([])
const salesAktifList    = ref([])
const selectedSalesId   = ref(null)
const ruteTanggal       = ref(null)
const ruteData          = ref([])
const trackingMapContainer = ref(null)
let trackingMapInstance  = null
let trackingTileLayer    = null
let liveMarkersLayer     = null
let rutePolylineLayer    = null
let liveRefreshTimer     = null
let countdownTimer       = null
const liveLastUpdated    = ref(null)
const liveCountdown      = ref(180)

const TRACKING_PALETTE = ['#3b82f6','#f59e0b','#8b5cf6','#06b6d4','#ec4899','#10b981','#f97316','#ef4444']

function trackingColor(idx) { return TRACKING_PALETTE[idx % TRACKING_PALETTE.length] }

async function setTab(tab) {
  activeTab.value = tab
  await nextTick()
  if (tab === 'tracking') {
    initTrackingMap()
    await loadLive()
    await loadSalesAktif()
    startLiveRefresh()
  } else {
    stopLiveRefresh()
    mapInstance?.invalidateSize()
  }
}

function initTrackingMap() {
  if (trackingMapInstance || !trackingMapContainer.value) return
  trackingMapInstance = L.map(trackingMapContainer.value, { zoomControl: true, scrollWheelZoom: true })
  trackingTileLayer = L.tileLayer(tileUrl(), { attribution: '© OpenStreetMap', maxZoom: 19 })
  trackingTileLayer.addTo(trackingMapInstance)
  liveMarkersLayer  = L.layerGroup().addTo(trackingMapInstance)
  rutePolylineLayer = L.layerGroup().addTo(trackingMapInstance)
  trackingMapInstance.setView([-7.5, 110.0], 10)
}

async function loadLive() {
  liveLoading.value = true
  try {
    const res = await trackingService.getLive()
    liveData.value = res.data ?? []
    liveLastUpdated.value = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
    liveCountdown.value = 180
    renderLiveMarkers()
  } catch {
    toast.error('Gagal memuat data tracking')
  } finally {
    liveLoading.value = false
  }
}

async function loadSalesAktif() {
  try {
    const res = await trackingService.getSalesAktif()
    salesAktifList.value = res.data ?? []
  } catch { /* silent */ }
}

async function loadRute() {
  if (!selectedSalesId.value) {
    ruteData.value = []
    rutePolylineLayer?.clearLayers()
    return
  }
  try {
    const tanggal = ruteTanggal.value ? tanggalLokal(new Date(ruteTanggal.value)) : null
    const res = await trackingService.getRute(selectedSalesId.value, tanggal)
    ruteData.value = res.data ?? []
    renderRute()
  } catch {
    toast.error('Gagal memuat rute')
  }
}

function renderLiveMarkers() {
  if (!liveMarkersLayer) return
  liveMarkersLayer.clearLayers()
  liveData.value.forEach((s, idx) => {
    const color = trackingColor(idx)
    const icon = L.divIcon({
      className: '',
      html: `<div style="background:${color};width:36px;height:36px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px">${s.nama[0]}</div>`,
      iconSize: [36, 36], iconAnchor: [18, 18],
    })
    const marker = L.marker([s.lat, s.lng], { icon })
    marker.bindPopup(`
      <div style="padding:8px 10px;min-width:160px">
        <div style="font-weight:700;font-size:13px">${s.nama}</div>
        <div style="font-size:11px;opacity:0.65;margin-top:2px">Update: ${s.recorded_at} · ${s.menit_lalu} menit lalu</div>
        ${s.akurasi ? `<div style="font-size:11px;opacity:0.45;margin-top:2px">Akurasi: ±${Math.round(s.akurasi)}m</div>` : ''}
      </div>
    `)
    liveMarkersLayer.addLayer(marker)
  })
  if (liveData.value.length && trackingMapInstance) {
    const bounds = liveData.value.map(s => [s.lat, s.lng])
    trackingMapInstance.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 })
  }
}

function renderRute() {
  if (!rutePolylineLayer) return
  rutePolylineLayer.clearLayers()
  if (!ruteData.value.length) return

  const latlngs = ruteData.value.map(p => [p.lat, p.lng])
  const color = trackingColor(salesAktifList.value.findIndex(s => s.id === selectedSalesId.value))

  L.polyline(latlngs, { color, weight: 4, opacity: 0.75, dashArray: '6,4' })
    .addTo(rutePolylineLayer)

  // Titik awal & akhir
  const startIcon = L.divIcon({ className: '', html: `<div style="background:#16a34a;width:14px;height:14px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,0.3)"></div>`, iconSize: [14, 14], iconAnchor: [7, 7] })
  const endIcon   = L.divIcon({ className: '', html: `<div style="background:#dc2626;width:14px;height:14px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,0.3)"></div>`, iconSize: [14, 14], iconAnchor: [7, 7] })
  L.marker(latlngs[0], { icon: startIcon }).bindTooltip('Mulai').addTo(rutePolylineLayer)
  L.marker(latlngs[latlngs.length - 1], { icon: endIcon }).bindTooltip('Terakhir').addTo(rutePolylineLayer)

  trackingMapInstance?.fitBounds(latlngs, { padding: [40, 40], maxZoom: 15 })
}

function flyToSales(s) {
  selectedSalesId.value = s.user_id
  trackingMapInstance?.flyTo([s.lat, s.lng], 15, { animate: true, duration: 1 })
  loadRute()
}

function startLiveRefresh() {
  stopLiveRefresh()
  liveCountdown.value = 180
  liveRefreshTimer = setInterval(() => loadLive(), 3 * 60 * 1000)
  countdownTimer   = setInterval(() => {
    liveCountdown.value--
    if (liveCountdown.value <= 0) liveCountdown.value = 180
  }, 1000)
}

function stopLiveRefresh() {
  if (liveRefreshTimer) { clearInterval(liveRefreshTimer); liveRefreshTimer = null }
  if (countdownTimer)   { clearInterval(countdownTimer);   countdownTimer   = null }
}

const loading    = ref(false)
const filterDate = ref(null)
const data       = ref({ kunjungan: [], presensi: [], per_sales: [], total: 0, sales_aktif: 0 })

const { routeMode, routingLoading, routingProgress, fetchRoadRoute, clearCache } = useMapsRouting()

const layers      = reactive({ kunjungan: true, presensi: true, polyline: true })
const activeSales = ref(new Set())

const mapContainer   = ref(null)
let mapInstance      = null
let clusterKunjungan = null
let clusterPresensi  = null
let polylinesLayer   = null
let tileLayer        = null

const PALETTE = ['#3b82f6','#f59e0b','#8b5cf6','#06b6d4','#ec4899','#10b981','#f97316','#6366f1','#ef4444','#84cc16']

// YYYY-MM-DD menurut jam lokal. Jangan toISOString(): itu UTC, sehingga
// tengah malam WIB terbaca hari sebelumnya (rute sales tertukar sehari).
function tanggalLokal(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

// ── Computed ──────────────────────────────────────────────────
const tanggalLabel = computed(() => {
  const d = filterDate.value ? new Date(filterDate.value) : new Date()
  return d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
})

const dalamToko  = computed(() => (data.value.kunjungan || []).filter(k => k.status === 'dalam_toko').length)
const selesai    = computed(() => (data.value.kunjungan || []).filter(k => k.status === 'selesai').length)
const salesHadir = computed(() => new Set((data.value.presensi || []).map(p => p.sales_id)).size)
const salesList  = computed(() => data.value.per_sales || [])

const filteredKunjungan = computed(() => {
  const all = data.value.kunjungan || []
  if (!activeSales.value.size) return all
  return all.filter(k => activeSales.value.has(k.sales_id))
})

// ── Helpers ───────────────────────────────────────────────────
function salesColor(idx)      { return PALETTE[idx % PALETTE.length] }
function salesColorById(salesId) {
  const idx = salesList.value.findIndex(s => s.sales_id === salesId)
  return idx >= 0 ? salesColor(idx) : '#64748b'
}

function formatTime(dt) {
  if (!dt) return '-'
  const match = String(dt).match(/T(\d{2}:\d{2})/)
  if (match) return match[1]
  return new Date(dt).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

// ── Layer toggles ─────────────────────────────────────────────
function toggleLayer(name) {
  layers[name] = !layers[name]
  applyLayerVisibility()
}

function applyLayerVisibility() {
  if (!mapInstance) return
  const action = (layer, show) => {
    if (!layer) return
    if (show  && !mapInstance.hasLayer(layer)) mapInstance.addLayer(layer)
    if (!show &&  mapInstance.hasLayer(layer)) mapInstance.removeLayer(layer)
  }
  action(clusterKunjungan, layers.kunjungan)
  action(clusterPresensi,  layers.presensi)
  action(polylinesLayer,   layers.polyline)
}

function toggleSales(salesId) {
  const s = new Set(activeSales.value)
  if (s.has(salesId)) s.delete(salesId)
  else s.add(salesId)
  activeSales.value = s
  renderMarkers()
}

function toggleAllSales() {
  if (activeSales.value.size === 0) {
    activeSales.value = new Set(salesList.value.map(s => s.sales_id))
  } else {
    activeSales.value = new Set()
  }
  renderMarkers()
}

async function setRouteMode(mode) {
  if (routeMode.value === mode) return
  routeMode.value = mode
  clearCache()  // hapus cache lama agar mode baru selalu fresh fetch
  await renderPolylines()
}

// ── Map init ──────────────────────────────────────────────────
function initMap() {
  if (mapInstance) return
  mapInstance = L.map(mapContainer.value, { zoomControl: true, scrollWheelZoom: true })
    .setView([-7.25, 112.75], 10)

  tileLayer = L.tileLayer(tileUrl(), { attribution: '© OpenStreetMap', maxZoom: 19 })
  tileLayer.addTo(mapInstance)

  clusterKunjungan = L.markerClusterGroup({
    maxClusterRadius: 50,
    iconCreateFunction(cluster) {
      const count     = cluster.getChildCount()
      const hasActive = cluster.getAllChildMarkers().some(m => m.options.status === 'dalam_toko')
      const bg        = hasActive ? '#16a34a' : '#dc2626'
      return L.divIcon({
        html: `<div style="background:${bg};color:#fff;border-radius:50%;width:40px;height:40px;
          display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;
          box-shadow:0 2px 12px ${bg}88;border:3px solid #fff;">${count}</div>`,
        className: '', iconSize: [40, 40], iconAnchor: [20, 20],
      })
    },
  }).addTo(mapInstance)

  clusterPresensi = L.markerClusterGroup({
    maxClusterRadius: 40,
    iconCreateFunction(cluster) {
      const count = cluster.getChildCount()
      return L.divIcon({
        html: `<div style="background:#3b82f6;color:#fff;border-radius:8px;width:36px;height:36px;
          display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;
          box-shadow:0 2px 10px #3b82f688;border:3px solid #fff;">${count}</div>`,
        className: '', iconSize: [36, 36], iconAnchor: [18, 18],
      })
    },
  }).addTo(mapInstance)

  polylinesLayer = L.layerGroup().addTo(mapInstance)
}

function tileUrl() {
  return appStore.darkMode
    ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
    : 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'
}

// ── Render markers ────────────────────────────────────────────
function renderMarkers() {
  if (!mapInstance) return
  clusterKunjungan.clearLayers()
  clusterPresensi.clearLayers()
  polylinesLayer.clearLayers()

  const kunjungan = data.value.kunjungan || []
  const presensis = data.value.presensi  || []
  const filterIds = activeSales.value.size ? activeSales.value : null
  const bounds    = []

  // Kunjungan markers
  kunjungan.forEach((k, idx) => {
    if (!k.lat || !k.lng) return
    if (filterIds && !filterIds.has(k.sales_id)) return

    bounds.push([k.lat, k.lng])
    const isDone   = k.status === 'selesai'
    const bg       = isDone ? '#dc2626' : '#16a34a'
    const shadow   = isDone ? '#dc262666' : '#16a34a66'
    const initials = k.sales?.nama?.[0]?.toUpperCase() || '?'
    const num      = idx + 1
    const color    = salesColorById(k.sales_id)

    const icon = L.divIcon({
      className: '',
      html: `<div style="position:relative">
        <div style="background:${bg};color:#fff;border-radius:50%;width:36px;height:36px;
          display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;
          box-shadow:0 2px 10px ${shadow};border:3px solid ${color};">
          ${initials}
        </div>
        <div style="position:absolute;top:-7px;right:-7px;background:${color};color:#fff;
          border-radius:50%;width:16px;height:16px;font-size:9px;font-weight:800;
          display:flex;align-items:center;justify-content:center;border:1.5px solid #fff;">
          ${num}
        </div>
        ${!isDone ? `<div style="position:absolute;bottom:-5px;left:50%;transform:translateX(-50%);
          width:10px;height:10px;border-radius:50%;background:#16a34a;
          animation:pulse 1.5s ease-in-out infinite;border:2px solid #fff;"></div>` : ''}
      </div>`,
      iconSize: [36, 36], iconAnchor: [18, 18],
    })

    const durasiRow = k.durasi_label
      ? `<div class="popup-row green">⏱ Durasi dalam toko: <strong>${k.durasi_label}</strong></div>`
      : `<div class="popup-row green">🟢 Masih berada di toko...</div>`

    const popup = `<div class="kmap-popup">
      <div class="popup-store">${k.nama_toko}</div>
      <div class="popup-sales">${k.sales?.nama || '-'} · ${k.sales?.area || '-'}</div>
      <div class="popup-times">
        <div class="popup-time-item">🕐 Tiba<br><strong>${formatTime(k.created_at)}</strong></div>
        ${k.checkout_at ? `<div class="popup-time-item">🚪 Keluar<br><strong>${formatTime(k.checkout_at)}</strong></div>` : ''}
      </div>
      ${durasiRow}
      <div class="popup-status ${isDone ? 'red' : 'green'}">${isDone ? '✓ Selesai' : '● Dalam Toko'}</div>
    </div>`

    clusterKunjungan.addLayer(
      L.marker([k.lat, k.lng], { icon, status: k.status })
        .bindPopup(popup, { maxWidth: 240, className: 'kmap-popup-wrap' })
    )
  })

  // Presensi markers
  presensis.forEach(p => {
    if (filterIds && !filterIds.has(p.sales_id)) return

    const makePresensiMarker = (lat, lng, type, jam) => {
      if (!lat || !lng) return
      bounds.push([lat, lng])
      const isMasuk = type === 'masuk'
      const bg      = isMasuk ? '#3b82f6' : '#f97316'
      const icon    = L.divIcon({
        className: '',
        html: `<div style="background:${bg};color:#fff;border-radius:6px;width:30px;height:30px;
          display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;
          box-shadow:0 2px 8px ${bg}88;border:2px solid #fff;">
          ${isMasuk ? '↑' : '↓'}
        </div>`,
        iconSize: [30, 30], iconAnchor: [15, 15],
      })
      const popup = `<div class="kmap-popup">
        <div class="popup-store">${isMasuk ? 'Absen Masuk' : 'Absen Pulang'}</div>
        <div class="popup-sales">${p.sales?.nama || '-'} · ${p.sales?.area || '-'}</div>
        <div class="popup-times">
          <div class="popup-time-item">${isMasuk ? '🕐' : '🕔'} Jam<br><strong>${jam || '-'}</strong></div>
        </div>
        <div class="popup-status ${isMasuk ? 'blue' : 'orange'}">${isMasuk ? '↑ Masuk' : '↓ Pulang'}</div>
      </div>`
      clusterPresensi.addLayer(
        L.marker([lat, lng], { icon }).bindPopup(popup, { maxWidth: 220, className: 'kmap-popup-wrap' })
      )
    }

    makePresensiMarker(p.lat_masuk,  p.lng_masuk,  'masuk',  p.jam_masuk)
    makePresensiMarker(p.lat_pulang, p.lng_pulang, 'pulang', p.jam_pulang)
  })

  if (bounds.length) mapInstance.fitBounds(bounds, { padding: [40, 40] })

  renderPolylines()
}

// ── Polyline drawing ──────────────────────────────────────────
function drawPolyline(latlngs, color, salesNama, salesArea) {
  L.polyline(latlngs, { color: '#fff', weight: 7, opacity: 0.4, interactive: false })
    .addTo(polylinesLayer)

  L.polyline(latlngs, { color, weight: 4, opacity: 0.85, lineJoin: 'round', lineCap: 'round' })
    .bindTooltip(`<strong>${salesNama}</strong><br><span style="font-size:11px;color:#666">${salesArea || ''}</span>`, { sticky: true })
    .addTo(polylinesLayer)

  L.circleMarker(latlngs[0], { radius: 7, color: '#fff', weight: 2, fillColor: '#16a34a', fillOpacity: 1 })
    .bindTooltip(`${salesNama}: Mulai`, { sticky: true })
    .addTo(polylinesLayer)

  L.circleMarker(latlngs[latlngs.length - 1], { radius: 7, color: '#fff', weight: 2, fillColor: '#dc2626', fillOpacity: 1 })
    .bindTooltip(`${salesNama}: Selesai`, { sticky: true })
    .addTo(polylinesLayer)
}

async function renderPolylines() {
  if (!mapInstance || !layers.polyline) return

  polylinesLayer.clearLayers()

  const perSales  = data.value.per_sales || []
  const filterIds = activeSales.value.size ? activeSales.value : null

  const targets = perSales
    .map((s, idx) => ({ s, idx }))
    .filter(({ s }) => {
      if (filterIds && !filterIds.has(s.sales_id)) return false
      return (s.polyline || []).filter(p => p[0] && p[1]).length >= 2
    })

  if (!targets.length) return

  if (routeMode.value === 'straight') {
    targets.forEach(({ s, idx }) => {
      const latlngs = (s.polyline || []).filter(p => p[0] && p[1])
      drawPolyline(latlngs, salesColor(idx), s.sales_nama, s.sales_area)
    })
    return
  }

  // Road routing — fetch OSRM per sales secara parallel
  routingLoading.value  = true
  routingProgress.value = `0 / ${targets.length}`

  const tglKey = tanggalLokal(filterDate.value ? new Date(filterDate.value) : new Date())

  let done = 0
  try {
    await Promise.all(targets.map(async ({ s, idx }) => {
      const rawPoints = (s.polyline || []).filter(p => p[0] && p[1])
      const cacheKey  = `${s.sales_id}_${tglKey}`
      const routed    = await fetchRoadRoute(rawPoints, cacheKey)
      drawPolyline(routed, salesColor(idx), s.sales_nama, s.sales_area)
      done++
      routingProgress.value = `${done} / ${targets.length}`
    }))
  } finally {
    routingLoading.value  = false
    routingProgress.value = ''
  }
}

function flyTo(lat, lng, kunjungan = null) {
  if (!mapInstance || !lat || !lng) return
  mapInstance.flyTo([lat, lng], 17, { duration: 1 })
  if (kunjungan) {
    setTimeout(() => {
      clusterKunjungan.eachLayer(m => {
        const ll = m.getLatLng?.()
        if (ll && Math.abs(ll.lat - lat) < 0.00002 && Math.abs(ll.lng - lng) < 0.00002) {
          clusterKunjungan.zoomToShowLayer(m, () => m.openPopup())
        }
      })
    }, 1100)
  }
}

// ── Data load ─────────────────────────────────────────────────
async function loadData() {
  loading.value = true
  try {
    const tanggal = filterDate.value ? tanggalLokal(new Date(filterDate.value)) : null
    const res = await mapsService.getKunjungan(tanggal)
    data.value = res.data?.data ?? res.data ?? {}
    activeSales.value = new Set()
    renderMarkers()
  } catch (err) {
    toast.apiError(err)
  } finally {
    loading.value = false
  }
}

watch(() => appStore.darkMode, () => {
  if (mapInstance && tileLayer) {
    mapInstance.removeLayer(tileLayer)
    tileLayer = L.tileLayer(tileUrl(), { attribution: '© OpenStreetMap', maxZoom: 19 })
    tileLayer.addTo(mapInstance)
  }
  if (trackingMapInstance && trackingTileLayer) {
    trackingMapInstance.removeLayer(trackingTileLayer)
    trackingTileLayer = L.tileLayer(tileUrl(), { attribution: '© OpenStreetMap', maxZoom: 19 })
    trackingTileLayer.addTo(trackingMapInstance)
  }
})

// Card dimuat async dan tiap tab memakai v-if, jadi wadah peta belum ada saat
// onMounted dan dibuat ulang setiap pindah tab. Peta dipasang mengikuti wadahnya.
watch(mapContainer, (el) => {
  if (!el || mapInstance?.getContainer() === el) return
  if (mapInstance) { mapInstance.remove(); mapInstance = null }
  initMap()
  renderMarkers()
}, { flush: 'post' })

watch(trackingMapContainer, (el) => {
  if (!el || trackingMapInstance?.getContainer() === el) return
  if (trackingMapInstance) { trackingMapInstance.remove(); trackingMapInstance = null }
  initTrackingMap()
  renderLiveMarkers()
}, { flush: 'post' })

onMounted(async () => {
  await loadData()
})

onBeforeUnmount(() => {
  if (mapInstance) { mapInstance.remove(); mapInstance = null }
  stopLiveRefresh()
  if (trackingMapInstance) { trackingMapInstance.remove(); trackingMapInstance = null; trackingTileLayer = null }
})
</script>

<style scoped>
.maps-view { display: flex; flex-direction: column; gap: 1.25rem; }

/* ── Tab bar ─────────────────────────────────────────────── */
.map-tab-bar {
  display: flex; gap: 0.5rem;
  border-bottom: 2px solid var(--s-border);
  padding-bottom: 0;
}
.map-tab-btn {
  display: flex; align-items: center; gap: 0.45rem;
  padding: 0.55rem 1.1rem; border: none; background: transparent;
  cursor: pointer; font-size: 0.875rem; font-weight: 600;
  color: var(--t-muted); border-bottom: 2px solid transparent;
  margin-bottom: -2px; transition: color 0.2s, border-color 0.2s;
  border-radius: 6px 6px 0 0;
}
.map-tab-btn:hover { color: var(--t-primary); background: var(--s-1); }
.map-tab-btn.active { color: var(--c-brand-600); border-bottom-color: var(--c-brand-500); }
.live-badge {
  background: var(--c-danger); color: #fff;
  font-size: 0.68rem; font-weight: 700;
  padding: 1px 6px; border-radius: 10px; min-width: 18px; text-align: center;
}

/* ── Tracking toolbar ────────────────────────────────────── */
.tracking-toolbar {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 0.75rem;
  background: var(--s-0); border: 1px solid var(--s-border);
  border-radius: 10px; padding: 0.75rem 1rem;
}
.tracking-info { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--t-muted); flex-wrap: wrap; }
.live-updated  { font-size: 0.78rem; opacity: 0.7; }
.live-countdown {
  display: flex; align-items: center; gap: 0.3rem;
  font-size: 0.75rem; font-weight: 600;
  padding: 0.25rem 0.6rem; border-radius: 20px;
  background: rgba(34,197,94,.12); color: var(--c-success);
  border: 1px solid rgba(34,197,94,.25); white-space: nowrap;
}
.live-countdown.urgent { background: rgba(239,68,68,.1); color: var(--c-danger); border-color: rgba(239,68,68,.2); }
.live-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--t-secondary); }
.live-dot.active { background: var(--c-success); box-shadow: 0 0 0 3px rgba(22,163,74,0.2); animation: pulse-dot 2s infinite; }
@keyframes pulse-dot { 0%,100% { box-shadow: 0 0 0 3px rgba(22,163,74,0.2); } 50% { box-shadow: 0 0 0 6px rgba(22,163,74,0.08); } }
.tracking-actions { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }

/* ── Tracking sidebar ────────────────────────────────────── */
.tracking-sidebar-card { min-width: 260px; max-width: 280px; }
.tracking-sidebar-card :deep(.p-card-body) { padding: 0.75rem; }
.tracking-sidebar { display: flex; flex-direction: column; gap: 0.5rem; max-height: calc(100vh - 350px); overflow-y: auto; }
.ts-title { font-size: 0.78rem; font-weight: 700; color: var(--t-muted); text-transform: uppercase; letter-spacing: 0.05em; padding: 0.25rem 0; }
.ts-title.mt { margin-top: 0.75rem; border-top: 1px solid var(--s-border); padding-top: 0.75rem; }
.ts-empty { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; padding: 2rem 0; color: var(--t-muted); font-size: 0.8rem; }
.ts-empty i { font-size: 1.8rem; }
.ts-item {
  display: flex; align-items: center; gap: 0.6rem;
  padding: 0.5rem 0.6rem; border-radius: 8px; cursor: pointer;
  transition: background 0.15s;
}
.ts-item:hover { background: var(--s-1); }
.ts-item.selected { background: rgba(249,115,22,0.08); }
.ts-avatar {
  width: 34px; height: 34px; border-radius: 50%; background: var(--c-brand-500);
  color: #fff; font-weight: 700; font-size: 14px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.ts-info { flex: 1; display: flex; flex-direction: column; gap: 1px; }
.ts-nama  { font-size: 0.82rem; font-weight: 600; color: var(--t-primary); }
.ts-waktu { font-size: 0.72rem; color: var(--t-muted); display: flex; align-items: center; gap: 0.25rem; }
.ts-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.ts-dot.online { background: var(--c-success); }
.ts-dot.idle   { background: var(--c-warning); }
.ts-rute-info { font-size: 0.78rem; color: var(--t-muted); display: flex; flex-direction: column; gap: 2px; }
.ts-rute-info b { color: var(--t-primary); }

.map-stats { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.stat-pill {
  display: flex; align-items: center; gap: 0.4rem;
  padding: 0.4rem 0.85rem; border-radius: 20px;
  font-size: 0.83rem; font-weight: 500; border: 1px solid transparent;
}
/* rgba agar otomatis terlihat di dark & light */
.stat-pill.green { background: rgba(34,197,94,.12);  color:var(--c-success); border-color:rgba(34,197,94,.25); }
.stat-pill.red   { background: rgba(239,68,68,.1);   color:var(--c-danger); border-color:rgba(239,68,68,.2); }
.stat-pill.blue  { background: rgba(59,130,246,.1);  color:var(--c-info); border-color:rgba(59,130,246,.2); }
.stat-pill.gray  { background: var(--s-1); color:var(--t-muted); border-color:var(--s-border); }

.map-layout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 1rem;
  align-items: start;
}

.map-card :deep(.p-card-body) { padding: 0; }
.map-card { flex: 1; }

.map-wrapper {
  position: relative;
  height: calc(100vh - 280px);
  min-height: 460px;
  border-radius: 10px 10px 0 0;
  overflow: hidden;
}

.map-container { height: 100%; width: 100%; z-index: 0; }

.map-overlay-loading {
  position: absolute; inset: 0; z-index: 10;
  background: color-mix(in srgb, var(--s-0) 80%, transparent);
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 1rem; color: var(--t-muted);
  backdrop-filter: blur(2px);
}

.map-overlay-routing {
  position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%);
  z-index: 10; pointer-events: none;
}
.routing-badge {
  display: flex; align-items: center; gap: 0.5rem;
  padding: 0.4rem 0.9rem; border-radius: 20px;
  background: rgba(30,41,59,0.85); color: #fff;
  font-size: 0.78rem; font-weight: 600;
  box-shadow: 0 2px 12px rgba(0,0,0,0.25);
  backdrop-filter: blur(4px); white-space: nowrap;
}

@media (max-width: 900px) {
  .map-layout { grid-template-columns: 1fr; }
  .map-wrapper { height: 420px; }
}

/* Leaflet popup — neutral colors agar cocok di kedua mode */
:global(.kmap-popup-wrap .leaflet-popup-content-wrapper) {
  border-radius: 12px !important;
  box-shadow: 0 4px 24px rgba(0,0,0,0.15) !important;
  padding: 0 !important;
}
:global(.kmap-popup-wrap .leaflet-popup-content) { margin: 0 !important; }
:global(.kmap-popup) { padding: 12px 14px; min-width: 180px; font-family: sans-serif; }
:global(.popup-store) { font-weight: 700; font-size: 14px; margin-bottom: 2px; }
:global(.popup-sales) { font-size: 11px; opacity: 0.6; margin-bottom: 8px; }
:global(.popup-times) { display: flex; gap: 8px; margin-bottom: 6px; }
:global(.popup-time-item) { background: rgba(0,0,0,0.05); border-radius: 6px; padding: 4px 8px; font-size: 11px; line-height: 1.4; }
:global(.popup-row) { font-size: 11px; padding: 4px 8px; border-radius: 6px; margin-bottom: 6px; }
:global(.popup-row.green) { background: rgba(34,197,94,.12); color: var(--c-success); }
:global(.popup-status) { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; }
:global(.popup-status.green)  { background: rgba(34,197,94,.15);  color: var(--c-success); }
:global(.popup-status.red)    { background: rgba(239,68,68,.12);  color: var(--c-danger); }
:global(.popup-status.blue)   { background: rgba(59,130,246,.12); color: var(--c-info); }
:global(.popup-status.orange) { background: rgba(249,115,22,.12); color: var(--c-brand-500); }
</style>

<!-- Global: keyframes untuk Leaflet divIcon inline HTML -->
<style>
@keyframes pulse {
  0%,100% { transform: translateX(-50%) scale(1); opacity: 1; }
  50%      { transform: translateX(-50%) scale(1.8); opacity: 0.3; }
}
</style>
