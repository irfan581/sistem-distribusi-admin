export const APP_NAME = import.meta.env.VITE_APP_NAME || 'TMK Admin'
export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api'
export const APP_URL = import.meta.env.VITE_APP_URL || 'http://localhost:8000'

export const STORAGE_KEYS = {
  TOKEN: 'tmk_auth_token',
  USER: 'tmk_auth_user',
  THEME: 'tmk_theme',
}

export const ATTENDANCE_STATUS = {
  PRESENT: 'present',
  ABSENT: 'absent',
  LATE: 'late',
  LEAVE: 'leave',
  PERMIT: 'permit',
}

export const ATTENDANCE_STATUS_LABELS = {
  present: 'Hadir',
  absent: 'Tidak Hadir',
  late: 'Terlambat',
  leave: 'Cuti',
  permit: 'Izin',
}

export const ATTENDANCE_STATUS_SEVERITY = {
  present: 'success',
  absent: 'danger',
  late: 'warn',
  leave: 'info',
  permit: 'secondary',
}

export const EMPLOYEE_STATUS = {
  ACTIVE: 'active',
  INACTIVE: 'inactive',
  ON_LEAVE: 'on_leave',
}

export const PAGINATION = {
  DEFAULT_ROWS: 25,
  ROWS_OPTIONS: [25, 50, 100],
}

// Paginator hanya menampilkan angka di dropdown kalau nilai `rows` persis ada
// di ROWS_OPTIONS (dan bertipe number). API kadang mengirim per_page sebagai
// string, jadi selalu lewatkan meta.per_page ke sini.
export function safeRows(value) {
  const n = Number(value)
  return PAGINATION.ROWS_OPTIONS.includes(n) ? n : PAGINATION.DEFAULT_ROWS
}
