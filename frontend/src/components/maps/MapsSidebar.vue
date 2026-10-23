<template>
  <div class="map-sidebar">
    <!-- Layer toggles -->
    <div class="sidebar-section">
      <div class="sidebar-title">Tampilkan Layer</div>
      <div class="layer-toggle" @click="$emit('toggleLayer', 'kunjungan')">
        <span class="layer-dot" style="background:#16a34a" />
        <span>Kunjungan</span>
        <i :class="layers.kunjungan ? 'pi pi-eye' : 'pi pi-eye-slash'" class="layer-icon" />
      </div>
      <div class="layer-toggle" @click="$emit('toggleLayer', 'presensi')">
        <span class="layer-dot" style="background:#3b82f6" />
        <span>Presensi Masuk</span>
        <i :class="layers.presensi ? 'pi pi-eye' : 'pi pi-eye-slash'" class="layer-icon" />
      </div>
      <div class="layer-toggle" @click="$emit('toggleLayer', 'polyline')">
        <span class="layer-line" />
        <span>Jalur per Sales</span>
        <i :class="layers.polyline ? 'pi pi-eye' : 'pi pi-eye-slash'" class="layer-icon" />
      </div>

      <!-- Route mode toggle -->
      <Transition name="slide-sub">
        <div v-if="layers.polyline" class="route-mode-wrap">
          <div
            class="route-mode-btn"
            :class="{ active: routeMode === 'straight' }"
            @click="$emit('setRouteMode', 'straight')"
          >
            <i class="pi pi-minus" />
            <span>Garis Lurus</span>
          </div>
          <div
            class="route-mode-btn"
            :class="{ active: routeMode === 'road', loading: routingLoading }"
            @click="$emit('setRouteMode', 'road')"
          >
            <i v-if="!routingLoading" class="pi pi-map" />
            <i v-else class="pi pi-spin pi-spinner" />
            <span>Ikuti Jalan</span>
          </div>
        </div>
      </Transition>
    </div>

    <!-- Per-sales filter -->
    <div class="sidebar-section">
      <div class="sidebar-title-row">
        <span class="sidebar-title">Filter Sales</span>
        <button class="toggle-all-btn" @click="$emit('toggleAllSales')">
          {{ activeSales.size === 0 ? 'Pilih Semua' : 'Reset' }}
        </button>
      </div>
      <div class="sales-filter-list">
        <div
          v-for="(s, idx) in salesList"
          :key="s.sales_id"
          class="sales-filter-item"
          :class="{ active: activeSales.has(s.sales_id) }"
          @click="$emit('toggleSales', s.sales_id)"
        >
          <span class="sales-dot" :style="{ background: salesColor(idx) }" />
          <div class="sales-filter-info">
            <span class="sales-filter-name">{{ s.sales_nama }}</span>
            <span class="sales-filter-count">{{ s.kunjungan.length }} kunjungan</span>
          </div>
          <i :class="activeSales.has(s.sales_id) ? 'pi pi-check-circle' : 'pi pi-circle'" class="sales-check" />
        </div>
        <div v-if="!salesList.length" class="sidebar-empty">Belum ada data</div>
      </div>
    </div>

    <!-- Quick list -->
    <div class="sidebar-section flex-fill">
      <div class="sidebar-title">Detail Kunjungan</div>
      <div class="visit-list">
        <div
          v-for="k in filteredKunjungan"
          :key="k.id"
          class="visit-item"
          :class="k.status"
          @click="$emit('flyTo', k.lat, k.lng, k)"
        >
          <div class="visit-header">
            <span class="visit-store">{{ k.nama_toko }}</span>
            <span class="visit-badge" :class="k.status">
              {{ k.status === 'selesai' ? 'Selesai' : 'Dalam Toko' }}
            </span>
          </div>
          <div class="visit-meta">
            <i class="pi pi-user" />
            <span>{{ k.sales?.nama }}</span>
            <i class="pi pi-clock" style="margin-left:8px" />
            <span>{{ formatTime(k.created_at) }}</span>
            <template v-if="k.durasi_label">
              <i class="pi pi-stopwatch" style="margin-left:8px" />
              <span>{{ k.durasi_label }}</span>
            </template>
          </div>
        </div>
        <div v-if="!filteredKunjungan.length" class="sidebar-empty">
          <i class="pi pi-map" />
          <span>Belum ada kunjungan</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const PALETTE = ['#3b82f6','#f59e0b','#8b5cf6','#06b6d4','#ec4899','#10b981','#f97316','#6366f1','#ef4444','#84cc16']

defineProps({
  layers:           { type: Object,  required: true },
  routeMode:        { type: String,  required: true },
  routingLoading:   { type: Boolean, default: false },
  activeSales:      { type: Set,     required: true },
  salesList:        { type: Array,   default: () => [] },
  filteredKunjungan:{ type: Array,   default: () => [] },
})

defineEmits(['toggleLayer', 'setRouteMode', 'toggleSales', 'toggleAllSales', 'flyTo'])

function salesColor(idx) { return PALETTE[idx % PALETTE.length] }

function formatTime(dt) {
  if (!dt) return '-'
  const match = String(dt).match(/T(\d{2}:\d{2})/)
  if (match) return match[1]
  return new Date(dt).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}
</script>

<style scoped>
.map-sidebar {
  display: flex; flex-direction: column; gap: 0;
  background: var(--s-0);
  border: 1px solid var(--s-border);
  border-radius: 12px;
  overflow: hidden;
  max-height: calc(100vh - 220px);
  overflow-y: auto;
}

.sidebar-section {
  padding: 0.875rem 1rem;
  border-bottom: 1px solid var(--s-border);
}
.sidebar-section.flex-fill { flex: 1; }

.sidebar-title {
  font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.05em; color: var(--t-muted);
  margin-bottom: 0.6rem;
}

.layer-toggle {
  display: flex; align-items: center; gap: 0.5rem;
  padding: 0.4rem 0.5rem; border-radius: 8px;
  cursor: pointer; font-size: 0.83rem; color: var(--t-primary);
  margin-bottom: 0.25rem; transition: background 0.15s;
}
.layer-toggle:hover { background: var(--s-1); }
.layer-dot  { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.layer-line {
  width: 22px; height: 3px; flex-shrink: 0;
  background: repeating-linear-gradient(to right, var(--c-info) 0, var(--c-info) 5px, transparent 5px, transparent 9px);
}
.layer-icon { margin-left: auto; font-size: 0.8rem; color: var(--t-muted); }

/* Route mode toggle */
.route-mode-wrap {
  display: flex; gap: 0.35rem; margin-top: 0.4rem;
  padding: 0.3rem 0.5rem;
  background: var(--s-1); border-radius: 8px;
}
.route-mode-btn {
  flex: 1; display: flex; align-items: center; justify-content: center;
  gap: 0.3rem; padding: 0.35rem 0.5rem;
  border-radius: 6px; cursor: pointer; font-size: 0.76rem; font-weight: 600;
  color: var(--t-muted); transition: all 0.18s;
  user-select: none;
}
.route-mode-btn:hover:not(.active) { background: var(--s-2); color: var(--t-primary); }
.route-mode-btn.active { background: var(--c-brand-600); color: #fff; box-shadow: 0 1px 4px rgba(0,0,0,0.15); }
.route-mode-btn.loading { opacity: 0.7; cursor: wait; }

.slide-sub-enter-active, .slide-sub-leave-active { transition: all 0.22s ease; overflow: hidden; }
.slide-sub-enter-from, .slide-sub-leave-to { opacity: 0; max-height: 0; margin-top: 0; }
.slide-sub-enter-to, .slide-sub-leave-from { opacity: 1; max-height: 60px; margin-top: 0.4rem; }

.sidebar-title-row {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 0.6rem;
}
.sidebar-title-row .sidebar-title { margin-bottom: 0; }
.toggle-all-btn {
  font-size: 0.68rem; font-weight: 600;
  color: var(--c-brand-500);
  background: none; border: none; cursor: pointer; padding: 0;
  text-decoration: underline; text-underline-offset: 2px;
}
.toggle-all-btn:hover { opacity: 0.75; }

.sales-filter-list { display: flex; flex-direction: column; gap: 0.3rem; }
.sales-filter-item {
  display: flex; align-items: center; gap: 0.5rem;
  padding: 0.45rem 0.5rem; border-radius: 8px;
  cursor: pointer; font-size: 0.82rem; transition: background 0.15s;
  border: 1px solid transparent;
}
.sales-filter-item:hover  { background: var(--s-1); }
.sales-filter-item.active { background: rgba(249,115,22,.1); border-color: rgba(249,115,22,.2); }
.sales-dot         { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.sales-filter-info { flex: 1; display: flex; flex-direction: column; }
.sales-filter-name { font-weight: 600; color: var(--t-primary); line-height: 1.2; }
.sales-filter-count{ font-size: 0.72rem; color: var(--t-muted); }
.sales-check       { font-size: 0.82rem; color: var(--c-brand-500); }

.visit-list { display: flex; flex-direction: column; gap: 0.35rem; }
.visit-item {
  padding: 0.6rem 0.65rem; border-radius: 8px;
  cursor: pointer; border: 1px solid var(--s-border); transition: all 0.15s;
}
.visit-item:hover       { border-color: rgba(249,115,22,.3); background: rgba(249,115,22,.06); }
.visit-item.dalam_toko  { border-left: 3px solid var(--c-success); }
.visit-item.selesai     { border-left: 3px solid var(--c-danger); }

.visit-header { display: flex; align-items: center; justify-content: space-between; gap: 0.4rem; margin-bottom: 0.3rem; }
.visit-store  { font-size: 0.82rem; font-weight: 600; color: var(--t-primary); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.visit-badge  { font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 10px; white-space: nowrap; }
.visit-badge.dalam_toko { background: rgba(34,197,94,.15);  color: var(--c-success); }
.visit-badge.selesai    { background: rgba(239,68,68,.12);  color: var(--c-danger); }
.visit-meta   { display: flex; align-items: center; gap: 0.3rem; font-size: 0.73rem; color: var(--t-muted); flex-wrap: wrap; }

.sidebar-empty {
  display: flex; flex-direction: column; align-items: center;
  gap: 0.4rem; padding: 1.5rem 0; color: var(--t-muted); font-size: 0.82rem;
}
.sidebar-empty i { font-size: 1.5rem; }
</style>
