<template>
  <div class="bento-cell bento-muatan-chart">
    <div class="cell-head">
      <div class="cell-title-group">
        <span class="cell-title">Trend Kiriman & Muatan</span>
        <span class="cell-sub">30 hari terakhir, kg terkirim per hari</span>
      </div>
      <div class="head-right">
        <div class="chart-legend">
          <span class="leg"><span class="led led-muatan" />Muatan (kg)</span>
          <span class="leg"><span class="led led-jadwal" />Jadwal</span>
        </div>
        <RouterLink to="/pengiriman/riwayat" class="cell-link">
          Riwayat <i class="pi pi-arrow-right" />
        </RouterLink>
      </div>
    </div>
    <div class="cell-body">
      <div v-if="loading" class="chart-ph">
        <!-- Di mobile wadahnya dikunci 280px, jadi skeleton ikut menyesuaikan
             supaya tidak menjebol kotak saat memuat. -->
        <div class="skeleton chart-skel" />
      </div>
      <div v-else-if="kosong" class="chart-empty">
        <i class="pi pi-truck" />
        <span>Belum ada kiriman dalam 30 hari terakhir.</span>
      </div>
      <MuatanTrendChart v-else :data="data" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import MuatanTrendChart from '@/components/charts/MuatanTrendChart.vue'

const props = defineProps({
  data:    { type: Array,   default: () => [] },
  loading: { type: Boolean, default: true },
})

// Garis datar di angka nol terlihat seperti grafik rusak, jadi tampilkan pesan saja.
const kosong = computed(() => !props.data.some(r => Number(r.total_ton) > 0 || Number(r.total_jadwal) > 0))
</script>

<style scoped>
.head-right {
  display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;
}

.chart-empty {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 0.5rem; min-height: 180px; color: var(--t-muted); font-size: 0.85rem;
}
.chart-empty i { font-size: 1.5rem; opacity: 0.6; }

.chart-skel {
  height: 300px;
  border-radius: 10px;
}
@media (max-width: 768px) {
  .chart-skel { height: 100%; }
}

/* Titik warna keterangan grafik. Dulu ditulis di atribut style sehingga tidak
   bisa mengikuti tema; sekarang memakai token yang punya varian gelap. */
.led-muatan { background: var(--c-brand-500); }
.led-jadwal { background: var(--c-info); }
</style>
