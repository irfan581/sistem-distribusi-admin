<template>
  <Teleport to="body">
    <Transition name="mention-drop">
      <div
        v-if="visible && list.length"
        class="mention-dropdown"
        :style="{ bottom: `${bottom}px`, left: `${left}px`, width: `${width}px` }"
      >
        <div
          v-for="(m, i) in list"
          :key="`${m.type}-${m.id}`"
          class="mention-item"
          :class="{ 'mention-item--active': i === activeIdx }"
          @mousedown.prevent="emit('pick', m)"
        >
          <div class="mention-avatar" :class="m.type === 'admin' ? 'mention-avatar--admin' : ''">
            {{ m.nama?.[0]?.toUpperCase() || '?' }}
          </div>
          <div class="mention-info">
            <span class="mention-name">{{ m.nama }}</span>
            <span class="mention-role">{{ m.type === 'admin' ? 'Admin' : 'Sales' }}</span>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
defineProps({
  visible:   { type: Boolean, default: false },
  list:      { type: Array,   default: () => [] },
  activeIdx: { type: Number,  default: 0 },
  bottom:    { type: Number,  default: 80 },
  left:      { type: Number,  default: 0 },
  width:     { type: Number,  default: 260 },
})

const emit = defineEmits(['pick'])
</script>
