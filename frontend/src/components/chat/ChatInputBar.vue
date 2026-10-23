<template>
  <div class="chat-input" style="position:relative">
    <input
      ref="fileInputRef"
      type="file"
      accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx"
      style="display:none"
      @change="emit('fileSelected', $event)"
    />
    <input
      ref="imageInputRef"
      type="file"
      accept="image/jpeg,image/png"
      capture="environment"
      style="display:none"
      @change="emit('fileSelected', $event)"
    />

    <button class="input-icon-btn" @click="fileInputRef.click()" v-tooltip.top="'Lampirkan file'">
      <i class="pi pi-paperclip" />
    </button>
    <button class="input-icon-btn" @click="imageInputRef.click()" v-tooltip.top="'Kirim gambar'">
      <i class="pi pi-image" />
    </button>

    <!-- Voice recorder trigger button -->
    <ChatVoiceRecorder :sending="sending" @send="emit('voiceSend', $event)" />

    <!-- Single pill: textarea + send button inside one bordered box -->
    <div class="chat-input-inner">
      <div class="input-textarea-wrap">
        <textarea
          ref="textareaRef"
          class="chat-textarea"
          :value="modelValue"
          placeholder="Tulis pesan..."
          rows="1"
          @input="onInput"
          @keydown="onKeydown"
        />
        <span v-if="modelValue.length > 200" class="char-count" :class="{ 'char-warn': modelValue.length > 500 }">
          {{ modelValue.length }}/500
        </span>
      </div>

      <button
        class="send-btn"
        @click="emit('send')"
        :disabled="(!modelValue.trim() && !hasFile) || sending"
        v-tooltip.top="'Kirim (Enter)'"
      >
        <i v-if="sending" class="pi pi-spin pi-spinner" />
        <i v-else class="pi pi-send" />
      </button>
    </div>

    <!-- Mention dropdown -->
    <ChatMentionDropdown
      :visible="showMention"
      :list="mentionList"
      :activeIdx="mentionActiveIdx"
      :bottom="mentionBottom"
      :left="mentionLeft"
      :width="mentionWidth"
      @pick="onPickMention"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import ChatVoiceRecorder     from './ChatVoiceRecorder.vue'
import ChatMentionDropdown   from './ChatMentionDropdown.vue'
import { useChatMention }    from '@/composables/chat/useChatMention'

const props = defineProps({
  modelValue: { type: String,  required: true },
  contact:    { type: Object,  required: true },
  sending:    { type: Boolean, default: false },
  hasFile:    { type: Boolean, default: false },
  members:    { type: Array,   default: () => [] },  // group members untuk @mention
})

const emit = defineEmits(['update:modelValue', 'send', 'typing', 'fileSelected', 'voiceSend', 'update:mentions'])

const fileInputRef  = ref(null)
const imageInputRef = ref(null)
const textareaRef   = ref(null)

// ── Mention state ─────────────────────────────────────────────────────────
const membersRef = computed(() => props.members)
const { mentionList, showMention, onInputMention, pickMention, closeMention } = useChatMention(membersRef)

// Collected mentions for this message
const currentMentions = ref([])
const mentionActiveIdx = ref(0)

// Position for floating dropdown — calculated from textarea bounding box
const mentionBottom = ref(80)
const mentionLeft   = ref(0)
const mentionWidth  = ref(260)

watch(showMention, (val) => {
  if (val) {
    nextTick(updateDropdownPos)
    mentionActiveIdx.value = 0
  }
})

function updateDropdownPos() {
  const el = textareaRef.value
  if (!el) return
  const rect    = el.getBoundingClientRect()
  mentionBottom.value = window.innerHeight - rect.top + 6
  mentionLeft.value   = rect.left
  mentionWidth.value  = Math.min(rect.width, 300)
}

// ── Input handler ─────────────────────────────────────────────────────────
function onInput(e) {
  // auto-resize
  e.target.style.height = 'auto'
  e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px'

  const val    = e.target.value
  const cursor = e.target.selectionStart

  emit('update:modelValue', val)
  emit('typing')

  // Only trigger mention in group chat
  if (props.contact?.type === 'group') {
    onInputMention(val, cursor)
  }
}

// ── Keyboard navigation ───────────────────────────────────────────────────
function onKeydown(e) {
  if (showMention.value && mentionList.value.length) {
    if (e.key === 'ArrowDown') {
      e.preventDefault()
      mentionActiveIdx.value = (mentionActiveIdx.value + 1) % mentionList.value.length
      return
    }
    if (e.key === 'ArrowUp') {
      e.preventDefault()
      mentionActiveIdx.value = (mentionActiveIdx.value - 1 + mentionList.value.length) % mentionList.value.length
      return
    }
    if (e.key === 'Enter' || e.key === 'Tab') {
      e.preventDefault()
      onPickMention(mentionList.value[mentionActiveIdx.value])
      return
    }
    if (e.key === 'Escape') {
      closeMention()
      return
    }
  }

  // Normal Enter — send message
  if (e.key === 'Enter' && !e.shiftKey && !showMention.value) {
    e.preventDefault()
    emit('send')
  }
}

function onPickMention(member) {
  const el     = textareaRef.value
  const cursor = el?.selectionStart ?? props.modelValue.length
  const { newText, newCursor, mentions } = pickMention(props.modelValue, cursor, member, currentMentions.value)

  currentMentions.value = mentions
  emit('update:modelValue', newText)
  emit('update:mentions', mentions)

  // Restore cursor after next tick
  nextTick(() => {
    if (el) {
      el.focus()
      el.setSelectionRange(newCursor, newCursor)
    }
  })
}

// Reset mentions when contact changes or message cleared
watch(() => props.contact, () => {
  currentMentions.value = []
  emit('update:mentions', [])
})
watch(() => props.modelValue, (val) => {
  if (!val) {
    currentMentions.value = []
    emit('update:mentions', [])
  }
})

defineExpose({ fileInputRef, imageInputRef, textareaRef })
</script>
