<template>
  <v-chart class="chart" :option="option" autoresize />
</template>

<script setup>
import { computed } from 'vue'
import VChart from 'vue-echarts'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { BarChart } from 'echarts/charts'
import { GridComponent, TooltipComponent } from 'echarts/components'

// echarts 6 tidak lagi menghitung grid.containLabel secara bawaan;
// tanpa fitur ini marginnya diabaikan dan label sumbu bisa terpotong.
import { LegacyGridContainLabel } from 'echarts/features'
import { useChartTheme } from '@/composables/useChartTheme'

const { axisColor, splitColor, labelColor, tooltipBg, tooltipBdr, tooltipTxt, isDark, seri } = useChartTheme()

use([CanvasRenderer, BarChart, GridComponent, TooltipComponent, LegacyGridContainLabel])

const props = defineProps({
  data: { type: Object, default: () => ({}) },
})



const option = computed(() => ({
  backgroundColor: 'transparent',

  animation: true,
  animationDuration: 800,
  animationEasing: 'cubicOut',
  animationDelay: (idx) => idx * 25,
  animationDurationUpdate: 500,
  animationEasingUpdate: 'cubicInOut',

  tooltip: {
    trigger: 'axis',
    axisPointer: {
      type: 'shadow',
      shadowStyle: { color: isDark.value ? 'rgba(255,255,255,.03)' : 'rgba(0,0,0,.03)' },
    },
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
    name: 'Orang',
    nameTextStyle: { fontSize: 10, color: labelColor.value, padding: [0, 0, 0, -10] },
    minInterval: 1,
    axisLine: { show: false },
    axisTick: { show: false },
    axisLabel: { fontSize: 10, color: labelColor.value },
    splitLine: { lineStyle: { color: splitColor.value, type: 'dashed' } },
  },

  series: [
    {
      name: 'Hadir',
      type: 'bar',
      data: props.data.present || [],
      stack: 'kehadiran',
      barCategoryGap: '20%',
      // Batas keras: sela proporsional saja tidak menahan lebar saat
      // datanya sedikit. Angkanya dipilih supaya lebar batang dan sela
      // kira-kira seimbang saat data sedikit (5 bulan: ~100px : ~100px).
      // Hanya mengikat saat data sedikit — di 12 bulan lebar kategorinya
      // sudah di bawah batas ini, jadi tampilannya tidak berubah.
      // Harus sama di kedua seri karena keduanya satu stack.
      barMaxWidth: 100,
      itemStyle: {
        color: '#22c55e',
        borderRadius: [0, 0, 0, 0],
      },
      emphasis: {
        itemStyle: { color: seri.value.sukses, shadowBlur: 6, shadowColor: 'rgba(34,197,94,.4)' },
      },
    },
    {
      name: 'Terlambat',
      type: 'bar',
      data: props.data.late || [],
      stack: 'kehadiran',
      barCategoryGap: '20%',
      // Batas keras: sela proporsional saja tidak menahan lebar saat
      // datanya sedikit. Angkanya dipilih supaya lebar batang dan sela
      // kira-kira seimbang saat data sedikit (5 bulan: ~100px : ~100px).
      // Hanya mengikat saat data sedikit — di 12 bulan lebar kategorinya
      // sudah di bawah batas ini, jadi tampilannya tidak berubah.
      // Harus sama di kedua seri karena keduanya satu stack.
      barMaxWidth: 100,
      itemStyle: {
        color: '#f59e0b',
        borderRadius: [4, 4, 0, 0],
      },
      emphasis: {
        itemStyle: { color: seri.value.peringatan, shadowBlur: 6, shadowColor: 'rgba(245,158,11,.4)' },
      },
    },
  ],
}))
</script>

<style scoped>
.chart {
  height: 260px;
  width: 100%;
}
</style>
