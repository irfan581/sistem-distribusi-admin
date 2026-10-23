<template>
  <div class="settings-view">

    <!-- Page Header -->
    <div class="settings-page-header">
      <h1 class="settings-page-title">Pengaturan</h1>
      <p class="settings-page-sub">Kelola profil, keamanan, dan preferensi akun Anda</p>
    </div>

    <!-- Card Grid -->
    <div class="settings-grid">

      <!-- ROW 1: Profile (full width) -->
      <SettingsProfileCard
        ref="profileCardRef"
        :form="profileForm"
        :saving="savingProfile"
        @save="saveProfile"
        @update:name="profileForm.name = $event"
        @update:email="profileForm.email = $event"
      />

      <!-- ROW 2 col-1: Password -->
      <SettingsPasswordCard
        :form="passwordForm"
        :saving="savingPassword"
        @save="changePassword"
        @update:currentPassword="passwordForm.current_password = $event"
        @update:newPassword="passwordForm.new_password = $event"
        @update:confirmPassword="passwordForm.new_password_confirmation = $event"
      />

      <!-- ROW 2 col-2: Tampilan -->
      <SettingsAppearanceCard
        :darkMode="appStore.darkMode"
        @toggleDark="appStore.toggleDarkMode()"
      />

      <!-- ROW 2 col-3: Informasi Aplikasi -->
      <SettingsAppInfoCard />

    </div>
  </div>
</template>

<script setup>
import '@/assets/css/settings.css'
import { ref, reactive } from 'vue'
import { useAuthStore }  from '@/stores/auth.store'
import { useAppStore }   from '@/stores/app.store'
import { useToast }      from '@/composables/useToast'
import { authService }   from '@/services/auth.service'
import SettingsProfileCard    from '@/components/settings/SettingsProfileCard.vue'
import SettingsPasswordCard   from '@/components/settings/SettingsPasswordCard.vue'
import SettingsAppearanceCard from '@/components/settings/SettingsAppearanceCard.vue'
import SettingsAppInfoCard    from '@/components/settings/SettingsAppInfoCard.vue'

const authStore = useAuthStore()
const appStore  = useAppStore()
const toast     = useToast()

const profileCardRef = ref(null)
const savingProfile  = ref(false)
const savingPassword = ref(false)

const profileForm = reactive({
  name:  authStore.user?.name  || '',
  email: authStore.user?.email || '',
})

const passwordForm = reactive({
  current_password:          '',
  new_password:              '',
  new_password_confirmation: '',
})

async function saveProfile(avatarFile) {
  savingProfile.value = true
  try {
    if (avatarFile) {
      const fd = new FormData()
      fd.append('name',    profileForm.name)
      fd.append('email',   profileForm.email)
      fd.append('avatar',  avatarFile)
      fd.append('_method', 'PUT')
      await authService.updateProfileWithAvatar(fd)
    } else {
      await authService.updateProfile(profileForm)
    }
    // Refresh store → sidebar, navbar, settings card all update instantly
    await authStore.fetchMe()
    profileForm.name  = authStore.user?.name  || ''
    profileForm.email = authStore.user?.email || ''
    // Clear local blob preview → server avatar URL now takes over
    profileCardRef.value?.clearPreview()
    toast.success('Profil berhasil diperbarui.')
  } catch (err) {
    toast.apiError(err)
  } finally {
    savingProfile.value = false
  }
}

async function changePassword() {
  if (passwordForm.new_password !== passwordForm.new_password_confirmation) {
    toast.error('Konfirmasi password tidak sesuai.')
    return
  }
  savingPassword.value = true
  try {
    await authService.changePassword(passwordForm)
    Object.assign(passwordForm, {
      current_password: '',
      new_password: '',
      new_password_confirmation: '',
    })
    toast.success('Password berhasil diubah.')
  } catch (err) {
    toast.apiError(err)
  } finally {
    savingPassword.value = false
  }
}
</script>
