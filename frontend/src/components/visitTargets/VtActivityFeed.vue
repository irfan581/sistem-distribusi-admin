<template>
  <div class="vt-feed-panel">
    <div class="feed-header">
      <div class="feed-header-icon"><i class="pi pi-bolt" /></div>
      <span class="feed-title">Aktivitas Realtime</span>
      <span class="feed-count">{{ store.realtimeLog.length }}</span>
    </div>
    <div class="feed-list" ref="feedListEl">
      <TransitionGroup name="feed-item">
        <div
          v-for="log in store.realtimeLog"
          :key="log.at_iso + log.sales_id"
          class="feed-item"
        >
          <div class="feed-dot" />
          <div class="feed-body">
            <span class="feed-name">{{ log.sales_nama }}</span>
            <span class="feed-target">→ {{ log.target }} toko/hari</span>
            <span class="feed-meta">oleh {{ log.set_by }} · {{ log.at }}</span>
          </div>
        </div>
      </TransitionGroup>
      <div v-if="store.realtimeLog.length === 0" class="feed-empty">
        <i class="pi pi-wifi" />
        <span>Menunggu perubahan realtime…</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  store: { type: Object, required: true },
})

const feedListEl = ref(null)

watch(() => props.store.realtimeLog.length, () => {
  if (feedListEl.value) feedListEl.value.scrollTop = 0
})
</script>
