<template>
  <div class="notif-view">
    <PageHeader title="Notifikasi" subtitle="Semua notifikasi dan aktivitas sistem.">
      <template #actions>
        <Button label="Tandai semua dibaca" icon="pi pi-check-double" outlined
          :disabled="unreadCount === 0" @click="markAll" :loading="markingAll" />
        <Button icon="pi pi-refresh" text plain @click="loadNotifications" />
      </template>
    </PageHeader>

    <Card>
      <template #content>
        <div v-if="loading" class="notif-list">
          <div v-for="i in 6" :key="i" class="notif-skeleton">
            <Skeleton shape="circle" size="40px" />
            <div style="flex:1"><Skeleton height="14px" class="mb-2" /><Skeleton height="12px" width="60%" /></div>
          </div>
        </div>
        <div v-else-if="notifications.length === 0" class="notif-empty">
          <i class="pi pi-bell-slash" />
          <p>Tidak ada notifikasi</p>
          <span>Semua notifikasi masuk akan ditampilkan di sini.</span>
        </div>
        <div v-else class="notif-list">
          <div
            v-for="notif in notifications"
            :key="notif.id"
            class="notif-item"
            :class="{ unread: !notif.read_at }"
            @click="markAsRead(notif)"
          >
            <div class="notif-icon-wrap" :class="notifIconClass(notif)">
              <i :class="notifIcon(notif)" />
            </div>
            <div class="notif-content">
              <p class="notif-msg">{{ notif.data?.message || notif.data?.title || 'Notifikasi baru' }}</p>
              <span class="notif-time">{{ formatDateTime(notif.created_at) }}</span>
            </div>
            <div v-if="!notif.read_at" class="unread-dot" />
          </div>
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePullRefresh } from '@/composables/usePullRefresh'
import { notificationService } from '@/services/notification.service'
import { useNotificationStore } from '@/stores/notification.store'
import { useToast } from '@/composables/useToast'
import { formatDateTime } from '@/utils/helpers'
import PageHeader from '@/components/common/PageHeader.vue'

const toast = useToast()
const notifStore = useNotificationStore()

const loading = ref(false)
const markingAll = ref(false)
const notifications = ref([])
const unreadCount = computed(() => notifications.value.filter(n => !n.read_at).length)

function notifIcon(notif) {
  const type = notif.data?.type || ''
  if (type.includes('izin')) return 'pi pi-file-edit'
  if (type.includes('presensi')) return 'pi pi-calendar-clock'
  if (type.includes('kunjungan')) return 'pi pi-map-marker'
  return 'pi pi-bell'
}

function notifIconClass(notif) {
  const type = notif.data?.type || ''
  if (type.includes('izin')) return 'icon-info'
  if (type.includes('presensi')) return 'icon-success'
  if (type.includes('kunjungan')) return 'icon-primary'
  return 'icon-default'
}

async function loadNotifications() {
  loading.value = true
  try {
    const res = await notificationService.getAll()
    notifications.value = res.data?.data || res.data || []
    notifStore.unreadCount = notifications.value.filter(n => !n.read_at).length
  } catch { /* silent */ }
  finally { loading.value = false }
}

async function markAsRead(notif) {
  if (notif.read_at) return
  try {
    await notificationService.markAsRead(notif.id)
    notif.read_at = new Date().toISOString()
    notifStore.unreadCount = Math.max(0, notifStore.unreadCount - 1)
  } catch { /* silent */ }
}

async function markAll() {
  markingAll.value = true
  try {
    await notificationService.markAllAsRead()
    notifications.value.forEach(n => { n.read_at = n.read_at || new Date().toISOString() })
    notifStore.unreadCount = 0
    toast.success('Semua notifikasi ditandai dibaca')
  } catch (err) { toast.apiError(err) }
  finally { markingAll.value = false }
}

onMounted(loadNotifications)
usePullRefresh(loadNotifications)
</script>

<style scoped>
.notif-view { display: flex; flex-direction: column; gap: 1.25rem; animation: fadeUp var(--dur-base) var(--ease) both; }

.notif-list { display: flex; flex-direction: column; gap: 0.25rem; }

.notif-skeleton { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 0; }

.notif-item {
  display: flex; align-items: center; gap: 0.75rem;
  padding: 0.85rem 1rem;
  border-radius: 10px;
  cursor: pointer;
  transition: background 0.15s;
  position: relative;
}
.notif-item:hover { background: var(--s-1); }
.notif-item.unread { background: color-mix(in srgb, var(--p-primary-500) 5%, transparent); }
.notif-item.unread:hover { background: color-mix(in srgb, var(--p-primary-500) 10%, transparent); }

.notif-icon-wrap {
  width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem;
}
.icon-primary { background: color-mix(in srgb, var(--p-primary-500) 12%, transparent); color: var(--p-primary-500); }
.icon-success { background: rgba(34,197,94,0.12); color: var(--c-success); }
.icon-info    { background: rgba(59,130,246,0.12); color: var(--c-info); }
.icon-default { background: var(--s-2); color: var(--p-text-muted-color); }

.notif-content { flex: 1; min-width: 0; }
.notif-msg { font-size: 0.875rem; color: var(--p-text-color); margin: 0 0 0.25rem; line-height: 1.4; }
.notif-time { font-size: 0.75rem; color: var(--p-text-muted-color); }

.unread-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--p-primary-500); flex-shrink: 0;
}

.notif-empty {
  display: flex; flex-direction: column; align-items: center;
  gap: 0.4rem; padding: 4rem 2rem; color: var(--p-text-muted-color); text-align: center;
}
.notif-empty i { font-size: 3rem; margin-bottom: 0.5rem; }
.notif-empty p { font-size: 1rem; font-weight: 600; margin: 0; color: var(--p-text-color); }
.notif-empty span { font-size: 0.85rem; }
</style>
