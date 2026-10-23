/**
 * useChatPolling — fallback polling when WebSocket (Reverb) is not available.
 * Polls the active conversation every 3 seconds and merges new messages.
 */
import { ref } from 'vue'
import { chatService } from '@/services/chat.service'
import { getEcho }     from '@/composables/useEcho'

export function useChatPolling({ activeContact, messages, scrollToBottom, isMyMessage }) {
  let pollTimer    = null
  let _isFetching  = false  // guard — cegah request overlap
  const isPolling  = ref(false)

  function isEchoConnected() {
    try {
      const echo = getEcho()
      if (!echo) return false
      const state = echo.connector?.pusher?.connection?.state
      return state === 'connected'
    } catch {
      return false
    }
  }

  async function doPoll() {
    if (!activeContact.value || _isFetching) return
    // Jangan poll jika WebSocket sedang connected — hemat bandwidth
    if (isEchoConnected()) return

    _isFetching = true
    try {
      let res
      if (activeContact.value.type === 'group') {
        res = await chatService.getGroupMessages(activeContact.value.id)
      } else {
        res = await chatService.getDmMessages(activeContact.value.id, activeContact.value.type)
      }
      const fetched = res.data || []
      if (!Array.isArray(fetched)) return

      let added = false
      for (const msg of fetched) {
        if (isMyMessage(msg)) continue
        if (!messages.value.find(m => m.id === msg.id)) {
          messages.value.push(msg)
          added = true
        }
      }
      if (added) scrollToBottom(true)
    } catch {
      // silent — jangan spam error saat polling
    } finally {
      _isFetching = false
    }
  }

  function startPolling() {
    stopPolling()
    // Jangan start polling jika WebSocket sudah connected
    if (isEchoConnected()) return
    isPolling.value = true
    // 8 detik — cukup untuk fallback, tidak terlalu agresif
    pollTimer = setInterval(doPoll, 8000)
  }

  function stopPolling() {
    isPolling.value = false
    _isFetching     = false
    if (pollTimer) {
      clearInterval(pollTimer)
      pollTimer = null
    }
  }

  return { startPolling, stopPolling, isPolling }
}
