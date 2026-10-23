import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

let echoInstance = null

function createEcho(token) {
  window.Pusher = Pusher
  return new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'tmk-reverb-key',
    wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
    wsPort: parseInt(import.meta.env.VITE_REVERB_PORT || '443'),
    wssPort: parseInt(import.meta.env.VITE_REVERB_PORT || '443'),
    wsPath: import.meta.env.VITE_REVERB_PATH ? '/' + import.meta.env.VITE_REVERB_PATH : '',
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${import.meta.env.VITE_APP_URL || 'http://localhost:8000'}/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
    },
  })
}

export function getEcho() {
  if (echoInstance) return echoInstance
  const token = localStorage.getItem('tmk_auth_token')
  if (!token) return null
  echoInstance = createEcho(token)
  window.Echo = echoInstance
  return echoInstance
}

export function destroyEcho() {
  if (echoInstance) {
    echoInstance.disconnect()
    echoInstance = null
    window.Echo = null
  }
}
