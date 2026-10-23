<template>
  <div class="izin-view">
    <PageHeader title="Izin Karyawan" subtitle="Kelola dan proses pengajuan izin karyawan.">
      <template #actions>
        <span class="total-label">Pending: <strong>{{ totalPending }}</strong></span>
        <Button
          icon="pi pi-chart-bar"
          label="Laporan Izin"
          size="small"
          severity="secondary"
          outlined
          @click="$router.push('/izin/report')"
          v-tooltip="'Rekap izin per sales'"
        />
        <Button icon="pi pi-refresh" text plain @click="loadData" aria-label="Refresh" />
      </template>
    </PageHeader>

    <!-- Filter Bar -->
    <Card class="filter-card">
      <template #content>
        <div class="filter-bar">
          <IconField class="search-field">
            <InputIcon class="pi pi-search" />
            <InputText v-model="searchQuery" placeholder="Cari nama sales..." @input="onSearch" />
          </IconField>
          <Select
            v-model="filterStatus"
            :options="statusOptions"
            option-label="label"
            option-value="value"
            placeholder="Semua Status"
            showClear
            @change="resetAndLoad"
            style="width: 160px"
          />
          <Select
            v-model="filterJenis"
            :options="jenisOptions"
            option-label="label"
            option-value="value"
            placeholder="Semua Jenis"
            showClear
            @change="resetAndLoad"
            style="width: 160px"
          />
        </div>
      </template>
    </Card>

    <!-- Table -->
    <Card>
      <template #content>
        <DataTable
          :value="items"
          :loading="loading"
          :rows="perPage"
          :totalRecords="totalRecords"
          :first="(currentPage - 1) * perPage"
          lazy
          paginator
          stripedRows
          @page="onPage"
          currentPageReportTemplate="{first} - {last} dari {totalRecords}"
          paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
          :rowsPerPageOptions="[10, 25, 50]"
        >
          <Column header="No." style="width: 56px; text-align: center">
            <template #body="{ index }">{{ (currentPage - 1) * perPage + index + 1 }}</template>
          </Column>
          <Column header="Sales" style="min-width: 180px">
            <template #body="{ data }">
              <div class="sales-cell">
                <Avatar
                  :label="data.sales?.nama?.[0]?.toUpperCase() || '?'"
                  shape="circle"
                  size="normal"
                  class="sales-avatar"
                />
                <div>
                  <div class="sales-nama">{{ data.sales?.nama || '-' }}</div>
                  <div class="sales-sub">{{ data.sales?.area || '-' }}</div>
                </div>
              </div>
            </template>
          </Column>

          <Column field="jenis_izin" header="Jenis" style="min-width: 110px">
            <template #body="{ data }">
              <Tag :value="jenisLabel(data.jenis_izin)" :severity="jenisSeverity(data.jenis_izin)" />
            </template>
          </Column>

          <Column header="Periode" style="min-width: 160px">
            <template #body="{ data }">
              <div class="tanggal-cell">
                <span class="tgl-main">{{ formatDate(data.tanggal) }}</span>
                <span v-if="data.sampai_tanggal && data.sampai_tanggal !== data.tanggal" class="tgl-end">
                  s/d {{ formatDate(data.sampai_tanggal) }}
                </span>
                <span class="tgl-durasi">{{ data.durasi_hari ?? 1 }} hari</span>
              </div>
            </template>
          </Column>

          <Column field="keterangan" header="Keterangan" style="min-width: 160px">
            <template #body="{ data }">
              <span class="keterangan-text" :title="data.keterangan">{{ data.keterangan || '-' }}</span>
            </template>
          </Column>

          <Column field="status" header="Status" style="min-width: 115px">
            <template #body="{ data }">
              <Tag
                :value="statusLabel(data.status)"
                :severity="statusSeverity(data.status)"
              />
            </template>
          </Column>

          <Column header="Bukti" style="min-width: 70px; text-align:center">
            <template #body="{ data }">
              <Button
                v-if="data.bukti_foto_url"
                icon="pi pi-image"
                text
                rounded
                size="small"
                severity="info"
                v-tooltip.top="'Lihat bukti foto'"
                @click="openFoto(data.bukti_foto_url)"
              />
              <span v-else class="no-action">-</span>
            </template>
          </Column>

          <Column field="alasan_tolak" header="Alasan Tolak" style="min-width: 140px">
            <template #body="{ data }">
              <span :class="{ 'alasan-text': data.alasan_tolak }">{{ data.alasan_tolak || '-' }}</span>
            </template>
          </Column>

          <Column header="Aksi" style="min-width: 130px">
            <template #body="{ data }">
              <div class="action-buttons">
                <template v-if="data.status === 'pending' && can('approve_izin')">
                  <Button
                    icon="pi pi-check"
                    size="small"
                    severity="success"
                    text
                    rounded
                    :loading="data._loading"
                    v-tooltip.top="'Setujui'"
                    @click="approve(data)"
                  />
                  <Button
                    icon="pi pi-times"
                    size="small"
                    severity="danger"
                    text
                    rounded
                    :loading="data._loading"
                    v-tooltip.top="'Tolak'"
                    @click="openRejectDialog(data)"
                  />
                </template>
                <template v-else-if="data.status !== 'pending'">
                  <i
                    :class="data.status === 'disetujui' ? 'pi pi-check-circle text-green' : 'pi pi-times-circle text-red'"
                    v-tooltip.top="statusLabel(data.status)"
                    style="font-size:1.1rem; padding:0.35rem"
                  />
                </template>
                <Button
                  v-if="can('delete_izin')"
                  icon="pi pi-trash"
                  size="small"
                  severity="danger"
                  text
                  rounded
                  :loading="data._deleting"
                  v-tooltip.top="'Hapus'"
                  @click="openDeleteDialog(data)"
                />
              </div>
            </template>
          </Column>

          <template #empty>
            <div class="empty-state">
              <i class="pi pi-file-edit" />
              <span>Tidak ada data izin ditemukan</span>
            </div>
          </template>
        </DataTable>
      </template>
    </Card>

    <!-- Reject Dialog -->
    <Dialog v-model:visible="rejectDialog" header="Tolak Izin" :style="{ width: '420px' }" modal>
      <div class="reject-form">
        <div class="reject-info">
          <Avatar :label="rejectTarget?.sales?.nama?.[0]?.toUpperCase() || '?'" shape="circle" class="sales-avatar" />
          <div>
            <div class="sales-nama">{{ rejectTarget?.sales?.nama }}</div>
            <div class="sales-sub">{{ jenisLabel(rejectTarget?.jenis_izin) }} · {{ formatDate(rejectTarget?.tanggal) }}</div>
          </div>
        </div>
        <label class="form-label">Alasan Penolakan</label>
        <Textarea
          v-model="rejectReason"
          placeholder="Masukkan alasan penolakan..."
          rows="3"
          class="w-full"
        />
      </div>
      <template #footer>
        <Button label="Batal" text @click="rejectDialog = false" />
        <Button label="Tolak Izin" severity="danger" :loading="rejectLoading" @click="confirmReject" />
      </template>
    </Dialog>

    <!-- Delete Confirm Dialog -->
    <Dialog v-model:visible="deleteDialog" header="Hapus Data Izin" :style="{ width: '400px' }" modal>
      <div class="delete-confirm">
        <i class="pi pi-exclamation-triangle delete-icon" />
        <div class="delete-body">
          <p class="delete-title">Yakin ingin menghapus data izin ini?</p>
          <div class="delete-info">
            <span class="sales-nama">{{ deleteTarget?.sales?.nama }}</span>
            <span class="sales-sub">
              {{ jenisLabel(deleteTarget?.jenis_izin) }} ·
              {{ formatDate(deleteTarget?.tanggal) }} ·
              <Tag :value="statusLabel(deleteTarget?.status)" :severity="statusSeverity(deleteTarget?.status)" style="font-size:0.7rem" />
            </span>
          </div>
          <p class="delete-warn">Tindakan ini tidak dapat dibatalkan.</p>
        </div>
      </div>
      <template #footer>
        <Button label="Batal" text @click="deleteDialog = false" />
        <Button label="Ya, Hapus" severity="danger" :loading="deleteLoading" @click="confirmDelete" />
      </template>
    </Dialog>

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
import { ref, computed, onMounted } from 'vue'
import { usePullRefresh } from '@/composables/usePullRefresh'
import { useRouter } from 'vue-router'
import { izinService } from '@/services/izin.service'
import { useToast } from '@/composables/useToast'
import { usePermission } from '@/composables/usePermission'
import { formatDate, debounce } from '@/utils/helpers'
import PageHeader from '@/components/common/PageHeader.vue'

const toast   = useToast()
const router  = useRouter()
const { can } = usePermission()

const items        = ref([])
const loading      = ref(false)
const totalRecords = ref(0)
const currentPage  = ref(1)
const perPage      = ref(10)
const searchQuery  = ref('')
const filterStatus = ref('pending')
const filterJenis  = ref(null)

const rejectDialog  = ref(false)
const rejectReason  = ref('')
const rejectLoading = ref(false)
const rejectTarget  = ref(null)

const fotoDialog = ref(false)
const fotoUrl    = ref('')
const fotoError  = ref(false)

const deleteDialog  = ref(false)
const deleteTarget  = ref(null)
const deleteLoading = ref(false)

// totalPending selalu diambil dari counter terpisah
const pendingCount = ref(0)
const totalPending = computed(() => pendingCount.value)

const statusOptions = [
  { label: 'Pending',   value: 'pending'   },
  { label: 'Disetujui', value: 'disetujui' },
  { label: 'Ditolak',   value: 'ditolak'   },
]

const jenisOptions = [
  { label: 'Sakit',       value: 'sakit'       },
  { label: 'Izin',        value: 'izin'         },
  { label: 'Cuti',        value: 'cuti'         },
  { label: 'Terlambat',   value: 'terlambat'   },
  { label: 'Pulang Cepat',value: 'pulang_cepat'},
]

function statusLabel(status) {
  const map = {
    pending:   'Pending',
    disetujui: 'Disetujui',
    ditolak:   'Ditolak',
    approved:  'Disetujui',
    rejected:  'Ditolak',
  }
  return map[status] || status
}

function statusSeverity(status) {
  const map = {
    pending:   'warn',
    disetujui: 'success',
    ditolak:   'danger',
    approved:  'success',
    rejected:  'danger',
  }
  return map[status] || 'secondary'
}

function jenisLabel(jenis) {
  const map = {
    sakit:       'Sakit',
    izin:        'Izin',
    cuti:        'Cuti',
    terlambat:   'Terlambat',
    pulang_cepat:'Pulang Cepat',
  }
  return map[jenis] || (jenis ?? '-')
}

function jenisSeverity(jenis) {
  const map = {
    sakit:       'danger',
    izin:        'info',
    cuti:        'success',
    terlambat:   'warn',
    pulang_cepat:'secondary',
  }
  return map[jenis] || 'info'
}

async function loadData() {
  loading.value = true
  try {
    const params = { page: currentPage.value, per_page: perPage.value }
    if (searchQuery.value) params.search     = searchQuery.value
    if (filterStatus.value) params.status    = filterStatus.value
    if (filterJenis.value)  params.jenis_izin = filterJenis.value

    const res = await izinService.getAll(params)
    const paginated = res.data
    items.value = (paginated.data || paginated).map((i) => ({ ...i, _loading: false, _deleting: false }))
    totalRecords.value = paginated.total || items.value.length
  } catch (err) {
    toast.apiError(err)
  } finally {
    loading.value = false
  }
  loadPendingCount()
}

async function loadPendingCount() {
  try {
    const res = await izinService.getAll({ status: 'pending', per_page: 1 })
    pendingCount.value = res.data?.total ?? 0
  } catch { /* silent */ }
}

function onPage(event) {
  currentPage.value = event.page + 1
  perPage.value     = event.rows
  loadData()
}

function resetAndLoad() {
  currentPage.value = 1
  loadData()
}

const onSearch = debounce(resetAndLoad, 400)

async function approve(izin) {
  izin._loading = true
  try {
    await izinService.approve(izin.id, 'approved')
    toast.success('Izin disetujui, notifikasi dikirim ke sales')
    loadData()
  } catch (err) {
    toast.apiError(err)
    izin._loading = false
  }
}

function openRejectDialog(izin) {
  rejectTarget.value = izin
  rejectReason.value = ''
  rejectDialog.value = true
}

async function confirmReject() {
  if (!rejectTarget.value) return
  rejectLoading.value = true
  try {
    await izinService.approve(rejectTarget.value.id, 'rejected', rejectReason.value || 'Ditolak oleh admin')
    toast.warn('Izin ditolak')
    rejectDialog.value = false
    loadData()
  } catch (err) {
    toast.apiError(err)
  } finally {
    rejectLoading.value = false
  }
}

function openDeleteDialog(izin) {
  deleteTarget.value = izin
  deleteDialog.value = true
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  deleteLoading.value = true
  try {
    await izinService.deleteIzin(deleteTarget.value.id)
    toast.success('Data izin berhasil dihapus')
    deleteDialog.value = false
    loadData()
  } catch (err) {
    toast.apiError(err)
  } finally {
    deleteLoading.value = false
  }
}

function openFoto(url) {
  fotoUrl.value   = url
  fotoError.value = false
  fotoDialog.value = true
}

function openFotoTab() {
  window.open(fotoUrl.value, '_blank')
}

onMounted(loadData)
usePullRefresh(loadData)
</script>

<style scoped>
.izin-view { display: flex; flex-direction: column; gap: 1.25rem; animation: fadeUp var(--dur-base) var(--ease) both; }

.filter-card :deep(.p-card-body) { padding: 0.75rem 1rem; }

.filter-bar {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.search-field { flex: 1; min-width: 220px; }

.total-label { font-size: 0.875rem; color: var(--p-text-muted-color); }

.sales-cell { display: flex; align-items: center; gap: 0.75rem; }

.sales-avatar {
  background: var(--p-primary-100) !important;
  color: var(--p-primary-700) !important;
  flex-shrink: 0;
}

.sales-nama { font-size: 0.875rem; font-weight: 600; color: var(--p-text-color); }
.sales-sub { font-size: 0.75rem; color: var(--p-text-muted-color); }

.tanggal-cell { display: flex; flex-direction: column; gap: 1px; }
.tgl-main { font-size: 0.875rem; font-weight: 500; }
.tgl-end { font-size: 0.75rem; color: var(--p-text-muted-color); }
.tgl-durasi {
  font-size: 0.7rem;
  background: var(--p-primary-50);
  color: var(--p-primary-600);
  padding: 1px 5px;
  border-radius: 4px;
  width: fit-content;
}

.keterangan-text {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  font-size: 0.8125rem;
}

.alasan-text { color: var(--p-red-600); font-size: 0.8125rem; }

.action-buttons { display: flex; gap: 0.15rem; }
.status-icon { display: flex; align-items: center; padding: 0.25rem 0; }

.text-green { color: var(--p-green-500); font-size: 1.1rem; }
.text-red   { color: var(--p-red-500);   font-size: 1.1rem; }
.text-orange{ color: var(--p-orange-500);font-size: 1.1rem; }

.no-action { color: var(--p-text-muted-color); font-size: 0.85rem; }

.empty-state {
  display: flex; flex-direction: column; align-items: center;
  gap: 0.5rem; padding: 3rem; color: var(--p-text-muted-color);
}
.empty-state i { font-size: 2.5rem; }

.reject-form { display: flex; flex-direction: column; gap: 0.75rem; }
.reject-info { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--s-1); border-radius: 8px; }
.form-label { font-size: 0.875rem; font-weight: 600; }

.foto-preview { display: flex; justify-content: center; padding: 0.5rem; }
.foto-img { max-width: 100%; max-height: 400px; border-radius: 8px; object-fit: contain; }
.foto-error { display: flex; align-items: center; gap: 0.5rem; color: var(--p-text-muted-color); padding: 2rem; }

.delete-confirm { display: flex; gap: 1rem; align-items: flex-start; padding: 0.5rem 0; }
.delete-icon { font-size: 2rem; color: var(--p-orange-500); margin-top: 2px; flex-shrink: 0; }
.delete-body { display: flex; flex-direction: column; gap: 0.5rem; }
.delete-title { font-size: 0.9375rem; font-weight: 600; margin: 0; }
.delete-info { display: flex; flex-direction: column; gap: 2px; padding: 0.5rem 0.75rem; background: var(--s-1); border-radius: 8px; }
.delete-warn { font-size: 0.8125rem; color: var(--p-red-500); margin: 0; }
</style>
