import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { visitTargetService } from '@/services/visitTarget.service'
import { getEcho } from '@/composables/useEcho'

export const useVisitTargetStore = defineStore('visitTarget', () => {
  // ── State ────────────────────────────────────────────────────
  const salesList   = ref([])
  const analytics   = ref(null)
  const loading     = ref(false)
  const saving      = ref(false)
  const lastUpdated = ref(null)
  const realtimeLog = ref([])
  const wsConnected = ref(false)

  let echoChannel   = null
  let wsListenersBound = false

  // ── Computed ─────────────────────────────────────────────────
  const totalSales = computed(() => analytics.value?.total_sales ?? salesList.value.length)
  const avgTarget  = computed(() => analytics.value?.avg_target  ?? 0)
  const maxTarget  = computed(() => analytics.value?.max_target  ?? 0)
  const minTarget  = computed(() => analytics.value?.min_target  ?? 0)

  // ── Actions ──────────────────────────────────────────────────
  async function fetchAll(filters = {}) {
    loading.value = true
    try {
      const res = await visitTargetService.getAll(filters)
      salesList.value = res.data ?? []
      lastUpdated.value = new Date()
    } catch (err) {
      console.error('[VisitTarget] fetchAll error:', err?.response?.data ?? err.message)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchAnalytics() {
    try {
      const res = await visitTargetService.getAnalytics()
      analytics.value = res.data ?? null
    } catch (err) {
      console.error('[VisitTarget] fetchAnalytics error:', err?.response?.data ?? err.message)
    }
  }

  async function updateTarget(salesId, target, reason) {
    saving.value = true
    try {
      const res = await visitTargetService.update(salesId, target, reason)
      // API returns full formatted sales object
      _patchSalesTarget(salesId, res.data)
      return res
    } finally {
      saving.value = false
    }
  }

  async function bulkUpdate(updates, reason) {
    saving.value = true
    try {
      const res = await visitTargetService.bulkUpdate(updates, reason)
      await fetchAll()
      return res
    } finally {
      saving.value = false
    }
  }

  async function resetTarget(salesId) {
    saving.value = true
    try {
      const res = await visitTargetService.reset(salesId)
      _patchSalesTarget(salesId, res.data)
      return res
    } finally {
      saving.value = false
    }
  }

  // ── Realtime (Reverb) ─────────────────────────────────────────
  function subscribeRealtime() {
    const echo = getEcho()
    if (!echo) return

    // Bind connection state events once
    if (!wsListenersBound) {
      const conn = echo.connector?.pusher?.connection
      if (conn) {
        conn.bind('connected',    () => { wsConnected.value = true })
        conn.bind('disconnected', () => { wsConnected.value = false })
        conn.bind('unavailable',  () => { wsConnected.value = false })
        conn.bind('failed',       () => { wsConnected.value = false })
        conn.bind('connecting',   () => { wsConnected.value = false })
        // Set initial state
        wsConnected.value = conn.state === 'connected'
        wsListenersBound = true
      }
    }

    if (echoChannel) return

    echoChannel = echo.channel('visit-targets')
    echoChannel.listen('.VisitTargetUpdated', (payload) => {
      _patchSalesTargetFromBroadcast(payload)

      realtimeLog.value.unshift({
        sales_id:   payload.sales_id,
        sales_nama: payload.sales_nama,
        target:     payload.target_per_day,
        set_by:     payload.set_by_name,
        at:         payload.effective_from_wib,
        at_iso:     payload.updated_at,
      })
      if (realtimeLog.value.length > 50) {
        realtimeLog.value = realtimeLog.value.slice(0, 50)
      }

      lastUpdated.value = new Date()

      // Refresh analytics after a realtime update
      fetchAnalytics()
    })
  }

  function unsubscribeRealtime() {
    const echo = getEcho()
    if (echo && echoChannel) {
      echo.leave('visit-targets')
      echoChannel = null
    }
    wsConnected.value = false
  }

  // ── Internal helpers ──────────────────────────────────────────
  function _patchSalesTarget(salesId, updatedData) {
    const idx = salesList.value.findIndex((s) => s.id === salesId)
    if (idx !== -1 && updatedData) {
      salesList.value[idx] = { ...salesList.value[idx], ...updatedData }
    }
  }

  function _patchSalesTargetFromBroadcast(payload) {
    const idx = salesList.value.findIndex((s) => s.id === payload.sales_id)
    if (idx !== -1) {
      salesList.value[idx] = {
        ...salesList.value[idx],
        target_per_day:     payload.target_per_day,
        target_source:      'dedicated',
        target_set_by:      payload.set_by_name,
        target_updated_wib: payload.effective_from_wib,
      }
    }
  }

  return {
    salesList,
    analytics,
    loading,
    saving,
    lastUpdated,
    realtimeLog,
    wsConnected,
    totalSales,
    avgTarget,
    maxTarget,
    minTarget,
    fetchAll,
    fetchAnalytics,
    updateTarget,
    bulkUpdate,
    resetTarget,
    subscribeRealtime,
    unsubscribeRealtime,
  }
})
