<template>
  <div class="izin-report-view">
    <PageHeader title="Laporan Rekap Izin" subtitle="Ringkasan izin karyawan per periode: disetujui, ditolak, dan pending.">
      <template #actions>
        <Button icon="pi pi-arrow-left" label="Kembali" text @click="$router.push('/izin')" />
      </template>
    </PageHeader>

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

          <Button
            v-if="can('export_pdf')"
            icon="pi pi-file-pdf"
            label="PDF"
            severity="danger"
            outlined
            size="small"
            :disabled="!canDownload"
            :loading="downloadingPdf"
            @click="downloadReport('pdf')"
            v-tooltip="'Unduh PDF rekap izin'"
          />
          <Button
            v-if="can('export_excel')"
            icon="pi pi-file-excel"
            label="Excel"
            severity="success"
            outlined
            size="small"
            :disabled="!canDownload"
            :loading="downloadingExcel"
            @click="downloadReport('excel')"
            v-tooltip="'Unduh Excel rekap izin'"
          />
        </div>

        <div v-if="!dateFrom || !dateTo" class="date-hint">
          <i class="pi pi-info-circle" /> Pilih rentang tanggal untuk melihat rekap izin
        </div>
        <div v-else class="period-active">
          <i class="pi pi-calendar-clock" />
          Menampilkan data {{ periodLabel }}
        </div>
      </template>
    </Card>

    <!-- Summary Cards -->
    <div class="summary-row">
      <div class="summary-card summary-total">
        <div class="summary-icon"><i class="pi pi-file-edit" /></div>
        <div class="summary-info">
          <div class="summary-val">
            <Skeleton v-if="loading" width="3rem" height="1.8rem" />
            <span v-else>{{ summary?.total ?? 0 }}</span>
          </div>
          <div class="summary-lbl">Total Pengajuan</div>
        </div>
      </div>
      <div class="summary-card summary-disetujui">
        <div class="summary-icon"><i class="pi pi-check-circle" /></div>
        <div class="summary-info">
          <div class="summary-val">
            <Skeleton v-if="loading" width="3rem" height="1.8rem" />
            <span v-else>{{ summary?.disetujui ?? 0 }}</span>
          </div>
          <div class="summary-lbl">Disetujui</div>
        </div>
      </div>
      <div class="summary-card summary-ditolak">
        <div class="summary-icon"><i class="pi pi-times-circle" /></div>
        <div class="summary-info">
          <div class="summary-val">
            <Skeleton v-if="loading" width="3rem" height="1.8rem" />
            <span v-else>{{ summary?.ditolak ?? 0 }}</span>
          </div>
          <div class="summary-lbl">Ditolak</div>
        </div>
      </div>
      <div class="summary-card summary-pending">
        <div class="summary-icon"><i class="pi pi-clock" /></div>
        <div class="summary-info">
          <div class="summary-val">
            <Skeleton v-if="loading" width="3rem" height="1.8rem" />
            <span v-else>{{ summary?.pending ?? 0 }}</span>
          </div>
          <div class="summary-lbl">Pending</div>
        </div>
      </div>
    </div>

    <!-- Per-Sales Rekap Table -->
    <Card>
      <template #header>
        <div class="card-header-row">
          <span class="card-title">Rekap Per Sales</span>
          <div class="header-actions">
            <Select
              v-model="filterJenis"
              :options="jenisOptions"
              option-label="label"
              option-value="value"
              placeholder="Semua Jenis"
              showClear
              style="width: 160px"
              @change="loadData"
            />
          </div>
        </div>
      </template>
      <template #content>
        <DataTable
          :value="filteredItems"
          :loading="loading"
          stripedRows
          expandedRows
          v-model:expandedRows="expandedRows"
          dataKey="sales_id"
          @row-expand="onRowExpanded"
        >
          <!-- Expand toggle -->
          <Column expander style="width: 3rem" />

          <Column header="No." style="width: 56px; text-align: center">
            <template #body="{ index }">{{ index + 1 }}</template>
          </Column>

          <Column header="Sales" style="min-width: 200px">
            <template #body="{ data }">
              <div class="sales-cell">
                <Avatar
                  :label="data.nama?.[0]?.toUpperCase() || '?'"
                  shape="circle"
                  size="normal"
                  class="sales-avatar"
                />
                <div>
                  <div class="sales-nama">{{ data.nama }}</div>
                  <div class="sales-sub">{{ data.area || '-' }}</div>
                </div>
              </div>
            </template>
          </Column>

          <Column header="Disetujui" style="min-width: 100px; text-align:center">
            <template #body="{ data }">
              <Tag :value="String(data.disetujui)" severity="success" />
            </template>
          </Column>

          <Column header="Ditolak" style="min-width: 90px; text-align:center">
            <template #body="{ data }">
              <Tag :value="String(data.ditolak)" severity="danger" />
            </template>
          </Column>

          <Column header="Pending" style="min-width: 85px; text-align:center">
            <template #body="{ data }">
              <Tag :value="String(data.pending)" severity="warn" />
            </template>
          </Column>

          <Column header="Total Hari Izin" style="min-width: 120px; text-align:center">
            <template #body="{ data }">
              <span class="hari-badge">{{ data.total_hari_izin }} hari</span>
            </template>
          </Column>

          <Column header="Jenis Terbanyak" style="min-width: 130px">
            <template #body="{ data }">
              <Tag v-if="data.jenis_terbanyak" :value="jenisLabel(data.jenis_terbanyak)" :severity="jenisSeverity(data.jenis_terbanyak)" />
              <span v-else class="no-action">-</span>
            </template>
          </Column>

          <!-- Expanded row: daftar izin detail -->
          <template #expansion="{ data }">
            <div class="expansion-area">
              <DataTable
                :value="data.detail"
                :loading="loadingDetail[data.sales_id]"
                size="small"
                stripedRows
              >
                <Column field="jenis_izin" header="Jenis" style="min-width:110px">
                  <template #body="{ data: d }">
                    <Tag :value="jenisLabel(d.jenis_izin)" :severity="jenisSeverity(d.jenis_izin)" />
                  </template>
                </Column>
                <Column header="Periode" style="min-width:180px">
                  <template #body="{ data: d }">
                    {{ formatDate(d.tanggal) }}
                    <span v-if="d.sampai_tanggal && d.sampai_tanggal !== d.tanggal"> s/d {{ formatDate(d.sampai_tanggal) }}</span>
                    <span class="tgl-durasi">{{ d.durasi_hari ?? 1 }} hari</span>
                  </template>
                </Column>
                <Column field="keterangan" header="Keterangan" style="min-width:160px">
                  <template #body="{ data: d }">{{ d.keterangan || '-' }}</template>
                </Column>
                <Column field="status" header="Status" style="min-width:100px">
                  <template #body="{ data: d }">
                    <Tag :value="statusLabel(d.status)" :severity="statusSeverity(d.status)" />
                  </template>
                </Column>
                <Column field="alasan_tolak" header="Alasan Tolak" style="min-width:140px">
                  <template #body="{ data: d }">
                    <span :class="{ 'alasan-text': d.alasan_tolak }">{{ d.alasan_tolak || '-' }}</span>
                  </template>
                </Column>
                <Column header="Bukti" style="min-width:60px; text-align:center">
                  <template #body="{ data: d }">
                    <Button
                      v-if="d.bukti_foto_url"
                      icon="pi pi-image"
                      text rounded size="small" severity="info"
                      v-tooltip.top="'Lihat bukti'"
                      @click="openFoto(d.bukti_foto_url)"
                    />
                    <span v-else class="no-action">-</span>
                  </template>
                </Column>
                <template #empty>
                  <div class="inner-empty">Tidak ada data izin pada periode ini</div>
                </template>
              </DataTable>
            </div>
          </template>

          <template #empty>
            <div class="empty-state">
              <i class="pi pi-chart-bar" />
              <span>{{ dateFrom && dateTo ? 'Tidak ada data pada periode ini' : 'Pilih rentang tanggal lalu klik Terapkan' }}</span>
            </div>
          </template>
        </DataTable>
      </template>
    </Card>

    <!-- Foto Preview Dialog -->
    <Dialog v-model:visible="fotoDialog" header="Bukti Foto Izin" :style="{ width: '500px' }" modal>
      <div class="foto-preview">
        <img :src="fotoUrl" alt="Bukti Izin" class="foto-img" @error="fotoError = true" />
        <div v-if="fotoError" class="foto-error">
          <i class="pi pi-image" /> Gagal memuat gambar
        </div>
      </div>
      <template #footer>
        <Button label="Tutup" text @click="fotoDialog = false" />
        <Button icon="pi pi-external-link" label="Buka di tab baru" severity="info" outlined @click="openFotoTab" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import axios from 'axios'
import { ref, computed, onMounted } from 'vue'
import { usePermission } from '@/composables/usePermission'
const { can } = usePermission()
import { izinService } from '@/services/izin.service'
import { useToast } from '@/composables/useToast'
import { formatDate } from '@/utils/helpers'
import { API_BASE_URL, STORAGE_KEYS } from '@/utils/constants'
import PageHeader from '@/components/common/PageHeader.vue'

const toast = useToast()

const loading         = ref(false)
const loadingDetail   = ref({})
const downloadingPdf  = ref(false)
const downloadingExcel = ref(false)
const items           = ref([])
const summary         = ref({ total: 0, disetujui: 0, ditolak: 0, pending: 0 })
const expandedRows    = ref({})
const searchQuery     = ref('')
const filterJenis     = ref(null)
const dateFrom        = ref(null)
const dateTo          = ref(null)

const canDownload = computed(() => !!dateFrom.value && !!dateTo.value && items.value.length > 0)

const fotoDialog = ref(false)
const fotoUrl    = ref('')
const fotoError  = ref(false)

// Set default ke bulan ini
onMounted(() => {
  const now   = new Date()
  dateFrom.value = new Date(now.getFullYear(), now.getMonth(), 1)
  dateTo.value   = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  loadData()
})

const jenisOptions = [
  { label: 'Sakit',        value: 'sakit'        },
  { label: 'Izin',         value: 'izin'          },
  { label: 'Cuti',         value: 'cuti'          },
  { label: 'Terlambat',    value: 'terlambat'    },
  { label: 'Pulang Cepat', value: 'pulang_cepat' },
]

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

function toYmd(date) {
  if (!date) return null
  const d = new Date(date)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

async function loadData() {
  if (!dateFrom.value || !dateTo.value) return
  loading.value = true
  expandedRows.value = {}
  try {
    const params = { dari: toYmd(dateFrom.value), sampai: toYmd(dateTo.value) }
    if (filterJenis.value) params.jenis_izin = filterJenis.value

    const res  = await izinService.getRecap(params)
    const payload = res?.data ?? res
    items.value   = (payload?.per_sales ?? []).map(s => ({ ...s, detail: [] }))
    summary.value = payload?.summary ?? { total: 0, disetujui: 0, ditolak: 0, pending: 0 }

    // Lazy load detail saat row expand
  } catch (err) {
    toast.apiError(err)
  } finally {
    loading.value = false
  }
}

// Ketika row expand: fetch detail izin sales tsb
async function onRowExpand({ data }) {
  if (data.detail?.length > 0) return
  loadingDetail.value[data.sales_id] = true
  try {
    const params = {
      sales_id: data.sales_id,
      dari:     toYmd(dateFrom.value),
      sampai:   toYmd(dateTo.value),
      per_page: 100,
    }
    if (filterJenis.value) params.jenis_izin = filterJenis.value
    const res = await izinService.getAll(params)
    // izinService.getAll sudah destructure {data}, jadi res = response body
    const payload = res?.data ?? res
    data.detail = Array.isArray(payload) ? payload : (payload?.data ?? [])
  } catch { /* silent */ } finally {
    loadingDetail.value[data.sales_id] = false
  }
}

async function downloadReport(type) {
  const dari   = toYmd(dateFrom.value)
  const sampai = toYmd(dateTo.value)
  const params = new URLSearchParams({ dari, sampai })
  const ext      = type === 'pdf' ? 'pdf' : 'xlsx'
  const filename = `Rekap_Izin_${dari}_${sampai}.${ext}`
  const loadingRef = type === 'pdf' ? downloadingPdf : downloadingExcel

  loadingRef.value = true
  try {
    const token = localStorage.getItem(STORAGE_KEYS.TOKEN)
    const resp = await axios.get(`${API_BASE_URL}/admin/report/izin-${type}?${params}`, {
      responseType: 'blob',
      headers: { Authorization: `Bearer ${token}` },
    })
    const a = document.createElement('a')
    a.href = URL.createObjectURL(resp.data)
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

// Watch expand — DataTable fires row-expand
function onRowExpanded(event) {
  onRowExpand(event)
}

function resetFilter() {
  const now = new Date()
  dateFrom.value  = new Date(now.getFullYear(), now.getMonth(), 1)
  dateTo.value    = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  filterJenis.value = null
  searchQuery.value = ''
  loadData()
}

function statusLabel(status) {
  const map = { pending: 'Pending', disetujui: 'Disetujui', ditolak: 'Ditolak', approved: 'Disetujui', rejected: 'Ditolak' }
  return map[status] || status
}
function statusSeverity(status) {
  const map = { pending: 'warn', disetujui: 'success', ditolak: 'danger', approved: 'success', rejected: 'danger' }
  return map[status] || 'secondary'
}
function jenisLabel(jenis) {
  const map = { sakit: 'Sakit', izin: 'Izin', cuti: 'Cuti', terlambat: 'Terlambat', pulang_cepat: 'Pulang Cepat' }
  return map[jenis] || (jenis ?? '-')
}
function jenisSeverity(jenis) {
  const map = { sakit: 'danger', izin: 'info', cuti: 'success', terlambat: 'warn', pulang_cepat: 'secondary' }
  return map[jenis] || 'info'
}

function openFoto(url) {
  fotoUrl.value    = url
  fotoError.value  = false
  fotoDialog.value = true
}

function openFotoTab() {
  window.open(fotoUrl.value, '_blank')
}
</script>

<style scoped>
.izin-report-view { display: flex; flex-direction: column; gap: 1.25rem; }

.filter-card :deep(.p-card-body) { padding: 0.75rem 1rem; }
.filter-bar {
  display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;
}
.search-field { flex: 1; min-width: 200px; }
.date-range { display: flex; align-items: center; gap: 0.5rem; }
.date-sep { color: var(--p-text-muted-color); }
.date-hint { display: flex; align-items: center; gap: 0.4rem; margin-top: 0.75rem; color: var(--p-text-muted-color); font-size: 0.85rem; }
.period-active { display: flex; align-items: center; gap: 0.4rem; margin-top: 0.75rem; color: var(--p-primary-color); font-size: 0.85rem; font-weight: 500; }

/* Summary cards */
.summary-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
}
@media (max-width: 768px) { .summary-row { grid-template-columns: repeat(2, 1fr); } }

.summary-card {
  display: flex; align-items: center; gap: 1rem;
  padding: 1rem 1.25rem;
  border-radius: 10px;
  background: var(--s-0);
  border: 1px solid var(--p-content-border-color);
  box-shadow: 0 1px 4px rgba(0,0,0,.07);
}
.summary-icon {
  width: 44px; height: 44px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.25rem; flex-shrink: 0;
}
.summary-val { font-size: 1.6rem; font-weight: 700; line-height: 1; color: var(--p-text-color); }
.summary-lbl { font-size: 0.78rem; color: var(--p-text-muted-color); margin-top: 2px; }

.summary-total     .summary-icon { background: rgba(99,102,241,.12); color: var(--c-nila); }
.summary-disetujui .summary-icon { background: rgba(22,163,74,.12);  color: var(--c-success); }
.summary-ditolak   .summary-icon { background: rgba(220,38,38,.12);  color: var(--c-danger); }
.summary-pending   .summary-icon { background: rgba(202,138,4,.12);  color: var(--c-warning); }

.summary-disetujui { border-left: 4px solid var(--c-success); }
.summary-ditolak   { border-left: 4px solid var(--c-danger); }
.summary-pending   { border-left: 4px solid var(--c-warning); }
.summary-total     { border-left: 4px solid var(--c-nila); }

.divider-v { width: 1px; height: 28px; background: var(--p-content-border-color); margin: 0 0.2rem; }

/* Card header */
.card-header-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0.75rem 1.25rem 0;
}
.card-title { font-size: 0.9375rem; font-weight: 600; }

/* Sales cell */
.sales-cell { display: flex; align-items: center; gap: 0.75rem; }
.sales-avatar { background: var(--p-primary-100) !important; color: var(--p-primary-700) !important; flex-shrink: 0; }
.sales-nama { font-size: 0.875rem; font-weight: 600; }
.sales-sub  { font-size: 0.75rem; color: var(--p-text-muted-color); }

.hari-badge {
  display: inline-block;
  padding: 2px 8px;
  background: var(--p-primary-50);
  color: var(--p-primary-600);
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 600;
}

/* Expansion */
.expansion-area {
  padding: 0.75rem 1.5rem;
  background: var(--s-1);
  border-top: 1px solid var(--s-border);
}
.tgl-durasi {
  display: inline-block;
  margin-left: 4px;
  font-size: 0.7rem;
  background: var(--p-primary-50);
  color: var(--p-primary-600);
  padding: 1px 5px;
  border-radius: 4px;
}
.alasan-text { color: var(--p-red-600); font-size: 0.8125rem; }
.inner-empty { padding: 1rem; color: var(--p-text-muted-color); font-size: 0.85rem; }
.no-action { color: var(--p-text-muted-color); font-size: 0.85rem; }

.empty-state {
  display: flex; flex-direction: column; align-items: center;
  gap: 0.5rem; padding: 3rem; color: var(--p-text-muted-color);
}
.empty-state i { font-size: 2.5rem; }

/* Foto */
.foto-preview { display: flex; justify-content: center; padding: 0.5rem; }
.foto-img { max-width: 100%; max-height: 400px; border-radius: 8px; object-fit: contain; }
.foto-error { display: flex; align-items: center; gap: 0.5rem; color: var(--p-text-muted-color); padding: 2rem; }

/* ── Dark mode overrides ─────────────────────────────────────── */
:global(.app-dark) .summary-total     .summary-icon { color: var(--c-nila); }
:global(.app-dark) .summary-disetujui .summary-icon { color: var(--c-success); }
:global(.app-dark) .summary-ditolak   .summary-icon { color: var(--c-danger); }
:global(.app-dark) .summary-pending   .summary-icon { color: var(--c-warning); }
</style>
