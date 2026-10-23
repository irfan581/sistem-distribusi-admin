import { ref } from 'vue'
import { useToast } from '@/composables/useToast'

const MAX_FILE_MB = 10

export function useChatUpload() {
  const toast         = useToast()
  const fileInputRef  = ref(null)
  const imageInputRef = ref(null)
  const filePreview   = ref(null)

  function formatBytes(bytes) {
    if (bytes < 1024)        return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
  }

  function onFileSelected(e) {
    const file = e.target.files?.[0]
    e.target.value = ''
    if (!file) return
    if (file.size > MAX_FILE_MB * 1024 * 1024) {
      toast.add({ severity: 'warn', summary: 'File terlalu besar', detail: `Maksimum ${MAX_FILE_MB}MB`, life: 4000 })
      return
    }
    const isImage = file.type.startsWith('image/')
    if (isImage) {
      const reader = new FileReader()
      reader.onload = (ev) => {
        filePreview.value = { file, name: file.name, sizeLabel: formatBytes(file.size), isImage: true, dataUrl: ev.target.result }
      }
      reader.readAsDataURL(file)
    } else {
      filePreview.value = { file, name: file.name, sizeLabel: formatBytes(file.size), isImage: false, dataUrl: null }
    }
  }

  function clearFilePreview() {
    filePreview.value = null
  }

  return {
    fileInputRef,
    imageInputRef,
    filePreview,
    onFileSelected,
    clearFilePreview,
  }
}
