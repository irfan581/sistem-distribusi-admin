<template>
  <div class="toko-view">
    <PageHeader title="Data Toko" subtitle="Kelola data toko untuk kunjungan sales.">
      <template #actions>
        <Button
          v-if="can('create_toko')"
          icon="pi pi-file-excel"
          label="Import Excel"
          severity="success"
          outlined
          @click="importDialog.open()"
        />
        <Button v-if="can('create_toko')" icon="pi pi-plus" label="Tambah Toko" @click="tokoDialog.openCreate()" />
      </template>
    </PageHeader>

    <!-- Filter bar -->
    <Card class="filter-card">
      <template #content>
        <div class="filter-bar">
          <IconField class="search-field">
            <InputIcon class="pi pi-search" />
            <InputText v-model="filters.search" placeholder="Cari nama kios / wilayah..." @input="onSearch" />
          </IconField>

          <Select
            v-model="filters.sales_id"
            :options="salesOptions"
            optionLabel="label"
            optionValue="id"
            placeholder="Semua Sales"
            style="min-width: 190px"
            showClear
            filter
            @change="loadData"
          />

          <Select
            v-model="filters.wilayah"
            :options="wilayahOptions"
            placeholder="Semua Wilayah"
            style="min-width: 170px"
            showClear
            filter
            @change="loadData"
          />

          <Select
            v-model="filters.punya_lokasi"
            :options="lokasiOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Semua Lokasi"
            style="min-width: 170px"
            showClear
            @change="loadData(1)"
          />

          <div class="filter-toggle">
            <ToggleButton
              v-model="filters.is_new"
              onLabel="Kios Baru"
              offLabel="Semua"
              onIcon="pi pi-star"
              offIcon="pi pi-list"
              @change="loadData"
            />
          </div>

          <Button icon="pi pi-refresh" text plain @click="resetFilters" v-tooltip="'Reset filter'" />
        </div>

        <div class="stats-row" v-if="!loading">
          <span class="stat"><b>{{ pagination.total ?? items.length }}</b> total toko</span>
          <span v-if="filters.is_new" class="stat new-badge"><i class="pi pi-star" /> kios baru ditandai</span>
        </div>
      </template>
    </Card>

    <!-- Table -->
    <!--
      Tanpa `scrollable` dan `scrollHeight`: tabel memanjang mengikuti jumlah
      baris dan halamannya sendiri yang digulir. Dengan tinggi tetap
      calc(100vh - 450px), di layar laptop hanya sekitar lima baris yang
      terlihat, sehingga memilih 50 baris per halaman justru memaksa
      menggulir kotak kecil di dalam kartu.
    -->
    <Card class="daftar-card">
      <template #content>
        <DataTable
          class="toko-tabel"
          :value="items"
          :loading="loading"
          stripedRows
          dataKey="id"
        >
          <!-- Nama Kios -->
          <Column header="No." style="width: 56px; text-align: center">
            <template #body="{ index }">{{ index + 1 }}</template>
          </Column>
          <Column header="Nama Kios" style="min-width: 180px">
            <template #body="{ data }">
              <div class="kios-cell">
                <span class="kios-nama">{{ data.nama_kios }}</span>
                <Tag v-if="data.jenis === 'kantor'" value="Kantor" severity="secondary" class="new-tag" />
                <Tag v-if="data.is_new" value="Baru" severity="success" class="new-tag" />
              </div>
            </template>
          </Column>

          <!-- Wilayah -->
          <Column header="Wilayah" style="min-width: 115px">
            <template #body="{ data }">
              <Tag :value="data.wilayah" severity="info" />
            </template>
          </Column>

          <!-- Sales -->
          <Column header="Sales" style="min-width: 150px">
            <template #body="{ data }">
              <div class="sales-cell">
                <span class="sales-nama">{{ data.sales_nama ?? '-' }}</span>
                <span v-if="data.sales_area" class="sales-area">{{ data.sales_area }}</span>
              </div>
            </template>
          </Column>

          <!-- Lokasi -->
          <Column header="Lokasi" style="min-width: 170px; max-width: 220px">
            <template #body="{ data }">
              <div v-if="data.lat != null" class="lokasi-cell">
                <span class="lokasi-alamat" :title="data.alamat">{{ data.alamat ?? '(tanpa alamat)' }}</span>
                <span class="lokasi-bawah">
                  <a :href="`https://www.google.com/maps?q=${data.lat},${data.lng}`" target="_blank" rel="noopener">
                    <i class="pi pi-map-marker" /> Buka peta
                  </a>
                  <Tag v-if="data.lokasi_sumber === 'manual'" value="Manual" severity="warn" />
                </span>
              </div>
              <span v-else class="date-text">Belum ada titik lokasi</span>
            </template>
          </Column>

          <!-- Tgl Input -->
          <Column header="Tgl Input" style="min-width: 100px">
            <template #body="{ data }">
              <span class="date-text">{{ formatDate(data.created_at) }}</span>
            </template>
          </Column>

          <!-- Aksi -->
          <Column header="Aksi" style="width: 90px; text-align: center">
            <template #body="{ data }">
              <div class="action-btns">
                <Button
                  v-if="can('edit_toko')"
                  icon="pi pi-pencil"
                  size="small"
                  text
                  v-tooltip="'Edit'"
                  @click="tokoDialog.openEdit(data)"
                />
                <Button
                  v-if="can('delete_toko')"
                  icon="pi pi-trash"
                  size="small"
                  text
                  severity="danger"
                  v-tooltip="'Hapus'"
                  @click="confirmDelete(data)"
                />
              </div>
            </template>
          </Column>

          <template #empty>
            <div class="empty-state">
              <i class="pi pi-building" />
              <span>{{ hasFilters ? 'Tidak ada toko sesuai filter' : 'Belum ada data toko' }}</span>
              <Button v-if="!hasFilters" label="Tambah Toko Pertama" size="small" outlined @click="tokoDialog.openCreate()" />
            </div>
          </template>
        </DataTable>

        <!-- Layar sempit: tiap toko jadi kartu, menggantikan tabel -->
        <div class="toko-cards">
          <p v-if="loading" class="date-text">Memuat data toko...</p>
          <div v-else-if="!items.length" class="empty-state">
            <i class="pi pi-building" />
            <span>{{ hasFilters ? 'Tidak ada toko sesuai filter' : 'Belum ada data toko' }}</span>
          </div>
          <article v-for="(data, index) in loading ? [] : items" :key="data.id" class="toko-card">
            <header class="toko-card-kepala">
              <span class="toko-card-no">{{ index + 1 }}</span>
              <span class="kios-nama">{{ data.nama_kios }}</span>
              <Tag v-if="data.jenis === 'kantor'" value="Kantor" severity="secondary" />
              <Tag v-if="data.is_new" value="Baru" severity="success" />
            </header>

            <div class="toko-card-baris">
              <Tag :value="data.wilayah" severity="info" />
              <span class="sales-nama">{{ data.sales_nama ?? '-' }}</span>
              <span v-if="data.sales_area" class="sales-area">{{ data.sales_area }}</span>
            </div>

            <div v-if="data.lat != null" class="lokasi-cell">
              <span class="lokasi-alamat" :title="data.alamat">{{ data.alamat ?? '(tanpa alamat)' }}</span>
              <span class="lokasi-bawah">
                <a :href="`https://www.google.com/maps?q=${data.lat},${data.lng}`" target="_blank" rel="noopener">
                  <i class="pi pi-map-marker" /> Buka peta
                </a>
                <Tag v-if="data.lokasi_sumber === 'manual'" value="Manual" severity="warn" />
              </span>
            </div>
            <span v-else class="date-text">Belum ada titik lokasi</span>

            <footer class="toko-card-kaki">
              <span class="date-text">Input {{ formatDate(data.created_at) }}</span>
              <div class="action-btns">
                <Button
                  v-if="can('edit_toko')"
                  icon="pi pi-pencil"
                  label="Edit"
                  size="small"
                  outlined
                  @click="tokoDialog.openEdit(data)"
                />
                <Button
                  v-if="can('delete_toko')"
                  icon="pi pi-trash"
                  size="small"
                  outlined
                  severity="danger"
                  aria-label="Hapus"
                  @click="confirmDelete(data)"
                />
              </div>
            </footer>
          </article>
        </div>

        <!-- Pagination -->
        <div class="pagination-bar" v-if="pagination.last_page > 1">
          <Paginator
            :rows="safeRows(pagination.per_page)"
            :totalRecords="pagination.total"
            :first="(pagination.current_page - 1) * safeRows(pagination.per_page)"
            @page="onPage"
            :rowsPerPageOptions="PAGINATION.ROWS_OPTIONS"
          />
        </div>
      </template>
    </Card>

    <TokoDialog ref="tokoDialog" @saved="loadData" />
    <TokoImportDialog ref="importDialog" @imported="loadData" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, defineAsyncComponent } from 'vue'
import { usePullRefresh } from '@/composables/usePullRefresh'
import { useConfirm } from 'primevue/useconfirm'
import { tokoService } from '@/services/toko.service'
import { useToast } from '@/composables/useToast'
import { usePermission } from '@/composables/usePermission'
import { debounce } from '@/utils/helpers'
import { PAGINATION, safeRows } from '@/utils/constants'
import PageHeader from '@/components/common/PageHeader.vue'

const { can } = usePermission()

const TokoDialog       = defineAsyncComponent(() => import('@/components/toko/TokoDialog.vue'))
const TokoImportDialog = defineAsyncComponent(() => import('@/components/toko/TokoImportDialog.vue'))

const confirm = useConfirm()
const toast   = useToast()

const loading      = ref(false)
const items        = ref([])
const salesOptions = ref([])
const wilayahOptions = ref([])
const tokoDialog   = ref(null)
const importDialog = ref(null)

const filters = reactive({
  search:   '',
  sales_id: null,
  wilayah:  null,
  is_new:   false,
  punya_lokasi: null,
})

const lokasiOptions = [
  { label: 'Sudah ada titik lokasi', value: 1 },
  { label: 'Belum ada titik lokasi', value: 0 },
]

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: PAGINATION.DEFAULT_ROWS,
  total: 0,
})

const hasFilters = computed(() =>
  filters.search || filters.sales_id || filters.wilayah || filters.is_new || filters.punya_lokasi !== null
)

function formatDate(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

async function loadData(page = 1) {
  loading.value = true
  try {
    const params = {
      page,
      per_page: pagination.per_page,
      search:   filters.search || undefined,
      sales_id: filters.sales_id || undefined,
      wilayah:  filters.wilayah || undefined,
      is_new:   filters.is_new ? 1 : undefined,
      punya_lokasi: filters.punya_lokasi ?? undefined,
    }
    const { data } = await tokoService.list(params)
    const res = data.data ?? data

    if (res.data && res.meta) {
      // paginated
      items.value = res.data.map(format)
      pagination.current_page = res.meta.current_page
      pagination.last_page    = res.meta.last_page
      pagination.per_page     = safeRows(res.meta.per_page)
      pagination.total        = res.meta.total
    } else if (res.data) {
      items.value = res.data.map(format)
      pagination.total = res.total ?? res.data.length
      pagination.last_page = res.last_page ?? 1
      pagination.current_page = res.current_page ?? 1
      pagination.per_page = safeRows(res.per_page)
    } else {
      items.value = (Array.isArray(res) ? res : []).map(format)
    }
  } catch (err) {
    toast.apiError(err)
  } finally {
    loading.value = false
  }
}

function format(t) {
  return {
    id:         t.id,
    sales_id:   t.sales_id,
    sales_nama: t.sales_nama ?? t.sales?.nama,
    sales_area: t.sales_area ?? t.sales?.area,
    nama_kios:  t.nama_kios,
    jenis:      t.jenis,
    wilayah:    t.wilayah,
    is_new:     t.is_new,
    created_at: t.created_at,
    alamat:     t.alamat,
    lat:        t.lat,
    lng:        t.lng,
    lokasi_sumber: t.lokasi_sumber,
  }
}

async function loadOptions() {
  const [salesRes, wilayahRes] = await Promise.all([
    tokoService.salesOptions(),
    tokoService.wilayahList(),
  ])
  salesOptions.value   = salesRes.data?.data ?? salesRes.data
  wilayahOptions.value = wilayahRes.data?.data ?? wilayahRes.data
}

const onSearch = debounce(() => loadData(1), 350)

function onPage(e) {
  pagination.per_page = safeRows(e.rows)
  loadData(e.page + 1)
}

function resetFilters() {
  filters.search   = ''
  filters.sales_id = null
  filters.wilayah  = null
  filters.is_new   = false
  filters.punya_lokasi = null
  loadData(1)
}

function confirmDelete(toko) {
  confirm.require({
    message: `Hapus toko "${toko.nama_kios}"?`,
    header: 'Hapus Toko',
    icon: 'pi pi-exclamation-triangle',
    acceptSeverity: 'danger',
    acceptLabel: 'Ya, Hapus',
    rejectLabel: 'Batal',
    accept: async () => {
      try {
        await tokoService.destroy(toko.id)
        toast.success('Toko berhasil dihapus')
        loadData(pagination.current_page)
      } catch (err) {
        toast.error(err.response?.data?.message || 'Gagal menghapus toko')
      }
    },
  })
}

onMounted(() => {
  loadData()
  loadOptions()
})
usePullRefresh(loadData)
</script>

<style scoped>
.toko-view { display: flex; flex-direction: column; gap: 1.25rem; animation: fadeUp var(--dur-base) var(--ease) both; }

.filter-card :deep(.p-card-body) { padding: 0.75rem 1rem; }

.filter-bar {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
}
.search-field { flex: 1; min-width: 210px; }

.stats-row {
  margin-top: 0.5rem;
  display: flex; align-items: center; gap: 1rem;
  font-size: 0.8rem; color: var(--p-text-muted-color);
}
.stat b { color: var(--p-text-color); }
.new-badge { color: var(--c-success); display: flex; align-items: center; gap: 0.3rem; }

.kios-cell { display: flex; align-items: center; gap: 0.5rem; }
.kios-nama { font-weight: 600; font-size: 0.875rem; }
.new-tag :deep(.p-tag) { font-size: 0.68rem; padding: 0.1rem 0.45rem; }

/* align-items ditulis tegas: visitTargets.css punya .sales-cell global yang menengahkan isi. */
.sales-cell { display: flex; flex-direction: column; align-items: flex-start; gap: 1px; }
.sales-nama { font-size: 0.85rem; font-weight: 500; color: var(--p-text-color); }
.sales-area { font-size: 0.73rem; color: var(--p-text-muted-color); }

.date-text { font-size: 0.8rem; color: var(--p-text-muted-color); white-space: nowrap; }
.lokasi-cell { display: flex; flex-direction: column; gap: 2px;}
.lokasi-alamat {
  font-size: 0.8rem; color: var(--p-text-color);
  display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
/* Layar sempit: tabel digulir mendatar, tombol Aksi tidak terpotong */
.toko-view :deep(.p-datatable-table-container) { overflow-x: auto; }
.lokasi-bawah { display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; }
.lokasi-bawah a {
  display: inline-flex; align-items: center; gap: 0.25rem;
  color: var(--p-primary-color); font-weight: 600; text-decoration: none;
}
.lokasi-bawah a:hover { text-decoration: underline; }
.lokasi-bawah a i { font-size: 0.75rem; }

.action-btns { display: flex; justify-content: center; gap: 0.25rem; }

.pagination-bar { margin-top: 0.75rem; display: flex; justify-content: center; }

.toko-cards { display: none; flex-direction: column; gap: 0.75rem; }
.toko-card {
  display: flex; flex-direction: column; gap: 0.6rem;
  padding: 0.85rem; border: 1px solid var(--p-content-border-color); border-radius: 12px;
}
.toko-card-kepala { display: flex; align-items: flex-start; gap: 0.5rem; }
.toko-card-kepala .kios-nama { line-height: 1.35; }
.toko-card-kepala .kios-nama { flex: 1; }
.toko-card-no { font-size: 0.75rem; color: var(--p-text-muted-color); min-width: 1.4rem; }
.toko-card-baris { display: flex; align-items: center; flex-wrap: wrap; gap: 0.4rem 0.6rem; }
.toko-card-kaki { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }

/* Ponsel: saringan selebar penuh, tabel diganti kartu */
@media (max-width: 640px) {
  .filter-bar { display: grid; grid-template-columns: 1fr auto; }
  .filter-bar > .search-field,
  .filter-bar > :deep(.p-select) { grid-column: 1 / -1; width: 100%; min-width: 0 !important; }
  .filter-toggle :deep(.p-togglebutton) { width: 100%; }
  .toko-tabel { display: none; }
  .toko-cards { display: flex; }

  /* Kartu toko berdiri sendiri, tidak di dalam kartu pembungkus */
  .daftar-card.daftar-card { background: transparent !important; border: 0 !important; box-shadow: none !important; }
  .daftar-card :deep(.p-card-content) { padding: 0 !important; }
  .toko-card {
    padding: 0.9rem 1rem; background: var(--p-content-background);
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.05);
  }
  .toko-card-no { padding-top: 0.2rem; }
  .toko-card-kaki { padding-top: 0.6rem; border-top: 1px solid var(--p-content-border-color); }
}

.empty-state {
  display: flex; flex-direction: column; align-items: center;
  gap: 0.75rem; padding: 3rem; color: var(--p-text-muted-color);
}
.empty-state i { font-size: 2.5rem; }
</style>
