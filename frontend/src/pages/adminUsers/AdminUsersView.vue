<template>
  <div class="admin-users-view">
    <PageHeader title="Pengguna Admin" subtitle="Kelola akun yang dapat mengakses panel admin.">
      <template #actions>
        <Button v-if="can('manage_users')" icon="pi pi-plus" label="Tambah Admin" @click="userDialog.openCreate()" />
      </template>
    </PageHeader>

    <!-- Filter -->
    <Card class="filter-card">
      <template #content>
        <div class="filter-bar">
          <IconField class="search-field">
            <InputIcon class="pi pi-search" />
            <InputText v-model="search" placeholder="Cari nama / email..." @input="onSearch" />
          </IconField>
          <Button icon="pi pi-refresh" text plain @click="loadData" :loading="loading" />
        </div>
      </template>
    </Card>

    <!-- Table -->
    <Card>
      <template #content>
        <DataTable :value="items" :loading="loading" stripedRows>

          <!-- Avatar + Nama -->
          <Column header="No." style="width: 56px; text-align: center">
            <template #body="{ index }">{{ index + 1 }}</template>
          </Column>
          <Column header="Pengguna" style="min-width: 220px">
            <template #body="{ data }">
              <div class="user-cell">
                <Avatar
                  :image="data.avatar_url || undefined"
                  :label="data.avatar_url ? undefined : data.name?.[0]?.toUpperCase()"
                  shape="circle"
                  size="normal"
                  class="user-avatar"
                />
                <div>
                  <div class="user-name">{{ data.name }}</div>
                  <div class="user-email">{{ data.email }}</div>
                </div>
              </div>
            </template>
          </Column>

          <!-- Role -->
          <Column header="Role" style="min-width: 130px">
            <template #body="{ data }">
              <Tag
                :value="data.role_label || data.role_nama || '-'"
                :severity="roleSeverity(data.role_nama)"
              />
              <div v-if="data.role_parent" class="role-parent">{{ data.role_parent }}</div>
            </template>
          </Column>

          <!-- Akses Panel -->
          <Column header="Panel" style="min-width: 80px; text-align: center">
            <template #body="{ data }">
              <i :class="['pi', data.is_admin ? 'pi-check-circle' : 'pi-times-circle', data.is_admin ? 'icon-yes' : 'icon-no']" />
            </template>
          </Column>

          <!-- Hak Akses -->
          <Column header="Hak Akses" style="min-width: 180px">
            <template #body="{ data }">
              <span v-if="!data.permissions?.length" class="no-perm">tidak ada</span>
              <div v-else class="perm-chips">
                <Tag
                  v-for="p in data.permissions.slice(0, 3)"
                  :key="p"
                  :value="permLabel(p)"
                  severity="secondary"
                  class="perm-chip"
                />
                <Tag
                  v-if="data.permissions.length > 3"
                  :value="`+${data.permissions.length - 3}`"
                  severity="secondary"
                  class="perm-chip"
                />
              </div>
            </template>
          </Column>

          <!-- Dibuat -->
          <Column header="Dibuat" style="min-width: 110px">
            <template #body="{ data }">
              <span class="date-text">{{ formatDate(data.created_at) }}</span>
            </template>
          </Column>

          <!-- Aksi -->
          <Column header="Aksi" style="min-width: 140px; text-align: center">
            <template #body="{ data }">
              <div class="action-buttons">
                <Button
                  v-if="can('manage_users')"
                  icon="pi pi-pencil"
                  size="small"
                  text
                  v-tooltip="'Edit'"
                  @click="userDialog.openEdit(data)"
                />
                <Button
                  v-if="can('manage_users')"
                  icon="pi pi-key"
                  size="small"
                  text
                  severity="warning"
                  v-tooltip="'Reset Password'"
                  @click="resetPwdDialog.open(data.id)"
                />
                <Button
                  v-if="can('manage_users') && data.role_nama !== 'owner'"
                  icon="pi pi-trash"
                  size="small"
                  text
                  severity="danger"
                  v-tooltip="'Hapus'"
                  @click="confirmDelete(data)"
                />
              </div>
            </template>
          </Column>

          <template #empty>
            <div class="empty-state">
              <i class="pi pi-users" />
              <span>Belum ada pengguna admin</span>
            </div>
          </template>
        </DataTable>
      </template>
    </Card>

    <AdminUserDialog ref="userDialog" @saved="loadData" />
    <AdminUserResetPasswordDialog ref="resetPwdDialog" @saved="loadData" />
  </div>
</template>

<script setup>
import { ref, onMounted, defineAsyncComponent } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import { adminUserService } from '@/services/adminUser.service'
import { useToast } from '@/composables/useToast'
import { usePermission } from '@/composables/usePermission'
import { ALL_PERMISSIONS } from '@/utils/permissions'
import { debounce } from '@/utils/helpers'
import PageHeader from '@/components/common/PageHeader.vue'

const { can } = usePermission()

const AdminUserDialog = defineAsyncComponent(() =>
  import('@/components/adminUsers/AdminUserDialog.vue')
)
const AdminUserResetPasswordDialog = defineAsyncComponent(() =>
  import('@/components/adminUsers/AdminUserResetPasswordDialog.vue')
)

const confirm = useConfirm()
const toast = useToast()
const loading = ref(false)
const items = ref([])
const search = ref('')
const userDialog = ref(null)
const resetPwdDialog = ref(null)

function roleSeverity(nama) {
  return { owner: 'danger', boss: 'danger', admin: 'warn', hrd: 'success', spv: 'info', keuangan: 'contrast' }[nama] ?? 'secondary'
}

function permLabel(key) {
  return ALL_PERMISSIONS.find((p) => p.key === key)?.label ?? key
}

function formatDate(dt) {
  if (!dt) return '-'
  return new Date(dt).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

async function loadData() {
  loading.value = true
  try {
    const { data } = await adminUserService.list({ search: search.value || undefined })
    items.value = data.data ?? data
  } catch (err) {
    toast.apiError(err)
  } finally {
    loading.value = false
  }
}

const onSearch = debounce(loadData, 350)

function confirmDelete(user) {
  confirm.require({
    message: `Hapus akun "${user.name}"? Tindakan ini tidak dapat dibatalkan.`,
    header: 'Hapus Akun Admin',
    icon: 'pi pi-exclamation-triangle',
    acceptSeverity: 'danger',
    acceptLabel: 'Ya, Hapus',
    rejectLabel: 'Batal',
    accept: async () => {
      try {
        await adminUserService.destroy(user.id)
        toast.success('Akun admin berhasil dihapus')
        loadData()
      } catch (err) {
        toast.error(err.response?.data?.message || 'Gagal menghapus akun')
      }
    },
  })
}

onMounted(loadData)
</script>

<style scoped>
.admin-users-view { display: flex; flex-direction: column; gap: 1.25rem; }

.filter-card :deep(.p-card-body) { padding: 0.75rem 1rem; }
.filter-bar { display: flex; align-items: center; gap: 0.75rem; }
.search-field { flex: 1; min-width: 220px; }

.user-cell { display: flex; align-items: center; gap: 0.75rem; }
.user-avatar {
  background: var(--p-primary-100) !important;
  color: var(--p-primary-700) !important;
  flex-shrink: 0;
}
.user-name { font-size: 0.875rem; font-weight: 600; color: var(--p-text-color); }
.user-email { font-size: 0.75rem; color: var(--p-text-muted-color); }

.role-parent { font-size: 0.72rem; color: var(--p-text-muted-color); margin-top: 2px; }

.icon-yes { color: var(--c-success); font-size: 1.1rem; }
.icon-no  { color: var(--t-secondary); font-size: 1.1rem; }

.no-perm { color: var(--p-text-muted-color); font-style: italic; font-size: 0.8rem; }

.perm-chips { display: flex; flex-wrap: wrap; gap: 0.25rem; }
.perm-chip :deep(.p-tag) { font-size: 0.68rem; padding: 0.1rem 0.45rem; }

.date-text { font-size: 0.8rem; color: var(--p-text-muted-color); }

.action-buttons { display: flex; gap: 0.25rem; justify-content: center; }

.empty-state {
  display: flex; flex-direction: column; align-items: center;
  gap: 0.5rem; padding: 3rem; color: var(--p-text-muted-color);
}
.empty-state i { font-size: 2.5rem; }
</style>
