import http from './http'

export const broadcastService = {
  /** Daftar area unik dari tabel sales */
  async getAreas() {
    const { data } = await http.get('/admin/broadcast/areas')
    return data
  },

  /** Riwayat broadcast yang pernah dikirim */
  async getHistory() {
    const { data } = await http.get('/admin/broadcast/history')
    return data
  },

  /**
   * Kirim broadcast pesan ke sales.
   * @param {Object} payload - { title, message, target: 'all'|'area', areas?: string[] }
   */
  async send(payload) {
    const { data } = await http.post('/admin/broadcast/send', payload)
    return data
  },
}
