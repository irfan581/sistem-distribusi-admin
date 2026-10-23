/**
 * useStaleRefresh — muat data saat halaman dipasang, lalu muat ulang saat
 * halaman dibuka kembali kalau datanya sudah kedaluwarsa.
 *
 * Dipakai halaman yang masuk daftar KeepAlive di AdminLayout. Halaman itu
 * tidak dibongkar saat pindah menu, jadi `onMounted` cuma jalan sekali seumur
 * sesi dan datanya bisa basi berjam-jam tanpa ada yang memuat ulang.
 * Memuat ulang setiap kali kembali juga bukan jawabannya — itu menghapus
 * seluruh manfaat KeepAlive dan membuat perpindahan menu terasa berat lagi.
 *
 * Jalan tengahnya: muat ulang hanya kalau data terakhir sudah lebih tua dari
 * [maxAge]. Bolak-balik menu dalam hitungan detik terasa seketika, sedangkan
 * halaman yang ditinggal lama tetap menyegarkan dirinya saat dibuka lagi.
 *
 * Cara pakai:
 *   useStaleRefresh(() => loadData())
 *   useStaleRefresh(() => loadData(), { maxAge: 120_000 })
 */
import { onActivated, onMounted } from 'vue'

export function useStaleRefresh(callback, { maxAge = 60_000 } = {}) {
  let terakhir = 0

  const jalankan = () => {
    terakhir = Date.now()
    callback()
  }

  onMounted(jalankan)

  // Pada pemasangan pertama di dalam KeepAlive, onActivated ikut jalan tepat
  // setelah onMounted. Penanda waktu di atas membuat pemanggilan itu terlewat
  // dengan sendirinya, jadi data tidak diambil dua kali.
  onActivated(() => {
    if (Date.now() - terakhir >= maxAge) jalankan()
  })
}
