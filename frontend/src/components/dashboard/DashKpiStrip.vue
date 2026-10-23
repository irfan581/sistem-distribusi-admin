<template>
  <div class="kpi-strip">
    <div
      v-for="(kpi, idx) in kpiItems"
      :key="kpi.label"
      class="kpi-card"
      :class="`kpi-${kpi.color}`"
      :style="{ animationDelay: `${idx * 55}ms` }"
    >
      <div class="kpi-bar" />

      <div class="kpi-head">
        <span class="kpi-icon"><i :class="kpi.icon" /></span>
        <span class="kpi-label">{{ kpi.label }}</span>
      </div>

      <div class="kpi-value">
        <div v-if="loading" class="kpi-skel" />
        <span v-else class="slot" aria-hidden="true">
          <span
            v-for="(digit, di) in digitsOf(kpi.value)"
            :key="di"
            class="slot-window"
          >
            <span class="slot-reel" :style="reelStyle(idx, di, digit)">
              <span v-for="(n, ni) in REEL" :key="ni" class="slot-cell">{{ n }}</span>
            </span>
          </span>
        </span>
        <!-- Angka sebenarnya untuk pembaca layar & copy-paste; reel di
             atas murni dekoratif dan tidak terbaca utuh oleh screen reader. -->
        <span v-if="!loading" class="sr-only">{{ kpi.value ?? 0 }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, nextTick } from 'vue'

const props = defineProps({
  kpiItems: { type: Array,   required: true },
  loading:  { type: Boolean, default: false },
})

// Berapa kali reel berputar penuh sebelum berhenti di angka tujuan.
const PUTARAN  = 4
// Durasi digit paling kiri; tiap digit ke kanan berhenti lebih lambat,
// jadi berhentinya berurutan kiri → kanan seperti mesin slot.
const DURASI_MS = 750
const JEDA_MS   = 160

// 0-9 diulang (PUTARAN + 1) kali: PUTARAN siklus untuk efek berputar,
// satu siklus terakhir tempat digit tujuan berada.
const REEL = Array.from(
  { length: (PUTARAN + 1) * 10 },
  (_, i) => i % 10,
)

// false = reel diam di posisi 0 tanpa transisi, true = meluncur ke tujuan.
const berputar = ref(props.kpiItems.map(() => false))

function digitsOf(value) {
  return String(Math.max(0, parseInt(value) || 0)).split('')
}

function reelStyle(idx, di, digit) {
  if (!berputar.value[idx]) {
    // Posisi awal: transisi dimatikan supaya lompatan balik ke 0 tidak terlihat.
    return { transform: 'translateY(0)', transition: 'none' }
  }

  const langkah = PUTARAN * 10 + Number(digit)

  return {
    transform: `translateY(calc(var(--slot-h) * -${langkah}))`,
    transitionDuration: `${DURASI_MS + di * JEDA_MS}ms`,
  }
}

async function putarReel() {
  berputar.value = props.kpiItems.map(() => false)

  // Dua frame: satu untuk Vue menerapkan posisi awal + transition:none,
  // satu lagi supaya browser mendaftarkannya sebelum transisi dinyalakan.
  await nextTick()
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      berputar.value = props.kpiItems.map(() => true)
    })
  })
}

// Putar saat loading selesai
watch(
  () => props.loading,
  (baru, lama) => {
    if (!baru && lama) putarReel()
  },
)

// Putar lagi kalau angkanya berubah di luar loading (refresh berkala)
watch(
  () => props.kpiItems.map(k => k.value),
  () => { if (!props.loading) putarReel() },
)

onMounted(() => {
  if (!props.loading) putarReel()
})
</script>

<style scoped>
.slot {
  --slot-h: 1.15em;
  display: inline-flex;
  align-items: flex-start;
}

.slot-window {
  height: var(--slot-h);
  overflow: hidden;
  display: inline-block;
}

.slot-reel {
  display: block;
  will-change: transform;
  /* Deselerasi di akhir — reel melambat lalu berhenti, bukan berhenti mendadak. */
  transition-property: transform;
  transition-timing-function: cubic-bezier(.16, .84, .28, 1);
}

.slot-cell {
  display: block;
  height: var(--slot-h);
  line-height: var(--slot-h);
  text-align: center;
  font-variant-numeric: tabular-nums;
}

/* Hormati preferensi sistem: tanpa animasi, angka langsung di posisi akhir. */
@media (prefers-reduced-motion: reduce) {
  .slot-reel { transition: none !important; }
}

.sr-only {
  position: absolute;
  width: 1px; height: 1px;
  padding: 0; margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
