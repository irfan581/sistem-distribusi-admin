<template>
  <div class="msg-row" :class="isMine ? 'msg-right' : 'msg-left'">
    <div v-if="!isMine && isGroup" class="msg-avatar-small">
      {{ msg.sender_name?.[0]?.toUpperCase() || '?' }}
    </div>

    <div class="msg-content">
      <div v-if="!isMine && showSenderName" class="msg-sender-name">
        {{ msg.sender_name }}
      </div>

      <div
        class="bubble-wrap"
        @contextmenu.prevent="!msg.is_deleted ? emit('openCtxMenu', $event, msg) : null"
        @touchstart="!msg.is_deleted ? emit('longPressStart', $event, msg) : null"
        @touchend="emit('longPressEnd')"
        @touchmove="emit('longPressEnd')"
      >
        <!-- Inline edit mode -->
        <div v-if="isEditing" class="bubble-edit-wrap">
          <textarea
            ref="editInputRef"
            :value="editingText"
            class="bubble-edit-input"
            rows="1"
            @input="emit('update:editingText', $event.target.value)"
            @keydown.enter.exact.prevent="emit('submitEdit')"
            @keydown.escape="emit('cancelEdit')"
            @blur="emit('submitEdit')"
          />
          <div class="bubble-edit-hint">Enter untuk simpan · Esc untuk batal</div>
        </div>

        <div v-else class="msg-bubble" :class="isMine ? 'bubble-mine' : 'bubble-other'">
          <!-- Forwarded label -->
          <div v-if="msg.is_forwarded" class="msg-forwarded-label">
            <i class="pi pi-share-alt" /> Diteruskan
          </div>

          <!-- Quoted message -->
          <div
            v-if="msg.reply_to"
            class="quote-block"
            :class="isMine ? 'quote-mine' : 'quote-other'"
          >
            <span class="quote-sender">{{ msg.reply_to.sender_name }}</span>
            <span class="quote-text">
              <template v-if="msg.reply_to.message_type === 'image'">📷 Foto</template>
              <template v-else-if="msg.reply_to.message_type === 'file'">📎 {{ msg.reply_to.file_name || 'File' }}</template>
              <template v-else-if="msg.reply_to.is_deleted">Pesan dihapus</template>
              <template v-else>{{ msg.reply_to.message }}</template>
            </span>
          </div>

          <span v-if="msg.is_deleted" class="msg-deleted">
            <i class="pi pi-ban" /> Pesan dihapus
          </span>
          <template v-else-if="msg.message_type === 'image'">
            <!-- Gelembungnya sempit, jadi yang dimuat versi kecil. Yang
                 dibuka saat diklik tetap berkas penuh — bukti bongkar sering
                 perlu dizoom sampai label barangnya terbaca. -->
            <div class="msg-image-wrap" @click="emit('openImage', msg.file_url)">
              <img :src="msg.thumb_url || msg.file_url" class="msg-image" loading="lazy" />
              <div class="msg-image-overlay">
                <i class="pi pi-search-plus" />
              </div>
            </div>
          </template>
          <template v-else-if="msg.message_type === 'file'">
            <a :href="msg.file_url" target="_blank" class="msg-file">
              <i class="pi pi-file" /> {{ msg.file_name || 'Unduh file' }}
            </a>
          </template>
          <template v-else-if="msg.message_type === 'audio'">
            <div class="msg-audio">
              <i class="pi pi-microphone msg-audio-icon" />
              <audio
                :src="msg.file_url"
                class="msg-audio-player"
                controls
                preload="metadata"
              />
            </div>
          </template>
          <template v-else>
            <template v-if="mentionSegments.length > 1">
              <span
                v-for="(seg, i) in mentionSegments"
                :key="i"
                :class="seg.isMention ? 'msg-mention' : ''"
              >{{ seg.text }}</span>
            </template>
            <template v-else>{{ msg.message }}</template>
          </template>
        </div>

        <!-- Link preview card — tampil di bawah teks bila ada URL -->
        <ChatLinkPreview
          v-if="previewUrl"
          :url="previewUrl"
          :isMine="isMine"
        />
      </div>

      <!-- Reaction bar — shown when there are reactions -->
      <div
        v-if="hasReactions"
        class="msg-reactions"
        :class="{ 'msg-reactions-right': isMine }"
      >
        <button
          v-for="r in msg.reactions"
          :key="r.emoji"
          class="reaction-chip"
          :class="{ 'reaction-chip--mine': r.reacted_by_me }"
          @click="emit('react', msg, r.emoji)"
          :title="`${r.emoji} · ${r.count}`"
        >
          <span class="reaction-chip__emoji">{{ r.emoji }}</span>
          <span class="reaction-chip__count">{{ r.count }}</span>
        </button>
        <!-- Add more reactions button -->
        <button
          v-if="!msg.is_deleted"
          class="reaction-add-btn"
          @click.stop="openPicker"
          title="Tambah reaksi"
        >
          <i class="pi pi-plus" style="font-size:0.6rem;margin-right:1px" />
          <i class="pi pi-face-smile" />
        </button>
      </div>

      <!-- Quick react btn (appears on hover when no reactions yet) -->
      <button
        v-if="!msg.is_deleted && !hasReactions"
        class="reaction-quick-btn"
        :class="{ 'reaction-quick-btn-right': isMine }"
        @click.stop="openPicker"
        title="Tambah reaksi"
      >
        <i class="pi pi-face-smile" />
      </button>

      <div class="msg-time" :class="{ 'msg-time-right': isMine }">
        <span v-if="msg.is_starred" class="msg-star-icon" title="Pesan berbintang">⭐</span>
        <span v-if="msg.is_edited" class="msg-edited">✏️ diedit</span>
        {{ msg.time }}
        <span v-if="isMine && !msg.is_deleted" class="msg-status">
          <span
            v-if="getMessageStatus(msg) === 'sent'"
            class="tick tick-sent"
            title="Terkirim"
          >✓</span>
          <span
            v-else-if="getMessageStatus(msg) === 'delivered'"
            class="tick tick-delivered"
            title="Tersampaikan"
          >✓✓</span>
          <span
            v-else
            class="tick tick-read"
            title="Dibaca"
          >✓✓</span>
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import ChatLinkPreview from './ChatLinkPreview.vue'
import { extractFirstUrl } from '@/composables/chat/useLinkPreview'
import { parseMentionSegments } from '@/composables/chat/useChatMention'

const props = defineProps({
  msg:            { type: Object,  required: true },
  isMine:         { type: Boolean, required: true },
  showSenderName: { type: Boolean, default: false },
  isEditing:      { type: Boolean, default: false },
  editingText:    { type: String,  default: '' },
  isGroup:        { type: Boolean, default: false },  // show avatar only in group
})

const emit = defineEmits([
  'openCtxMenu', 'longPressStart', 'longPressEnd',
  'openImage', 'submitEdit', 'cancelEdit', 'update:editingText',
  'react', 'openReactionPicker',
])

const editInputRef = ref(null)

const hasReactions = computed(() => props.msg.reactions?.length > 0)

// Mention segments — highlight @nama
const mentionSegments = computed(() =>
  parseMentionSegments(props.msg.message, props.msg.mentions)
)

// Link preview — extract URL dari teks pesan (hanya message_type=text, tidak deleted)
const previewUrl = computed(() => {
  if (props.msg.is_deleted || props.msg.message_type !== 'text') return null
  return extractFirstUrl(props.msg.message)
})

function openPicker(e) {
  const btn     = e.currentTarget
  const rect    = btn.getBoundingClientRect()
  const pickerW = 296   // 6 emojis × ~44px + 24px padding
  const pickerH = 58    // pill height with padding
  const gap     = 8

  // Default: open above the button, centred horizontally
  let x = rect.left + rect.width / 2 - pickerW / 2
  let y = rect.top - pickerH - gap

  // Keep within viewport horizontally
  if (x + pickerW > window.innerWidth - 8) x = window.innerWidth - pickerW - 8
  if (x < 8) x = 8

  // If above is too close to top edge, open below instead
  if (y < 8) y = rect.bottom + gap

  const myReactions = (props.msg.reactions || [])
    .filter(r => r.reacted_by_me).map(r => r.emoji)

  emit('openReactionPicker', { x, y, msg: props.msg, myReactions })
}

function getMessageStatus(msg) {
  if (msg.read_at || msg.is_read) return 'read'
  if (msg.delivered_at) return 'delivered'
  return 'sent'
}

watch(() => props.isEditing, (val) => {
  if (val) nextTick(() => { const el = editInputRef.value; if (el) { el.focus(); el.select() } })
})
</script>
