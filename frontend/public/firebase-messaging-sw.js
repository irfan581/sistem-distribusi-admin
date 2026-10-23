importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js')
importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js')

firebase.initializeApp({
  apiKey:            'ISI_API_KEY',
  authDomain:        'ISI_AUTH_DOMAIN',
  projectId:         'ISI_PROJECT_ID',
  storageBucket:     'ISI_STORAGE_BUCKET',
  messagingSenderId: 'ISI_MESSAGING_SENDER_ID',
  appId:             'ISI_APP_ID',
})

const messaging = firebase.messaging()

// Background message — saat tab tidak aktif
messaging.onBackgroundMessage((payload) => {
  console.log('[SW] Background message:', payload)
  const { title, body, icon } = payload.notification || {}
  const notifTitle = title || 'TMK Chat'
  const notifBody  = body  || 'Pesan baru'

  self.registration.showNotification(notifTitle, {
    body:     notifBody,
    icon:     icon || '/logo-icon.png',
    badge:    '/logo-icon.png',
    tag:      'chat-message',
    renotify: true,
    data:     payload.data || {},
    actions: [
      { action: 'open',  title: 'Buka Chat' },
      { action: 'close', title: 'Tutup' },
    ],
  })
})

// Klik notifikasi → buka halaman chat
self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  if (event.action === 'close') return

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if (client.url.includes('/chat') && 'focus' in client) {
          return client.focus()
        }
      }
      if (clients.openWindow) return clients.openWindow('/chat')
    })
  )
})
