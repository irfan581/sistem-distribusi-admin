import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/services/http'

// Jumlah foto lapangan yang belum ditanggapi admin, untuk penanda di menu samping.
export const useTanggapanStore = defineStore('tanggapan', () => {
  const kunjungan = ref(0)
  const laporan   = ref(0)

  async function muat() {
    try {
      const { data } = await http.get('/admin/tanggapan/ringkasan')
      kunjungan.value = data.data?.kunjungan ?? 0
      laporan.value   = data.data?.laporan ?? 0
    } catch { /* penanda boleh kosong bila gagal */ }
  }

  return { kunjungan, laporan, muat }
})
