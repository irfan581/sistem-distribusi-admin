import { ref, nextTick } from 'vue'
import { useAuthStore } from '@/stores/auth.store'
import { chatService } from '@/services/chat.service'
import { getEcho } from '@/composables/useEcho'
import { useChatGlobalStore } from '@/stores/chatGlobal.store'

export function useChatRealtime({ contacts, messages, activeContact, scrollToBottom, pushNotif, isMyMessage, onPinEvent, onReactionEvent }) {
  const authStore       = useAuthStore()
  const chatGlobalStore = useChatGlobalStore()
  const typingUser      = ref(null)

  let typingTimeout      = null
  let typingClearTimeout = null
  let typingNotified     = false
  let dmChannel          = null
  let groupChannel       = null

  function handleTyping(data) {
    const me = authStore.user
    // Abaikan event dari diri sendiri:
    // harus cek user_id DAN user_type — Sales & Admin bisa punya id yang sama
    const senderIsAdmin = (data.user_type || '').includes('User')
    const isSelf = senderIsAdmin && Number(data.user_id) === Number(me?.id)
    if (isSelf) return
    const current = activeContact.value
    if (!current) return

    // Alur typing event di DM:
    //
    //  Sales mengetik → Admin:
    //    Flutter kirim chat_room_id = 'user:{adminId}'
    //    → broadcast ke chat.user.{adminId}
    //    → admin terima di dmChannel (chat.user.{myId})
    //    → isRelevant: pastikan pengirim (user_id) = kontak aktif (current.id)
    //
    //  Admin mengetik → Sales:
    //    Vue kirim chat_room_id = 'sales:{salesId}'
    //    → broadcast ke chat.sales.{salesId}
    //    → TIDAK masuk dmChannel admin, hanya ke Flutter
    //    → admin TIDAK perlu handle ini (bukan untuk dia)
    //
    //  Admin mengetik → Admin lain:
    //    Vue kirim chat_room_id = 'user:{otherAdminId}'
    //    → broadcast ke chat.user.{otherAdminId}
    //    → admin penerima terima di dmChannel-nya
    //    → isRelevant: chat_room_id = 'user:{myId}' DAN pengirim = kontak aktif
    //
    //  Grup: cek room_id cocok saja

    const roomId = data.chat_room_id || ''
    let isRelevant = false

    if (current.type === 'group') {
      isRelevant = roomId === `group:${current.id}`
    } else {
      // Untuk DM, kunci utama: pengirim harus sama dengan kontak yang sedang aktif
      const senderMatchesContact = Number(data.user_id) === Number(current.id)
      isRelevant = senderMatchesContact
    }

    if (!isRelevant) return
    typingUser.value = data.is_typing ? data.user_name : null
    if (data.is_typing) {
      clearTimeout(typingClearTimeout)
      typingClearTimeout = setTimeout(() => { typingUser.value = null }, 3000)
    }
  }

  function setupDmChannel() {
    const myId = authStore.user?.id
    if (!myId) return
    const echo = getEcho()
    if (!echo) return
    dmChannel = echo.private(`chat.user.${myId}`)
    dmChannel.listen('.ChatMessageSent', (data) => {
      const msg               = data.message || data
      if (msg.type === 'group') return
      const currentContact    = activeContact.value
      const senderType        = msg.sender_type || ''
      const senderContactType = senderType.includes('Sales') ? 'sales' : 'admin'
      const isCurrentDm       = currentContact && currentContact.type !== 'group' &&
        currentContact.type === senderContactType && Number(currentContact.id) === Number(msg.sender_id)

      if (isCurrentDm) {
        // Pesan dari kontak yang sedang dibuka — push ke message list
        if (!messages.value.find(m => m.id === msg.id)) {
          messages.value.push(msg)
          nextTick(() => scrollToBottom(true, true))
        }
      } else {
        // Pesan dari kontak lain — update unread badge di sidebar + tampilkan banner
        const list = senderContactType === 'sales' ? contacts.value.sales : contacts.value.admins
        const c    = list.find(c => c.id === msg.sender_id)
        if (c) {
          c.unread       = (c.unread || 0) + 1
          c.last_message = msg.message
          c.last_time    = msg.time || new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' })
        }
        // Forward ke global store untuk banner + badge navbar
        chatGlobalStore.notifyIncoming(msg)
      }
    })
    dmChannel.listen('.ChatMessagesRead', (data) => {
      const me             = authStore.user
      const readerType     = data.reader_type || ''
      const readerTypeSlug = readerType.includes('Sales') ? 'sales' : 'admin'
      const readerId       = data.reader_id
      const current        = activeContact.value
      const readAt         = data.read_at || new Date().toISOString()
      if (current && current.type !== 'group' && current.type === readerTypeSlug && Number(current.id) === Number(readerId)) {
        messages.value.forEach(m => {
          if (Number(m.sender_id) === Number(me?.id) && m.sender_type === 'App\\Models\\User') {
            m.is_read = true
            m.read_at = m.read_at || readAt
          }
        })
      }
    })
    dmChannel.listen('.typing', handleTyping)
    if (onPinEvent) {
      dmChannel.listen('.ChatMessagePinned', (data) => onPinEvent(data))
    }
    if (onReactionEvent) {
      dmChannel.listen('.ChatMessageReacted', (data) => onReactionEvent(data))
    }
  }

  function subscribeGroupChannel(groupId) {
    const echo = getEcho()
    if (!echo) return
    groupChannel = echo.private(`chat.group.${groupId}`)
    groupChannel.listen('.ChatMessageSent', (data) => {
      const msg            = data.message || data
      const currentContact = activeContact.value
      if (currentContact?.type === 'group' && currentContact.id === groupId) {
        if (!messages.value.find(m => m.id === msg.id) && !isMyMessage(msg)) {
          messages.value.push(msg)
          nextTick(() => scrollToBottom(true, true))
          pushNotif(msg.sender_name, msg.message || '📎 File', currentContact)
        }
      } else {
        const g = contacts.value.groups.find(g => g.id === groupId)
        if (g) {
          g.unread       = (g.unread || 0) + 1
          g.last_message = msg.message
          g.last_time    = msg.time || new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' })
          pushNotif(msg.sender_name, msg.message || '📎 File', g)
        }
      }
    })
    groupChannel.listen('.typing', handleTyping)
    if (onPinEvent) {
      groupChannel.listen('.ChatMessagePinned', (data) => onPinEvent(data))
    }
    if (onReactionEvent) {
      groupChannel.listen('.ChatMessageReacted', (data) => onReactionEvent(data))
    }
  }

  function leaveGroupChannel() {
    if (!groupChannel) return
    groupChannel.stopListening('.ChatMessageSent')
    groupChannel.stopListening('.typing')
    if (activeContact.value?.type === 'group') {
      // Use leave() (not leaveChannel) — fully unsubscribes private channel.
      // Do NOT add 'private-' prefix — Echo manages that internally.
      getEcho()?.leave(`chat.group.${activeContact.value.id}`)
    }
    groupChannel = null
  }

  function onTyping(currentContact) {
    if (!currentContact) return
    // roomId must target the RECIPIENT's channel so they receive the typing event:
    //   group  → group:{groupId}   → broadcasts to chat.group.{id}
    //   sales  → sales:{contactId} → broadcasts to chat.sales.{id}  (Flutter app)
    //   admin  → user:{contactId}  → broadcasts to chat.user.{id}   (other admin's channel)
    const roomId = currentContact.type === 'group'
      ? `group:${currentContact.id}`
      : currentContact.type === 'sales'
        ? `sales:${currentContact.id}`
        : `user:${currentContact.id}`
    // Only fire is_typing:true once per 2s — not on every keystroke
    if (!typingNotified) {
      typingNotified = true
      chatService.sendTyping(roomId, true)
    }
    clearTimeout(typingTimeout)
    typingTimeout = setTimeout(() => {
      typingNotified = false
      chatService.sendTyping(roomId, false)
    }, 2000)
  }

  function teardown() {
    if (dmChannel) {
      dmChannel.stopListening('.ChatMessageSent')
      dmChannel.stopListening('.ChatMessagesRead')
      dmChannel.stopListening('.typing')
    }
    if (groupChannel) {
      groupChannel.stopListening('.ChatMessageSent')
      groupChannel.stopListening('.typing')
      if (activeContact.value?.type === 'group') {
        getEcho()?.leave(`chat.group.${activeContact.value.id}`)
      }
    }
    clearTimeout(typingTimeout)
    clearTimeout(typingClearTimeout)
    typingNotified = false
  }

  return {
    typingUser,
    setupDmChannel,
    subscribeGroupChannel,
    leaveGroupChannel,
    onTyping,
    teardown,
  }
}
