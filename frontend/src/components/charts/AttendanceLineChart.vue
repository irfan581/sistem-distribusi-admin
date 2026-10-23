<template>
  <v-chart class="chart" :option="option" autoresize />
</template>

<script setup>
import { computed } from 'vue'
import VChart from 'vue-echarts'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { LineChart, BarChart } from 'echarts/charts'
import {
  GridComponent, TooltipComponent,
} from 'echarts/components'

// Removed: LegendComponent (legend moved to HTML), DataZoomComponent (unused)
// echarts 6 tidak lagi menghitung grid.containLabel secara bawaan;
// tanpa fitur ini marginnya diabaikan dan label sumbu bisa terpotong.
import { LegacyGridContainLabel } from 'echarts/features'
import { useChartTheme } from '@/composables/useChartTheme'

const { axisColor, splitColor, labelColor, tooltipBg, tooltipBdr, tooltipTxt, borderClr, isDark, seri } = useChartTheme()

use([CanvasRenderer, LineChart, BarChart, GridComponent, TooltipComponent, LegacyGridContainLabel])

const props = defineProps({
  data: { type: Object, default: () => ({}) },
})


const C_PRESENT = '#22c55e'
const C_LATE    = '#f59e0b'
const C_VISIT   = '#f97316'

// ── Dark-mode reactive colors ─────────────────────────────────

const option = computed(() => ({
  backgroundColor: 'transparent',

  // ── Animations ───────────────────────────────────────────────
  animation: true,
  animationDuration: 800,
  animationEasing: 'cubicOut',
  animationDelay: (idx) => idx * 30,
  animationDurationUpdate: 500,
  animationEasingUpdate: 'cubicInOut',

  // ── Tooltip ──────────────────────────────────────────────────
  tooltip: {
    trigger: 'axis',
    axisPointer: { type: 'shadow', shadowStyle: { color: isDark.value ? 'rgba(255,255,255,.03)' : 'rgba(0,0,0,.03)' } },
    backgroundColor: tooltipBg.value,
    borderColor: tooltipBdr.value,
    borderWidth: 1,
    padding: [10, 14],
    textStyle: { color: tooltipTxt.value, fontSize: 12 },
    extraCssText: 'box-shadow:0 4px 20px rgba(0,0,0,.15);border-radius:10px;',
    formatter(params) {
      const date = params[0]?.axisValue ?? ''
      const rows = params
        .filter(p => p.value > 0)
        .map(p => `
          <div style="display:flex;align-items:center;gap:8px;padding:2px 0">
            <span style="flex-shrink:0;width:9px;height:9px;border-radius:2px;background:${p.color}"></span>
            <span style="flex:1;color:${tooltipTxt.value}">${p.seriesName}</span>
            <span style="font-weight:700;color:${tooltipTxt.value}">${p.value}</span>
          </div>`)
        .join('')
      return `<div style="font-weight:700;font-size:12px;margin-bottom:8px;color:${tooltipTxt.value}">${date}</div>${rows}`
    },
  },

  // ── No legend — handled in HTML .chart-legend ────────────────
  legend: { show: false },

  // ── Grid ─────────────────────────────────────────────────────
  grid: { top: 12, left: 44, right: 52, bottom: 16, containLabel: true },

  // ── X Axis ───────────────────────────────────────────────────
  xAxis: {
    type: 'category',
    data: props.data.labels || [],
    axisLine: { lineStyle: { color: axisColor.value } },
    axisTick: { show: false },
    axisLabel: {
      fontSize: 10,
      color: labelColor.value,
      interval: Math.floor((props.data.labels?.length || 30) / 8),
      margin: 10,
    },
  },

  // ── Y Axes ───────────────────────────────────────────────────
  yAxis: [
    {
      // left — attendance headcount
      type: 'value',
      name: 'Orang',
      nameTextStyle: { fontSize: 10, color: labelColor.value, padding: [0, 0, 0, -10] },
      minInterval: 1,
      axisLine: { show: false },
      axisTick: { show: false },
      axisLabel: { fontSize: 10, color: labelColor.value },
      splitLine: { lineStyle: { color: splitColor.value, type: 'dashed' } },
    },
    {
      // right — visit count
      type: 'value',
      name: 'Kunjungan',
      nameTextStyle: { fontSize: 10, color: labelColor.value },
      minInterval: 1,
      axisLine: { show: false },
      axisTick: { show: false },
      axisLabel: { fontSize: 10, color: labelColor.value },
      splitLine: { show: false },
    },
  ],

  // ── Series ───────────────────────────────────────────────────
  series: [
    {
      // Hadir = tepat_waktu only
      name: 'Hadir',
      type: 'bar',
      yAxisIndex: 0,
      data: props.data.present || [],
      stack: 'kehadiran',
      barCategoryGap: '40%',
      itemStyle: {
        color: C_PRESENT,
        borderRadius: [0, 0, 0, 0],
      },
      emphasis: {
        itemStyle: { color: seri.value.sukses, shadowBlur: 6, shadowColor: 'rgba(34,197,94,.4)' },
      },
    },
    {
      // Terlambat
      name: 'Terlambat',
      type: 'bar',
      yAxisIndex: 0,
      data: props.data.late || [],
      stack: 'kehadiran',
      barCategoryGap: '40%',
      itemStyle: {
        color: C_LATE,
        borderRadius: [4, 4, 0, 0],   // always rounded top (topmost in stack)
      },
      emphasis: {
        itemStyle: { color: seri.value.peringatan, shadowBlur: 6, shadowColor: 'rgba(245,158,11,.4)' },
      },
    },
    // "Tidak Hadir" series REMOVED — formula (total_sales - hadir) was inflated
    // and made the chart unreadable. Backend doesn't track true absent per day yet.
    {
      // Kunjungan — line on right Y axis
      name: 'Kunjungan',
      type: 'line',
      yAxisIndex: 1,
      data: props.data.visits || [],
      smooth: 0.35,
      symbol: 'circle',
      symbolSize: 5,
      lineStyle: { color: C_VISIT, width: 2.5 },
      itemStyle: {
        color: C_VISIT,
        borderWidth: 2,
        borderColor: borderClr.value,
      },
      areaStyle: {
        color: {
          type: 'linear', x: 0, y: 0, x2: 0, y2: 1,
          colorStops: [
            { offset: 0, color: 'rgba(249,115,22,.20)' },
            { offset: 1, color: 'rgba(249,115,22,.00)' },
          ],
        },
      },
    },
  ],
}))
</script>

<style scoped>
.chart {
  height: 300px;
  width: 100%;
}
</style>
