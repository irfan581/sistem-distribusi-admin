<template>
  <Teleport to="body">
    <Transition name="starred-slide">
      <div v-if="visible" class="sp-overlay" @click.self="emit('close')">
        <div class="sp-panel">

          <div class="sp-header">
            <button class="sp-close" @click="emit('close')"><i class="pi pi-times" /></button>
            <h3 class="sp-title"><i class="pi pi-star-fill sp-title-icon" /> Pesan Berbintang</h3>
          </div>

          <div v-if="loading" class="sp-loading">
            <i class="pi pi-spin pi-spinner" />
            <span>Memuat…</span>
          </div>

          <div v-else-if="items.length === 0" class="sp-empty">
            <i class="pi pi-star" />
            <span>Belum ada pesan berbintang</span>
            <p>Klik kanan pesan → Bintangi untuk menyimpan pesan penting</p>
          </div>

          <div v-else class="sp-list">
            <div
              v-for="msg in items"
              :key="msg.id"
              class="sp-item"
              @click="emit('jumpTo', msg)"
            >
              <!-- Context label -->
              <div class="sp-item-context">
                <i :class="msg.type === 'group' ? 'pi pi-users' : 'pi pi-user'" />
                {{ msg.context_name }}
                <span class="sp-item-date">· {{ msg.date }}</span>
              </div>

              <div class="sp-item-body">
                <div class="sp-item-avatar">{{ msg.sender_initial }}</div>
                <div class="sp-item-content">
                  <span class="sp-item-sender">{{ msg.sender_name }}</span>
                  <template v-if="msg.message_type === 'image'">
                    <span class="sp-item-text sp-file">📷 Foto</span>
                  </template>
                  <template v-else-if="msg.message_type === 'file'">
                    <span class="sp-item-text sp-file">📎 {{ msg.file_name || 'File' }}</span>
                  </template>
                  <template v-else>
                    <span class="sp-item-text">{{ msg.message }}</span>
                  </template>
                </div>
                <button
                  class="sp-unstar-btn"
                  :disabled="unstarringId === msg.id"
                  @click.stop="unstar(msg)"
                  title="Hapus bintang"
                >
                  <i v-if="unstarringId === msg.id" class="pi pi-spin pi-spinner" />
                  <i v-else class="pi pi-star-fill" />
                </button>
              </div>
            </div>
          </div>

        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue'
import { chatService } from '@/services/chat.service'
import { useToast }    from '@/composables/useToast'

const props = defineProps({
  visible: { type: Boolean, required: true },
})

const emit = defineEmits(['close', 'jumpTo', 'unstarred'])

const toast       = useToast()
const loading     = ref(false)
const items       = ref([])
const unstarringId = ref(null)

watch(() => props.visible, (val) => {
  if (val) loadStarred()
})

async function loadStarred() {
  loading.value = true
  try {
    const res  = await chatService.getStarredMessages()
    items.value = res.data || []
  } catch (e) {
    toast.apiError(e)
  } finally {
    loading.value = false
  }
}

async function unstar(msg) {
  unstarringId.value = msg.id
  try {
    await chatService.unstarMessage(msg.id)
    items.value = items.value.filter(m => m.id !== msg.id)
    emit('unstarred', msg.id)
    toast.success('Bintang dihapus')
  } catch (e) { toast.apiError(e) }
  finally { unstarringId.value = null }
}
</script>
