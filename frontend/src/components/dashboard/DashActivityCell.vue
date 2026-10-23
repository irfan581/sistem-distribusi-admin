<template>
  <div class="bento-cell bento-activity">
    <div class="cell-head">
      <div class="cell-title-group">
        <span class="cell-title">Aktivitas Realtime</span>
        <span class="cell-sub">Presensi hari ini</span>
      </div>
      <RouterLink to="/attendance" class="cell-link">Semua <i class="pi pi-arrow-right" /></RouterLink>
    </div>
    <div class="cell-body no-pad">
      <div v-if="loading" class="stream-skel">
        <div v-for="i in 6" :key="i" class="stream-skel-row">
          <div class="skeleton" style="width:32px;height:32px;border-radius:50%;flex-shrink:0" />
          <div class="skeleton-lines">
            <div class="skeleton" style="height:10px" />
            <div class="skeleton" style="height:9px;width:60%" />
          </div>
        </div>
      </div>
      <div v-else-if="!activities.length" class="cell-empty">
        <div class="empty-icon"><i class="pi pi-inbox" /></div>
        <span>Belum ada aktivitas hari ini</span>
      </div>
      <ul v-else class="stream-list">
        <li
          v-for="(item, idx) in activities.slice(0, 8)"
          :key="item.created_at + item.sales_nama"
          class="stream-item"
          :style="{ animationDelay: idx * 0.04 + 's' }"
        >
          <div class="stream-avatar">{{ (item.sales_nama || '?')[0].toUpperCase() }}</div>
          <div class="stream-info">
            <span class="stream-name">{{ item.sales_nama }}</span>
            <span class="stream-desc">{{ item.status }}</span>
          </div>
          <span class="stream-time">{{ item.waktu }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
defineProps({
  activities: { type: Array,   required: true },
  loading:    { type: Boolean, default: false },
})
</script>
