<template>
  <div class="sales-avatar-wrap" :style="wrapStyle">
    <img
      v-if="src && !imgError"
      :src="src"
      :alt="nama"
      class="sales-avatar-img"
      :style="imgStyle"
      @error="imgError = true"
    />
    <span v-else class="sales-avatar-label" :style="labelStyle">{{ initial }}</span>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  src:   { type: String,  default: null },
  nama:  { type: String,  default: '' },
  size:  { type: Number,  default: 32 },
  shape: { type: String,  default: 'circle' }, // 'circle' | 'square'
})

const imgError = ref(false)

const initial  = computed(() => props.nama?.[0]?.toUpperCase() || '?')
const radius   = computed(() => props.shape === 'circle' ? '50%' : '8px')
const fontSize = computed(() => `${Math.round(props.size * 0.4)}px`)

const wrapStyle = computed(() => ({
  width:           `${props.size}px`,
  height:          `${props.size}px`,
  borderRadius:    radius.value,
  flexShrink:      '0',
  overflow:        'hidden',
  display:         'flex',
  alignItems:      'center',
  justifyContent:  'center',
  background:      'var(--p-primary-100)',
}))

const imgStyle = computed(() => ({
  width:        '100%',
  height:       '100%',
  objectFit:    'cover',
  borderRadius: radius.value,
}))

const labelStyle = computed(() => ({
  fontSize:   fontSize.value,
  fontWeight: '700',
  color:      'var(--p-primary-700)',
  lineHeight:  '1',
}))
</script>
