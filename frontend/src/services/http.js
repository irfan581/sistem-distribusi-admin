import axios from 'axios'
import { API_BASE_URL, STORAGE_KEYS } from '@/utils/constants'

const http = axios.create({
  baseURL: API_BASE_URL,
  timeout: 30000, // 30 detik — cegah request hang selamanya
  headers: {
    Accept: 'application/json',
  },
})

http.interceptors.request.use((config) => {
  const token = localStorage.getItem(STORAGE_KEYS.TOKEN)
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  // Jika bukan FormData, set Content-Type ke JSON secara eksplisit.
  // Jika FormData, biarkan browser/Axios auto-set multipart/form-data + boundary.
  if (!(config.data instanceof FormData)) {
    config.headers['Content-Type'] = 'application/json'
  }

  // Attach Echo socket ID so Laravel's toOthers() can exclude the sender.
  // Without this header, broadcast(...)->toOthers() falls back to sending
  // to all subscribers including the sender themselves.
  const socketId = window.Echo?.socketId?.()
  if (socketId) {
    config.headers['X-Socket-ID'] = socketId
  }

  return config
})

http.interceptors.response.use(
  (response) => response,
  async (error) => {
    const { response, config } = error

    // Request background/non-kritis (mis. ping heartbeat chat, poll status online)
    // ditandai skipAuthRedirect — sengaja gagal diam-diam, tidak boleh menendang
    // user keluar cuma karena heartbeat kena 401 di tengah dia mengisi form lain.
    if (response?.status === 401 && !config?.skipAuthRedirect) {
      localStorage.removeItem(STORAGE_KEYS.TOKEN)
      localStorage.removeItem(STORAGE_KEYS.USER)
      // Kasih tahu user dulu + kasih jeda sebelum pindah halaman, supaya tidak
      // terasa seperti "tombol tidak berfungsi" saat sesi kadaluarsa di
      // tengah-tengah dia menyimpan sesuatu (mis. form Daftar Muat).
      window.dispatchEvent(new CustomEvent('auth:expired'))
      setTimeout(() => { window.location.href = '/login' }, 1500)
    }

    // 403 tidak auto-redirect — biarkan tiap komponen/service handle sendiri
    // agar error pada endpoint minor (notifikasi, dll) tidak mengusir user dari dashboard

    return Promise.reject(error)
  },
)

export default http
