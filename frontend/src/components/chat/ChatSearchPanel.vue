<template>
  <Transition name="search-panel-slide">
    <div v-if="visible" class="csp-overlay">
      <div class="csp-panel">

          <!-- Search input bar -->
          <div class="csp-header">
            <div class="csp-input-wrap">
              <i class="pi pi-search csp-icon" />
              <input
                ref="inputRef"
                v-model="query"
                class="csp-input"
                placeholder="Cari pesan…"
                @input="onInput"
                @keydown.escape="emit('close')"
              />
              <button v-if="query" class="csp-clear" @click="query = ''; results = []">
                <i class="pi pi-times" />
              </button>
            </div>
            <button class="csp-close-btn" @click="emit('close')">Batal</button>
          </div>

          <!-- Context label -->
          <div v-if="contactName" class="csp-context">
            <i class="pi pi-filter" />
            Mencari dalam: <strong>{{ contactName }}</strong>
          </div>

          <!-- Loading -->
          <div v-if="searching" class="csp-loading">
            <i class="pi pi-spin pi-spinner" />
            <span>Mencari…</span>
          </div>

          <!-- Empty state -->
          <div v-else-if="searched && results.length === 0" class="csp-empty">
            <i class="pi pi-search" />
            <span>Tidak ada pesan yang cocok dengan <em>"{{ query }}"</em></span>
          </div>

          <!-- Results -->
          <div v-else-if="results.length" class="csp-results">
            <div class="csp-results-label">{{ results.length }} hasil{{ results.length >= 30 ? ' (30 teratas)' : '' }}</div>
            <div
              v-for="msg in results"
              :key="msg.id"
              class="csp-result-item"
              @click="emit('jumpTo', msg)"
            >
              <div class="csp-result-avatar">{{ msg.sender_name?.[0]?.toUpperCase() }}</div>
              <div class="csp-result-body">
                <div class="csp-result-top">
                  <span class="csp-result-sender">{{ msg.sender_name }}</span>
                  <span class="csp-result-date">{{ msg.date }} · {{ msg.time }}</span>
                </div>
                <!-- Highlight matched text -->
                <div class="csp-result-text" v-html="highlight(msg.message)" />
              </div>
            </div>
          </div>

          <!-- Initial hint -->
          <div v-else class="csp-hint">
            <i class="pi pi-info-circle" />
            Ketik minimal 2 karakter untuk mencari
          </div>

      </div>
    </div>
  </Transition>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
import { chatService } from '@/services/chat.service'

const props = defineProps({
  visible:     { type: Boolean, required: true },
  contactId:   { type: Number,  default: null },
  contactType: { type: String,  default: null }, // 'admin'|'sales'|'group'
  contactName: { type: String,  default: null },
})

const emit = defineEmits(['close', 'jumpTo'])

const inputRef  = ref(null)
const query     = ref('')
const results   = ref([])
const searching = ref(false)
const searched  = ref(false)
let   debounce  = null

watch(() => props.visible, (val) => {
  if (val) {
    query.value   = ''
    results.value = []
    searched.value = false
    nextTick(() => inputRef.value?.focus())
  }
})

function onInput() {
  searched.value = false
  clearTimeout(debounce)
  if (query.value.trim().length < 2) {
    results.value = []
    return
  }
  debounce = setTimeout(doSearch, 400)
}

async function doSearch() {
  searching.value = true
  try {
    const res = await chatService.searchMessages(
      query.value.trim(),
      props.contactId,
      props.contactType,
    )
    results.value  = res.data || []
    searched.value = true
  } catch {
    results.value = []
  } finally {
    searching.value = false
  }
}

// Sanitize text untuk mencegah XSS — hapus semua tag HTML sebelum highlight
function sanitizeText(text) {
  if (!text) return ''
  // Escape karakter HTML agar tag tidak dirender
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#x27;')
}

// Highlight kata kunci di teks pesan — wrap dengan <mark>
// Sanitize dulu SEBELUM inject ke v-html untuk cegah XSS
function highlight(text) {
  if (!text || !query.value) return sanitizeText(text)
  const safe    = sanitizeText(text)
  const escaped = query.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  return safe.replace(new RegExp(`(${escaped})`, 'gi'), '<mark class="csp-mark">$1</mark>')
}
</script>
