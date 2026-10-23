<template>
  <div class="detail-view">
    <div class="detail-header">
      <Button icon="pi pi-arrow-left" text plain @click="$router.back()" label="Kembali" />
      <div class="header-actions">
        <Button
          v-if="isDeveloper"
          icon="pi pi-pencil"
          label="Edit"
          severity="secondary"
          outlined
          @click="openEdit"
        />
        <Button
          v-if="can('delete_kunjungan')"
          icon="pi pi-trash"
          label="Hapus"
          severity="danger"
          outlined
          :loading="deleting"
          @click="confirmDelete"
        />
      </div>
    </div>

    <div v-if="loading" class="skeleton-wrap">
      <Skeleton height="120px" class="mb-3" />
      <div class="grid-2"><Skeleton height="260px" /><Skeleton height="260px" /></div>
    </div>

    <template v-else-if="item">
      <!-- Top info card -->
      <Card class="info-card">
        <template #content>
          <div class="section-title"><i class="pi pi-info-circle" /> Informasi Kunjungan</div>
          <div class="info-grid-3">
            <div class="info-item">
              <span class="info-label"><i class="pi pi-user" /> Nama Sales</span>
              <span class="info-value primary">{{ item.sales_nama }}</span>
              <span class="info-sub">{{ item.sales_area }}</span>
            </div>
            <div class="info-item">
              <span class="info-label"><i class="pi pi-building" /> Nama Toko</span>
              <span class="info-value">{{ item.nama_toko }}</span>
            </div>
            <div class="info-item">
              <span class="info-label"><i class="pi pi-clock" /> Waktu Kunjungan</span>
              <span class="info-value">{{ item.tanggal }}, {{ item.jam }}</span>
              <span v-if="item.durasi_label" class="info-sub">Durasi: {{ item.durasi_label }}</span>
            </div>
          </div>
        </template>
      </Card>

      <!-- Middle: location + photo -->
      <div class="middle-grid">

        <!-- Location card -->
        <Card class="location-card">
          <template #content>
            <div class="section-title"><i class="pi pi-map-marker" /> Detail & Validasi Lokasi</div>

            <div class="field-block">
              <span class="field-label">Hasil Kunjungan / Keterangan</span>
              <p class="field-prose">{{ item.keterangan || 'Tidak ada catatan tambahan.' }}</p>
            </div>

            <div class="gps-grid">
              <div class="field-block">
                <span class="field-label"><i class="pi pi-map-pin" /> Koordinat GPS</span>
                <a v-if="item.location" :href="mapsUrl" target="_blank" class="coord-link">
                  {{ item.location }}
                </a>
                <span v-else class="coord-empty">Tidak tersedia</span>
                <span v-if="item.alamat" class="coord-address">{{ item.alamat }}</span>
                <span class="field-hint">Klik koordinat di atas untuk membuka Google Maps</span>
              </div>

              <div class="field-block">
                <span class="field-label">Status Keamanan GPS</span>
                <div class="gps-status" :class="item.is_suspicious ? 'gps-warn' : 'gps-ok'">
                  <i :class="['pi', item.is_suspicious ? 'pi-exclamation-triangle' : 'pi-check-circle']" />
                  <span>{{ item.is_suspicious ? 'Mencurigakan' : 'Aman' }}</span>
                </div>
                <p v-if="item.is_suspicious && item.suspicious_reason" class="suspicious-reason">
                  {{ item.suspicious_reason }}
                </p>
              </div>
            </div>
          </template>
        </Card>

        <!-- Photo card -->
        <Card class="photo-card">
          <template #content>
            <div class="section-title"><i class="pi pi-camera" /> Bukti Foto</div>
            <div class="photo-wrap" @click="openPhoto(item.foto_url)">
              <img v-if="item.foto_url" :src="item.foto_url" class="foto-kunjungan" alt="Bukti foto" />
              <div v-else class="foto-empty">
                <i class="pi pi-image" />
                <span>Foto tidak tersedia</span>
              </div>
              <div v-if="item.foto_url" class="foto-overlay"><i class="pi pi-search-plus" /></div>
            </div>
            <TanggapanFoto
              class="tanggapan-kunjungan"
              :tanggapan="item.tanggapan"
              :boleh-ubah="can('edit_kunjungan')"
              :simpan="simpanTanggapan"
              @tersimpan="t => { item.tanggapan = t; tanggapanStore.muat() }"
            />
          </template>
        </Card>

      </div>
    </template>

    <div v-else class="empty-state">
      <i class="pi pi-exclamation-circle" />
      <span>Data kunjungan tidak ditemukan</span>
    </div>

    <FotoViewer v-model:visible="photoVisible" :src="photoUrl" title="Bukti Foto Kunjungan" />

    <!-- Dialog Edit Kunjungan (developer only) -->
    <Dialog v-model:visible="editVisible" modal header="Edit Kunjungan" :style="{ width: '500px' }" :draggable="false">
      <div class="edit-form">
        <div class="field">
          <label class="field-label">Nama Toko <span class="req">*</span></label>
          <InputText v-model="editForm.nama_toko" fluid />
        </div>
        <div class="field">
          <label class="field-label">Keterangan / Catatan</label>
          <Textarea v-model="editForm.keterangan" rows="3" fluid />
        </div>
        <div class="field">
          <label class="field-label">Waktu Kunjungan <span class="req">*</span></label>
          <DatePicker
            v-model="editForm.created_at"
            showIcon showTime hourFormat="24"
            dateFormat="dd/mm/yy"
            placeholder="Pilih tanggal & jam..."
            fluid
          />
        </div>
        <div class="field">
          <label class="field-label">Waktu Checkout</label>
          <DatePicker
            v-model="editForm.checkout_at"
            showIcon showTime hourFormat="24"
            dateFormat="dd/mm/yy"
            placeholder="Pilih tanggal & jam... (opsional)"
            showClear fluid
          />
        </div>
        <div class="field">
          <label class="field-label">Ganti Foto Bukti</label>
          <div class="foto-upload-wrap" @click="editFileInput?.click()" @dragover.prevent @drop.prevent="onEditDrop">
            <input ref="editFileInput" type="file" accept="image/*" class="hidden-input" @change="onEditFileChange" />
            <div v-if="editFotoPreview" class="foto-preview-box">
              <img :src="editFotoPreview" class="foto-preview-img" alt="Preview" />
              <Button icon="pi pi-times" rounded text severity="danger" class="foto-remove-btn" @click.stop="removeEditFoto" />
            </div>
            <div v-else-if="item?.foto_url" class="foto-preview-box">
              <img :src="item.foto_url" class="foto-preview-img" alt="Foto saat ini" />
              <span class="foto-current-label">Foto saat ini · klik untuk ganti</span>
            </div>
            <div v-else class="foto-placeholder">
              <i class="pi pi-camera" />
              <span>Klik atau drag foto ke sini</span>
              <small>JPG / PNG · maks 5 MB</small>
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Batal" text @click="editVisible = false" />
        <Button label="Simpan" icon="pi pi-check" :loading="saving" @click="doEdit" />
      </template>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import { kunjunganService } from '@/services/kunjungan.service'
import { useToast } from '@/composables/useToast'
import { usePermission } from '@/composables/usePermission'
import { useAuthStore } from '@/stores/auth.store'
import FotoViewer from '@/components/common/FotoViewer.vue'
import TanggapanFoto from '@/components/common/TanggapanFoto.vue'
import { useTanggapanStore } from '@/stores/tanggapan.store'

const { can }   = usePermission()
const authStore = useAuthStore()
const tanggapanStore = useTanggapanStore()

async function simpanTanggapan(payload) {
  const { data } = await kunjunganService.tanggapi(item.value.id, payload)
  toast.success(`Foto ${payload.status}. Sales diberi tahu lewat HP.`)
  return data.data
}

// Hanya developer/owner yang bisa edit & tambah kunjungan
const isDeveloper = computed(() =>
  ['developer', 'owner'].includes(authStore.user?.role_nama)
)

const route   = useRoute()
const router  = useRouter()
const confirm = useConfirm()
const toast   = useToast()

const loading      = ref(false)
const deleting     = ref(false)
const saving       = ref(false)
const item         = ref(null)
const photoVisible = ref(false)
const photoUrl     = ref('')

// ── Edit dialog ───────────────────────────────────────────
const editVisible   = ref(false)
const editForm      = ref({ nama_toko: '', keterangan: '', created_at: null, checkout_at: null })
const editFileInput = ref(null)
const editFotoFile  = ref(null)
const editFotoPreview = ref(null)

function onEditFileChange(e) {
  const file = e.target.files[0]
  if (!file) return
  if (file.size > 5 * 1024 * 1024) { toast.error('Ukuran foto maksimal 5 MB'); return }
  editFotoFile.value    = file
  editFotoPreview.value = URL.createObjectURL(file)
}
function onEditDrop(e) {
  const file = e.dataTransfer.files[0]
  if (!file || !file.type.startsWith('image/')) return
  editFotoFile.value    = file
  editFotoPreview.value = URL.createObjectURL(file)
}
function removeEditFoto() {
  editFotoFile.value    = null
  editFotoPreview.value = null
  if (editFileInput.value) editFileInput.value.value = ''
}

function parseToDate(str) {
  if (!str) return null
  const match = String(str).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/)
  if (!match) return null
  const [, year, month, day, hour, minute, second = '0'] = match
  const d = new Date(
    Number(year),
    Number(month) - 1,
    Number(day),
    Number(hour),
    Number(minute),
    Number(second)
  )
  return isNaN(d.getTime()) ? null : d
}

function formatDatetime(date) {
  if (!date) return null
  const pad = n => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:00`
}

function openEdit() {
  editForm.value = {
    nama_toko:   item.value?.nama_toko  ?? '',
    keterangan:  item.value?.keterangan ?? '',
    created_at:  parseToDate(item.value?.created_at),
    checkout_at: parseToDate(item.value?.checkout_at),
  }
  editFotoFile.value    = null
  editFotoPreview.value = null
  editVisible.value     = true
}

async function doEdit() {
  if (!editForm.value.nama_toko.trim()) {
    toast.error('Nama toko wajib diisi')
    return
  }
  saving.value = true
  try {
    await kunjunganService.update(route.params.id, {
      nama_toko:       editForm.value.nama_toko.trim(),
      keterangan:      editForm.value.keterangan.trim() || null,
      created_at:      formatDatetime(editForm.value.created_at),
      checkout_at:     formatDatetime(editForm.value.checkout_at),
      foto_kunjungan:  editFotoFile.value ?? undefined,
    })
    toast.success('Data kunjungan berhasil diperbarui')
    editVisible.value = false
    await loadData()
  } catch (err) {
    toast.apiError(err)
  } finally {
    saving.value = false
  }
}

// ── Map & photo ───────────────────────────────────────────
const mapsUrl = computed(() => {
  if (!item.value?.location) return '#'
  return `https://www.google.com/maps/search/?api=1&query=${item.value.location}`
})

function openPhoto(url) {
  if (!url) return
  photoUrl.value = url
  photoVisible.value = true
}


async function loadData() {
  loading.value = true
  try {
    const { data } = await kunjunganService.show(route.params.id)
    item.value = data.data ?? data
  } catch {
    toast.error('Data kunjungan tidak ditemukan')
  } finally {
    loading.value = false
  }
}

function confirmDelete() {
  confirm.require({
    message: `Hapus data kunjungan ke "${item.value?.nama_toko}"?`,
    header: 'Hapus Kunjungan',
    icon: 'pi pi-exclamation-triangle',
    acceptSeverity: 'danger',
    acceptLabel: 'Ya, Hapus',
    rejectLabel: 'Batal',
    accept: async () => {
      deleting.value = true
      try {
        await kunjunganService.destroy(route.params.id)
        toast.success('Data kunjungan berhasil dihapus')
        router.push({ name: 'kunjungan' })
      } catch (err) {
        toast.error(err.response?.data?.message || 'Gagal menghapus')
      } finally {
        deleting.value = false
      }
    },
  })
}

onMounted(loadData)
</script>

<style scoped>
.detail-view { display: flex; flex-direction: column; gap: 1.25rem; }

.detail-header {
  display: flex; align-items: center; justify-content: space-between;
}
.header-actions { display: flex; align-items: center; gap: 0.5rem; }

.edit-form { display: flex; flex-direction: column; gap: 1rem; padding: 0.5rem 0; }
.edit-form .field { display: flex; flex-direction: column; gap: 0.35rem; }
.edit-form .field-label { font-size: 0.82rem; font-weight: 600; color: var(--p-text-color); }
.edit-form .req { color: var(--p-red-500); }
.edit-form .field-hint { font-size: 0.72rem; color: var(--p-text-muted-color); }

.hidden-input { display: none; }

.foto-upload-wrap {
  border: 2px dashed var(--p-content-border-color);
  border-radius: 10px; cursor: pointer; overflow: hidden;
  transition: border-color 0.2s;
}
.foto-upload-wrap:hover { border-color: var(--p-primary-color); }

.foto-placeholder {
  display: flex; flex-direction: column; align-items: center;
  gap: 0.4rem; padding: 1.5rem;
  color: var(--p-text-muted-color);
}
.foto-placeholder .pi { font-size: 1.75rem; color: var(--p-primary-color); }
.foto-placeholder small { font-size: 0.72rem; }

.foto-preview-box { position: relative; }
.foto-preview-img {
  width: 100%; max-height: 200px;
  object-fit: cover; display: block;
}
.foto-current-label {
  position: absolute; bottom: 0; left: 0; right: 0;
  background: rgba(0,0,0,0.55); color: #fff;
  font-size: 0.75rem; text-align: center; padding: 0.35rem;
}
.foto-remove-btn {
  position: absolute; top: 0.4rem; right: 0.4rem;
  background: rgba(0,0,0,0.5) !important; color: #fff !important;
}

.skeleton-wrap { display: flex; flex-direction: column; gap: 1rem; }
.grid-2 { display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; }

/* Info top card */
.info-card :deep(.p-card-body) { padding: 1.25rem 1.5rem; }
.section-title {
  font-size: 0.9rem; font-weight: 700;
  color: var(--p-text-color);
  margin-bottom: 1rem;
  display: flex; align-items: center; gap: 0.4rem;
}
.section-title .pi { color: var(--p-primary-color); }

.info-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
@media (max-width: 640px) { .info-grid-3 { grid-template-columns: 1fr; } }

.info-item { display: flex; flex-direction: column; gap: 3px; }
.info-label { font-size: 0.78rem; color: var(--p-text-muted-color); display: flex; align-items: center; gap: 0.3rem; }
.info-value { font-size: 0.95rem; font-weight: 600; color: var(--p-text-color); }
.info-value.primary { color: var(--p-primary-color); }
.info-sub { font-size: 0.75rem; color: var(--p-text-muted-color); }

/* Middle layout */
.middle-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1.25rem; }
@media (max-width: 800px) { .middle-grid { grid-template-columns: 1fr; } }

.location-card :deep(.p-card-body),
.photo-card :deep(.p-card-body) { padding: 1.25rem 1.5rem; }
.tanggapan-kunjungan { margin-top: 1rem; }

.field-block { margin-bottom: 1.25rem; }
.field-label {
  display: flex; align-items: center; gap: 0.3rem;
  font-size: 0.8rem; font-weight: 600;
  color: var(--p-text-muted-color);
  margin-bottom: 0.35rem;
  text-transform: uppercase; letter-spacing: 0.03em;
}
.field-prose {
  font-size: 0.9rem; color: var(--p-text-color);
  line-height: 1.6; margin: 0;
}
.field-hint { font-size: 0.73rem; color: var(--p-text-muted-color); margin-top: 0.25rem; display: block; }

.gps-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 640px) { .gps-grid { grid-template-columns: 1fr; } }

.coord-link {
  font-size: 0.88rem; font-weight: 700;
  color: var(--p-primary-color);
  text-decoration: none; display: block;
}
.coord-link:hover { text-decoration: underline; }
.coord-empty { font-size: 0.85rem; color: var(--p-text-muted-color); font-style: italic; }
.coord-address { font-size: 0.8rem; color: var(--p-text-muted-color); line-height: 1.4; }

.gps-status {
  display: inline-flex; align-items: center; gap: 0.4rem;
  font-size: 0.85rem; font-weight: 600;
  padding: 0.3rem 0.75rem; border-radius: 20px;
  margin-top: 0.25rem;
}
.gps-ok   { background: var(--p-green-100, var(--c-success-soft)); color: var(--p-green-700, var(--c-success)); }
.gps-warn { background: var(--p-red-100, var(--c-danger-soft)); color: var(--p-red-600, var(--c-danger)); }
.suspicious-reason { font-size: 0.8rem; color: var(--p-red-500); margin: 0.5rem 0 0; }

/* dark mode overrides */
:global(.p-dark) .gps-ok  { background: rgba(22, 163, 74, 0.15); color: var(--c-success); }
:global(.p-dark) .gps-warn { background: rgba(220, 38, 38, 0.15); color: var(--c-danger); }

/* Photo */
.photo-wrap {
  position: relative; cursor: zoom-in;
  border-radius: 12px; overflow: hidden;
  border: 1px solid var(--p-content-border-color);
}
.foto-kunjungan {
  width: 100%; display: block;
  object-fit: cover; max-height: 420px;
  transition: transform 0.3s;
}
.photo-wrap:hover .foto-kunjungan { transform: scale(1.02); }
.foto-overlay {
  position: absolute; inset: 0;
  background: rgba(0,0,0,0.3);
  display: flex; align-items: center; justify-content: center;
  opacity: 0; transition: opacity 0.2s;
  color: #fff; font-size: 1.75rem;
}
.photo-wrap:hover .foto-overlay { opacity: 1; }
.foto-empty {
  height: 260px; background: var(--s-1);
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 0.5rem; color: var(--p-text-muted-color);
  font-size: 0.85rem;
  border: 1px dashed var(--p-content-border-color);
  border-radius: 8px;
}
.foto-empty .pi { font-size: 2.5rem; }

.empty-state {
  display: flex; flex-direction: column; align-items: center;
  gap: 0.75rem; padding: 4rem; color: var(--p-text-muted-color);
}
.empty-state i { font-size: 3rem; }

</style>
