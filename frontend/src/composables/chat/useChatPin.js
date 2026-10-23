import { ref, watch } from 'vue'
import { chatService } from '@/services/chat.service'

/**
 * Mengelola pin pesan untuk chat aktif.
 * - Fetch pin saat kontak berubah
 * - pinMessage / unpinMessage dengan optimistic update
 * - Terima realtime event ChatMessagePinned dari useChatRealtime
 */
export function useChatPin(activeContact) {
  const pinnedMessage = ref(null)
  const pinLoading    = ref(false)

  async function fetchPin(contact) {
    if (!contact) { pinnedMessage.value = null; return }

    pinLoading.value = true
    try {
      const params = contact.type === 'group'
        ? { group_id: contact.id }
        : { contact_id: contact.id, contact_type: contact.type }

      const res = await chatService.getPinnedMessage(params)
      pinnedMessage.value = res.data ?? null
    } catch {
      pinnedMessage.value = null
    } finally {
      pinLoading.value = false
    }
  }

  async function togglePin(msg) {
    if (!msg) return
    const alreadyPinned = pinnedMessage.value?.message_id === msg.id

    if (alreadyPinned) {
      // Optimistic unpin
      pinnedMessage.value = null
      try {
        await chatService.unpinMessage(msg.id)
      } catch {
        // revert — refetch
        await fetchPin(activeContact.value)
      }
    } else {
      // Optimistic pin — show preview immediately
      pinnedMessage.value = {
        message_id:   msg.id,
        message:      msg.message,
        message_type: msg.message_type,
        file_url:     msg.file_url,
        file_name:    msg.file_name,
        sender_name:  msg.sender_name,
      }
      try {
        const res = await chatService.pinMessage(msg.id)
        pinnedMessage.value = res.data ?? pinnedMessage.value
      } catch {
        await fetchPin(activeContact.value)
      }
    }
  }

  /** Called by useChatRealtime when it receives ChatMessagePinned broadcast */
  function onPinEvent(data) {
    pinnedMessage.value = data.pinned ?? null
  }

  // Auto-fetch when active contact changes
  watch(activeContact, (contact) => fetchPin(contact), { immediate: true })

  return { pinnedMessage, pinLoading, togglePin, onPinEvent, fetchPin }
}
