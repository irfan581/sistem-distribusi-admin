import { defineStore } from 'pinia'
import { ref } from 'vue'
import { STORAGE_KEYS } from '@/utils/constants'

export const useAppStore = defineStore('app', () => {
  const sidebarOpen = ref(true)
  const sidebarMobileOpen = ref(false)
  const globalLoading = ref(false)
  // Flag: true setelah login berhasil — sidebar/navbar tampilkan animasi masuk
  const freshLogin = ref(false)
  // Default light mode — jika belum pernah disimpan, pakai light
  const savedTheme = localStorage.getItem(STORAGE_KEYS.THEME)
  const darkMode = ref(savedTheme === null ? false : savedTheme === 'dark')

  function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value
  }

  function toggleMobileSidebar() {
    sidebarMobileOpen.value = !sidebarMobileOpen.value
  }

  function closeMobileSidebar() {
    sidebarMobileOpen.value = false
  }

  function setGlobalLoading(val) {
    globalLoading.value = val
  }

  function toggleDarkMode() {
    darkMode.value = !darkMode.value
    localStorage.setItem(STORAGE_KEYS.THEME, darkMode.value ? 'dark' : 'light')
    applyTheme()
  }

  function applyTheme() {
    const el = document.documentElement
    if (darkMode.value) {
      el.classList.add('app-dark')
    } else {
      el.classList.remove('app-dark')
    }
  }

  function initTheme() {
    applyTheme()
  }

  function setFreshLogin() {
    freshLogin.value = true
    // Reset setelah animasi selesai (~1.2s) agar tidak re-trigger saat navigasi
    setTimeout(() => { freshLogin.value = false }, 1200)
  }

  return {
    sidebarOpen,
    sidebarMobileOpen,
    globalLoading,
    darkMode,
    freshLogin,
    toggleSidebar,
    toggleMobileSidebar,
    closeMobileSidebar,
    setGlobalLoading,
    toggleDarkMode,
    initTheme,
    setFreshLogin,
  }
})
