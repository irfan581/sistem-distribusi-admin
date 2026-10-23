import http from './http'
import { buildQueryString } from '@/utils/helpers'

export const izinService = {
  async getAll(params = {}) {
    const query = buildQueryString(params)
    const { data } = await http.get(`/admin/izin${query ? '?' + query : ''}`)
    return data
  },

  async getRecap(params = {}) {
    const query = buildQueryString(params)
    const { data } = await http.get(`/admin/izin/recap${query ? '?' + query : ''}`)
    return data
  },

  async approve(id, action, alasan_tolak = null) {
    const { data } = await http.post(`/admin/izin/${id}/approve`, { action, alasan_tolak })
    return data
  },

  async deleteIzin(id) {
    const { data } = await http.delete(`/admin/izin/${id}`)
    return data
  },
}
