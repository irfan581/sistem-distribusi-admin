<template>
  <div class="employees-view">
    <PageHeader title="Data Sales" subtitle="Kelola dan pantau seluruh data sales aktif.">
      <template #actions>
        <span class="total-label">Total: <strong>{{ totalRecords }}</strong> sales</span>
        <Button icon="pi pi-refresh" text plain @click="loadData" aria-label="Refresh" />
        <Button v-if="can('create_sales')" icon="pi pi-plus" label="Tambah Sales" @click="openCreate" />
      </template>
    </PageHeader>

    <!-- Filter Bar -->
    <Card class="filter-card">
      <template #content>
        <div class="filter-bar">
          <IconField class="search-field">
            <InputIcon class="pi pi-search" />
            <InputText v-model="searchQuery" placeholder="Cari nama, NIK, area..." @input="onSearch" />
          </IconField>
          <Select
            v-model="filterActive"
            :options="activeOptions"
            option-label="label"
            option-value="value"
            placeholder="Semua Status"
            showClear
            @change="resetAndLoad"
            style="width: 160px"
          />
        </div>
      </template>
    </Card>

    <!-- Table -->
    <Card>
      <template #content>
        <DataTable
          :value="items"
          :loading="loading"
          stripedRows
          lazy
          :totalRecords="totalRecords"
          :rows="perPage"
          :first="(currentPage - 1) * perPage"
          paginator
          :rowsPerPageOptions="[10, 25, 50]"
          @page="onPage"
          currentPageReportTemplate="{first} - {last} dari {totalRecords}"
          paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
        >
          <Column header="No." style="width: 56px; text-align: center">
            <template #body="{ index }">{{ (currentPage - 1) * perPage + index + 1 }}</template>
          </Column>
          <Column header="Sales" style="min-width: 220px">
            <template #body="{ data }">
              <div class="sales-cell">
                <SalesAvatar :src="data.foto_profil_url" :nama="data.nama" :size="36" />
                <div>
                  <div class="sales-nama">{{ data.nama }}</div>
                  <div class="sales-sub">NIK: {{ data.nik }}</div>
                </div>
              </div>
            </template>
          </Column>
          <Column field="area" header="Area" style="min-width: 120px">
            <template #body="{ data }">{{ data.area || '-' }}</template>
          </Column>
          <Column field="no_hp" header="No. HP" style="min-width: 130px">
            <template #body="{ data }">
              <a v-if="data.no_hp" :href="`tel:${data.no_hp}`" class="phone-link">{{ data.no_hp }}</a>
              <span v-else>-</span>
            </template>
          </Column>
          <Column header="Absen Bulan Ini" style="min-width: 140px; text-align: center">
            <template #body="{ data }">
              <Tag :value="`${data.total_hadir_bulan_ini ?? 0} hari`" severity="success" />
            </template>
          </Column>
          <Column header="Status" style="min-width: 100px">
            <template #body="{ data }">
              <Tag
                :value="data.is_active ? 'Aktif' : 'Nonaktif'"
                :severity="data.is_active ? 'success' : 'secondary'"
              />
            </template>
          </Column>
          <Column header="Aksi" style="min-width: 170px; text-align: center">
            <template #body="{ data }">
              <Button icon="pi pi-eye" size="small" text rounded severity="info"
                @click="$router.push(`/employees/${data.id}`)" v-tooltip.top="'Detail'" />
              <Button v-if="can('edit_sales')" icon="pi pi-pencil" size="small" text rounded severity="secondary"
                @click="openEdit(data)" v-tooltip.top="'Edit'" />
              <Button v-if="can('edit_sales')" icon="pi pi-key" size="small" text rounded severity="warn"
                @click="openResetPassword(data)" v-tooltip.top="'Reset Password'" />
              <Button v-if="can('delete_sales')" icon="pi pi-trash" size="small" text rounded severity="danger"
                @click="confirmDelete(data)" v-tooltip.top="'Hapus'" />
            </template>
          </Column>

          <template #empty>
            <div class="empty-state">
              <i class="pi pi-users" />
              <span>Tidak ada data sales ditemukan</span>
            </div>
          </template>
        </DataTable>
      </template>
    </Card>

    <!-- Create / Edit Dialog -->
    <EmployeeDialog
      v-model:visible="dialogVisible"
      :employee="selectedEmployee"
      @saved="loadData"
    />

    <!-- Reset Password Dialog -->
    <Dialog
      v-model:visible="resetDialogVisible"
      header="Reset Password Sales"
      :style="{ width: '400px' }"
      modal
      :draggable="false"
    >
      <div v-if="selectedEmployee" class="reset-info">
        <i class="pi pi-user" />
        <span>{{ selectedEmployee.nama }}</span>
      </div>
      <div class="field" style="margin-top: 1rem">
        <label class="field-label">Password Baru <span style="color:var(--p-red-500)">*</span></label>
        <Password v-model="resetForm.password" :feedback="false" toggleMask fluid />
      </div>
      <div class="field" style="margin-top: 0.75rem">
        <label class="field-label">Konfirmasi Password <span style="color:var(--p-red-500)">*</span></label>
        <Password v-model="resetForm.password_confirmation" :feedback="false" toggleMask fluid />
      </div>

      <template #footer>
        <Button label="Batal" text plain @click="resetDialogVisible = false" />
        <Button label="Reset Password" icon="pi pi-shield" severity="warn" :loading="resetting" @click="doResetPassword" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { usePullRefresh } from '@/composables/usePullRefresh'
import { useConfirm } from 'primevue/useconfirm'
import { employeeService } from '@/services/employee.service'
import { useToast } from '@/composables/useToast'
import { usePermission } from '@/composables/usePermission'
import { debounce } from '@/utils/helpers'
import PageHeader from '@/components/common/PageHeader.vue'
import EmployeeDialog from '@/components/employees/EmployeeDialog.vue'

const { can } = usePermission()

const toast   = useToast()
const confirm = useConfirm()

const items = ref([])
const loading = ref(false)
const totalRecords = ref(0)
const currentPage = ref(1)
const perPage = ref(10)
const searchQuery = ref('')
const filterActive = ref(null)

const dialogVisible = ref(false)
const selectedEmployee = ref(null)

const resetDialogVisible = ref(false)
const resetting = ref(false)
const resetForm = reactive({ password: '', password_confirmation: '' })

const activeOptions = [
  { label: 'Aktif', value: 1 },
  { label: 'Nonaktif', value: 0 },
]

async function loadData() {
  loading.value = true
  try {
    const res = await employeeService.getAll({
      page: currentPage.value,
      per_page: perPage.value,
      ...(searchQuery.value && { search: searchQuery.value }),
      ...(filterActive.value !== null && filterActive.value !== undefined && { is_active: filterActive.value }),
    })
    const paginated = res.data
    items.value = paginated.data || paginated
    totalRecords.value = paginated.total || items.value.length
  } catch (err) {
    toast.apiError(err)
  } finally {
    loading.value = false
  }
}

function onPage(event) {
  currentPage.value = event.page + 1
  perPage.value = event.rows
  loadData()
}

function resetAndLoad() {
  currentPage.value = 1
  loadData()
}

const onSearch = debounce(resetAndLoad, 400)

function openCreate() {
  selectedEmployee.value = null
  dialogVisible.value = true
}

function openEdit(employee) {
  selectedEmployee.value = employee
  dialogVisible.value = true
}

function openResetPassword(employee) {
  selectedEmployee.value = employee
  resetForm.password = ''
  resetForm.password_confirmation = ''
  resetDialogVisible.value = true
}

async function doResetPassword() {
  if (!resetForm.password) { toast.error('Password wajib diisi.'); return }
  if (resetForm.password.length < 6) { toast.error('Password minimal 6 karakter.'); return }
  if (resetForm.password !== resetForm.password_confirmation) { toast.error('Password tidak cocok.'); return }
  resetting.value = true
  try {
    await employeeService.resetPassword(selectedEmployee.value.id, resetForm)
    toast.success('Password sales berhasil direset.')
    resetDialogVisible.value = false
  } catch (err) {
    toast.apiError(err)
  } finally {
    resetting.value = false
  }
}

function confirmDelete(employee) {
  confirm.require({
    message: `Hapus akun sales "${employee.nama}"? Semua data presensi dan kunjungan akan ikut terhapus.`,
    header: 'Konfirmasi Hapus Sales',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Ya, Hapus',
    rejectLabel: 'Batal',
    acceptClass: 'p-button-danger',
    accept: () => doDelete(employee.id),
  })
}

async function doDelete(id) {
  try {
    await employeeService.destroy(id)
    toast.success('Akun sales berhasil dihapus.')
    loadData()
  } catch (err) {
    toast.apiError(err)
  }
}

onMounted(loadData)
usePullRefresh(loadData)
</script>

<style scoped>
.employees-view { display: flex; flex-direction: column; gap: 1.25rem; animation: fadeUp var(--dur-base) var(--ease) both; }

.filter-card :deep(.p-card-body) { padding: 0.75rem 1rem; }

.filter-bar {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.search-field { flex: 1; min-width: 220px; }

.total-label { font-size: 0.875rem; color: var(--p-text-muted-color); }

.sales-cell { display: flex; align-items: center; gap: 0.75rem; }

.sales-avatar {
  background: var(--p-primary-100) !important;
  color: var(--p-primary-700) !important;
  flex-shrink: 0;
}

.sales-nama { font-size: 0.875rem; font-weight: 600; color: var(--p-text-color); }
.sales-sub { font-size: 0.75rem; color: var(--p-text-muted-color); }
.phone-link { color: var(--p-primary-500); text-decoration: none; font-size: 0.85rem; }
.phone-link:hover { text-decoration: underline; }

.reset-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem;
  background: var(--s-2);
  border-radius: 8px;
  font-weight: 600;
}

.field-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--p-text-color);
  display: block;
  margin-bottom: 0.35rem;
}

.empty-state {
  display: flex; flex-direction: column; align-items: center;
  gap: 0.5rem; padding: 3rem; color: var(--p-text-muted-color);
}
.empty-state i { font-size: 2.5rem; }
</style>
