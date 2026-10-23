import http from './http'

const BASE = '/admin/toko'

export const tokoService = {
  list(params = {}) {
    return http.get(BASE, { params })
  },

  wilayahList() {
    return http.get(`${BASE}/wilayah`)
  },

  salesOptions() {
    return http.get(`${BASE}/sales`)
  },

  store(payload) {
    return http.post(BASE, payload)
  },

  update(id, payload) {
    return http.put(`${BASE}/${id}`, payload)
  },

  destroy(id) {
    return http.delete(`${BASE}/${id}`)
  },

  import(file) {
    const fd = new FormData()
    fd.append('file', file)
    return http.post(`${BASE}/import`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
}
