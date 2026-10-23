<template>
  <div class="map-legend">
    <div class="legend-item">
      <div class="legend-marker" style="background:#16a34a">S</div>
      <span>Dalam Toko</span>
    </div>
    <div class="legend-item">
      <div class="legend-marker" style="background:#dc2626">S</div>
      <span>Selesai</span>
    </div>
    <div class="legend-item">
      <div class="legend-marker" style="background:#3b82f6;border-radius:4px">↑</div>
      <span>Absen Masuk</span>
    </div>
    <div class="legend-item">
      <div class="legend-marker" style="background:#f97316;border-radius:4px">↓</div>
      <span>Absen Pulang</span>
    </div>
    <div class="legend-item">
      <div class="legend-cluster">42</div>
      <span>Cluster (zoom untuk buka)</span>
    </div>
    <div class="legend-item">
      <div class="legend-line" />
      <span>Jalur per Sales</span>
    </div>
  </div>
</template>

<style scoped>
.map-legend {
  display: flex; gap: 1rem;
  padding: 0.6rem 1rem;
  border-top: 1px solid var(--s-border);
  flex-wrap: wrap; align-items: center;
}
.legend-item { display: flex; align-items: center; gap: 0.4rem; font-size: 0.75rem; color: var(--p-text-muted-color); }
.legend-marker {
  width: 22px; height: 22px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 10px; font-weight: 800; border: 2px solid var(--s-0);
  box-shadow: 0 1px 4px rgba(0,0,0,0.2);
}
.legend-cluster {
  width: 28px; height: 28px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  background: var(--c-success); color: #fff; font-size: 10px; font-weight: 800;
  border: 3px solid var(--s-0); box-shadow: 0 1px 6px #16a34a66;
}
.legend-line {
  width: 28px; height: 3px;
  background: repeating-linear-gradient(to right, var(--c-info) 0, var(--c-info) 6px, transparent 6px, transparent 10px);
  border-radius: 2px;
}
</style>
