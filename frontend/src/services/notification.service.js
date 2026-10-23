import http from './http'

export const notificationService = {
  async getAll(params = {}) {
    const { data } = await http.get('/admin/notifications', { params })
    return data
  },

  async getUnreadCount() {
    const { data } = await http.get('/admin/notifications/unread-count')
    return data
  },

  async markAsRead(id) {
    const { data } = await http.post(`/admin/notifications/${id}/read`)
    return data
  },

  async markAllAsRead() {
    const { data } = await http.post('/admin/notifications/read-all')
    return data
  },
}
