<template>
  <Dialog
    v-model:visible="visible"
    :header="isEdit ? 'Edit Akun Admin' : 'Buat Akun Admin Baru'"
    modal
    :style="{ width: '820px', maxWidth: '96vw' }"
    @hide="resetForm"
  >
    <form @submit.prevent="submit" enctype="multipart/form-data">
      <div class="dialog-grid">

        <!-- LEFT: info & permissions -->
        <div class="col-left">

          <!-- Avatar upload -->
          <div class="avatar-section">
            <div class="avatar-wrap" @click="triggerUpload">
              <img v-if="avatarPreview" :src="avatarPreview" class="avatar-img" alt="avatar" />
              <div v-else class="avatar-placeholder">
                <i class="pi pi-camera" />
                <span>Pilih Foto</span>
              </div>
              <div class="avatar-overlay"><i class="pi pi-camera" /></div>
            </div>
            <input ref="fileInput" type="file" accept="image/*" style="display:none" @change="onFileChange" />
            <small class="avatar-hint">Klik untuk pilih foto profil (maks 2 MB)</small>
          </div>

          <!-- Basic info -->
          <div class="field">
            <label>Nama Lengkap <span class="req">*</span></label>
            <InputText v-model="form.name" placeholder="Nama lengkap" class="w-full" />
            <small class="err" v-if="errors.name">{{ errors.name }}</small>
          </div>

          <div class="field">
            <label>Email <span class="req">*</span></label>
            <InputText v-model="form.email" type="email" placeholder="email@example.com" class="w-full" />
            <small class="err" v-if="errors.email">{{ errors.email }}</small>
          </div>

          <div class="field" v-if="!isEdit">
            <label>Password <span class="req">*</span></label>
            <Password v-model="form.password" toggleMask :feedback="false" placeholder="Min. 6 karakter" class="w-full" inputClass="w-full" />
            <small class="err" v-if="errors.password">{{ errors.password }}</small>
          </div>

          <div class="field" v-if="!isEdit">
            <label>Ulangi Password <span class="req">*</span></label>
            <Password v-model="form.password_confirmation" toggleMask :feedback="false" placeholder="Ulangi password" class="w-full" inputClass="w-full" />
          </div>

          <div class="field-row">
            <div class="field" style="flex:1">
              <label>Role <span class="req">*</span></label>
              <Select
                v-model="form.role_id"
                :options="roles"
                optionLabel="label"
                optionValue="id"
                placeholder="Pilih role"
                class="w-full"
                :loading="loadingRoles"
                :disabled="!!roleTerkunci"
              />
              <small class="err" v-if="errors.role_id">{{ errors.role_id }}</small>
            </div>
            <div class="field toggle-field">
              <label>Akses Panel</label>
              <div class="toggle-wrap">
                <ToggleSwitch v-model="form.is_admin" />
                <span class="toggle-label">{{ form.is_admin ? 'Ya' : 'Tidak' }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT: permissions -->
        <div class="col-right">
          <div class="perm-header">
            <span class="perm-title">Hak Akses Spesifik</span>
            <div class="perm-bulk">
              <Button type="button" label="Pilih Semua" size="small" text @click="selectAll" />
              <Button type="button" label="Hapus Semua" size="small" text severity="secondary" @click="clearAll" />
            </div>
          </div>

          <div v-for="group in permGroups" :key="group" class="perm-group">
            <div class="perm-group-header" @click="toggleGroup(group)">
              <Checkbox
                :modelValue="isGroupChecked(group)"
                :indeterminate="isGroupIndeterminate(group)"
                @change.stop="toggleGroupPerms(group)"
                binary
                @click.stop
              />
              <span class="perm-group-label">{{ group }}</span>
              <i :class="['pi', openGroups[group] ? 'pi-chevron-up' : 'pi-chevron-down', 'perm-chevron']" />
            </div>
            <Transition name="slide">
              <div v-if="openGroups[group]" class="perm-items">
                <label v-for="perm in groupPerms(group)" :key="perm.key" class="perm-item" @click.stop>
                  <Checkbox v-model="form.permissions" :value="perm.key" />
                  <span>{{ perm.label }}</span>
                </label>
              </div>
            </Transition>
          </div>
        </div>

      </div>
    </form>

    <template #footer>
      <Button label="Batal" severity="secondary" text @click="visible = false" />
      <Button :label="isEdit ? 'Simpan Perubahan' : 'Buat Akun'" icon="pi pi-check" :loading="saving" @click="submit" />
    </template>
  </Dialog>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { adminUserService } from '@/services/adminUser.service'
import { useToast } from '@/composables/useToast'
import { ALL_PERMISSIONS, PERMISSION_GROUPS } from '@/utils/permissions'

const emit = defineEmits(['saved'])
const toast = useToast()

const visible = ref(false)
const saving = ref(false)
const loadingRoles = ref(false)
const roles = ref([])
const fileInput = ref(null)
const avatarPreview = ref(null)
const avatarFile = ref(null)
const errors = ref({})

const isEdit = ref(false)
const editId = ref(null)
// Peran owner tidak ada di daftar pilihan dan tidak boleh diganti.
const roleTerkunci = ref(null)

const permGroups = PERMISSION_GROUPS
const openGroups = reactive(Object.fromEntries(PERMISSION_GROUPS.map((g) => [g, true])))

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role_id: null,
  is_admin: true,
  permissions: [],
})

function resetForm() {
  form.name = ''
  form.email = ''
  form.password = ''
  form.password_confirmation = ''
  form.role_id = null
  form.is_admin = true
  form.permissions = []
  avatarPreview.value = null
  avatarFile.value = null
  errors.value = {}
  isEdit.value = false
  editId.value = null
  roleTerkunci.value = null
}

async function loadRoles() {
  loadingRoles.value = true
  try {
    const { data } = await adminUserService.roles()
    const daftar = data.data ?? data
    roles.value = roleTerkunci.value ? [roleTerkunci.value, ...daftar] : daftar
  } catch {
    toast.error('Gagal memuat daftar role')
  } finally {
    loadingRoles.value = false
  }
}

function openCreate() {
  resetForm()
  isEdit.value = false
  visible.value = true
  loadRoles()
}

function openEdit(user) {
  resetForm()
  isEdit.value = true
  editId.value = user.id
  form.name = user.name
  form.email = user.email
  form.role_id = user.role_id
  form.is_admin = user.is_admin
  const perms = user.permissions
  form.permissions = Array.isArray(perms) ? [...perms] : []
  avatarPreview.value = user.avatar_url ?? null
  if (user.role_nama === 'owner') {
    roleTerkunci.value = { id: user.role_id, label: user.role_label || 'Owner' }
  }
  visible.value = true
  loadRoles()
}

defineExpose({ openCreate, openEdit })

// ─── Permissions helpers ───────────────────────────────────

// Guard: pastikan selalu array meski data dari server tidak konsisten
function safePerms() {
  return Array.isArray(form.permissions) ? form.permissions : []
}

function groupPerms(group) {
  return ALL_PERMISSIONS.filter((p) => p.group === group)
}

function isGroupChecked(group) {
  const keys = groupPerms(group).map((p) => p.key)
  const perms = safePerms()
  return keys.every((k) => perms.includes(k))
}

function isGroupIndeterminate(group) {
  const keys = groupPerms(group).map((p) => p.key)
  const perms = safePerms()
  const checked = keys.filter((k) => perms.includes(k))
  return checked.length > 0 && checked.length < keys.length
}

function toggleGroup(group) {
  openGroups[group] = !openGroups[group]
}

function toggleGroupPerms(group) {
  const keys = groupPerms(group).map((p) => p.key)
  const perms = safePerms()
  if (isGroupChecked(group)) {
    form.permissions = perms.filter((k) => !keys.includes(k))
  } else {
    form.permissions = [...new Set([...perms, ...keys])]
  }
}

function selectAll() {
  form.permissions = ALL_PERMISSIONS.map((p) => p.key)
}

function clearAll() {
  form.permissions = []
}

// ─── Avatar ───────────────────────────────────────────────

function triggerUpload() {
  fileInput.value?.click()
}

function onFileChange(e) {
  const file = e.target.files[0]
  if (!file) return
  if (file.size > 2 * 1024 * 1024) {
    toast.error('Ukuran foto maksimal 2 MB')
    return
  }
  avatarFile.value = file
  avatarPreview.value = URL.createObjectURL(file)
}

// ─── Submit ───────────────────────────────────────────────

async function submit() {
  errors.value = {}
  saving.value = true

  try {
    const fd = new FormData()
    fd.append('name', form.name)
    fd.append('email', form.email)
    fd.append('role_id', form.role_id)
    fd.append('is_admin', form.is_admin ? '1' : '0')
    form.permissions.forEach((p) => fd.append('permissions[]', p))
    if (avatarFile.value) fd.append('avatar', avatarFile.value)

    if (!isEdit.value) {
      fd.append('password', form.password)
      fd.append('password_confirmation', form.password_confirmation)
      await adminUserService.store(fd)
      toast.success('Akun admin berhasil dibuat')
    } else {
      await adminUserService.update(editId.value, fd)
      toast.success('Akun admin berhasil diperbarui')
    }

    visible.value = false
    emit('saved')
  } catch (err) {
    const data = err.response?.data
    if (data?.errors) {
      errors.value = Object.fromEntries(
        Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
      )
    } else {
      toast.error(data?.message || 'Terjadi kesalahan')
    }
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.dialog-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}

@media (max-width: 640px) {
  .dialog-grid { grid-template-columns: 1fr; }
}

/* Avatar */
.avatar-section { display: flex; flex-direction: column; align-items: center; gap: 0.4rem; margin-bottom: 1rem; }
.avatar-wrap {
  position: relative;
  width: 96px; height: 96px;
  border-radius: 50%;
  overflow: hidden;
  cursor: pointer;
  border: 2px dashed var(--p-primary-color);
  background: var(--s-1);
}
.avatar-img { width: 100%; height: 100%; object-fit: cover; }
.avatar-placeholder {
  width: 100%; height: 100%;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 0.25rem; font-size: 0.7rem;
  color: var(--p-text-muted-color);
}
.avatar-placeholder .pi { font-size: 1.5rem; }
.avatar-overlay {
  position: absolute; inset: 0;
  background: rgba(0,0,0,0.35);
  display: flex; align-items: center; justify-content: center;
  opacity: 0; transition: opacity 0.2s;
  color: #fff; font-size: 1.25rem;
}
.avatar-wrap:hover .avatar-overlay { opacity: 1; }
.avatar-hint { font-size: 0.72rem; color: var(--p-text-muted-color); text-align: center; }

/* Fields */
.field { display: flex; flex-direction: column; gap: 0.3rem; margin-bottom: 0.85rem; }
.field label { font-size: 0.82rem; font-weight: 600; color: var(--p-text-color); }
.req { color: var(--c-danger); }
.err { color: var(--c-danger); font-size: 0.75rem; }

.field-row { display: flex; gap: 0.75rem; align-items: flex-end; }
.toggle-field { flex-shrink: 0; }
.toggle-wrap { display: flex; align-items: center; gap: 0.5rem; padding-bottom: 0.1rem; }
.toggle-label { font-size: 0.82rem; color: var(--p-text-muted-color); }

/* Permissions */
.col-right {
  border-left: 1px solid var(--p-content-border-color);
  padding-left: 1.25rem;
  overflow-y: auto;
  max-height: 520px;
}

.perm-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 0.75rem;
}
.perm-title { font-size: 0.875rem; font-weight: 700; color: var(--p-text-color); }
.perm-bulk { display: flex; gap: 0.25rem; }

.perm-group { margin-bottom: 0.5rem; border: 1px solid var(--p-content-border-color); border-radius: 6px; overflow: hidden; }

.perm-group-header {
  display: flex; align-items: center; gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  background: var(--s-1);
  cursor: pointer;
  user-select: none;
}
.perm-group-label { flex: 1; font-size: 0.82rem; font-weight: 600; }
.perm-chevron { font-size: 0.7rem; color: var(--p-text-muted-color); }

.perm-items {
  display: flex; flex-direction: column; gap: 0;
  padding: 0.25rem 0;
}
.perm-item {
  display: flex; align-items: center; gap: 0.5rem;
  padding: 0.35rem 0.75rem;
  font-size: 0.8rem;
  cursor: pointer;
  transition: background 0.15s;
}
.perm-item:hover { background: var(--s-2); }

/* Slide transition — pakai transform:scaleY bukan max-height untuk hindari layout thrashing */
.slide-enter-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
  transform-origin: top;
}
.slide-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
  transform-origin: top;
}
.slide-enter-from, .slide-leave-to { opacity: 0; transform: scaleY(0.85); }
.slide-enter-to, .slide-leave-from  { opacity: 1; transform: scaleY(1); }
</style>
