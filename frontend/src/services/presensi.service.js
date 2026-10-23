import http from './http'

const BASE = '/admin/presensi'

export const presensiService = {
  list(params = {}) {
    return http.get(BASE, { params })
  },

  show(id) {
    return http.get(`${BASE}/${id}`)
  },

  update(id, payload) {
    return http.put(`${BASE}/${id}`, payload)
  },

  destroy(id) {
    return http.delete(`${BASE}/${id}`)
  },
}
