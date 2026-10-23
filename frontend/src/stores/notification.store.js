import { defineStore } from 'pinia'
import { ref } from 'vue'
import { notificationService } from '@/services/notification.service'

export const useNotificationStore = defineStore('notification', () => {
  const notifications = ref([])
  const unreadCount = ref(0)
  const loading = ref(false)

  async function fetchNotifications() {
    loading.value = true
    try {
      const response = await notificationService.getAll()
      // API returns { success, data: ResourceCollection } which wraps in { data: [...] }
      // Handle both plain array and resource-wrapped { data: [...] }
      const raw = response.data
      notifications.value = Array.isArray(raw) ? raw : (Array.isArray(raw?.data) ? raw.data : [])
    } finally {
      loading.value = false
    }
  }

  async function fetchUnreadCount() {
    try {
      const response = await notificationService.getUnreadCount()
      // Backend returns { success, unread_count: N }
      unreadCount.value = response.unread_count ?? response.data?.count ?? 0
    } catch {
      // silent
    }
  }

  async function markAsRead(id) {
    await notificationService.markAsRead(id)
    const notif = notifications.value.find((n) => n.id === id)
    if (notif) {
      notif.read_at = new Date().toISOString()
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    }
  }

  async function markAllAsRead() {
    await notificationService.markAllAsRead()
    notifications.value.forEach((n) => {
      n.read_at = n.read_at || new Date().toISOString()
    })
    unreadCount.value = 0
  }

  function addNotification(notif) {
    notifications.value.unshift(notif)
    unreadCount.value++
  }

  return {
    notifications,
    unreadCount,
    loading,
    fetchNotifications,
    fetchUnreadCount,
    markAsRead,
    markAllAsRead,
    addNotification,
  }
})
