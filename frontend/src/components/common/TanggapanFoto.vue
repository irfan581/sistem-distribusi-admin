<template>
  <!-- Tanggapan admin atas bukti foto: diterima / ditolak (+ alasan wajib) -->
  <div class="tanggapan" :class="kelasStatus">
    <div v-if="tanggapan?.status && !mengubah" class="tg-hasil">
      <span class="tg-label">
        <i :class="tanggapan.status === 'diterima' ? 'pi pi-check-circle' : 'pi pi-times-circle'" />
        {{ tanggapan.status === 'diterima' ? 'Diterima' : 'Ditolak' }}
      </span>
      <span class="tg-oleh" v-if="tanggapan.oleh || tanggapan.otomatis || tanggapan.pada">
        {{ [tanggapan.otomatis ? 'Otomatis · lokasi aman' : tanggapan.oleh, tanggapan.pada].filter(Boolean).join(' · ') }}
      </span>
      <p class="tg-catatan" v-if="tanggapan.catatan">{{ tanggapan.catatan }}</p>
      <Button v-if="bolehUbah" label="Ubah" size="small" text @click="mulaiUbah" />
    </div>

    <template v-else-if="bolehUbah">
      <div class="tg-judul">
        Tanggapan admin
        <span v-if="tanggapan?.perlu && !tanggapan?.status" class="tg-belum">belum ditanggapi</span>
      </div>
      <Textarea
        v-model="catatan"
        rows="2"
        autoResize
        fluid
        :invalid="!!galat"
        placeholder="Catatan (wajib bila ditolak), mis. foto buram, bukan di toko"
      />
      <small v-if="galat" class="tg-galat">{{ galat }}</small>
      <div class="tg-aksi">
        <Button label="Diterima" icon="pi pi-check" size="small" severity="success" :loading="menyimpan === 'diterima'"
                :disabled="!!menyimpan" @click="kirim('diterima')" />
        <Button label="Ditolak" icon="pi pi-times" size="small" severity="danger" outlined :loading="menyimpan === 'ditolak'"
                :disabled="!!menyimpan" @click="kirim('ditolak')" />
        <Button v-if="mengubah" label="Batal" size="small" text :disabled="!!menyimpan" @click="mengubah = false" />
      </div>
    </template>

    <div v-else class="tg-judul">
      Tanggapan admin: <span class="tg-belum">{{ tanggapan?.perlu ? 'belum ditanggapi' : 'tidak ada' }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  tanggapan: { type: Object, default: null },
  // Fungsi penyimpan dari halaman induk: (payload) => Promise<tanggapanBaru>.
  simpan:    { type: Function, required: true },
  bolehUbah: { type: Boolean, default: true },
})
const emit = defineEmits(['tersimpan'])

const catatan   = ref('')
const galat     = ref('')
const menyimpan = ref(null)
const mengubah  = ref(false)

const kelasStatus = computed(() => props.tanggapan?.status ? `tg-${props.tanggapan.status}` : '')

function mulaiUbah() {
  catatan.value = props.tanggapan?.catatan ?? ''
  galat.value = ''
  mengubah.value = true
}

async function kirim(status) {
  galat.value = ''
  if (status === 'ditolak' && !catatan.value.trim()) {
    galat.value = 'Tulis alasannya dulu bila foto ditolak.'
    return
  }
  menyimpan.value = status
  try {
    const baru = await props.simpan({ status, catatan: catatan.value.trim() || null })
    mengubah.value = false
    catatan.value = ''
    emit('tersimpan', baru)
  } catch (err) {
    galat.value = err?.response?.data?.errors?.catatan?.[0] ?? err?.response?.data?.message ?? 'Gagal menyimpan tanggapan.'
  } finally {
    menyimpan.value = null
  }
}
</script>

<style scoped>
.tanggapan     { display: flex; flex-direction: column; gap: 0.45rem; padding: 0.75rem; border-radius: 10px;
                 border: 1px dashed var(--p-content-border-color); }
.tanggapan.tg-diterima { border-style: solid; border-color: var(--c-success-line); background: var(--c-success-soft); }
.tanggapan.tg-ditolak  { border-style: solid; border-color: var(--c-danger-line);  background: var(--c-danger-soft); }
.tg-judul      { font-size: 0.8rem; font-weight: 600; color: var(--t-secondary, inherit); }
.tg-belum      { margin-left: 0.4rem; font-size: 0.72rem; font-weight: 600; color: var(--c-warning); }
.tg-hasil      { display: flex; flex-wrap: wrap; align-items: center; gap: 0.25rem 0.75rem; }
.tg-label      { display: inline-flex; align-items: center; gap: 0.35rem; font-weight: 700; font-size: 0.85rem; }
.tg-diterima .tg-label { color: var(--c-success); }
.tg-ditolak  .tg-label { color: var(--c-danger); }
.tg-oleh       { font-size: 0.75rem; color: var(--t-muted); }
.tg-catatan    { flex-basis: 100%; margin: 0; font-size: 0.82rem; white-space: pre-line; }
.tg-aksi       { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.tg-galat      { color: var(--c-danger); font-size: 0.75rem; }
</style>
