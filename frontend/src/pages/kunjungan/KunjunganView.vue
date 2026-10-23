<template>
  <div class="kunjungan-view">
    <PageHeader title="Kunjungan Toko" subtitle="Monitor data kunjungan sales ke toko.">
      <template #actions>
        <Button icon="pi pi-refresh" text plain @click="loadData" :loading="loading" />
        <Button
          v-if="isDeveloper"
          icon="pi pi-plus"
          label="Tambah Manual"
          severity="secondary"
          @click="$router.push({ name: 'kunjungan-tambah' })"
        />
      </template>
    </PageHeader>

    <Card class="filter-card">
      <template #content>
        <div class="filter-bar">
          <IconField class="search-field">
            <InputIcon class="pi pi-search" />
            <InputText v-model="filters.search" placeholder="Cari nama toko / sales..." @input="onSearch" />
          </IconField>

          <Select v-model="filters.sales_id" :options="salesOptions"
            optionLabel="label" optionValue="id" placeholder="Semua Sales"
            showClear filter class="filter-sales" @change="resetAndLoad" />

          <Select v-model="filters.tanggapan" :options="pilihanTanggapan"
            optionLabel="label" optionValue="value" placeholder="Semua Tanggapan"
            showClear class="filter-tanggapan" @change="resetAndLoad" />

          <!-- Kolom sendiri agar rentang tertulis "tanggal sampai tanggal"; kalendernya di popover -->
          <div class="filter-tanggal">
            <IconField>
              <InputIcon class="pi pi-calendar" />
              <InputText :value="teksRentang" placeholder="Rentang tanggal" readonly class="tanggal-input" @click="bukaKalender" />
            </IconField>
            <button v-if="filters.rentang" type="button" class="tanggal-hapus" aria-label="Hapus rentang tanggal" @click="hapusRentang">
              <i class="pi pi-times" />
            </button>
          </div>
          <Popover ref="kalender">
            <DatePicker v-model="filters.rentang" selectionMode="range" inline @update:modelValue="onRentang" />
          </Popover>
        </div>
      </template>
    </Card>

    <Card>
      <template #content>
        <DataTable :value="items" :loading="loading" stripedRows dataKey="id">

          <Column header="No." style="width: 56px; text-align: center">
            <template #body="{ index }">{{ index + 1 }}</template>
          </Column>
          <Column header="Foto" style="width: 70px">
            <template #body="{ data }">
              <img v-if="data.foto_url" :src="data.foto_url" class="thumb-img" @click="openPhoto(data.foto_url)" />
              <div v-else class="thumb-empty"><i class="pi pi-image" /></div>
            </template>
          </Column>

          <Column header="Nama Toko" style="min-width: 180px">
            <template #body="{ data }">
              <div class="toko-cell">
                <span class="toko-nama">{{ data.nama_toko }}</span>
                <span v-if="data.keterangan" class="toko-ket">{{ truncate(data.keterangan, 55) }}</span>
              </div>
            </template>
          </Column>

          <Column header="Sales" style="min-width: 150px">
            <template #body="{ data }">
              <Tag :value="data.sales_nama || '-'" severity="success" />
            </template>
          </Column>

          <Column header="GPS" style="width: 80px; text-align:center">
            <template #body="{ data }">
              <i v-if="data.is_suspicious"
                class="pi pi-exclamation-triangle gps-warn"
                v-tooltip="'GPS Mencurigakan'" />
              <i v-else class="pi pi-check-circle gps-ok" v-tooltip="'GPS Aman'" />
            </template>
          </Column>

          <Column header="Tanggapan" style="min-width: 120px">
            <template #body="{ data }">
              <Tag v-if="data.tanggapan?.status === 'diterima'" :value="data.tanggapan.otomatis ? 'Diterima otomatis' : 'Diterima'"
                   severity="success" v-tooltip="data.tanggapan.catatan || null" />
              <Tag v-else-if="data.tanggapan?.status === 'ditolak'" value="Ditolak" severity="danger"
                   v-tooltip="data.tanggapan.catatan || null" />
              <Tag v-else-if="data.tanggapan?.perlu" value="Belum" severity="warn" />
              <span v-else class="tanggapan-lama">—</span>
            </template>
          </Column>

          <Column header="Tanggal" style="min-width: 110px">
            <template #body="{ data }">
              <div class="date-cell">
                <span class="date-main">{{ data.tanggal }}</span>
                <Tag :value="data.jam" severity="secondary" class="jam-tag" />
              </div>
            </template>
          </Column>

          <Column header="Aksi" style="width: 80px; text-align:center">
            <template #body="{ data }">
              <Button icon="pi pi-eye" size="small" text @click="goDetail(data.id)" v-tooltip="'Lihat Detail'" />
            </template>
          </Column>

          <template #empty>
            <div class="empty-state">
              <i class="pi pi-building" />
              <span>Tidak ada data kunjungan</span>
            </div>
          </template>
        </DataTable>

        <div class="pagination-bar" v-if="totalRecords > 0">
          <Paginator :rows="perPage" :totalRecords="totalRecords"
            :first="(currentPage - 1) * perPage" @page="onPage"
            :rowsPerPageOptions="PAGINATION.ROWS_OPTIONS" />
        </div>
      </template>
    </Card>

    <FotoViewer v-model:visible="photoVisible" :src="photoUrl" title="Bukti Foto" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { usePullRefresh } from '@/composables/usePullRefresh'
import { useRoute, useRouter } from 'vue-router'
import { kunjunganService } from '@/services/kunjungan.service'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth.store'
import { debounce } from '@/utils/helpers'
import { PAGINATION, safeRows } from '@/utils/constants'
import PageHeader from '@/components/common/PageHeader.vue'
import FotoViewer from '@/components/common/FotoViewer.vue'

const authStore   = useAuthStore()
const isDeveloper = computed(() =>
  ['developer', 'owner'].includes(authStore.user?.role_nama)
)

const router = useRouter()
const route  = useRoute()
const toast  = useToast()

const loading      = ref(false)
const items        = ref([])
const totalRecords = ref(0)
const currentPage  = ref(1)
const perPage      = ref(PAGINATION.DEFAULT_ROWS)
const photoVisible = ref(false)
const photoUrl     = ref('')

// Saat dibuka, semua kunjungan tampil (terbaru di atas). Isi tanggal untuk
// membatasi; backend tidak lagi membatasi ke hari ini secara diam-diam.
const filters = reactive({ search: '', sales_id: null, rentang: null, tanggapan: null })
const pilihanTanggapan = [
  { label: 'Belum ditanggapi', value: 'belum' },
  { label: 'Diterima',         value: 'diterima' },
  { label: 'Ditolak',          value: 'ditolak' },
]
const salesOptions = ref([])
const kalender = ref(null)

function fmtTanggal(d) {
  const dt = new Date(d)
  return `${String(dt.getDate()).padStart(2, '0')}/${String(dt.getMonth() + 1).padStart(2, '0')}/${dt.getFullYear()}`
}

const teksRentang = computed(() => {
  const [awal, akhir] = filters.rentang ?? []
  if (!awal) return ''
  if (!akhir || toYmd(awal) === toYmd(akhir)) return fmtTanggal(awal)
  return `${fmtTanggal(awal)} sampai ${fmtTanggal(akhir)}`
})

function bukaKalender(e) { kalender.value?.toggle(e) }
function hapusRentang() { filters.rentang = null; resetAndLoad() }

function truncate(str, len) {
  return str?.length > len ? str.slice(0, len) + '…' : str
}

function toYmd(d) {
  if (!d) return null
  const dt = new Date(d)
  return `${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,'0')}-${String(dt.getDate()).padStart(2,'0')}`
}

async function loadData(page = 1) {
  loading.value = true
  try {
    const params = {
      page,
      per_page:      perPage.value,
      // Satu tanggal saja berarti hari itu
      tanggal_mulai: toYmd(filters.rentang?.[0]),
      tanggal_akhir: toYmd(filters.rentang?.[1] ?? filters.rentang?.[0]),
    }
    if (filters.sales_id)                    params.sales_id      = filters.sales_id
    if (filters.search)                      params.search        = filters.search
    if (filters.tanggapan)                   params.tanggapan     = filters.tanggapan
    const { data } = await kunjunganService.list(params)
    const res = data.data ?? data
    items.value        = Array.isArray(res) ? res : (res.data ?? [])
    totalRecords.value = res.total ?? items.value.length
    currentPage.value  = page
  } catch (err) {
    toast.apiError(err)
  } finally {
    loading.value = false
  }
}

function onPage(e) { perPage.value = safeRows(e.rows); loadData(e.page + 1) }
function resetAndLoad() { loadData(1) }

/** Muat ulang saat rentang lengkap (dua tanggal) atau dikosongkan, bukan di klik pertama. */
function onRentang(nilai) {
  if (!nilai || (nilai[0] && nilai[1])) {
    kalender.value?.hide()
    resetAndLoad()
  }
}

async function loadSales() {
  try {
    const { data } = await kunjunganService.salesOptions()
    salesOptions.value = data.data ?? data
  } catch {
    salesOptions.value = []
  }
}
const onSearch = debounce(resetAndLoad, 350)

function goDetail(id) { router.push({ name: 'kunjungan-detail', params: { id } }) }

function openPhoto(url) { photoUrl.value = url; photoVisible.value = true }


onMounted(() => {
  // Dari penanda di menu samping: langsung tampilkan yang belum ditanggapi.
  if (route.query.tanggapan) filters.tanggapan = String(route.query.tanggapan)
  loadData(); loadSales()
})
usePullRefresh(() => loadData())
</script>

<style scoped>
.kunjungan-view { display: flex; flex-direction: column; gap: 1.25rem; animation: fadeUp var(--dur-base) var(--ease) both; }

.filter-card :deep(.p-card-body) { padding: 0.75rem 1rem; }
.filter-bar { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
.search-field { flex: 1; min-width: 200px; }
.filter-sales { width: 230px; }
.filter-tanggapan { width: 190px; }
.tanggapan-lama { color: var(--t-muted); }
.filter-tanggal { position: relative; width: 270px; }
.filter-tanggal :deep(.p-iconfield), .tanggal-input { width: 100%; }
.tanggal-input { cursor: pointer; padding-right: 2.2rem; }
.tanggal-hapus {
  position: absolute; right: 0.4rem; top: 50%; transform: translateY(-50%);
  display: grid; place-items: center; width: 1.6rem; height: 1.6rem;
  border: 0; border-radius: 999px; background: transparent; color: var(--p-text-muted-color); cursor: pointer;
}
.tanggal-hapus:hover { background: var(--p-content-hover-background); }
@media (max-width: 640px) {
  .filter-bar > * { width: 100% !important; }
}

.thumb-img {
  width: 52px; height: 52px;
  object-fit: cover; border-radius: 8px;
  cursor: zoom-in;
  border: 1px solid var(--p-content-border-color);
  transition: transform 0.2s;
}
.thumb-img:hover { transform: scale(1.08); }
.thumb-empty {
  width: 52px; height: 52px; border-radius: 8px;
  background: var(--s-2);
  display: flex; align-items: center; justify-content: center;
  color: var(--p-text-muted-color); font-size: 1.2rem;
}

.toko-cell { display: flex; flex-direction: column; gap: 2px; }
.toko-nama { font-size: 0.875rem; font-weight: 600; color: var(--p-text-color); }
.toko-ket  { font-size: 0.75rem; color: var(--p-text-muted-color); }

.gps-warn { color: var(--c-danger); font-size: 1.1rem; }
.gps-ok   { color: var(--c-success); font-size: 1.1rem; }
:global(.app-dark) .gps-warn { color: var(--c-danger); }
:global(.app-dark) .gps-ok   { color: var(--c-success); }

.date-cell { display: flex; flex-direction: column; gap: 3px; }
.date-main { font-size: 0.8rem; color: var(--p-text-color); }
.jam-tag :deep(.p-tag) { font-size: 0.7rem; }

.pagination-bar { margin-top: 0.75rem; display: flex; justify-content: center; }

.empty-state {
  display: flex; flex-direction: column; align-items: center;
  gap: 0.5rem; padding: 3rem; color: var(--p-text-muted-color);
}
.empty-state i { font-size: 2.5rem; }

</style>
