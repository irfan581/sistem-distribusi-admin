/**
 * usePullToRefresh
 *
 * Pull-to-refresh untuk mobile web — mirip native iOS/Android.
 * Attach ke elemen scroll container (workspace-content).
 *
 * Cara kerja:
 * 1. Deteksi touchstart di bagian atas scroll (scrollTop <= 0)
 * 2. Saat user tarik ke bawah, tampilkan indikator dengan efek spring
 * 3. Jika tarik melebihi threshold (70px), panggil onRefresh()
 * 4. Hanya aktif di mobile (max-width 991px) & bukan di halaman Chat/Maps
 */
import { ref, onMounted, onBeforeUnmount } from 'vue'

const THRESHOLD    = 70   // px — seberapa jauh harus tarik sebelum trigger
const MAX_PULL     = 110  // px — batas maksimum indikator turun
const RESISTANCE   = 0.45 // makin kecil makin berat tarikannya (spring effect)

export function usePullToRefresh(scrollElRef, onRefresh) {
  const pulling      = ref(false)   // sedang ditarik
  const pullY        = ref(0)       // jarak tarikan saat ini (px)
  const refreshing   = ref(false)   // sedang loading

  let startY    = 0
  let startScrollTop = 0
  let canPull   = false

  // ── TOUCH HANDLERS ──────────────────────────────────────────

  function onTouchStart(e) {
    if (refreshing.value) return
    const el = scrollElRef.value
    if (!el) return

    startScrollTop = el.scrollTop
    startY         = e.touches[0].clientY
    canPull        = startScrollTop <= 0   // hanya dari posisi paling atas
  }

  function onTouchMove(e) {
    if (!canPull || refreshing.value) return
    const el = scrollElRef.value
    if (!el) return

    // Cek ulang scrollTop — kalau sudah tidak 0, batalkan
    if (el.scrollTop > 2) {
      canPull = false
      pulling.value = false
      pullY.value = 0
      return
    }

    const currentY = e.touches[0].clientY
    const delta    = currentY - startY

    if (delta <= 0) {
      // Tarik ke atas — reset
      pulling.value = false
      pullY.value   = 0
      return
    }

    // Cegah scroll native saat kita handle pull
    e.preventDefault()

    pulling.value = true
    // Spring resistance — makin jauh makin berat
    pullY.value = Math.min(delta * RESISTANCE, MAX_PULL)
  }

  function onTouchEnd() {
    if (!pulling.value) return

    if (pullY.value >= THRESHOLD * RESISTANCE) {
      // Trigger refresh
      triggerRefresh()
    } else {
      // Kembalikan ke posisi semula
      reset()
    }
  }

  async function triggerRefresh() {
    refreshing.value = true
    pulling.value    = false
    pullY.value      = THRESHOLD * RESISTANCE  // tahan di posisi loading

    try {
      await onRefresh()
    } finally {
      // Animasi balik ke atas
      await new Promise(r => setTimeout(r, 400))
      refreshing.value = false
      pullY.value      = 0
    }
  }

  function reset() {
    pulling.value    = false
    pullY.value      = 0
  }

  // ── LIFECYCLE ────────────────────────────────────────────────

  function isMobile() {
    return window.innerWidth <= 991
  }

  function attach() {
    const el = scrollElRef.value
    if (!el || !isMobile()) return
    el.addEventListener('touchstart', onTouchStart, { passive: true })
    el.addEventListener('touchmove',  onTouchMove,  { passive: false })
    el.addEventListener('touchend',   onTouchEnd,   { passive: true })
  }

  function detach() {
    const el = scrollElRef.value
    if (!el) return
    el.removeEventListener('touchstart', onTouchStart)
    el.removeEventListener('touchmove',  onTouchMove)
    el.removeEventListener('touchend',   onTouchEnd)
  }

  onMounted(attach)
  onBeforeUnmount(detach)

  return { pulling, pullY, refreshing }
}
