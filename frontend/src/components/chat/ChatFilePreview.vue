<template>
  <div class="file-preview-bar">
    <div class="file-preview-inner">
      <img v-if="filePreview.isImage" :src="filePreview.dataUrl" class="preview-img" />
      <div v-else class="preview-file-icon">
        <i class="pi pi-file" />
      </div>
      <div class="preview-meta">
        <span class="preview-name">{{ filePreview.name }}</span>
        <span class="preview-size">{{ filePreview.sizeLabel }}</span>
      </div>
      <button class="preview-cancel" @click="emit('clear')" title="Batalkan">
        <i class="pi pi-times" />
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  filePreview: { type: Object, required: true },
})
const emit = defineEmits(['clear'])
</script>
