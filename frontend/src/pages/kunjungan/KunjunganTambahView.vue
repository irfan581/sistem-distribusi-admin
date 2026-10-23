<template>
  <div class="tambah-view">
    <PageHeader title="Tambah Kunjungan Manual" subtitle="Fitur khusus developer: tambah data kunjungan atas nama sales.">
      <template #actions>
        <Button icon="pi pi-arrow-left" text plain label="Kembali" @click="$router.back()" />
      </template>
    </PageHeader>

    <!-- Guard: hanya developer -->
    <Card v-if="!isDeveloper" class="guard-card">
      <template #content>
        <div class="guard-content">
          <i class="pi pi-lock" />
          <span>Halaman ini hanya dapat diakses oleh Developer.</span>
        </div>
      </template>
    </Card>

    <template v-else>
      <Card>
        <template #content>
          <div class="form-grid">

            <!-- Sales -->
            <div class="field">
              <label class="field-label">Nama Sales <span class="req">*</span></label>
              <Select
                v-model="form.sales_id"
                :options="salesList"
                option-label="nama"
                option-value="id"
                placeholder="Pilih sales..."
                :loading="loadingSales"
                filter
                fluid
              />
            </div>

            <!-- Nama Toko -->
            <div class="field">
              <label class="field-label">Nama Toko <span class="req">*</span></label>
              <InputText v-model="form.nama_toko" placeholder="Nama toko yang dikunjungi" fluid />
            </div>

            <!-- Keterangan -->
            <div class="field full">
              <label class="field-label">Keterangan / Catatan Kunjungan</label>
              <Textarea v-model="form.keterangan" rows="3" placeholder="Hasil kunjungan, kondisi toko, dll..." fluid />
            </div>

            <!-- Waktu Kunjungan -->
            <div class="field">
              <label class="field-label">Waktu Kunjungan <span class="req">*</span></label>
              <DatePicker
                v-model="form.created_at"
                showIcon
                showTime
                hourFormat="24"
                dateFormat="dd/mm/yy"
                placeholder="Pilih tanggal & jam..."
                fluid
              />
            </div>

            <!-- Waktu Checkout -->
            <div class="field">
              <label class="field-label">Waktu Checkout</label>
              <DatePicker
                v-model="form.checkout_at"
                showIcon
                showTime
                hourFormat="24"
                dateFormat="dd/mm/yy"
                placeholder="Pilih tanggal & jam... (opsional)"
                showClear
                fluid
              />
            </div>

            <!-- Location -->
            <div class="field full">
              <label class="field-label">Koordinat GPS</label>
              <InputText v-model="form.location" placeholder="-7.585936, 112.224248" fluid />
              <small class="field-hint">Format: lat,lng. Kosongkan jika tidak ada</small>
            </div>

            <!-- Foto -->
            <div class="field full">
              <label class="field-label">Foto Bukti Kunjungan</label>
              <div class="foto-upload-wrap" @click="triggerFileInput" @dragover.prevent @drop.prevent="onDrop">
                <input ref="fileInput" type="file" accept="image/*" class="hidden-input" @change="onFileChange" />
                <div v-if="fotoPreview" class="foto-preview-box">
                  <img :src="fotoPreview" class="foto-preview-img" alt="Preview" />
                  <Button icon="pi pi-times" rounded text severity="danger" class="foto-remove-btn" @click.stop="removeFoto" />
                </div>
                <div v-else class="foto-placeholder">
                  <i class="pi pi-camera" />
                  <span>Klik atau drag foto ke sini</span>
                  <small>JPG / PNG / WEBP · maks 5 MB</small>
                </div>
              </div>
            </div>

          </div>

          <div class="form-actions">
            <Button label="Batal" text @click="$router.back()" />
            <Button label="Simpan Kunjungan" icon="pi pi-check" :loading="saving" @click="doSave" />
          </div>
        </template>
      </Card>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { kunjunganService } from '@/services/kunjungan.service'
import { employeeService } from '@/services/employee.service'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth.store'
import PageHeader from '@/components/common/PageHeader.vue'

const router    = useRouter()
const toast     = useToast()
const authStore = useAuthStore()

const isDeveloper = computed(() =>
  ['developer', 'owner'].includes(authStore.user?.role_nama)
)

const saving       = ref(false)
const loadingSales = ref(false)
const salesList    = ref([])
const fileInput    = ref(null)
const fotoFile     = ref(null)
const fotoPreview  = ref(null)

const form = ref({
  sales_id:    null,
  nama_toko:   '',
  keterangan:  '',
  created_at:  null,
  checkout_at: null,
  location:    '',
})

function formatDatetime(date) {
  if (!date) return null
  const pad = n => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:00`
}

function triggerFileInput() { fileInput.value?.click() }

function onFileChange(e) {
  const file = e.target.files[0]
  if (!file) return
  if (file.size > 5 * 1024 * 1024) { toast.error('Ukuran foto maksimal 5 MB'); return }
  fotoFile.value    = file
  fotoPreview.value = URL.createObjectURL(file)
}

function onDrop(e) {
  const file = e.dataTransfer.files[0]
  if (!file || !file.type.startsWith('image/')) return
  if (file.size > 5 * 1024 * 1024) { toast.error('Ukuran foto maksimal 5 MB'); return }
  fotoFile.value    = file
  fotoPreview.value = URL.createObjectURL(file)
}

function removeFoto() {
  fotoFile.value    = null
  fotoPreview.value = null
  if (fileInput.value) fileInput.value.value = ''
}

async function loadSales() {
  loadingSales.value = true
  try {
    const res = await employeeService.getAll({ per_page: 200, is_active: 1 })
    const paginated = res.data
    salesList.value = paginated.data || paginated
  } catch {
    toast.error('Gagal memuat daftar sales')
  } finally {
    loadingSales.value = false
  }
}

async function doSave() {
  if (!form.value.sales_id)       { toast.error('Pilih sales terlebih dahulu'); return }
  if (!form.value.nama_toko.trim()) { toast.error('Nama toko wajib diisi'); return }
  if (!form.value.created_at)     { toast.error('Waktu kunjungan wajib diisi'); return }

  saving.value = true
  try {
    const payload = {
      sales_id:        form.value.sales_id,
      nama_toko:       form.value.nama_toko.trim(),
      keterangan:      form.value.keterangan.trim() || null,
      created_at:      formatDatetime(form.value.created_at),
      checkout_at:     formatDatetime(form.value.checkout_at),
      location:        form.value.location.trim() || '0,0',
      foto_kunjungan:  fotoFile.value ?? undefined,
    }
    await kunjunganService.store(payload)
    toast.success('Kunjungan berhasil ditambahkan')
    router.push({ name: 'kunjungan' })
  } catch (err) {
    toast.apiError(err)
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  if (isDeveloper.value) loadSales()
})
</script>

<style scoped>
.tambah-view { display: flex; flex-direction: column; gap: 1.25rem; }

.guard-card :deep(.p-card-body) { padding: 2rem; }
.guard-content {
  display: flex; flex-direction: column; align-items: center;
  gap: 0.75rem; color: var(--p-text-muted-color);
}
.guard-content .pi { font-size: 2.5rem; }

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
  margin-bottom: 1.5rem;
}
@media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }

.field { display: flex; flex-direction: column; gap: 0.35rem; }
.field.full { grid-column: 1 / -1; }
.field-label { font-size: 0.82rem; font-weight: 600; color: var(--p-text-color); }
.req { color: var(--p-red-500); }
.field-hint { font-size: 0.72rem; color: var(--p-text-muted-color); }

.form-actions {
  display: flex; justify-content: flex-end;
  gap: 0.75rem; padding-top: 1rem;
  border-top: 1px solid var(--p-content-border-color);
}

.hidden-input { display: none; }

.foto-upload-wrap {
  border: 2px dashed var(--p-content-border-color);
  border-radius: 10px; cursor: pointer;
  transition: border-color 0.2s;
  overflow: hidden;
}
.foto-upload-wrap:hover { border-color: var(--p-primary-color); }

.foto-placeholder {
  display: flex; flex-direction: column; align-items: center;
  gap: 0.4rem; padding: 2rem;
  color: var(--p-text-muted-color);
}
.foto-placeholder .pi { font-size: 2rem; color: var(--p-primary-color); }
.foto-placeholder small { font-size: 0.72rem; }

.foto-preview-box {
  position: relative;
}
.foto-preview-img {
  width: 100%; max-height: 260px;
  object-fit: cover; display: block;
}
.foto-remove-btn {
  position: absolute; top: 0.5rem; right: 0.5rem;
  background: rgba(0,0,0,0.5) !important;
  color: #fff !important;
}
</style>
