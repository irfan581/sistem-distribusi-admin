<template>
  <div class="bento-cell bento-vtrend">
    <div class="cell-head">
      <div class="cell-title-group">
        <span class="cell-title">Tren Kunjungan Harian</span>
        <span class="cell-sub">Total kunjungan per hari, 30 hari terakhir</span>
      </div>
      <div class="chart-legend">
        <span class="leg"><span class="led" style="background:#f97316" />Kunjungan</span>
      </div>
    </div>
    <div class="cell-body">
      <div v-if="loading" class="chart-ph">
        <div class="skeleton" style="height:220px;border-radius:10px" />
      </div>
      <v-chart v-else class="vtrend-chart" :option="option" autoresize />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import VChart from 'vue-echarts'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { LineChart } from 'echarts/charts'
import { GridComponent, TooltipComponent } from 'echarts/components'

// echarts 6 tidak lagi menghitung grid.containLabel secara bawaan;
// tanpa fitur ini marginnya diabaikan dan label sumbu bisa terpotong.
import { LegacyGridContainLabel } from 'echarts/features'
import { useChartTheme } from '@/composables/useChartTheme'

const { axisColor, splitColor, labelColor, tooltipBg, tooltipBdr, tooltipTxt, borderClr } = useChartTheme()

use([CanvasRenderer, LineChart, GridComponent, TooltipComponent, LegacyGridContainLabel])

const props = defineProps({
  data:    { type: Object,  default: () => ({}) },
  loading: { type: Boolean, default: false },
})



const option = computed(() => ({
  backgroundColor: 'transparent',

  animation: true,
  animationDuration: 900,
  animationEasing: 'cubicOut',
  animationDelay: (idx) => idx * 20,
  animationDurationUpdate: 500,

  tooltip: {
    trigger: 'axis',
    axisPointer: {
      type: 'line',
      lineStyle: { color: 'rgba(249,115,22,.4)', type: 'dashed' },
    },
    backgroundColor: tooltipBg.value,
    borderColor: tooltipBdr.value,
    borderWidth: 1,
    padding: [10, 14],
    textStyle: { color: tooltipTxt.value, fontSize: 12 },
    extraCssText: 'box-shadow:0 4px 20px rgba(0,0,0,.15);border-radius:10px;',
    formatter(params) {
      const date = params[0]?.axisValue ?? ''
      const val  = params[0]?.value ?? 0
      return `
        <div style="font-weight:700;font-size:12px;margin-bottom:8px;color:${tooltipTxt.value}">${date}</div>
        <div style="display:flex;align-items:center;gap:8px">
          <span style="flex-shrink:0;width:9px;height:9px;border-radius:50%;background:#f97316"></span>
          <span style="color:${tooltipTxt.value}">Kunjungan</span>
          <span style="font-weight:700;color:${tooltipTxt.value};margin-left:auto">${val}</span>
        </div>`
    },
  },

  legend: { show: false },

  grid: { top: 12, left: 8, right: 16, bottom: 8, containLabel: true },

  xAxis: {
    type: 'category',
    data: props.data.labels || [],
    axisLine: { lineStyle: { color: axisColor.value } },
    axisTick: { show: false },
    axisLabel: {
      fontSize: 10,
      color: labelColor.value,
      interval: Math.floor((props.data.labels?.length || 30) / 7),
      margin: 10,
    },
  },

  yAxis: {
    type: 'value',
    name: 'Kunjungan',
    nameTextStyle: { fontSize: 10, color: labelColor.value, padding: [0, 0, 0, -10] },
    minInterval: 1,
    min: 0,
    axisLine: { show: false },
    axisTick: { show: false },
    axisLabel: { fontSize: 10, color: labelColor.value },
    splitLine: { lineStyle: { color: splitColor.value, type: 'dashed' } },
  },

  series: [{
    name: 'Kunjungan',
    type: 'line',
    data: props.data.visits || [],
    smooth: 0.4,
    symbol: 'circle',
    symbolSize: 6,
    lineStyle: { color: '#f97316', width: 2.5 },
    itemStyle: {
      color: '#f97316',
      borderWidth: 2.5,
      borderColor: borderClr.value,
    },
    emphasis: {
      itemStyle: {
        color: '#f97316',
        borderWidth: 3,
        shadowBlur: 8,
        shadowColor: 'rgba(249,115,22,.5)',
      },
    },
    areaStyle: {
      color: {
        type: 'linear', x: 0, y: 0, x2: 0, y2: 1,
        colorStops: [
          { offset: 0, color: 'rgba(249,115,22,.25)' },
          { offset: 1, color: 'rgba(249,115,22,.00)' },
        ],
      },
    },
  }],
}))
</script>

<style scoped>
.vtrend-chart {
  height: 240px;
  width: 100%;
}
</style>
