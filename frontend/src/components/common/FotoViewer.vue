<template>
  <Teleport to="body">
    <Transition name="foto-viewer">
      <div
        v-if="visible"
        class="foto-viewer"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
      >
        <div
          ref="stage"
          class="foto-stage"
          :class="{ 'bisa-geser': scale > 1, 'sedang-geser': dragging }"
          @wheel.prevent="onWheel"
          @pointerdown="onPointerDown"
          @pointermove="onPointerMove"
          @pointerup="onPointerUp"
          @pointercancel="onPointerUp"
          @dblclick="onDoubleClick"
        >
          <img
            ref="img"
            :src="src"
            :alt="title"
            class="foto-img"
            :class="{ halus: !dragging && !pinching }"
            :style="{ transform: `translate(${tx}px, ${ty}px) scale(${scale})` }"
            draggable="false"
          />
        </div>

        <header class="foto-bar">
          <span class="foto-judul">{{ title }}</span>
          <div class="foto-aksi">
            <button type="button" aria-label="Perkecil" :disabled="scale <= MIN" @click="zoomTengah(1 / LANGKAH)">
              <i class="pi pi-search-minus" />
            </button>
            <span class="foto-persen">{{ Math.round(scale * 100) }}%</span>
            <button type="button" aria-label="Perbesar" :disabled="scale >= MAX" @click="zoomTengah(LANGKAH)">
              <i class="pi pi-search-plus" />
            </button>
            <button type="button" aria-label="Ukuran awal" :disabled="scale === 1 && !tx && !ty" @click="reset">
              <i class="pi pi-refresh" />
            </button>
            <button type="button" class="foto-tutup" aria-label="Tutup" @click="tutup">
              <i class="pi pi-times" />
            </button>
          </div>
        </header>

        <p class="foto-petunjuk">Gulir atau cubit untuk memperbesar, seret untuk menggeser, klik dua kali untuk memperbesar cepat</p>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch, onBeforeUnmount } from 'vue'

const props = defineProps({
  visible: { type: Boolean, default: false },
  src: { type: String, default: '' },
  title: { type: String, default: 'Foto' },
})
const emit = defineEmits(['update:visible'])

const MIN = 1
const MAX = 6
const LANGKAH = 1.4

const stage = ref(null)
const img = ref(null)
const scale = ref(1)
const tx = ref(0)
const ty = ref(0)
const dragging = ref(false)
const pinching = ref(false)

// Pointer aktif: satu jari/mouse menggeser, dua jari mencubit
const pointers = new Map()
let jarakAwal = 0
let bergeser = false

function tutup() {
  emit('update:visible', false)
}

function reset() {
  scale.value = 1
  tx.value = 0
  ty.value = 0
}

/** Ukuran foto saat pas di layar (skala 1), mengikuti rasio aslinya. */
function ukuranPas() {
  const s = stage.value
  const i = img.value
  if (!s || !i || !i.naturalWidth) return { w: 0, h: 0 }
  const pas = Math.min(s.clientWidth / i.naturalWidth, s.clientHeight / i.naturalHeight)
  return { w: i.naturalWidth * pas, h: i.naturalHeight * pas }
}

/** Menahan geseran supaya foto tidak bisa diseret keluar layar. */
function batasi() {
  const s = stage.value
  if (!s) return
  const { w, h } = ukuranPas()
  const maxX = Math.max(0, (w * scale.value - s.clientWidth) / 2)
  const maxY = Math.max(0, (h * scale.value - s.clientHeight) / 2)
  tx.value = Math.min(maxX, Math.max(-maxX, tx.value))
  ty.value = Math.min(maxY, Math.max(-maxY, ty.value))
}

/** Apakah titik layar ini berada di atas foto (bukan area gelap di sekelilingnya). */
function diAtasFoto(clientX, clientY) {
  const s = stage.value
  if (!s) return false
  const kotak = s.getBoundingClientRect()
  const { w, h } = ukuranPas()
  const cx = kotak.left + kotak.width / 2 + tx.value
  const cy = kotak.top + kotak.height / 2 + ty.value
  return Math.abs(clientX - cx) <= (w * scale.value) / 2 && Math.abs(clientY - cy) <= (h * scale.value) / 2
}

/** Zoom dengan titik di bawah kursor/jari tetap di tempatnya. */
function zoomDi(clientX, clientY, faktor) {
  const s = stage.value
  if (!s) return
  const kotak = s.getBoundingClientRect()
  const cx = clientX - kotak.left - kotak.width / 2
  const cy = clientY - kotak.top - kotak.height / 2
  const baru = Math.min(MAX, Math.max(MIN, scale.value * faktor))
  const rasio = baru / scale.value
  tx.value = cx - (cx - tx.value) * rasio
  ty.value = cy - (cy - ty.value) * rasio
  scale.value = +baru.toFixed(3)
  batasi()
}

function zoomTengah(faktor) {
  const kotak = stage.value?.getBoundingClientRect()
  if (kotak) zoomDi(kotak.left + kotak.width / 2, kotak.top + kotak.height / 2, faktor)
}

function onWheel(e) {
  zoomDi(e.clientX, e.clientY, e.deltaY < 0 ? 1.15 : 1 / 1.15)
}

function onDoubleClick(e) {
  if (!diAtasFoto(e.clientX, e.clientY)) return
  if (scale.value > 1) reset()
  else zoomDi(e.clientX, e.clientY, 2.5)
}

function jarakDuaJari() {
  const [a, b] = [...pointers.values()]
  return Math.hypot(a.x - b.x, a.y - b.y)
}

function onPointerDown(e) {
  pointers.set(e.pointerId, { x: e.clientX, y: e.clientY })
  e.currentTarget.setPointerCapture?.(e.pointerId)
  bergeser = false
  if (pointers.size === 2) {
    pinching.value = true
    dragging.value = false
    jarakAwal = jarakDuaJari()
  } else if (pointers.size === 1) {
    dragging.value = scale.value > 1
  }
}

function onPointerMove(e) {
  const lama = pointers.get(e.pointerId)
  if (!lama) return
  const dx = e.clientX - lama.x
  const dy = e.clientY - lama.y
  pointers.set(e.pointerId, { x: e.clientX, y: e.clientY })
  if (Math.abs(dx) + Math.abs(dy) > 2) bergeser = true

  if (pointers.size === 2) {
    const [a, b] = [...pointers.values()]
    const jarak = jarakDuaJari()
    if (jarakAwal > 0) zoomDi((a.x + b.x) / 2, (a.y + b.y) / 2, jarak / jarakAwal)
    jarakAwal = jarak
  } else if (dragging.value) {
    tx.value += dx
    ty.value += dy
    batasi()
  }
}

function onPointerUp(e) {
  const hanyaKlik = pointers.size === 1 && !bergeser && e.type === 'pointerup'
  pointers.delete(e.pointerId)
  if (pointers.size < 2) pinching.value = false
  if (pointers.size === 0) dragging.value = false
  // Klik di area gelap (bukan di foto) menutup penampil
  if (hanyaKlik && !diAtasFoto(e.clientX, e.clientY)) tutup()
}

function onKey(e) {
  if (e.key === 'Escape') tutup()
  else if (e.key === '+' || e.key === '=') zoomTengah(LANGKAH)
  else if (e.key === '-') zoomTengah(1 / LANGKAH)
  else if (e.key === '0') reset()
}

watch(
  () => props.visible,
  (buka) => {
    if (buka) {
      reset()
      pointers.clear()
      document.addEventListener('keydown', onKey)
      document.body.style.overflow = 'hidden'
    } else {
      document.removeEventListener('keydown', onKey)
      document.body.style.overflow = ''
    }
  },
)

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKey)
  if (props.visible) document.body.style.overflow = ''
})
</script>

<style scoped>
.foto-viewer {
  position: fixed; inset: 0; z-index: 5000;
  background: rgb(8 8 10 / 0.94);
  color: #fff;
}
.foto-stage {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  overflow: hidden; touch-action: none; user-select: none; cursor: zoom-in;
}
.foto-stage.bisa-geser { cursor: grab; }
.foto-stage.sedang-geser { cursor: grabbing; }
.foto-img {
  width: 100%; height: 100%; object-fit: contain;
  transform-origin: center center; will-change: transform;
}
.foto-img.halus { transition: transform 0.18s ease-out; }

.foto-bar {
  position: absolute; top: 0; left: 0; right: 0;
  display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
  padding: 0.6rem 0.75rem;
  background: linear-gradient(to bottom, rgb(0 0 0 / 0.65), transparent);
  pointer-events: none;
}
.foto-judul { font-size: 0.9rem; font-weight: 600; text-shadow: 0 1px 2px rgb(0 0 0 / 0.6); }
.foto-aksi { display: flex; align-items: center; gap: 0.25rem; pointer-events: auto; }
.foto-aksi button {
  display: grid; place-items: center; width: 2.5rem; height: 2.5rem;
  border: 0; border-radius: 999px; background: rgb(255 255 255 / 0.12); color: #fff; cursor: pointer;
}
.foto-aksi button:hover:not(:disabled) { background: rgb(255 255 255 / 0.24); }
.foto-aksi button:disabled { opacity: 0.35; cursor: default; }
.foto-aksi .foto-tutup { margin-left: 0.5rem; background: rgb(255 255 255 / 0.2); }
.foto-persen { min-width: 3.2rem; text-align: center; font-size: 0.85rem; font-weight: 600; }

.foto-petunjuk {
  position: absolute; left: 0; right: 0; bottom: 0.75rem; margin: 0;
  text-align: center; font-size: 0.75rem; color: rgb(255 255 255 / 0.6);
  pointer-events: none; padding: 0 1rem;
}

.foto-viewer-enter-active, .foto-viewer-leave-active { transition: opacity 0.15s ease; }
.foto-viewer-enter-from, .foto-viewer-leave-to { opacity: 0; }

@media (max-width: 640px) {
  .foto-judul { display: none; }
  .foto-bar { justify-content: flex-end; }
}
</style>
