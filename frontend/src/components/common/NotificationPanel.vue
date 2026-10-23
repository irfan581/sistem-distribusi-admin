<template>
  <div class="notif-panel-inner">
    <div class="notif-header">
      <div class="notif-header-left">
        <span class="notif-title">Notifikasi</span>
        <span v-if="notificationStore.unreadCount > 0" class="notif-count">
          {{ notificationStore.unreadCount }}
        </span>
      </div>
      <button
        v-if="notificationStore.unreadCount > 0"
        class="mark-all-btn"
        @click="markAll"
      >
        <i class="pi pi-check-square" />
        Tandai semua
      </button>
    </div>

    <div v-if="notificationStore.loading" class="notif-loading">
      <div v-for="i in 4" :key="i" class="skeleton-notif">
        <Skeleton shape="circle" size="32px" />
        <div style="flex:1;display:flex;flex-direction:column;gap:5px;">
          <Skeleton height="11px" />
          <Skeleton height="9px" width="50%" />
        </div>
      </div>
    </div>

    <div v-else-if="notificationStore.notifications.length === 0" class="notif-empty">
      <div class="notif-empty-icon"><i class="pi pi-bell-slash" /></div>
      <p>Tidak ada notifikasi</p>
    </div>

    <div v-else class="notif-list-wrap">
      <div
        v-for="notif in notificationStore.notifications.slice(0, 10)"
        :key="notif.id"
        class="notif-item"
        :class="{ unread: !notif.read_at }"
        @click="handleClick(notif)"
      >
        <div class="notif-icon-wrap" :class="{ 'notif-icon-unread': !notif.read_at }">
          <i class="pi pi-bell" />
        </div>
        <div class="notif-content">
          <p class="notif-msg">{{ notif.data?.message || notif.message }}</p>
          <span class="notif-time">{{ formatDateTime(notif.created_at) }}</span>
        </div>
        <div v-if="!notif.read_at" class="notif-unread-dot" />
      </div>
    </div>

    <div class="notif-footer">
      <RouterLink to="/notifications" @click="$emit('close')" class="notif-view-all">
        <i class="pi pi-list" />
        Lihat semua notifikasi
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useNotificationStore } from '@/stores/notification.store'
import { formatDateTime } from '@/utils/helpers'

const emit = defineEmits(['close'])
const notificationStore = useNotificationStore()

onMounted(() => notificationStore.fetchNotifications())

async function markAll() { await notificationStore.markAllAsRead() }

async function handleClick(notif) {
  if (!notif.read_at) await notificationStore.markAsRead(notif.id)
  emit('close')
}
</script>

<style scoped>
.notif-panel-inner {
  display: flex;
  flex-direction: column;
  min-width: 320px;
}

/* Header */
.notif-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 0.85rem;
  border-bottom: 1px solid var(--tmk-border);
  margin-bottom: 0.25rem;
  gap: 0.75rem;
}

.notif-header-left {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.notif-title {
  font-weight: 800;
  font-size: 0.95rem;
  color: var(--tmk-text);
}

.notif-count {
  background: var(--tmk-orange-500);
  color: white;
  border-radius: 999px;
  font-size: 0.68rem;
  font-weight: 800;
  padding: 1px 7px;
  min-width: 20px;
  text-align: center;
}

.mark-all-btn {
  display: flex;
  align-items: center;
  gap: 0.3rem;
  background: transparent;
  border: none;
  color: var(--tmk-orange-500);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  padding: 0.3rem 0.5rem;
  border-radius: var(--tmk-radius-sm);
  transition: all var(--tmk-transition);
  white-space: nowrap;
}
.mark-all-btn:hover {
  background: rgba(249,115,22,0.08);
}

/* Loading */
.notif-loading {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.5rem 0;
}
.skeleton-notif {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.25rem 0;
}

/* Empty */
.notif-empty {
  text-align: center;
  padding: 2rem 1rem;
  color: var(--tmk-text-muted);
}
.notif-empty-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: var(--tmk-surface-2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.3rem;
  color: var(--tmk-text-subtle);
  margin: 0 auto 0.75rem;
}
.notif-empty p { font-size: 0.85rem; margin: 0; }

/* List */
.notif-list-wrap {
  max-height: 340px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  margin: 0 -0.25rem;
}

.notif-item {
  display: flex;
  align-items: flex-start;
  gap: 0.6rem;
  padding: 0.65rem 0.5rem;
  border-radius: var(--tmk-radius-md);
  cursor: pointer;
  transition: background var(--tmk-transition);
  position: relative;
}
.notif-item:hover { background: var(--tmk-surface-2); }
.notif-item.unread { background: rgba(249,115,22,0.05); }
.notif-item.unread:hover { background: rgba(249,115,22,0.1); }

.notif-icon-wrap {
  width: 32px;
  height: 32px;
  border-radius: 9px;
  background: var(--tmk-surface-2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  color: var(--tmk-text-muted);
  flex-shrink: 0;
  transition: all var(--tmk-transition);
}
.notif-icon-unread {
  background: rgba(249,115,22,0.12);
  color: var(--tmk-orange-500);
}

.notif-content { flex: 1; min-width: 0; }

.notif-msg {
  font-size: 0.82rem;
  margin: 0 0 0.25rem;
  color: var(--tmk-text);
  line-height: 1.45;
  word-break: break-word;
}

.notif-time {
  font-size: 0.72rem;
  color: var(--tmk-text-subtle);
}

.notif-unread-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--tmk-orange-500);
  flex-shrink: 0;
  margin-top: 0.35rem;
}

/* Footer */
.notif-footer {
  padding-top: 0.75rem;
  border-top: 1px solid var(--tmk-border);
  margin-top: 0.25rem;
  text-align: center;
}

.notif-view-all {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  color: var(--tmk-orange-500);
  text-decoration: none;
  font-size: 0.82rem;
  font-weight: 600;
  padding: 0.4rem 0.75rem;
  border-radius: var(--tmk-radius-sm);
  transition: all var(--tmk-transition);
}
.notif-view-all:hover {
  background: rgba(249,115,22,0.08);
  color: var(--tmk-orange-600);
}
</style>
