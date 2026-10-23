/**
 * useChatGlobal — global chat listener aktif di seluruh halaman (mount di AdminLayout).
 * Hanya subscribe DM channel milik admin yang login.
 * Saat pesan masuk: update unread count + tampilkan notif banner global.
 *
 * Tidak subscribe group channel (group hanya aktif saat buka ChatView).
 */
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth.store'
import { getEcho } from '@/composables/useEcho'

// Singleton — hanya satu instance aktif di seluruh app
let globalDmChannel = null
let isSetup         = false

export function useChatGlobal() {
  const authStore     = useAuthStore()
  const globalUnread  = ref(0)
  const globalNotif   = ref(null)   // { id, senderName, body, contact, ts }
  let   notifTimer    = null

  function showGlobalNotif(senderName, body, contact) {
    clearTimeout(notifTimer)
    globalNotif.value = { id: Date.now(), senderName, body, contact }
    notifTimer = setTimeout(() => { globalNotif.value = null }, 5000)
  }

  function dismissNotif() {
    clearTimeout(notifTimer)
    globalNotif.value = null
  }

  /**
   * Dipanggil dari AdminLayout onMounted.
   * Callback onMessage(senderName, body, contact) dipanggil saat pesan masuk
   * dari luar ChatView — untuk update badge navbar, dll.
   */
  function setupGlobal(onMessage) {
    if (isSetup) return
    const myId = authStore.user?.id
    if (!myId) return
    const echo = getEcho()
    if (!echo) return

    isSetup = true
    globalDmChannel = echo.private(`chat.user.${myId}`)

    globalDmChannel.listen('.ChatMessageSent', (data) => {
      const msg = data.message || data
      if (msg.type === 'group') return

      // Kalau halaman /chat sedang buka, biarkan ChatView yang handle
      // (ChatView punya listener sendiri + isCurrentDm logic)
      // Kita tetap update badge dan tunjukkan notif global jika tab visible
      const isOnChatPage = window.location.pathname === '/chat'

      const senderName = msg.sender_name || 'Pesan baru'
      const body       = msg.message    || '📎 File'

      // Tentukan contact object untuk navigasi klik notif
      const senderType = msg.sender_type || ''
      const contactType = senderType.includes('Sales') ? 'sales' : 'admin'
      const contact = {
        id:   msg.sender_id,
        type: contactType,
        nama: senderName,
      }

      // Tambah unread badge global
      globalUnread.value++

      // Panggil callback (untuk update navbar badge)
      if (onMessage) onMessage(senderName, body, contact)

      // Browser notification — kalau tab hidden
      if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
        const n = new Notification(senderName, {
          body: body.length > 80 ? body.slice(0, 80) + '…' : body,
          icon: '/logo-icon.png',
          tag:  `global-chat-${contactType}-${msg.sender_id}`,
        })
        n.onclick = () => {
          window.focus()
          window.location.href = '/chat'
          n.close()
        }
      }

      // In-app banner — kalau tab visible dan tidak sedang di halaman chat
      if (!document.hidden && !isOnChatPage) {
        showGlobalNotif(senderName, body, contact)
      }
    })
  }

  function teardownGlobal() {
    if (globalDmChannel) {
      globalDmChannel.stopListening('.ChatMessageSent')
      globalDmChannel = null
    }
    clearTimeout(notifTimer)
    isSetup        = false
    globalNotif.value = null
    globalUnread.value = 0
  }

  function resetUnread() {
    globalUnread.value = 0
  }

  return {
    globalUnread,
    globalNotif,
    setupGlobal,
    teardownGlobal,
    dismissNotif,
    resetUnread,
  }
}
