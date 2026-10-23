<template>
  <div class="vt-analytics-grid">
    <div class="vt-stat-card">
      <div class="stat-icon stat-icon-orange"><i class="pi pi-users" /></div>
      <div class="stat-body">
        <span class="stat-value">{{ store.totalSales }}</span>
        <span class="stat-label">Total Sales Aktif</span>
      </div>
    </div>
    <div class="vt-stat-card">
      <div class="stat-icon stat-icon-amber"><i class="pi pi-chart-bar" /></div>
      <div class="stat-body">
        <span class="stat-value">{{ store.avgTarget }}</span>
        <span class="stat-label">Rata-rata Target / Hari</span>
      </div>
    </div>
    <div class="vt-stat-card">
      <div class="stat-icon stat-icon-green"><i class="pi pi-arrow-up" /></div>
      <div class="stat-body">
        <span class="stat-value">{{ store.maxTarget }}</span>
        <span class="stat-label">Target Tertinggi</span>
      </div>
    </div>
    <div class="vt-stat-card">
      <div class="stat-icon stat-icon-blue"><i class="pi pi-arrow-down" /></div>
      <div class="stat-body">
        <span class="stat-value">{{ store.minTarget }}</span>
        <span class="stat-label">Target Terendah</span>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  store: { type: Object, required: true },
})
</script>
