<template>
  <div class="pengawasan-view">
    <PageHeader title="Penugasan Checker" subtitle="Tugaskan checker ke truk non-subsidi per tanggal muat.">
      <template #actions>
        <Button v-if="can('edit_pengiriman') && muats.length && isDirty" icon="pi pi-save" label="Simpan" :loading="saving" @click="doSave" />
      </template>
    </PageHeader>

    <!-- Date picker -->
    <Card class="date-card">
      <template #content>
        <div class="date-bar">
          <div class="date-group">
            <label class="date-label">Tanggal Muat</label>
            <DatePicker v-model="selectedDate" dateFormat="dd/mm/yy" showIcon @date-select="onDateChange" class="date-picker" />
          </div>
          <div class="date-stats" v-if="!loading && muats.length">
            <span class="ds-item"><b>{{ muats.length }}</b> truk</span>
            <span class="ds-item assigned"><i class="pi pi-check-circle" /> {{ assignedCount }} ter-assign</span>
            <span class="ds-item unassigned" v-if="muats.length - assignedCount > 0">
              <i class="pi pi-exclamation-circle" /> {{ muats.length - assignedCount }} belum
            </span>
          </div>
        </div>
      </template>
    </Card>

    <!-- Desktop table -->
    <Card class="desktop-only">
      <template #content>
        <DataTable :value="muats" :loading="loading" dataKey="id" stripedRows>
          <Column header="No." style="width: 56px; text-align: center">
            <template #body="{ index }">{{ index + 1 }}</template>
          </Column>
          <Column header="Rit" style="width: 60px; text-align: center">
            <template #body="{ data }">
              <span class="rit-badge">{{ data.nomor_rit ?? '-' }}</span>
            </template>
          </Column>
          <Column header="Driver" style="min-width: 160px">
            <template #body="{ data }">
              <span class="driver-nama">{{ data.driver ?? '-' }}</span>
            </template>
          </Column>
          <Column header="Kendaraan" style="min-width: 120px">
            <template #body="{ data }">
              <span class="kend-polisi">{{ data.kendaraan ?? '-' }}</span>
            </template>
          </Column>
          <Column header="Muatan (Kg)" style="min-width: 120px">
            <template #body="{ data }">
              <span class="muatan">{{ fmtKg(data.total_muatan_ton) }} Kg</span>
            </template>
          </Column>
          <Column header="Checker / Pengawas" style="min-width: 200px">
            <template #body="{ data }">
              <Select
                v-if="can('edit_pengiriman')"
                v-model="assignments[data.id]"
                :options="checkers"
                optionLabel="nama"
                optionValue="id"
                placeholder="Pilih Checker"
                showClear
                class="checker-select"
              />
              <span v-else>{{ data.checker_nama ?? '-' }}</span>
            </template>
          </Column>
          <template #empty>
            <div class="empty-state">
              <i class="pi pi-truck" />
              <span>Tidak ada daftar muat pada tanggal ini</span>
            </div>
          </template>
        </DataTable>
        <div v-if="can('edit_pengiriman') && muats.length" class="table-footer">
          <Button v-if="isDirty" icon="pi pi-save" label="Simpan Perubahan" :loading="saving" @click="doSave" />
        </div>
      </template>
    </Card>

    <!-- Mobile card list -->
    <div class="mobile-only">
      <div v-if="loading" class="mobile-loading"><i class="pi pi-spin pi-spinner" /> Memuat...</div>
      <div v-else-if="!muats.length" class="empty-state">
        <i class="pi pi-truck" /><span>Tidak ada daftar muat pada tanggal ini</span>
      </div>
      <TransitionGroup name="card-list" tag="div" class="muat-cards">
        <div v-for="(m, i) in muats" :key="m.id" :style="{'--i': i}" class="muat-card">
          <div class="mc-top">
            <div class="mc-info">
              <span v-if="m.nomor_rit" class="mc-rit">Rit {{ m.nomor_rit }}</span>
              <span class="mc-driver">{{ m.driver ?? '-' }}</span>
              <span class="mc-kend">{{ m.kendaraan }} · {{ fmtKg(m.total_muatan_ton) }} Kg</span>
            </div>
          </div>
          <div class="mc-checker">
            <label class="mc-label">Checker / Pengawas</label>
            <Select
              v-if="can('edit_pengiriman')"
              v-model="assignments[m.id]"
              :options="checkers"
              optionLabel="nama"
              optionValue="id"
              placeholder="Pilih Checker"
              showClear
              class="full-width"
            />
            <span v-else class="mc-checker-nama">{{ m.checker_nama ?? '-' }}</span>
          </div>
        </div>
        <Button v-if="can('edit_pengiriman') && isDirty" icon="pi pi-save" label="Simpan Perubahan" :loading="saving" @click="doSave" class="save-mobile-btn" />
      </TransitionGroup>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useStaleRefresh } from '@/composables/useStaleRefresh'
import { pengawasanMuatService } from '@/services/checker.service'
import { useToast } from '@/composables/useToast'
import { useConfirm } from 'primevue/useconfirm'
import { usePermission } from '@/composables/usePermission'
import PageHeader from '@/components/common/PageHeader.vue'

const toast   = useToast()
const confirm = useConfirm()
const { can } = usePermission()

const loading      = ref(false)
const saving       = ref(false)
const selectedDate = ref(new Date())
const loadedDate   = ref(null)   // tanggal yang sedang tampil (untuk rollback DatePicker)
const muats        = ref([])
const checkers     = ref([])
const assignments  = reactive({})
const original     = reactive({})   // snapshot checker_id awal, untuk deteksi perubahan

const assignedCount = computed(() => muats.value.filter(m => assignments[m.id]).length)

// Dirty = ada baris yang checker_id-nya beda dari snapshot awal (termasuk saat di-clear)
const isDirty = computed(() =>
  muats.value.some(m => (assignments[m.id] ?? null) !== (original[m.id] ?? null))
)

function fmtKg(val) {
  return Math.round((parseFloat(val) || 0) * 1000).toLocaleString('id-ID')
}

function toDateStr(d) {
  const dt = d ?? new Date()
  const y  = dt.getFullYear()
  const m  = String(dt.getMonth() + 1).padStart(2, '0')
  const dy = String(dt.getDate()).padStart(2, '0')
  return `${y}-${m}-${dy}`
}

async function loadData(date) {
  loading.value = true
  try {
    const res = await pengawasanMuatService.getByTanggal(toDateStr(date ?? selectedDate.value))
    muats.value    = res.data.muats    ?? []
    checkers.value = res.data.checkers ?? []
    // Sinkronkan DatePicker dengan tanggal aktual dari server
    if (!date && res.data.tanggal) {
      const [y, m, d2] = res.data.tanggal.split('-').map(Number)
      selectedDate.value = new Date(y, m - 1, d2)
    }
    loadedDate.value = new Date(selectedDate.value)

    // Reset assignments + snapshot awal dari data server
    Object.keys(assignments).forEach(k => delete assignments[k])
    Object.keys(original).forEach(k => delete original[k])
    muats.value.forEach(m => {
      assignments[m.id] = m.checker_id ?? null
      original[m.id]    = m.checker_id ?? null
    })
  } catch {
    toast.error('Gagal memuat data pengawasan')
  } finally {
    loading.value = false
  }
}

function onDateChange(date) {
  if (isDirty.value) {
    confirm.require({
      message: 'Ada perubahan checker yang belum disimpan. Pindah tanggal dan buang perubahan?',
      header: 'Perubahan Belum Disimpan',
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Buang & Pindah',
      rejectLabel: 'Batal',
      acceptSeverity: 'danger',
      accept: () => loadData(date),
      reject: () => {
        // Kembalikan DatePicker ke tanggal yang sedang tampil
        if (loadedDate.value) selectedDate.value = new Date(loadedDate.value)
      },
    })
    return
  }
  loadData(date)
}

async function doSave() {
  // Hanya kirim baris yang benar-benar berubah
  const items = muats.value
    .filter(m => (assignments[m.id] ?? null) !== (original[m.id] ?? null))
    .map(m => ({ daftar_muat_id: m.id, checker_id: assignments[m.id] ?? null }))

  if (items.length === 0) {
    toast.info('Tidak ada perubahan untuk disimpan')
    return
  }

  saving.value = true
  try {
    await pengawasanMuatService.assign(items)
    toast.success('Checker berhasil disimpan')
    loadData(selectedDate.value)
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'Gagal menyimpan checker')
  } finally {
    saving.value = false
  }
}

useStaleRefresh(() => loadData())
</script>

<style scoped>
.pengawasan-view { display: flex; flex-direction: column; gap: 1.25rem; animation: fadeUp var(--dur-base) var(--ease) both; }

.date-card :deep(.p-card-body) { padding: 0.75rem 1rem; }
.date-bar { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
.date-group { display: flex; align-items: center; gap: 0.6rem; }
.date-label { font-size: 0.85rem; font-weight: 600; white-space: nowrap; }
.date-picker { width: 175px; }
.date-stats { display: flex; gap: 1.2rem; font-size: 0.82rem; }
.ds-item { display: flex; align-items: center; gap: 0.3rem; color: var(--p-text-muted-color); }
.ds-item b { color: var(--p-text-color); font-size: 0.9rem; }
.ds-item.assigned { color: var(--c-success); }
.ds-item.unassigned { color: var(--c-warning); }

.rit-badge { font-weight: 700; font-size: 0.9rem; color: var(--c-brand-600); }
.driver-nama { font-weight: 600; font-size: 0.875rem; }
.kend-polisi { font-weight: 700; font-size: 0.85rem; letter-spacing: 0.03em; }
.muatan { font-size: 0.85rem; color: var(--p-text-muted-color); }
.checker-select { width: 100%; }
.table-footer { padding: 0.75rem 0 0; display: flex; justify-content: flex-end; }
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 0.75rem; padding: 3rem; color: var(--p-text-muted-color); }
.empty-state i { font-size: 2.5rem; }

/* Mobile */
.mobile-only { display: none; }
.desktop-only { display: block; }
.muat-cards { display: flex; flex-direction: column; gap: 0.75rem; }
.muat-card { background: var(--p-card-background); border: 1px solid var(--p-card-border-color); border-radius: 10px; padding: 0.9rem 1rem; display: flex; flex-direction: column; gap: 0.7rem; transition: box-shadow 0.18s ease; }

.card-list-enter-active { transition: opacity 0.3s ease, transform 0.3s ease; transition-delay: calc(var(--i, 0) * 0.045s); }
.card-list-enter-from   { opacity: 0; transform: translateY(14px); }
.card-list-leave-active { transition: opacity 0.18s ease; }
.card-list-leave-to     { opacity: 0; }
.card-list-move         { transition: transform 0.3s ease; }
.mc-top { display: flex; align-items: flex-start; justify-content: space-between; }
.mc-info { display: flex; flex-direction: column; gap: 2px; }
.mc-rit { font-size: 0.72rem; font-weight: 600; color: var(--c-brand-600); }
.mc-driver { font-weight: 700; font-size: 0.95rem; }
.mc-kend { font-size: 0.78rem; color: var(--p-text-muted-color); }
.mc-checker { display: flex; flex-direction: column; gap: 0.35rem; }
.mc-label { font-size: 0.8rem; font-weight: 600; }
.mc-checker-nama { font-size: 0.875rem; }
.full-width { width: 100%; }
.mobile-loading { text-align: center; padding: 2rem; color: var(--p-text-muted-color); display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
.save-mobile-btn { width: 100%; margin-top: 0.5rem; }

@media (max-width: 768px) {
  .mobile-only { display: block; }
  .desktop-only { display: none; }
  .date-bar { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
  .date-picker { width: 100%; }
  .date-stats { flex-wrap: wrap; }
}

/* ── Dark mode overrides ─────────────────────────────────────── */
:global(.app-dark) .ds-item.assigned   { color: var(--c-success); }
:global(.app-dark) .ds-item.unassigned { color: var(--c-warning); }
</style>
