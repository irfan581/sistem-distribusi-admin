import { ref, onMounted, onBeforeUnmount } from 'vue'
import { chatService } from '@/services/chat.service'

/**
 * Ping backend setiap 30 detik agar last_seen_at ter-update.
 * Poll status online kontak yang sedang aktif setiap 30 detik.
 */
export function useChatOnlineStatus(contacts) {
  // Map: "admin-5" | "sales-3" → { is_online, last_seen_at }
  const onlineMap = ref({})

  let pingInterval   = null
  let pollInterval   = null

  // Kirim ping — update last_seen_at user yang login
  async function sendPing() {
    await chatService.ping()
  }

  // Poll status online semua kontak yang ada di sidebar
  async function pollOnlineStatus() {
    const list = [
      ...(contacts.value?.admins || []).map(c => ({ id: c.id, type: 'admin' })),
      ...(contacts.value?.sales  || []).map(c => ({ id: c.id, type: 'sales' })),
    ]
    if (!list.length) return

    try {
      const res = await chatService.getOnlineStatus(list)
      const data = res.data || {}
      // Update onlineMap dan sekaligus update contacts langsung
      onlineMap.value = data

      // Patch is_online ke objek kontak reaktif agar sidebar ikut update
      ;(contacts.value?.admins || []).forEach(c => {
        const key = `admin-${c.id}`
        if (data[key] !== undefined) {
          c.is_online    = data[key].is_online
          c.last_seen_at = data[key].last_seen_at
        }
      })
      ;(contacts.value?.sales || []).forEach(c => {
        const key = `sales-${c.id}`
        if (data[key] !== undefined) {
          c.is_online    = data[key].is_online
          c.last_seen_at = data[key].last_seen_at
        }
      })
    } catch { /* silent */ }
  }

  function start() {
    sendPing()
    pollOnlineStatus()
    pingInterval = setInterval(sendPing, 30_000)
    pollInterval = setInterval(pollOnlineStatus, 30_000)
  }

  function stop() {
    clearInterval(pingInterval)
    clearInterval(pollInterval)
  }

  onMounted(start)
  onBeforeUnmount(stop)

  // Format "terakhir dilihat X menit lalu"
  function formatLastSeen(isoStr) {
    if (!isoStr) return ''
    const diff = Math.floor((Date.now() - new Date(isoStr).getTime()) / 1000)
    if (diff < 60)       return 'baru saja'
    if (diff < 3600)     return `${Math.floor(diff / 60)} menit lalu`
    if (diff < 86400)    return `${Math.floor(diff / 3600)} jam lalu`
    return `${Math.floor(diff / 86400)} hari lalu`
  }

  return { onlineMap, formatLastSeen, pollOnlineStatus }
}
