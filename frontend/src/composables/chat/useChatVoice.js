import { ref, computed } from 'vue'

/**
 * useChatVoice — rekam audio via MediaRecorder, return blob siap kirim.
 *
 * Usage:
 *   const { isRecording, duration, startRecording, stopRecording, cancelRecording, audioBlob } = useChatVoice()
 */
export function useChatVoice() {
  const isRecording  = ref(false)
  const duration     = ref(0)          // detik
  const audioBlob    = ref(null)       // Blob hasil rekam
  const hasPermission = ref(null)      // null=unknown, true, false

  let mediaRecorder  = null
  let chunks         = []
  let timerInterval  = null
  let stream         = null

  // Format detik → "0:05" / "1:23"
  const durationLabel = computed(() => {
    const m = Math.floor(duration.value / 60)
    const s = duration.value % 60
    return `${m}:${s.toString().padStart(2, '0')}`
  })

  async function startRecording() {
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      hasPermission.value = true
    } catch {
      hasPermission.value = false
      throw new Error('Izin mikrofon ditolak')
    }

    chunks         = []
    audioBlob.value = null
    duration.value  = 0

    // Prefer webm, fallback ke ogg/mp4
    const mimeType = getSupportedMime()
    mediaRecorder  = new MediaRecorder(stream, mimeType ? { mimeType } : {})

    mediaRecorder.ondataavailable = (e) => {
      if (e.data?.size > 0) chunks.push(e.data)
    }

    mediaRecorder.onstop = () => {
      const mime = mediaRecorder.mimeType || 'audio/webm'
      audioBlob.value = new Blob(chunks, { type: mime })
      // stop all tracks
      stream?.getTracks().forEach(t => t.stop())
      stream = null
    }

    mediaRecorder.start(100)   // collect every 100ms
    isRecording.value = true

    timerInterval = setInterval(() => {
      duration.value++
      // Auto-stop at 2 menit
      if (duration.value >= 120) stopRecording()
    }, 1000)
  }

  function stopRecording() {
    if (!isRecording.value) return
    clearInterval(timerInterval)
    isRecording.value = false
    mediaRecorder?.stop()
  }

  function cancelRecording() {
    if (!isRecording.value) return
    clearInterval(timerInterval)
    isRecording.value = false
    audioBlob.value   = null
    duration.value    = 0
    // stop immediately without saving chunks
    mediaRecorder?.stream?.getTracks().forEach(t => t.stop())
    if (mediaRecorder?.state !== 'inactive') {
      mediaRecorder.ondataavailable = null
      mediaRecorder.onstop = null
      mediaRecorder.stop()
    }
    stream?.getTracks().forEach(t => t.stop())
    stream = null
  }

  function reset() {
    audioBlob.value = null
    duration.value  = 0
  }

  return {
    isRecording,
    duration,
    durationLabel,
    audioBlob,
    hasPermission,
    startRecording,
    stopRecording,
    cancelRecording,
    reset,
  }
}

function getSupportedMime() {
  const types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4']
  return types.find(t => MediaRecorder.isTypeSupported(t)) || ''
}
