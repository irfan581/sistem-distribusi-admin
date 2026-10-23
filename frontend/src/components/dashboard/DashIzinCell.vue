<template>
  <div class="bento-cell bento-izin">
    <div class="cell-head">
      <div class="cell-title-group">
        <span class="cell-title">Izin Pending</span>
        <span v-if="izinPendingCount > 0" class="cell-sub warn-sub">{{ izinPendingCount }} menunggu</span>
        <span v-else class="cell-sub">Tidak ada izin</span>
      </div>
      <RouterLink to="/izin" class="cell-link">Kelola <i class="pi pi-arrow-right" /></RouterLink>
    </div>
    <div class="cell-body no-pad">
      <div v-if="loading" class="stream-skel">
        <div v-for="i in 4" :key="i" class="stream-skel-row">
          <div class="skeleton" style="width:32px;height:32px;border-radius:50%;flex-shrink:0" />
          <div class="skeleton-lines">
            <div class="skeleton" style="height:10px" />
            <div class="skeleton" style="height:9px;width:55%" />
          </div>
        </div>
      </div>
      <div v-else-if="!izinPending.length" class="cell-empty">
        <div class="empty-icon empty-ok"><i class="pi pi-check-circle" /></div>
        <span>Semua izin sudah diproses</span>
      </div>
      <ul v-else class="stream-list">
        <li v-for="izin in izinPending" :key="izin.id" class="stream-item izin-item">
          <div class="stream-avatar av-amber">{{ (izin.sales?.nama || '?')[0].toUpperCase() }}</div>
          <div class="stream-info">
            <span class="stream-name">{{ izin.sales?.nama || '-' }}</span>
            <div class="izin-meta">
              <span class="chip chip-info" style="font-size:.62rem;padding:.15rem .5rem">{{ izin.jenis_izin }}</span>
              <span class="stream-desc">{{ formatDate(izin.tanggal) }}</span>
            </div>
          </div>
          <div class="izin-btns" v-if="can('approve_izin')">
            <button class="act-btn act-ok"  @click="emit('approve', izin.id)" :disabled="izin._loading" title="Setujui"><i class="pi pi-check" /></button>
            <button class="act-btn act-no"  @click="emit('reject',  izin.id)" :disabled="izin._loading" title="Tolak"  ><i class="pi pi-times" /></button>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { formatDate } from '@/utils/helpers'
import { usePermission } from '@/composables/usePermission'

const { can } = usePermission()

defineProps({
  izinPending:      { type: Array,  required: true },
  izinPendingCount: { type: Number, default: 0 },
  loading:          { type: Boolean, default: false },
})

const emit = defineEmits(['approve', 'reject'])
</script>
