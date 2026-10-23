<template>
  <!-- Edit Target Dialog -->
  <Dialog
    v-model:visible="editDialog.visible"
    :header="`Edit Target: ${editDialog.sales?.nama}`"
    modal
    :style="{ width: '440px' }"
    class="vt-dialog"
  >
    <div v-if="editDialog.sales" class="edit-dialog-body">
      <div class="edit-sales-preview">
        <div class="edit-avatar">{{ initial(editDialog.sales.nama) }}</div>
        <div>
          <strong>{{ editDialog.sales.nama }}</strong>
          <span>{{ editDialog.sales.area }}</span>
        </div>
      </div>

      <div class="edit-field">
        <label class="edit-label">Target Kunjungan / Hari</label>
        <div class="target-input-group">
          <button @click="emit('editTargetDec')" class="target-stepper"><i class="pi pi-minus" /></button>
          <input v-model.number="editDialog.target" type="number" min="1" max="50" class="target-number-input" />
          <button @click="emit('editTargetInc')" class="target-stepper"><i class="pi pi-plus" /></button>
          <span class="target-input-unit">toko / hari</span>
        </div>
        <div class="target-presets">
          <button v-for="n in [3, 5, 7, 10, 15]" :key="n" @click="emit('setEditTarget', n)" class="preset-btn" :class="{ active: editDialog.target === n }">{{ n }}</button>
        </div>
      </div>

      <div class="edit-field">
        <label class="edit-label">Alasan Perubahan <span class="optional">(opsional)</span></label>
        <textarea v-model="editDialog.reason" placeholder="Misal: Kenaikan target Q2 2026" class="edit-textarea" rows="2" />
      </div>
    </div>
    <template #footer>
      <Button label="Batal" severity="secondary" @click="emit('closeEdit')" />
      <Button label="Simpan Target" :loading="saving" @click="emit('doUpdate')" class="save-target-btn" />
    </template>
  </Dialog>

  <!-- Bulk Update Dialog -->
  <Dialog
    v-model:visible="bulkDialog.visible"
    :header="`Update Target: ${selectedRows.length} Sales`"
    modal
    :style="{ width: '480px' }"
    class="vt-dialog"
  >
    <div class="bulk-dialog-body">
      <div class="bulk-sales-list">
        <div v-for="s in selectedRows" :key="s.id" class="bulk-sales-item">
          <span class="bulk-sales-name">{{ s.nama }}</span>
          <span class="bulk-current">{{ s.target_per_day }} toko</span>
        </div>
      </div>
      <div class="edit-field">
        <label class="edit-label">Target Baru untuk Semua</label>
        <div class="target-input-group">
          <button @click="emit('bulkTargetDec')" class="target-stepper"><i class="pi pi-minus" /></button>
          <input v-model.number="bulkDialog.target" type="number" min="1" max="50" class="target-number-input" />
          <button @click="emit('bulkTargetInc')" class="target-stepper"><i class="pi pi-plus" /></button>
          <span class="target-input-unit">toko / hari</span>
        </div>
        <div class="target-presets">
          <button v-for="n in [3, 5, 7, 10, 15]" :key="n" @click="emit('setBulkTarget', n)" class="preset-btn" :class="{ active: bulkDialog.target === n }">{{ n }}</button>
        </div>
      </div>
      <div class="edit-field">
        <label class="edit-label">Alasan <span class="optional">(opsional)</span></label>
        <textarea v-model="bulkDialog.reason" placeholder="Misal: Penyesuaian target akhir bulan" class="edit-textarea" rows="2" />
      </div>
    </div>
    <template #footer>
      <Button label="Batal" severity="secondary" @click="emit('closeBulk')" />
      <Button :label="`Update ${selectedRows.length} Sales`" :loading="saving" @click="emit('doBulkUpdate')" class="save-target-btn" />
    </template>
  </Dialog>

  <!-- History Dialog -->
  <Dialog
    v-model:visible="historyDialog.visible"
    :header="`Riwayat Target: ${historyDialog.sales?.nama}`"
    modal
    :style="{ width: '560px' }"
    class="vt-dialog"
  >
    <div v-if="historyDialog.loading" class="history-loading">
      <i class="pi pi-spin pi-spinner" />
    </div>
    <div v-else class="history-list">
      <div v-for="log in historyDialog.logs" :key="log.id" class="history-item">
        <div class="history-dot" :class="log.action === 'reset' ? 'dot-reset' : 'dot-update'" />
        <div class="history-body">
          <div class="history-change">
            <span v-if="log.old_target" class="old-target">{{ log.old_target }}</span>
            <i v-if="log.old_target" class="pi pi-arrow-right history-arrow" />
            <span class="new-target">{{ log.new_target }} toko/hari</span>
            <span class="history-action-badge" :class="`badge-${log.action}`">{{ actionLabel(log.action) }}</span>
          </div>
          <div class="history-meta">
            <span class="history-by">{{ log.changed_by }}</span>
            <span>·</span>
            <span class="history-at">{{ log.changed_at_wib }}</span>
          </div>
          <p v-if="log.reason" class="history-reason">{{ log.reason }}</p>
        </div>
      </div>
      <div v-if="historyDialog.logs.length === 0" class="history-empty">
        Belum ada riwayat perubahan
      </div>
    </div>
  </Dialog>
</template>

<script setup>
defineProps({
  editDialog:    { type: Object,  required: true },
  bulkDialog:    { type: Object,  required: true },
  historyDialog: { type: Object,  required: true },
  selectedRows:  { type: Array,   required: true },
  saving:        { type: Boolean, default: false },
  initial:       { type: Function, required: true },
})

const emit = defineEmits([
  'closeEdit', 'doUpdate', 'editTargetDec', 'editTargetInc', 'setEditTarget',
  'closeBulk', 'doBulkUpdate', 'bulkTargetDec', 'bulkTargetInc', 'setBulkTarget',
])

function actionLabel(action) {
  const map = { create: 'Buat', update: 'Update', reset: 'Reset', bulk_update: 'Bulk' }
  return map[action] ?? action
}
</script>
