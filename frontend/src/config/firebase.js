import { initializeApp, getApps } from '@firebase/app'
import { getMessaging, isSupported } from '@firebase/messaging'

const firebaseConfig = {
  apiKey:            import.meta.env.VITE_FIREBASE_API_KEY,
  authDomain:        import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
  projectId:         import.meta.env.VITE_FIREBASE_PROJECT_ID,
  storageBucket:     import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
  messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
  appId:             import.meta.env.VITE_FIREBASE_APP_ID,
}

// Init hanya sekali
let app = getApps().length ? getApps()[0] : initializeApp(firebaseConfig)

/**
 * Get messaging instance — null jika browser tidak support atau config belum diisi.
 */
export async function getFirebaseMessaging() {
  try {
    const supported = await isSupported()
    if (!supported) return null
    // Pastikan config sudah diisi
    if (!import.meta.env.VITE_FIREBASE_API_KEY || !import.meta.env.VITE_FIREBASE_APP_ID) {
      console.warn('[FCM] Firebase App ID belum dikonfigurasi di .env')
      return null
    }
    return getMessaging(app)
  } catch {
    return null
  }
}

export { app }
