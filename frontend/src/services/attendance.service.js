import http from './http'
import { buildQueryString } from '@/utils/helpers'

export const attendanceService = {
  async getAll(params = {}) {
    const query = buildQueryString(params)
    const { data } = await http.get(`/admin/presensi${query ? '?' + query : ''}`)
    return data
  },

  async getById(id) {
    const { data } = await http.get(`/admin/presensi/${id}`)
    return data
  },
}
