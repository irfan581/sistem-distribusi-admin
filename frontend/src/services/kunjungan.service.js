import http from './http'

const BASE = '/admin/kunjungan'

function toFormData(payload) {
  const fd = new FormData()
  Object.entries(payload).forEach(([k, v]) => {
    if (v !== null && v !== undefined) fd.append(k, v)
  })
  return fd
}

export const kunjunganService = {
  list(params = {})   { return http.get(BASE, { params }) },
  salesOptions()      { return http.get(`${BASE}/sales`) },
  show(id)            { return http.get(`${BASE}/${id}`) },
  destroy(id)         { return http.delete(`${BASE}/${id}`) },
  // status: 'diterima' | 'ditolak'; catatan wajib bila ditolak.
  tanggapi(id, payload) { return http.post(`${BASE}/${id}/tanggapan`, payload) },

  store(payload) {
    return http.post(BASE, toFormData(payload), {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  update(id, payload) {
    const fd = toFormData(payload)
    fd.append('_method', 'PUT')
    return http.post(`${BASE}/${id}`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
}
