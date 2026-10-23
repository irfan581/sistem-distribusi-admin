import { ATTENDANCE_STATUS_LABELS, ATTENDANCE_STATUS_SEVERITY } from './constants'

export function formatDate(date, locale = 'id-ID') {
  if (!date) return '-'
  return new Intl.DateTimeFormat(locale, {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  }).format(new Date(date))
}

export function formatDateTime(date, locale = 'id-ID') {
  if (!date) return '-'
  return new Intl.DateTimeFormat(locale, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(date))
}

export function formatTime(date) {
  if (!date) return '-'
  return new Intl.DateTimeFormat('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(date))
}

export function formatCurrency(amount, currency = 'IDR') {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency,
    minimumFractionDigits: 0,
  }).format(amount)
}

export function formatNumber(num) {
  return new Intl.NumberFormat('id-ID').format(num)
}

export function getAttendanceLabel(status) {
  return ATTENDANCE_STATUS_LABELS[status] || status
}

export function getAttendanceSeverity(status) {
  return ATTENDANCE_STATUS_SEVERITY[status] || 'secondary'
}

export function getInitials(name) {
  if (!name) return '?'
  return name
    .split(' ')
    .slice(0, 2)
    .map((n) => n[0])
    .join('')
    .toUpperCase()
}

export function truncate(str, length = 50) {
  if (!str) return ''
  return str.length > length ? str.substring(0, length) + '...' : str
}

export function debounce(fn, delay = 300) {
  let timer
  return (...args) => {
    clearTimeout(timer)
    timer = setTimeout(() => fn(...args), delay)
  }
}

/**
 * Salin teks ke clipboard. `navigator.clipboard` cuma tersedia di secure
 * context (HTTPS atau localhost) — di jaringan lokal lewat http://IP (mis.
 * saat dev/testing di HP asli), API itu tidak ada sama sekali dan langsung
 * gagal. Fallback ke document.execCommand('copy') lewat textarea sementara,
 * yang walau sudah deprecated masih jalan di semua browser tanpa syarat
 * secure context.
 */
export async function salinTeks(teks) {
  if (navigator.clipboard && window.isSecureContext) {
    try {
      await navigator.clipboard.writeText(teks)
      return true
    } catch {
      // lanjut ke fallback di bawah
    }
  }

  const area = document.createElement('textarea')
  area.value = teks
  area.style.position = 'fixed'
  area.style.opacity = '0'
  document.body.appendChild(area)
  area.focus()
  area.select()

  let berhasil = false
  try {
    berhasil = document.execCommand('copy')
  } catch {
    berhasil = false
  }
  document.body.removeChild(area)

  return berhasil
}

export function buildQueryString(params) {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, val]) => {
    if (val !== null && val !== undefined && val !== '') {
      query.append(key, val)
    }
  })
  return query.toString()
}
