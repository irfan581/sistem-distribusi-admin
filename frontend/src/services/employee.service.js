import http from './http'
import { buildQueryString } from '@/utils/helpers'

export const employeeService = {
  async getAll(params = {}) {
    const query = buildQueryString(params)
    const { data } = await http.get(`/admin/sales${query ? '?' + query : ''}`)
    return data
  },

  async getById(id) {
    const { data } = await http.get(`/admin/sales/${id}`)
    return data
  },

  async create(payload) {
    // payload bisa FormData (dengan foto) atau plain object
    const isFormData = payload instanceof FormData
    const { data } = await http.post('/admin/sales', payload, {
      headers: isFormData ? { 'Content-Type': 'multipart/form-data' } : {},
    })
    return data
  },

  async update(id, payload) {
    const { data } = await http.put(`/admin/sales/${id}`, payload)
    return data
  },

  async updateWithPhoto(id, formData) {
    // Laravel PUT tidak support multipart — pakai POST + _method=PUT
    formData.append('_method', 'PUT')
    const { data } = await http.post(`/admin/sales/${id}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data
  },

  async resetPassword(id, payload) {
    const { data } = await http.put(`/admin/sales/${id}/reset-password`, payload)
    return data
  },

  async destroy(id) {
    const { data } = await http.delete(`/admin/sales/${id}`)
    return data
  },

  async resetDevice(id) {
    const { data } = await http.put(`/admin/sales/${id}/reset-device`)
    return data
  },

  downloadReportUrl(id, dari, sampai, tipe = ['absen', 'toko', 'izin']) {
    const base = import.meta.env.VITE_API_BASE_URL
    const tipeParam = tipe.map(t => `tipe[]=${t}`).join('&')
    return `${base}/admin/sales/${id}/report-pdf?dari=${dari}&sampai=${sampai}&${tipeParam}`
  },
}
