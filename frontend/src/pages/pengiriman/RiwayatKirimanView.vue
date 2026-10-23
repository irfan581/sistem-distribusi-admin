<template>
  <div class="rk-view">
    <PageHeader title="Riwayat Pengiriman" subtitle="Rekap seluruh pengiriman berdasarkan periode waktu.">
      <template #actions>
        <Button
          icon="pi pi-file-pdf"
          label="Cetak Rekap"
          outlined
          :loading="mencetak"
          @click="menuCetak.toggle($event)"
        />
      </template>
    </PageHeader>

    <!-- Jenis muatan dipilih saat mencetak, bukan lewat filter halaman.
         Rekap ini memang dibuat terpisah per jenis — kantor menempelkan yang
         subsidi dan non subsidi di papan yang berbeda — sedangkan daftar di
         layar justru enak dibaca kalau keduanya tercampur menurut tanggal. -->
    <Menu ref="menuCetak" :model="pilihanCetak" popup />

    <!-- Filter -->
    <Card class="filter-card">
      <template #content>
        <!-- Baris 1: preset + date range -->
        <div class="filter-top">
          <div class="preset-group">
            <button
              v-for="p in presets" :key="p.key"
              class="preset-btn" :class="{ active: activePreset === p.key }"
              @click="applyPreset(p.key)"
            >{{ p.label }}</button>
          </div>
          <div class="date-range-group">
            <DatePicker
              v-model="filters.dari"
              placeholder="Dari"
              dateFormat="dd/mm/yy"
              :showClear="!layarSempit"
              @date-select="onCustomDate"
              @clear-click="onCustomDate"
            />
            <span class="range-sep">sampai</span>
            <DatePicker
              v-model="filters.sampai"
              placeholder="Sampai"
              dateFormat="dd/mm/yy"
              :showClear="!layarSempit"
              @date-select="onCustomDate"
              @clear-click="onCustomDate"
            />
          </div>
        </div>

        <!-- Baris 2: search + reset -->
        <div class="filter-bottom">
          <IconField class="search-field">
            <InputIcon class="pi pi-search" />
            <InputText v-model="filters.search" placeholder="Cari lokasi tujuan..." @input="onSearch" />
          </IconField>
          <ToggleButton :modelValue="filters.belumDitanggapi" onLabel="Belum ditanggapi" offLabel="Belum ditanggapi"
                        onIcon="pi pi-filter-fill" offIcon="pi pi-filter" class="toggle-tanggapan"
                        @update:modelValue="ubahBelumDitanggapi" />
          <Button icon="pi pi-refresh" text plain @click="resetFilters" v-tooltip="'Reset filter'" />
        </div>
      </template>
    </Card>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card stat-card--brand">
        <div class="stat-icon-wrap stat-icon--brand">
          <i class="pi pi-truck" />
        </div>
        <div class="stat-body">
          <div class="stat-val">
            <template v-if="loading"><span class="stat-skeleton" /></template>
            <template v-else>{{ meta.total ?? 0 }}</template>
          </div>
          <div class="stat-label">Total Pengiriman</div>
        </div>
      </div>

      <div class="stat-card stat-card--success">
        <div class="stat-icon-wrap stat-icon--success">
          <i class="pi pi-box" />
        </div>
        <div class="stat-body">
          <div class="stat-val">
            <template v-if="loading"><span class="stat-skeleton" /></template>
            <template v-else>{{ fmtKg(totalTonAll) }} <span class="stat-unit">kg</span></template>
          </div>
          <div class="stat-label">Total Muatan</div>
        </div>
      </div>

      <div class="stat-card stat-card--info">
        <div class="stat-icon-wrap stat-icon--info">
          <i class="pi pi-map-marker" />
        </div>
        <div class="stat-body">
          <div class="stat-val">
            <template v-if="loading"><span class="stat-skeleton" /></template>
            <template v-else>{{ totalTujuanAll }}</template>
          </div>
          <div class="stat-label">Total Tujuan</div>
        </div>
      </div>
    </div>

    <!-- Tabel desktop -->
    <Card class="desktop-only">
      <template #content>
        <DataTable
          :value="list"
          :loading="loading"
          stripedRows
          dataKey="id"
          v-model:expandedRows="expandedRows"
        >
          <Column expander style="width: 3rem" />

          <Column header="No." style="width: 56px; text-align: center">
            <template #body="{ index }">{{ index + 1 }}</template>
          </Column>

          <Column header="Tanggal / Rit" style="min-width:130px">
            <template #body="{ data }">
              <div class="tanggal-rit-cell">
                <span class="date-text">{{ formatDate(data.tanggal_muatan) }}</span>
                <span class="rit-badge" :class="data.nomor_rit === 2 ? 'rit-2' : data.nomor_rit === 3 ? 'rit-3' : 'rit-1'">Rit {{ data.nomor_rit ?? 1 }}</span>
              </div>
            </template>
          </Column>

          <Column header="Kendaraan" style="min-width:160px">
            <template #body="{ data }">
              <div v-if="data.kendaraan" class="kendaraan-cell">
                <span class="polisi">{{ data.kendaraan.nomor_polisi }}</span>
                <span class="jenis">{{ data.kendaraan.jenis_kendaraan }}</span>
              </div>
              <span v-else class="na-tag">-</span>
            </template>
          </Column>

          <Column header="Driver" style="min-width:140px">
            <template #body="{ data }">
              <span v-if="data.driver" class="driver-name">{{ data.driver.nama_driver }}</span>
              <span v-else class="na-tag">-</span>
            </template>
          </Column>

          <Column header="Tujuan" style="min-width:200px">
            <template #body="{ data }">
              <div class="tujuan-cell">
                <span class="tujuan-badge">{{ data.jumlah_tujuan }} toko</span>
                <span class="tujuan-list">{{ data.lokasi_pengiriman }}</span>
              </div>
            </template>
          </Column>

          <Column header="Total Muatan" style="min-width:120px">
            <template #body="{ data }">
              <span class="tonase">{{ fmtKg(data.total_muatan_ton) }} Kg</span>
            </template>
          </Column>

          <Column header="Kategori" style="min-width:110px">
            <template #body="{ data }">
              <div class="kategori-cell">
                <span class="kat kat-muatan">
                  {{ data.jenis_muatan === 'subsidi' ? 'Subsidi' : 'Non-subsidi' }}
                </span>
              </div>
            </template>
          </Column>

          <Column header="Progres" style="min-width:130px">
            <template #body="{ data }">
              <div class="progres-cell">
                <span class="progres-status" :class="'st-' + (data.status_perjalanan || 'Menunggu').toLowerCase()">
                  {{ data.status_perjalanan || 'Menunggu' }}
                </span>
                <span class="progres-angka">
                  {{ data.tujuan_tuntas ?? 0 }}/{{ data.jumlah_tujuan }} tujuan
                  <template v-if="data.tujuan_bermasalah > 0">
                    &middot; <span class="progres-masalah">{{ data.tujuan_bermasalah }} bermasalah</span>
                  </template>
                  <template v-if="data.tujuan_belum_ditanggapi > 0">
                    &middot; <span class="progres-tanggapan">{{ data.tujuan_belum_ditanggapi }} belum ditanggapi</span>
                  </template>
                </span>
              </div>
            </template>
          </Column>

          <Column header="Aksi" style="min-width:170px">
            <template #body="{ data }">
              <div class="action-btns">
                <Button
                  icon="pi pi-camera"
                  label="Laporan"
                  size="small"
                  outlined
                  :disabled="!data.driver"
                  :aria-label="data.driver ? 'Lihat laporan driver' : 'Belum ada driver'"
                  v-tooltip.top="data.driver ? 'Foto bukti dan keterangan dari sopir' : 'Rit ini belum punya driver'"
                  @click="openLaporan(data)"
                />
              </div>
            </template>
          </Column>

          <template #expansion="{ data }">
            <div class="expansion-body">
              <div class="exp-title">Detail Tujuan Pengiriman</div>
              <div class="exp-items">
                <div v-for="(item, idx) in data.items" :key="item.id" class="exp-item">
                  <span class="exp-num">{{ idx + 1 }}</span>
                  <span class="exp-lokasi">{{ item.lokasi_pengiriman }}</span>
                  <span v-if="item.kode_area" class="exp-kode">{{ item.kode_area }}</span>
                  <span class="exp-status" :class="'sb-' + (item.status_bongkar || 'Menunggu').toLowerCase()">
                    {{ item.status_bongkar || 'Menunggu' }}
                  </span>
                  <span v-if="item.catatan_driver" class="exp-catatan" v-tooltip.top="item.catatan_driver">
                    <i class="pi pi-comment" /> {{ item.catatan_driver }}
                  </span>
                </div>
              </div>
            </div>
          </template>

          <template #empty>
            <div class="empty-state">
              <i class="pi pi-inbox" />
              <span>Tidak ada data pengiriman pada periode ini</span>
            </div>
          </template>
        </DataTable>

        <div class="pagination-bar" v-if="meta.total > 0">
          <Paginator
            :rows="perPage"
            :totalRecords="meta.total"
            :first="(meta.current_page - 1) * perPage"
            @page="onPage"
            :rowsPerPageOptions="PAGINATION.ROWS_OPTIONS"
          />
        </div>
      </template>
    </Card>

    <!-- Card list mobile -->
    <div class="mobile-only">
      <div v-if="loading" class="mobile-loading">
        <i class="pi pi-spin pi-spinner" /> Memuat data...
      </div>
      <template v-else-if="list.length">
        <div v-for="item in list" :key="item.id" class="muat-card">
          <div class="muat-card-top">
            <div class="muat-card-date"><i class="pi pi-calendar" /> {{ formatDate(item.tanggal_muatan) }}</div>
            <span class="tonase">{{ fmtKg(item.total_muatan_ton) }} Kg</span>
          </div>
          <div class="kategori-cell kategori-mobile">
            <span class="kat kat-muatan">
              {{ item.jenis_muatan === 'subsidi' ? 'Subsidi' : 'Non-subsidi' }}
            </span>
          </div>
          <div class="muat-card-progres">
            <span class="progres-status" :class="'st-' + (item.status_perjalanan || 'Menunggu').toLowerCase()">
              {{ item.status_perjalanan || 'Menunggu' }}
            </span>
            <span class="progres-angka">
              {{ item.tujuan_tuntas ?? 0 }}/{{ item.jumlah_tujuan }} tujuan
              <template v-if="item.tujuan_bermasalah > 0">
                &middot; <span class="progres-masalah">{{ item.tujuan_bermasalah }} bermasalah</span>
              </template>
              <template v-if="item.tujuan_belum_ditanggapi > 0">
                &middot; <span class="progres-tanggapan">{{ item.tujuan_belum_ditanggapi }} belum ditanggapi</span>
              </template>
            </span>
          </div>
          <div class="muat-card-body">
            <div class="muat-card-row">
              <i class="pi pi-truck" />
              <span v-if="item.kendaraan"><b>{{ item.kendaraan.nomor_polisi }}</b><span class="muted"> ({{ item.kendaraan.jenis_kendaraan }})</span></span>
              <span v-else class="na-tag">Kendaraan tidak tercatat</span>
            </div>
            <div class="muat-card-row">
              <i class="pi pi-user" />
              <span v-if="item.driver">{{ item.driver.nama_driver }}</span>
              <span v-else class="na-tag">Driver tidak tercatat</span>
            </div>
            <div class="muat-card-row">
              <i class="pi pi-map-marker" />
              <span><span class="tujuan-badge">{{ item.jumlah_tujuan }} toko</span> {{ item.lokasi_pengiriman }}</span>
            </div>
          </div>
          <div class="muat-card-actions">
            <Button
              icon="pi pi-camera"
              label="Laporan"
              size="small"
              text
              :disabled="!item.driver"
              @click="openLaporan(item)"
            />
          </div>
        </div>
      </template>
      <div v-else class="empty-state">
        <i class="pi pi-inbox" />
        <span>Tidak ada data pengiriman pada periode ini</span>
      </div>
      <div class="pagination-bar" v-if="meta.total > 0">
        <Paginator
          :rows="perPage"
          :totalRecords="meta.total"
          :first="(meta.current_page - 1) * perPage"
          @page="onPage"
          :rowsPerPageOptions="PAGINATION.ROWS_OPTIONS"
        />
      </div>
    </div>

    <LaporanDriverDialog v-model:visible="showLaporanDialog" :rit-id="laporanRitId" @tanggapan-berubah="perbaruiRit" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import { useStaleRefresh } from '@/composables/useStaleRefresh'
import { daftarMuatService } from '@/services/daftarMuat.service'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth.store'
import { usePermission } from '@/composables/usePermission'
import { debounce } from '@/utils/helpers'
import { PAGINATION, safeRows } from '@/utils/constants'
import PageHeader from '@/components/common/PageHeader.vue'
import LaporanDriverDialog from '@/components/pengiriman/LaporanDriverDialog.vue'

const toast     = useToast()
const authStore = useAuthStore()
const { can }   = usePermission()

const loading      = ref(false)
const list         = ref([])
const meta         = ref({ total: 0, current_page: 1, last_page: 1, per_page: PAGINATION.DEFAULT_ROWS })
const currentPage  = ref(1)
const perPage      = ref(PAGINATION.DEFAULT_ROWS)
const expandedRows = ref([])
const activePreset = ref('bulan_ini')

const filters = reactive({ search: '', dari: null, sampai: null, belumDitanggapi: false })

// Di ponsel ikon hapus tanggal tidak dipasang: PrimeVue menempelkan display:block
// langsung di ikonnya, jadi CSS tidak bisa menyembunyikannya.
const mqSempit    = window.matchMedia('(max-width: 768px)')
const layarSempit = ref(mqSempit.matches)
const onUbahLayar = (e) => { layarSempit.value = e.matches }
onMounted(() => mqSempit.addEventListener('change', onUbahLayar))
onBeforeUnmount(() => mqSempit.removeEventListener('change', onUbahLayar))

// Laporan driver — isinya diambil komponen dialognya sendiri saat dibuka.
const showLaporanDialog = ref(false)
const laporanRitId      = ref(null)

// Penanda di menu menghitung semua bulan, jadi saat filter ini aktif batas tanggal dilepas
// supaya rit lama yang belum ditanggapi tidak tersembunyi oleh preset "Bulan Ini".
function ubahBelumDitanggapi(aktif) {
  filters.belumDitanggapi = aktif
  if (aktif) {
    activePreset.value = null
    filters.dari = null
    filters.sampai = null
  }
  loadData(1)
}

// Setelah admin menanggapi laporan, angka "belum ditanggapi" rit itu diperbarui di tempat.
async function perbaruiRit(id) {
  try {
    const res = await daftarMuatService.show(id)
    const i = list.value.findIndex(r => r.id === id)
    if (i !== -1 && res.data) list.value[i] = res.data
  } catch { /* angka diperbarui saat halaman dimuat ulang */ }
}

function openLaporan(item) {
  laporanRitId.value      = item.id
  showLaporanDialog.value = true
}

const presets = [
  { key: 'hari_ini',   label: 'Hari Ini' },
  { key: 'minggu_ini', label: 'Minggu Ini' },
  { key: 'bulan_ini',  label: 'Bulan Ini' },
  { key: 'bulan_lalu', label: 'Bulan Lalu' },
]

function getPresetRange(key) {
  const now = new Date()
  const y   = now.getFullYear()
  const m   = now.getMonth()
  const d   = now.getDate()
  const dow = now.getDay()
  if (key === 'hari_ini')   return { dari: new Date(y, m, d), sampai: new Date(y, m, d) }
  if (key === 'minggu_ini') return { dari: new Date(y, m, d - ((dow + 6) % 7)), sampai: new Date() }
  if (key === 'bulan_ini')  return { dari: new Date(y, m, 1), sampai: new Date(y, m + 1, 0) }
  if (key === 'bulan_lalu') return { dari: new Date(y, m - 1, 1), sampai: new Date(y, m, 0) }
  return { dari: null, sampai: null }
}

function applyPreset(key) {
  activePreset.value = key
  const { dari, sampai } = getPresetRange(key)
  filters.dari   = dari
  filters.sampai = sampai
  loadData(1)
}

function onCustomDate() {
  activePreset.value = null
  loadData(1)
}

const totalTonAll    = computed(() => list.value.reduce((s, i) => s + Number(i.total_muatan_ton ?? 0), 0))
const totalTujuanAll = computed(() => list.value.reduce((s, i) => s + (i.jumlah_tujuan ?? 0), 0))

function fmtTon(val) {
  const n = parseFloat(val) || 0
  return parseFloat(n.toFixed(3)).toString()
}

function fmtKg(val) {
  return Math.round((parseFloat(val) || 0) * 1000).toLocaleString('id-ID')
}

/// ── Cetak rekap ───────────────────────────────────────────────

const menuCetak = ref(null)
const mencetak  = ref(false)

/*
 * Dua bentuk rekap dijadikan satu tombol.
 *
 * Yang harian sebelumnya ada di halaman Muat, dan memang cocok di sana selama
 * dipakai untuk merencanakan hari itu — isinya termasuk sopir yang belum
 * tertugaskan dan yang izin. Tapi mencetak rekap jadi tersebar di dua halaman,
 * dan admin harus ingat yang mana di mana. Dikumpulkan di sini, keduanya
 * berdampingan dan bedanya kelihatan langsung dari labelnya.
 *
 * Rekap harian memakai tanggal AWAL dari filter, bukan hari ini, supaya yang
 * tercetak selalu cocok dengan yang sedang dilihat di layar.
 */
// Admin yang hanya memegang satu jenis muatan tidak diberi pilihan jenis lain.
function pilihanJenis(cetak) {
  const subsidi = can('muat_subsidi')
  const nonSubsidi = can('muat_non_subsidi')
  return [
    subsidi && nonSubsidi && { label: 'Semua Muatan', icon: 'pi pi-list', command: () => cetak(null) },
    subsidi               && { label: 'Subsidi',      icon: 'pi pi-tag',  command: () => cetak('subsidi') },
    nonSubsidi            && { label: 'Non Subsidi',  icon: 'pi pi-tag',  command: () => cetak('non_subsidi') },
  ].filter(Boolean)
}

const pilihanCetak = computed(() => [
  { label: 'Rentang tanggal', items: pilihanJenis(cetakRekap) },
  { label: labelHarian.value, items: pilihanJenis(cetakHarian) },
])

const labelHarian = computed(() => {
  const d = toDateStr(filters.dari)
  return d ? `Satu hari: ${formatDate(d)}` : 'Satu hari'
})

/** Rekap satu hari: ikut menyebut sopir yang tidak jalan dan yang izin. */
async function cetakHarian(jenis) {
  const tanggal = toDateStr(filters.dari)
  if (!tanggal) {
    toast.error('Pilih dulu tanggal awal periodenya.')
    return
  }

  mencetak.value = true
  try {
    const res = await fetch(
      daftarMuatService.rekapHarianUrl(tanggal, jenis),
      { headers: { Authorization: `Bearer ${authStore.token}` } },
    )
    if (!res.ok) throw new Error()

    const blob = await res.blob()
    const a    = document.createElement('a')
    a.href     = URL.createObjectURL(blob)
    a.download = `RekapMuat_${tanggal}.pdf`
    a.click()
    URL.revokeObjectURL(a.href)
  } catch {
    toast.error('Gagal membuat rekap harian')
  } finally {
    mencetak.value = false
  }
}

async function cetakRekap(jenis) {
  const dari   = toDateStr(filters.dari)
  const sampai = toDateStr(filters.sampai)

  // Rekapnya selalu berupa rentang, jadi tanggalnya wajib. Tombol preset di
  // atas sudah mengisinya, tapi dua kolom tanggal itu bisa dikosongkan
  // sendiri — dan tanpa penjagaan ini yang muncul cuma galat 422 dari server
  // yang tidak menjelaskan apa yang harus dilakukan.
  if (!dari || !sampai) {
    toast.error('Pilih dulu tanggal awal dan akhir periodenya.')
    return
  }

  mencetak.value = true
  try {
    const res = await fetch(
      daftarMuatService.rekapPeriodeUrl(dari, sampai, jenis),
      { headers: { Authorization: `Bearer ${authStore.token}` } },
    )
    if (!res.ok) throw new Error()

    const blob = await res.blob()
    const a    = document.createElement('a')
    a.href     = URL.createObjectURL(blob)
    a.download = `RekapKiriman_${dari}-${sampai}.pdf`
    a.click()
    URL.revokeObjectURL(a.href)
  } catch {
    toast.error('Gagal membuat rekap')
  } finally {
    mencetak.value = false
  }
}

// Pakai tanggal lokal, bukan toISOString(): itu UTC, sehingga tengah malam WIB
// terbaca hari sebelumnya ("Bulan Ini" Oktober terkirim 30 Sep s.d. 30 Okt).
function toDateStr(d) {
  if (!d) return undefined
  if (!(d instanceof Date)) return String(d).slice(0, 10)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

async function loadData(page = currentPage.value) {
  currentPage.value = page
  loading.value     = true
  try {
    const res = await daftarMuatService.getAll({
      search:         filters.search || undefined,
      tanggapan:      filters.belumDitanggapi ? 'belum' : undefined,
      tanggal_dari:   toDateStr(filters.dari),
      tanggal_sampai: toDateStr(filters.sampai),
      page,
      per_page: perPage.value,
    })
    list.value = res.data ?? []
    meta.value = res.meta ?? meta.value
    perPage.value = safeRows(meta.value.per_page)
  } catch {
    toast.error('Gagal memuat riwayat kiriman')
  } finally {
    loading.value = false
  }
}

const onSearch = debounce(() => loadData(1), 350)

function onPage(event) {
  perPage.value = safeRows(event.rows)
  loadData(event.page + 1)
}

function resetFilters() {
  filters.search = ''
  filters.belumDitanggapi = false
  applyPreset('bulan_ini')
}

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

useStaleRefresh(() => applyPreset('bulan_ini'))
</script>

<style scoped>
.rk-view { display: flex; flex-direction: column; gap: 1.25rem; animation: fadeUp var(--dur-base) var(--ease) both; }

/* ── Filter card ──────────────────────────────── */
.filter-card :deep(.p-card-body) { padding: 0.875rem 1rem; }

.filter-top {
  display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;
  margin-bottom: 0.75rem;
}
.preset-group { display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; }
.preset-btn {
  padding: 0.3rem 0.9rem; border-radius: 20px;
  border: 1px solid var(--s-border);
  background: var(--s-0); color: var(--t-primary);
  font-size: 0.8rem; font-weight: 600;
  cursor: pointer; transition: all 0.15s;
  white-space: nowrap;
}
.preset-btn:hover  { border-color: var(--c-brand-500); color: var(--c-brand-600); background: rgba(249,115,22,.06); }
.preset-btn.active { background: var(--c-brand-500); border-color: var(--c-brand-500); color: #fff; }

.date-range-group {
  display: flex; align-items: center; gap: 0.4rem; margin-left: auto;
}
.range-sep { color: var(--t-muted); font-size: 0.875rem; }
.date-range-group :deep(.p-datepicker-input) { font-size: 0.82rem; }

.filter-bottom { display: flex; align-items: center; gap: 0.6rem; }
.search-field  { flex: 1; }

/* ── Stats ─────────────────────────────────────── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.875rem;
}

.stat-card {
  display: flex; align-items: center; gap: 1rem;
  padding: 1rem 1.25rem;
  background: var(--s-0);
  border: 1px solid var(--s-border);
  border-radius: 12px;
  border-left-width: 4px;
}
.stat-card--brand   { border-left-color: var(--c-brand-500); }
.stat-card--success { border-left-color: var(--c-success); }
.stat-card--info    { border-left-color: var(--c-info); }

.stat-icon-wrap {
  width: 44px; height: 44px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  font-size: 1.1rem;
}
.stat-icon--brand   { background: rgba(249,115,22,.12); color: var(--c-brand-500); }
.stat-icon--success { background: rgba(22,163,74,.12);  color: var(--c-success); }
.stat-icon--info    { background: rgba(37,99,235,.12);  color: var(--c-info); }

.stat-body  { display: flex; flex-direction: column; gap: 2px; }
.stat-val   { font-size: 1.6rem; font-weight: 700; color: var(--t-primary); line-height: 1.1; }
.stat-unit  { font-size: 0.875rem; font-weight: 500; color: var(--t-muted); }
.stat-label { font-size: 0.78rem; color: var(--t-muted); font-weight: 500; }

.stat-skeleton {
  display: inline-block; width: 60px; height: 1.6rem;
  background: var(--s-1); border-radius: 6px;
  animation: pulse 1.4s ease-in-out infinite;
}
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.4; } }

/* ── Table cells ───────────────────────────────── */
.tanggal-rit-cell { display: flex; flex-direction: column; gap: 3px; }
.date-text     { font-size: 0.82rem; color: var(--t-muted); white-space: nowrap; }
.rit-badge { display: inline-block; font-size: 0.68rem; font-weight: 700; padding: 1px 7px; border-radius: 8px; width: fit-content; }
.rit-1 { background: var(--c-success-soft); color: var(--c-success); }
.rit-2 { background: var(--c-warning-soft); color: var(--c-warning); }
.rit-3 { background: var(--c-danger-soft); color: var(--c-danger); }
.kendaraan-cell{ display: flex; flex-direction: column; gap: 1px; }
.polisi        { font-weight: 600; font-size: 0.875rem; color: var(--t-primary); }
.jenis         { font-size: 0.72rem; color: var(--t-muted); }
.driver-name   { font-size: 0.875rem; font-weight: 500; color: var(--t-primary); }
.na-tag        { font-size: 0.75rem; color: var(--t-muted); }
.tonase        { font-weight: 700; font-size: 0.875rem; color: var(--c-brand-600); }
.tujuan-cell   { display: flex; flex-direction: column; gap: 3px; }
.tujuan-badge  {
  display: inline-block;
  background: rgba(249,115,22,.1); color: var(--c-brand-600);
  font-size: 0.7rem; font-weight: 700;
  padding: 1px 7px; border-radius: 8px; width: fit-content;
}
.tujuan-list   { font-size: 0.75rem; color: var(--t-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }

/* ── Row expansion ─────────────────────────────── */
.expansion-body {
  /* Baris perluasan ini hidup di dalam tabel yang bisa digulir menyamping,
     jadi lebarnya ikut selebar SELURUH tabel, bukan selebar layar. Tanpa
     dipatok, lencana kode area dan status terdorong ke ujung kanan tabel lalu
     terpotong tepi layar — isinya ada, cuma tidak pernah terlihat.

     sticky menahannya menempel di tepi kiri saat tabel digeser, dan max-width
     menjaga isinya tetap berkumpul dekat nama tujuan. */
  position: sticky;
  left: 0;
  max-width: 880px;
  padding: 0.875rem 1.5rem 0.875rem 3.5rem;
  background: var(--s-1);
  border-top: 1px solid var(--s-border);
}
.exp-title {
  font-size: 0.72rem; font-weight: 700;
  color: var(--t-muted); text-transform: uppercase;
  letter-spacing: 0.05em; margin-bottom: 0.6rem;
}
.exp-items { display: flex; flex-direction: column; gap: 0; }
.exp-item  {
  display: flex; align-items: center; gap: 0.75rem;
  font-size: 0.82rem; padding: 0.4rem 0;
  border-bottom: 1px solid var(--s-border);
  /* Nama toko yang panjang tidak boleh mendorong lencana keluar barisnya:
     yang mengalah namanya, bukan penanda kode area dan statusnya. */
  flex-wrap: wrap;
}
.exp-item:last-child { border-bottom: none; }
.exp-num   {
  width: 20px; height: 20px; border-radius: 50%;
  background: var(--c-brand-500); color: #fff;
  font-size: 0.68rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.exp-lokasi {
  flex: 1 1 220px; min-width: 0;
  color: var(--t-primary); font-weight: 500;
}
.exp-kode   {
  font-size: 0.7rem; color: #fff;
  background: var(--c-brand-600);
  padding: 1px 6px; border-radius: 6px; font-weight: 700;
  flex-shrink: 0; white-space: nowrap;
}
.exp-ton { font-weight: 700; color: var(--c-brand-600); white-space: nowrap; }
.kategori-cell   { display: flex; flex-wrap: wrap; gap: 4px; }
.kategori-mobile { margin-bottom: 0.6rem; }
.kat {
  font-size: 0.68rem; font-weight: 700; padding: 2px 7px;
  border-radius: 8px; white-space: nowrap;
}
.kat-muatan   { background: var(--c-nila-soft); color: var(--c-nila); }

.exp-status  { font-size: 0.68rem; font-weight: 700; padding: 1px 7px; border-radius: 8px; white-space: nowrap; flex-shrink: 0; }
.exp-catatan {
  flex-basis: 100%; font-size: 0.75rem; color: var(--t-muted);
  display: flex; align-items: center; gap: 0.35rem;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.exp-catatan i { font-size: 0.7rem; flex-shrink: 0; }

/* ── Progres driver ────────────────────────────── */
/* Dulu dua ikon telanjang tanpa latar maupun garis, jadi tidak terbaca
   sebagai tombol sama sekali — apalagi keduanya sewarna. Sekarang bergaris:
   yang kiri berlabel karena itu tindakan utama di baris ini, yang kanan cukup
   ikon karena lambang PDF sudah dikenal dan labelnya akan memakan lebar
   kolom. */
.action-btns { display: flex; gap: 0.35rem; align-items: center; }
.action-btns :deep(.p-button) { white-space: nowrap; }
.action-btns :deep(.p-button-label) { font-size: 0.78rem; font-weight: 600; }
.progres-cell    { display: flex; flex-direction: column; gap: 3px; }
.progres-status  { display: inline-block; font-size: 0.68rem; font-weight: 700; padding: 1px 7px; border-radius: 8px; width: fit-content; }
.progres-angka   { font-size: 0.73rem; color: var(--t-muted); }
.progres-masalah { color: var(--c-warning); font-weight: 600; }
.progres-tanggapan { color: var(--c-danger); font-weight: 600; }
.toggle-tanggapan { flex: none; }
.muat-card-progres { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.65rem; }

/* Status rit dan status bongkar per tujuan memakai palet yang sama supaya
   admin tidak perlu menghafal dua sistem warna. */
.st-menunggu, .sb-menunggu { background: var(--s-2); color: var(--t-secondary); }
.st-dimuat,   .sb-tiba     { background: var(--c-info-soft); color: var(--c-info); }
.st-berangkat              { background: var(--c-info-soft); color: var(--c-info); }
.st-selesai,  .sb-selesai  { background: var(--c-success-soft); color: var(--c-success); }
.st-batal,    .sb-gagal    { background: var(--c-danger-soft); color: var(--c-danger); }
.sb-bermasalah             { background: var(--c-warning-soft); color: var(--c-warning); }

/* ── Empty + pagination ────────────────────────── */
.empty-state   { display: flex; flex-direction: column; align-items: center; gap: 0.75rem; padding: 3rem; color: var(--t-muted); }
.empty-state i { font-size: 2.5rem; opacity: 0.5; }
.pagination-bar { padding-top: 0.75rem; display: flex; justify-content: center; }

/* ── Mobile cards ──────────────────────────────── */
.desktop-only { display: block; }
.mobile-only  { display: none; }

.muat-card {
  background: var(--s-0); border: 1px solid var(--s-border);
  border-radius: 12px; padding: 0.9rem 1rem; margin-bottom: 0.6rem;
  border-left: 3px solid var(--c-brand-500);
}
.muat-card-top     { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.65rem; }
.muat-card-date    { font-size: 0.82rem; color: var(--t-muted); display: flex; align-items: center; gap: 0.35rem; }
.muat-card-body    { display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 0.7rem; }
.muat-card-row     { display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.83rem; color: var(--t-primary); }
.muat-card-row i   { color: var(--t-muted); font-size: 0.8rem; margin-top: 2px; flex-shrink: 0; }
.muat-card-row .muted { color: var(--t-muted); }
.muat-card-actions { display: flex; gap: 0.3rem; padding-top: 0.5rem; border-top: 1px solid var(--s-border); }
.mobile-loading    { text-align: center; padding: 2rem; color: var(--t-muted); display: flex; align-items: center; justify-content: center; gap: 0.5rem; }

@media (max-width: 768px) {
  .desktop-only  { display: none; }
  .mobile-only   { display: block; }
  /* Tiga kartu angka tetap berjajar, tidak bertumpuk.
   *
   * Sebagai tumpukan, ketiganya memakan sekitar 380px — dan bersama filter di
   * atasnya, pemakai harus menggulir hampir dua layar penuh sebelum melihat
   * satu kiriman pun. Padahal isinya cuma tiga angka pendek.
   *
   * Ikonnya dilepas di layar sempit: garis warna di tepi kiri kartu sudah
   * membedakan ketiganya, dan ikon itu yang paling banyak memakan lebar
   * justru saat lebarnya paling sedikit.
   */
  .stats-grid       { grid-template-columns: repeat(3, 1fr); gap: 0.5rem; }
  .stat-card        { flex-direction: column; align-items: flex-start; gap: 0.15rem; padding: 0.6rem 0.7rem; }
  .stat-icon-wrap   { display: none; }
  .stat-val         { font-size: 1.15rem; line-height: 1.2; }
  .stat-unit        { font-size: 0.7rem; }
  .stat-label       { font-size: 0.68rem; line-height: 1.25; }

  .filter-top    { flex-direction: column; align-items: stretch; gap: 0.5rem; }
  /* Di 360px dengan huruf 16px (layar sentuh) "31/10/2026" terpotong jadi
   * "31/10/202". Ikon hapus dilepas di script (layarSempit): tombol periode dan
   * reset sudah menggantikannya, dan ikon itu memakan ruang terbanyak. */
  .date-range-group {
    display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
    gap: 0.3rem; margin-left: 0;
  }
  .date-range-group :deep(.p-datepicker) { min-width: 0; }
  .date-range-group :deep(.p-datepicker-input) { min-width: 0; padding-inline: 0.5rem; }

  /* Tombol segarkan tetap sebaris dengan kolom pencarian. Diturunkan ke baris
   * sendiri, satu ikon kecil itu menghabiskan satu baris penuh. */
  .filter-bottom { flex-direction: row; align-items: center; }
}

@media (min-width: 769px) and (max-width: 1024px) {
  .stats-grid { grid-template-columns: repeat(3, 1fr); }
}

/* ── Dark mode overrides ─────────────────────────────────────── */
:global(.app-dark) .rit-1           { background: rgba(34,197,94,.15);  color: var(--c-success); }
:global(.app-dark) .rit-2           { background: rgba(245,158,11,.15); color: var(--c-warning); }
:global(.app-dark) .rit-3           { background: rgba(239,68,68,.15);  color: var(--c-danger); }
:global(.app-dark) .stat-icon--success { color: var(--c-success); }
:global(.app-dark) .stat-icon--info    { color: var(--c-info); }
:global(.app-dark) .stat-card--success { border-left-color: var(--c-success); }
:global(.app-dark) .stat-card--info    { border-left-color: var(--c-info); }
</style>
