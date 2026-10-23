import http from './http'

export const adminUserService = {
  list(params = {}) {
    return http.get('/admin/admin-users', { params })
  },

  show(id) {
    return http.get(`/admin/admin-users/${id}`)
  },

  roles() {
    return http.get('/admin/admin-users/roles')
  },

  store(formData) {
    return http.post('/admin/admin-users', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  update(id, formData) {
    // Gunakan POST murni — route backend sudah Route::post untuk update
    return http.post(`/admin/admin-users/${id}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  resetPassword(id, payload) {
    return http.put(`/admin/admin-users/${id}/password`, payload)
  },

  destroy(id) {
    return http.delete(`/admin/admin-users/${id}`)
  },
}
