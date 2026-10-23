import { FilterService } from '@primevue/core/api'

const rapikan = teks => String(teks ?? '').toLowerCase().replace(/\s+/g, ' ').trim()
const pecahKata = teks => rapikan(teks).split(' ').filter(Boolean)
const awalKata = kata => new RegExp(`(^|[^a-z0-9])${kata.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`)

// Aturan saring Select PrimeVue: setiap kata yang diketik harus ada di nama,
// urutannya bebas — "made 0812" tetap menemukan "Made Sudana (081200000010)".
export const ATURAN_SEMUA_KATA = 'semuaKata'
FilterService.register(ATURAN_SEMUA_KATA, (nilai, kataKunci) => {
  const kata = pecahKata(kataKunci)
  const teks = rapikan(nilai)
  return kata.every(k => teks.includes(k))
})

// 0 = nama diawali kata kunci, 1 = diawali kata pertama, 2 = salah satu kata
// di nama diawali kata pertama, 3 = sekadar mengandung.
function peringkat(teks, kataKunci, kata) {
  if (teks.startsWith(kataKunci)) return 0
  if (teks.startsWith(kata[0])) return 1
  if (awalKata(kata[0]).test(teks)) return 2
  return 3
}

/**
 * Menyaring daftar ke yang memuat semua kata yang diketik, lalu mendahulukan
 * yang namanya diawali kata kunci. Urutan asal (biasanya abjad) dipertahankan
 * di dalam tiap peringkat.
 */
export function urutkanHasilCari(daftar, kataKunci, ambilTeks = x => x) {
  const kata = pecahKata(kataKunci)
  if (!kata.length) return daftar
  const utuh = kata.join(' ')

  return daftar
    .map(item => ({ item, teks: rapikan(ambilTeks(item)) }))
    .filter(({ teks }) => kata.every(k => teks.includes(k)))
    .map(x => ({ ...x, nilai: peringkat(x.teks, utuh, kata) }))
    .sort((a, b) => a.nilai - b.nilai)
    .map(x => x.item)
}

// Kata terpanjang paling menyempitkan hasil saat hanya satu kata yang bisa
// dikirim ke server; kata lainnya disaring di sini.
export function kataTerpanjang(kataKunci) {
  return pecahKata(kataKunci).reduce((a, b) => (b.length > a.length ? b : a), '')
}
