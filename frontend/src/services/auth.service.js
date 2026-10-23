import http from './http'

export const authService = {
  async login(credentials) {
    try {
      // skipAuthRedirect: a failed login attempt returns 401 too, but it's
      // not an expired session — without this the global interceptor in
      // http.js hard-reloads the page 1.5s later, wiping the error/shake
      // state right after it briefly appears.
      const res = await http.post('/admin/login', credentials, { skipAuthRedirect: true })
      // API shape: { success, message, data: { access_token, user } }
      return res.data?.data ?? res.data
    } catch (err) {
      throw err
    }
  },

  async logout() {
    const { data } = await http.post('/admin/logout')
    return data
  },

  async me() {
    const { data } = await http.get('/admin/me')
    return data
  },

  async updateProfile(payload) {
    const { data } = await http.put('/admin/profile', payload)
    return data
  },

  async updateProfileWithAvatar(formData) {
    const { data } = await http.post('/admin/profile', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data
  },

  async changePassword(payload) {
    const { data } = await http.put('/admin/profile/password', payload)
    return data
  },
}
