import http from './http'

export const mapsService = {
  async getKunjungan(tanggal) {
    const { data } = await http.get('/admin/maps/kunjungan', { params: tanggal ? { tanggal } : {} })
    return data
  },
}
