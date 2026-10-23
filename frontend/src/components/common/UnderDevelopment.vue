<template>
  <div class="under-dev-overlay">
    <div class="under-dev-card">
      <div class="icon-wrapper">
        <i class="pi pi-wrench"></i>
      </div>
      <h2>Fitur Dalam Pengembangan</h2>
      <p class="subtitle">{{ title }} sedang dalam tahap pengembangan dan akan segera tersedia.</p>
      <div class="info-box">
        <i class="pi pi-info-circle"></i>
        <span>Tim kami sedang mempersiapkan fitur ini agar dapat berjalan dengan optimal. Terima kasih atas kesabarannya.</span>
      </div>
      <div class="badge-row">
        <span class="badge">
          <i class="pi pi-clock"></i> Segera Hadir
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    default: 'Fitur ini',
  },
})
</script>

<style scoped>
.under-dev-overlay {
  min-height: calc(100vh - 120px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  background: var(--surface-ground, var(--s-1));
}

.under-dev-card {
  background: var(--surface-card, var(--s-0));
  border-radius: 1.25rem;
  padding: 3rem 2.5rem;
  max-width: 480px;
  width: 100%;
  text-align: center;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
  border: 1px solid var(--surface-border, var(--s-border));
}

.icon-wrapper {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: linear-gradient(135deg, #ff8c42 0%, #ff6b1a 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.5rem;
  box-shadow: 0 4px 16px rgba(255, 107, 26, 0.3);
}

.icon-wrapper .pi {
  font-size: 2rem;
  color: #fff;
}

h2 {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-color, var(--t-primary));
  margin: 0 0 0.75rem;
}

.subtitle {
  font-size: 1rem;
  color: var(--text-color-secondary, var(--t-secondary));
  margin: 0 0 1.5rem;
  line-height: 1.6;
}

.info-box {
  display: flex;
  align-items: flex-start;
  gap: 0.625rem;
  background: var(--blue-50, var(--c-info-soft));
  border: 1px solid var(--blue-200, var(--c-info-line));
  border-radius: 0.75rem;
  padding: 0.875rem 1rem;
  text-align: left;
  margin-bottom: 1.5rem;
}

.info-box .pi {
  color: var(--blue-500, var(--c-info));
  font-size: 1rem;
  flex-shrink: 0;
  margin-top: 2px;
}

.info-box span {
  font-size: 0.875rem;
  color: var(--blue-700, var(--c-info));
  line-height: 1.5;
}

.badge-row {
  display: flex;
  justify-content: center;
}

.badge {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  background: linear-gradient(135deg, var(--c-brand-soft) 0%, var(--c-brand-soft) 100%);
  border: 1px solid var(--c-brand-soft);
  color: var(--c-brand-700);
  font-size: 0.8125rem;
  font-weight: 600;
  padding: 0.375rem 0.875rem;
  border-radius: 999px;
}

.badge .pi {
  font-size: 0.75rem;
}

/* Dark mode */
:root[data-theme="dark"] .under-dev-card,
.dark .under-dev-card {
  background: var(--surface-card);
  border-color: var(--surface-border);
}

:root[data-theme="dark"] .info-box,
.dark .info-box {
  background: rgba(59, 130, 246, 0.1);
  border-color: rgba(59, 130, 246, 0.3);
}
</style>
