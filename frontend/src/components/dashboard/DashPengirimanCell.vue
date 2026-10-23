<template>
  <div class="bento-cell bento-pengiriman">
    <div class="cell-head">
      <div class="cell-title-group">
        <span class="cell-title">Pengiriman & Armada</span>
        <span class="cell-sub">Status hari ini</span>
      </div>
      <div class="cell-links">
        <RouterLink v-if="can('muat_subsidi')" to="/pengiriman/muat-subsidi" class="cell-link">
          Subsidi <i class="pi pi-arrow-right" />
        </RouterLink>
        <RouterLink v-if="can('muat_non_subsidi')" to="/pengiriman/muat-non-subsidi" class="cell-link">
          Non Subsidi <i class="pi pi-arrow-right" />
        </RouterLink>
      </div>
    </div>

    <div class="cell-body">

      <!-- Skelekg -->
      <template v-if="loading">
        <div class="pg-grid">
          <div v-for="i in 3" :key="i" class="skelekg" style="height:90px;border-radius:12px" />
        </div>
        <div class="skelekg" style="height:80px;border-radius:12px;margin-top:1rem" />
      </template>

      <template v-else>
        <!-- Row atas: 3 kartu ringkas -->
        <div class="pg-grid">

          <!-- Muatan hari ini -->
          <div class="pg-card pg-card--brand">
            <div class="pg-card-icon"><i class="pi pi-box"></i></div>
            <div class="pg-card-body">
              <div class="pg-val">
                {{ formatTon(stats.muatan_hari_ini?.total_ton) }}
                <span class="pg-unit">kg</span>
              </div>
              <div class="pg-label">Muatan Hari Ini</div>
              <div class="pg-sub">{{ stats.muatan_hari_ini?.jadwal ?? 0 }} jadwal terdaftar</div>
            </div>
          </div>

          <!-- Kendaraan -->
          <div class="pg-card pg-card--success">
            <div class="pg-card-icon"><i class="pi pi-car"></i></div>
            <div class="pg-card-body">
              <div class="pg-val">{{ stats.kendaraan?.tersedia ?? 0 }}</div>
              <div class="pg-label">Kendaraan Tersedia</div>
              <div class="pg-sub">{{ stats.kendaraan?.tidak_tersedia ?? 0 }} sedang beroperasi</div>
            </div>
          </div>

          <!-- Driver -->
          <div class="pg-card pg-card--info">
            <div class="pg-card-icon"><i class="pi pi-id-card"></i></div>
            <div class="pg-card-body">
              <div class="pg-val">{{ stats.driver?.aktif ?? 0 }}</div>
              <div class="pg-label">Driver Aktif</div>
              <div class="pg-sub">dari {{ stats.driver?.total ?? 0 }} total driver</div>
            </div>
          </div>

        </div>

        <!-- Progress bar status jadwal hari ini -->
        <div class="pg-status-bar">
          <div class="pg-status-head">
            <span class="pg-status-title">Status Jadwal Hari Ini</span>
            <span class="pg-status-total">{{ totalJadwal }} jadwal</span>
          </div>
          <div class="pg-bars">
            <div
              class="pg-bar pg-bar--sudah"
              :style="{ width: pct(sudahDriver, totalJadwal) }"
              :title="`Sudah Driver: ${sudahDriver}`"
            />
            <div
              class="pg-bar pg-bar--belum"
              :style="{ width: pct(belumDriver, totalJadwal) }"
              :title="`Belum Driver: ${belumDriver}`"
            />
          </div>
          <div class="pg-legend">
            <span class="pg-leg"><span class="pg-dot pg-dot--sudah" />Sudah Ada Driver ({{ sudahDriver }})</span>
            <span class="pg-leg"><span class="pg-dot pg-dot--belum" />Belum Ada Driver ({{ belumDriver }})</span>
          </div>
        </div>

        <!-- Footer: muatan bulan ini -->
        <div class="pg-footer">
          <div class="pg-footer-item">
            <span class="pg-footer-label">Muatan Bulan Ini</span>
            <span class="pg-footer-val">{{ formatTon(stats.muatan_bulan_ini?.total_ton) }} kg</span>
          </div>
          <div class="pg-footer-divider" />
          <div class="pg-footer-item">
            <span class="pg-footer-label">Total Jadwal Bulan Ini</span>
            <span class="pg-footer-val">{{ stats.muatan_bulan_ini?.jadwal ?? 0 }} jadwal</span>
          </div>
        </div>

      </template>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { usePermission } from '@/composables/usePermission'

const { can } = usePermission()

const props = defineProps({
  stats:   { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: true },
})

const totalJadwal = computed(() => props.stats.muatan_hari_ini?.jadwal ?? 0)
const sudahDriver = computed(() => props.stats.muatan_hari_ini?.sudah_driver ?? 0)
const belumDriver = computed(() => props.stats.muatan_hari_ini?.belum_driver ?? 0)

function formatTon(val) {
  return Math.round((parseFloat(val) || 0) * 1000).toLocaleString('id-ID')
}

function pct(val, total) {
  if (!total) return '0%'
  return Math.round(((val ?? 0) / total) * 100) + '%'
}
</script>

<style scoped>
/* Cell ini cuma dapat 1 kolom bento (±370px), jadi patokannya lebar
   widget — bukan lebar viewport. */
.cell-body {
  container-type: inline-size;
  container-name: pgcell;
}

/* Grid 3 kartu */
.pg-grid {
  display: grid;
  /* minmax(0,1fr), bukan 1fr: track 1fr punya minimum auto, jadi angka
     panjang seperti "481.200 kg" mendorong kolom melebihi container. */
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.75rem;
  margin-bottom: 1rem;
}

/* Tiga kartu butuh ±560px. Di bawah itu jadi 2 kolom, kartu ketiga
   melebar penuh supaya barisnya tidak timpang. */
@container pgcell (max-width: 560px) {
  .pg-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .pg-grid .pg-card:last-child { grid-column: 1 / -1; }
  .pg-val { font-size: 1.25rem; }
}

@container pgcell (max-width: 330px) {
  .pg-grid { grid-template-columns: minmax(0, 1fr); gap: 0.5rem; }
  .pg-grid .pg-card:last-child { grid-column: auto; }
  .pg-footer { flex-direction: column; gap: 0.5rem; align-items: stretch; }
  .pg-footer-divider { display: none; }
  .pg-footer-item { flex-direction: row; justify-content: space-between; align-items: center; }
}

.pg-card {
  border-radius: 12px;
  padding: 0.875rem 1rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  border: 1px solid transparent;
}

/* Light mode — pastel gradient (dark override di dashboard.css global) */
.pg-card--brand  { background: linear-gradient(135deg, var(--c-brand-soft), var(--c-brand-soft)); border-color: var(--c-brand-soft); }
.pg-card--success{ background: linear-gradient(135deg, var(--c-success-soft), var(--c-success-soft)); border-color: var(--c-success-soft); }
.pg-card--info   { background: linear-gradient(135deg, var(--c-info-soft), var(--c-info-soft)); border-color: var(--c-info-line); }

.pg-card-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  align-self: flex-start;
  margin-top: 2px;
}

.pg-card-body {
  flex: 1;
  min-width: 0;
}

.pg-card--brand  .pg-card-icon { background: var(--c-brand-600); color: #fff; }
.pg-card--success .pg-card-icon{ background: var(--c-success); color: #fff; }
.pg-card--info   .pg-card-icon { background: var(--c-info); color: #fff; }

.pg-card-icon .pi { font-size: 1rem; }

.pg-val {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--t-primary);
  line-height: 1.2;
}

.pg-unit {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--t-secondary);
  margin-left: 2px;
}

.pg-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--t-primary);
  margin-top: 2px;
}

.pg-sub {
  font-size: 0.72rem;
  color: var(--t-muted);
  margin-top: 1px;
}

/* Status bar — pakai project vars, otomatis dark/light */
.pg-status-bar {
  background: var(--s-1);
  border: 1px solid var(--s-border);
  border-radius: 10px;
  padding: 0.75rem 0.875rem;
  margin-bottom: 0.75rem;
}

.pg-status-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.pg-status-title { font-size: 0.8rem; font-weight: 600; color: var(--t-primary); }
.pg-status-total { font-size: 0.75rem; color: var(--t-muted); }

.pg-bars {
  height: 8px;
  border-radius: 999px;
  background: var(--s-border);
  overflow: hidden;
  display: flex;
  margin-bottom: 0.5rem;
}

.pg-bar { height: 100%; transition: width 0.5s ease; min-width: 0; }
.pg-bar--sudah { background: var(--c-success); }
.pg-bar--belum { background: var(--c-warning); }

.pg-legend { display: flex; gap: 1rem; flex-wrap: wrap; }

.pg-leg {
  display: flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.72rem;
  color: var(--t-muted);
}

.pg-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.pg-dot--sudah { background: var(--c-success); }
.pg-dot--belum { background: var(--c-warning); }

/* Footer */
.pg-footer {
  display: flex;
  align-items: center;
  gap: 1rem;
  background: var(--s-1);
  border: 1px solid var(--s-border);
  border-radius: 10px;
  padding: 0.625rem 0.875rem;
}

.pg-footer-item { display: flex; flex-direction: column; gap: 1px; flex: 1; }
.pg-footer-label { font-size: 0.72rem; color: var(--t-muted); }
.pg-footer-val   { font-size: 1rem; font-weight: 700; color: var(--t-primary); }
.pg-footer-divider { width: 1px; height: 32px; background: var(--s-border); }

/* Penyesuaian sempit ditangani container query di atas — media query
   viewport tidak dipakai lagi karena lebar cell tidak mengikuti
   lebar layar (di layar lebar pun cell ini cuma ±370px). */
@container pgcell (max-width: 330px) {
  .pg-label { font-size: 0.75rem; }
  .pg-sub   { font-size: 0.68rem; }
  .pg-footer-val { font-size: 0.9rem; }
}
</style>
