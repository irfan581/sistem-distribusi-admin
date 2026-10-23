<template>
  <div class="bento-cell bento-visit">
    <div class="cell-head">
      <div class="cell-title-group">
        <span class="cell-title">Peringkat Sales</span>
        <span class="cell-sub">{{ currentMonth }}, 10 kunjungan terbanyak</span>
      </div>
      <RouterLink to="/kunjungan" class="cell-link">
        Semua <i class="pi pi-arrow-right" />
      </RouterLink>
    </div>
    <div class="cell-body">
      <div v-if="loading" class="vskel-wrap">
        <div v-for="i in 5" :key="i" class="vskel-row">
          <div class="skeleton" style="width:80px;height:10px;border-radius:4px;flex-shrink:0" />
          <div class="skeleton" :style="`height:14px;border-radius:4px;width:${25+i*12}%`" />
        </div>
      </div>
      <div v-else-if="!items.length" class="cell-empty">
        <div class="empty-icon"><i class="pi pi-map-marker" /></div>
        <span>Belum ada kunjungan bulan ini</span>
      </div>
      <v-chart v-else class="visit-chart" :option="option" autoresize />
    </div>
  </div>
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

const { splitColor, labelColor, tooltipBg, tooltipBdr, tooltipTxt, isDark } = useChartTheme()

use([CanvasRenderer, BarChart, GridComponent, TooltipComponent, LegacyGridContainLabel])

const props = defineProps({
  items:        { type: Array,   default: () => [] },
  loading:      { type: Boolean, default: false },
  currentMonth: { type: String,  default: '' },
})



// ── Blue gradient: lightest (#bfdbfe) → darkest (#1e40af)
// Biru, bukan oranye brand: chart ini bersebelahan dengan "Tren Kunjungan"
// yang juga soal kunjungan, jadi warnanya dibedakan supaya tidak terbaca
// sebagai dua tampilan dari data yang sama.
// Bars are sorted ascending (lowest at top, highest at bottom),
// so bar index 0 = lowest count → lightest, last = highest → darkest
const BRAND_GRADIENT = [
  '#bfdbfe', // 200 — lightest
  '#93c5fd', // 300
  '#60a5fa', // 400
  '#3b82f6', // 500
  '#2f76e8', // between 500–600
  '#2563eb', // 600
  '#1f5ede', // deeper
  '#1d4ed8', // 700
  '#1a45c4', // deeper
  '#1e40af', // 800 — darkest
]

// Di mode gelap urutannya dibalik. Aturan "makin tinggi makin pekat" hanya
// bekerja di atas kertas putih; di atas kanvas gelap justru warna paling
// terang yang paling menonjol, sedangkan #1e40af nyaris lenyap ke latar.
const BRAND_GRADIENT_GELAP = [...BRAND_GRADIENT].reverse()
const gradasi = computed(() => (isDark.value ? BRAND_GRADIENT_GELAP : BRAND_GRADIENT))

function shortName(nama) {
  if (!nama) return '?'
  const parts = nama.trim().split(' ')
  return parts.length >= 2 ? parts[0] + ' ' + parts[1][0] + '.' : parts[0]
}

const option = computed(() => {
  // Sort ascending so highest value bar appears at bottom (most prominent)
  const sorted = [...props.items].sort((a, b) => a.kunjungan - b.kunjungan)
  const n      = sorted.length   // actual count (≤10)
  const names  = sorted.map(i => shortName(i.nama))

  const values = sorted.map((item, idx) => {
    // Map idx to gradient: lower bars (idx=0) get lightest, higher bars get darkest
    // Use proportional mapping across available colors
    const colorIdx = Math.round((idx / Math.max(n - 1, 1)) * (gradasi.value.length - 1))
    return {
      value: item.kunjungan,
      itemStyle: {
        color: gradasi.value[colorIdx],
        borderRadius: [0, 5, 5, 0],
      },
    }
  })

  return {
    backgroundColor: 'transparent',

    // ── Animations ─────────────────────────────────────────────
    animation: true,
    animationDuration: 1000,
    animationEasing: 'cubicOut',
    animationDelay: (idx) => idx * 60,
    animationDurationUpdate: 500,
    animationEasingUpdate: 'cubicInOut',

    // ── Tooltip ────────────────────────────────────────────────
    tooltip: {
      trigger: 'axis',
      axisPointer: { type: 'shadow' },
      backgroundColor: tooltipBg.value,
      borderColor: tooltipBdr.value,
      borderWidth: 1,
      padding: [10, 14],
      textStyle: { color: tooltipTxt.value, fontSize: 12 },
      extraCssText: 'box-shadow:0 4px 20px rgba(0,0,0,.15);border-radius:10px;',
      formatter: (params) => {
        const p = params[0]
        return `
          <div style="font-weight:700;font-size:12px;color:${tooltipTxt.value};margin-bottom:6px">${p.name}</div>
          <div style="display:flex;align-items:center;gap:8px">
            <span style="flex-shrink:0;width:9px;height:9px;border-radius:50%;background:${p.color}"></span>
            <span style="color:${tooltipTxt.value}">${p.value} kunjungan</span>
          </div>`
      },
    },

    // ── Grid ───────────────────────────────────────────────────
    grid: { top: 8, left: 8, right: 52, bottom: 8, containLabel: true },

    // ── X Axis ─────────────────────────────────────────────────
    xAxis: {
      type: 'value',
      minInterval: 1,
      axisLabel: { fontSize: 10, color: labelColor.value },
      axisLine: { show: false },
      axisTick: { show: false },
      splitLine: { lineStyle: { color: splitColor.value, type: 'dashed' } },
    },

    // ── Y Axis ─────────────────────────────────────────────────
    yAxis: {
      type: 'category',
      data: names,
      axisLine: { show: false },
      axisTick: { show: false },
      axisLabel: {
        fontSize: 11,
        color: labelColor.value,
        width: 72,
        overflow: 'truncate',
      },
    },

    // ── Series ─────────────────────────────────────────────────
    series: [{
      type: 'bar',
      data: values,
      barMaxWidth: 18,
      label: {
        show: true,
        position: 'right',
        fontSize: 10,
        fontWeight: 700,
        color: labelColor.value,
        formatter: '{c}',
      },
    }],
  }
})
</script>

<style scoped>
.vskel-wrap {
  display: flex;
  flex-direction: column;
  gap: 0.7rem;
  padding: 0.5rem 0;
}
.vskel-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}
.visit-chart {
  height: 260px;
  width: 100%;
}
@media (max-width: 768px) {
  .visit-chart { height: 260px; }
}
</style>
