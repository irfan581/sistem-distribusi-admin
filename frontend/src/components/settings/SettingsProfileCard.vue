<template>
  <div class="sc sc-full">
    <div class="sc-head">
      <div class="sc-head-icon sc-head-icon-brand"><i class="pi pi-user" /></div>
      <div class="sc-head-text">
        <h3 class="sc-head-title">Profil Saya</h3>
        <p class="sc-head-sub">Foto, nama, dan email akun Anda</p>
      </div>
    </div>

    <div class="sc-body">
      <div class="profile-inner">

        <!-- ── Avatar column ──────────────────────────────── -->
        <div class="avatar-col">
          <!-- Hidden file input -->
          <input
            ref="avatarInput"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            style="display:none"
            @change="onAvatarChange"
          />

          <!-- Clickable ring -->
          <div class="avatar-wrap" @click="triggerFileInput" title="Ganti foto profil">
            <div class="avatar-ring">
              <img
                v-if="avatarPreview || authStore.user?.avatar"
                :src="avatarPreview || authStore.user?.avatar"
                alt="Avatar"
              />
              <span v-else>{{ authStore.userInitials }}</span>
            </div>
            <div class="avatar-cam"><i class="pi pi-camera" /></div>
          </div>

          <!-- Identity -->
          <div class="avatar-meta">
            <span class="avatar-name">{{ authStore.user?.name || 'Admin' }}</span>
            <span class="avatar-email">{{ authStore.user?.email || '-' }}</span>
            <span class="avatar-role-chip">{{ authStore.user?.role || 'Administrator' }}</span>
          </div>

          <!-- File hint -->
          <div v-if="avatarFile" class="avatar-file-hint">
            <i class="pi pi-image" style="flex-shrink:0;font-size:0.7rem" />
            <span>{{ avatarFile.name }}</span>
          </div>

          <!-- Change-photo button -->
          <button type="button" class="avatar-change-btn" @click="triggerFileInput">
            <i class="pi pi-camera" />
            <span>{{ avatarFile ? 'Ganti Pilihan' : 'Ganti Foto' }}</span>
          </button>
        </div>

        <!-- ── Vertical divider ───────────────────────────── -->
        <div class="v-divider" />

        <!-- ── Form column ────────────────────────────────── -->
        <form @submit.prevent="handleSave" class="form-col">
          <div class="sfield">
            <label class="sfield-label">
              <i class="pi pi-id-card" /> Nama Lengkap
            </label>
            <InputText
              :modelValue="form.name"
              @update:modelValue="emit('update:name', $event)"
              fluid
              placeholder="Nama lengkap Anda"
            />
          </div>

          <div class="sfield">
            <label class="sfield-label">
              <i class="pi pi-envelope" /> Alamat Email
            </label>
            <InputText
              :modelValue="form.email"
              @update:modelValue="emit('update:email', $event)"
              type="email"
              fluid
              placeholder="Email akun Anda"
            />
          </div>

          <div class="form-action-row">
            <button type="submit" class="sbtn sbtn-primary" :disabled="saving">
              <i v-if="saving" class="pi pi-spin pi-spinner" />
              <i v-else class="pi pi-check" />
              <span>{{ saving ? 'Menyimpan...' : 'Simpan Perubahan' }}</span>
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth.store'

defineProps({
  form:   { type: Object,  required: true },
  saving: { type: Boolean, default: false },
})
const emit = defineEmits(['save', 'update:name', 'update:email'])

const authStore   = useAuthStore()
const avatarInput = ref(null)
const avatarFile  = ref(null)
const avatarPreview = ref(null)

function triggerFileInput() { avatarInput.value?.click() }

function onAvatarChange(e) {
  const file = e.target.files?.[0]
  if (!file) return
  if (file.size > 2 * 1024 * 1024) { alert('Ukuran foto maksimal 2 MB'); return }
  avatarFile.value = file
  const reader = new FileReader()
  reader.onload = ev => { avatarPreview.value = ev.target.result }
  reader.readAsDataURL(file)
}

function handleSave() { emit('save', avatarFile.value) }

/** Called by parent after fetchMe() — clears blob preview, store URL takes over */
function clearPreview() {
  avatarPreview.value = null
  avatarFile.value    = null
  if (avatarInput.value) avatarInput.value.value = ''
}
defineExpose({ clearPreview })
</script>
