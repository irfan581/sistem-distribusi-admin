<template>
  <Teleport to="body">
    <Transition name="reaction-picker">
      <div
        v-if="visible"
        class="reaction-picker"
        :style="pickerStyle"
        @click.stop
      >
        <button
          v-for="emoji in EMOJIS"
          :key="emoji"
          class="reaction-picker__btn"
          :class="{ 'reaction-picker__btn--active': myReactions.includes(emoji) }"
          @click="emit('pick', emoji)"
          :title="emoji"
        >
          {{ emoji }}
        </button>
      </div>
    </Transition>

    <!-- Invisible backdrop to close on outside click (mobile-friendly) -->
    <div
      v-if="visible"
      class="reaction-picker-backdrop"
      @click="emit('close')"
      @touchstart="emit('close')"
    />
  </Teleport>
</template>

<script setup>
import { computed } from 'vue'

const EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '😡']

const props = defineProps({
  visible:     { type: Boolean, default: false },
  x:           { type: Number,  default: 0 },
  y:           { type: Number,  default: 0 },
  myReactions: { type: Array,   default: () => [] },
})
const emit = defineEmits(['pick', 'close'])

const pickerStyle = computed(() => ({
  top:  props.y + 'px',
  left: props.x + 'px',
}))
</script>

<style scoped>
/* Invisible full-screen backdrop — z-index just below picker */
.reaction-picker-backdrop {
  position: fixed;
  inset: 0;
  z-index: 9998;
  background: transparent;
}
</style>
