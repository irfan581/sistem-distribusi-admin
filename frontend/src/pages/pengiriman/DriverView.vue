<template>
  <div class="driver-view">
    <PageHeader title="Master Driver" subtitle="Kelola data pengemudi armada pengiriman." :hide-title="dalamTab">
      <template #actions>
        <Button v-if="can('manage_armada')" icon="pi pi-plus" label="Tambah Driver" @click="openCreate" />
      </template>
    </PageHeader>

    <!-- Filter bar -->
    <Card class="filter-card">
      <template #content>
        <div class="filter-bar">
          <IconField class="search-field">
            <InputIcon class="pi pi-search" />
            <InputText v-model="filters.search" placeholder="Cari nama, HP, kode..." @input="onSearch" />
          </IconField>
          <Select
            v-model="filters.status"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Semua Status"
            style="min-width: 155px"
            showClear
            @change="loadData"
          />
          <Button icon="pi pi-refresh" text plain @click="resetFilters" v-tooltip="'Reset filter'" />
        </div>
        <div class="stats-row" v-if="!loading">
          <span class="stat"><b>{{ items.length }}</b> driver</span>
          <span class="stat aktif"><i class="pi pi-check-circle" /> {{ items.filter(i => i.status_kerja === 'Aktif').length }} Aktif</span>
          <span class="stat akun"><i class="pi pi-mobile" /> {{ items.filter(i => i.mobile_aktif).length }} punya akun aplikasi</span>
        </div>
      </template>
    </Card>

    <!-- Mobile card list -->
    <div class="mobile-only">
      <div v-if="loading" class="mobile-loading">
        <i class="pi pi-spin pi-spinner" /> Memuat data...
      </div>
      <div v-else-if="!items.length" class="empty-state">
        <i class="pi pi-id-card" />
        <span>{{ filters.search || filters.status ? 'Tidak ada driver sesuai filter' : 'Belum ada data driver' }}</span>
        <Button v-if="!filters.search && !filters.status && can('manage_armada')" label="Tambah Driver Pertama" size="small" outlined @click="openCreate" />
      </div>
      <TransitionGroup name="card-list" tag="div" class="driver-cards">
        <div v-for="(drv, i) in items" :key="drv.id" :style="{'--i': i}" class="driver-card">
          <div class="dc-top">
            <div class="dc-info">
              <span class="dc-nama">{{ drv.nama_driver }}</span>
              <span v-if="drv.nama_panggilan" class="dc-panggilan">"{{ drv.nama_panggilan }}"</span>
            </div>
            <Tag :value="drv.status_kerja" :severity="drv.status_kerja === 'Aktif' ? 'success' : 'secondary'" />
          </div>
          <div class="dc-details">
            <span class="dc-item"><i class="pi pi-phone" /> {{ drv.no_telepon }}</span>
            <span v-if="drv.jenis_sim" class="dc-item"><i class="pi pi-id-card" /> SIM {{ drv.jenis_sim }}</span>
            <span class="dc-item dc-alamat"><i class="pi pi-map-marker" /> {{ drv.alamat }}</span>
            <span class="dc-item">
              <i class="pi pi-mobile" />
              <span class="kode-driver">{{ drv.kode_driver ?? '-' }}</span>
              <Tag
                :value="drv.mobile_aktif ? 'Akun aktif' : 'Akun belum aktif'"
                :severity="drv.mobile_aktif ? 'success' : 'warn'"
              />
            </span>
          </div>
          <div v-if="can('manage_armada')" class="dc-actions">
            <Button icon="pi pi-pencil" label="Edit" size="small" text @click="openEdit(drv)" />
            <Button icon="pi pi-key" label="Akun" size="small" text @click="openAkunMenu($event, drv)" />
            <Button icon="pi pi-trash" label="Hapus" size="small" text severity="danger" @click="confirmDelete(drv)" />
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
          <Column header="Nama Driver" style="min-width: 200px">
            <template #body="{ data }">
              <div class="driver-nama-cell">
                <span class="nama-driver">{{ data.nama_driver }}</span>
                <span v-if="data.nama_panggilan" class="nama-panggilan">"{{ data.nama_panggilan }}"</span>
              </div>
            </template>
          </Column>

          <Column header="Akun Aplikasi" style="min-width: 170px">
            <template #body="{ data }">
              <div class="akun-cell">
                <span class="kode-driver">{{ data.kode_driver ?? '-' }}</span>
                <Tag
                  :value="data.mobile_aktif ? 'Aktif' : 'Belum aktif'"
                  :severity="data.mobile_aktif ? 'success' : 'warn'"
                  :icon="data.mobile_aktif ? 'pi pi-mobile' : 'pi pi-lock'"
                />
              </div>
            </template>
          </Column>

          <Column header="No. Telepon" style="min-width: 140px">
            <template #body="{ data }">
              <span class="telp">{{ data.no_telepon }}</span>
            </template>
          </Column>

          <Column header="Jenis SIM" style="min-width: 100px">
            <template #body="{ data }">
              <Tag :value="data.jenis_sim ?? '-'" severity="info" />
            </template>
          </Column>

          <Column header="Alamat" style="min-width: 200px">
            <template #body="{ data }">
              <span class="alamat">{{ data.alamat }}</span>
            </template>
          </Column>

          <Column header="Status" style="min-width: 110px">
            <template #body="{ data }">
              <Tag
                :value="data.status_kerja"
                :severity="data.status_kerja === 'Aktif' ? 'success' : 'secondary'"
              />
            </template>
          </Column>

          <Column header="Aksi" style="min-width: 140px; text-align: center">
            <template #body="{ data }">
              <div class="action-btns">
                <Button v-if="can('manage_armada')" icon="pi pi-pencil" size="small" text v-tooltip="'Edit'" @click="openEdit(data)" />
                <Button v-if="can('manage_armada')" icon="pi pi-key" size="small" text v-tooltip="'Akun aplikasi'" @click="openAkunMenu($event, data)" />
                <Button v-if="can('manage_armada')" icon="pi pi-trash" size="small" text severity="danger" v-tooltip="'Hapus'" @click="confirmDelete(data)" />
              </div>
            </template>
          </Column>

          <template #empty>
            <div class="empty-state">
              <i class="pi pi-id-card" />
              <span>{{ filters.search || filters.status ? 'Tidak ada driver sesuai filter' : 'Belum ada data driver' }}</span>
              <Button v-if="!filters.search && !filters.status && can('manage_armada')" label="Tambah Driver Pertama" size="small" outlined @click="openCreate" />
            </div>
          </template>
        </DataTable>
      </template>
    </Card>
    <!-- end desktop-only -->

    <!-- Dialog Create/Edit -->
    <Dialog v-model:visible="showDialog" :header="editId ? 'Edit Driver' : 'Tambah Driver'" modal :style="{ width: '480px' }" :closable="!saving">
      <div class="form-grid">
        <div class="form-row">
          <div class="form-group">
            <label>Nama Lengkap <span class="req">*</span></label>
            <InputText v-model="form.nama_driver" placeholder="Nama lengkap driver" class="full-width" />
          </div>
          <div class="form-group">
            <label>Nama Panggilan</label>
            <InputText v-model="form.nama_panggilan" placeholder="Contoh: Pak Budi" class="full-width" />
          </div>
        </div>
        <div class="form-group">
          <label>No. Telepon <span class="req">*</span></label>
          <InputText v-model="form.no_telepon" placeholder="08xxxxxxxxxx" class="full-width" />
        </div>
        <div class="form-group">
          <label>Jenis SIM</label>
          <Select v-model="form.jenis_sim" :options="simOptions" placeholder="Pilih jenis SIM..." class="full-width" showClear />
        </div>
        <div class="form-group">
          <label>Alamat <span class="req">*</span></label>
          <Textarea v-model="form.alamat" placeholder="Alamat lengkap driver" rows="3" class="full-width" />
        </div>
        <div class="form-group">
          <label>Status Kerja</label>
          <Select v-model="form.status_kerja" :options="statusOptions" optionLabel="label" optionValue="value" class="full-width" />
        </div>
      </div>

      <template #footer>
        <Button label="Batal" severity="secondary" text @click="showDialog = false" :disabled="saving" />
        <Button :label="editId ? 'Simpan Perubahan' : 'Tambah Driver'" icon="pi pi-check" :loading="saving" @click="doSave" />
      </template>
    </Dialog>

    <!-- Menu akun aplikasi driver -->
    <Menu ref="akunMenuRef" :model="akunMenuItems" popup />

    <!-- Kredensial hasil aktivasi / reset. Password hanya ada di sini, sekali. -->
    <Dialog v-model:visible="showKredensial" header="Kredensial Aplikasi Driver" modal :style="{ width: '420px' }">
      <div class="kredensial">
        <p class="kredensial-nama">{{ kredensial.nama }}</p>

        <div class="kredensial-baris">
          <label>Kode Driver</label>
          <div class="kredensial-nilai">
            <code>{{ kredensial.kode_driver }}</code>
            <Button icon="pi pi-copy" text size="small" v-tooltip="'Salin'" @click="salin(kredensial.kode_driver)" />
          </div>
        </div>

        <div class="kredensial-baris">
          <label>Password Sementara</label>
          <div class="kredensial-nilai">
            <code>{{ kredensial.password_sementara }}</code>
            <Button icon="pi pi-copy" text size="small" v-tooltip="'Salin'" @click="salin(kredensial.password_sementara)" />
          </div>
        </div>

        <Message severity="warn" :closable="false" class="kredensial-peringatan">
          Password ini hanya ditampilkan sekali. Catat atau kirimkan sekarang ke driver.
          Driver akan diminta menggantinya saat pertama kali masuk aplikasi.
        </Message>
      </div>

      <template #footer>
        <Button label="Salin Keduanya" icon="pi pi-copy" outlined @click="salinKeduanya" />
        <Button label="Sudah Dicatat" icon="pi pi-check" @click="showKredensial = false" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { driverService } from '@/services/driver.service'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from '@/composables/useToast'
import { usePermission } from '@/composables/usePermission'
import { debounce, salinTeks } from '@/utils/helpers'
import PageHeader from '@/components/common/PageHeader.vue'

// true saat dirender sebagai isi tab di MasterArmadaView — judul "Master
// Driver" disembunyikan supaya tidak dobel dengan label tab-nya sendiri.
defineProps({ dalamTab: { type: Boolean, default: false } })

const confirm = useConfirm()
const toast   = useToast()
const { can } = usePermission()

const loading    = ref(false)
const saving     = ref(false)
const items      = ref([])
const showDialog = ref(false)
const editId     = ref(null)

const akunMenuRef    = ref(null)
const akunDriver     = ref(null)
const showKredensial = ref(false)
const kredensial     = reactive({ nama: '', kode_driver: '', password_sementara: '' })

const statusOptions = [
  { label: 'Aktif',      value: 'Aktif' },
  { label: 'Tidak Aktif', value: 'Tidak Aktif' },
]
const simOptions = ['A', 'B1', 'B1 Umum', 'B2', 'B2 Umum']

const filters = reactive({ search: '', status: null })

const form = reactive({
  nama_driver: '',
  nama_panggilan: '',
  no_telepon:  '',
  jenis_sim:   null,
  alamat:      '',
  status_kerja: 'Aktif',
})

async function loadData() {
  loading.value = true
  try {
    const res = await driverService.getAll({ search: filters.search || undefined, status: filters.status || undefined })
    items.value = res.data ?? []
  } catch {
    toast.error('Gagal memuat data driver')
  } finally {
    loading.value = false
  }
}

const onSearch = debounce(() => loadData(), 350)

function resetFilters() {
  filters.search = ''
  filters.status = null
  loadData()
}

function openCreate() {
  editId.value = null
  Object.assign(form, { nama_driver: '', nama_panggilan: '', no_telepon: '', jenis_sim: null, alamat: '', status_kerja: 'Aktif' })
  showDialog.value = true
}

function openEdit(item) {
  editId.value = item.id
  Object.assign(form, {
    nama_driver:    item.nama_driver,
    nama_panggilan: item.nama_panggilan ?? '',
    no_telepon:     item.no_telepon,
    jenis_sim:      item.jenis_sim,
    alamat:         item.alamat,
    status_kerja:   item.status_kerja,
  })
  showDialog.value = true
}

async function doSave() {
  if (!form.nama_driver || !form.no_telepon || !form.alamat) {
    toast.error('Lengkapi field yang wajib diisi')
    return
  }
  saving.value = true
  try {
    if (editId.value) {
      await driverService.update(editId.value, { ...form })
      toast.success('Data driver berhasil diupdate')
    } else {
      await driverService.store({ ...form })
      toast.success('Driver baru berhasil ditambahkan')
    }
    showDialog.value = false
    loadData()
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'Gagal menyimpan data driver')
  } finally {
    saving.value = false
  }
}

// ── Akun aplikasi driver ────────────────────────────────────────

const akunMenuItems = computed(() => {
  const drv = akunDriver.value
  if (!drv) return []

  if (!drv.mobile_aktif) {
    return [{
      label: 'Aktifkan Akun Aplikasi',
      icon: 'pi pi-mobile',
      command: () => aktifkanAkun(drv),
    }]
  }

  return [
    { label: 'Reset Password', icon: 'pi pi-refresh', command: () => confirmResetPassword(drv) },
    { separator: true },
    { label: 'Nonaktifkan Akun', icon: 'pi pi-ban', command: () => confirmNonaktifkan(drv) },
  ]
})

function openAkunMenu(event, item) {
  akunDriver.value = item
  akunMenuRef.value?.toggle(event)
}

function tampilkanKredensial(nama, data) {
  Object.assign(kredensial, {
    nama,
    kode_driver: data.kode_driver,
    password_sementara: data.password_sementara,
  })
  showKredensial.value = true
}

async function aktifkanAkun(item) {
  try {
    const res = await driverService.aktifkanAkun(item.id)
    tampilkanKredensial(item.nama_driver, res.data)
    loadData()
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'Gagal mengaktifkan akun driver')
  }
}

function confirmResetPassword(item) {
  confirm.require({
    message: `Reset password aplikasi untuk "${item.nama_driver}"? Driver akan langsung keluar dari aplikasi dan harus masuk lagi dengan password baru.`,
    header: 'Reset Password Driver',
    icon: 'pi pi-refresh',
    acceptLabel: 'Ya, Reset',
    rejectLabel: 'Batal',
    accept: async () => {
      try {
        const res = await driverService.resetPassword(item.id)
        tampilkanKredensial(item.nama_driver, res.data)
        loadData()
      } catch (err) {
        toast.error(err?.response?.data?.message ?? 'Gagal mereset password driver')
      }
    },
  })
}

function confirmNonaktifkan(item) {
  confirm.require({
    message: `Nonaktifkan akun aplikasi "${item.nama_driver}"? Driver langsung keluar dan tidak bisa masuk lagi sampai diaktifkan kembali. Data pengirimannya tetap tersimpan.`,
    header: 'Nonaktifkan Akun Driver',
    icon: 'pi pi-exclamation-triangle',
    acceptSeverity: 'danger',
    acceptLabel: 'Ya, Nonaktifkan',
    rejectLabel: 'Batal',
    accept: async () => {
      try {
        await driverService.nonaktifkanAkun(item.id)
        toast.success('Akun aplikasi driver dinonaktifkan')
        loadData()
      } catch (err) {
        toast.error(err?.response?.data?.message ?? 'Gagal menonaktifkan akun driver')
      }
    },
  })
}

async function salin(teks) {
  if (await salinTeks(teks)) {
    toast.success('Disalin')
  } else {
    toast.error('Gagal menyalin, salin manual saja')
  }
}

function salinKeduanya() {
  salin(`Kode Driver: ${kredensial.kode_driver}\nPassword: ${kredensial.password_sementara}`)
}

function confirmDelete(item) {
  confirm.require({
    message: `Hapus driver "${item.nama_driver}"? Driver yang sudah punya riwayat pengiriman tidak bisa dihapus; ubah status kerjanya menjadi Tidak Aktif.`,
    header: 'Hapus Driver',
    icon: 'pi pi-exclamation-triangle',
    acceptSeverity: 'danger',
    acceptLabel: 'Ya, Hapus',
    rejectLabel: 'Batal',
    accept: async () => {
      try {
        await driverService.destroy(item.id)
        toast.success('Driver berhasil dihapus')
        loadData()
      } catch (err) {
        toast.error(err?.response?.data?.message ?? 'Gagal menghapus driver')
      }
    },
  })
}

onMounted(loadData)
</script>

<style scoped>
.driver-view { display: flex; flex-direction: column; gap: 1.25rem; animation: fadeUp var(--dur-base) var(--ease) both; }
.filter-card :deep(.p-card-body) { padding: 0.75rem 1rem; }
.filter-bar { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
.search-field { flex: 1; min-width: 210px; }
.stats-row { margin-top: 0.5rem; display: flex; gap: 1.2rem; font-size: 0.8rem; color: var(--p-text-muted-color); }
.stat { display: flex; align-items: center; gap: 0.3rem; }
.stat b { color: var(--p-text-color); }
.stat.aktif { color: var(--c-success); }
.stat.akun { color: var(--c-brand-600); }
.akun-cell { display: flex; flex-direction: column; align-items: flex-start; gap: 0.3rem; }
.kode-driver { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.82rem; font-weight: 600; letter-spacing: 0.02em; }
.kredensial { display: flex; flex-direction: column; gap: 0.9rem; }
.kredensial-nama { margin: 0; font-weight: 600; }
.kredensial-baris { display: flex; flex-direction: column; gap: 0.3rem; }
.kredensial-baris label { font-size: 0.78rem; font-weight: 600; color: var(--p-text-muted-color); }
.kredensial-nilai { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; background: var(--p-content-hover-background); border: 1px solid var(--p-card-border-color); border-radius: 8px; padding: 0.4rem 0.5rem 0.4rem 0.75rem; }
.kredensial-nilai code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 1rem; font-weight: 700; letter-spacing: 0.06em; user-select: all; }
.kredensial-peringatan { font-size: 0.8rem; }
.driver-nama-cell { display: flex; flex-direction: column; gap: 2px; }
.nama-driver { font-weight: 600; font-size: 0.875rem; }
.nama-panggilan { font-size: 0.75rem; color: var(--c-brand-600); font-style: italic; }
.telp { font-size: 0.85rem; color: var(--p-text-muted-color); }
.alamat { font-size: 0.8rem; color: var(--p-text-muted-color); }
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
.driver-cards { display: flex; flex-direction: column; gap: 0.75rem; }
.driver-card { background: var(--p-card-background); border: 1px solid var(--p-card-border-color); border-radius: 10px; padding: 0.9rem 1rem; display: flex; flex-direction: column; gap: 0.6rem; transition: box-shadow 0.18s ease, transform 0.18s ease; }
.driver-card:active { transform: scale(0.985); }

/* Mobile card stagger animation */
.card-list-enter-active { transition: opacity 0.3s ease, transform 0.3s ease; transition-delay: calc(var(--i, 0) * 0.045s); }
.card-list-enter-from   { opacity: 0; transform: translateY(14px); }
.card-list-leave-active { transition: opacity 0.18s ease; }
.card-list-leave-to     { opacity: 0; }
.card-list-move         { transition: transform 0.3s ease; }
.dc-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem; }
.dc-info { display: flex; flex-direction: column; gap: 2px; }
.dc-nama { font-weight: 700; font-size: 0.95rem; }
.dc-panggilan { font-size: 0.75rem; color: var(--c-brand-600); font-style: italic; }
.dc-details { display: flex; flex-direction: column; gap: 0.3rem; }
.dc-item { font-size: 0.82rem; color: var(--p-text-muted-color); display: flex; align-items: flex-start; gap: 0.4rem; }
.dc-item i { margin-top: 1px; flex-shrink: 0; font-size: 0.8rem; }
.dc-alamat { align-items: flex-start; }
.dc-actions { display: flex; gap: 0.4rem; border-top: 1px solid var(--p-card-border-color); padding-top: 0.5rem; }
.mobile-loading { text-align: center; padding: 2rem; color: var(--p-text-muted-color); display: flex; align-items: center; justify-content: center; gap: 0.5rem; }

@media (max-width: 768px) {
  .mobile-only { display: block; }
  .desktop-only { display: none; }
  .form-row { grid-template-columns: 1fr; }
}

/* ── Dark mode overrides ─────────────────────────────────────── */
:global(.app-dark) .stat.aktif { color: var(--c-success); }
</style>
