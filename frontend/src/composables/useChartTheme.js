/**
 * useChartTheme — warna grafik yang ikut berubah saat mode gelap dinyalakan.
 *
 * ECharts menggambar ke canvas, bukan ke DOM, jadi ia tidak mengerti
 * `var(--t-secondary)` seperti CSS. Warnanya harus berupa nilai jadi, dan
 * satu-satunya cara membuatnya mengikuti tema adalah menghitungnya ulang dari
 * penanda mode gelap di store.
 *
 * Sebelum composable ini ada, tujuh definisi yang sama persis disalin ke enam
 * komponen grafik. Salinannya sudah mulai berbeda diam-diam: MuatanTrendChart
 * memakai #666666 untuk label sedangkan yang lain #71717a, dan garis sumbunya
 * memakai alpha yang berbeda pula. Perbedaan begitu tidak pernah disengaja,
 * cuma tertinggal saat menyalin.
 *
 * Cara pakai:
 *   const { labelColor, tooltipBg, seri } = useChartTheme()
 *   // di dalam computed option:
 *   textStyle: { color: labelColor.value }
 *   itemStyle: { color: seri.value.sukses }
 */
import { computed } from 'vue'
import { useAppStore } from '@/stores/app.store'

export function useChartTheme() {
  const appStore = useAppStore()
  const isDark = computed(() => appStore.darkMode)

  const pilih = (gelap, terang) => computed(() => (isDark.value ? gelap : terang))

  return {
    isDark,

    // ── Rangka grafik: sumbu, label, garis bantu, tooltip ──────
    axisColor:   pilih('rgba(255,255,255,.15)', 'rgba(0,0,0,.12)'),
    splitColor:  pilih('rgba(255,255,255,.06)', 'rgba(0,0,0,.05)'),
    shadowColor: pilih('rgba(255,255,255,.03)', 'rgba(0,0,0,.03)'),
    labelColor:  pilih('#a1a1aa', '#71717a'),
    centerColor: pilih('#f4f4f5', '#18181b'),
    subColor:    pilih('#71717a', '#a1a1aa'),
    tooltipBg:   pilih('#1c1412', '#ffffff'),
    tooltipBdr:  pilih('#3a2a1f', '#e5e7eb'),
    tooltipTxt:  pilih('#e4e4e7', '#18181b'),

    // Garis pemisah antar potongan donat dan antar batang. Warnanya mengikuti
    // permukaan halaman supaya terlihat seperti celah, bukan garis tambahan.
    borderClr:   pilih('#161210', '#ffffff'),

    // ── Warna deret data ──────────────────────────────────────
    // Nilai gelapnya dinaikkan terangnya. Hijau #16a34a dan merah #dc2626
    // terbaca di atas kertas putih, tapi di atas kanvas gelap keduanya
    // tenggelam jadi noda yang sulit dibedakan satu sama lain.
    seri: computed(() =>
      isDark.value
        ? {
            sukses:     '#4ade80',
            peringatan: '#fbbf24',
            bahaya:     '#f87171',
            info:       '#60a5fa',
            merek:      '#fb923c',
            nila:       '#a5b4fc',
          }
        : {
            sukses:     '#16a34a',
            peringatan: '#d97706',
            bahaya:     '#dc2626',
            info:       '#3b82f6',
            merek:      '#f97316',
            nila:       '#4338ca',
          },
    ),
  }
}
