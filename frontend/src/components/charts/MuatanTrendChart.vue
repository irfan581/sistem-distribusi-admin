<template>
  <v-chart class="chart" :option="option" autoresize />
</template>

<script setup>
import { computed } from 'vue'
import VChart from 'vue-echarts'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { BarChart, LineChart, PictorialBarChart } from 'echarts/charts'
import { GridComponent, TooltipComponent, LegendComponent } from 'echarts/components'

// echarts 6 tidak lagi menghitung grid.containLabel secara bawaan;
// tanpa fitur ini marginnya diabaikan dan label sumbu bisa terpotong.
import { LegacyGridContainLabel } from 'echarts/features'
import { useChartTheme } from '@/composables/useChartTheme'

const { axisColor, splitColor, shadowColor, labelColor, tooltipBg, tooltipBdr, tooltipTxt } = useChartTheme()

use([CanvasRenderer, BarChart, PictorialBarChart, LineChart, GridComponent, TooltipComponent, LegendComponent, LegacyGridContainLabel])

const props = defineProps({
  data: { type: Array, default: () => [] },
})



// Warna brand, seragam dengan chart lain di dashboard. Palet 7 warna
// Chart.js dilepas: di 12 batang berurutan warnanya berulang tanpa makna,
// dan bikin chart ini terlihat asing dibanding Tren Kehadiran.
const C_MUATAN = 'rgb(249, 115, 22)'
const C_JADWAL = 'rgb(59, 130, 246)'

const transparentize = (rgb, alpha = 0.2) => rgb.replace('rgb(', 'rgba(').replace(')', `, ${alpha})`)

const nf = new Intl.NumberFormat('id-ID')

/** 470300 → "470 rb" — sumbu kiri jadi terbaca tanpa deretan nol. */
function ringkasKg(v) {
  if (v >= 1_000_000) return (v / 1_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt'
  if (v >= 1_000)     return Math.round(v / 1_000) + ' rb'
  return String(v)
}

const option = computed(() => {
  const rows  = props.data
  const labels = rows.map(r => {
    const d = new Date(r.tanggal)
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' })
  })
  const tons   = rows.map(r => Math.round(r.total_ton * 1000))
  const jadwal = rows.map(r => r.total_jadwal)

  return {
    backgroundColor: 'transparent',
    animation: true,
    animationDuration: 900,
    animationEasing: 'cubicOut',

    tooltip: {
      trigger: 'axis',
      confine: true,
      appendToBody: false,
      // Bar pakai penanda bayangan sekolom, bukan garis vertikal.
      axisPointer: { type: 'shadow', shadowStyle: { color: shadowColor.value } },
      backgroundColor: tooltipBg.value,
      borderColor: tooltipBdr.value,
      borderWidth: 1,
      padding: [10, 14],
      textStyle: { color: tooltipTxt.value, fontSize: 12 },
      extraCssText: 'box-shadow:0 4px 20px rgba(0,0,0,.15);border-radius:10px;',
      formatter(params) {
        const ton  = params.find(p => p.seriesName === 'Muatan (kg)')
        const jdwl = params.find(p => p.seriesName === 'Jadwal')

        const baris = (warna, nama, nilai) => `
          <div style="display:flex;align-items:center;gap:8px;padding:2px 0">
            <span style="flex-shrink:0;width:9px;height:9px;border-radius:2px;background:${warna}"></span>
            <span style="flex:1;color:${tooltipTxt.value}">${nama}</span>
            <span style="font-weight:700;color:${tooltipTxt.value}">${nilai}</span>
          </div>`

        return `<div style="font-weight:700;font-size:12px;margin-bottom:8px;color:${tooltipTxt.value}">${params[0].axisValue}</div>`
          + (ton  ? baris(C_MUATAN, 'Muatan', nf.format(ton.value) + ' kg') : '')
          + (jdwl ? baris(C_JADWAL, 'Jadwal', jdwl.value + ' jadwal')       : '')
      },
    },

    // Legend digambar sebagai chip HTML di header cell, sama seperti
    // Tren Kehadiran — bukan di dalam kanvas.
    legend: { show: false },

    grid: { top: 14, left: 8, right: 16, bottom: 8, containLabel: true },

    xAxis: {
      type: 'category',
      data: labels,
      axisLine: { lineStyle: { color: axisColor.value } },
      axisTick: { show: false },
      axisLabel: {
        fontSize: 10,
        color: labelColor.value,
        interval: Math.floor(rows.length / 7),
        margin: 10,
      },
    },

    yAxis: [
      {
        type: 'value',
        name: 'Kg',
        min: 0,
        nameTextStyle: { fontSize: 10, color: labelColor.value, padding: [0, 0, 0, -10] },
        axisLine: { show: false },
        axisTick: { show: false },
        axisLabel: { fontSize: 10, color: labelColor.value, formatter: ringkasKg },
        splitLine: { lineStyle: { color: splitColor.value, type: 'dashed' } },
      },
      {
        type: 'value',
        name: 'Jadwal',
        min: 0,
        nameTextStyle: { fontSize: 10, color: labelColor.value },
        axisLine: { show: false },
        axisTick: { show: false },
        axisLabel: { fontSize: 10, color: labelColor.value },
        splitLine: { show: false },
        minInterval: 1,
      },
    ],

    series: [
      // ── Badan batang: memudar ke bawah, tanpa garis tepi ──────────
      {
        name: 'Muatan (kg)',
        type: 'bar',
        yAxisIndex: 0,
        z: 2,
        data: tons,
        itemStyle: {
          borderWidth: 0,
          // Pekat di atas lalu hilang ke bawah — sisi kiri, kanan, dan
          // bawah tidak punya batas, jadi terbaca sebagai bayangan yang
          // menggantung dari garis atas.
          color: {
            type: 'linear', x: 0, y: 0, x2: 0, y2: 1,
            colorStops: [
              { offset: 0,    color: transparentize(C_MUATAN, 0.45) },
              { offset: 0.55, color: transparentize(C_MUATAN, 0.16) },
              { offset: 1,    color: transparentize(C_MUATAN, 0) },
            ],
          },
        },
        // Sela proporsional untuk data padat (30 hari), plus batas keras
        // supaya saat datanya sedikit (12 bulan) batangnya tidak melar —
        // 12 kategori di lebar penuh bisa jadi >100px per batang.
        barCategoryGap: '40%',
        barMaxWidth: 90,
        emphasis: { disabled: true },
        animationDuration: 1000,
        animationEasing: 'cubicOut',
        animationDelay: idx => idx * 90,
      },

      // ── Garis warna di puncak batang ──────────────────────────────
      // pictorialBar dipakai supaya tebal garisnya tetap 3px berapa pun
      // tinggi batangnya. Kalau memakai gradien bertahap, tebal garis
      // ikut proporsional dan bulan bermuatan kecil garisnya jadi tipis.
      {
        name: 'Puncak muatan',
        type: 'pictorialBar',
        yAxisIndex: 0,
        z: 3,
        silent: true,
        data: tons,
        itemStyle: { color: C_MUATAN },
        symbol: 'rect',
        symbolSize: ['100%', 3],
        symbolPosition: 'end',
        barCategoryGap: '40%',
        barMaxWidth: 90,
        // Menumpuk tepat di atas seri batang, bukan berdampingan.
        barGap: '-100%',
        animationDuration: 1000,
        animationEasing: 'cubicOut',
        animationDelay: idx => idx * 90,
      },

      {
        name: 'Jadwal',
        type: 'line',
        yAxisIndex: 1,
        z: 4,
        data: jadwal,
        // Chart.js line default: tension 0.4, titik bulat, garis 3px.
        smooth: 0.4,
        symbol: 'circle',
        symbolSize: 4,
        showSymbol: false,
        lineStyle: { color: C_JADWAL, width: 2 },
        itemStyle: { color: C_JADWAL },
        // Garis digambar setelah batang selesai naik, bukan bersamaan.
        animationDuration: 1200,
        animationDelay: 700,
      },
    ],
  }
})
</script>

<style scoped>
/* Cell-nya selebar penuh, jadi batang sudah dapat ruang dari lebar —
   tinggi cukup secukupnya untuk sumbu dan legend. */
.chart {
  width: 100%;
  height: 100%;
  min-height: 300px;
}

/* Di mobile wadahnya dikunci 280px (dashboard.css), lantai tinggi di atas
   akan menjebol kotaknya. */
@media (max-width: 768px) {
  .chart { min-height: 0; }
}
</style>
