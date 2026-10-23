<template>
  <div class="employee-detail">
    <PageHeader :title="sales?.nama || 'Detail Sales'" subtitle="Informasi lengkap dan statistik sales.">
      <template #actions>
        <Button label="Kembali" icon="pi pi-arrow-left" text @click="$router.back()" />
        <template v-if="sales">
          <Button v-if="can('edit_sales')" icon="pi pi-pencil" label="Edit" severity="secondary" outlined @click="openEdit" />
          <Button v-if="can('export_pdf')" icon="pi pi-download" label="Unduh PDF" severity="success" outlined @click="pdfDialogVisible = true" />
          <Button v-if="can('edit_sales')" icon="pi pi-mobile" label="Reset Device" severity="warn" outlined @click="confirmResetDevice" />
          <Button v-if="can('edit_sales')" icon="pi pi-key" label="Reset Password" severity="danger" outlined @click="resetPwDialogVisible = true" />
        </template>
      </template>
    </PageHeader>

    <div v-if="loading" class="detail-skeleton">
      <div class="detail-grid">
        <Skeleton height="420px" />
        <Skeleton height="420px" />
      </div>
    </div>

    <div v-else-if="sales" class="detail-grid">
      <!-- Profil Card -->
      <Card class="profile-card">
        <template #content>
          <div class="profile-content">
            <SalesAvatar :src="sales.foto_profil_url" :nama="sales.nama" :size="80" class="profile-avatar" />
            <h2>{{ sales.nama }}</h2>
            <p class="profile-sub">Area: {{ sales.area || '-' }}</p>
            <div class="tag-row">
              <Tag :value="sales.is_active ? 'Aktif' : 'Nonaktif'" :severity="sales.is_active ? 'success' : 'secondary'" />
              <Tag v-if="sales.is_spv" value="Supervisor" severity="info" />
            </div>
          </div>

          <Divider />

          <div class="info-list">
            <div class="info-item">
              <i class="pi pi-id-card" />
              <div>
                <span class="info-label">NIK</span>
                <span class="info-value">{{ sales.nik }}</span>
              </div>
            </div>
            <div class="info-item">
              <i class="pi pi-phone" />
              <div>
                <span class="info-label">No. HP</span>
                <a :href="`tel:${sales.no_hp}`" class="info-value phone-link">{{ sales.no_hp || '-' }}</a>
              </div>
            </div>
            <div class="info-item">
              <i class="pi pi-map-marker" />
              <div>
                <span class="info-label">Alamat</span>
                <span class="info-value">{{ sales.alamat || '-' }}</span>
              </div>
            </div>
            <div class="info-item">
              <i class="pi pi-target" />
              <div>
                <span class="info-label">Target Kunjungan / Hari</span>
                <span class="info-value">{{ sales.target_kunjungan ?? 3 }} toko</span>
              </div>
            </div>
            <div class="info-item">
              <i class="pi pi-desktop" />
              <div>
                <span class="info-label">Device ID</span>
                <span class="info-value" style="font-size:0.75rem; word-break:break-all;">
                  {{ sales.device_id || '-' }}
                </span>
              </div>
            </div>
          </div>

          <Divider />

          <div v-if="detail" class="stats-mini-grid">
            <div class="stats-mini-item">
              <span class="stats-mini-val">{{ detail.statistik_bulan.total_hadir }}</span>
              <span class="stats-mini-label">Hadir</span>
            </div>
            <div class="stats-mini-item">
              <span class="stats-mini-val warn">{{ detail.statistik_bulan.terlambat }}</span>
              <span class="stats-mini-label">Terlambat</span>
            </div>
            <div class="stats-mini-item">
              <span class="stats-mini-val success">{{ detail.statistik_bulan.total_kunjungan }}</span>
              <span class="stats-mini-label">Kunjungan</span>
            </div>
            <div class="stats-mini-item">
              <span class="stats-mini-val info">{{ detail.statistik_bulan.total_izin }}</span>
              <span class="stats-mini-label">Izin</span>
            </div>
          </div>
        </template>
      </Card>

      <!-- Right Column -->
      <div class="right-col">
        <Card v-if="detail">
          <template #header>
            <div class="card-header"><span class="card-title">Status Hari Ini</span></div>
          </template>
          <template #content>
            <div v-if="detail.presensi_hari_ini" class="today-status">
              <div class="today-row">
                <span class="today-label"><i class="pi pi-sign-in" /> Absen Masuk</span>
                <span class="today-val">{{ detail.presensi_hari_ini.jam_masuk || '-' }}</span>
                <Tag :value="detail.presensi_hari_ini.status_label" :severity="detail.presensi_hari_ini.status === 'tepat_waktu' ? 'success' : 'warn'" />
              </div>
              <div class="today-row" v-if="detail.presensi_hari_ini.jam_pulang">
                <span class="today-label"><i class="pi pi-sign-out" /> Absen Pulang</span>
                <span class="today-val">{{ detail.presensi_hari_ini.jam_pulang }}</span>
                <Tag value="Sudah Pulang" severity="secondary" />
              </div>
            </div>
            <div v-else class="empty-state-small">
              <i class="pi pi-calendar-times" />
              <span>Belum absen hari ini</span>
            </div>
          </template>
        </Card>

        <Card>
          <template #header>
            <div class="card-header"><span class="card-title">Riwayat Presensi Bulan Ini</span></div>
          </template>
          <template #content>
            <DataTable :value="presensi" :loading="presensiLoading" stripedRows size="small" scrollable scrollHeight="320px">
              <Column field="tanggal" header="Tanggal" style="min-width:120px">
                <template #body="{ data }">{{ formatDate(data.tanggal) }}</template>
              </Column>
              <Column field="jam_masuk" header="Masuk" style="min-width:90px">
                <template #body="{ data }">{{ data.jam_masuk || '-' }}</template>
              </Column>
              <Column field="jam_pulang" header="Pulang" style="min-width:90px">
                <template #body="{ data }">{{ data.jam_pulang || '-' }}</template>
              </Column>
              <Column field="status_label" header="Status" style="min-width:120px">
                <template #body="{ data }">
                  <Tag :value="data.status_label" :severity="data.status === 'tepat_waktu' ? 'success' : data.status === 'terlambat' ? 'warn' : 'secondary'" />
                </template>
              </Column>
              <template #empty>
                <div class="empty-state-small">Belum ada presensi bulan ini</div>
              </template>
            </DataTable>
          </template>
        </Card>
      </div>
    </div>

    <div v-else class="empty-state">
      <i class="pi pi-user" />
      <span>Sales tidak ditemukan</span>
    </div>

    <!-- Edit Dialog -->
    <EmployeeDialog
      v-model:visible="editDialogVisible"
      :employee="sales"
      @saved="fetchDetail"
    />

    <!-- Reset Password Dialog -->
    <Dialog v-model:visible="resetPwDialogVisible" header="Reset Password Sales" :style="{ width: '400px' }" modal :draggable="false">
      <div v-if="sales" class="reset-info">
        <i class="pi pi-user" />
        <span>{{ sales.nama }}</span>
      </div>
      <div class="field mt-3">
        <label class="field-label">Password Baru <span class="req">*</span></label>
        <Password v-model="resetPwForm.password" :feedback="false" toggleMask fluid />
      </div>
      <div class="field mt-2">
        <label class="field-label">Konfirmasi Password <span class="req">*</span></label>
        <Password v-model="resetPwForm.password_confirmation" :feedback="false" toggleMask fluid />
      </div>
      <template #footer>
        <Button label="Batal" text plain @click="resetPwDialogVisible = false" />
        <Button label="Reset Password" icon="pi pi-shield" severity="danger" :loading="resettingPw" @click="doResetPassword" />
      </template>
    </Dialog>

    <!-- Download PDF Dialog -->
    <Dialog v-model:visible="pdfDialogVisible" header="Unduh Rekap Aktivitas (PDF)" :style="{ width: '460px' }" modal :draggable="false">
      <div class="pdf-form">
        <div class="form-row">
          <div class="field">
            <label class="field-label">Dari Tanggal <span class="req">*</span></label>
            <DatePicker v-model="pdfForm.dari" dateFormat="dd/mm/yy" showIcon fluid />
          </div>
          <div class="field">
            <label class="field-label">Sampai Tanggal <span class="req">*</span></label>
            <DatePicker v-model="pdfForm.sampai" dateFormat="dd/mm/yy" showIcon fluid />
          </div>
        </div>
        <div class="field mt-3">
          <label class="field-label">Data yang Disertakan</label>
          <div class="checkbox-group">
            <div v-for="opt in tipeOptions" :key="opt.value" class="checkbox-item">
              <Checkbox v-model="pdfForm.tipe" :inputId="opt.value" :value="opt.value" />
              <label :for="opt.value">{{ opt.label }}</label>
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Batal" text plain @click="pdfDialogVisible = false" />
        <Button label="Unduh PDF" icon="pi pi-download" severity="success" :loading="downloadingPdf" @click="doDownloadPdf" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import { employeeService } from '@/services/employee.service'
import { attendanceService } from '@/services/attendance.service'
import { useToast } from '@/composables/useToast'
import { usePermission } from '@/composables/usePermission'
import { formatDate } from '@/utils/helpers'
import { STORAGE_KEYS, API_BASE_URL } from '@/utils/constants'
import PageHeader from '@/components/common/PageHeader.vue'

const { can } = usePermission()
import EmployeeDialog from '@/components/employees/EmployeeDialog.vue'

const route = useRoute()
const toast = useToast()
const confirm = useConfirm()

const loading = ref(true)
const presensiLoading = ref(false)
const sales = ref(null)
const detail = ref(null)
const presensi = ref([])

const editDialogVisible = ref(false)
const resetPwDialogVisible = ref(false)
const pdfDialogVisible = ref(false)
const resettingPw = ref(false)
const downloadingPdf = ref(false)

const resetPwForm = reactive({ password: '', password_confirmation: '' })

const now = new Date()
const firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1)

const pdfForm = reactive({
  dari: firstOfMonth,
  sampai: now,
  tipe: ['absen', 'toko', 'izin'],
})

const tipeOptions = [
  { label: 'Presensi (Masuk & Pulang)', value: 'absen' },
  { label: 'Kunjungan Toko', value: 'toko' },
  { label: 'Izin / Sakit', value: 'izin' },
]

async function fetchDetail() {
  loading.value = true
  try {
    const res = await employeeService.getById(route.params.id)
    detail.value = res.data
    sales.value = res.data.sales
  } catch (err) {
    toast.apiError(err)
  } finally {
    loading.value = false
  }
}

async function fetchPresensi() {
  presensiLoading.value = true
  try {
    const y = now.getFullYear()
    const m = String(now.getMonth() + 1).padStart(2, '0')
    const res = await attendanceService.getAll({
      sales_id: route.params.id,
      tanggal_mulai: `${y}-${m}-01`,
      tanggal_akhir: `${y}-${m}-31`,
      per_page: 31,
    })
    presensi.value = res.data?.data || res.data || []
  } catch (err) {
    toast.apiError(err)
    presensi.value = []
  } finally {
    presensiLoading.value = false
  }
}

function openEdit() {
  editDialogVisible.value = true
}

function confirmResetDevice() {
  confirm.require({
    message: `Reset device ID ${sales.value?.nama}? Sales harus login ulang dari perangkat baru.`,
    header: 'Konfirmasi Reset Device',
    icon: 'pi pi-mobile',
    acceptLabel: 'Ya, Reset',
    rejectLabel: 'Batal',
    acceptClass: 'p-button-warning',
    accept: doResetDevice,
  })
}

async function doResetDevice() {
  try {
    await employeeService.resetDevice(route.params.id)
    toast.success('Device ID berhasil direset.')
    sales.value = { ...sales.value, device_id: null }
  } catch (err) {
    toast.apiError(err)
  }
}

async function doResetPassword() {
  if (!resetPwForm.password) { toast.error('Password wajib diisi.'); return }
  if (resetPwForm.password !== resetPwForm.password_confirmation) { toast.error('Password tidak cocok.'); return }
  resettingPw.value = true
  try {
    await employeeService.resetPassword(route.params.id, resetPwForm)
    toast.success('Password berhasil direset.')
    resetPwDialogVisible.value = false
    resetPwForm.password = ''
    resetPwForm.password_confirmation = ''
  } catch (err) {
    toast.apiError(err)
  } finally {
    resettingPw.value = false
  }
}

function toYMD(date) {
  const d = new Date(date)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

async function doDownloadPdf() {
  if (!pdfForm.dari || !pdfForm.sampai) { toast.error('Tanggal wajib diisi.'); return }
  if (!pdfForm.tipe.length) { toast.error('Pilih minimal satu jenis data.'); return }

  downloadingPdf.value = true
  try {
    const token = localStorage.getItem(STORAGE_KEYS.TOKEN)
    const dari   = toYMD(pdfForm.dari)
    const sampai = toYMD(pdfForm.sampai)
    const tipeParam = pdfForm.tipe.map(t => `tipe[]=${t}`).join('&')
    const url = `${API_BASE_URL}/admin/sales/${route.params.id}/report-pdf?dari=${dari}&sampai=${sampai}&${tipeParam}`

    const res = await fetch(url, {
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/pdf' },
    })

    if (!res.ok) {
      const err = await res.json().catch(() => ({}))
      toast.error(err.message || 'Gagal mengunduh PDF.')
      return
    }

    const blob = await res.blob()
    const link = document.createElement('a')
    link.href = URL.createObjectURL(blob)
    link.download = `Rekap_${sales.value.nama}_${dari}.pdf`
    link.click()
    URL.revokeObjectURL(link.href)
    pdfDialogVisible.value = false
  } catch (err) {
    toast.error('Gagal mengunduh PDF.')
  } finally {
    downloadingPdf.value = false
  }
}

onMounted(() => {
  fetchDetail()
  fetchPresensi()
})
</script>

<style scoped>
.employee-detail { display: flex; flex-direction: column; gap: 1.5rem; animation: fadeUp var(--dur-base) var(--ease) both; }

.detail-grid {
  display: grid;
  grid-template-columns: 300px 1fr;
  gap: 1.5rem;
  align-items: start;
}

.right-col { display: flex; flex-direction: column; gap: 1rem; }

.profile-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  text-align: center;
  padding-bottom: 1rem;
}

.profile-avatar {
  background: var(--p-primary-100) !important;
  color: var(--p-primary-700) !important;
}

.profile-content h2 { font-size: 1.2rem; font-weight: 700; margin: 0; }
.profile-sub { font-size: 0.85rem; color: var(--p-text-muted-color); margin: 0; }
.tag-row { display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: center; }

.info-list { display: flex; flex-direction: column; gap: 0.75rem; }
.info-item { display: flex; align-items: flex-start; gap: 0.75rem; }
.info-item > i { margin-top: 0.1rem; color: var(--p-primary-500); width: 16px; flex-shrink: 0; }
.info-item > div { display: flex; flex-direction: column; }
.info-label { font-size: 0.75rem; color: var(--p-text-muted-color); }
.info-value { font-size: 0.875rem; font-weight: 500; }
.phone-link { color: var(--p-primary-500); text-decoration: none; }

.stats-mini-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.5rem;
  text-align: center;
}
.stats-mini-item { display: flex; flex-direction: column; gap: 0.2rem; padding: 0.5rem; background: var(--s-1); border-radius: 8px; }
.stats-mini-val { font-size: 1.4rem; font-weight: 800; color: var(--p-text-color); }
.stats-mini-val.warn { color: var(--p-yellow-600); }
.stats-mini-val.success { color: var(--p-green-600); }
.stats-mini-val.info { color: var(--p-cyan-600); }
.stats-mini-label { font-size: 0.7rem; color: var(--p-text-muted-color); }

.card-header { padding: 1rem 1.25rem 0; }
.card-title { font-weight: 700; font-size: 0.95rem; }

.today-status { display: flex; flex-direction: column; gap: 0.75rem; }
.today-row { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
.today-label { font-size: 0.875rem; color: var(--p-text-muted-color); display: flex; align-items: center; gap: 0.4rem; min-width: 120px; }
.today-val { font-size: 1.1rem; font-weight: 700; color: var(--p-text-color); }

.reset-info { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem; background: var(--s-2); border-radius: 8px; font-weight: 600; }
.field-label { font-size: 0.875rem; font-weight: 500; color: var(--p-text-color); display: block; margin-bottom: 0.35rem; }
.req { color: var(--p-red-500); }
.mt-2 { margin-top: 0.5rem; }
.mt-3 { margin-top: 1rem; }

.pdf-form { display: flex; flex-direction: column; gap: 0; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.checkbox-group { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.35rem; }
.checkbox-item { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; }

.empty-state { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; padding: 3rem; color: var(--p-text-muted-color); }
.empty-state i { font-size: 2.5rem; }
.empty-state-small { display: flex; align-items: center; gap: 0.5rem; color: var(--p-text-muted-color); font-size: 0.85rem; padding: 1rem 0; }
.empty-state-small i { font-size: 1.5rem; }

@media (max-width: 900px) {
  .detail-grid { grid-template-columns: 1fr; }
  .form-row { grid-template-columns: 1fr; }
}
</style>
