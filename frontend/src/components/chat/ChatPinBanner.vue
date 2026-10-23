<template>
  <Transition name="pin-banner">
    <div v-if="pin" class="pin-banner" @click="emit('jumpToPin', pin.message_id)">
      <span class="pin-icon"><i class="pi pi-bookmark-fill" /></span>
      <div class="pin-content">
        <span class="pin-label">Pesan Disematkan</span>
        <span class="pin-text">{{ previewText }}</span>
      </div>
      <button
        v-if="canUnpin"
        class="pin-unpin-btn"
        title="Lepas sematan"
        @click.stop="emit('unpin', pin.message_id)"
      >
        <i class="pi pi-times" />
      </button>
    </div>
  </Transition>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  pin:      { type: Object, default: null },
  canUnpin: { type: Boolean, default: true },
})
const emit = defineEmits(['jumpToPin', 'unpin'])

const previewText = computed(() => {
  if (!props.pin) return ''
  if (props.pin.message_type === 'image') return '📷 Gambar'
  if (props.pin.message_type === 'file')  return `📎 ${props.pin.file_name || 'File'}`
  return props.pin.message || ''
})
</script>
