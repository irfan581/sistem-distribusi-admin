<template>
  <v-chart class="chart" :option="option" autoresize />
</template>

<script setup>
import { computed } from 'vue'
import VChart from 'vue-echarts'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { PieChart } from 'echarts/charts'
import { TooltipComponent, LegendComponent, GraphicComponent } from 'echarts/components'
import { useChartTheme } from '@/composables/useChartTheme'

const { labelColor, centerColor, subColor, tooltipBg, tooltipBdr, tooltipTxt } = useChartTheme()

use([CanvasRenderer, PieChart, TooltipComponent, LegendComponent, GraphicComponent])

const props = defineProps({
  present: { type: Number, default: 0 },
  late:    { type: Number, default: 0 },
  absent:  { type: Number, default: 0 },
  permit:  { type: Number, default: 0 },
})

const total    = computed(() => props.present + props.late + props.absent + props.permit)

// ── Dark-mode reactive colors ─────────────────────────────────

const option = computed(() => ({
  backgroundColor: 'transparent',

  // ── Animations ───────────────────────────────────────────────
  animation: true,
  animationDuration: 1000,
  animationEasing: 'cubicOut',
  animationType: 'scale',          // slices scale in from center
  animationDelay: (idx) => idx * 80,
  animationDurationUpdate: 500,
  animationEasingUpdate: 'cubicInOut',

  // ── Tooltip ──────────────────────────────────────────────────
  tooltip: {
    trigger: 'item',
    backgroundColor: tooltipBg.value,
    borderColor: tooltipBdr.value,
    borderWidth: 1,
    padding: [10, 14],
    textStyle: { color: tooltipTxt.value, fontSize: 12 },
    extraCssText: 'box-shadow:0 4px 20px rgba(0,0,0,.15);border-radius:10px;',
    formatter: (p) =>
      `<div style="display:flex;align-items:center;gap:8px">
         <span style="flex-shrink:0;width:9px;height:9px;border-radius:2px;background:${p.color}"></span>
         <span style="color:${tooltipTxt.value};font-weight:700">${p.name}</span>
         <span style="color:${tooltipTxt.value}">${p.value}</span>
         <span style="color:${labelColor.value}">(${p.percent}%)</span>
       </div>`,
  },

  // ── Legend ───────────────────────────────────────────────────
  legend: {
    bottom: 0,
    left: 'center',
    textStyle: { fontSize: 11.5, color: labelColor.value },
    icon: 'roundRect',
    itemWidth: 12,
    itemHeight: 6,
    itemGap: 16,
  },

  // ── Center text overlay ──────────────────────────────────────
  graphic: total.value > 0 ? [
    {
      type: 'text',
      left: 'center',
      top: '31%',
      style: {
        text: String(total.value),
        textAlign: 'center',
        fontSize: 24,
        fontWeight: '800',
        fill: centerColor.value,
        fontFamily: 'inherit',
      },
    },
    {
      type: 'text',
      left: 'center',
      top: '43%',
      style: {
        text: 'Total',
        textAlign: 'center',
        fontSize: 11,
        fontWeight: '600',
        fill: subColor.value,
        fontFamily: 'inherit',
      },
    },
  ] : [],

  // ── Series ───────────────────────────────────────────────────
  series: [{
    type: 'pie',
    radius: ['52%', '76%'],
    center: ['50%', '40%'],
    avoidLabelOverlap: false,
    label: { show: false },
    labelLine: { show: false },
    // Keep existing slice colors unchanged
    data: [
      { value: props.present, name: 'Hadir',       itemStyle: { color: '#22c55e' } },
      { value: props.late,    name: 'Terlambat',   itemStyle: { color: '#f59e0b' } },
      { value: props.absent,  name: 'Tidak Hadir', itemStyle: { color: '#ef4444' } },
      { value: props.permit,  name: 'Izin',        itemStyle: { color: '#3b82f6' } },
    ],
    // ── Hover emphasis ─────────────────────────────────────────
    emphasis: {
      scale: true,
      scaleSize: 8,
      label: { show: false },
      itemStyle: {
        shadowBlur: 20,
        shadowColor: 'rgba(0,0,0,.25)',
      },
    },
    // Subtle gap between slices
    itemStyle: {
      borderWidth: 2,
      borderColor: 'transparent',
    },
  }],
}))
</script>

<style scoped>
.chart {
  height: 240px;
  width: 100%;
}
</style>
