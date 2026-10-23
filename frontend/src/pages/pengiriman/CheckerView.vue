<template>
  <div class="checker-view">
    <PageHeader title="Master Checker" subtitle="Kelola data pengawas muat (ceker gudang)." :hide-title="dalamTab">
      <template #actions>
        <Button v-if="can('manage_armada')" icon="pi pi-plus" label="Tambah Checker" @click="openCreate" />
      </template>
    </PageHeader>

    <!-- Filter -->
    <Card class="filter-card">
      <template #content>
        <div class="filter-bar">
          <IconField class="search-field">
            <InputIcon class="pi pi-search" />
            <InputText v-model="filters.search" placeholder="Cari nama checker..." @input="onSearch" />
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
          <span class="stat"><b>{{ items.length }}</b> checker</span>
          <span class="stat aktif"><i class="pi pi-check-circle" /> {{ items.filter(i => i.status === 'Aktif').length }} Aktif</span>
        </div>
      </template>
    </Card>

    <!-- Desktop table -->
    <Card class="desktop-only">
      <template #content>
        <DataTable :value="items" :loading="loading" stripedRows dataKey="id">
          <Column header="No." style="width: 56px; text-align: center">
            <template #body="{ index }">{{ index + 1 }}</template>
          </Column>
          <Column header="Nama Checker" style="min-width: 200px">
            <template #body="{ data }">
              <span class="checker-nama">{{ data.nama }}</span>
            </template>
          </Column>
          <Column header="Status" style="min-width: 130px">
            <template #body="{ data }">
              <Tag :value="data.status" :severity="data.status === 'Aktif' ? 'success' : 'secondary'" />
            </template>
          </Column>
          <Column v-if="can('manage_armada')" header="Aksi" style="min-width: 100px; text-align: center">
            <template #body="{ data }">
              <div class="action-btns">
                <Button icon="pi pi-pencil" size="small" text v-tooltip="'Edit'" @click="openEdit(data)" />
                <Button icon="pi pi-trash" size="small" text severity="danger" v-tooltip="'Hapus'" @click="confirmDelete(data)" />
              </div>
            </template>
          </Column>
          <template #empty>
            <div class="empty-state">
              <i class="pi pi-users" />
              <span>Belum ada data checker</span>
              <Button v-if="can('manage_armada')" label="Tambah Checker Pertama" size="small" outlined @click="openCreate" />
            </div>
          </template>
        </DataTable>
      </template>
    </Card>

    <!-- Mobile cards -->
    <div class="mobile-only">
      <div v-if="loading" class="mobile-loading"><i class="pi pi-spin pi-spinner" /> Memuat...</div>
      <div v-else-if="!items.length" class="empty-state">
        <i class="pi pi-users" /><span>Belum ada data checker</span>
        <Button v-if="can('manage_armada')" label="Tambah Checker Pertama" size="small" outlined @click="openCreate" />
      </div>
      <TransitionGroup name="card-list" tag="div" class="checker-cards">
        <div v-for="(ck, i) in items" :key="ck.id" :style="{'--i': i}" class="checker-card">
          <div class="cc-top">
            <span class="cc-nama">{{ ck.nama }}</span>
            <Tag :value="ck.status" :severity="ck.status === 'Aktif' ? 'success' : 'secondary'" />
          </div>
          <div v-if="can('manage_armada')" class="cc-actions">
            <Button icon="pi pi-pencil" label="Edit" size="small" text @click="openEdit(ck)" />
            <Button icon="pi pi-trash" label="Hapus" size="small" text severity="danger" @click="confirmDelete(ck)" />
          </div>
        </div>
      </TransitionGroup>
    </div>

    <!-- Dialog -->
    <Dialog v-model:visible="showDialog" :header="editId ? 'Edit Checker' : 'Tambah Checker'" modal :style="{ width: '380px' }" :closable="!saving">
      <div class="form-grid">
        <div class="form-group">
          <label>Nama Checker <span class="req">*</span></label>
          <InputText v-model="form.nama" placeholder="Nama checker..." class="full-width" />
        </div>
        <div class="form-group">
          <label>Status</label>
          <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" class="full-width" />
        </div>
      </div>
      <template #footer>
        <Button label="Batal" severity="secondary" text @click="showDialog = false" :disabled="saving" />
        <Button :label="editId ? 'Simpan' : 'Tambah'" icon="pi pi-check" :loading="saving" @click="doSave" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { checkerService } from '@/services/checker.service'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from '@/composables/useToast'
import { usePermission } from '@/composables/usePermission'
import { debounce } from '@/utils/helpers'
import PageHeader from '@/components/common/PageHeader.vue'

// true saat dirender sebagai isi tab di MasterArmadaView — judul "Master
// Checker" disembunyikan supaya tidak dobel dengan label tab-nya sendiri.
defineProps({ dalamTab: { type: Boolean, default: false } })

const confirm = useConfirm()
const toast   = useToast()
const { can } = usePermission()

const loading    = ref(false)
const saving     = ref(false)
const items      = ref([])
const showDialog = ref(false)
const editId     = ref(null)

const statusOptions = [
  { label: 'Aktif',       value: 'Aktif' },
  { label: 'Tidak Aktif', value: 'Tidak Aktif' },
]

const filters = reactive({ search: '', status: null })
const form    = reactive({ nama: '', status: 'Aktif' })

async function loadData() {
  loading.value = true
  try {
    const res = await checkerService.getAll({ search: filters.search || undefined, status: filters.status || undefined })
    items.value = res.data ?? []
  } catch {
    toast.error('Gagal memuat data checker')
  } finally {
    loading.value = false
  }
}

const onSearch = debounce(() => loadData(), 350)

function resetFilters() { filters.search = ''; filters.status = null; loadData() }

function openCreate() {
  editId.value = null
  Object.assign(form, { nama: '', status: 'Aktif' })
  showDialog.value = true
}

function openEdit(item) {
  editId.value = item.id
  Object.assign(form, { nama: item.nama, status: item.status })
  showDialog.value = true
}

async function doSave() {
  if (!form.nama.trim()) { toast.error('Nama checker wajib diisi'); return }
  saving.value = true
  try {
    if (editId.value) {
      await checkerService.update(editId.value, { ...form })
      toast.success('Data checker berhasil diupdate')
    } else {
      await checkerService.store({ ...form })
      toast.success('Checker baru berhasil ditambahkan')
    }
    showDialog.value = false
    loadData()
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'Gagal menyimpan data checker')
  } finally {
    saving.value = false
  }
}

function confirmDelete(item) {
  confirm.require({
    message: `Hapus checker "${item.nama}"?`,
    header: 'Hapus Checker',
    icon: 'pi pi-exclamation-triangle',
    acceptSeverity: 'danger',
    acceptLabel: 'Ya, Hapus',
    rejectLabel: 'Batal',
    accept: async () => {
      try {
        await checkerService.destroy(item.id)
        toast.success('Checker berhasil dihapus')
        loadData()
      } catch (err) {
        toast.error(err?.response?.data?.message ?? 'Gagal menghapus checker')
      }
    },
  })
}

onMounted(loadData)
</script>

<style scoped>
.checker-view { display: flex; flex-direction: column; gap: 1.25rem; animation: fadeUp var(--dur-base) var(--ease) both; }
.filter-card :deep(.p-card-body) { padding: 0.75rem 1rem; }
.filter-bar { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
.search-field { flex: 1; min-width: 200px; }
.stats-row { margin-top: 0.5rem; display: flex; gap: 1.2rem; font-size: 0.8rem; color: var(--p-text-muted-color); }
.stat { display: flex; align-items: center; gap: 0.3rem; }
.stat b { color: var(--p-text-color); }
.stat.aktif { color: var(--c-success); }
.checker-nama { font-weight: 600; font-size: 0.9rem; }
.action-btns { display: flex; justify-content: center; gap: 0.25rem; }
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 0.75rem; padding: 3rem; color: var(--p-text-muted-color); }
.empty-state i { font-size: 2.5rem; }
.form-grid { display: flex; flex-direction: column; gap: 1rem; }
.form-group { display: flex; flex-direction: column; gap: 0.35rem; }
.form-group label { font-size: 0.85rem; font-weight: 600; }
.req { color: var(--c-brand-500); }
.full-width { width: 100%; }

.mobile-only { display: none; }
.desktop-only { display: block; }
.checker-cards { display: flex; flex-direction: column; gap: 0.6rem; }
.checker-card { background: var(--p-card-background); border: 1px solid var(--p-card-border-color); border-radius: 10px; padding: 0.85rem 1rem; display: flex; flex-direction: column; gap: 0.5rem; transition: transform 0.18s ease; }
.checker-card:active { transform: scale(0.985); }

.card-list-enter-active { transition: opacity 0.3s ease, transform 0.3s ease; transition-delay: calc(var(--i, 0) * 0.045s); }
.card-list-enter-from   { opacity: 0; transform: translateY(14px); }
.card-list-leave-active { transition: opacity 0.18s ease; }
.card-list-leave-to     { opacity: 0; }
.card-list-move         { transition: transform 0.3s ease; }
.cc-top { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
.cc-nama { font-weight: 700; font-size: 0.95rem; }
.cc-actions { display: flex; gap: 0.4rem; border-top: 1px solid var(--p-card-border-color); padding-top: 0.45rem; }
.mobile-loading { text-align: center; padding: 2rem; color: var(--p-text-muted-color); display: flex; align-items: center; justify-content: center; gap: 0.5rem; }

@media (max-width: 768px) {
  .mobile-only { display: block; }
  .desktop-only { display: none; }
}

/* ── Dark mode overrides ─────────────────────────────────────── */
:global(.app-dark) .stat.aktif { color: var(--c-success); }
</style>
