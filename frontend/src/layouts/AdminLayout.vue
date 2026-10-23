<template>
  <div class="workspace" :class="{ 'app-dark': appStore.darkMode }">
    <AppSidebar />

    <div class="workspace-body">
      <AppNavbar />
      <main class="workspace-content" ref="scrollEl">

        <!-- ── Pull-to-refresh indicator (mobile only) ─────── -->
        <Transition name="ptr">
          <div
            v-if="pulling || refreshing"
            class="ptr-indicator"
            :style="{ transform: `translateX(-50%) translateY(${pullY}px)` }"
          >
            <div class="ptr-spinner" :class="{ 'ptr-spinner--spin': refreshing }">
              <svg viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5"
                  stroke-linecap="round"
                  :stroke-dasharray="refreshing ? '56.5' : `${(pullY / 32) * 56.5} 56.5`"
                  :stroke-dashoffset="refreshing ? '0' : '-14'"
                />
              </svg>
            </div>
            <span class="ptr-text">
              {{ refreshing ? 'Memperbarui…' : pullY >= 32 ? 'Lepaskan untuk refresh' : 'Tarik untuk refresh' }}
            </span>
          </div>
        </Transition>

        <RouterView v-slot="{ Component, route }">
          <Transition name="rt">
            <!-- max dinaikkan mengikuti panjang daftar di atas. Kalau tetap 8,
                 halaman yang baru ditambahkan justru saling menendang keluar
                 cache dan hasilnya sama saja seperti tanpa KeepAlive. -->
            <KeepAlive :include="keepAlivePages" :max="14">
              <component :is="Component" :key="route.name" />
            </KeepAlive>
          </Transition>
        </RouterView>
      </main>
    </div>

    <!-- Global loading veil -->
    <Transition name="veil">
      <div v-if="appStore.globalLoading" class="workspace-veil">
        <div class="veil-inner">
          <picture>
            <source srcset="/logo-icon-192.webp" type="image/webp" />
            <img src="/logo-icon.png" alt="TMK" class="veil-logo" width="64" height="64" />
          </picture>
          <div class="veil-dots">
            <span /><span /><span />
          </div>
        </div>
      </div>
    </Transition>

    <!-- Mobile sidebar backdrop -->
    <Transition name="backdrop">
      <div
        v-if="appStore.sidebarMobileOpen"
        class="workspace-backdrop"
        @click="appStore.closeMobileSidebar()"
      />
    </Transition>

    <!-- ── Global chat notification banner (WhatsApp-style) ───── -->
    <Transition name="global-notif">
      <div
        v-if="chatGlobalStore.globalNotif"
        class="global-chat-notif"
        @click="goToChat()"
      >
        <div class="gcn-icon">
          <!-- Foto profil jika ada, fallback inisial -->
          <img
            v-if="chatGlobalStore.globalNotif.contact?.foto"
            :src="chatGlobalStore.globalNotif.contact.foto"
            :alt="chatGlobalStore.globalNotif.senderName"
            class="gcn-avatar-img"
            width="40" height="40"
          />
          <span v-else class="gcn-avatar-initial">
            {{ chatGlobalStore.globalNotif.senderName?.charAt(0)?.toUpperCase() }}
          </span>
        </div>
        <div class="gcn-body">
          <span class="gcn-name">{{ chatGlobalStore.globalNotif.senderName }}</span>
          <span class="gcn-text">{{ chatGlobalStore.globalNotif.body }}</span>
        </div>
        <button class="gcn-close" @click.stop="chatGlobalStore.dismissNotif()">✕</button>
      </div>
    </Transition>

    <Toast position="top-right" />
    <ConfirmDialog />
  </div>
</template>

<script setup>
import { ref, provide, onMounted, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAppStore } from '@/stores/app.store'
import { useAuthStore } from '@/stores/auth.store'
import { useNotificationStore } from '@/stores/notification.store'
import { useChatGlobalStore } from '@/stores/chatGlobal.store'
import { requestFcmPermission } from '@/composables/useFcmPush'
import { usePermission } from '@/composables/usePermission'
import { HAK_SALES } from '@/utils/permissions'
import { usePullToRefresh } from '@/composables/usePullToRefresh'
import AppSidebar from '@/components/common/AppSidebar.vue'
import AppNavbar from '@/components/common/AppNavbar.vue'

// ── KeepAlive pages ──────────────────────────────────────────
// Halaman di sini tidak dibongkar saat pindah menu, cuma disembunyikan, jadi
// kembali ke sana tidak membangun ulang tabel dan tidak menembak server lagi.
// Halaman pengiriman dan armada ikut masuk karena justru di situ paling terasa:
// tabelnya panjang dan tiap kunjungan menarik data dari beberapa endpoint.
const keepAlivePages = [
  'DashboardView',
  'AttendanceView',
  'EmployeesView',
  'KunjunganView',
  'IzinView',
  'TokoView',
  'NotificationsView',
  'DaftarMuatView',
  'MasterArmadaView',
  'RiwayatKirimanView',
  'StatistikPengirimanView',
  'PengawasanMuatView',
]

const router            = useRouter()
const route             = useRoute()
const appStore          = useAppStore()
const authStore         = useAuthStore()
const notificationStore = useNotificationStore()
const chatGlobalStore   = useChatGlobalStore()

// ── Pull-to-refresh ──────────────────────────────────────────
const scrollEl = ref(null)

// Halaman yang tidak perlu PTR (punya scroll internal sendiri)
const NO_PTR_ROUTES = ['chat', 'maps']

// refreshBus: counter yang di-increment saat PTR trigger.
// Child page inject 'onPullRefresh' dan watch counter ini untuk reload data.
const refreshBus = ref(0)
provide('onPullRefresh', refreshBus)

async function handleRefresh() {
  if (NO_PTR_ROUTES.includes(String(route.name))) return
  refreshBus.value++
  await new Promise(r => setTimeout(r, 800))
}

const { pulling, pullY, refreshing } = usePullToRefresh(scrollEl, handleRefresh)

function goToChat() {
  const contact = chatGlobalStore.globalNotif?.contact
  chatGlobalStore.dismissNotif()
  // Navigasi ke chat dengan state kontak — ChatView akan auto-select kontak ini
  router.push({ name: 'chat', state: contact ? { openContact: JSON.stringify(contact) } : undefined })
}

onMounted(async () => {
  appStore.initTheme()
  if (authStore.isAuthenticated) {
    await authStore.fetchMe().catch(() => {})
    // Admin pengiriman tidak punya Chat/Notifikasi, jadi tidak perlu badge, channel, maupun push.
    if (!usePermission().can(HAK_SALES)) return
    await notificationStore.fetchUnreadCount()
    chatGlobalStore.setup()
    // Daftarkan FCM token ke backend — minta izin notif + register service worker
    requestFcmPermission()
  }
})

onBeforeUnmount(() => {
  chatGlobalStore.teardown()
})
</script>

<style scoped>
/* The workspace shell itself comes from main.css (.workspace, .workspace-body, .workspace-content) */

/* ── Pull-to-refresh indicator ────────────────────────────── */
.ptr-indicator {
  position: sticky;   /* sticky agar selalu terlihat di atas konten saat ditarik */
  top: -56px;
  left: 50%;
  width: fit-content;
  z-index: 500;
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--s-0);
  border-radius: 999px;
  padding: 8px 16px 8px 10px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.14), 0 1px 4px rgba(0,0,0,0.08);
  pointer-events: none;
  will-change: transform;
  margin-bottom: -56px; /* tidak mendorong konten ke bawah */
}
.app-dark .ptr-indicator {
  background: var(--t-primary);
  box-shadow: 0 4px 20px rgba(0,0,0,0.4);
}
.ptr-spinner {
  width: 22px;
  height: 22px;
  color: var(--c-brand-500);
  flex-shrink: 0;
}
.ptr-spinner svg {
  width: 100%;
  height: 100%;
}
.ptr-spinner--spin svg {
  animation: ptrSpin 0.8s linear infinite;
}
.ptr-text {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--t-secondary);
  white-space: nowrap;
}
.app-dark .ptr-text { color: var(--s-border); }

@keyframes ptrSpin {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}

/* Hanya tampil di mobile */
@media (min-width: 992px) {
  .ptr-indicator { display: none !important; }
}

/* Transition untuk enter/leave */
.ptr-enter-active, .ptr-leave-active { transition: opacity .15s ease; }
.ptr-enter-from, .ptr-leave-to       { opacity: 0; }

/* ── Global chat notification banner ─────────────────────── */
.global-chat-notif {
  position: fixed;
  top: 24px;
  right: 24px;
  z-index: 9000;
  display: flex;
  align-items: center;
  gap: 12px;
  width: 320px;
  padding: 14px 16px;
  background: var(--s-0);
  border-radius: 16px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.18), 0 2px 8px rgba(0,0,0,0.08);
  cursor: pointer;
  border-left: 4px solid var(--c-brand-500);
  transition: box-shadow .15s;
}
.global-chat-notif:hover {
  box-shadow: 0 12px 36px rgba(0,0,0,0.22), 0 2px 8px rgba(0,0,0,0.1);
}
.gcn-icon {
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--c-brand-soft);
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--c-brand-soft);
}
.gcn-avatar-img {
  width: 40px;
  height: 40px;
  object-fit: cover;
  border-radius: 50%;
}
.gcn-avatar-initial {
  font-size: 1rem;
  font-weight: 700;
  color: var(--c-brand-500);
}
.gcn-icon img {
  width: 22px;
  height: 22px;
  object-fit: contain;
}
.gcn-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  overflow: hidden;
  min-width: 0;
}
.gcn-name {
  font-weight: 700;
  font-size: 0.84rem;
  color: var(--t-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.gcn-text {
  font-size: 0.8rem;
  color: var(--t-secondary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.gcn-close {
  flex-shrink: 0;
  background: none;
  border: none;
  color: var(--t-muted);
  font-size: 0.78rem;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  line-height: 1;
  transition: color .15s, background .15s;
}
.gcn-close:hover { color: var(--t-secondary); background: var(--s-2); }

/* Dark mode */
.app-dark .global-chat-notif {
  background: var(--t-primary);
  border-left-color: var(--c-brand-500);
  box-shadow: 0 8px 32px rgba(0,0,0,0.5);
}
.app-dark .gcn-name  { color: var(--s-2); }
.app-dark .gcn-text  { color: var(--t-muted); }
.app-dark .gcn-icon  { background: #292524; border-color: #78350f; }
.app-dark .gcn-close { color: var(--t-secondary); }
.app-dark .gcn-close:hover { color: var(--s-border); background: #374151; }

/* Slide-down from top */
.global-notif-enter-active { transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
.global-notif-leave-active { transition: all 0.2s ease-in; }
.global-notif-enter-from   { opacity: 0; transform: translateY(-20px) scale(0.95); }
.global-notif-leave-to     { opacity: 0; transform: translateY(-10px) scale(0.97); }

/* Mobile */
@media (max-width: 480px) {
  .global-chat-notif {
    top: 12px;
    right: 12px;
    left: 12px;
    width: auto;
  }
}

/* ── Global loading veil ──────────────────────────────────── */
.workspace-veil {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(0,0,0,.55);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
}

.veil-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.25rem;
}

.veil-logo {
  width: 56px;
  height: 56px;
  object-fit: contain;
  animation: glowPulse 2s ease-in-out infinite;
  filter: drop-shadow(0 0 20px rgba(249,115,22,.7));
  will-change: box-shadow;
}

.veil-dots {
  display: flex;
  gap: 6px;
  align-items: center;
}
.veil-dots span {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--c-brand-400);
  animation: bounce3 1.4s ease-in-out infinite;
}
.veil-dots span:nth-child(2) { animation-delay: .2s; background: var(--c-brand-500); }
.veil-dots span:nth-child(3) { animation-delay: .4s; background: var(--c-amber); }

/* ── Mobile backdrop ──────────────────────────────────────── */
.workspace-backdrop {
  position: fixed;
  inset: 0;
  z-index: 998;
  background: rgba(0,0,0,.55);
  backdrop-filter: blur(4px);
}

/* ── Transitions ──────────────────────────────────────────── */
.veil-enter-active,.veil-leave-active { transition: opacity .2s ease; }
.veil-enter-from,.veil-leave-to       { opacity: 0; }

.backdrop-enter-active,.backdrop-leave-active { transition: opacity .2s ease; }
.backdrop-enter-from,.backdrop-leave-to       { opacity: 0; }

/* Route transition from main.css .rt-* */

/* ── Responsive: handled in main.css ─────────────────────── */
</style>
