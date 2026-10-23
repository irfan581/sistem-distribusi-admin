<template>
  <header class="navbar" :class="{ 'navbar-enter': appStore.freshLogin }">

    <!-- Left: tombol menu -->
    <div class="navbar-left">
      <!-- Hanya di mobile: di desktop tombolnya ada di header sidebar.
           Di sini sidebar berupa drawer di luar layar, jadi tombolnya
           harus ada di navbar — kalau tidak, drawer tak bisa dibuka. -->
      <button
        class="nb-menu"
        :aria-label="pageTitle"
        :title="pageTitle"
        @click="toggleMenu"
      >
        <span class="nb-menu-lines" aria-hidden="true">
          <span /><span /><span />
        </span>
      </button>
    </div>

    <!-- Right: actions strip -->
    <div class="navbar-right">

      <!-- Realtime clock + pulse -->
      <div class="realtime-badge">
        <span class="pulse-ring" />
        <span class="pulse-dot" />
        <span class="realtime-time">{{ liveTime }}</span>
      </div>

      <!-- Dark mode -->
      <button class="nb-btn" @click="appStore.toggleDarkMode()" :aria-label="appStore.darkMode ? 'Light mode' : 'Dark mode'">
        <i :class="appStore.darkMode ? 'pi pi-sun' : 'pi pi-moon'" />
      </button>

      <!-- Chat -->
      <button v-if="bisaSales" class="nb-btn nb-notif" @click="router.push('/chat')" aria-label="Chat">
        <i class="pi pi-comments" />
        <span v-if="chatGlobalStore.chatUnread > 0" class="nb-badge">
          {{ chatGlobalStore.chatUnread > 99 ? '99+' : chatGlobalStore.chatUnread }}
        </span>
      </button>

      <!-- Notifications -->
      <button v-if="bisaSales" class="nb-btn nb-notif" @click="toggleNotif" aria-label="Notifikasi">
        <i class="pi pi-bell" />
        <span v-if="notificationStore.unreadCount > 0" class="nb-badge">
          {{ notificationStore.unreadCount > 99 ? '99+' : notificationStore.unreadCount }}
        </span>
      </button>

      <!-- Divider -->
      <div class="nb-divider" />

      <!-- User chip -->
      <div class="user-chip" @click="toggleUserMenu">
        <div class="uc-avatar">
          <img v-if="authStore.user?.avatar" :src="authStore.user.avatar" :alt="authStore.userInitials" class="uc-avatar-img" />
          <span v-else>{{ authStore.userInitials }}</span>
        </div>
        <div class="uc-info">
          <span class="uc-name">{{ authStore.user?.name || 'Admin' }}</span>
        </div>
        <i class="pi pi-angle-down uc-chevron" />
      </div>

      <Menu ref="userMenuRef" :model="userMenuItems" popup />
    </div>

    <!-- Notification flyout -->
    <Popover ref="notifRef" class="notif-flyout">
      <NotificationPanel @close="notifRef?.hide()" />
    </Popover>

  </header>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAppStore } from '@/stores/app.store'
import { useAuthStore } from '@/stores/auth.store'
import { useNotificationStore } from '@/stores/notification.store'
import { useChatGlobalStore } from '@/stores/chatGlobal.store'
import { useToast } from '@/composables/useToast'
import NotificationPanel from './NotificationPanel.vue'
import { usePermission } from '@/composables/usePermission'
import { HAK_SALES } from '@/utils/permissions'

const appStore          = useAppStore()
const authStore         = useAuthStore()
const notificationStore = useNotificationStore()
const chatGlobalStore   = useChatGlobalStore()
const route   = useRoute()
const router  = useRouter()
const toast   = useToast()

const userMenuRef = ref(null)
const notifRef    = ref(null)
const liveTime    = ref('')

let clockTimer = null

function tick() {
  liveTime.value = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

onMounted(() => { tick(); clockTimer = setInterval(tick, 1000) })
onBeforeUnmount(() => clearInterval(clockTimer))

const pageTitle = computed(() => route.meta.title || 'Dashboard')
const { can } = usePermission()
const bisaSales = computed(() => can(HAK_SALES))

const userMenuItems = computed(() => [
  {
    label: authStore.user?.name || 'Admin',
    items: [
      { label: 'Profil & Pengaturan', icon: 'pi pi-user',     command: () => router.push('/settings') },
      { separator: true },
      { label: 'Keluar',              icon: 'pi pi-sign-out', command: handleLogout, class: 'menu-danger' },
    ],
  },
])

/**
 * Di bawah 991px sidebar berupa drawer, di atas itu sidebar menetap
 * dan tombol ini hanya melebar-menyempitkannya.
 */
function toggleMenu() {
  if (window.matchMedia('(max-width: 991px)').matches) {
    appStore.toggleMobileSidebar()
  } else {
    appStore.toggleSidebar()
  }
}

function toggleUserMenu(e) { userMenuRef.value?.toggle(e) }
function toggleNotif(e)    { notifRef.value?.toggle(e) }

async function handleLogout() {
  await authStore.logout()
  toast.success('Berhasil keluar.')
  router.push('/login')
}
</script>

<style scoped>
/* ── Shell ──────────────────────────────────────────────────── */
.navbar {
  height: var(--navbar-h);
  background: var(--s-0);
  border-bottom: 1px solid var(--s-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 1.25rem;
  position: sticky;
  top: 0;
  z-index: 100;
  gap: 1rem;
  flex-shrink: 0;
}

/* Animasi hanya saat freshLogin */
.navbar-enter {
  animation: navbarIn .55s cubic-bezier(.22,1,.36,1) both;
}
.navbar-enter .navbar-left  { animation: navbarItemIn .45s .15s cubic-bezier(.22,1,.36,1) both; }
.navbar-enter .navbar-right { animation: navbarItemIn .45s .25s cubic-bezier(.22,1,.36,1) both; }

@keyframes navbarIn {
  from { opacity: 0; transform: translateY(-100%); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes navbarItemIn {
  from { opacity: 0; transform: translateY(-8px); }
  to   { opacity: 1; transform: translateY(0); }
}
.app-dark .navbar { background: #130f0c; border-color: #2a1f15; }

/* ── Left ────────────────────────────────────────────────────── */
.navbar-left { display: flex; align-items: center; gap: .65rem; min-width: 0; }

/* ── Tombol menu (tiga garis) ─────────────────────────────────
   Menggantikan judul halaman + breadcrumb. Garisnya digambar dengan
   span, bukan ikon font, supaya panjang & tebalnya bisa diatur persis
   dan animasi hover-nya halus. */
.nb-menu {
  /* Desktop: tersembunyi — toggle-nya ada di header sidebar. */
  display: none;
  width: 40px; height: 40px;
  border: 1px solid var(--s-border);
  background: var(--s-0);
  border-radius: var(--r-sm);
  cursor: pointer;
  align-items: center; justify-content: center;
  flex-shrink: 0;
  transition: background var(--dur-fast), border-color var(--dur-fast);
}
.nb-menu:hover { background: rgba(249,115,22,.08); border-color: rgba(249,115,22,.3); }

.nb-menu-lines {
  display: flex;
  flex-direction: column;
  gap: 4px;
  width: 18px;
}
.nb-menu-lines span {
  display: block;
  height: 2px;
  width: 100%;
  border-radius: 2px;
  background: var(--t-secondary);
  transition: background var(--dur-fast), width var(--dur-fast);
}
.nb-menu:hover .nb-menu-lines span { background: var(--c-brand-500); }
/* Garis tengah sedikit memendek saat hover — penanda kecil bahwa
   tombolnya bisa diklik. */
.nb-menu:hover .nb-menu-lines span:nth-child(2) { width: 65%; }

/* ── Right ───────────────────────────────────────────────────── */
.navbar-right { display: flex; align-items: center; gap: .2rem; flex-shrink: 0; }

/* Base icon button */
.nb-btn {
  width: 34px;
  height: 34px;
  border: none;
  background: transparent;
  border-radius: var(--r-sm);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--t-secondary);
  font-size: .95rem;
  transition: all var(--dur-fast) var(--ease);
}
.nb-btn:hover { background: var(--s-1); color: var(--t-primary); }

/* Notification button */
.nb-notif { position: relative; }
.nb-badge {
  position: absolute;
  top: 2px; right: 2px;
  background: var(--c-brand-500);
  color: #fff;
  border-radius: var(--r-full);
  font-size: .58rem;
  font-weight: 800;
  padding: 0 3.5px;
  min-width: 15px;
  height: 15px;
  line-height: 15px;
  text-align: center;
  border: 2px solid var(--s-0);
}

/* Divider */
.nb-divider { width: 1px; height: 20px; background: var(--s-border); margin: 0 .3rem; }

/* ── Realtime badge ─────────────────────────────────────────── */
.realtime-badge {
  display: flex;
  align-items: center;
  gap: .45rem;
  background: rgba(22,163,74,.08);
  border: 1px solid rgba(34,197,94,.22);
  border-radius: var(--r-full);
  padding: .28rem .75rem;
  margin-right: .3rem;
  position: relative;
}

.pulse-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--c-online);
  box-shadow: 0 0 5px var(--c-online);
  flex-shrink: 0;
  position: relative;
  z-index: 1;
}

.pulse-ring {
  position: absolute;
  left: .65rem;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--c-online);
  opacity: .5;
  animation: ping 1.8s cubic-bezier(0,0,.2,1) infinite;
}

.realtime-time {
  font-size: .72rem;
  font-weight: 700;
  color: var(--c-success);
  letter-spacing: .04em;
  font-variant-numeric: tabular-nums;
}
.app-dark .realtime-time { color: var(--c-success); }

/* ── User chip ───────────────────────────────────────────────── */
.user-chip {
  display: flex;
  align-items: center;
  gap: .5rem;
  padding: .3rem .5rem .3rem .35rem;
  border-radius: var(--r-sm);
  cursor: pointer;
  transition: background var(--dur-fast);
  margin-left: .25rem;
  border: 1px solid transparent;
  min-width: 0;
}
.user-chip:hover { background: var(--s-1); border-color: var(--s-border); }

.uc-avatar {
  width: 28px;
  height: 28px;
  border-radius: var(--r-full);
  background: linear-gradient(135deg, var(--c-brand-500), var(--c-brand-700));
  color: #fff;
  font-size: .7rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 2px 6px rgba(249,115,22,.3);
  overflow: hidden;
}
.uc-avatar-img {
  width: 100%; height: 100%;
  object-fit: cover;
  border-radius: 50%;
}

.uc-info { display: flex; flex-direction: column; }
.uc-name { font-size: .85rem; font-weight: 700; color: var(--t-primary); white-space: nowrap; line-height: 1.2; }
.uc-role { font-size: .68rem; font-weight: 600; color: var(--c-brand-500); letter-spacing: .02em; }

.uc-chevron { font-size: .68rem; color: var(--t-muted); }

/* ── Mobile visibility ───────────────────────────────────────── */
@media (max-width: 991px) {
  .nb-menu { display: flex; }
  .uc-info, .uc-chevron { display: none; }
  .realtime-time { display: none; }
  .realtime-badge { padding: .28rem .5rem; }
}
@media (max-width: 480px) {
  .realtime-badge { display: none; }
}
</style>

<style>
.notif-flyout { width: 380px !important; max-width: 95vw !important; }
</style>
