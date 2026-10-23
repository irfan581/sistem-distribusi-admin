<template>
  <RouterView />
</template>

<script setup>
import { onMounted, onBeforeUnmount } from 'vue'
import { useToast } from 'primevue/usetoast'

const toast = useToast()

function onAuthExpired() {
  toast.add({
    severity: 'warn',
    summary: 'Sesi berakhir',
    detail: 'Sesi Anda berakhir, silakan login kembali. Perubahan yang belum disimpan mungkin hilang.',
    life: 4000,
  })
}

onMounted(() => window.addEventListener('auth:expired', onAuthExpired))
onBeforeUnmount(() => window.removeEventListener('auth:expired', onAuthExpired))
</script>
