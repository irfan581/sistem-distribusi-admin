<template>
  <div class="ud-wrapper">
    <div class="ud-card">
      <div class="ud-icon">
        <i class="pi pi-wrench"></i>
      </div>

      <h1>Halaman Dalam Pengembangan</h1>
      <p class="ud-desc">
        Fitur <strong>{{ pageTitle }}</strong> sedang dalam tahap pengembangan
        dan akan segera tersedia untuk digunakan.
      </p>

      <div class="ud-info">
        <i class="pi pi-info-circle"></i>
        <span>
          Tim kami sedang mempersiapkan fitur ini agar dapat berjalan dengan
          optimal. Terima kasih atas kesabarannya.
        </span>
      </div>

      <div class="ud-badge">
        <i class="pi pi-clock"></i>
        Segera Hadir
      </div>

      <button class="ud-back" @click="$router.push('/')">
        <i class="pi pi-arrow-left"></i>
        Kembali ke Dashboard
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const pageTitle = computed(() => route.meta?.title ?? 'Fitur ini')
</script>

<style scoped>
.ud-wrapper {
  min-height: calc(100vh - 120px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  background: var(--surface-ground, var(--s-1));
}

.ud-card {
  background: var(--surface-card, var(--s-0));
  border-radius: 1.25rem;
  padding: 3.5rem 2.5rem;
  max-width: 500px;
  width: 100%;
  text-align: center;
  box-shadow: 0 4px 32px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--surface-border, var(--s-border));
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.25rem;
}

.ud-icon {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  background: linear-gradient(135deg, #ff8c42 0%, var(--c-brand-600) 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 6px 20px rgba(234, 88, 12, 0.35);
  margin-bottom: 0.25rem;
}

.ud-icon .pi {
  font-size: 2.25rem;
  color: #fff;
}

h1 {
  font-size: 1.6rem;
  font-weight: 700;
  color: var(--text-color, var(--t-primary));
  margin: 0;
}

.ud-desc {
  font-size: 1rem;
  color: var(--text-color-secondary, var(--t-secondary));
  line-height: 1.65;
  margin: 0;
}

.ud-info {
  display: flex;
  align-items: flex-start;
  gap: 0.625rem;
  background: var(--c-info-soft);
  border: 1px solid var(--c-info-line);
  border-radius: 0.75rem;
  padding: 0.875rem 1rem;
  text-align: left;
  width: 100%;
}

.ud-info .pi {
  color: var(--c-info);
  font-size: 1rem;
  flex-shrink: 0;
  margin-top: 2px;
}

.ud-info span {
  font-size: 0.875rem;
  color: var(--c-info);
  line-height: 1.55;
}

.ud-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  background: var(--c-brand-soft);
  border: 1px solid var(--c-brand-soft);
  color: var(--c-brand-700);
  font-size: 0.8125rem;
  font-weight: 600;
  padding: 0.4rem 1rem;
  border-radius: 999px;
}

.ud-badge .pi {
  font-size: 0.75rem;
}

.ud-back {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: transparent;
  border: 1.5px solid var(--surface-border, var(--s-border));
  color: var(--text-color-secondary, var(--t-secondary));
  font-size: 0.875rem;
  font-weight: 500;
  padding: 0.5rem 1.25rem;
  border-radius: 0.5rem;
  cursor: pointer;
  transition: border-color 0.18s, color 0.18s;
  margin-top: 0.25rem;
}

.ud-back:hover {
  border-color: var(--c-brand-600);
  color: var(--c-brand-600);
}

/* Dark mode */
.dark .ud-card,
[data-theme="dark"] .ud-card {
  background: var(--surface-card);
  border-color: var(--surface-border);
}

.dark .ud-info,
[data-theme="dark"] .ud-info {
  background: rgba(59, 130, 246, 0.08);
  border-color: rgba(59, 130, 246, 0.25);
}
</style>
