<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    :header="isEdit ? 'Edit Data Sales' : 'Tambah Akun Sales'"
    :style="{ width: '600px' }"
    modal
    :draggable="false"
  >
    <form @submit.prevent="handleSubmit" class="employee-form">

      <!-- Foto Profil Upload -->
      <div class="foto-field">
        <div class="foto-preview" @click="triggerFotoInput">
          <img v-if="fotoPreview" :src="fotoPreview" class="foto-img" alt="Foto" />
          <SalesAvatar
            v-else-if="props.employee?.foto_profil_url"
            :src="props.employee.foto_profil_url"
            :nama="props.employee.nama"
            :size="80"
            shape="circle"
            class="foto-avatar"
          />
          <div v-else class="foto-placeholder">
            <i class="pi pi-camera" />
            <span>Foto Profil</span>
          </div>
          <div class="foto-overlay"><i class="pi pi-camera" /> Ganti Foto</div>
        </div>
        <input
          ref="fotoInputRef"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          style="display:none"
          @change="onFotoChange"
        />
        <small class="foto-hint">Klik foto untuk ganti · JPG/PNG/WEBP · Maks 2MB</small>
        <small v-if="errors.foto" class="p-error">{{ errors.foto }}</small>
      </div>

      <div class="form-grid">
        <div class="field">
          <label>Nama Lengkap <span class="required">*</span></label>
          <InputText v-model="form.nama" :class="{ 'p-invalid': errors.nama }" fluid />
          <small v-if="errors.nama" class="p-error">{{ errors.nama }}</small>
        </div>
        <div class="field">
          <label>NIK <span class="required">*</span></label>
          <InputText v-model="form.nik" :class="{ 'p-invalid': errors.nik }" fluid />
          <small v-if="errors.nik" class="p-error">{{ errors.nik }}</small>
        </div>
        <div class="field">
          <label>No. HP <span class="required">*</span></label>
          <InputText v-model="form.no_hp" :class="{ 'p-invalid': errors.no_hp }" fluid />
          <small v-if="errors.no_hp" class="p-error">{{ errors.no_hp }}</small>
        </div>
        <div class="field">
          <label>Area</label>
          <InputText v-model="form.area" fluid />
        </div>
        <div class="field full-width">
          <label>Alamat</label>
          <Textarea v-model="form.alamat" rows="2" fluid autoResize />
        </div>
        <div class="field">
          <label>Target Kunjungan / Hari</label>
          <InputNumber v-model="form.target_kunjungan" :min="0" :max="99" fluid />
        </div>
        <div class="field">
          <label>Status</label>
          <Select v-model="form.is_active" :options="statusOptions" option-label="label" option-value="value" fluid />
        </div>
        <div class="field">
          <label>Role</label>
          <Select v-model="form.is_spv" :options="roleOptions" option-label="label" option-value="value" fluid />
        </div>

        <template v-if="!isEdit">
          <div class="field">
            <label>Password <span class="required">*</span></label>
            <Password v-model="form.password" :feedback="false" toggleMask fluid :class="{ 'p-invalid': errors.password }" />
            <small v-if="errors.password" class="p-error">{{ errors.password }}</small>
          </div>
          <div class="field">
            <label>Konfirmasi Password <span class="required">*</span></label>
            <Password v-model="form.password_confirmation" :feedback="false" toggleMask fluid :class="{ 'p-invalid': errors.password_confirmation }" />
            <small v-if="errors.password_confirmation" class="p-error">{{ errors.password_confirmation }}</small>
          </div>
        </template>
      </div>
    </form>

    <template #footer>
      <Button label="Batal" text plain @click="$emit('update:visible', false)" />
      <Button
        :label="isEdit ? 'Simpan Perubahan' : 'Buat Akun Sales'"
        icon="pi pi-check"
        :loading="saving"
        @click="handleSubmit"
      />
    </template>
  </Dialog>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { employeeService } from '@/services/employee.service'
import { useToast } from '@/composables/useToast'

const props = defineProps({
  visible: Boolean,
  employee: { type: Object, default: null },
})
const emit = defineEmits(['update:visible', 'saved'])
const toast = useToast()
const saving = ref(false)

const isEdit = computed(() => !!props.employee?.id)

const defaultForm = () => ({
  nik: '', nama: '', no_hp: '', area: '', alamat: '',
  target_kunjungan: 3, is_active: true, is_spv: false,
  password: '', password_confirmation: '',
})

const form        = reactive(defaultForm())
const errors      = reactive({ nik: '', nama: '', no_hp: '', password: '', password_confirmation: '', foto: '' })
const fotoFile    = ref(null)   // File object untuk upload
const fotoPreview = ref(null)   // Object URL untuk preview
const fotoInputRef = ref(null)

function triggerFotoInput() {
  fotoInputRef.value?.click()
}

function onFotoChange(e) {
  const file = e.target.files[0]
  if (!file) return
  // Validasi ukuran dan tipe
  if (file.size > 2 * 1024 * 1024) {
    errors.foto = 'Foto maksimal 2MB.'
    return
  }
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    errors.foto = 'Format harus JPG, PNG, atau WEBP.'
    return
  }
  errors.foto = ''
  fotoFile.value = file
  if (fotoPreview.value) URL.revokeObjectURL(fotoPreview.value)
  fotoPreview.value = URL.createObjectURL(file)
}

const statusOptions = [
  { label: 'Aktif', value: true },
  { label: 'Nonaktif', value: false },
]
const roleOptions = [
  { label: 'Sales', value: false },
  { label: 'SPV', value: true },
]

watch(
  () => props.employee,
  (emp) => {
    Object.assign(form, defaultForm())
    if (emp) Object.assign(form, {
      nik:              emp.nik || '',
      nama:             emp.nama || '',
      no_hp:            emp.no_hp || '',
      area:             emp.area || '',
      alamat:           emp.alamat || '',
      target_kunjungan: emp.target_kunjungan ?? 3,
      is_active:        emp.is_active ?? true,
      is_spv:           emp.is_spv ?? false,
    })
    Object.assign(errors, { nik: '', nama: '', no_hp: '', password: '', password_confirmation: '', foto: '' })
    // Reset foto
    if (fotoPreview.value) { URL.revokeObjectURL(fotoPreview.value); fotoPreview.value = null }
    fotoFile.value = null
  },
  { immediate: true },
)

function validate() {
  errors.nik   = form.nik.trim()  ? '' : 'NIK wajib diisi.'
  errors.nama  = form.nama.trim() ? '' : 'Nama wajib diisi.'
  errors.no_hp = form.no_hp.trim() ? '' : 'No. HP wajib diisi.'
  if (!isEdit.value) {
    errors.password = !form.password
      ? 'Password wajib diisi.'
      : form.password.length < 6 ? 'Password minimal 6 karakter.' : ''
    errors.password_confirmation = !form.password_confirmation
      ? 'Konfirmasi password wajib diisi.'
      : form.password !== form.password_confirmation ? 'Password tidak cocok.' : ''
  }
  return !errors.nik && !errors.nama && !errors.no_hp && !errors.password && !errors.password_confirmation
}

async function handleSubmit() {
  if (!validate()) return
  saving.value = true
  try {
    if (isEdit.value) {
      // Gunakan FormData jika ada foto, plain object jika tidak
      if (fotoFile.value) {
        const fd = new FormData()
        fd.append('foto_profil', fotoFile.value)
        fd.append('nik',              form.nik)
        fd.append('nama',             form.nama)
        fd.append('no_hp',            form.no_hp)
        fd.append('area',             form.area)
        fd.append('alamat',           form.alamat)
        fd.append('target_kunjungan', form.target_kunjungan ?? 3)
        fd.append('is_active',        form.is_active ? '1' : '0')
        fd.append('is_spv',           form.is_spv ? '1' : '0')
        await employeeService.updateWithPhoto(props.employee.id, fd)
      } else {
        await employeeService.update(props.employee.id, {
          nik: form.nik, nama: form.nama, no_hp: form.no_hp,
          area: form.area, alamat: form.alamat,
          target_kunjungan: form.target_kunjungan,
          is_active: form.is_active, is_spv: form.is_spv,
        })
      }
      toast.success('Data sales berhasil diperbarui.')
    } else {
      const fd = new FormData()
      Object.entries(form).forEach(([k, v]) => {
        if (v !== null && v !== undefined && v !== '') fd.append(k, v)
      })
      if (fotoFile.value) fd.append('foto_profil', fotoFile.value)
      await employeeService.create(fd)
      toast.success('Akun sales berhasil dibuat.')
    }
    emit('saved')
    emit('update:visible', false)
  } catch (err) {
    const ve = err?.response?.data?.errors
    if (ve) {
      Object.keys(ve).forEach((k) => { if (k in errors) errors[k] = ve[k][0] })
    } else {
      toast.apiError(err)
    }
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
/* ── Foto upload ─────────────────────────────────────── */
.foto-field {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  margin-bottom: 1.25rem;
}

.foto-preview {
  position: relative;
  width: 88px;
  height: 88px;
  border-radius: 50%;
  overflow: hidden;
  cursor: pointer;
  border: 2.5px solid var(--p-primary-200);
  background: var(--s-2);
  flex-shrink: 0;
  transition: border-color 0.15s;
}
.foto-preview:hover { border-color: var(--p-primary-500); }
.foto-preview:hover .foto-overlay { opacity: 1; }

.foto-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.foto-avatar {
  width: 100% !important;
  height: 100% !important;
}

.foto-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  color: var(--p-text-muted-color);
  font-size: 0.7rem;
}
.foto-placeholder .pi { font-size: 1.5rem; }

.foto-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  color: #fff;
  font-size: 0.72rem;
  font-weight: 600;
  opacity: 0;
  transition: opacity 0.15s;
}

.foto-hint {
  font-size: 0.72rem;
  color: var(--p-text-muted-color);
  text-align: center;
}

/* ── Form grid ───────────────────────────────────────── */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.field.full-width {
  grid-column: 1 / -1;
}

.field label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--p-text-color);
}

.required { color: var(--p-red-500); }

@media (max-width: 480px) {
  .form-grid { grid-template-columns: 1fr; }
}
</style>
