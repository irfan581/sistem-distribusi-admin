<template>
  <div class="report-view">
    <PageHeader title="Laporan Presensi" subtitle="Rekap kehadiran dan kunjungan karyawan per periode." />

    <!-- Filter Bar -->
    <Card class="filter-card">
      <template #content>
        <div class="filter-bar">
          <IconField class="search-field">
            <InputIcon class="pi pi-search" />
            <InputText v-model="searchQuery" placeholder="Cari nama sales..." />
          </IconField>

          <div class="date-range">
            <DatePicker
              v-model="dateFrom"
              dateFormat="dd/mm/yy"
              placeholder="Dari tanggal"
              showIcon
              :maxDate="dateTo || undefined"
              style="width: 155px"
            />
            <span class="date-sep">sampai</span>
            <DatePicker
              v-model="dateTo"
              dateFormat="dd/mm/yy"
              placeholder="Sampai tanggal"
              showIcon
              :minDate="dateFrom || undefined"
              style="width: 155px"
            />
          </div>

          <Button icon="pi pi-search" label="Terapkan" @click="loadData" :loading="loading" :disabled="!dateFrom || !dateTo" />
          <Button icon="pi pi-refresh" text plain @click="resetFilter" v-tooltip="'Reset ke bulan ini'" />

          <div class="divider-v" />

          <!-- Global download -->
          <Button
            v-if="can('export_pdf')"
            icon="pi pi-file-pdf"
            label="Semua PDF"
            severity="danger"
            outlined
            size="small"
            :disabled="!canDownload"
            :loading="downloadingAllPdf"
            @click="downloadAll('pdf')"
            v-tooltip="'Unduh PDF rekap semua sales'"
          />
          <Button
            v-if="can('export_excel')"
            icon="pi pi-file-excel"
            label="Semua Excel"
            severity="success"
            outlined
            size="small"
            :disabled="!canDownload"
            :loading="downloadingAllExcel"
            @click="downloadAll('excel')"
            v-tooltip="'Unduh Excel rekap semua sales (multi-sheet)'"
          />
        </div>

        <div v-if="!dateFrom || !dateTo" class="date-hint">
          <i class="pi pi-info-circle" /> Pilih rentang tanggal untuk melihat data dan mengunduh laporan
        </div>
        <div v-else class="period-active">
          <i class="pi pi-calendar-clock" />
          Menampilkan data {{ periodLabel }}
        </div>
      </template>
    </Card>

    <!-- Summary Table -->
    <Card>
      <template #content>
        <DataTable
          :value="filteredItems"
          :loading="loading"
          stripedRows
        >
          <Column header="No." style="width: 56px; text-align: center">
            <template #body="{ index }">{{ index + 1 }}</template>
          </Column>
          <Column header="Sales" style="min-width: 200px">
            <template #body="{ data }">
              <div class="sales-cell">
                <SalesAvatar :src="data.foto_profil_url" :nama="data.nama" :size="36" />
                <div>
                  <div class="sales-nama">{{ data.nama }}</div>
                  <div class="sales-sub">{{ data.area || '-' }}</div>
                </div>
              </div>
            </template>
          </Column>

          <Column field="total_hadir" header="Hadir" style="min-width: 75px; text-align: center">
            <template #body="{ data }">
              <Tag :value="String(data.total_hadir ?? 0)" severity="success" />
            </template>
          </Column>

          <Column field="tepat_waktu" header="Tepat Waktu" style="min-width: 100px; text-align: center">
            <template #body="{ data }">
              <Tag :value="String(data.tepat_waktu ?? 0)" severity="success" />
            </template>
          </Column>

          <Column field="terlambat" header="Terlambat" style="min-width: 90px; text-align: center">
            <template #body="{ data }">
              <Tag :value="String(data.terlambat ?? 0)" severity="warn" />
            </template>
          </Column>

          <Column field="total_kunjungan" header="Kunjungan" style="min-width: 95px; text-align: center">
            <template #body="{ data }">
              <Tag :value="String(data.total_kunjungan ?? 0)" severity="info" />
            </template>
          </Column>

          <Column field="total_izin" header="Izin" style="min-width: 75px; text-align: center">
            <template #body="{ data }">
              <Tag :value="String(data.total_izin ?? 0)" severity="secondary" />
            </template>
          </Column>

          <Column header="Tingkat Hadir" style="min-width: 130px">
            <template #body="{ data }">
              <div class="progress-cell">
                <ProgressBar :value="attendanceRate(data)" :showValue="false" style="height: 6px; flex: 1" />
                <span class="rate-label">{{ attendanceRate(data) }}%</span>
              </div>
            </template>
          </Column>

          <!-- Per-row download buttons -->
          <Column v-if="can('export_pdf') || can('export_excel')" header="Unduh Rekap" style="min-width: 140px">
            <template #body="{ data }">
              <div class="row-actions">
                <Button
                  v-if="can('export_pdf')"
                  icon="pi pi-file-pdf"
                  label="PDF"
                  size="small"
                  severity="danger"
                  outlined
                  :disabled="!canDownload"
                  :loading="!!rowLoading[data.id + '_pdf']"
                  @click="downloadRow(data, 'pdf')"
                  v-tooltip="'Unduh PDF rekap ' + data.nama"
                />
                <Button
                  v-if="can('export_excel')"
                  icon="pi pi-file-excel"
                  label="Excel"
                  size="small"
                  severity="success"
                  outlined
                  :disabled="!canDownload"
                  :loading="!!rowLoading[data.id + '_excel']"
                  @click="downloadRow(data, 'excel')"
                  v-tooltip="'Unduh Excel rekap ' + data.nama"
                />
              </div>
            </template>
          </Column>

          <template #empty>
            <div class="empty-state">
              <i class="pi pi-chart-bar" />
              <span>{{ dateFrom && dateTo ? 'Tidak ada data pada periode ini' : 'Pilih rentang tanggal lalu klik Terapkan' }}</span>
            </div>
          </template>
        </DataTable>
      </template>
    </Card>
  </div>
</template>

<script setup>
import axios from 'axios'
import { ref, computed, onMounted } from 'vue'
import { usePermission } from '@/composables/usePermission'
const { can } = usePermission()
import { dashboardService } from '@/services/dashboard.service'
import { useToast } from '@/composables/useToast'
import { API_BASE_URL, STORAGE_KEYS } from '@/utils/constants'
import PageHeader from '@/components/common/PageHeader.vue'

const toast = useToast()
const loading = ref(false)
const downloadingAllPdf = ref(false)
const downloadingAllExcel = ref(false)
const rowLoading = ref({})   // { '5_pdf': true, '5_excel': false, ... }
const items = ref([])
const searchQuery = ref('')
const dateFrom = ref(null)
const dateTo = ref(null)

const canDownload = computed(() => !!dateFrom.value && !!dateTo.value && items.value.length > 0)

const periodLabel = computed(() => {
  if (!dateFrom.value || !dateTo.value) return ''
  const fmt = (d) => new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })
  return `${fmt(dateFrom.value)} sampai ${fmt(dateTo.value)}`
})

const filteredItems = computed(() => {
  if (!searchQuery.value) return items.value
  const q = searchQuery.value.toLowerCase()
  return items.value.filter((i) => i.nama?.toLowerCase().includes(q) || i.area?.toLowerCase().includes(q))
})

function attendanceRate(data) {
  if (!dateFrom.value || !dateTo.value) return 0
  const days = Math.round((new Date(dateTo.value) - new Date(dateFrom.value)) / 86400000) + 1
  if (!days) return 0
  return Math.min(100, Math.round(((data.total_hadir ?? 0) / days) * 100))
}

function toYmd(date) {
  if (!date) return null
  const d = new Date(date)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

async function binaryDownload(url, filename, loadingRef) {
  loadingRef.value = true
  try {
    const token = localStorage.getItem(STORAGE_KEYS.TOKEN)
    if (!token) {
      toast.error('Token otentikasi tidak ditemukan. Silakan login ulang.')
      loadingRef.value = false
      return
    }
    const resp = await axios.get(url, {
      responseType: 'blob',
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
    })
    if (resp.status !== 200) {
      const err = await resp.data?.message || 'Gagal mengunduh laporan'
      toast.error(err)
      return
    }
    const blob = resp.data
    const a = document.createElement('a')
    a.style.display = 'none'
    a.href = URL.createObjectURL(blob)
    a.download = filename
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(a.href)
  } catch (e) {
    toast.error('Gagal mengunduh: ' + e.message)
  } finally {
    loadingRef.value = false
  }
}

async function downloadRow(rowData, type) {
  const dari = toYmd(dateFrom.value)
  const sampai = toYmd(dateTo.value)
  const params = new URLSearchParams({ dari, sampai, sales_id: rowData.id })
  const ext = type === 'pdf' ? 'pdf' : 'xlsx'
  const filename = `Rekap_${rowData.nama.replace(/\s+/g, '_')}_${dari}_${sampai}.${ext}`
  const key = `${rowData.id}_${type}`

  // Use a dedicated ref per row-type key so binaryDownload can toggle it
  const loadingRef = {
    get value() { return !!rowLoading.value[key] },
    set value(v) { rowLoading.value = { ...rowLoading.value, [key]: v } },
  }
  await binaryDownload(`${API_BASE_URL}/admin/report/${type}?${params}`, filename, loadingRef)
}

async function downloadAll(type) {
  const dari = toYmd(dateFrom.value)
  const sampai = toYmd(dateTo.value)
  const params = new URLSearchParams({ dari, sampai })
  const ext = type === 'pdf' ? 'pdf' : 'xlsx'
  const filename = `Rekap_Semua_Sales_${dari}_${sampai}.${ext}`
  const loadingRef = type === 'pdf' ? downloadingAllPdf : downloadingAllExcel
  await binaryDownload(`${API_BASE_URL}/admin/report/${type}?${params}`, filename, loadingRef)
}

async function loadData() {
  loading.value = true
  try {
    const params = {}
    const dari = toYmd(dateFrom.value)
    const sampai = toYmd(dateTo.value)
    if (dari && sampai) { params.dari = dari; params.sampai = sampai }

    const res = await dashboardService.getMonthlySummary(params)
    const raw = res.data?.data ?? res.data
    items.value = Array.isArray(raw) ? raw : Object.values(raw ?? {}).filter((i) => typeof i === 'object' && i !== null)
  } catch (err) {
    toast.apiError(err)
  } finally {
    loading.value = false
  }
}

function resetFilter() {
  const now = new Date()
  dateFrom.value = new Date(now.getFullYear(), now.getMonth(), 1)
  dateTo.value = now
  loadData()
}

onMounted(() => {
  const now = new Date()
  dateFrom.value = new Date(now.getFullYear(), now.getMonth(), 1)
  dateTo.value = now
  loadData()
})
</script>

<style scoped>
.report-view { display: flex; flex-direction: column; gap: 1.25rem; }

.filter-card :deep(.p-card-body) { padding: 0.75rem 1rem; }

.filter-bar {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
}

.search-field { min-width: 190px; flex: 1; }

.date-range { display: flex; align-items: center; gap: 0.35rem; }
.date-sep { color: var(--p-text-muted-color); font-weight: 600; }

.divider-v { width: 1px; height: 28px; background: var(--p-content-border-color); margin: 0 0.2rem; }

.date-hint {
  margin-top: 0.5rem;
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.period-active {
  margin-top: 0.4rem;
  font-size: 0.8rem;
  color: var(--p-primary-color);
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-weight: 500;
}

.sales-cell { display: flex; align-items: center; gap: 0.75rem; }
.sales-avatar {
  background: var(--p-primary-100) !important;
  color: var(--p-primary-700) !important;
  flex-shrink: 0;
}
.sales-nama { font-size: 0.875rem; font-weight: 600; color: var(--p-text-color); }
.sales-sub { font-size: 0.75rem; color: var(--p-text-muted-color); }

.progress-cell { display: flex; align-items: center; gap: 0.5rem; }
.rate-label { font-size: 0.8rem; font-weight: 600; color: var(--p-text-color); white-space: nowrap; }

.row-actions { display: flex; gap: 0.4rem; align-items: center; }

.empty-state {
  display: flex; flex-direction: column; align-items: center;
  gap: 0.5rem; padding: 3rem; color: var(--p-text-muted-color);
}
.empty-state i { font-size: 2.5rem; }
</style>
