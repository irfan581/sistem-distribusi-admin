import { ref, computed } from 'vue'

/**
 * useChatMention — deteksi '@' di input, filter anggota grup, inject nama.
 *
 * Usage:
 *   const { mentionQuery, mentionList, showMention, onInputMention, pickMention, resetMention } = useChatMention(members)
 *
 *   members: ref([ { id, nama, type } ])
 */
export function useChatMention(members) {
  // Teks setelah '@' yang sedang diketik
  const mentionQuery  = ref('')
  const showMention   = ref(false)

  // Filtered list — max 6
  const mentionList = computed(() => {
    if (!showMention.value) return []
    const q = mentionQuery.value.toLowerCase()
    return (members.value ?? [])
      .filter(m => m.nama?.toLowerCase().includes(q))
      .slice(0, 6)
  })

  /**
   * Panggil setiap kali teks berubah.
   * Mengembalikan { query } atau null (tidak ada @-trigger aktif).
   */
  function onInputMention(text, cursorPos) {
    // Ambil teks sebelum kursor
    const before = text.slice(0, cursorPos)

    // Cari '@' terakhir yang belum ada spasi setelahnya
    const match  = before.match(/@([\w\s]*)$/)
    if (match) {
      mentionQuery.value = match[1]
      showMention.value  = true
    } else {
      closeMention()
    }
  }

  function closeMention() {
    showMention.value  = false
    mentionQuery.value = ''
  }

  /**
   * User pilih member dari dropdown.
   * Mengembalikan { newText, mentions } setelah inject.
   */
  function pickMention(text, cursorPos, member, existingMentions = []) {
    const before = text.slice(0, cursorPos)
    const after  = text.slice(cursorPos)

    // Replace '@query' dengan '@Nama '
    const replaced = before.replace(/@[\w\s]*$/, `@${member.nama} `)
    const newText  = replaced + after

    // Build mentions array (deduplicate)
    const exists = existingMentions.some(m => m.id === member.id && m.type === member.type)
    const mentions = exists
      ? existingMentions
      : [...existingMentions, { id: member.id, nama: member.nama, type: member.type }]

    closeMention()
    return { newText, newCursor: replaced.length, mentions }
  }

  return {
    mentionQuery,
    mentionList,
    showMention,
    onInputMention,
    pickMention,
    closeMention,
  }
}

/**
 * renderMentions — highlight @nama di teks pesan.
 * Mengembalikan array of { text, isMention, member? }
 */
export function parseMentionSegments(message, mentions = []) {
  if (!mentions?.length || !message) return [{ text: message, isMention: false }]

  // Build regex dari semua nama yang di-mention
  const namaList = mentions.map(m => escapeRegex(m.nama))
  const re       = new RegExp(`@(${namaList.join('|')})`, 'g')

  const segments = []
  let   last     = 0
  let   match

  while ((match = re.exec(message)) !== null) {
    if (match.index > last) {
      segments.push({ text: message.slice(last, match.index), isMention: false })
    }
    const member = mentions.find(m => m.nama === match[1])
    segments.push({ text: match[0], isMention: true, member })
    last = re.lastIndex
  }

  if (last < message.length) {
    segments.push({ text: message.slice(last), isMention: false })
  }

  return segments
}

function escapeRegex(str) {
  return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}
