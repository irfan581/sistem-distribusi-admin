<template>
  <div class="message-area" ref="areaEl" @scroll="emit('scroll')">

    <!-- Load more indicator — muncul di atas saat scroll ke atas -->
    <Transition name="load-more-fade">
      <div v-if="loadingMore" class="load-more-indicator">
        <i class="pi pi-spin pi-spinner" />
        <span>Memuat pesan lama…</span>
      </div>
    </Transition>

    <!-- Loading skeletons -->
    <div v-if="messagesLoading" class="messages-loading">
      <div v-for="i in 6" :key="i" class="msg-skeleton" :class="i % 2 === 0 ? 'sk-right' : 'sk-left'">
        <Skeleton v-if="i % 2 !== 0" shape="circle" size="30px" />
        <Skeleton
          :width="['160px','210px','140px','190px','150px','200px'][i-1]"
          height="38px"
          style="border-radius:14px"
        />
        <Skeleton v-if="i % 2 === 0" shape="circle" size="30px" />
      </div>
    </div>

    <div v-else-if="messages.length === 0" class="no-messages">
      <i class="pi pi-chat" />
      <span>Belum ada pesan. Mulai percakapan!</span>
    </div>

    <div v-else class="message-list">
      <template v-for="(msg, idx) in messages" :key="msg.id">
        <div v-if="showDateSeparator(idx)" class="date-separator">
          <span>{{ formatDateLabel(msg.created_at) }}</span>
        </div>

        <div
          :data-msg-id="msg.id"
          :class="{ 'msg-highlighted': highlightedMsgId === msg.id }"
        >
          <ChatMessageBubble
            :msg="msg"
            :isMine="isMyMessage(msg)"
            :showSenderName="showSenderName(idx)"
            :isEditing="editingMsg?.id === msg.id"
            :editingText="editingText"
            :isGroup="isGroup"
            @update:editingText="emit('update:editingText', $event)"
            @openCtxMenu="(e, m) => emit('openCtxMenu', e, m)"
            @longPressStart="(e, m) => emit('longPressStart', e, m)"
            @longPressEnd="emit('longPressEnd')"
            @openImage="(url) => emit('openImage', url)"
            @submitEdit="emit('submitEdit')"
            @cancelEdit="emit('cancelEdit')"
            @react="(msg, emoji) => emit('react', msg, emoji)"
            @openReactionPicker="(payload) => emit('openReactionPicker', payload)"
          />
        </div>
      </template>
    </div>

    <ChatContextMenu
      :ctxMenu="ctxMenu"
      :activeUserId="activeUserId"
      :pinnedMsgId="pinnedMsgId"
      @reply="(msg) => emit('startReply', msg)"
      @edit="(msg) => emit('startEdit', msg)"
      @delete="(msg) => emit('confirmDelete', msg)"
      @toggleStar="(msg) => emit('toggleStar', msg)"
      @togglePin="(msg) => emit('togglePin', msg)"
      @forward="(msg) => emit('forward', msg)"
    />

    <Dialog
      v-model:visible="deleteDialog.visible"
      modal
      header="Hapus Pesan"
      :style="{ width: '320px' }"
    >
      <p style="margin:0;font-size:0.875rem;color:var(--tmk-text-muted)">
        Pesan ini akan dihapus permanen dan tidak bisa dikembalikan.
      </p>
      <template #footer>
        <button class="dlg-btn dlg-cancel" @click="emit('closeDeleteDialog')">Batal</button>
        <button class="dlg-btn dlg-confirm" :disabled="deleteDialog.loading" @click="emit('doDelete')">
          <i v-if="deleteDialog.loading" class="pi pi-spin pi-spinner" />
          Hapus
        </button>
      </template>
    </Dialog>

    <!-- Typing indicator -->
    <div v-if="typingUser && !messagesLoading" class="typing-indicator">
      <div class="typing-avatar">{{ typingUser[0]?.toUpperCase() }}</div>
      <div class="typing-bubble">
        <span/><span/><span/>
      </div>
    </div>

    <Transition name="scroll-btn">
      <button
        v-if="showScrollBtn"
        class="scroll-to-bottom"
        @click="emit('scrollToBottom')"
        title="Ke pesan terbaru"
      >
        <i class="pi pi-angle-double-down" />
      </button>
    </Transition>
  </div>
</template>

<script setup>
import ChatMessageBubble from './ChatMessageBubble.vue'
import ChatContextMenu   from './ChatContextMenu.vue'

import { ref } from 'vue'

const areaEl = ref(null)
defineExpose({ areaEl })

const props = defineProps({
  messages:        { type: Array,   required: true },
  messagesLoading: { type: Boolean, default: false },
  loadingMore:      { type: Boolean, default: false },
  hasMore:          { type: Boolean, default: false },
  showScrollBtn:    { type: Boolean, default: false },
  highlightedMsgId: { type: [Number, String], default: null },
  activeUserId:    { type: [Number, String], default: null },
  editingMsg:      { type: Object,  default: null },
  editingText:     { type: String,  default: '' },
  ctxMenu:         { type: Object,  required: true },
  pinnedMsgId:     { type: [Number, String], default: null },
  deleteDialog:    { type: Object,  required: true },
  typingUser:      { type: String,  default: null },
  isMyMessage:     { type: Function, required: true },
  showSenderName:  { type: Function, required: true },
  showDateSeparator: { type: Function, required: true },
  formatDateLabel:   { type: Function, required: true },
  isGroup:           { type: Boolean, default: false },
})

const emit = defineEmits([
  'scroll', 'scrollToBottom',
  'openCtxMenu', 'longPressStart', 'longPressEnd',
  'openImage',
  'startEdit', 'submitEdit', 'cancelEdit', 'update:editingText',
  'startReply', 'confirmDelete', 'doDelete', 'closeDeleteDialog',
  'toggleStar',
  'togglePin',
  'react',
  'openReactionPicker',
  'forward',
])
</script>
