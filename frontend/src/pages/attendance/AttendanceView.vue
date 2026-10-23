<template>
  <div class="attendance-view">
    <PageHeader title="Presensi" subtitle="Monitor dan kelola data presensi karyawan.">
      <template #actions>
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
          <DatePicker
            v-model="filterDate"
            placeholder="Filter tanggal"
            show-icon
            date-format="yy-mm-dd"
            @date-select="resetAndLoad"
            @clear-click="resetAndLoad"
            showClear
            style="width: 200px"
          />
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
          <Column header="Sales" style="min-width: 200px">
            <template #body="{ data }">
              <div class="sales-cell">
                <SalesAvatar :src="data.sales?.foto_profil_url" :nama="data.sales?.nama" :size="36" />
                <div>
                  <div class="sales-nama">{{ data.sales?.nama || '-' }}</div>
                  <div class="sales-sub">{{ data.sales?.area || '-' }}</div>
                </div>
              </div>
            </template>
          </Column>
          <Column field="tanggal" header="Tanggal" style="min-width: 120px">
            <template #body="{ data }">{{ formatDate(data.tanggal) }}</template>
          </Column>
          <Column field="jam_masuk" header="Jam Masuk" style="min-width: 100px">
            <template #body="{ data }">{{ data.jam_masuk || '-' }}</template>
          </Column>
          <Column field="jam_pulang" header="Jam Pulang" style="min-width: 100px">
            <template #body="{ data }">{{ data.jam_pulang || '-' }}</template>
          </Column>
          <Column field="status" header="Status" style="min-width: 120px">
            <template #body="{ data }">
              <Tag
                :value="data.status_label || data.status"
                :severity="data.status === 'tepat_waktu' ? 'success' : data.status === 'terlambat' ? 'warn' : 'secondary'"
              />
            </template>
          </Column>
          <Column field="keterangan" header="Keterangan" style="min-width: 140px">
            <template #body="{ data }">{{ data.keterangan || '-' }}</template>
          </Column>

          <Column header="Aksi" style="min-width: 120px; text-align:center">
            <template #body="{ data }">
              <Button icon="pi pi-eye" size="small" text rounded severity="info"
                @click="goDetail(data.id)" v-tooltip.top="'Lihat Detail'" />
              <Button v-if="can('edit_presensi')" icon="pi pi-pencil" size="small" text rounded severity="secondary"
                @click="goEdit(data.id)" v-tooltip.top="'Edit Presensi'" />
              <Button v-if="can('delete_presensi')" icon="pi pi-trash" size="small" text rounded severity="danger"
                @click="confirmDelete(data)" v-tooltip.top="'Hapus'" />
            </template>
          </Column>

          <template #empty>
            <div class="empty-state">
              <i class="pi pi-calendar" />
              <span>Tidak ada data presensi ditemukan</span>
            </div>
          </template>
        </DataTable>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { usePullRefresh } from '@/composables/usePullRefresh'
import { useRouter } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import { attendanceService } from '@/services/attendance.service'
import { presensiService } from '@/services/presensi.service'
import { useToast } from '@/composables/useToast'
import { usePermission } from '@/composables/usePermission'
import { formatDate, debounce } from '@/utils/helpers'
import PageHeader from '@/components/common/PageHeader.vue'

const router  = useRouter()
const toast   = useToast()
const confirm = useConfirm()
const { can } = usePermission()

function goDetail(id) { router.push({ name: 'attendance-detail', params: { id } }) }
function goEdit(id)   { router.push({ name: 'attendance-detail', params: { id }, query: { edit: '1' } }) }

function confirmDelete(data) {
  const tgl = formatDate(data.tanggal)   // pakai helper — ada null-guard & konsisten
  confirm.require({
    message: `Hapus presensi ${data.sales?.nama || '-'} tanggal ${tgl}?`,
    header: 'Konfirmasi Hapus',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Ya, Hapus',
    rejectLabel: 'Batal',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await presensiService.destroy(data.id)
        toast.success('Data presensi berhasil dihapus.')
        loadData()
      } catch (err) {
        toast.apiError(err)
      }
    },
  })
}

const items = ref([])
const loading = ref(false)
const totalRecords = ref(0)
const currentPage = ref(1)
const perPage = ref(10)
const searchQuery = ref('')
const filterDate = ref(null)
const filterStatus = ref(null)

const statusOptions = [
  { label: 'Tepat Waktu', value: 'tepat_waktu' },
  { label: 'Terlambat', value: 'terlambat' },
]

async function loadData() {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value,
    }
    if (searchQuery.value) params.search = searchQuery.value
    if (filterDate.value) {
      const d = new Date(filterDate.value)
      const ymd = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
      params.tanggal_mulai = ymd
      params.tanggal_akhir = ymd
    }
    if (filterStatus.value) params.status = filterStatus.value

    const res = await attendanceService.getAll(params)
    const paginated = res.data
    items.value = paginated.data || paginated
    totalRecords.value = paginated.total || items.value.length
  } catch (err) {
    toast.apiError(err)
  } finally {
    loading.value = false
  }
}

function onPage(event) {
  currentPage.value = event.page + 1
  perPage.value = event.rows
  loadData()
}

function resetAndLoad() {
  currentPage.value = 1
  loadData()
}

const onSearch = debounce(resetAndLoad, 400)

onMounted(loadData)
usePullRefresh(loadData)
</script>

<style scoped>
.attendance-view { display: flex; flex-direction: column; gap: 1.25rem; animation: fadeUp var(--dur-base) var(--ease) both; }

.filter-card :deep(.p-card-body) { padding: 0.75rem 1rem; }

.filter-bar {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.search-field { flex: 1; min-width: 220px; }

.sales-cell { display: flex; align-items: center; gap: 0.75rem; }

.sales-avatar {
  background: var(--p-primary-100) !important;
  color: var(--p-primary-700) !important;
  flex-shrink: 0;
}

.sales-nama { font-size: 0.875rem; font-weight: 600; color: var(--p-text-color); }
.sales-sub { font-size: 0.75rem; color: var(--p-text-muted-color); }

.empty-state {
  display: flex; flex-direction: column; align-items: center;
  gap: 0.5rem; padding: 3rem; color: var(--p-text-muted-color);
}
.empty-state i { font-size: 2.5rem; }
</style>
