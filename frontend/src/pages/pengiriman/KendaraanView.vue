<template>
  <div class="kendaraan-view">
    <PageHeader title="Master Kendaraan" subtitle="Kelola data armada truk pengiriman." :hide-title="dalamTab">
      <template #actions>
        <Button v-if="can('manage_armada')" icon="pi pi-plus" label="Tambah Kendaraan" @click="openCreate" />
      </template>
    </PageHeader>

    <!-- Filter bar -->
    <Card class="filter-card">
      <template #content>
        <div class="filter-bar">
          <IconField class="search-field">
            <InputIcon class="pi pi-search" />
            <InputText v-model="filters.search" placeholder="Cari no. polisi / merk..." @input="onSearch" />
          </IconField>
          <Select
            v-model="filters.status"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Semua Status"
            style="min-width: 165px"
            showClear
            @change="loadData"
          />
          <Button icon="pi pi-refresh" text plain @click="resetFilters" v-tooltip="'Reset filter'" />
        </div>
        <div class="stats-row" v-if="!loading">
          <span class="stat"><b>{{ items.length }}</b> unit</span>
          <span class="stat tersedia"><i class="pi pi-check-circle" /> {{ items.filter(i => i.status_kendaraan === 'Tersedia').length }} Tersedia</span>
          <span class="stat tidak"><i class="pi pi-times-circle" /> {{ items.filter(i => i.status_kendaraan === 'Tidak Tersedia').length }} Tidak Tersedia</span>
        </div>
      </template>
    </Card>

    <!-- Mobile card list -->
    <div class="mobile-only">
      <div v-if="loading" class="mobile-loading">
        <i class="pi pi-spin pi-spinner" /> Memuat data...
      </div>
      <div v-else-if="!items.length" class="empty-state">
        <i class="pi pi-truck" />
        <span>{{ filters.search || filters.status ? 'Tidak ada kendaraan sesuai filter' : 'Belum ada data kendaraan' }}</span>
        <Button v-if="!filters.search && !filters.status && can('manage_armada')" label="Tambah Kendaraan Pertama" size="small" outlined @click="openCreate" />
      </div>
      <TransitionGroup name="card-list" tag="div" class="kendaraan-cards">
        <div v-for="(kend, i) in items" :key="kend.id" :style="{'--i': i}" class="kendaraan-card">
          <div class="kc-top">
            <div class="kc-info">
              <span class="kc-polisi">{{ kend.nomor_polisi }}</span>
              <span class="kc-detail">{{ kend.jenis_kendaraan }} · {{ kend.merk }} {{ kend.model }}</span>
            </div>
            <Tag :value="kend.status_kendaraan" :severity="kend.status_kendaraan === 'Tersedia' ? 'success' : 'danger'" />
          </div>
          <div class="kc-details">
            <span class="kc-item"><i class="pi pi-calendar" /> {{ kend.tahun_pembuatan }}</span>
            <span class="kc-item"><i class="pi pi-box" /> {{ (kend.kapasitas_muatan_ton * 1000).toLocaleString('id-ID') }} Kg</span>
            <span class="kc-item"><i class="pi pi-user" /> {{ kend.driver?.nama_driver ?? 'Belum ada driver' }}</span>
          </div>
          <div v-if="can('manage_armada')" class="kc-actions">
            <Button icon="pi pi-pencil" label="Edit" size="small" text @click="openEdit(kend)" />
            <Button icon="pi pi-trash" label="Hapus" size="small" text severity="danger" @click="confirmDelete(kend)" />
          </div>
        </div>
      </TransitionGroup>
    </div>

    <Card class="desktop-only">
      <template #content>
        <DataTable :value="items" :loading="loading" stripedRows dataKey="id">

          <Column header="No." style="width: 56px; text-align: center">
            <template #body="{ index }">{{ index + 1 }}</template>
          </Column>
          <Column header="No. Polisi" style="min-width: 130px">
            <template #body="{ data }">
              <span class="polisi">{{ data.nomor_polisi }}</span>
            </template>
          </Column>

          <Column header="Kendaraan" style="min-width: 180px">
            <template #body="{ data }">
              <div class="kend-cell">
                <span class="jenis">{{ data.jenis_kendaraan }}</span>
                <span class="merk-model">{{ data.merk }} {{ data.model }} ({{ data.tahun_pembuatan }})</span>
              </div>
            </template>
          </Column>

          <Column header="Kapasitas" style="min-width: 110px">
            <template #body="{ data }">
              <span class="kapasitas">{{ (data.kapasitas_muatan_ton * 1000).toLocaleString('id-ID') }} Kg</span>
            </template>
          </Column>

          <Column header="Driver" style="min-width: 150px">
            <template #body="{ data }">
              <span class="driver-nama">{{ data.driver?.nama_driver ?? '-' }}</span>
            </template>
          </Column>

          <Column header="Status" style="min-width: 130px">
            <template #body="{ data }">
              <Tag
                :value="data.status_kendaraan"
                :severity="data.status_kendaraan === 'Tersedia' ? 'success' : 'danger'"
              />
            </template>
          </Column>

          <Column header="Aksi" style="min-width: 100px; text-align: center">
            <template #body="{ data }">
              <div class="action-btns">
                <Button v-if="can('manage_armada')" icon="pi pi-pencil" size="small" text v-tooltip="'Edit'" @click="openEdit(data)" />
                <Button v-if="can('manage_armada')" icon="pi pi-trash" size="small" text severity="danger" v-tooltip="'Hapus'" @click="confirmDelete(data)" />
              </div>
            </template>
          </Column>

          <template #empty>
            <div class="empty-state">
              <i class="pi pi-truck" />
              <span>{{ filters.search || filters.status ? 'Tidak ada kendaraan sesuai filter' : 'Belum ada data kendaraan' }}</span>
              <Button v-if="!filters.search && !filters.status && can('manage_armada')" label="Tambah Kendaraan Pertama" size="small" outlined @click="openCreate" />
            </div>
          </template>
        </DataTable>
      </template>
    </Card>

    <!-- Dialog Create/Edit -->
    <Dialog v-model:visible="showDialog" :header="editId ? 'Edit Kendaraan' : 'Tambah Kendaraan'" modal :style="{ width: '520px' }" :closable="!saving">
      <div class="form-grid">
        <div class="form-row">
          <div class="form-group">
            <label>Jenis Kendaraan <span class="req">*</span></label>
            <InputText v-model="form.jenis_kendaraan" placeholder="Truk, Pickup..." class="full-width" />
          </div>
          <div class="form-group">
            <label>No. Polisi <span class="req">*</span></label>
            <InputText v-model="form.nomor_polisi" placeholder="B 1234 XYZ" class="full-width" />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Merk <span class="req">*</span></label>
            <InputText v-model="form.merk" placeholder="Mitsubishi, Hino..." class="full-width" />
          </div>
          <div class="form-group">
            <label>Model</label>
            <InputText v-model="form.model" placeholder="Colt Diesel FE..." class="full-width" />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Tahun Pembuatan <span class="req">*</span></label>
            <InputNumber v-model="form.tahun_pembuatan" :min="1990" :max="2030" :useGrouping="false" class="full-width" />
          </div>
          <div class="form-group">
            <label>Kapasitas Muatan (Kg) <span class="req">*</span></label>
            <InputNumber
              :modelValue="form.kapasitas_muatan_ton != null ? Math.round(form.kapasitas_muatan_ton * 1000) : null"
              :min="500"
              :maxFractionDigits="0"
              class="full-width"
              @update:modelValue="(v) => form.kapasitas_muatan_ton = v != null ? v / 1000 : null"
            />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Driver</label>
            <Select v-model="form.driver_id" :options="driverOptions" optionLabel="label" optionValue="id" placeholder="Pilih driver..." class="full-width" showClear filter />
          </div>
          <div class="form-group">
            <label>Status</label>
            <Select v-model="form.status_kendaraan" :options="statusOptions" optionLabel="label" optionValue="value" class="full-width" />
          </div>
        </div>
      </div>

      <template #footer>
        <Button label="Batal" severity="secondary" text @click="showDialog = false" :disabled="saving" />
        <Button :label="editId ? 'Simpan Perubahan' : 'Tambah Kendaraan'" icon="pi pi-check" :loading="saving" @click="doSave" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { kendaraanService } from '@/services/kendaraan.service'
import { driverService } from '@/services/driver.service'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from '@/composables/useToast'
import { usePermission } from '@/composables/usePermission'
import { debounce } from '@/utils/helpers'
import PageHeader from '@/components/common/PageHeader.vue'

// true saat dirender sebagai isi tab di MasterArmadaView — judul "Master
// Kendaraan" disembunyikan supaya tidak dobel dengan label tab-nya sendiri.
defineProps({ dalamTab: { type: Boolean, default: false } })

const confirm = useConfirm()
const toast   = useToast()
const { can } = usePermission()

const loading      = ref(false)
const saving       = ref(false)
const items        = ref([])
const driverOptions = ref([])
const showDialog   = ref(false)
const editId       = ref(null)

const statusOptions = [
  { label: 'Tersedia',       value: 'Tersedia' },
  { label: 'Tidak Tersedia', value: 'Tidak Tersedia' },
]

const filters = reactive({ search: '', status: null })

const form = reactive({
  jenis_kendaraan:     '',
  nomor_polisi:        '',
  merk:                '',
  model:               '',
  tahun_pembuatan:     new Date().getFullYear(),
  kapasitas_muatan_ton: null,
  driver_id:           null,
  status_kendaraan:    'Tersedia',
})

async function loadData() {
  loading.value = true
  try {
    const res = await kendaraanService.getAll({ search: filters.search || undefined, status: filters.status || undefined })
    items.value = res.data ?? []
  } catch {
    toast.error('Gagal memuat data kendaraan')
  } finally {
    loading.value = false
  }
}

async function loadDriverOptions() {
  const res = await driverService.getAll({ status: 'Aktif' })
  driverOptions.value = (res.data ?? []).map(d => ({ id: d.id, label: d.nama_driver }))
}

const onSearch = debounce(() => loadData(), 350)

function resetFilters() {
  filters.search = ''
  filters.status = null
  loadData()
}

function openCreate() {
  editId.value = null
  Object.assign(form, {
    jenis_kendaraan: '', nomor_polisi: '', merk: '', model: '',
    tahun_pembuatan: new Date().getFullYear(), kapasitas_muatan_ton: null,
    driver_id: null, status_kendaraan: 'Tersedia',
  })
  showDialog.value = true
}

function openEdit(item) {
  editId.value = item.id
  Object.assign(form, {
    jenis_kendaraan:      item.jenis_kendaraan,
    nomor_polisi:         item.nomor_polisi,
    merk:                 item.merk,
    model:                item.model,
    tahun_pembuatan:      item.tahun_pembuatan,
    kapasitas_muatan_ton: item.kapasitas_muatan_ton,
    driver_id:            item.driver_id ?? null,
    status_kendaraan:     item.status_kendaraan,
  })
  showDialog.value = true
}

async function doSave() {
  if (!form.jenis_kendaraan || !form.nomor_polisi || !form.merk || !form.tahun_pembuatan || !form.kapasitas_muatan_ton) {
    toast.error('Lengkapi field yang wajib diisi')
    return
  }
  saving.value = true
  try {
    if (editId.value) {
      await kendaraanService.update(editId.value, { ...form })
      toast.success('Data kendaraan berhasil diupdate')
    } else {
      await kendaraanService.store({ ...form })
      toast.success('Kendaraan baru berhasil ditambahkan')
    }
    showDialog.value = false
    loadData()
  } catch (err) {
    const errors = err?.response?.data?.errors
    if (errors) {
      const firstMsg = Object.values(errors)[0]?.[0]
      toast.error(firstMsg ?? err?.response?.data?.message ?? 'Gagal menyimpan data kendaraan')
    } else {
      toast.error(err?.response?.data?.message ?? 'Gagal menyimpan data kendaraan')
    }
  } finally {
    saving.value = false
  }
}

function confirmDelete(item) {
  confirm.require({
    message: `Hapus kendaraan "${item.nomor_polisi}"?`,
    header: 'Hapus Kendaraan',
    icon: 'pi pi-exclamation-triangle',
    acceptSeverity: 'danger',
    acceptLabel: 'Ya, Hapus',
    rejectLabel: 'Batal',
    accept: async () => {
      try {
        await kendaraanService.destroy(item.id)
        toast.success('Kendaraan berhasil dihapus')
        loadData()
      } catch (err) {
        toast.error(err?.response?.data?.message ?? 'Gagal menghapus kendaraan')
      }
    },
  })
}

onMounted(() => {
  loadData()
  loadDriverOptions()
})
</script>

<style scoped>
.kendaraan-view { display: flex; flex-direction: column; gap: 1.25rem; animation: fadeUp var(--dur-base) var(--ease) both; }
.filter-card :deep(.p-card-body) { padding: 0.75rem 1rem; }
.filter-bar { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
.search-field { flex: 1; min-width: 210px; }
.stats-row { margin-top: 0.5rem; display: flex; gap: 1.2rem; font-size: 0.8rem; color: var(--p-text-muted-color); }
.stat { display: flex; align-items: center; gap: 0.3rem; }
.stat b { color: var(--p-text-color); }
.stat.tersedia { color: var(--c-success); }
.stat.tidak { color: var(--c-danger); }
.polisi { font-weight: 700; font-size: 0.9rem; letter-spacing: 0.03em; }
.kend-cell { display: flex; flex-direction: column; gap: 1px; }
.jenis { font-weight: 600; font-size: 0.875rem; }
.merk-model { font-size: 0.73rem; color: var(--p-text-muted-color); }
.kapasitas { font-weight: 600; font-size: 0.875rem; color: var(--c-brand-600); }
.driver-nama { font-size: 0.85rem; }
.action-btns { display: flex; justify-content: center; gap: 0.25rem; }
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 0.75rem; padding: 3rem; color: var(--p-text-muted-color); }
.empty-state i { font-size: 2.5rem; }
.form-grid { display: flex; flex-direction: column; gap: 1rem; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.form-group { display: flex; flex-direction: column; gap: 0.35rem; }
.form-group label { font-size: 0.85rem; font-weight: 600; }
.req { color: var(--c-brand-500); }
.full-width { width: 100%; }

/* Mobile */
.mobile-only { display: none; }
.desktop-only { display: block; }
.kendaraan-cards { display: flex; flex-direction: column; gap: 0.75rem; }
.kendaraan-card { background: var(--p-card-background); border: 1px solid var(--p-card-border-color); border-radius: 10px; padding: 0.9rem 1rem; display: flex; flex-direction: column; gap: 0.6rem; transition: box-shadow 0.18s ease, transform 0.18s ease; }
.kendaraan-card:active { transform: scale(0.985); }

/* Mobile card stagger animation */
.card-list-enter-active { transition: opacity 0.3s ease, transform 0.3s ease; transition-delay: calc(var(--i, 0) * 0.045s); }
.card-list-enter-from   { opacity: 0; transform: translateY(14px); }
.card-list-leave-active { transition: opacity 0.18s ease; }
.card-list-leave-to     { opacity: 0; }
.card-list-move         { transition: transform 0.3s ease; }
.kc-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem; }
.kc-info { display: flex; flex-direction: column; gap: 2px; }
.kc-polisi { font-weight: 700; font-size: 1rem; letter-spacing: 0.04em; }
.kc-detail { font-size: 0.78rem; color: var(--p-text-muted-color); }
.kc-details { display: flex; flex-direction: column; gap: 0.3rem; }
.kc-item { font-size: 0.82rem; color: var(--p-text-muted-color); display: flex; align-items: center; gap: 0.4rem; }
.kc-item i { font-size: 0.8rem; flex-shrink: 0; }
.kc-actions { display: flex; gap: 0.4rem; border-top: 1px solid var(--p-card-border-color); padding-top: 0.5rem; }
.mobile-loading { text-align: center; padding: 2rem; color: var(--p-text-muted-color); display: flex; align-items: center; justify-content: center; gap: 0.5rem; }

@media (max-width: 768px) {
  .mobile-only { display: block; }
  .desktop-only { display: none; }
  .form-row { grid-template-columns: 1fr; }
}

/* ── Dark mode overrides ─────────────────────────────────────── */
:global(.app-dark) .stat.tersedia { color: var(--c-success); }
:global(.app-dark) .stat.tidak    { color: var(--c-danger); }
</style>
