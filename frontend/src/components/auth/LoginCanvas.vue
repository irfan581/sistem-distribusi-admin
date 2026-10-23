<template>
  <aside class="lp-canvas" aria-hidden="true">
    <div class="ca-glow ca-glow-top" />
    <div class="ca-glow ca-glow-bottom" />
    <div class="ca-noise" />


    <div class="ca-body">
      <div class="ca-brand">
        <div class="ca-icon-frame">
          <img src="/logo-icon.png" alt="" class="ca-icon" />
        </div>
        <div class="ca-wordmark">
          <span class="ca-wordmark-main">Tani Makmur</span>
          <span class="ca-wordmark-sub">Sales &amp; Management Platform</span>
        </div>
      </div>

      <div class="ca-sep">
        <div class="ca-sep-pill" />
      </div>

      <ul class="ca-list" role="list">
        <li
          v-for="(cap, i) in caps"
          :key="cap.label"
          class="ca-list-item"
          :style="{ animationDelay: `${0.95 + i * 0.1}s` }"
        >
          <span class="ca-list-icon"><i :class="cap.icon" /></span>
          <div class="ca-list-copy">
            <span class="ca-list-title">{{ cap.label }}</span>
            <span class="ca-list-desc">{{ cap.desc }}</span>
          </div>
        </li>
      </ul>

      <div class="ca-status-row">
        <span class="ca-status-pip" />
        <span class="ca-status-text">All systems operational</span>
        <span class="ca-status-divider" />
        <span class="ca-status-time">{{ liveTime }}</span>
      </div>
    </div>

    <div class="ca-ring" />
  </aside>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

defineProps({
  caps: { type: Array, required: true },
})

const liveTime = ref('')
let timer = null

function tick() {
  liveTime.value = new Date().toLocaleTimeString('id-ID', {
    hour: '2-digit', minute: '2-digit', second: '2-digit'
  })
}

onMounted(() => { tick(); timer = setInterval(tick, 1000) })
onBeforeUnmount(() => clearInterval(timer))
</script>
