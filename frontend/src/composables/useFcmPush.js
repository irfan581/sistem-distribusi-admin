import { ref } from 'vue'
import { getToken, onMessage } from '@firebase/messaging'
import { getFirebaseMessaging } from '@/config/firebase'
import http from '@/services/http'

const VAPID_KEY = import.meta.env.VITE_FIREBASE_VAPID_KEY

const fcmReady   = ref(false)
const fcmBlocked = ref(false)
let   _onMessageUnsubscribe = null  // agar tidak double-register

/**
 * Minta permission browser, ambil FCM token, kirim ke backend.
 * Aman dipanggil berkali-kali — idempotent.
 */
export async function requestFcmPermission() {
  if (!VAPID_KEY) {
    console.warn('[FCM] VITE_FIREBASE_VAPID_KEY belum diisi di .env, push notif dinonaktifkan')
    return
  }
  if (!('serviceWorker' in navigator)) {
    console.warn('[FCM] Service Worker tidak didukung browser ini')
    return
  }

  try {
    const messaging = await getFirebaseMessaging()
    if (!messaging) return

    // Register service worker dan tunggu sampai ready
    const swReg = await navigator.serviceWorker.register('/firebase-messaging-sw.js')
    await navigator.serviceWorker.ready  // tunggu sampai SW aktif

    // Minta permission browser
    const permission = await Notification.requestPermission()
    if (permission !== 'granted') {
      fcmBlocked.value = true
      console.info('[FCM] Notifikasi ditolak user')
      return
    }

    // Ambil FCM token — gunakan SW yang sudah aktif
    const token = await getToken(messaging, {
      vapidKey: VAPID_KEY,
      serviceWorkerRegistration: swReg,
    })

    if (!token) {
      console.warn('[FCM] Gagal mendapatkan token, coba reload browser')
      return
    }

    // Simpan token ke backend
    await http.post('/admin/chat/fcm-token', { token }).catch(() => {})

    fcmReady.value = true
    console.info('[FCM] Token terdaftar:', token.substring(0, 20) + '…')

    // Foreground message handler — jangan double register
    if (_onMessageUnsubscribe) _onMessageUnsubscribe()
    _onMessageUnsubscribe = onMessage(messaging, (payload) => {
      window.dispatchEvent(new CustomEvent('fcm-foreground-message', { detail: payload }))
    })

  } catch (err) {
    console.error('[FCM] Gagal inisialisasi:', err)
  }
}

export { fcmReady, fcmBlocked }
