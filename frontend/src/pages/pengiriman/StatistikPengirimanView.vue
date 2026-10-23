<template>
  <div class="statistik-view">
    <PageHeader title="Statistik Pengiriman" subtitle="Rekap perjalanan driver dan wilayah pengiriman per periode.">
      <template #actions>
        <div class="periode-toggle">
          <button
            v-for="p in periodeOptions" :key="p.value"
            class="periode-btn"
            :class="{ active: periode === p.value }"
            @click="setPeriode(p.value)"
          >{{ p.label }}</button>
        </div>
      </template>
    </PageHeader>

    <!-- Summary cards -->
    <div class="summary-row" v-if="!loading">
      <div class="summary-card card-total">
        <div class="sc-icon"><i class="pi pi-truck" /></div>
        <div class="sc-body">
          <div class="sc-value">{{ data.summary?.total ?? 0 }}</div>
          <div class="sc-label">Total Trip</div>
        </div>
      </div>
      <div class="summary-card card-subsidi">
        <div class="sc-icon"><i class="pi pi-check-circle" /></div>
        <div class="sc-body">
          <div class="sc-value">{{ data.summary?.subsidi ?? 0 }}</div>
          <div class="sc-label">Subsidi</div>
        </div>
      </div>
      <div class="summary-card card-nonsub">
        <div class="sc-icon"><i class="pi pi-circle" /></div>
        <div class="sc-body">
          <div class="sc-value">{{ data.summary?.non_subsidi ?? 0 }}</div>
          <div class="sc-label">Non Subsidi</div>
        </div>
      </div>
      <div class="summary-card card-info">
        <div class="sc-icon"><i class="pi pi-calendar" /></div>
        <div class="sc-body">
          <div class="sc-value-sm">{{ data.mulai }}</div>
          <div class="sc-label">Mulai dihitung</div>
        </div>
      </div>
    </div>

    <div v-if="loading" class="loading-state">
      <i class="pi pi-spin pi-spinner" /> Memuat statistik...
    </div>

    <div v-else class="tables-row">
      <!-- Tabel Driver -->
      <Card class="stat-card">
        <template #header>
          <div class="card-head">
            <i class="pi pi-id-card" />
            <span>Statistik Driver</span>
            <span class="head-count">{{ data.driver_stats?.length ?? 0 }} driver</span>
          </div>
        </template>
        <template #content>
          <DataTable
            :value="data.driver_stats ?? []"
            stripedRows
            :loading="loading"
            scrollable
            scrollHeight="400px"
          >
            <Column header="No" style="width:42px;text-align:center">
              <template #body="{ index }">
                <span class="row-no">{{ index + 1 }}</span>
              </template>
            </Column>
            <Column field="nama_driver" header="Driver" style="min-width:160px">
              <template #body="{ data }">
                <span class="driver-name">{{ data.nama_driver }}</span>
              </template>
            </Column>
            <Column header="Subsidi" style="width:90px;text-align:center">
              <template #body="{ data }">
                <span v-if="data.subsidi > 0" class="badge-subsidi">{{ data.subsidi }}x</span>
                <span v-else class="badge-zero">-</span>
              </template>
            </Column>
            <Column header="Non Subsidi" style="width:110px;text-align:center">
              <template #body="{ data }">
                <span v-if="data.non_subsidi > 0" class="badge-nonsub">{{ data.non_subsidi }}x</span>
                <span v-else class="badge-zero">-</span>
              </template>
            </Column>
            <Column header="Total" style="width:80px;text-align:center">
              <template #body="{ data }">
                <span class="badge-total">{{ data.total }}x</span>
              </template>
            </Column>
            <template #empty>
              <div class="empty-state">
                <i class="pi pi-id-card" />
                <span>Tidak ada data driver pada periode ini</span>
              </div>
            </template>
          </DataTable>
        </template>
      </Card>

      <!-- Tabel Wilayah -->
      <Card class="stat-card">
        <template #header>
          <div class="card-head">
            <i class="pi pi-map-marker" />
            <span>Statistik Wilayah</span>
            <span class="head-count">{{ data.wilayah_stats?.length ?? 0 }} wilayah</span>
          </div>
        </template>
        <template #content>
          <DataTable
            :value="data.wilayah_stats ?? []"
            stripedRows
            :loading="loading"
            scrollable
            scrollHeight="400px"
          >
            <Column header="No" style="width:42px;text-align:center">
              <template #body="{ index }">
                <span class="row-no">{{ index + 1 }}</span>
              </template>
            </Column>
            <Column header="Kode" style="width:70px">
              <template #body="{ data }">
                <span class="kode-badge">{{ data.kode_area }}</span>
              </template>
            </Column>
            <Column field="nama" header="Wilayah" style="min-width:160px">
              <template #body="{ data }">
                <span class="wilayah-name">{{ data.nama }}</span>
              </template>
            </Column>
            <Column header="Pengiriman" style="width:110px;text-align:center">
              <template #body="{ data }">
                <span class="badge-total">{{ data.total }}x</span>
              </template>
            </Column>
            <template #empty>
              <div class="empty-state">
                <i class="pi pi-map-marker" />
                <span>Tidak ada data wilayah pada periode ini</span>
              </div>
            </template>
          </DataTable>
        </template>
      </Card>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useStaleRefresh } from '@/composables/useStaleRefresh'
import { useToast } from '@/composables/useToast'
import PageHeader from '@/components/common/PageHeader.vue'
import http from '@/services/http'

const toast   = useToast()
const loading = ref(false)
const periode = ref('bulan')
const data    = ref({ summary: null, driver_stats: [], wilayah_stats: [], mulai: '' })

const periodeOptions = [
  { label: 'Minggu Ini', value: 'minggu' },
  { label: 'Bulan Ini',  value: 'bulan'  },
  { label: '6 Bulan',    value: '6bulan' },
  { label: 'Setahun',    value: 'tahun'  },
]

async function loadData() {
  loading.value = true
  try {
    const res  = await http.get('/admin/pengiriman/daftar-muat/statistik', { params: { periode: periode.value } })
    data.value = res.data
  } catch {
    toast.error('Gagal memuat statistik pengiriman')
  } finally {
    loading.value = false
  }
}

function setPeriode(p) {
  periode.value = p
  loadData()
}

useStaleRefresh(loadData)
</script>

<style scoped>
.statistik-view { display: flex; flex-direction: column; gap: 1.25rem; animation: fadeUp var(--dur-base) var(--ease) both; }

/* Periode toggle */
.periode-toggle { display: flex; gap: 0.25rem; }
.periode-btn {
  padding: 0.35rem 0.85rem; border-radius: 6px; border: 1px solid var(--p-content-border-color);
  background: transparent; color: var(--p-text-color); font-size: 0.8rem; cursor: pointer;
  transition: all 0.15s;
}
.periode-btn:hover  { background: var(--p-content-hover-background); }
.periode-btn.active { background: var(--p-primary-color); color: #fff; border-color: var(--p-primary-color); font-weight: 600; }

/* Summary cards */
.summary-row { display: flex; gap: 1rem; flex-wrap: wrap; }
.summary-card {
  flex: 1; min-width: 160px; display: flex; align-items: center; gap: 1rem;
  padding: 1rem 1.25rem; border-radius: 10px; border: 1px solid var(--p-content-border-color);
  background: var(--p-content-background);
}
.sc-icon { font-size: 1.5rem; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
.sc-value { font-size: 1.6rem; font-weight: 800; line-height: 1; }
.sc-value-sm { font-size: 0.85rem; font-weight: 700; }
.sc-label { font-size: 0.75rem; color: var(--p-text-muted-color); margin-top: 2px; }

.card-total   .sc-icon { background: var(--c-brand-soft); color: var(--c-brand-600); }
.card-total   .sc-value { color: var(--c-brand-600); }
.card-subsidi .sc-icon { background: var(--c-success-soft); color: var(--c-success); }
.card-subsidi .sc-value { color: var(--c-success); }
.card-nonsub  .sc-icon { background: var(--c-nila-soft); color: var(--c-nila); }
.card-nonsub  .sc-value { color: var(--c-nila); }
.card-info    .sc-icon { background: var(--s-2); color: var(--t-secondary); }

/* Tables */
.tables-row { display: flex; gap: 1rem; flex-wrap: wrap; }
.stat-card { flex: 1; min-width: 300px; }
.stat-card :deep(.p-card-body) { padding: 0; }

.card-head {
  display: flex; align-items: center; gap: 0.5rem; padding: 0.85rem 1rem;
  border-bottom: 1px solid var(--p-content-border-color); font-weight: 700; font-size: 0.9rem;
}
.head-count { margin-left: auto; font-size: 0.75rem; font-weight: 400; color: var(--p-text-muted-color); }

.row-no { color: var(--p-text-muted-color); font-size: 0.78rem; }
.driver-name { font-weight: 600; font-size: 0.85rem; }
.wilayah-name { font-size: 0.85rem; }

.kode-badge {
  display: inline-block; padding: 2px 7px; border-radius: 5px;
  background: var(--s-2); color: var(--t-secondary); font-size: 0.75rem; font-weight: 700;
}
.badge-subsidi {
  display: inline-block; padding: 2px 8px; border-radius: 5px;
  background: var(--c-success-soft); color: var(--c-success); font-size: 0.78rem; font-weight: 700;
}
.badge-nonsub {
  display: inline-block; padding: 2px 8px; border-radius: 5px;
  background: var(--c-nila-soft); color: var(--c-nila); font-size: 0.78rem; font-weight: 700;
}
.badge-total {
  display: inline-block; padding: 2px 8px; border-radius: 5px;
  background: var(--c-brand-soft); color: var(--c-brand-600); font-size: 0.78rem; font-weight: 700;
}
.badge-zero { color: var(--p-text-muted-color); font-size: 0.78rem; }

.loading-state { display: flex; align-items: center; gap: 0.5rem; padding: 2rem; color: var(--p-text-muted-color); }

.empty-state { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; padding: 2rem; color: var(--p-text-muted-color); font-size: 0.85rem; }
.empty-state i { font-size: 1.5rem; }

/* ── Mobile responsive ─────────────────────────────────────── */
@media (max-width: 768px) {
  /* Periode toggle: wrap, equal-width buttons */
  .periode-toggle { flex-wrap: wrap; gap: 0.3rem; }
  .periode-btn    { flex: 1 1 auto; text-align: center; min-width: 80px; }

  /* Summary cards: 2 per row */
  .summary-row  { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
  .summary-card { min-width: unset; }

  /* Tables: stack vertically */
  .tables-row { flex-direction: column; }
  .stat-card  { min-width: unset; width: 100%; }
}

@media (max-width: 480px) {
  /* Summary cards: still 2 per row but more compact */
  .summary-card { padding: 0.75rem 0.875rem; gap: 0.65rem; }
  .sc-icon      { width: 32px; height: 32px; font-size: 1.1rem; }
  .sc-value     { font-size: 1.3rem; }
}

/* ── Dark mode overrides ─────────────────────────────────────── */
:global(.app-dark) .card-total   .sc-icon  { background: rgba(234,88,12,.15);   color: var(--c-brand-400); }
:global(.app-dark) .card-total   .sc-value { color: var(--c-brand-400); }
:global(.app-dark) .card-subsidi .sc-icon  { background: rgba(34,197,94,.15);   color: var(--c-success); }
:global(.app-dark) .card-subsidi .sc-value { color: var(--c-success); }
:global(.app-dark) .card-nonsub  .sc-icon  { background: rgba(99,102,241,.15);  color: var(--c-nila); }
:global(.app-dark) .card-nonsub  .sc-value { color: var(--c-nila); }
:global(.app-dark) .card-info    .sc-icon  { background: rgba(100,116,139,.15); color: var(--t-secondary); }
:global(.app-dark) .kode-badge             { background: rgba(255,255,255,.08); color: var(--p-text-color); }
:global(.app-dark) .badge-subsidi          { background: rgba(34,197,94,.15);   color: var(--c-success); }
:global(.app-dark) .badge-nonsub           { background: rgba(99,102,241,.15);  color: var(--c-nila); }
:global(.app-dark) .badge-total            { background: rgba(234,88,12,.15);   color: var(--c-brand-400); }
</style>
