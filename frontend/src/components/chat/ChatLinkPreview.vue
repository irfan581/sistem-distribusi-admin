<template>
  <a
    v-if="preview"
    :href="preview.url"
    target="_blank"
    rel="noopener noreferrer"
    class="link-preview"
    :class="isMine ? 'link-preview--mine' : 'link-preview--other'"
    @click.stop
  >
    <!-- Thumbnail -->
    <div v-if="preview.image && !imgError" class="lp-image-wrap">
      <img
        :src="preview.image"
        class="lp-image"
        @error="imgError = true"
        loading="lazy"
      />
    </div>

    <!-- Text content -->
    <div class="lp-body">
      <span class="lp-site">
        <img
          v-if="!favError"
          :src="preview.favicon"
          class="lp-favicon"
          @error="favError = true"
        />
        {{ preview.site_name || domain }}
      </span>
      <span class="lp-title">{{ preview.title || preview.url }}</span>
      <span v-if="preview.description" class="lp-desc">{{ preview.description }}</span>
    </div>
  </a>

  <!-- Loading skeleton -->
  <div v-else-if="loading" class="link-preview link-preview--loading">
    <div class="lp-body">
      <Skeleton height="10px" width="80px" />
      <Skeleton height="12px" width="100%" style="margin-top:4px" />
      <Skeleton height="9px"  width="75%" style="margin-top:4px" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { fetchLinkPreview } from '@/composables/chat/useLinkPreview'

const props = defineProps({
  url:    { type: String,  required: true },
  isMine: { type: Boolean, default: false },
})

const preview  = ref(null)
const loading  = ref(false)
const imgError = ref(false)
const favError = ref(false)

const domain = computed(() => {
  try { return new URL(props.url).hostname.replace('www.', '') }
  catch { return props.url }
})

async function load() {
  if (!props.url) return
  loading.value = true
  imgError.value = false
  favError.value = false
  preview.value = await fetchLinkPreview(props.url)
  loading.value = false
}

onMounted(load)
watch(() => props.url, load)
</script>
