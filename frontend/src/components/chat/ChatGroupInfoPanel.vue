<template>
  <Teleport to="body">
    <Transition name="group-info-slide">
      <div v-if="visible" class="gi-overlay" @click.self="emit('close')">
        <div class="gi-panel">

          <!-- Header -->
          <div class="gi-header">
            <button class="gi-close" @click="emit('close')"><i class="pi pi-times" /></button>
            <h3 class="gi-title">Info Grup</h3>
          </div>

          <!-- Loading -->
          <div v-if="loading" class="gi-loading">
            <i class="pi pi-spin pi-spinner" />
            <span>Memuat info grup…</span>
          </div>

          <template v-else-if="info">
            <!-- Group avatar + name -->
            <div class="gi-group-avatar">
              <div class="gi-avatar-circle">{{ info.nama?.[0]?.toUpperCase() }}</div>
              <!-- Rename form — hanya admin -->
              <div v-if="!renaming" class="gi-group-name-row">
                <span class="gi-group-name">{{ info.nama }}</span>
                <button v-if="info.am_admin" class="gi-icon-btn" @click="startRename" title="Ganti nama">
                  <i class="pi pi-pencil" />
                </button>
              </div>
              <div v-else class="gi-rename-row">
                <input
                  ref="renameInputRef"
                  v-model="renameValue"
                  class="gi-rename-input"
                  maxlength="100"
                  @keydown.enter="submitRename"
                  @keydown.escape="cancelRename"
                />
                <button class="gi-icon-btn gi-icon-btn--ok" :disabled="renameSaving" @click="submitRename">
                  <i class="pi pi-check" />
                </button>
                <button class="gi-icon-btn gi-icon-btn--cancel" @click="cancelRename">
                  <i class="pi pi-times" />
                </button>
              </div>
              <span class="gi-group-meta">{{ info.members?.length }} anggota · dibuat {{ info.created_at }}</span>
            </div>

            <div class="gi-divider" />

            <!-- Member list -->
            <div class="gi-section-title">
              <i class="pi pi-users" />
              Anggota ({{ info.members?.length }})
            </div>

            <div class="gi-member-list">
              <div v-for="m in info.members" :key="`${m.type}-${m.id}`" class="gi-member-row">
                <div class="gi-member-avatar" :class="m.type === 'admin' ? 'gi-member-avatar--admin' : ''">
                  {{ m.initial }}
                </div>
                <div class="gi-member-info">
                  <span class="gi-member-name">{{ m.nama }} <span v-if="m.is_me" class="gi-me-badge">Saya</span></span>
                  <span class="gi-member-role">{{ m.type === 'admin' ? 'Admin' : 'Sales' }}{{ m.is_admin ? ' · Admin Grup' : '' }}</span>
                </div>
                <!-- Remove button — hanya admin, tidak bisa hapus diri sendiri kalau satu-satunya admin -->
                <button
                  v-if="info.am_admin && !m.is_me"
                  class="gi-remove-btn"
                  :disabled="removing === m.id"
                  @click="removeMember(m)"
                  title="Keluarkan dari grup"
                >
                  <i v-if="removing === m.id" class="pi pi-spin pi-spinner" />
                  <i v-else class="pi pi-user-minus" />
                </button>
              </div>
            </div>

            <!-- Add member — hanya admin -->
            <template v-if="info.am_admin">
              <div class="gi-divider" />
              <div class="gi-section-title"><i class="pi pi-user-plus" /> Tambah Anggota</div>

              <div class="gi-add-member">
                <div class="gi-search-wrap">
                  <i class="pi pi-search gi-search-icon" />
                  <input
                    v-model="addSearch"
                    class="gi-search-input"
                    placeholder="Cari sales…"
                    @input="filterSales"
                  />
                </div>

                <div v-if="filteredSales.length" class="gi-sales-list">
                  <div
                    v-for="s in filteredSales"
                    :key="s.id"
                    class="gi-sales-row"
                    :class="{ 'gi-sales-row--adding': addingId === s.id }"
                    @click="addMember(s)"
                  >
                    <div class="gi-member-avatar">{{ s.initial }}</div>
                    <div class="gi-member-info">
                      <span class="gi-member-name">{{ s.nama }}</span>
                      <span class="gi-member-role">{{ s.area }}</span>
                    </div>
                    <i v-if="addingId === s.id" class="pi pi-spin pi-spinner" style="color:var(--c-brand-500)" />
                    <i v-else class="pi pi-plus" style="color:var(--c-brand-500)" />
                  </div>
                </div>
                <div v-else-if="addSearch.length > 0" class="gi-no-result">Tidak ada sales yang cocok</div>
              </div>
            </template>

          </template>

        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch, nextTick, computed } from 'vue'
import { chatService } from '@/services/chat.service'
import { useToast }    from '@/composables/useToast'

const props = defineProps({
  visible:  { type: Boolean, required: true },
  groupId:  { type: Number,  default: null },
  contacts: { type: Object,  default: () => ({ sales: [], admins: [], groups: [] }) },
})

const emit = defineEmits(['close', 'groupRenamed'])

const toast   = useToast()
const loading = ref(false)
const info    = ref(null)

// Rename
const renaming      = ref(false)
const renameValue   = ref('')
const renameSaving  = ref(false)
const renameInputRef = ref(null)

// Add member
const addSearch     = ref('')
const addingId      = ref(null)
const removing      = ref(null)

const existingIds = computed(() => {
  if (!info.value) return new Set()
  return new Set(info.value.members.filter(m => m.type === 'sales').map(m => m.id))
})

const filteredSales = computed(() => {
  if (!addSearch.value.trim()) return []
  const q = addSearch.value.toLowerCase()
  return (props.contacts.sales || [])
    .filter(s => !existingIds.value.has(s.id) && s.nama?.toLowerCase().includes(q))
    .slice(0, 8)
})

async function loadInfo() {
  if (!props.groupId) return
  loading.value = true
  info.value    = null
  try {
    const res  = await chatService.getGroupInfo(props.groupId)
    info.value = res.data
  } catch (e) {
    toast.apiError(e)
    emit('close')
  } finally {
    loading.value = false
  }
}

watch(() => props.visible, (val) => {
  if (val) {
    addSearch.value = ''
    renaming.value  = false
    loadInfo()
  }
})

function startRename() {
  renameValue.value = info.value.nama
  renaming.value    = true
  nextTick(() => renameInputRef.value?.focus())
}

function cancelRename() { renaming.value = false }

async function submitRename() {
  const nama = renameValue.value.trim()
  if (!nama || nama === info.value.nama) { cancelRename(); return }
  renameSaving.value = true
  try {
    await chatService.renameGroup(props.groupId, nama)
    info.value.nama = nama
    emit('groupRenamed', nama)
    renaming.value  = false
    toast.success('Nama grup berhasil diubah')
  } catch (e) { toast.apiError(e) }
  finally { renameSaving.value = false }
}

async function removeMember(member) {
  removing.value = member.id
  try {
    await chatService.removeGroupMember(props.groupId, member.id)
    info.value.members = info.value.members.filter(m => !(m.id === member.id && m.type === member.type))
    toast.success(`${member.nama} dikeluarkan dari grup`)
  } catch (e) { toast.apiError(e) }
  finally { removing.value = null }
}

async function addMember(sales) {
  addingId.value = sales.id
  try {
    await chatService.addGroupMember(props.groupId, sales.id)
    info.value.members.push({
      id: sales.id, type: 'sales', nama: sales.nama,
      initial: sales.initial, is_admin: false, is_me: false,
    })
    addSearch.value = ''
    toast.success(`${sales.nama} ditambahkan ke grup`)
  } catch (e) { toast.apiError(e) }
  finally { addingId.value = null }
}
</script>
