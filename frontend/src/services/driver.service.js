import http from './http'

export const driverService = {
  async getAll(params = {}) {
    const query = new URLSearchParams()
    if (params.search) query.set('search', params.search)
    if (params.status) query.set('status', params.status)
    const qs = query.toString()
    const { data } = await http.get(`/admin/pengiriman/drivers${qs ? '?' + qs : ''}`)
    return data
  },

  async store(payload) {
    const { data } = await http.post('/admin/pengiriman/drivers', payload)
    return data
  },

  async update(id, payload) {
    const { data } = await http.put(`/admin/pengiriman/drivers/${id}`, payload)
    return data
  },

  async destroy(id) {
    const { data } = await http.delete(`/admin/pengiriman/drivers/${id}`)
    return data
  },

  // Akun aplikasi driver. Password sementara hanya ada di respons ini —
  // tidak tersimpan di mana pun dan tidak bisa dilihat lagi setelahnya.
  async aktifkanAkun(id) {
    const { data } = await http.post(`/admin/pengiriman/drivers/${id}/akun/aktifkan`)
    return data
  },

  async resetPassword(id) {
    const { data } = await http.post(`/admin/pengiriman/drivers/${id}/akun/reset-password`)
    return data
  },

  async nonaktifkanAkun(id) {
    const { data } = await http.post(`/admin/pengiriman/drivers/${id}/akun/nonaktifkan`)
    return data
  },
}
