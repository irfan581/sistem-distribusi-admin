<template>
  <Dialog
    v-model:visible="visible"
    :header="isEdit ? 'Edit Toko' : 'Tambah Toko Baru'"
    modal
    :style="{ width: '480px', maxWidth: '96vw' }"
    @hide="reset"
  >
    <div class="field">
      <label>Sales Representative <span class="req">*</span></label>
      <Select
        v-model="form.sales_id"
        :options="salesOptions"
        optionLabel="label"
        optionValue="id"
        placeholder="Pilih Sales"
        class="w-full"
        filter
        :loading="loadingSales"
      />
      <small class="err" v-if="errors.sales_id">{{ errors.sales_id }}</small>
    </div>

    <div class="field">
      <label>Nama Kios <span class="req">*</span></label>
      <InputText
        ref="namaInput"
        v-model="form.nama_kios"
        placeholder="Contoh: Toko Sumber Jaya"
        class="w-full"
        @keyup.enter="submit"
      />
      <small class="err" v-if="errors.nama_kios">{{ errors.nama_kios }}</small>
    </div>

    <div class="field">
      <label>Wilayah <span class="req">*</span></label>
      <InputText v-model="form.wilayah" placeholder="Contoh: Surabaya Barat" class="w-full" />
      <small class="err" v-if="errors.wilayah">{{ errors.wilayah }}</small>
    </div>

    <template v-if="isEdit">
      <div class="field">
        <label>Alamat</label>
        <InputText v-model="form.alamat" placeholder="Terisi otomatis dari kunjungan sales" class="w-full" />
        <small class="err" v-if="errors.alamat">{{ errors.alamat }}</small>
      </div>

      <div class="field-row">
        <div class="field">
          <label>Lat</label>
          <InputText v-model="form.lat" placeholder="-7.7231435" class="w-full" />
          <small class="err" v-if="errors.lat">{{ errors.lat }}</small>
        </div>
        <div class="field">
          <label>Long</label>
          <InputText v-model="form.lng" placeholder="111.5327588" class="w-full" />
          <small class="err" v-if="errors.lng">{{ errors.lng }}</small>
        </div>
      </div>
      <div class="lokasi-info">
        <span>Titik saat ini: <b>{{ labelSumber }}</b></span>
        <a v-if="petaUrl" :href="petaUrl" target="_blank" rel="noopener">Buka peta</a>
      </div>
      <small class="hint">Mengubah titik menandainya "Manual". Kosongkan Lat dan Long agar kembali diisi dari kunjungan.</small>
    </template>

    <p v-if="!isEdit && jumlahDitambah" class="hitung">
      <i class="pi pi-check-circle" />
      {{ jumlahDitambah }} kios ditambahkan untuk sales ini
    </p>

    <template #footer>
      <!-- Dalam mode tambah, dialognya sengaja tidak menutup sesudah
           menyimpan: sales dan wilayah dipertahankan, hanya nama kios yang
           dikosongkan lalu difokuskan ulang. Memasukkan lima puluh kios
           berarti mengulang pilih-sales dan ketik-wilayah lima puluh kali,
           padahal untuk satu rombongan isinya sama persis. -->
      <Button :label="isEdit ? 'Batal' : 'Selesai'" severity="secondary" text @click="visible = false" />
      <Button
        :label="isEdit ? 'Simpan' : 'Tambah & Lanjut'"
        icon="pi pi-check"
        :loading="saving"
        @click="submit"
      />
    </template>
  </Dialog>
</template>

<script setup>
import { ref, reactive, computed, nextTick } from 'vue'
import { tokoService } from '@/services/toko.service'
import { useToast } from '@/composables/useToast'

const emit = defineEmits(['saved'])
const toast = useToast()

const visible = ref(false)
const saving = ref(false)
const loadingSales = ref(false)
const salesOptions = ref([])
const isEdit = ref(false)
const editId = ref(null)
const errors = ref({})
const namaInput = ref(null)
const jumlahDitambah = ref(0)

const form = reactive({ sales_id: null, nama_kios: '', wilayah: '', alamat: '', lat: null, lng: null })
const sumberLokasi = ref(null)

/** Angka dari kolom isian; koma desimal diterima, kosong jadi null. */
function angka(nilai) {
  const teks = String(nilai ?? '').trim().replace(',', '.')
  return teks === '' ? null : teks
}

const labelSumber = computed(() => {
  if (sumberLokasi.value === 'manual') return 'dikoreksi manual'
  if (sumberLokasi.value === 'kunjungan') return 'dari kunjungan sales'
  return 'belum ada'
})

const petaUrl = computed(() => {
  const lat = Number(angka(form.lat))
  const lng = Number(angka(form.lng))
  if (angka(form.lat) === null || angka(form.lng) === null || Number.isNaN(lat) || Number.isNaN(lng)) return null
  return `https://www.google.com/maps?q=${lat},${lng}`
})

function reset() {
  form.sales_id = null
  form.nama_kios = ''
  form.wilayah = ''
  form.alamat = ''
  form.lat = null
  form.lng = null
  sumberLokasi.value = null
  errors.value = {}
  isEdit.value = false
  editId.value = null
  jumlahDitambah.value = 0
}

/** Kosongkan nama kiosnya saja, lalu kembalikan kursor ke sana. */
async function siapUntukBerikutnya() {
  form.nama_kios = ''
  errors.value = {}
  await nextTick()
  namaInput.value?.$el?.focus()
}

async function loadSales() {
  loadingSales.value = true
  try {
    const { data } = await tokoService.salesOptions()
    salesOptions.value = data.data ?? data
  } finally {
    loadingSales.value = false
  }
}

function openCreate() {
  reset()
  visible.value = true
  loadSales()
}

function openEdit(toko) {
  reset()
  isEdit.value = true
  editId.value = toko.id
  form.sales_id = toko.sales_id
  form.nama_kios = toko.nama_kios
  form.wilayah = toko.wilayah
  form.alamat = toko.alamat ?? ''
  form.lat = toko.lat
  form.lng = toko.lng
  sumberLokasi.value = toko.lokasi_sumber ?? null
  visible.value = true
  loadSales()
}

defineExpose({ openCreate, openEdit })

async function submit() {
  errors.value = {}
  saving.value = true
  try {
    if (isEdit.value) {
      await tokoService.update(editId.value, { ...form, lat: angka(form.lat), lng: angka(form.lng) })
      toast.success('Toko berhasil diperbarui')
      visible.value = false
    } else {
      await tokoService.store({ sales_id: form.sales_id, nama_kios: form.nama_kios, wilayah: form.wilayah })
      toast.success(`"${form.nama_kios}" ditambahkan`)
      jumlahDitambah.value++
      await siapUntukBerikutnya()
    }
    emit('saved')
  } catch (err) {
    const data = err.response?.data
    if (data?.errors) {
      errors.value = Object.fromEntries(
        Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
      )
    } else {
      toast.error(data?.message || 'Terjadi kesalahan')
    }
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.field { display: flex; flex-direction: column; gap: 0.3rem; margin-bottom: 1rem; }
.field label { font-size: 0.82rem; font-weight: 600; color: var(--p-text-color); }
.req { color: var(--c-danger); }
.err { color: var(--c-danger); font-size: 0.75rem; }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.field-row .field { margin-bottom: 0.5rem; }
.lokasi-info {
  display: flex; align-items: center; justify-content: space-between;
  font-size: 0.78rem; color: var(--p-text-muted-color);
}
.lokasi-info b { color: var(--p-text-color); }
.hint { display: block; margin: 0.35rem 0 1rem; font-size: 0.75rem; color: var(--p-text-muted-color); }
.hitung {
  display: flex; align-items: center; gap: 0.4rem;
  margin: 0; font-size: 0.78rem; font-weight: 600;
  color: var(--c-success);
}
</style>
