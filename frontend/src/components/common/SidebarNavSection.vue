<template>
  <div class="nav-section">
    <span v-show="open" class="nav-section-label">{{ label }}</span>
    <ul class="nav-list">
      <li v-for="item in items" :key="item.to">
        <RouterLink
          :to="item.to"
          class="nav-item"
          :class="{ active: isActive(item.to) }"
          @click="emit('close')"
          v-tooltip.right="!open ? item.label : null"
        >
          <span class="nav-icon" :class="{ 'nav-icon-active': isActive(item.to) }">
            <i :class="item.icon" />
            <span v-if="item.badge && !open" class="icon-pip" />
          </span>
          <Transition name="label">
            <span v-show="open" class="nav-label">{{ item.label }}</span>
          </Transition>
          <Transition name="label">
            <span v-if="item.badge && open" v-show="open" class="nav-badge">
              {{ item.badge > 99 ? '99+' : item.badge }}
            </span>
          </Transition>
        </RouterLink>
      </li>
    </ul>
  </div>
</template>

<script setup>
defineProps({
  label:    { type: String,   required: true },
  items:    { type: Array,    required: true },
  open:     { type: Boolean,  required: true },
  isActive: { type: Function, required: true },
})
const emit = defineEmits(['close'])
</script>
