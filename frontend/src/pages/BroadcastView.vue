<template>
  <div class="broadcast-view">
    <PageHeader title="Pengumuman" subtitle="Kirim notifikasi ke semua sales atau berdasarkan area.">
      <template #actions>
        <Button icon="pi pi-history" label="Riwayat" outlined @click="showHistory = true" />
      </template>
    </PageHeader>

    <div class="broadcast-layout">

      <!-- ── Form Kirim ──────────────────────────────────────── -->
      <Card class="broadcast-form-card">
        <template #title>
          <div class="bc-card-title">
            <i class="pi pi-megaphone" />
            Buat Pengumuman
          </div>
        </template>
        <template #content>
          <div class="bc-form">

            <!-- Target -->
            <div class="bc-field">
              <label class="bc-label">Target Penerima</label>
              <div class="bc-target-tabs">
                <button
                  class="bc-tab"
                  :class="{ 'bc-tab--active': form.target === 'all' }"
                  @click="setTarget('all')"
                >
                  <i class="pi pi-users" />
                  Semua Sales
                  <span class="bc-tab-badge">{{ totalSales }}</span>
                </button>
                <button
                  class="bc-tab"
                  :class="{ 'bc-tab--active': form.target === 'area' }"
                  @click="setTarget('area')"
                >
                  <i class="pi pi-map-marker" />
                  Per Area
                </button>
              </div>
            </div>

            <!-- Filter area -->
            <Transition name="slide-down">
              <div v-if="form.target === 'area'" class="bc-field">
                <label class="bc-label">Pilih Area <span class="bc-required">*</span></label>
                <div class="bc-area-grid">
                  <button
                    v-for="area in areas"
                    :key="area"
                    class="bc-area-chip"
                    :class="{ 'bc-area-chip--selected': form.areas.includes(area) }"
                    @click="toggleArea(area)"
                  >
                    <i class="pi" :class="form.areas.includes(area) ? 'pi-check-circle' : 'pi-circle'" />
                    {{ area }}
                  </button>
                </div>
                <span v-if="form.target === 'area' && form.areas.length === 0" class="bc-hint-error">
                  Pilih minimal 1 area
                </span>
                <div v-if="form.areas.length" class="bc-area-summary">
                  {{ form.areas.length }} area dipilih · Estimasi ~{{ estimatedRecipients }} penerima
                </div>
              </div>
            </Transition>

            <!-- Judul -->
            <div class="bc-field">
              <label class="bc-label">Judul Notifikasi <span class="bc-required">*</span></label>
              <InputText
                v-model="form.title"
                placeholder="Contoh: Target Kunjungan Hari Ini"
                class="w-full"
                :maxlength="150"
              />
              <span class="bc-char-count">{{ form.title.length }}/150</span>
            </div>

            <!-- Pesan -->
            <div class="bc-field">
              <label class="bc-label">Isi Pesan <span class="bc-required">*</span></label>
              <Textarea
                v-model="form.message"
                placeholder="Tulis isi pesan yang akan diterima sales di aplikasi mereka..."
                :rows="5"
                class="w-full"
                :maxlength="1000"
                autoResize
              />
              <span class="bc-char-count">{{ form.message.length }}/1000</span>
            </div>

            <!-- Preview -->
            <div v-if="form.title || form.message" class="bc-preview">
              <div class="bc-preview-label">Preview notifikasi di HP sales:</div>
              <div class="bc-preview-notif">
                <div class="bc-preview-body">
                  <div class="bc-preview-title">{{ form.title || 'Judul Pesan' }}</div>
                  <div class="bc-preview-msg">{{ form.message || 'Isi pesan...' }}</div>
                  <div class="bc-preview-time">Baru saja</div>
                </div>
              </div>
            </div>

            <!-- Tombol kirim -->
            <div class="bc-actions">
              <Button
                label="Reset"
                icon="pi pi-refresh"
                text
                plain
                @click="resetForm"
                :disabled="sending"
              />
              <Button
                :label="sending ? 'Mengirim...' : `Kirim ke ${recipientLabel}`"
                icon="pi pi-send"
                :loading="sending"
                :disabled="!canSend"
                @click="confirmSend"
                class="bc-send-btn"
              />
            </div>
          </div>
        </template>
      </Card>

      <!-- ── Info Panel ──────────────────────────────────────── -->
      <div class="broadcast-info-col">

        <!-- Ringkasan target -->
        <Card class="bc-info-card">
          <template #content>
            <div class="bc-stat-row">
              <div class="bc-stat">
                <i class="pi pi-users bc-stat-icon" />
                <div>
                  <div class="bc-stat-val">{{ totalSales }}</div>
                  <div class="bc-stat-lbl">Total Sales Aktif</div>
                </div>
              </div>
              <div class="bc-stat">
                <i class="pi pi-map-marker bc-stat-icon" style="color:#6366f1" />
                <div>
                  <div class="bc-stat-val">{{ areas.length }}</div>
                  <div class="bc-stat-lbl">Area Tersedia</div>
                </div>
              </div>
            </div>

            <!-- Sales tanpa FCM token -->
            <div v-if="salesTanpaToken > 0" class="bc-warn-box">
              <i class="pi pi-exclamation-triangle" />
              {{ salesTanpaToken }} sales belum punya FCM token, notif tersimpan tapi tidak akan muncul di HP sampai mereka login ulang.
            </div>
          </template>
        </Card>

        <!-- Tips -->
        <Card class="bc-info-card bc-tips-card">
          <template #content>
            <div class="bc-tips-title"><i class="pi pi-lightbulb" /> Tips Pengumuman</div>
            <ul class="bc-tips-list">
              <li>Pesan tersimpan di <b>notifikasi</b> aplikasi sales meski HP sedang mati.</li>
              <li>Sales dengan HP menyala akan langsung menerima push notification.</li>
              <li>Pilih <b>Per Area</b> untuk pengumuman lebih terarah.</li>
              <li>Gunakan judul yang jelas agar sales langsung paham isi pesan.</li>
            </ul>
          </template>
        </Card>
      </div>
    </div>

    <!-- ── Dialog Konfirmasi ───────────────────────────────── -->
    <Dialog
      v-model:visible="showConfirm"
      modal
      header="Konfirmasi Kirim Pengumuman"
      :style="{ width: '420px' }"
    >
      <div class="bc-confirm-body">
        <i class="pi pi-megaphone bc-confirm-icon" />
        <p>Pesan akan dikirim ke <strong>{{ recipientLabel }}</strong>.</p>
        <div class="bc-confirm-preview">
          <div class="bc-confirm-title">{{ form.title }}</div>
          <div class="bc-confirm-msg">{{ form.message }}</div>
        </div>
        <p class="bc-confirm-warn">Tindakan ini tidak dapat dibatalkan.</p>
      </div>
      <template #footer>
        <Button label="Batal" text @click="showConfirm = false" />
        <Button label="Ya, Kirim Sekarang" icon="pi pi-send" :loading="sending" @click="doSend" />
      </template>
    </Dialog>

    <!-- ── Dialog Hasil Kirim ─────────────────────────────── -->
    <Dialog
      v-model:visible="showResult"
      modal
      header="Pengumuman Berhasil Dikirim!"
      :style="{ width: '400px' }"
    >
      <div class="bc-result-body">
        <i class="pi pi-check-circle bc-result-icon" />
        <div class="bc-result-stats">
          <div class="bc-result-stat">
            <span class="bc-result-val">{{ lastResult.total_penerima }}</span>
            <span class="bc-result-lbl">Total Penerima</span>
          </div>
          <div class="bc-result-stat">
            <span class="bc-result-val" style="color:#22c55e">{{ lastResult.fcm_terkirim }}</span>
            <span class="bc-result-lbl">Punya Token HP</span>
          </div>
          <div class="bc-result-stat">
            <span class="bc-result-val" style="color:#f59e0b">{{ lastResult.fcm_gagal }}</span>
            <span class="bc-result-lbl">Tanpa Token</span>
          </div>
        </div>
        <p class="bc-result-note">
          Semua pesan tersimpan di inbox sales.
          <span v-if="lastResult.fcm_terkirim > 0">Push notification dikirim ke {{ lastResult.fcm_terkirim }} sales yang sedang aktif.</span>
          <span v-if="lastResult.fcm_gagal > 0"> {{ lastResult.fcm_gagal }} sales tanpa token akan menerima saat login berikutnya.</span>
        </p>
      </div>
      <template #footer>
        <Button label="Tutup" @click="showResult = false; resetForm()" />
      </template>
    </Dialog>

    <!-- ── Dialog Riwayat ─────────────────────────────────── -->
    <Dialog
      v-model:visible="showHistory"
      modal
      header="Riwayat Pengumuman"
      :style="{ width: '680px' }"
    >
      <div v-if="historyLoading" class="bc-history-loading">
        <i class="pi pi-spin pi-spinner" /> Memuat riwayat...
      </div>
      <div v-else-if="history.length === 0" class="bc-history-empty">
        <i class="pi pi-inbox" />
        <p>Belum ada riwayat pengumuman.</p>
      </div>
      <div v-else class="bc-history-list">
        <div v-for="item in history" :key="item.batch_id" class="bc-history-item">
          <div class="bc-history-meta">
            <span class="bc-history-title">{{ item.title }}</span>
            <span class="bc-history-date">{{ formatDate(item.created_at) }}</span>
          </div>
          <div class="bc-history-msg">{{ item.message }}</div>
          <div class="bc-history-stats">
            <span><i class="pi pi-users" /> {{ item.total_penerima }} penerima</span>
            <span><i class="pi pi-eye" /> {{ item.total_dibaca }} dibaca</span>
            <span class="bc-history-pct">{{ readPct(item) }}%</span>
          </div>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { broadcastService } from '@/services/broadcast.service'
import { useToast } from '@/composables/useToast'
import PageHeader from '@/components/common/PageHeader.vue'

const toast = useToast()

// ── State ─────────────────────────────────────────────────
const areas         = ref([])
const totalSales    = ref(0)
const salesTanpaToken = ref(0)
const sending       = ref(false)
const showConfirm   = ref(false)
const showResult    = ref(false)
const showHistory   = ref(false)
const historyLoading = ref(false)
const history       = ref([])
const lastResult    = ref({ total_penerima: 0, fcm_terkirim: 0, fcm_gagal: 0 })

// Jumlah sales per area — diisi dari API
const areaCount = ref({})  // { area: count }

const form = ref({
  target:  'all',
  areas:   [],
  title:   '',
  message: '',
})

// ── Computed ──────────────────────────────────────────────
const recipientLabel = computed(() => {
  if (form.value.target === 'all') return `semua ${totalSales.value} sales`
  if (!form.value.areas.length)   return '0 sales'
  const est = estimatedRecipients.value
  return `~${est} sales di ${form.value.areas.length} area`
})

const estimatedRecipients = computed(() => {
  return form.value.areas.reduce((sum, a) => sum + (areaCount.value[a] ?? 0), 0)
})

const canSend = computed(() => {
  if (!form.value.title.trim() || !form.value.message.trim()) return false
  if (form.value.target === 'area' && form.value.areas.length === 0) return false
  return true
})

// ── Methods ───────────────────────────────────────────────
function setTarget(t) {
  form.value.target = t
  if (t === 'all') form.value.areas = []
}

function toggleArea(area) {
  const idx = form.value.areas.indexOf(area)
  if (idx === -1) form.value.areas.push(area)
  else            form.value.areas.splice(idx, 1)
}

function resetForm() {
  form.value = { target: 'all', areas: [], title: '', message: '' }
}

function confirmSend() {
  if (!canSend.value) return
  showConfirm.value = true
}

async function doSend() {
  sending.value = true
  try {
    const payload = {
      title:   form.value.title,
      message: form.value.message,
      target:  form.value.target,
    }
    if (form.value.target === 'area') payload.areas = form.value.areas

    const res = await broadcastService.send(payload)
    lastResult.value = res.data
    showConfirm.value = false
    showResult.value  = true
    toast.success(res.message)
  } catch (err) {
    toast.apiError(err)
  } finally {
    sending.value = false
  }
}

async function loadHistory() {
  historyLoading.value = true
  try {
    const res = await broadcastService.getHistory()
    history.value = res.data ?? []
  } catch {
    toast.error('Gagal memuat riwayat')
  } finally {
    historyLoading.value = false
  }
}

function readPct(item) {
  if (!item.total_penerima) return 0
  return Math.round((item.total_dibaca / item.total_penerima) * 100)
}

function formatDate(dt) {
  return new Date(dt).toLocaleDateString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  })
}

// ── Watch history dialog ──────────────────────────────────
import { watch } from 'vue'
watch(showHistory, (val) => { if (val) loadHistory() })

// ── Init ──────────────────────────────────────────────────
onMounted(async () => {
  try {
    // getAreas mengembalikan data (area names) + meta (total, tanpa_token, area_counts)
    const res = await broadcastService.getAreas()
    areas.value           = res.data ?? []
    totalSales.value      = res.meta?.total_sales       ?? 0
    salesTanpaToken.value = res.meta?.sales_tanpa_token ?? 0
    areaCount.value       = res.meta?.area_counts       ?? {}
  } catch (e) {
    console.warn('Broadcast init error:', e)
  }
})
</script>

<style scoped>
.broadcast-view { padding: 0; animation: fadeUp var(--dur-base) var(--ease) both; }

.broadcast-layout {
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 1.25rem;
  align-items: start;
}

@media (max-width: 900px) {
  .broadcast-layout { grid-template-columns: 1fr; }
  .broadcast-info-col { order: -1; }
}

/* ── Card title ────────────────────────────────────────── */
.bc-card-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 1rem;
  font-weight: 700;
  color: var(--tmk-text);
}
.bc-card-title .pi { color: var(--tmk-primary); }

/* ── Form fields ────────────────────────────────────────── */
.bc-form { display: flex; flex-direction: column; gap: 1.25rem; }

.bc-field { display: flex; flex-direction: column; gap: 6px; }

.bc-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--tmk-text);
}
.bc-required { color: var(--c-danger); }

.bc-char-count {
  font-size: 0.75rem;
  color: var(--tmk-text-muted);
  text-align: right;
}

.bc-hint-error { font-size: 0.78rem; color: var(--c-danger); }

/* ── Target tabs ─────────────────────────────────────────── */
.bc-target-tabs { display: flex; gap: 10px; }

.bc-tab {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 16px;
  border: 1.5px solid var(--s-border);
  border-radius: 10px;
  background: var(--s-0);
  color: var(--tmk-text-muted);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
}
.bc-tab:hover { border-color: var(--tmk-primary); color: var(--tmk-primary); }
.bc-tab--active {
  border-color: var(--tmk-primary);
  background: color-mix(in srgb, var(--tmk-primary) 8%, transparent);
  color: var(--tmk-primary);
  font-weight: 700;
}

.bc-tab-badge {
  background: var(--tmk-primary);
  color: #fff;
  border-radius: 20px;
  padding: 1px 7px;
  font-size: 0.7rem;
  font-weight: 700;
}

/* ── Area chips ──────────────────────────────────────────── */
.bc-area-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.bc-area-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border: 1.5px solid var(--s-border);
  border-radius: 20px;
  background: var(--s-0);
  color: var(--tmk-text);
  font-size: 0.8rem;
  cursor: pointer;
  transition: all 0.15s;
}
.bc-area-chip:hover { border-color: var(--tmk-primary); }
.bc-area-chip--selected {
  border-color: var(--tmk-primary);
  background: color-mix(in srgb, var(--tmk-primary) 10%, transparent);
  color: var(--tmk-primary);
  font-weight: 600;
}
.bc-area-chip .pi { font-size: 0.75rem; }

.bc-area-summary {
  font-size: 0.78rem;
  color: var(--tmk-primary);
  font-weight: 600;
}

/* ── Preview ─────────────────────────────────────────────── */
.bc-preview {
  border: 1.5px dashed var(--s-border);
  border-radius: 12px;
  padding: 12px;
  background: var(--s-50);
}
.bc-preview-label {
  font-size: 0.75rem;
  color: var(--tmk-text-muted);
  margin-bottom: 8px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.bc-preview-notif {
  display: flex;
  gap: 10px;
  background: var(--s-0);
  border-radius: 10px;
  padding: 10px 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.bc-preview-body { flex: 1; min-width: 0; }
.bc-preview-title { font-size: 0.85rem; font-weight: 700; color: var(--tmk-text); }
.bc-preview-msg { font-size: 0.78rem; color: var(--tmk-text-muted); margin-top: 2px; line-clamp: 2; }
.bc-preview-time { font-size: 0.7rem; color: var(--tmk-text-muted); margin-top: 4px; }

/* ── Actions ─────────────────────────────────────────────── */
.bc-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 4px;
}
.bc-send-btn { min-width: 180px; }

/* ── Info col ─────────────────────────────────────────────── */
.broadcast-info-col { display: flex; flex-direction: column; gap: 1rem; }

.bc-info-card {}

.bc-stat-row { display: flex; gap: 16px; margin-bottom: 12px; }
.bc-stat { display: flex; align-items: center; gap: 10px; flex: 1; }
.bc-stat-icon { font-size: 1.5rem; color: var(--tmk-primary); }
.bc-stat-val { font-size: 1.4rem; font-weight: 700; color: var(--tmk-text); line-height: 1; }
.bc-stat-lbl { font-size: 0.72rem; color: var(--tmk-text-muted); margin-top: 2px; }

.bc-warn-box {
  display: flex; align-items: flex-start; gap: 8px;
  background: var(--c-warning-soft);
  border: 1px solid var(--c-warning);
  border-radius: 8px;
  padding: 10px 12px;
  font-size: 0.78rem;
  color: var(--c-warning);
  line-height: 1.4;
}
.bc-warn-box .pi { color: var(--c-warning); margin-top: 1px; flex-shrink: 0; }

.bc-tips-card { background: color-mix(in srgb, var(--tmk-primary) 4%, var(--s-0)); }
.bc-tips-title { font-weight: 700; color: var(--tmk-primary); margin-bottom: 10px; display: flex; gap: 6px; align-items: center; }
.bc-tips-list { margin: 0; padding-left: 18px; display: flex; flex-direction: column; gap: 6px; }
.bc-tips-list li { font-size: 0.8rem; color: var(--tmk-text-muted); line-height: 1.4; }
.bc-tips-list b { color: var(--tmk-text); }

/* ── Confirm dialog ───────────────────────────────────────── */
.bc-confirm-body { text-align: center; padding: 8px 0; }
.bc-confirm-icon { font-size: 2.5rem; color: var(--tmk-primary); display: block; margin-bottom: 12px; }
.bc-confirm-preview {
  background: var(--s-50);
  border-radius: 10px;
  padding: 12px 14px;
  margin: 12px 0;
  text-align: left;
}
.bc-confirm-title { font-weight: 700; font-size: 0.9rem; margin-bottom: 4px; }
.bc-confirm-msg { font-size: 0.82rem; color: var(--tmk-text-muted); line-height: 1.4; }
.bc-confirm-warn { font-size: 0.78rem; color: var(--c-danger); }

/* ── Result dialog ────────────────────────────────────────── */
.bc-result-body { text-align: center; padding: 8px 0; }
.bc-result-icon { font-size: 3rem; color: var(--c-success); display: block; margin-bottom: 12px; }
.bc-result-stats { display: flex; justify-content: center; gap: 24px; margin: 16px 0; }
.bc-result-stat { display: flex; flex-direction: column; align-items: center; gap: 4px; }
.bc-result-val { font-size: 1.75rem; font-weight: 800; color: var(--tmk-primary); }
.bc-result-lbl { font-size: 0.72rem; color: var(--tmk-text-muted); }
.bc-result-note { font-size: 0.78rem; color: var(--tmk-text-muted); line-height: 1.5; max-width: 300px; margin: 0 auto; }

/* ── History dialog ───────────────────────────────────────── */
.bc-history-loading, .bc-history-empty {
  text-align: center; padding: 32px 0; color: var(--tmk-text-muted);
}
.bc-history-empty .pi { font-size: 2rem; display: block; margin-bottom: 8px; }
.bc-history-list { display: flex; flex-direction: column; gap: 12px; max-height: 480px; overflow-y: auto; }
.bc-history-item {
  border: 1px solid var(--s-border);
  border-radius: 10px;
  padding: 12px 14px;
}
.bc-history-meta { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; margin-bottom: 4px; }
.bc-history-title { font-weight: 700; font-size: 0.9rem; }
.bc-history-date { font-size: 0.72rem; color: var(--tmk-text-muted); white-space: nowrap; }
.bc-history-msg { font-size: 0.8rem; color: var(--tmk-text-muted); margin-bottom: 8px; line-height: 1.4; }
.bc-history-stats { display: flex; align-items: center; gap: 14px; font-size: 0.75rem; color: var(--tmk-text-muted); }
.bc-history-stats .pi { margin-right: 3px; }
.bc-history-pct { margin-left: auto; font-weight: 700; color: var(--tmk-primary); }

/* ── Transitions ─────────────────────────────────────────── */
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.2s ease; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
