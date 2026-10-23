<template>
  <div class="vt-view animate-fade-in">

    <div class="vt-header">
      <div class="vt-header-left">
        <div class="vt-header-icon">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75Z"/>
            <path d="M9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625Z"/>
            <path d="M16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
          </svg>
        </div>
        <div>
          <h1 class="vt-title">Target Kunjungan Sales</h1>
          <p class="vt-subtitle">Manajemen & monitoring target kunjungan toko realtime</p>
        </div>
      </div>
      <div class="vt-header-right">
        <div class="ws-indicator" :class="wsConnected ? 'ws-online' : 'ws-offline'">
          <span class="ws-dot" />
          <span class="ws-label">{{ wsConnected ? 'Live' : 'Offline' }}</span>
        </div>
        <div v-if="store.lastUpdated" class="vt-last-updated">Diperbarui {{ lastUpdatedLabel }}</div>
        <Button @click="refresh" :loading="store.loading" severity="secondary" size="small" class="vt-refresh-btn" title="Refresh data">
          <i class="pi pi-refresh" />
        </Button>
      </div>
    </div>

    <VtAnalyticsGrid :store="store" />

    <div class="vt-content-grid">
      <VtSalesTable
        :filteredList="filteredList"
        :selectedRows="selectedRows"
        :loading="store.loading"
        :search="search"
        :filterActive="filterActive"
        :allSelected="allSelected"
        :isSelected="isSelected"
        :initial="initial"
        @update:search="search = $event"
        @update:filterActive="filterActive = $event"
        @toggleSelect="toggleSelect"
        @toggleSelectAll="toggleSelectAll"
        @edit="openEditDialog"
        @history="openHistoryDialog"
        @reset="doReset"
        @openBulk="openBulkDialog"
      />
      <VtActivityFeed :store="store" />
    </div>

    <VtDialogs
      :editDialog="editDialog"
      :bulkDialog="bulkDialog"
      :historyDialog="historyDialog"
      :selectedRows="selectedRows"
      :saving="store.saving"
      :initial="initial"
      @closeEdit="editDialog.visible = false"
      @doUpdate="doUpdate"
      @editTargetDec="editDialog.target = Math.max(1, editDialog.target - 1)"
      @editTargetInc="editDialog.target = Math.min(50, editDialog.target + 1)"
      @setEditTarget="(n) => editDialog.target = n"
      @closeBulk="bulkDialog.visible = false"
      @doBulkUpdate="doBulkUpdate"
      @bulkTargetDec="bulkDialog.target = Math.max(1, bulkDialog.target - 1)"
      @bulkTargetInc="bulkDialog.target = Math.min(50, bulkDialog.target + 1)"
      @setBulkTarget="(n) => bulkDialog.target = n"
    />
  </div>
</template>

<script setup>
import '@/assets/css/visitTargets.css'
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useVisitTargetStore }  from '@/stores/visitTarget.store'
import { visitTargetService }   from '@/services/visitTarget.service'
import { useToast }             from '@/composables/useToast'
import { useConfirm }           from 'primevue/useconfirm'
import Button                   from 'primevue/button'
import VtAnalyticsGrid from '@/components/visitTargets/VtAnalyticsGrid.vue'
import VtSalesTable    from '@/components/visitTargets/VtSalesTable.vue'
import VtActivityFeed  from '@/components/visitTargets/VtActivityFeed.vue'
import VtDialogs       from '@/components/visitTargets/VtDialogs.vue'

const store   = useVisitTargetStore()
const toast   = useToast()
const confirm = useConfirm()

const wsConnected = computed(() => store.wsConnected)

const lastUpdatedLabel = computed(() => {
  if (!store.lastUpdated) return ''
  const diff = Math.floor((Date.now() - store.lastUpdated) / 1000)
  if (diff < 60)   return `${diff}dtk yang lalu`
  if (diff < 3600) return `${Math.floor(diff / 60)}m yang lalu`
  return store.lastUpdated.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
})

const search       = ref('')
const filterActive = ref(null)

const filteredList = computed(() => {
  let list = store.salesList
  if (filterActive.value !== null) list = list.filter(s => s.is_active === filterActive.value)
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(s =>
      s.nama?.toLowerCase().includes(q) ||
      s.nik?.toLowerCase().includes(q)  ||
      s.area?.toLowerCase().includes(q)
    )
  }
  return list
})

const selectedRows = ref([])
const allSelected  = computed(() => filteredList.value.length > 0 && filteredList.value.every(s => isSelected(s.id)))

function isSelected(id)    { return selectedRows.value.some(s => s.id === id) }
function toggleSelect(s)   { const i = selectedRows.value.findIndex(r => r.id === s.id); i === -1 ? selectedRows.value.push(s) : selectedRows.value.splice(i, 1) }
function toggleSelectAll() { selectedRows.value = allSelected.value ? [] : [...filteredList.value] }

function initial(nama) {
  if (!nama) return '?'
  return nama.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase()
}

const editDialog    = ref({ visible: false, sales: null, target: 5, reason: '' })
const bulkDialog    = ref({ visible: false, target: 5, reason: '' })
const historyDialog = ref({ visible: false, sales: null, logs: [], loading: false })

function openEditDialog(sales) {
  editDialog.value = { visible: true, sales, target: sales.target_per_day, reason: '' }
}

async function doUpdate() {
  const { sales, target, reason } = editDialog.value
  try {
    await store.updateTarget(sales.id, target, reason || null)
    editDialog.value.visible = false
    await store.fetchAnalytics()
    toast.success(`Target diperbarui menjadi ${target} toko/hari`)
  } catch (err) {
    toast.error(err?.response?.data?.message || 'Terjadi kesalahan')
  }
}

function openBulkDialog() {
  bulkDialog.value = { visible: true, target: 5, reason: '' }
}

async function doBulkUpdate() {
  try {
    const updates = selectedRows.value.map(s => ({ sales_id: s.id, target: bulkDialog.value.target }))
    const res = await store.bulkUpdate(updates, bulkDialog.value.reason || null)
    bulkDialog.value.visible = false
    selectedRows.value = []
    await store.fetchAnalytics()
    toast.success(res.message || 'Bulk update selesai')
  } catch (err) {
    toast.error(err?.response?.data?.message || 'Terjadi kesalahan')
  }
}

function doReset(sales) {
  confirm.require({
    message:       `Reset target ${sales.nama} ke default (5 toko/hari)?`,
    header:        'Reset Target',
    icon:          'pi pi-refresh',
    acceptSeverity: 'warning',
    acceptLabel:   'Ya, Reset',
    rejectLabel:   'Batal',
    accept: async () => {
      try {
        await store.resetTarget(sales.id)
        toast.info(`Target ${sales.nama} direset ke 5 toko/hari`, 'Reset')
      } catch (err) {
        toast.error(err?.response?.data?.message || 'Terjadi kesalahan')
      }
    },
  })
}

async function openHistoryDialog(sales) {
  historyDialog.value = { visible: true, sales, logs: [], loading: true }
  try {
    const res = await visitTargetService.getHistory(sales.id)
    historyDialog.value.logs = res.data ?? []
  } finally {
    historyDialog.value.loading = false
  }
}

async function refresh() {
  try {
    await Promise.all([store.fetchAll(), store.fetchAnalytics()])
  } catch (err) {
    const status = err?.response?.status
    if (status === 403) {
      toast.warn('Anda tidak memiliki izin untuk melihat data ini', 'Akses Ditolak')
    } else {
      toast.error('Tidak dapat mengambil data target kunjungan', 'Gagal Memuat')
    }
  }
}

onMounted(async () => {
  await refresh()
  store.subscribeRealtime()
})

onBeforeUnmount(() => store.unsubscribeRealtime())
</script>
