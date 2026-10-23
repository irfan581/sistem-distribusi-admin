import { useAuthStore } from '@/stores/auth.store'

export function usePermission() {
  const authStore = useAuthStore()

  const isOwner     = () => authStore.user?.role_nama === 'owner'
  const isDeveloper = () => authStore.user?.role_nama === 'developer'

  const hasFullAccess = () => {
    if (isOwner() || isDeveloper()) return true
    const perms = Array.isArray(authStore.user?.permissions)
      ? authStore.user.permissions : []
    return perms.includes('*')
  }

  function can(key) {
    if (!authStore.isAuthenticated) return false
    if (hasFullAccess()) return true
    const perms = Array.isArray(authStore.user?.permissions)
      ? authStore.user.permissions : []
    if (Array.isArray(key)) return key.some((k) => perms.includes(k))
    return perms.includes(key)
  }

  function canAll(keys) {
    if (!authStore.isAuthenticated) return false
    if (hasFullAccess()) return true
    const perms = Array.isArray(authStore.user?.permissions)
      ? authStore.user.permissions : []
    return keys.every((k) => perms.includes(k))
  }

  return { can, canAll, isOwner, isDeveloper, hasFullAccess }
}
