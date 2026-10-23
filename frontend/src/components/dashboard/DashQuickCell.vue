<template>
  <div class="bento-cell bento-quick">
    <div class="cell-head">
      <div class="cell-title-group"><span class="cell-title">Akses Cepat</span></div>
    </div>
    <div class="cell-body">
      <div class="quick-grid">
        <RouterLink v-for="q in quickLinks" :key="q.to" :to="q.to" class="quick-btn">
          <div class="quick-icon" :class="`qi-${q.color}`"><i :class="q.icon" /></div>
          <span class="quick-label">{{ q.label }}</span>
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  quickLinks: { type: Array, required: true },
})
</script>
