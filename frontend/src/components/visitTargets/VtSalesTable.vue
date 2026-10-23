<template>
  <div class="vt-table-panel">
    <!-- Toolbar -->
    <div class="vt-toolbar">
      <div class="vt-toolbar-left">
        <div class="vt-search-wrap">
          <i class="pi pi-search vt-search-icon" />
          <input
            :value="search"
            @input="emit('update:search', $event.target.value)"
            placeholder="Cari nama, NIK, atau area..."
            class="vt-search-input"
          />
        </div>
        <div class="vt-filter-group">
          <button @click="emit('update:filterActive', null)" class="vt-filter-btn" :class="{ active: filterActive === null }">Semua</button>
          <button @click="emit('update:filterActive', true)" class="vt-filter-btn" :class="{ active: filterActive === true }">Aktif</button>
          <button @click="emit('update:filterActive', false)" class="vt-filter-btn" :class="{ active: filterActive === false }">Nonaktif</button>
        </div>
      </div>
      <div class="vt-toolbar-right">
        <Button
          v-if="selectedRows.length > 0"
          @click="emit('openBulk')"
          size="small"
          class="vt-bulk-btn"
        >
          <i class="pi pi-sliders-h" />
          <span>Atur {{ selectedRows.length }} Sales</span>
        </Button>
      </div>
    </div>

    <!-- Table -->
    <div class="vt-table-wrap">
      <div v-if="loading" class="vt-loading">
        <i class="pi pi-spin pi-spinner" />
        <span>Memuat data...</span>
      </div>

      <table v-else class="vt-table">
        <thead>
          <tr>
            <th class="col-check">
              <input type="checkbox" :checked="allSelected" @change="emit('toggleSelectAll')" class="vt-checkbox" />
            </th>
            <th class="col-no">No.</th>
            <th class="col-sales">Sales</th>
            <th class="col-area">Area</th>
            <th class="col-target">Target / Hari</th>
            <th class="col-source">Sumber</th>
            <th class="col-updated">Diperbarui</th>
            <th class="col-action">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(s, index) in filteredList"
            :key="s.id"
            class="vt-row"
            :class="{ 'vt-row-selected': isSelected(s.id), 'vt-row-inactive': !s.is_active }"
          >
            <td class="col-check">
              <input type="checkbox" :checked="isSelected(s.id)" @change="emit('toggleSelect', s)" class="vt-checkbox" />
            </td>
            <td class="col-no">{{ index + 1 }}</td>
            <td class="col-sales">
              <div class="sales-cell">
                <SalesAvatar :src="s.foto_profil_url" :nama="s.nama" :size="32" />
                <div class="sales-info">
                  <span class="sales-name">{{ s.nama }}</span>
                  <span class="sales-nik">{{ s.nik }}</span>
                </div>
                <span v-if="s.is_spv" class="spv-badge">SPV</span>
              </div>
            </td>
            <td class="col-area"><span class="area-text">{{ s.area || '-' }}</span></td>
            <td class="col-target">
              <div class="target-cell">
                <span class="target-value">{{ s.target_per_day }}</span>
                <span class="target-unit">toko</span>
                <div class="target-bar-wrap">
                  <div class="target-bar" :style="{ width: Math.min((s.target_per_day / 20) * 100, 100) + '%' }" />
                </div>
              </div>
            </td>
            <td class="col-source">
              <span class="source-badge" :class="s.target_source === 'dedicated' ? 'src-dedicated' : 'src-legacy'">
                {{ s.target_source === 'dedicated' ? 'Khusus' : 'Default' }}
              </span>
            </td>
            <td class="col-updated">
              <div v-if="s.target_updated_wib" class="updated-cell">
                <span class="updated-by">{{ s.target_set_by }}</span>
                <span class="updated-at">{{ s.target_updated_wib }}</span>
              </div>
              <span v-else class="updated-empty">-</span>
            </td>
            <td class="col-action">
              <div class="action-group">
                <button v-if="can('manage_target_kunjungan')" @click="emit('edit', s)" class="action-btn action-edit" title="Edit target"><i class="pi pi-pencil" /></button>
                <button @click="emit('history', s)" class="action-btn action-history" title="Riwayat perubahan"><i class="pi pi-history" /></button>
                <button v-if="can('manage_target_kunjungan')" @click="emit('reset', s)" class="action-btn action-reset" title="Reset ke default"><i class="pi pi-undo" /></button>
              </div>
            </td>
          </tr>

          <tr v-if="filteredList.length === 0">
            <td colspan="8" class="vt-empty">
              <i class="pi pi-inbox" />
              <span>Tidak ada data ditemukan</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { usePermission } from '@/composables/usePermission'
const { can } = usePermission()

defineProps({
  filteredList:  { type: Array,   required: true },
  selectedRows:  { type: Array,   required: true },
  loading:       { type: Boolean, default: false },
  search:        { type: String,  default: '' },
  filterActive:  { default: null },
  allSelected:   { type: Boolean, default: false },
  isSelected:    { type: Function, required: true },
  initial:       { type: Function, required: true },
})

const emit = defineEmits([
  'update:search', 'update:filterActive',
  'toggleSelect', 'toggleSelectAll',
  'edit', 'history', 'reset', 'openBulk',
])
</script>
