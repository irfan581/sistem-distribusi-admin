<template>
  <div class="forbidden-page">
    <div class="forbidden-card">
      <div class="fb-icon-wrap">
        <i class="pi pi-lock fb-icon" />
      </div>
      <div class="fb-code">403</div>
      <h1 class="fb-title">Akses Ditolak</h1>
      <p class="fb-desc">
        Anda tidak memiliki izin untuk mengakses halaman ini.<br />
        Hubungi administrator untuk mendapatkan akses.
      </p>
      <div class="fb-actions">
        <button class="fb-btn fb-btn-back" @click="$router.back()">
          <i class="pi pi-arrow-left" />
          <span>Kembali</span>
        </button>
        <RouterLink to="/" class="fb-btn fb-btn-home">
          <i class="pi pi-home" />
          <span>Dashboard</span>
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup></script>

<style scoped>
.forbidden-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--s-bg, var(--s-2));
  padding: 2rem;
}

.forbidden-card { animation: scaleIn 0.35s cubic-bezier(.22,1,.36,1) both;
  text-align: center;
  padding: 3rem 2.5rem;
  background: var(--s-0, var(--s-0));
  border-radius: 20px;
  box-shadow: 0 4px 24px rgba(0,0,0,.08);
  border: 1px solid var(--s-2, var(--s-border));
  max-width: 420px;
  width: 100%;
}

.fb-icon-wrap {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--c-danger-soft), var(--c-danger-soft));
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.25rem;
}

.fb-icon {
  font-size: 2rem;
  color: var(--c-danger);
}

.fb-code {
  font-size: 5rem;
  font-weight: 900;
  color: var(--c-danger);
  line-height: 1;
  margin-bottom: 0.5rem;
  letter-spacing: -2px;
}

.fb-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--c-text-1, var(--t-primary));
  margin-bottom: 0.75rem;
}

.fb-desc {
  color: var(--c-text-3, var(--t-secondary));
  font-size: 0.9rem;
  line-height: 1.6;
  margin-bottom: 2rem;
}

.fb-actions {
  display: flex;
  gap: 0.75rem;
  justify-content: center;
  flex-wrap: wrap;
}

.fb-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 1.25rem;
  border-radius: 10px;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  transition: all .15s;
  border: none;
}

.fb-btn-back {
  background: var(--s-1, var(--s-2));
  color: var(--c-text-1, var(--t-secondary));
  border: 1px solid var(--s-2, var(--s-border));
}
.fb-btn-back:hover {
  background: var(--s-2, var(--s-border));
}

.fb-btn-home {
  background: linear-gradient(135deg, var(--c-brand-500), var(--c-brand-600));
  color: #fff;
  box-shadow: 0 2px 8px rgba(249,115,22,.35);
}
.fb-btn-home:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(249,115,22,.45);
}

/* Dark mode */
.app-dark .forbidden-card { animation: scaleIn 0.35s cubic-bezier(.22,1,.36,1) both;
  background: var(--s-0, var(--t-primary));
  border-color: var(--s-2, #374151);
}
.app-dark .fb-btn-back {
  background: var(--s-1, #374151);
  color: var(--s-border);
  border-color: var(--s-2, var(--t-secondary));
}
</style>
