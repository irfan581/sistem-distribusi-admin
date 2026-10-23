<template>
  <Dialog
    v-model:visible="localVisible"
    modal
    header="Teruskan Pesan"
    :style="{ width: '360px', maxWidth: '95vw' }"
    :draggable="false"
    @hide="emit('close')"
  >
    <!-- Preview pesan yang akan diteruskan -->
    <div class="fwd-preview">
      <i class="pi pi-share-alt fwd-preview__icon" />
      <span class="fwd-preview__text">{{ previewText }}</span>
    </div>

    <!-- Search kontak -->
    <div class="fwd-search-wrap">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="query" placeholder="Cari kontak atau grup…" fluid autofocus />
      </IconField>
    </div>

    <!-- Daftar tujuan -->
    <div class="fwd-list">
      <div
        v-for="item in filtered"
        :key="`${item.type}-${item.id}`"
        class="fwd-item"
        :class="{ 'fwd-item--selected': isSelected(item) }"
        @click="toggleSelect(item)"
      >
        <div class="fwd-item__avatar" :class="`avatar-${item.type}`">
          {{ item.type === 'group' ? (item.nama?.[0]?.toUpperCase() || 'G') : item.initial }}
        </div>
        <div class="fwd-item__info">
          <span class="fwd-item__name">{{ item.nama }}</span>
          <span class="fwd-item__sub">
            {{ item.type === 'group' ? `${item.member_count || 0} anggota` : item.area || item.type }}
          </span>
        </div>
        <i v-if="isSelected(item)" class="pi pi-check-circle fwd-item__check" />
      </div>

      <div v-if="filtered.length === 0" class="fwd-empty">
        <i class="pi pi-users" />
        <span>Tidak ada kontak ditemukan</span>
      </div>
    </div>

    <template #footer>
      <button class="dlg-btn dlg-cancel" @click="emit('close')" :disabled="sending">
        Batal
      </button>
      <button
        class="dlg-btn dlg-ok"
        :disabled="selected.length === 0 || sending"
        @click="doForward"
      >
        <i v-if="sending" class="pi pi-spin pi-spinner" />
        <i v-else class="pi pi-share-alt" />
        Teruskan{{ selected.length ? ` (${selected.length})` : '' }}
      </button>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  visible:  { type: Boolean, default: false },
  msg:      { type: Object,  default: null },
  contacts: { type: Object,  default: () => ({}) },
})
const emit = defineEmits(['close', 'forward'])

const localVisible = ref(props.visible)
watch(() => props.visible, v => { localVisible.value = v })

const query    = ref('')
const selected = ref([])
const sending  = ref(false)

// Build flat list: admins + sales + groups (exclude current contact if needed)
const allTargets = computed(() => {
  const admins = (props.contacts?.admins || []).map(c => ({ ...c, type: 'admin' }))
  const sales  = (props.contacts?.sales  || []).map(c => ({ ...c, type: 'sales' }))
  const groups = (props.contacts?.groups || []).map(c => ({ ...c, type: 'group' }))
  return [...admins, ...sales, ...groups]
})

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return allTargets.value
  return allTargets.value.filter(c => c.nama?.toLowerCase().includes(q))
})

const previewText = computed(() => {
  if (!props.msg) return ''
  if (props.msg.message_type === 'image') return '📷 Gambar'
  if (props.msg.message_type === 'file')  return `📎 ${props.msg.file_name || 'File'}`
  return props.msg.message || ''
})

function isSelected(item) {
  return selected.value.some(s => s.type === item.type && s.id === item.id)
}

function toggleSelect(item) {
  if (isSelected(item)) {
    selected.value = selected.value.filter(s => !(s.type === item.type && s.id === item.id))
  } else {
    if (selected.value.length >= 5) return  // maks 5 tujuan
    selected.value.push({ type: item.type, id: item.id })
  }
}

async function doForward() {
  if (!props.msg || selected.value.length === 0) return
  sending.value = true
  try {
    emit('forward', { msg: props.msg, targets: selected.value })
  } finally {
    sending.value = false
  }
}

// Reset saat dialog dibuka ulang
watch(() => props.visible, v => {
  if (v) { query.value = ''; selected.value = [] }
})
</script>
