import http from './http'

export const kendaraanService = {
  async getAll(params = {}) {
    const query = new URLSearchParams()
    if (params.search) query.set('search', params.search)
    if (params.status) query.set('status', params.status)
    const qs = query.toString()
    const { data } = await http.get(`/admin/pengiriman/kendaraans${qs ? '?' + qs : ''}`)
    return data
  },

  async store(payload) {
    const { data } = await http.post('/admin/pengiriman/kendaraans', payload)
    return data
  },

  async update(id, payload) {
    const { data } = await http.put(`/admin/pengiriman/kendaraans/${id}`, payload)
    return data
  },

  async destroy(id) {
    const { data } = await http.delete(`/admin/pengiriman/kendaraans/${id}`)
    return data
  },
}
