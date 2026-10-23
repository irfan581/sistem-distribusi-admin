<template>
  <Dialog v-model:visible="visible" header="Reset Password Admin" modal style="width: 400px" @hide="reset">
    <div class="field">
      <label>Password Baru <span class="req">*</span></label>
      <Password v-model="form.password" toggleMask :feedback="false" placeholder="Min. 6 karakter" class="w-full" inputClass="w-full" />
      <small class="err" v-if="errors.password">{{ errors.password }}</small>
    </div>
    <div class="field">
      <label>Ulangi Password <span class="req">*</span></label>
      <Password v-model="form.password_confirmation" toggleMask :feedback="false" placeholder="Ulangi password" class="w-full" inputClass="w-full" />
    </div>

    <template #footer>
      <Button label="Batal" severity="secondary" text @click="visible = false" />
      <Button label="Reset Password" icon="pi pi-key" severity="warning" :loading="saving" @click="submit" />
    </template>
  </Dialog>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { adminUserService } from '@/services/adminUser.service'
import { useToast } from '@/composables/useToast'

const emit = defineEmits(['saved'])
const toast = useToast()
const visible = ref(false)
const saving = ref(false)
const userId = ref(null)
const errors = ref({})

const form = reactive({ password: '', password_confirmation: '' })

function reset() {
  form.password = ''
  form.password_confirmation = ''
  errors.value = {}
}

function open(id) {
  userId.value = id
  reset()
  visible.value = true
}
defineExpose({ open })

async function submit() {
  errors.value = {}
  saving.value = true
  try {
    await adminUserService.resetPassword(userId.value, {
      password: form.password,
      password_confirmation: form.password_confirmation,
    })
    toast.success('Password berhasil direset')
    visible.value = false
    emit('saved')
  } catch (err) {
    const data = err.response?.data
    if (data?.errors) {
      errors.value = Object.fromEntries(
        Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
      )
    } else {
      toast.error(data?.message || 'Gagal mereset password')
    }
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.field { display: flex; flex-direction: column; gap: 0.3rem; margin-bottom: 0.85rem; }
.field label { font-size: 0.82rem; font-weight: 600; }
.req { color: var(--c-danger); }
.err { color: var(--c-danger); font-size: 0.75rem; }
</style>
