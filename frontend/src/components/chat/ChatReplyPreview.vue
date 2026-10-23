<template>
  <div class="reply-preview-bar">
    <div class="reply-preview-inner">
      <div class="reply-preview-accent" />
      <div class="reply-preview-meta">
        <span class="reply-preview-sender">{{ replyingTo.sender_name }}</span>
        <span class="reply-preview-text">
          <template v-if="replyingTo.message_type === 'image'">📷 Foto</template>
          <template v-else-if="replyingTo.message_type === 'file'">📎 {{ replyingTo.file_name || 'File' }}</template>
          <template v-else>{{ replyingTo.message }}</template>
        </span>
      </div>
      <button class="preview-cancel" @click="emit('clear')" title="Batalkan balasan">
        <i class="pi pi-times" />
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  replyingTo: { type: Object, required: true },
})
const emit = defineEmits(['clear'])
</script>
