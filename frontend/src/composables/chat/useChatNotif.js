export function useChatNotif(selectContact) {
  // In-app notification callback — set by ChatView to show a toast/banner
  // when a message arrives for a contact that isn't currently open
  let _inAppCallback = null

  function setInAppCallback(fn) {
    _inAppCallback = fn
  }

  function requestNotifPermission() {
    if (!('Notification' in window)) return
    if (Notification.permission === 'default') Notification.requestPermission()
  }

  function pushNotif(senderName, body, contact) {
    // In-app alert: tab is visible, message is for a different contact
    if (!document.hidden && _inAppCallback) {
      _inAppCallback(senderName, body, contact)
    }

    // Browser notification: only when tab is hidden (minimised / other tab)
    if (!('Notification' in window)) return
    if (Notification.permission !== 'granted') return
    if (!document.hidden) return

    const n = new Notification(senderName, {
      body: body.length > 80 ? body.slice(0, 80) + '…' : body,
      icon: '/logo-icon.png',
      tag:  `chat-${contact?.type}-${contact?.id ?? 0}`,
    })

    n.onclick = () => {
      window.focus()
      if (contact) selectContact(contact)
      n.close()
    }
  }

  return { requestNotifPermission, pushNotif, setInAppCallback }
}
