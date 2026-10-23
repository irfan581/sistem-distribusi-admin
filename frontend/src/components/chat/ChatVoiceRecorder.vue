<template>
  <!-- Trigger button — shown when NOT recording and no preview -->
  <button
    v-if="!isRecording && !audioBlob"
    class="input-icon-btn voice-trigger-btn"
    @mousedown.prevent="startRec"
    @touchstart.prevent="startRec"
    v-tooltip.top="'Rekam pesan suara'"
    title="Rekam suara"
  >
    <i class="pi pi-microphone" />
  </button>

  <!-- Recording bar — shown while recording -->
  <Teleport to="body">
    <Transition name="voice-bar">
      <div v-if="isRecording" class="voice-recording-bar">
        <button class="voice-cancel-btn" @click="cancelRec" title="Batal">
          <i class="pi pi-times" />
        </button>

        <!-- Animated waveform -->
        <div class="voice-wave">
          <span v-for="i in 5" :key="i" class="voice-wave-bar" :style="{ animationDelay: `${(i-1)*0.12}s` }" />
        </div>

        <span class="voice-duration">{{ durationLabel }}</span>
        <span class="voice-rec-dot" />

        <button class="voice-stop-btn" @click="stopRec" title="Selesai rekam">
          <i class="pi pi-check" />
        </button>
      </div>
    </Transition>
  </Teleport>

  <!-- Preview bar — after stop, before send -->
  <Teleport to="body">
    <Transition name="voice-bar">
      <div v-if="!isRecording && audioBlob" class="voice-preview-bar">
        <button class="voice-cancel-btn" @click="discardRec" title="Hapus">
          <i class="pi pi-trash" />
        </button>

        <audio ref="audioRef" :src="blobUrl" class="voice-preview-audio" controls preload="metadata" />

        <button class="voice-send-btn" @click="sendRec" :disabled="sending" title="Kirim">
          <i v-if="sending" class="pi pi-spin pi-spinner" />
          <i v-else class="pi pi-send" />
        </button>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { useChatVoice } from '@/composables/chat/useChatVoice'

const props = defineProps({
  sending: { type: Boolean, default: false },
})

const emit = defineEmits(['send'])   // emit(audioBlob)

const { isRecording, durationLabel, audioBlob, startRecording, stopRecording, cancelRecording, reset } = useChatVoice()

const audioRef = ref(null)

// Blob URL for <audio> preview
const blobUrl = computed(() => audioBlob.value ? URL.createObjectURL(audioBlob.value) : null)

// Clean up blob URL when blob changes
let prevBlobUrl = null
watch(blobUrl, (val, old) => {
  if (old) URL.revokeObjectURL(old)
  prevBlobUrl = val
})
onUnmounted(() => { if (prevBlobUrl) URL.revokeObjectURL(prevBlobUrl) })

async function startRec() {
  try { await startRecording() }
  catch (e) { alert(e.message) }
}

function stopRec()   { stopRecording() }
function cancelRec() { cancelRecording() }

function discardRec() {
  if (audioRef.value) { audioRef.value.pause(); audioRef.value.src = '' }
  reset()
}

function sendRec() {
  if (!audioBlob.value) return
  emit('send', audioBlob.value)
}
</script>
