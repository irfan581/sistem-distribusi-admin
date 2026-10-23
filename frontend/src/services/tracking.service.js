import http from './http'

export const trackingService = {
  // Posisi terakhir semua sales (live)
  async getLive() {
    const { data } = await http.get('/admin/tracking/live')
    return data
  },

  // List sales yang sudah tercatat hari ini
  async getSalesAktif() {
    const { data } = await http.get('/admin/tracking/sales-aktif')
    return data
  },

  // Histori rute 1 sales 1 hari
  async getRute(userId, tanggal = null) {
    const params = tanggal ? `?tanggal=${tanggal}` : ''
    const { data } = await http.get(`/admin/tracking/rute/${userId}${params}`)
    return data
  },
}
