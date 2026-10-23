<template>
  <div class="detail-view">
    <div class="detail-header">
      <Button icon="pi pi-arrow-left" text plain @click="$router.back()" label="Kembali" />
      <div class="header-actions" v-if="item">
        <Button
          v-if="can('edit_presensi')"
          icon="pi pi-pencil"
          label="Edit"
          severity="secondary"
          outlined
          @click="openEdit"
        />
        <Button
          v-if="can('delete_presensi')"
          icon="pi pi-trash"
          label="Hapus"
          severity="danger"
          outlined
          @click="confirmDelete"
        />
      </div>
    </div>

    <div v-if="loading" class="skeleton-wrap">
      <Skeleton height="120px" class="mb-3" />
      <Skeleton height="160px" class="mb-3" />
      <div class="photos-grid"><Skeleton height="380px" /><Skeleton height="380px" /></div>
    </div>

    <template v-else-if="item">
      <!-- Sales info -->
      <Card class="info-card">
        <template #content>
          <div class="section-title"><i class="pi pi-user" /> Informasi Sales</div>
          <div class="info-grid-3">
            <div class="info-item">
              <span class="info-label">Nama Sales</span>
              <span class="info-value primary">{{ item.sales_nama }}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Area</span>
              <span class="info-value">{{ item.sales_area || '-' }}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Tanggal</span>
              <span class="info-value">{{ formatDate(item.tanggal) }}</span>
            </div>
          </div>
        </template>
      </Card>

      <!-- Attendance data -->
      <Card class="info-card">
        <template #content>
          <div class="section-title"><i class="pi pi-calendar-clock" /> Data Kehadiran</div>

          <div class="attend-grid">
            <div class="info-item">
              <span class="info-label">Status</span>
              <Tag
                :value="statusLabel(item.status)"
                :severity="item.status === 'tepat_waktu' ? 'success' : item.status === 'terlambat' ? 'danger' : 'secondary'"
                class="status-tag"
              />
            </div>
            <div class="info-item">
              <span class="info-label"><i class="pi pi-sign-in" /> Jam Masuk</span>
              <span class="info-value">{{ item.jam_masuk || '-' }}</span>
            </div>
            <div class="info-item">
              <span class="info-label"><i class="pi pi-sign-out" /> Jam Pulang</span>
              <span class="info-value">{{ item.jam_pulang || '-' }}</span>
              <span v-if="!item.jam_pulang" class="info-sub muted">Belum absen pulang</span>
            </div>
          </div>

          <div v-if="item.keterangan" class="keterangan-block">
            <span class="info-label"><i class="pi pi-comment" /> Keterangan</span>
            <span class="info-value keterangan-text">{{ item.keterangan }}</span>
          </div>

          <Divider />

          <div class="location-grid">
            <div class="field-block">
              <span class="field-label"><i class="pi pi-map-pin" /> Lokasi Masuk</span>
              <a v-if="item.location_masuk" :href="mapsUrlMasuk" target="_blank" class="coord-link">
                {{ item.location_masuk }}
              </a>
              <span v-else class="coord-empty">Lokasi tidak tersedia</span>
              <span v-if="item.alamat_masuk" class="coord-address">{{ item.alamat_masuk }}</span>
            </div>
            <div class="field-block">
              <span class="field-label"><i class="pi pi-map-pin" /> Lokasi Pulang</span>
              <a v-if="item.location_pulang" :href="mapsUrlPulang" target="_blank" class="coord-link">
                {{ item.location_pulang }}
              </a>
              <span v-else class="coord-empty">Belum absen pulang</span>
              <span v-if="item.alamat_pulang" class="coord-address">{{ item.alamat_pulang }}</span>
            </div>
          </div>
        </template>
      </Card>

      <!-- Photos -->
      <Card v-if="item.foto_masuk_url || item.foto_pulang_url" class="info-card">
        <template #content>
          <div class="section-title"><i class="pi pi-camera" /> Bukti Foto</div>
          <div class="photos-grid">

            <div class="photo-section">
              <div class="photo-label"><i class="pi pi-sign-in" /> Foto Masuk</div>
              <div class="photo-wrap" @click="openPhoto(item.foto_masuk_url)">
                <img v-if="item.foto_masuk_url" :src="item.foto_masuk_url" class="foto-img" alt="Foto Masuk" />
                <div v-else class="foto-empty">
                  <i class="pi pi-image" /><span>Foto tidak tersedia</span>
                </div>
                <div v-if="item.foto_masuk_url" class="foto-overlay"><i class="pi pi-search-plus" /></div>
              </div>
              <div class="photo-meta">{{ item.jam_masuk || '-' }}</div>
            </div>

            <div class="photo-section">
              <div class="photo-label"><i class="pi pi-sign-out" /> Foto Pulang</div>
              <div class="photo-wrap" @click="openPhoto(item.foto_pulang_url)">
                <img v-if="item.foto_pulang_url" :src="item.foto_pulang_url" class="foto-img" alt="Foto Pulang" />
                <div v-else class="foto-empty">
                  <i class="pi pi-image" /><span>Belum absen pulang</span>
                </div>
                <div v-if="item.foto_pulang_url" class="foto-overlay"><i class="pi pi-search-plus" /></div>
              </div>
              <div class="photo-meta">{{ item.jam_pulang || '-' }}</div>
            </div>

          </div>
        </template>
      </Card>
    </template>

    <div v-else-if="!loading" class="empty-state">
      <i class="pi pi-exclamation-circle" />
      <span>Data presensi tidak ditemukan</span>
    </div>

    <!-- ── Edit Dialog ─────────────────────────────────────── -->
    <Dialog
      v-model:visible="editVisible"
      header="Edit Data Presensi"
      :style="{ width: '480px' }"
      modal
      :draggable="false"
      :closable="!saving"
    >
      <div v-if="editForm" class="edit-form">

        <!-- Sales info (readonly) -->
        <div class="edit-sales-info">
          <i class="pi pi-user" />
          <div>
            <div class="edit-sales-name">{{ item?.sales_nama }}</div>
            <div class="edit-sales-date">{{ formatDate(editForm.tanggal) }}</div>
          </div>
        </div>

        <!-- Tanggal -->
        <div class="field">
          <label class="field-label">Tanggal <span class="required">*</span></label>
          <DatePicker
            v-model="editFormDate"
            date-format="yy-mm-dd"
            show-icon
            fluid
            :max-date="new Date()"
          />
        </div>

        <!-- Jam Masuk & Pulang -->
        <div class="field-row">
          <div class="field">
            <label class="field-label">Jam Masuk</label>
            <InputMask
              v-model="editForm.jam_masuk"
              mask="99:99"
              placeholder="HH:MM"
              fluid
            />
          </div>
          <div class="field">
            <label class="field-label">Jam Pulang</label>
            <InputMask
              v-model="editForm.jam_pulang"
              mask="99:99"
              placeholder="HH:MM"
              fluid
            />
          </div>
        </div>

        <!-- Status -->
        <div class="field">
          <label class="field-label">Status Kehadiran <span class="required">*</span></label>
          <Select
            v-model="editForm.status"
            :options="statusOptions"
            option-label="label"
            option-value="value"
            fluid
          />
        </div>

        <!-- Keterangan -->
        <div class="field">
          <label class="field-label">Keterangan</label>
          <Textarea
            v-model="editForm.keterangan"
            rows="3"
            placeholder="Catatan tambahan (opsional)..."
            fluid
            auto-resize
            style="resize: none"
          />
        </div>

        <!-- Hint jam masuk -->
        <div class="edit-hint">
          <i class="pi pi-info-circle" />
          <span>Status akan dihitung otomatis jika Jam Masuk diubah (masuk &gt; 08:00 = Terlambat)</span>
        </div>

      </div>

      <template #footer>
        <Button label="Batal" text plain @click="editVisible = false" :disabled="saving" />
        <Button label="Simpan Perubahan" icon="pi pi-check" :loading="saving" @click="doSave" />
      </template>
    </Dialog>

    <FotoViewer v-model:visible="photoVisible" :src="photoUrl" title="Bukti Foto" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import { presensiService } from '@/services/presensi.service'
import { useToast } from '@/composables/useToast'
import { usePermission } from '@/composables/usePermission'
import { formatDate } from '@/utils/helpers'
import FotoViewer from '@/components/common/FotoViewer.vue'

const route   = useRoute()
const router  = useRouter()
const toast   = useToast()
const confirm = useConfirm()
const { can } = usePermission()

// ── State ────────────────────────────────────────────────────
const loading      = ref(false)
const item         = ref(null)
const photoVisible = ref(false)
const photoUrl     = ref('')

// ── Edit ─────────────────────────────────────────────────────
const editVisible = ref(false)
const saving      = ref(false)
const editForm    = ref(null)

// BUG FIX #4 (UTC midnight): new Date("YYYY-MM-DD") diparsing sebagai UTC midnight.
// Di WIB (UTC+7) ini berarti "2026-05-28" → 27 Mei 17:00 lokal → DatePicker tampil H-1.
// Solusi: parse manual ke local midnight agar tanggal selalu tepat.
const editFormDate = computed({
  get: () => {
    const t = editForm.value?.tanggal
    if (!t) return null
    const [y, m, d] = t.split('-').map(Number)
    return new Date(y, m - 1, d)   // local midnight — tidak terpengaruh timezone
  },
  set: (d) => {
    if (!editForm.value) return
    if (d) {
      editForm.value.tanggal = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
    } else {
      editForm.value.tanggal = ''
    }
  },
})

const statusOptions = [
  { label: 'Tepat Waktu',      value: 'tepat_waktu' },
  { label: 'Terlambat',        value: 'terlambat' },
  { label: 'Terlambat (Izin)', value: 'terlambat_izin' },
]

// ── Computed ─────────────────────────────────────────────────
const mapsUrlMasuk  = computed(() => item.value?.location_masuk  ? `https://maps.google.com/?q=${item.value.location_masuk}`  : '#')
const mapsUrlPulang = computed(() => item.value?.location_pulang ? `https://maps.google.com/?q=${item.value.location_pulang}` : '#')

// ── Helpers ──────────────────────────────────────────────────
function statusLabel(s) {
  return {
    tepat_waktu:    'Tepat Waktu',
    terlambat:      'Terlambat',
    terlambat_izin: 'Terlambat (Izin)',
  }[s] ?? s
}

// ── Penampil foto ─────────────────────────────────────────────
function openPhoto(url) {
  if (!url) return
  photoUrl.value = url
  photoVisible.value = true
}

// ── Load data ─────────────────────────────────────────────────
async function loadData() {
  loading.value = true
  try {
    const { data } = await presensiService.show(route.params.id)
    item.value = data.data ?? data

    // Auto-buka dialog edit jika navigasi dari tabel dengan ?edit=1
    if (route.query.edit === '1' && can('edit_presensi')) {
      // Hapus query param dari URL tanpa reload
      router.replace({ name: 'attendance-detail', params: { id: route.params.id } })
      openEdit()
    }
  } catch {
    toast.error('Data presensi tidak ditemukan')
  } finally {
    loading.value = false
  }
}

// ── Edit ──────────────────────────────────────────────────────
// API mengirim tanggal sebagai waktu UTC ("2026-09-04T17:00:00.000000Z" = 5 Sep WIB),
// sedangkan form memakai YYYY-MM-DD lokal; tanpa ini kolom Tanggal tampil "NaN-NaN-NaN".
function keTanggalLokal(nilai) {
  if (!nilai) return ''
  if (/^\d{4}-\d{2}-\d{2}$/.test(nilai)) return nilai
  // Safari lama gagal membaca pecahan detik lebih dari 3 digit
  const d = new Date(String(nilai).replace(/(\.\d{3})\d+/, '$1'))
  if (Number.isNaN(d.getTime())) return ''
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function openEdit() {
  if (!item.value) return
  editForm.value = {
    tanggal:    keTanggalLokal(item.value.tanggal),
    jam_masuk:  item.value.jam_masuk  ?? '',
    jam_pulang: item.value.jam_pulang ?? '',
    status:     item.value.status     ?? 'tepat_waktu',
    keterangan: item.value.keterangan ?? '',
  }
  editVisible.value = true
}

async function doSave() {
  if (!editForm.value.tanggal) { toast.error('Tanggal wajib diisi.'); return }
  if (!editForm.value.status)  { toast.error('Status wajib dipilih.'); return }

  // InputMask mengembalikan "08:__" jika tidak lengkap — bersihkan dulu
  const cleanJam = (v) => (v && !v.includes('_')) ? v.trim() : ''
  const jamMasuk  = cleanJam(editForm.value.jam_masuk)
  const jamPulang = cleanJam(editForm.value.jam_pulang)

  // Validasi format jam yang sudah diisi
  const jamRe = /^([01]\d|2[0-3]):[0-5]\d$/
  if (jamMasuk  && !jamRe.test(jamMasuk))  { toast.error('Format jam masuk tidak valid (HH:MM).'); return }
  if (jamPulang && !jamRe.test(jamPulang)) { toast.error('Format jam pulang tidak valid (HH:MM).'); return }

  // Cegah jam pulang lebih awal dari jam masuk
  if (jamMasuk && jamPulang && jamPulang <= jamMasuk) {
    toast.error('Jam pulang harus lebih dari jam masuk.'); return
  }

  saving.value = true
  try {
    const payload = {
      tanggal:    editForm.value.tanggal,
      status:     editForm.value.status,
      keterangan: editForm.value.keterangan || null,
      jam_masuk:  jamMasuk  || null,
      jam_pulang: jamPulang || null,
    }

    await presensiService.update(route.params.id, payload)
    toast.success('Data presensi berhasil diperbarui.')
    editVisible.value = false
    await loadData()
  } catch (err) {
    toast.apiError(err)
  } finally {
    saving.value = false
  }
}

// ── Delete ────────────────────────────────────────────────────
function confirmDelete() {
  confirm.require({
    message: `Hapus data presensi ${item.value?.sales_nama} tanggal ${formatDate(item.value?.tanggal)}? Tindakan ini tidak bisa dibatalkan.`,
    header: 'Konfirmasi Hapus Presensi',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Ya, Hapus',
    rejectLabel: 'Batal',
    acceptClass: 'p-button-danger',
    accept: doDelete,
  })
}

async function doDelete() {
  try {
    await presensiService.destroy(route.params.id)
    toast.success('Data presensi berhasil dihapus.')
    router.back()
  } catch (err) {
    toast.apiError(err)
  }
}

onMounted(loadData)
</script>

<style scoped>
.detail-view { display: flex; flex-direction: column; gap: 1.25rem; }

.detail-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.header-actions { display: flex; gap: 0.5rem; }

.skeleton-wrap { display: flex; flex-direction: column; gap: 1rem; }
.mb-3 { margin-bottom: 0; }

.info-card :deep(.p-card-body) { padding: 1.25rem 1.5rem; }

.section-title {
  font-size: 0.9rem; font-weight: 700;
  color: var(--p-text-color); margin-bottom: 1rem;
  display: flex; align-items: center; gap: 0.4rem;
}
.section-title .pi { color: var(--p-primary-color); }

.info-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
.attend-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 0.5rem; }
@media (max-width: 640px) {
  .info-grid-3, .attend-grid { grid-template-columns: 1fr; }
}

.info-item { display: flex; flex-direction: column; gap: 4px; }
.info-label {
  font-size: 0.75rem; color: var(--p-text-muted-color);
  text-transform: uppercase; letter-spacing: 0.03em;
  display: flex; align-items: center; gap: 0.25rem;
}
.info-value { font-size: 0.95rem; font-weight: 600; color: var(--p-text-color); }
.info-value.primary { color: var(--p-primary-color); }
.info-sub { font-size: 0.75rem; color: var(--p-text-muted-color); }
.status-tag { margin-top: 2px; }

.keterangan-block {
  display: flex; flex-direction: column; gap: 4px;
  margin-top: 1rem; padding: 0.75rem;
  background: var(--s-1);
  border-radius: 8px;
  border-left: 3px solid var(--p-primary-200);
}
.keterangan-text { font-size: 0.88rem; font-weight: 400; white-space: pre-wrap; }

.location-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
@media (max-width: 640px) { .location-grid { grid-template-columns: 1fr; } }

.field-block { display: flex; flex-direction: column; gap: 0.3rem; }
.field-label {
  font-size: 0.78rem; font-weight: 600; color: var(--p-text-muted-color);
  display: flex; align-items: center; gap: 0.3rem;
}
.coord-link {
  font-size: 0.88rem; font-weight: 700; color: var(--p-primary-color);
  text-decoration: none; word-break: break-all;
}
.coord-link:hover { text-decoration: underline; }
.coord-empty { font-size: 0.85rem; color: var(--p-text-muted-color); font-style: italic; }
.coord-address { font-size: 0.8rem; color: var(--p-text-muted-color); line-height: 1.4; }

/* Photos */
.photos-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
@media (max-width: 640px) { .photos-grid { grid-template-columns: 1fr; } }

.photo-section { display: flex; flex-direction: column; gap: 0.5rem; }
.photo-label {
  font-size: 0.82rem; font-weight: 600; color: var(--p-text-color);
  display: flex; align-items: center; gap: 0.35rem;
}
.photo-label .pi { color: var(--p-primary-color); }
.photo-wrap {
  position: relative; cursor: zoom-in;
  border-radius: 12px; overflow: hidden;
  border: 2px solid var(--p-content-border-color);
  background: var(--s-1);
}
.foto-img {
  width: 100%; display: block;
  object-fit: cover; max-height: 420px;
  transition: transform 0.3s;
}
.photo-wrap:hover .foto-img { transform: scale(1.02); }
.foto-overlay {
  position: absolute; inset: 0;
  background: rgba(0,0,0,0.3);
  display: flex; align-items: center; justify-content: center;
  opacity: 0; transition: opacity 0.2s;
  color: #fff; font-size: 1.75rem;
}
.photo-wrap:hover .foto-overlay { opacity: 1; }
.foto-empty {
  height: 320px;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 0.5rem; color: var(--p-text-muted-color); font-size: 0.85rem;
}
.foto-empty .pi { font-size: 2.5rem; }
.photo-meta { font-size: 0.78rem; color: var(--p-text-muted-color); text-align: center; }

.empty-state {
  display: flex; flex-direction: column; align-items: center;
  gap: 0.75rem; padding: 4rem; color: var(--p-text-muted-color);
}
.empty-state i { font-size: 3rem; }

/* ── Edit Form ───────────────────────────────────────────── */
.edit-form { display: flex; flex-direction: column; gap: 1rem; padding-top: 0.25rem; }

.edit-sales-info {
  display: flex; align-items: center; gap: 0.75rem;
  padding: 0.75rem 1rem;
  background: var(--s-1);
  border-radius: 10px;
  border: 1px solid var(--p-content-border-color);
}
.edit-sales-info .pi { font-size: 1.1rem; color: var(--p-primary-color); }
.edit-sales-name { font-weight: 700; font-size: 0.9rem; color: var(--p-text-color); }
.edit-sales-date { font-size: 0.78rem; color: var(--p-text-muted-color); margin-top: 2px; }

.field { display: flex; flex-direction: column; gap: 0.35rem; }
.field-label {
  font-size: 0.85rem; font-weight: 600;
  color: var(--p-text-color);
}
.required { color: var(--p-red-500); }

.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }

.edit-hint {
  display: flex; align-items: flex-start; gap: 0.5rem;
  padding: 0.6rem 0.75rem;
  background: var(--p-blue-50);
  border-radius: 8px;
  font-size: 0.78rem;
  color: var(--p-blue-700);
  line-height: 1.5;
}
.edit-hint .pi { color: var(--p-blue-500); flex-shrink: 0; margin-top: 1px; }

</style>
