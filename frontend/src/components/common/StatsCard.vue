<template>
  <div class="stats-card" :class="`stats-card--${color}`">
    <div class="stats-info">
      <span class="stats-label">{{ label }}</span>
      <div class="stats-value">
        <span v-if="loading">
          <Skeleton width="80px" height="32px" />
        </span>
        <span v-else>{{ formattedValue }}</span>
      </div>
      <div v-if="trend !== null" class="stats-trend" :class="trendClass">
        <i :class="trendIcon" />
        <span>{{ Math.abs(trend) }}% dari kemarin</span>
      </div>
    </div>
    <div class="stats-icon-wrap">
      <i :class="icon" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { formatNumber } from '@/utils/helpers'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], default: 0 },
  icon: { type: String, default: 'pi pi-chart-bar' },
  color: { type: String, default: 'primary' },
  trend: { type: Number, default: null },
  loading: { type: Boolean, default: false },
  format: { type: String, default: 'number' },
})

const formattedValue = computed(() => {
  if (props.format === 'number') return formatNumber(props.value)
  return props.value
})

const trendClass = computed(() => {
  if (props.trend === null) return ''
  return props.trend >= 0 ? 'trend-up' : 'trend-down'
})

const trendIcon = computed(() => {
  if (props.trend === null) return ''
  return props.trend >= 0 ? 'pi pi-arrow-up' : 'pi pi-arrow-down'
})
</script>

<style scoped>
.stats-card {
  background: var(--s-0);
  border: 1px solid var(--s-border);
  border-radius: 12px;
  padding: 1.25rem;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  transition: box-shadow 0.2s, transform 0.2s;
}

.stats-card:hover {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  transform: translateY(-1px);
}

.stats-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.stats-label {
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--p-text-muted-color);
  font-weight: 500;
}

.stats-value {
  font-size: 2rem;
  font-weight: 800;
  color: var(--p-text-color);
  line-height: 1;
}

.stats-trend {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.78rem;
  margin-top: 0.25rem;
}

.trend-up {
  color: var(--p-green-500);
}

.trend-down {
  color: var(--p-red-500);
}

.stats-icon-wrap {
  width: 52px;
  height: 52px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  flex-shrink: 0;
}

.stats-card--primary .stats-icon-wrap {
  background: color-mix(in srgb, var(--p-primary-500) 12%, transparent);
  color: var(--p-primary-500);
}

.stats-card--success .stats-icon-wrap {
  background: color-mix(in srgb, var(--p-green-500) 12%, transparent);
  color: var(--p-green-600);
}

.stats-card--warning .stats-icon-wrap {
  background: color-mix(in srgb, var(--p-yellow-500) 12%, transparent);
  color: var(--p-yellow-600);
}

.stats-card--danger .stats-icon-wrap {
  background: color-mix(in srgb, var(--p-red-500) 12%, transparent);
  color: var(--p-red-500);
}

.stats-card--info .stats-icon-wrap {
  background: color-mix(in srgb, var(--p-cyan-500) 12%, transparent);
  color: var(--p-cyan-600);
}
</style>
