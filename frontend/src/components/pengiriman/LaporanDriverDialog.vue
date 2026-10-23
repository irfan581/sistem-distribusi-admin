<template>
  <!-- Laporan driver: hasil kerja di lapangan berikut foto buktinya -->
  <Dialog
    v-model:visible="terbuka"
    modal
    :style="{ width: 'min(860px, 96vw)' }"
    :header="laporan ? `Laporan Driver: Rit ${laporan.nomor_rit}` : 'Laporan Driver'"
  >
    <div v-if="laporanLoading" class="laporan-loading">
      <i class="pi pi-spin pi-spinner" /> Memuat laporan…
    </div>

    <div v-else-if="laporanError" class="laporan-kosong">
      <i class="pi pi-exclamation-triangle" />
      <span>{{ laporanError }}</span>
    </div>

    <div v-else-if="laporan" class="laporan">
      <div class="laporan-kepala">
        <div class="lk-baris">
          <span class="lk-label">Driver</span>
          <span class="lk-nilai">{{ laporan.driver?.nama_driver ?? '-' }}</span>
        </div>
        <div class="lk-baris">
          <span class="lk-label">Mulai dikerjakan</span>
          <span class="lk-nilai">{{ laporan.berangkat_at ?? 'Belum' }}</span>
        </div>
        <div class="lk-baris">
          <span class="lk-label">Rit selesai</span>
          <span class="lk-nilai">{{ laporan.selesai_at ?? 'Belum' }}</span>
        </div>
        <div class="lk-ringkas">
          <span class="rk rk-selesai">{{ laporan.ringkasan.selesai }} selesai</span>
          <span class="rk rk-masalah" v-if="laporan.ringkasan.bermasalah">{{ laporan.ringkasan.bermasalah }} bermasalah</span>
          <span class="rk rk-belum" v-if="laporan.ringkasan.belum">{{ laporan.ringkasan.belum }} belum</span>
          <span class="rk rk-foto">{{ laporan.ringkasan.foto }} foto</span>
          <span class="rk rk-belum" v-if="laporan.ringkasan.belum_ditanggapi">{{ laporan.ringkasan.belum_ditanggapi }} belum ditanggapi</span>
        </div>
      </div>

      <div class="tujuan-laporan" v-for="t in laporan.tujuan" :key="t.id">
        <div class="tl-kepala">
          <span class="tl-urutan">{{ t.urutan }}</span>
          <div class="tl-nama">
            <span class="tl-lokasi">{{ t.lokasi_pengiriman }}</span>
            <span class="tl-area" v-if="t.kode_area">{{ t.kode_area }}</span>
            <span class="tl-alamat" v-if="t.alamat_otomatis">
              <i class="pi pi-map-marker" /> {{ t.alamat_otomatis }}
            </span>
          </div>
          <span class="tl-status" :class="'sb-' + t.status_bongkar.toLowerCase()">{{ t.status_bongkar }}</span>
        </div>

        <div class="tl-jam" v-if="t.bongkar_selesai_at">
          <i class="pi pi-clock" /> Dilaporkan {{ t.bongkar_selesai_at }}
        </div>

        <div class="tl-catatan" v-if="t.catatan_driver">{{ t.catatan_driver }}</div>

        <div class="tl-foto" v-if="t.fotos.length">
          <button
            v-for="f in t.fotos"
            :key="f.id"
            type="button"
            class="tl-petak"
            :class="{ rusak: f.status_proses === 'gagal' }"
            @click="fotoPreview = f"
          >
            <img :src="f.foto_url" :alt="`Bukti ${t.lokasi_pengiriman}`" loading="lazy" />
            <span v-if="f.status_proses === 'gagal'" class="tl-rusak" v-tooltip="'Berkas hilang di server'">
              <i class="pi pi-exclamation-triangle" />
            </span>
          </button>
        </div>

        <div class="tl-nofoto" v-else-if="t.status_bongkar !== 'Menunggu'">
          Tidak ada foto tersimpan.
        </div>

        <!-- Tanggapan hanya untuk tujuan yang sudah dilaporkan driver -->
        <TanggapanFoto
          v-if="TUNTAS.includes(t.status_bongkar)"
          :tanggapan="t.tanggapan"
          :boleh-ubah="can('edit_pengiriman')"
          :simpan="payload => simpanTanggapan(t, payload)"
          @tersimpan="baru => tanggapanTersimpan(t, baru)"
        />
      </div>
    </div>
  </Dialog>

  <!-- Foto diperbesar: surat jalan sering perlu dibaca nomornya -->
  <Dialog
    :visible="!!fotoPreview"
    modal
    dismissableMask
    :style="{ width: 'min(720px, 96vw)' }"
    header="Foto Bukti"
    @update:visible="v => { if (!v) fotoPreview = null }"
  >
    <div class="foto-besar" v-if="fotoPreview">
      <img :src="fotoPreview.foto_url" alt="Foto bukti bongkar" />
      <dl class="foto-meta">
        <div><dt>Diambil</dt><dd>{{ fotoPreview.diambil_pada ?? '-' }}</dd></div>
        <div><dt>Diterima server</dt><dd>{{ fotoPreview.diterima_pada ?? '-' }}</dd></div>
        <div><dt>Alamat</dt><dd>{{ fotoPreview.alamat ?? 'Tidak terbaca' }}</dd></div>
        <div v-if="fotoPreview.latitude">
          <dt>Koordinat</dt>
          <dd>
            <a :href="`https://www.google.com/maps?q=${fotoPreview.latitude},${fotoPreview.longitude}`" target="_blank" rel="noopener">
              {{ fotoPreview.latitude }}, {{ fotoPreview.longitude }}
              <i class="pi pi-external-link" />
            </a>
            <span v-if="fotoPreview.accuracy"> &middot; ±{{ Math.round(fotoPreview.accuracy) }} m</span>
          </dd>
        </div>
        <div v-if="fotoPreview.is_mock" class="foto-mock">
          <dt>Peringatan</dt><dd>Lokasi ditandai palsu oleh Android.</dd>
        </div>
      </dl>
    </div>
  </Dialog>
</template>

<script setup>
/**
 * Laporan hasil kerja driver untuk satu rit, dipakai bersama oleh halaman
 * Daftar Muat (operasional hari ini) dan Riwayat Pengiriman (rekap periode).
 *
 * Isinya sengaja diambil saat dialog dibuka, bukan ikut di daftar rit: satu
 * rit bisa membawa puluhan foto, dan menariknya untuk seluruh halaman membuat
 * tabel lambat padahal sebagian besar barisnya tidak pernah dibuka.
 */
import { ref, watch } from 'vue'
import { daftarMuatService } from '@/services/daftarMuat.service'
import { usePermission } from '@/composables/usePermission'
import { useToast } from '@/composables/useToast'
import { useTanggapanStore } from '@/stores/tanggapan.store'
import TanggapanFoto from '@/components/common/TanggapanFoto.vue'

const props = defineProps({
  visible: { type: Boolean, default: false },
  ritId:   { type: [Number, String], default: null },
})

// tanggapan-berubah: halaman induk memuat ulang penanda "belum ditanggapi" per rit.
const emit = defineEmits(['update:visible', 'tanggapan-berubah'])

const TUNTAS = ['Selesai', 'Bermasalah', 'Gagal']
const { can } = usePermission()
const toast = useToast()
const tanggapanStore = useTanggapanStore()

async function simpanTanggapan(tujuan, payload) {
  const res = await daftarMuatService.tanggapiLaporan(laporan.value.id, tujuan.id, payload)
  toast.success(`Laporan ${payload.status}. Driver diberi tahu lewat HP.`)
  return res.data
}

function tanggapanTersimpan(tujuan, baru) {
  const sebelumnya = tujuan.tanggapan?.status
  tujuan.tanggapan = baru
  if (!sebelumnya && laporan.value.ringkasan.belum_ditanggapi > 0) laporan.value.ringkasan.belum_ditanggapi--
  tanggapanStore.muat()
  emit('tanggapan-berubah', laporan.value.id)
}

const terbuka = ref(props.visible)

const laporanLoading = ref(false)
const laporanError   = ref(null)
const laporan        = ref(null)
const fotoPreview    = ref(null)

watch(() => props.visible, v => { terbuka.value = v })
watch(terbuka, v => {
  emit('update:visible', v)
  // Foto yang diperbesar ikut ditutup, kalau tidak ia menggantung sendirian
  // di layar setelah dialog laporannya hilang.
  if (!v) fotoPreview.value = null
})

// Selalu ambil ulang tiap dialog dibuka: foto bisa bertambah kapan saja dari
// antrian unggah driver, jadi isi yang di-cache akan cepat basi.
watch(
  () => [props.visible, props.ritId],
  ([tampil, id]) => { if (tampil && id) muat(id) },
  { immediate: true },
)

async function muat(id) {
  laporanLoading.value = true
  laporanError.value   = null
  laporan.value        = null

  try {
    const res     = await daftarMuatService.laporanDriver(id)
    laporan.value = res.data
  } catch (err) {
    laporanError.value = err?.response?.data?.message ?? 'Laporan gagal dimuat.'
  } finally {
    laporanLoading.value = false
  }
}
</script>

<style scoped>
/* ── Dialog laporan driver ───────────────────────────────── */
.laporan-loading,
.laporan-kosong  { display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 2.5rem; color: var(--p-text-muted-color); font-size: 0.875rem; }

.laporan         { display: flex; flex-direction: column; gap: 0.9rem; }

.laporan-kepala  { display: flex; flex-wrap: wrap; gap: 0.5rem 1.75rem; padding: 0.9rem 1rem; background: var(--p-content-background, var(--s-2)); border: 1px solid var(--p-content-border-color, var(--s-border)); border-radius: 10px; }
.lk-baris        { display: flex; flex-direction: column; gap: 1px; }
.lk-label        { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--p-text-muted-color); }
.lk-nilai        { font-size: 0.85rem; font-weight: 600; }
.lk-ringkas      { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; margin-left: auto; }
.rk              { font-size: 0.7rem; font-weight: 700; padding: 2px 8px; border-radius: 8px; }
.rk-selesai      { background: var(--c-success-soft); color: var(--c-success); }
.rk-masalah      { background: var(--c-warning-soft); color: var(--c-warning); }
.rk-belum        { background: var(--s-2); color: var(--t-secondary); }
.rk-foto         { background: var(--c-nila-soft); color: var(--c-nila); }

.tujuan-laporan  { border: 1px solid var(--p-content-border-color, var(--s-border)); border-radius: 10px; padding: 0.85rem 1rem; display: flex; flex-direction: column; gap: 0.6rem; }
.tl-kepala       { display: flex; align-items: center; gap: 0.65rem; }
.tl-urutan       { flex: none; width: 26px; height: 26px; border-radius: 50%; background: var(--c-brand-50, var(--c-brand-soft)); color: var(--c-brand-600); font-size: 0.78rem; font-weight: 700; display: flex; align-items: center; justify-content: center; }
.tl-nama         { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.tl-lokasi       { font-weight: 600; font-size: 0.9rem; }
.tl-area         { font-size: 0.7rem; color: var(--p-text-muted-color); }
.tl-alamat       { font-size: 0.72rem; color: var(--p-text-muted-color); display: flex; align-items: flex-start; gap: 0.3rem; }
.tl-alamat i     { font-size: 0.7rem; margin-top: 2px; flex-shrink: 0; }
.tl-status       { margin-left: auto; font-size: 0.68rem; font-weight: 700; padding: 2px 8px; border-radius: 8px; white-space: nowrap; }
.sb-menunggu     { background: var(--s-2); color: var(--t-secondary); }
.sb-tiba         { background: var(--c-info-soft); color: var(--c-info); }
.sb-selesai      { background: var(--c-success-soft); color: var(--c-success); }
.sb-bermasalah   { background: var(--c-warning-soft); color: var(--c-warning); }
.sb-gagal        { background: var(--c-danger-soft); color: var(--c-danger); }

.tl-jam          { font-size: 0.73rem; color: var(--p-text-muted-color); display: flex; align-items: center; gap: 0.35rem; }
.tl-catatan      { font-size: 0.83rem; line-height: 1.5; background: var(--p-content-background, var(--s-2)); border-radius: 8px; padding: 0.6rem 0.75rem; }
.tl-nofoto       { font-size: 0.75rem; color: var(--c-warning); }

.tl-foto         { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.tl-petak        { position: relative; width: 96px; height: 96px; padding: 0; border: 1px solid var(--p-content-border-color, var(--s-border)); border-radius: 8px; overflow: hidden; cursor: pointer; background: none; }
.tl-petak img    { width: 100%; height: 100%; object-fit: cover; display: block; }
.tl-petak:hover  { border-color: var(--c-brand-600); }
.tl-petak:focus-visible { outline: 2px solid var(--c-brand-600); outline-offset: 2px; }
.tl-petak.rusak  { border-color: var(--c-danger); }
.tl-rusak        { position: absolute; top: 4px; right: 4px; background: var(--c-danger); color: #fff; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; }

/* ── Foto diperbesar ─────────────────────────────────────── */
.foto-besar      { display: flex; flex-direction: column; gap: 0.9rem; }
.foto-besar img  { width: 100%; max-height: 60vh; object-fit: contain; border-radius: 8px; background: var(--t-primary); }
.foto-meta       { margin: 0; display: flex; flex-wrap: wrap; gap: 0.6rem 1.75rem; }
.foto-meta div   { display: flex; flex-direction: column; gap: 1px; }
.foto-meta dt    { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--p-text-muted-color); }
.foto-meta dd    { margin: 0; font-size: 0.82rem; font-weight: 600; }
.foto-meta a     { color: var(--c-brand-600); text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem; }
.foto-meta a:hover { text-decoration: underline; }
.foto-mock dd    { color: var(--c-danger); }
</style>
