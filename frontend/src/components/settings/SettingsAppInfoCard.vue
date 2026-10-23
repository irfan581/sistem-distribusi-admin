<template>
  <div class="sc appinfo-card">
    <div class="sc-head">
      <div class="sc-head-icon sc-head-icon-purple"><i class="pi pi-info-circle" /></div>
      <div class="sc-head-text">
        <h3 class="sc-head-title">Informasi Aplikasi</h3>
        <p class="sc-head-sub">Versi dan status sistem</p>
      </div>
    </div>

    <div class="sc-body appinfo-body">

      <!-- ── Brand hero ─────────────────────────────────────── -->
      <div class="appinfo-hero">
        <div class="appinfo-logo-wrap">
          <i class="pi pi-building appinfo-logo-icon" />
        </div>
        <div class="appinfo-brand-text">
          <span class="appinfo-app-name">Tani Makmur</span>
          <span class="appinfo-module">Sistem Presensi &amp; Kunjungan Sales</span>
          <span class="appinfo-env-chip">
            <span class="dot-online" /> Production
          </span>
        </div>
      </div>

      <!-- ── Info rows ──────────────────────────────────────── -->
      <div class="appinfo-rows">
        <div class="appinfo-row">
          <span class="appinfo-row-icon" style="background:rgba(139,92,246,.1);color:#8b5cf6">
            <i class="pi pi-tag" />
          </span>
          <span class="appinfo-row-label">Versi Aplikasi</span>
          <span class="appinfo-row-val">v2.0.0</span>
        </div>

        <div class="appinfo-row">
          <span class="appinfo-row-icon" style="background:rgba(59,130,246,.1);color:#3b82f6">
            <i class="pi pi-code" />
          </span>
          <span class="appinfo-row-label">Stack Teknologi</span>
          <span class="appinfo-row-val">Laravel 12 · Vue 3 · Flutter</span>
        </div>

        <div class="appinfo-row">
          <span class="appinfo-row-icon" style="background:rgba(249,115,22,.1);color:#f97316">
            <i class="pi pi-calendar" />
          </span>
          <span class="appinfo-row-label">Tahun Build</span>
          <span class="appinfo-row-val">2026</span>
        </div>

        <div class="appinfo-row">
          <span class="appinfo-row-icon" style="background:rgba(22,163,74,.1);color:#16a34a">
            <i class="pi pi-server" />
          </span>
          <span class="appinfo-row-label">Status Server</span>
          <span class="appinfo-row-val appinfo-online">
            <span class="dot-online" /> Online
          </span>
        </div>
      </div>

      <!-- spacer mendorong footer ke bawah -->
      <div style="flex:1" />

      <!-- ── Footer ─────────────────────────────────────────── -->
      <div class="appinfo-footer">
        <span>&copy; 2026 CV. Tani Makmur Sejahtera</span>
        <span class="appinfo-footer-badge">TMK Admin v2</span>
      </div>

    </div>
  </div>
</template>

<script setup>
// Static card — no props needed
</script>

<style scoped>
/* ── Card body override ─────────────────────────────── */
.appinfo-body {
  gap: 1rem;
  padding: 1.25rem 1.5rem;
  display: flex;
  flex-direction: column;
  flex: 1;
}

/* ── Brand hero ─────────────────────────────────────── */
.appinfo-hero {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.9rem 1rem;
  background: linear-gradient(135deg,
    rgba(249,115,22,.07) 0%,
    rgba(139,92,246,.05) 100%);
  border: 1px solid rgba(249,115,22,.14);
  border-radius: 10px;
}

.appinfo-logo-wrap {
  width: 46px;
  height: 46px;
  border-radius: 12px;
  background: linear-gradient(135deg, var(--c-brand-500), var(--c-brand-600));
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(249,115,22,.35);
}
.appinfo-logo-icon {
  font-size: 1.3rem;
  color: #fff;
}

.appinfo-brand-text {
  display: flex;
  flex-direction: column;
  gap: 0.12rem;
  min-width: 0;
}
.appinfo-app-name {
  font-size: 0.95rem;
  font-weight: 800;
  color: var(--t-primary);
  letter-spacing: -0.02em;
  line-height: 1.2;
}
.appinfo-module {
  font-size: 0.72rem;
  color: var(--t-secondary);
  line-height: 1.35;
}
.appinfo-env-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.67rem;
  font-weight: 700;
  color: var(--c-success);
  background: rgba(22,163,74,.1);
  border: 1px solid rgba(22,163,74,.2);
  border-radius: 20px;
  padding: 1px 8px;
  margin-top: 0.1rem;
  width: fit-content;
}

/* ── Info rows ─────────────────────────────────────── */
.appinfo-rows {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.appinfo-row {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.5rem 0.65rem;
  border-radius: 8px;
  background: var(--s-1);
  border: 1px solid var(--s-border);
  transition: border-color 0.15s;
}
.appinfo-row:hover {
  border-color: rgba(249,115,22,.25);
}
.appinfo-row-icon {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 0.72rem;
}
.appinfo-row-label {
  font-size: 0.78rem;
  color: var(--t-secondary);
  flex: 1;
  min-width: 0;
}
.appinfo-row-val {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--t-primary);
  white-space: nowrap;
}
.appinfo-online {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  color: var(--c-success) !important;
}

/* ── Dot indicator ──────────────────────────────────── */
.dot-online {
  display: inline-block;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--c-success);
  box-shadow: 0 0 0 2px rgba(34,197,94,.25);
  flex-shrink: 0;
  animation: pulse-dot 2s infinite;
}
@keyframes pulse-dot {
  0%, 100% { box-shadow: 0 0 0 2px rgba(34,197,94,.25); }
  50%       { box-shadow: 0 0 0 4px rgba(34,197,94,.15); }
}


/* ── Footer ────────────────────────────────────────── */
.appinfo-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 0.65rem;
  border-top: 1px solid var(--s-border);
  font-size: 0.67rem;
  color: var(--t-muted);
}
.appinfo-footer-badge {
  font-size: 0.62rem;
  font-weight: 700;
  background: rgba(249,115,22,.1);
  color: var(--c-brand-600);
  border: 1px solid rgba(249,115,22,.2);
  border-radius: 20px;
  padding: 2px 9px;
}

/* ── Dark mode ─────────────────────────────────────── */
.app-dark .appinfo-hero {
  background: linear-gradient(135deg, rgba(249,115,22,.1) 0%, rgba(139,92,246,.07) 100%);
  border-color: rgba(249,115,22,.2);
}
.app-dark .appinfo-row {
  background: var(--s-1);
  border-color: var(--s-border);
}
.app-dark .appinfo-row:hover { border-color: rgba(249,115,22,.3); }

.app-dark .appinfo-footer { border-color: var(--s-border); }
.app-dark .appinfo-footer-badge {
  color: var(--c-brand-400);
  background: rgba(249,115,22,.12);
  border-color: rgba(249,115,22,.25);
}
</style>
