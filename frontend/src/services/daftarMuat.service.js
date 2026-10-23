import http from './http'

export const daftarMuatService = {
  async getAll(params = {}) {
    const query = new URLSearchParams()
    if (params.search)          query.set('search', params.search)
    if (params.tanggal)         query.set('tanggal', params.tanggal)
    if (params.tanggal_dari)    query.set('tanggal_dari', params.tanggal_dari)
    if (params.tanggal_sampai)  query.set('tanggal_sampai', params.tanggal_sampai)
    if (params.page)            query.set('page', params.page)
    if (params.per_page)        query.set('per_page', params.per_page)
    if (params.belum_driver)    query.set('belum_driver', '1')
    if (params.sudah_driver)    query.set('sudah_driver', '1')
    if (params.jenis_muatan)    query.set('jenis_muatan', params.jenis_muatan)
    if (params.tanggapan)       query.set('tanggapan', params.tanggapan)
    const qs = query.toString()
    const { data } = await http.get(`/admin/pengiriman/daftar-muat${qs ? '?' + qs : ''}`)
    return data
  },

  async show(id) {
    const { data } = await http.get(`/admin/pengiriman/daftar-muat/${id}`)
    return data
  },

  // Hasil kerja driver di lapangan untuk satu rit, berikut foto buktinya.
  // Dipisah dari show() karena isinya berat dan cuma dibutuhkan saat admin
  // sengaja membuka laporannya.
  async laporanDriver(id) {
    const { data } = await http.get(`/admin/pengiriman/daftar-muat/${id}/laporan-driver`)
    return data
  },

  // Tanggapan admin atas laporan driver untuk satu tujuan (diterima/ditolak + catatan).
  async tanggapiLaporan(id, itemId, payload) {
    const { data } = await http.post(`/admin/pengiriman/daftar-muat/${id}/item/${itemId}/tanggapan`, payload)
    return data
  },

  // Pencarian toko untuk dipilih sebagai tujuan non-subsidi — alamat &
  // koordinat disertakan (diambil dari kunjungan sales terakhir ke toko
  // itu), bukan endpoint Master Toko yang tidak membawa alamat.
  async cariTokoTujuan(search) {
    const query = new URLSearchParams()
    if (search) query.set('search', search)
    const { data } = await http.get(`/admin/pengiriman/toko-tujuan?${query.toString()}`)
    return data.data ?? []
  },

  async nextRit(driverId, tanggal, excludeId = null) {
    const query = new URLSearchParams({ driver_id: driverId, tanggal })
    if (excludeId) query.set('exclude_id', excludeId)
    const { data } = await http.get(`/admin/pengiriman/daftar-muat/next-rit?${query.toString()}`)
    return data.next_rit ?? 1
  },

  async store(payload) {
    const { data } = await http.post('/admin/pengiriman/daftar-muat', payload)
    return data
  },

  async update(id, payload) {
    const { data } = await http.put(`/admin/pengiriman/daftar-muat/${id}`, payload)
    return data
  },

  async destroy(id) {
    const { data } = await http.delete(`/admin/pengiriman/daftar-muat/${id}`)
    return data
  },

  suratJalanUrl(id) {
    const base = import.meta.env.VITE_API_BASE_URL
    return `${base}/admin/pengiriman/daftar-muat/${id}/surat-jalan`
  },

  rekapHarianUrl(tanggal, jenisMuatan = null) {
    const base = import.meta.env.VITE_API_BASE_URL
    const qs   = jenisMuatan ? `&jenis_muatan=${jenisMuatan}` : ''
    return `${base}/admin/pengiriman/daftar-muat/rekap-harian/pdf?tanggal=${tanggal}${qs}`
  },

  rekapPeriodeUrl(dari, sampai, jenisMuatan = null) {
    const base = import.meta.env.VITE_API_BASE_URL
    const qs   = jenisMuatan ? `&jenis_muatan=${jenisMuatan}` : ''
    return `${base}/admin/pengiriman/daftar-muat/rekap-periode/pdf?dari=${dari}&sampai=${sampai}${qs}`
  },
}
