<template>
  <Dialog
    v-model:visible="visible"
    header="Import Excel Data Toko"
    modal
    :style="{ width: '460px', maxWidth: '96vw' }"
    @hide="reset"
  >
    <div class="import-info">
      <i class="pi pi-info-circle" />
      <div>
        <div class="info-title">Format kolom yang diperlukan:</div>
        <div class="info-cols">
          <Tag value="nama_toko" severity="info" />
          <Tag value="kode_wilayah" severity="info" />
        </div>
        <div class="info-note">
          Wilayah akan dicocokkan dengan area sales secara otomatis.<br />
          Toko yang sudah ada akan diperbarui (upsert).
        </div>
      </div>
    </div>

    <div class="upload-zone" @click="triggerPick" @dragover.prevent @drop.prevent="onDrop">
      <template v-if="!selectedFile">
        <i class="pi pi-file-excel upload-icon" />
        <div class="upload-text">Klik atau seret file Excel ke sini</div>
        <div class="upload-hint">.xlsx atau .xls, maks. 5 MB</div>
      </template>
      <template v-else>
        <i class="pi pi-file-excel upload-icon selected" />
        <div class="upload-text selected">{{ selectedFile.name }}</div>
        <div class="upload-hint">{{ (selectedFile.size / 1024).toFixed(0) }} KB</div>
        <Button
          icon="pi pi-times"
          text
          severity="danger"
          size="small"
          class="clear-btn"
          @click.stop="selectedFile = null"
        />
      </template>
    </div>
    <input ref="fileInput" type="file" accept=".xlsx,.xls" style="display:none" @change="onPick" />

    <div v-if="result" class="result-box" :class="result.ok ? 'result-ok' : 'result-err'">
      <i :class="['pi', result.ok ? 'pi-check-circle' : 'pi-times-circle']" />
      {{ result.message }}
    </div>

    <template #footer>
      <Button label="Tutup" severity="secondary" text @click="visible = false" />
      <Button
        icon="pi pi-upload"
        label="Import Sekarang"
        severity="success"
        :disabled="!selectedFile"
        :loading="importing"
        @click="doImport"
      />
    </template>
  </Dialog>
</template>

<script setup>
import { ref } from 'vue'
import { tokoService } from '@/services/toko.service'
import { useToast } from '@/composables/useToast'

const emit = defineEmits(['imported'])
const toast = useToast()

const visible = ref(false)
const importing = ref(false)
const selectedFile = ref(null)
const result = ref(null)
const fileInput = ref(null)

function reset() {
  selectedFile.value = null
  result.value = null
}

function open() {
  reset()
  visible.value = true
}
defineExpose({ open })

function triggerPick() { fileInput.value?.click() }

function onPick(e) {
  const f = e.target.files[0]
  if (f) pickFile(f)
  e.target.value = ''
}

function onDrop(e) {
  const f = e.dataTransfer.files[0]
  if (f) pickFile(f)
}

function pickFile(f) {
  if (f.size > 5 * 1024 * 1024) { toast.error('File maks. 5 MB'); return }
  selectedFile.value = f
  result.value = null
}

async function doImport() {
  if (!selectedFile.value) return
  importing.value = true
  result.value = null
  try {
    const { data } = await tokoService.import(selectedFile.value)
    const msg = data.message ?? `Import selesai`
    result.value = { ok: true, message: msg }
    toast.success(msg)
    emit('imported')
  } catch (err) {
    const msg = err.response?.data?.message ?? 'Import gagal'
    result.value = { ok: false, message: msg }
    toast.error(msg)
  } finally {
    importing.value = false
  }
}
</script>

<style scoped>
.import-info {
  display: flex; gap: 0.75rem;
  padding: 0.75rem 1rem;
  background: var(--s-1);
  border: 1px solid var(--p-content-border-color);
  border-radius: 8px;
  margin-bottom: 1rem;
  font-size: 0.82rem;
}
.import-info .pi { color: var(--p-primary-color); font-size: 1.1rem; flex-shrink: 0; margin-top: 2px; }
.info-title { font-weight: 600; margin-bottom: 0.35rem; }
.info-cols { display: flex; gap: 0.4rem; margin-bottom: 0.35rem; }
.info-note { color: var(--p-text-muted-color); line-height: 1.5; }

.upload-zone {
  position: relative;
  border: 2px dashed var(--p-primary-color);
  border-radius: 10px;
  padding: 2rem 1rem;
  text-align: center;
  cursor: pointer;
  transition: background 0.2s;
  background: var(--s-0);
}
.upload-zone:hover { background: var(--p-primary-50); }

.upload-icon { font-size: 2.5rem; color: var(--c-success); display: block; margin-bottom: 0.5rem; }
.upload-icon.selected { color: var(--p-primary-color); }
.upload-text { font-weight: 600; font-size: 0.9rem; color: var(--p-text-color); }
.upload-text.selected { color: var(--p-primary-color); }
.upload-hint { font-size: 0.75rem; color: var(--p-text-muted-color); margin-top: 0.25rem; }

.clear-btn { position: absolute; top: 0.5rem; right: 0.5rem; }

.result-box {
  margin-top: 1rem;
  display: flex; align-items: center; gap: 0.5rem;
  padding: 0.65rem 1rem;
  border-radius: 8px;
  font-size: 0.85rem; font-weight: 500;
}
.result-ok  { background: var(--c-success-soft); color: var(--c-success); border: 1px solid var(--c-success-soft); }
.result-err { background: var(--c-danger-soft); color: var(--c-danger); border: 1px solid var(--c-danger-soft); }
</style>
