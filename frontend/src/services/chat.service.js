import http from './http'

export const chatService = {
  async getContacts() {
    const { data } = await http.get('/admin/chat/contacts')
    return data
  },
  async getDmMessages(userId, type = 'sales', beforeId = null) {
    const params = { type }
    if (beforeId) params.before_id = beforeId
    const { data } = await http.get(`/admin/chat/dm/${userId}`, { params })
    return data
  },
  async getGroups() {
    const { data } = await http.get('/admin/chat/groups')
    return data
  },
  async getGroupMessages(groupId, beforeId = null) {
    const params = {}
    if (beforeId) params.before_id = beforeId
    const { data } = await http.get(`/admin/chat/groups/${groupId}/messages`, { params })
    return data
  },
  async sendMessage(payload) {
    const { data } = await http.post('/admin/chat/send', payload)
    return data
  },
  async sendTyping(chatRoomId, isTyping) {
    await http.post('/admin/chat/typing', { chat_room_id: chatRoomId, is_typing: isTyping }).catch(() => {})
  },
  async getUnreadCount() {
    const { data } = await http.get('/admin/chat/unread')
    return data
  },
  async markRead(messageId) {
    await http.post(`/admin/chat/messages/${messageId}/read`).catch(() => {})
  },
  async editMessage(messageId, message) {
    const { data } = await http.put(`/admin/chat/messages/${messageId}`, { message })
    return data
  },
  async deleteMessage(messageId) {
    await http.delete(`/admin/chat/messages/${messageId}`)
  },

  // ── Online status ─────────────────────────────────────────────
  // skipAuthRedirect: heartbeat/poll background — gagal diam-diam, jangan
  // sampai menendang user keluar dari halaman lain yang sedang dia kerjakan.
  async ping() {
    await http.post('/admin/chat/ping', {}, { skipAuthRedirect: true }).catch(() => {})
  },
  async getOnlineStatus(contacts) {
    // contacts: [{ id, type }]
    const { data } = await http.get('/admin/chat/online-status', {
      params: { contacts },
      skipAuthRedirect: true,
    })
    return data
  },

  // ── Search ───────────────────────────────────────────────────
  async searchMessages(q, contactId = null, contactType = null) {
    const params = { q }
    if (contactId)   params.contact_id   = contactId
    if (contactType) params.contact_type = contactType
    const { data } = await http.get('/admin/chat/search', { params })
    return data
  },

  // ── Star/Bookmark ─────────────────────────────────────────────
  async starMessage(id) {
    const { data } = await http.post(`/admin/chat/messages/${id}/star`)
    return data
  },
  async unstarMessage(id) {
    const { data } = await http.delete(`/admin/chat/messages/${id}/star`)
    return data
  },
  async getStarredMessages() {
    const { data } = await http.get('/admin/chat/starred')
    return data
  },

  // ── Link Preview ────────────────────────────────────────
  async getLinkPreview(url) {
    const { data } = await http.get('/admin/chat/link-preview', { params: { url } })
    return data
  },

  // ── FCM Token ───────────────────────────────────────────
  async saveFcmToken(token) {
    await http.post('/admin/chat/fcm-token', { token }).catch(() => {})
  },

  // ── Forward Pesan ───────────────────────────────────────
  async forwardMessage(id, targets) {
    // targets: [{ type: 'sales'|'admin'|'group', id: number }]
    const { data } = await http.post(`/admin/chat/messages/${id}/forward`, { targets })
    return data
  },

  // ── Reaction Emoji ───────────────────────────────────────
  async reactMessage(id, emoji) {
    // Toggle: POST sama endpoint untuk add/remove
    const { data } = await http.post(`/admin/chat/messages/${id}/react`, { emoji })
    return data
  },

  // ── Pin Pesan ─────────────────────────────────────────────
  async getPinnedMessage(params) {
    // params: { contact_id, contact_type } OR { group_id }
    const { data } = await http.get('/admin/chat/pinned', { params })
    return data
  },
  async pinMessage(id) {
    const { data } = await http.post(`/admin/chat/messages/${id}/pin`)
    return data
  },
  async unpinMessage(id) {
    await http.delete(`/admin/chat/messages/${id}/pin`)
  },

  // ── Group create ─────────────────────────────────────────────
  async createGroup(nama, memberIds = []) {
    const { data } = await http.post('/admin/chat/groups', { nama, member_ids: memberIds })
    return data
  },

  // ── Group info ────────────────────────────────────────────────
  async getGroupInfo(groupId) {
    const { data } = await http.get(`/admin/chat/groups/${groupId}/info`)
    return data
  },
  async addGroupMember(groupId, salesId) {
    const { data } = await http.post(`/admin/chat/groups/${groupId}/members`, { sales_id: salesId })
    return data
  },
  async removeGroupMember(groupId, memberId) {
    const { data } = await http.delete(`/admin/chat/groups/${groupId}/members/${memberId}`)
    return data
  },
  async renameGroup(groupId, nama) {
    const { data } = await http.put(`/admin/chat/groups/${groupId}/rename`, { nama })
    return data
  },
}
