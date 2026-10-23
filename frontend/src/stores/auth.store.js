import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authService } from '@/services/auth.service'
import { destroyEcho } from '@/composables/useEcho'
import { STORAGE_KEYS } from '@/utils/constants'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(JSON.parse(localStorage.getItem(STORAGE_KEYS.USER) || 'null'))
  const token = ref(localStorage.getItem(STORAGE_KEYS.TOKEN))
  const loading = ref(false)
  // Track apakah fetchMe sudah pernah dijalankan di session ini
  const userResolved = ref(false)

  const isAuthenticated = computed(() => !!token.value && !!user.value)

  /** Cek permission — owner (role_nama === 'owner') bypass semua */
  /** Cek permission — owner & developer bypass semua */
  function hasPermission(key) {
    if (!user.value) return false
    if (['owner', 'developer'].includes(user.value.role_nama)) return true
    const perms = Array.isArray(user.value.permissions) ? user.value.permissions : []
    if (perms.includes('*')) return true
    if (Array.isArray(key)) return key.some((k) => perms.includes(k))
    return perms.includes(key)
  }

  const userInitials = computed(() => {
    if (!user.value?.name) return '?'
    return user.value.name
      .split(' ')
      .slice(0, 2)
      .map((n) => n[0])
      .join('')
      .toUpperCase()
  })

  async function login(credentials) {
    loading.value = true
    try {
      const res = await authService.login(credentials)
      // authService.login now returns the inner data object: { access_token, user }
      token.value = res.access_token
      user.value = res.user
      localStorage.setItem(STORAGE_KEYS.TOKEN, token.value)
      localStorage.setItem(STORAGE_KEYS.USER, JSON.stringify(user.value))
      // loading tetap true — akan di-set false oleh caller setelah animasi door selesai
      return res
    } catch (err) {
      loading.value = false
      throw err
    }
  }

  async function logout() {
    loading.value = true
    try {
      await authService.logout()
    } catch {
      // silent — token mungkin sudah expired
    } finally {
      clearAuth()
      loading.value = false
    }
  }

  async function fetchMe() {
    if (!token.value) return
    try {
      const res = await authService.me()
      user.value = res.data
      localStorage.setItem(STORAGE_KEYS.USER, JSON.stringify(user.value))
    } catch (err) {
      // Hanya logout kalau token benar-benar invalid (401)
      // Error jaringan / 500 tidak boleh logout user
      if (err?.response?.status === 401) {
        clearAuth()
      }
    } finally {
      userResolved.value = true
    }
  }

  /**
   * Pastikan user sudah punya role_nama (dari fetchMe).
   * Dipanggil di router guard sebelum cek permission.
   */
  async function ensureResolved() {
    if (!token.value) return
    // Kalau role_nama belum ada (localStorage lama), fetch dulu
    if (!userResolved.value || !user.value?.role_nama) {
      await fetchMe()
    }
  }

  function clearAuth() {
    destroyEcho()
    user.value = null
    token.value = null
    localStorage.removeItem(STORAGE_KEYS.TOKEN)
    localStorage.removeItem(STORAGE_KEYS.USER)
  }

  return {
    user,
    token,
    loading,
    isAuthenticated,
    userResolved,
    userInitials,
    hasPermission,
    login,
    logout,
    fetchMe,
    ensureResolved,
    clearAuth,
  }
})
