// Titik berangkat truk, dari Irfan 2026-10-15.
export const LOKASI_GUDANG = { lat: -7.6298, lng: 111.5239 }

// Jarak garis lurus (km) antara dua titik lat/lng — cukup untuk membandingkan
// mana yang lebih dekat, bukan jarak tempuh lewat jalan.
function jarakKm(a, b) {
  const rad = d => (d * Math.PI) / 180
  const dLat = rad(b.lat - a.lat)
  const dLng = rad(b.lng - a.lng)
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2
  return 2 * 6371 * Math.asin(Math.sqrt(h))
}

function titik(item) {
  const lat = Number(item._toko?.lat)
  const lng = Number(item._toko?.lng)
  if (item._toko?.lat == null || item._toko?.lng == null || !Number.isFinite(lat) || !Number.isFinite(lng)) return null
  return { lat, lng }
}

/**
 * Mulai dari gudang, tiap tujuan berikutnya adalah yang terdekat dengan
 * tujuan sebelumnya. Tujuan tanpa koordinat ditaruh di akhir dengan urutan semula.
 */
export function urutkanTujuan(items, awal = LOKASI_GUDANG) {
  const bertitik = items.filter(i => titik(i))
  const tanpaTitik = items.filter(i => !titik(i))

  const hasil = []
  let posisi = awal
  while (bertitik.length > 0) {
    let terdekat = 0
    for (let i = 1; i < bertitik.length; i++) {
      if (jarakKm(posisi, titik(bertitik[i])) < jarakKm(posisi, titik(bertitik[terdekat]))) terdekat = i
    }
    const [dipilih] = bertitik.splice(terdekat, 1)
    hasil.push(dipilih)
    posisi = titik(dipilih)
  }

  return [...hasil, ...tanpaTitik]
}
