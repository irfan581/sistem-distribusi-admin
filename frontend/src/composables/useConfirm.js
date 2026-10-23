import { useConfirm as usePrimeConfirm } from 'primevue/useconfirm'

export function useConfirm() {
  const confirm = usePrimeConfirm()

  function confirmDelete(target, onAccept, message = 'Data yang dihapus tidak dapat dikembalikan.') {
    confirm.require({
      target,
      message,
      header: 'Konfirmasi Hapus',
      icon: 'pi pi-exclamation-triangle',
      rejectProps: { label: 'Batal', severity: 'secondary', outlined: true },
      acceptProps: { label: 'Hapus', severity: 'danger' },
      accept: onAccept,
    })
  }

  function confirmAction(options) {
    confirm.require({
      header: 'Konfirmasi',
      icon: 'pi pi-question-circle',
      rejectProps: { label: 'Batal', severity: 'secondary', outlined: true },
      acceptProps: { label: 'Ya, Lanjutkan', severity: 'primary' },
      ...options,
    })
  }

  return { confirmDelete, confirmAction }
}
