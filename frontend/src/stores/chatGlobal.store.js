/**
 * chatGlobal.store — badge + banner notif chat global.
 *
 * Arsitektur:
 * - Saat di LUAR ChatView: store subscribe sendiri ke channel chat.user.{id}
 * - Saat di DALAM ChatView: store TIDAK subscribe (useChatRealtime yang pegang channel),
 *   useChatRealtime panggil notifyIncoming() untuk forward pesan ke store
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth.store'
import { getEcho } from '@/composables/useEcho'

export const useChatGlobalStore = defineStore('chatGlobal', () => {
  const chatUnread  = ref(0)
  const globalNotif = ref(null)
  let   notifTimer  = null
  let   dmChannel   = null
  let   isSetup     = false
  let   chatViewActive = false  // true saat ChatView di-mount

  // Kontak yang sedang aktif dibuka di ChatView
  let _activeContactId   = null
  let _activeContactType = null

  function setActiveContact(id, type) {
    _activeContactId   = id   ? Number(id)  : null
    _activeContactType = type || null
  }

  function showNotif(senderName, body, contact) {
    clearTimeout(notifTimer)
    globalNotif.value = { senderName, body, contact }
    notifTimer = setTimeout(() => { globalNotif.value = null }, 5000)
  }

  function dismissNotif() {
    clearTimeout(notifTimer)
    globalNotif.value = null
  }

  // Proses pesan masuk — dipakai internal store maupun dipanggil dari useChatRealtime
  function notifyIncoming(msg) {
    const authStore = useAuthStore()
    const myId      = authStore.user?.id

    if (Number(msg.sender_id) === Number(myId)) return
    if (msg.type === 'group') return

    const senderName  = msg.sender_name || 'Pesan baru'
    const body        = msg.message_type === 'image' ? '📷 Foto'
                      : msg.message_type === 'file'  ? '📎 File'
                      : msg.message_type === 'audio' ? '🎙️ Pesan suara'
                      : msg.message || 'Pesan baru'
    const contactType = (msg.sender_type || '').includes('Sales') ? 'sales' : 'admin'
    const contact     = { id: msg.sender_id, type: contactType, nama: senderName, foto: msg.sender_avatar || null }

    const isActiveChat = _activeContactId   === Number(msg.sender_id) &&
                         _activeContactType === contactType

    if (!isActiveChat) chatUnread.value++

    if (!document.hidden && !isActiveChat) {
      showNotif(senderName, body, contact)
    }
  }

  // Subscribe channel — hanya saat ChatView tidak aktif
  function _subscribeChannel() {
    const authStore = useAuthStore()
    const myId      = authStore.user?.id
    if (!myId) return
    const echo = getEcho()
    if (!echo) return

    dmChannel = echo.private(`chat.user.${myId}`)
    dmChannel.listen('.ChatMessageSent', (data) => {
      notifyIncoming(data.message || data)
    })
  }

  function _unsubscribeChannel() {
    if (dmChannel) {
      dmChannel.stopListening('.ChatMessageSent')
      dmChannel = null
    }
  }

  // Dipanggil dari AdminLayout onMounted
  function setup() {
    if (isSetup) return
    isSetup = true
    if (!chatViewActive) {
      _subscribeChannel()
    }
  }

  // Dipanggil dari ChatView onMounted — store lepas channel, useChatRealtime yang pegang
  function onChatViewEnter() {
    chatViewActive = true
    _unsubscribeChannel()
  }

  // Dipanggil dari ChatView onBeforeUnmount — ambil kembali channel
  function onChatViewLeave() {
    chatViewActive = false
    _activeContactId   = null
    _activeContactType = null
    if (isSetup) {
      _subscribeChannel()
    }
  }

  function teardown() {
    _unsubscribeChannel()
    clearTimeout(notifTimer)
    isSetup           = false
    chatViewActive    = false
    globalNotif.value = null
    chatUnread.value  = 0
    _activeContactId  = null
    _activeContactType = null
  }

  function resetChatUnread() {
    chatUnread.value = 0
  }

  return {
    chatUnread,
    globalNotif,
    setup,
    teardown,
    onChatViewEnter,
    onChatViewLeave,
    notifyIncoming,
    setActiveContact,
    dismissNotif,
    resetChatUnread,
  }
})
