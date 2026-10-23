import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth.store'

export function useChatMessages() {
  const authStore       = useAuthStore()
  const messages        = ref([])
  const messagesLoading = ref(false)
  const loadingMore     = ref(false)
  const hasMore         = ref(false)
  const oldestId        = ref(null)
  const messageAreaRef  = ref(null)
  const showScrollBtn   = ref(false)

  function isMyMessage(msg) {
    const me = authStore.user
    if (!me) return false
    // Gunakan == bukan === karena sender_id dari API bisa string, me.id bisa number
    return Number(msg.sender_id) === Number(me.id) && msg.sender_type === 'App\\Models\\User'
  }

  function showSenderName(idx) {
    if (idx === 0) return true
    const prev = messages.value[idx - 1]
    const curr = messages.value[idx]
    return prev.sender_id !== curr.sender_id || prev.sender_type !== curr.sender_type
  }

  function isNearBottom() {
    const el = messageAreaRef.value
    if (!el) return true
    return el.scrollHeight - el.scrollTop - el.clientHeight < 200
  }

  function scrollToBottom(smooth = false, force = false) {
    const el = messageAreaRef.value
    if (!el) return
    // Kalau user sedang scroll ke atas baca pesan lama, jangan paksa scroll
    // kecuali force=true (misal saat kirim pesan sendiri)
    if (!force && !isNearBottom()) return
    el.scrollTo({ top: el.scrollHeight, behavior: smooth ? 'smooth' : 'instant' })
    showScrollBtn.value = false
  }

  function onMessageScroll() {
    const el = messageAreaRef.value
    if (!el) return
    showScrollBtn.value = el.scrollHeight - el.scrollTop - el.clientHeight > 120
  }

  function dayKey(dateStr) {
    if (!dateStr) return ''
    return new Date(dateStr).toDateString()
  }

  function showDateSeparator(idx) {
    if (idx === 0) return true
    return dayKey(messages.value[idx].created_at) !== dayKey(messages.value[idx - 1].created_at)
  }

  function formatDateLabel(dateStr) {
    if (!dateStr) return ''
    const d         = new Date(dateStr)
    const today     = new Date()
    const yesterday = new Date(today)
    yesterday.setDate(today.getDate() - 1)
    if (d.toDateString() === today.toDateString())     return 'Hari ini'
    if (d.toDateString() === yesterday.toDateString()) return 'Kemarin'
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
  }

  // Lightbox state
  const lightboxUrl = ref(null)

  function openImage(url) {
    lightboxUrl.value = url
  }

  function closeLightbox() {
    lightboxUrl.value = null
  }

  async function downloadImage(url) {
    try {
      const res  = await fetch(url)
      const blob = await res.blob()
      const a    = document.createElement('a')
      a.href     = URL.createObjectURL(blob)
      a.download = url.split('/').pop() || 'foto-chat.jpg'
      a.click()
      URL.revokeObjectURL(a.href)
    } catch {
      window.open(url, '_blank')
    }
  }

  return {
    messages,
    messagesLoading,
    loadingMore,
    hasMore,
    oldestId,
    messageAreaRef,
    showScrollBtn,
    lightboxUrl,
    isMyMessage,
    showSenderName,
    scrollToBottom,
    onMessageScroll,
    showDateSeparator,
    formatDateLabel,
    openImage,
    closeLightbox,
    downloadImage,
  }
}
