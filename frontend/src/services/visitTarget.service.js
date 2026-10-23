import http from './http'

export const visitTargetService = {
  async getAll(params = {}) {
    const query = new URLSearchParams()
    if (params.search)    query.set('search', params.search)
    if (params.is_active !== undefined) query.set('is_active', params.is_active ? 1 : 0)
    const qs = query.toString()
    const { data } = await http.get(`/admin/visit-targets${qs ? '?' + qs : ''}`)
    return data
  },

  async getAnalytics() {
    const { data } = await http.get('/admin/visit-targets/analytics')
    return data
  },

  async getOne(salesId) {
    const { data } = await http.get(`/admin/visit-targets/${salesId}`)
    return data
  },

  async getHistory(salesId, limit = 20) {
    const { data } = await http.get(`/admin/visit-targets/${salesId}/history?limit=${limit}`)
    return data
  },

  async update(salesId, target, reason = null) {
    const { data } = await http.put(`/admin/visit-targets/${salesId}`, { target, reason })
    return data
  },

  async bulkUpdate(updates, reason = null) {
    const { data } = await http.post('/admin/visit-targets/bulk', { updates, reason })
    return data
  },

  async reset(salesId, defaultVal = 5) {
    const { data } = await http.post(`/admin/visit-targets/${salesId}/reset`, { default: defaultVal })
    return data
  },
}
