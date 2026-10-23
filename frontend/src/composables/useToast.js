import { useToast as usePrimeToast } from 'primevue/usetoast'

export function useToast() {
  const toast = usePrimeToast()

  function success(message, title = 'Berhasil', life = 3000) {
    toast.add({ severity: 'success', summary: title, detail: message, life })
  }

  function error(message, title = 'Error', life = 5000) {
    toast.add({ severity: 'error', summary: title, detail: message, life })
  }

  function warn(message, title = 'Perhatian', life = 4000) {
    toast.add({ severity: 'warn', summary: title, detail: message, life })
  }

  function info(message, title = 'Info', life = 3000) {
    toast.add({ severity: 'info', summary: title, detail: message, life })
  }

  function apiError(err) {
    const message =
      err?.response?.data?.message ||
      err?.response?.data?.error ||
      err?.message ||
      'Terjadi kesalahan, silakan coba lagi.'
    error(message)
  }

  return { success, error, warn, info, apiError }
}
