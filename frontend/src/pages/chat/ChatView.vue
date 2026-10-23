<template>
  <div class="chat-page">
    <!-- Contact list card -->
    <div class="chat-card chat-card-sidebar" :class="{ hidden: showChat, 'sidebar-hidden-mobile': activeContact && isMobile }">
      <ChatSidebar
        :contacts="contacts"
        :contactsLoading="contactsLoading"
        :searchQuery="searchQuery"
        :activeTab="activeTab"
        :tabs="tabs"
        :filteredContacts="filteredContacts"
        :totalUnreadAll="totalUnreadAll"
        :activeContact="activeContact"
        :isMobile="isMobile"
        :isActiveContact="isActiveContact"
        @update:searchQuery="searchQuery = $event"
        @update:activeTab="activeTab = $event"
        @contactSelected="selectContact"
        @openBuatGrup="showBuatGrupDialog = true"
      />
    </div>

    <!-- Chat panel card -->
    <div class="chat-card chat-card-main chat-main" :class="{ hidden: !showChat, 'main-full-mobile': activeContact && isMobile }">


      <div v-if="!activeContact" class="chat-empty">
        <div class="empty-chat-icon">
          <img src="/logo-icon.png" alt="TMK" style="width:52px;height:52px;object-fit:contain;opacity:0.35;" />
        </div>
        <h3>Pilih percakapan</h3>
        <p>Klik kontak di sebelah kiri untuk memulai chat</p>
      </div>

      <template v-else>
        <ChatHeader
          :contact="activeContact"
          :typingUser="typingUser"
          :isMobile="isMobile"
          :goBack="goBack"
          @back="goBack"
          @openGroupInfo="showGroupInfo = true"
          @openSearch="showSearch = true"
          @openStarred="showStarred = true"
        />

        <!-- Pin banner — di bawah header, di atas area pesan -->
        <ChatPinBanner
          :pin="pinnedMessage"
          @jumpToPin="(id) => scrollToMessageById(id)"
          @unpin="(id) => handleTogglePin({ id })"
        />

        <!-- Search panel — posisi absolut di dalam chat-card-main -->
        <ChatSearchPanel
          :visible="showSearch"
          :contactId="activeContact?.id"
          :contactType="activeContact?.type"
          :contactName="activeContact?.nama"
          @close="showSearch = false"
          @jumpTo="jumpToMessage"
        />

        <ChatMessageArea
          ref="chatMessageAreaRef"
          :messages="messages"
          :messagesLoading="messagesLoading"
          :loadingMore="loadingMore"
          :hasMore="hasMore"
          :showScrollBtn="showScrollBtn"
          :highlightedMsgId="highlightedMsgId"
          :activeUserId="authStore.user?.id"
          :editingMsg="editingMsg"
          :editingText="editingText"
          :ctxMenu="ctxMenu"
          :pinnedMsgId="pinnedMessage?.message_id ?? null"
          :deleteDialog="deleteDialog"
          :typingUser="typingUser"
          :isMyMessage="isMyMessage"
          :showSenderName="showSenderName"
          :showDateSeparator="showDateSeparator"
          :formatDateLabel="formatDateLabel"
          :isGroup="activeContact?.type === 'group'"
          @scroll="onScrollArea"
          @scrollToBottom="scrollToBottom(true)"
          @openCtxMenu="openCtxMenu"
          @longPressStart="onBubbleLongPressStart"
          @longPressEnd="onBubbleLongPressEnd"
          @openImage="openImage"
          @startEdit="startEdit"
          @submitEdit="submitEdit"
          @cancelEdit="cancelEdit"
          @update:editingText="editingText = $event"
          @startReply="startReply"
          @confirmDelete="confirmDelete"
          @doDelete="doDelete"
          @closeDeleteDialog="deleteDialog.visible = false"
          @toggleStar="toggleStar"
          @togglePin="handleTogglePin"
          @react="handleReact"
          @openReactionPicker="openReactionPicker"
          @forward="openForward"
        />

        <ChatReplyPreview
          v-if="replyingTo"
          :replyingTo="replyingTo"
          @clear="clearReply"
        />

        <ChatFilePreview
          v-if="filePreview"
          :filePreview="filePreview"
          @clear="clearFilePreview"
        />

        <ChatInputBar
          ref="inputBarRef"
          v-model="newMessage"
          v-model:mentions="currentMentions"
          :contact="activeContact"
          :sending="sending"
          :hasFile="!!filePreview"
          :members="groupMembers"
          @send="sendMessage"
          @typing="onTyping"
          @fileSelected="onFileSelected"
          @voiceSend="onVoiceSend"
        />
      </template>
    </div>
  </div>

  <!-- Buat Grup Dialog -->
  <Dialog
    v-model:visible="showBuatGrupDialog"
    modal
    header="Buat Grup Baru"
    :style="{ width: '380px' }"
    @hide="resetBuatGrup"
  >
    <div class="dlg-grup-body">
      <!-- Nama Grup -->
      <div class="dlg-field">
        <label class="dlg-label">Nama Grup <span class="dlg-required">*</span></label>
        <input
          v-model="buatGrupNama"
          class="dlg-input"
          placeholder="Contoh: Tim Sales Jawa Barat"
          maxlength="100"
          @keydown.enter="submitBuatGrup"
        />
      </div>

      <!-- Pilih Anggota Sales -->
      <div class="dlg-field">
        <label class="dlg-label">Tambah Anggota Sales <span class="dlg-optional">(opsional)</span></label>
        <div class="dlg-member-list">
          <div
            v-for="s in contacts.sales"
            :key="s.id"
            class="dlg-member-item"
            :class="{ 'dlg-member-item--selected': buatGrupMembers.includes(s.id) }"
            @click="toggleGrupMember(s.id)"
          >
            <div class="dlg-member-avatar">{{ s.nama?.[0]?.toUpperCase() }}</div>
            <div class="dlg-member-info">
              <span class="dlg-member-name">{{ s.nama }}</span>
              <span class="dlg-member-area">{{ s.area }}</span>
            </div>
            <i v-if="buatGrupMembers.includes(s.id)" class="pi pi-check dlg-member-check" />
          </div>
          <div v-if="!contacts.sales.length" class="dlg-member-empty">Tidak ada kontak sales</div>
        </div>
        <div v-if="buatGrupMembers.length" class="dlg-member-count">
          {{ buatGrupMembers.length }} anggota dipilih
        </div>
      </div>
    </div>

    <template #footer>
      <button class="dlg-btn dlg-cancel" @click="showBuatGrupDialog = false" :disabled="buatGrupLoading">Batal</button>
      <button class="dlg-btn dlg-confirm" @click="submitBuatGrup" :disabled="!buatGrupNama.trim() || buatGrupLoading">
        <i v-if="buatGrupLoading" class="pi pi-spin pi-spinner" style="margin-right:6px" />
        {{ buatGrupLoading ? 'Membuat...' : 'Buat Grup' }}
      </button>
    </template>
  </Dialog>

  <!-- Starred Messages Panel -->
  <ChatStarredPanel
    :visible="showStarred"
    @close="showStarred = false"
    @jumpTo="jumpToMessage"
    @unstarred="(id) => { const m = messages.value.find(m => m.id === id); if (m) m.is_starred = false }"
  />

  <!-- Forward Dialog -->
  <ChatForwardDialog
    :visible="showForward"
    :msg="forwardMsg"
    :contacts="contacts"
    @close="showForward = false"
    @forward="handleForward"
  />

  <!-- Reaction Picker -->
  <ChatReactionPicker
    :visible="reactionPicker.visible"
    :x="reactionPicker.x"
    :y="reactionPicker.y"
    :myReactions="reactionPicker.myReactions"
    @pick="(emoji) => handleReact(reactionPicker.msg, emoji)"
    @close="closeReactionPicker"
  />

  <!-- Group Info Panel -->
  <ChatGroupInfoPanel
    :visible="showGroupInfo"
    :groupId="activeContact?.type === 'group' ? activeContact.id : null"
    :contacts="contacts"
    @close="showGroupInfo = false"
    @groupRenamed="(nama) => { if (activeContact) activeContact.nama = nama }"
  />

  <!-- Lightbox viewer gambar chat -->
  <Teleport to="body">
    <Transition name="lightbox-fade">
      <div v-if="lightboxUrl" class="chat-lightbox" @click.self="closeLightbox">
        <!-- Toolbar atas -->
        <div class="chat-lightbox__toolbar">
          <span class="chat-lightbox__title">Foto</span>
          <div class="chat-lightbox__actions">
            <button class="chat-lightbox__btn" @click="downloadImage(lightboxUrl)" title="Unduh">
              <i class="pi pi-download" />
              <span>Unduh</span>
            </button>
            <button class="chat-lightbox__btn chat-lightbox__btn--close" @click="closeLightbox" title="Tutup">
              <i class="pi pi-times" />
            </button>
          </div>
        </div>
        <!-- Gambar -->
        <div class="chat-lightbox__body" @click.self="closeLightbox">
          <img :src="lightboxUrl" class="chat-lightbox__img" @click.stop />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import '@/assets/css/chat.css'
import { ref, watch, watchEffect, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { useAuthStore }    from '@/stores/auth.store'
import { chatService }     from '@/services/chat.service'
import { useToast }        from '@/composables/useToast'
import { useChatContacts }     from '@/composables/chat/useChatContacts'
import { useChatMessages }     from '@/composables/chat/useChatMessages'
import { useChatUpload }       from '@/composables/chat/useChatUpload'
import { useChatRealtime }     from '@/composables/chat/useChatRealtime'
import { useChatPolling }      from '@/composables/chat/useChatPolling'
import { useChatNotif }        from '@/composables/chat/useChatNotif'
import { useChatOnlineStatus } from '@/composables/chat/useChatOnlineStatus'
import { useChatPin }          from '@/composables/chat/useChatPin'
import { useChatGlobalStore } from '@/stores/chatGlobal.store'
import ChatSidebar        from '@/components/chat/ChatSidebar.vue'
import ChatHeader         from '@/components/chat/ChatHeader.vue'
import ChatMessageArea    from '@/components/chat/ChatMessageArea.vue'
import ChatReplyPreview   from '@/components/chat/ChatReplyPreview.vue'
import ChatFilePreview    from '@/components/chat/ChatFilePreview.vue'
import ChatInputBar       from '@/components/chat/ChatInputBar.vue'
import ChatForwardDialog     from '@/components/chat/ChatForwardDialog.vue'
import ChatGroupInfoPanel    from '@/components/chat/ChatGroupInfoPanel.vue'
import ChatSearchPanel       from '@/components/chat/ChatSearchPanel.vue'
import ChatStarredPanel      from '@/components/chat/ChatStarredPanel.vue'
import ChatPinBanner         from '@/components/chat/ChatPinBanner.vue'
import ChatReactionPicker    from '@/components/chat/ChatReactionPicker.vue'

const authStore = useAuthStore()
const toast     = useToast()

const activeContact = ref(null)
const showChat = ref(false)

const {
  contacts, contactsLoading, searchQuery, activeTab,
  tabs, filteredContacts, totalUnreadAll,
  isActiveContact, updateContactPreview, loadContacts,
} = useChatContacts()

const {
  messages, messagesLoading, loadingMore, hasMore, oldestId,
  messageAreaRef, showScrollBtn,
  isMyMessage, showSenderName, scrollToBottom, onMessageScroll,
  showDateSeparator, formatDateLabel,
  openImage, closeLightbox, downloadImage, lightboxUrl,
} = useChatMessages()

const {
  filePreview, onFileSelected, clearFilePreview,
} = useChatUpload()

const { requestNotifPermission, pushNotif } = useChatNotif(selectContact)
const chatGlobalStore = useChatGlobalStore()

// Banner in-app ditangani oleh globalNotif di AdminLayout (berlaku di semua halaman)
// sehingga tidak perlu banner duplikat di sini

// Pin pesan
const { pinnedMessage, togglePin, onPinEvent } = useChatPin(activeContact)

// Reaction emoji
const reactionPicker = ref({ visible: false, x: 0, y: 0, msg: null, myReactions: [] })

function onReactionEvent(data) {
  // data: { message_id, reactions }
  const m = messages.value.find(m => m.id === data.message_id)
  if (m) m.reactions = data.reactions
}

function openReactionPicker(payload) {
  reactionPicker.value = { visible: true, ...payload }
}
function closeReactionPicker() {
  reactionPicker.value = { visible: false, x: 0, y: 0, msg: null, myReactions: [] }
}

async function handleReact(msg, emoji) {
  closeReactionPicker()
  // Optimistic update
  const m = messages.value.find(m => m.id === msg.id)
  if (m) {
    const reactions = [...(m.reactions || [])]
    const existing  = reactions.find(r => r.emoji === emoji)
    if (existing) {
      existing.reacted_by_me ? existing.count-- : existing.count++
      existing.reacted_by_me = !existing.reacted_by_me
      if (existing.count <= 0) {
        const idx = reactions.indexOf(existing)
        reactions.splice(idx, 1)
      }
    } else {
      reactions.push({ emoji, count: 1, reacted_by_me: true })
    }
    m.reactions = reactions
  }
  try {
    const res = await chatService.reactMessage(msg.id, emoji)
    // Replace with server truth
    const updated = messages.value.find(m => m.id === msg.id)
    if (updated) updated.reactions = res.data?.reactions ?? updated.reactions
  } catch (err) {
    toast.apiError(err)
  }
}

const {
  typingUser, setupDmChannel, subscribeGroupChannel,
  leaveGroupChannel, onTyping: realtimeOnTyping, teardown,
} = useChatRealtime({ contacts, messages, activeContact, scrollToBottom, pushNotif, isMyMessage, onPinEvent, onReactionEvent })

const { startPolling, stopPolling } = useChatPolling({ activeContact, messages, scrollToBottom, isMyMessage })

// Online/offline status — ping every 30s, poll contacts online status
useChatOnlineStatus(contacts)

// Scroll ke bawah otomatis saat typing indicator muncul
// supaya bubble "••• sedang mengetik" selalu terlihat tanpa scroll manual
watch(typingUser, async (val) => {
  if (val) {
    await nextTick()
    scrollToBottom(true, true)  // force scroll saat typing muncul
  }
})

// Template ref untuk ChatMessageArea — areaEl di-expose via defineExpose
const chatMessageAreaRef = ref(null)
const areaEl = ref(null)
watchEffect(() => {
  // Sync DOM el ke messageAreaRef composable setiap kali berubah
  areaEl.value = chatMessageAreaRef.value?.areaEl ?? null
  messageAreaRef.value = areaEl.value
})

watch(messages, async () => {
  if (!loadingMore.value) {
    await nextTick()
    scrollToBottom(false)
  }
}, { deep: true })

const newMessage         = ref('')
const sending            = ref(false)
const isMobile           = ref(window.innerWidth < 768)
const inputBarRef        = ref(null)
const editingMsg         = ref(null)
const editingText        = ref('')
const ctxMenu            = ref({ visible: false, x: 0, y: 0, msg: null })
const deleteDialog       = ref({ visible: false, msg: null, loading: false })
const replyingTo         = ref(null)
const showBuatGrupDialog = ref(false)
const buatGrupNama       = ref('')
const buatGrupMembers    = ref([])   // array of sales IDs
const buatGrupLoading    = ref(false)
const showGroupInfo      = ref(false)
const showSearch         = ref(false)
const showStarred        = ref(false)
const showForward        = ref(false)
const forwardMsg         = ref(null)

// ── Mention ───────────────────────────────────────────────────────────────
const currentMentions = ref([])   // [{id, nama, type}] — dikumpul dari ChatInputBar
const groupMembers    = ref([])   // anggota grup aktif — di-load saat ganti contact

let longPressTimer = null

// ── Named global event handlers (agar bisa di-removeEventListener) ──────────
function _onGlobalClick() { closeCtxMenu(); closeReactionPicker() }
function _onGlobalKeydown(e) {
  if (e.key === 'Escape') {
    if (lightboxUrl.value)        closeLightbox()
    else if (showGroupInfo.value) showGroupInfo.value = false
    else if (showSearch.value)    showSearch.value    = false
    else closeCtxMenu()
  }
}

async function selectContact(contact) {
  stopPolling()
  leaveGroupChannel()

  activeContact.value   = contact
  showChat.value        = true
  // Beritahu global store kontak mana yang sedang dibuka — agar banner tidak muncul untuk kontak ini
  chatGlobalStore.setActiveContact(contact?.id, contact?.type)
  messages.value        = []
  hasMore.value         = false
  oldestId.value        = null
  messagesLoading.value = true
  typingUser.value      = null
  replyingTo.value      = null
  currentMentions.value = []
  groupMembers.value    = []

  const listMap = { sales: contacts.value.sales, admin: contacts.value.admins, group: contacts.value.groups }
  const list    = listMap[contact.type] || []
  const found   = list.find(c => c.id === contact.id)
  if (found) found.unread = 0

  try {
    let res
    if (contact.type === 'group') {
      res = await chatService.getGroupMessages(contact.id)
      subscribeGroupChannel(contact.id)
      // Load group members untuk @mention dropdown
      chatService.getGroupInfo(contact.id).then(r => {
        groupMembers.value = r.data?.members ?? []
      }).catch(() => {})
    } else {
      res = await chatService.getDmMessages(contact.id, contact.type)
    }
    // Response format baru: { messages, has_more, oldest_id }
    const payload   = res.data || res
    messages.value  = payload.messages ?? payload
    hasMore.value   = payload.has_more  ?? false
    oldestId.value  = payload.oldest_id ?? null
    await nextTick()
    scrollToBottom(false, true)  // force=true: selalu scroll saat buka kontak
    startPolling()
  } finally {
    messagesLoading.value = false
  }
}

function goBack() {
  showChat.value = false
  activeContact.value = null
}

async function loadMoreMessages() {
  if (!hasMore.value || loadingMore.value || !activeContact.value) return
  const el = messageAreaRef.value
  // simpan posisi scroll sebelum prepend supaya tidak loncat ke atas
  const prevScrollHeight = el?.scrollHeight ?? 0
  loadingMore.value = true
  try {
    let res
    if (activeContact.value.type === 'group') {
      res = await chatService.getGroupMessages(activeContact.value.id, oldestId.value)
    } else {
      res = await chatService.getDmMessages(activeContact.value.id, activeContact.value.type, oldestId.value)
    }
    const payload  = res.data || res
    const older    = payload.messages ?? payload
    hasMore.value  = payload.has_more  ?? false
    oldestId.value = payload.oldest_id ?? null
    if (older.length) {
      messages.value = [...older, ...messages.value]
      // Pertahankan posisi scroll — user tidak terlempar ke atas
      await nextTick()
      if (el) el.scrollTop = el.scrollHeight - prevScrollHeight
    }
  } catch { /* silent */ }
  finally { loadingMore.value = false }
}

async function sendMessage() {
  const text    = newMessage.value.trim()
  const hasFile = !!filePreview.value
  if (!text && !hasFile) return
  if (!activeContact.value || sending.value) return

  sending.value = true
  const me  = authStore.user
  const now = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' })

  const replyTarget = replyingTo.value
  const optimistic  = {
    id: Date.now(),
    sender_id:    me?.id,
    sender_type:  'App\\Models\\User',
    sender_name:  me?.name,
    message:      text || (hasFile ? filePreview.value.name : ''),
    message_type: hasFile ? (filePreview.value.isImage ? 'image' : 'file') : 'text',
    file_url:     hasFile && filePreview.value.isImage ? filePreview.value.dataUrl : null,
    file_name:    hasFile ? filePreview.value.name : null,
    reply_to: replyTarget ? {
      sender_name:  replyTarget.sender_name,
      message:      replyTarget.message,
      message_type: replyTarget.message_type,
      file_name:    replyTarget.file_name,
      is_deleted:   replyTarget.is_deleted,
    } : null,
    type:       activeContact.value.type === 'group' ? 'group' : 'dm',
    time:       now,
    created_at: new Date().toISOString(),
    _optimistic: true,
  }
  messages.value.push(optimistic)

  const capturedText     = text
  const capturedFile     = filePreview.value
  const capturedMentions = currentMentions.value.length ? [...currentMentions.value] : null
  newMessage.value      = ''
  filePreview.value     = null
  replyingTo.value      = null
  currentMentions.value = []
  await nextTick()
  scrollToBottom(true, true)  // force=true: selalu scroll saat kirim pesan sendiri

  try {
    let payload
    if (capturedFile) {
      payload = new FormData()
      payload.append('file', capturedFile.file)
      if (capturedText) payload.append('message', capturedText)
      if (replyTarget)  payload.append('reply_to_id', replyTarget.id)
      if (activeContact.value.type === 'group') {
        payload.append('type', 'group')
        payload.append('group_id', activeContact.value.id)
        payload.append('receiver_id', 0)
        payload.append('receiver_type', 'admin')
        if (capturedMentions) payload.append('mentions', JSON.stringify(capturedMentions))
      } else {
        payload.append('type', 'dm')
        payload.append('receiver_id', activeContact.value.id)
        payload.append('receiver_type', activeContact.value.type)
      }
    } else if (activeContact.value.type === 'group') {
      payload = { message: capturedText, type: 'group', group_id: activeContact.value.id, receiver_id: 0, receiver_type: 'admin', reply_to_id: replyTarget?.id ?? null, mentions: capturedMentions }
    } else {
      payload = { message: capturedText, type: 'dm', receiver_id: activeContact.value.id, receiver_type: activeContact.value.type, reply_to_id: replyTarget?.id ?? null }
    }

    const res = await chatService.sendMessage(payload)
    const idx = messages.value.findIndex(m => m.id === optimistic.id)
    if (idx !== -1) messages.value.splice(idx, 1, res.data)
    updateContactPreview(activeContact.value, optimistic.message, now)
  } catch (err) {
    messages.value = messages.value.filter(m => m.id !== optimistic.id)
    toast.apiError(err)
  } finally {
    sending.value = false
  }
}

function onScrollArea() {
  onMessageScroll()
  // Trigger load more saat scroll mendekati atas (< 120px dari top)
  const el = messageAreaRef.value
  if (el && el.scrollTop < 120) {
    loadMoreMessages()
  }
}

function onTyping() { realtimeOnTyping(activeContact.value) }

// ── Voice message send ─────────────────────────────────────────────────────
async function onVoiceSend(audioBlob) {
  if (!audioBlob || !activeContact.value) return
  sending.value = true

  const now = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' })
  const me  = authStore.user

  // Optimistic bubble
  const blobUrl  = URL.createObjectURL(audioBlob)
  const optimistic = {
    id: Date.now(),
    sender_id:    me?.id,
    sender_type:  'App\\Models\\User',
    sender_name:  me?.name,
    message:      '',
    message_type: 'audio',
    file_url:     blobUrl,
    file_name:    'Pesan suara',
    reply_to:     null,
    type:         activeContact.value.type === 'group' ? 'group' : 'dm',
    time:         now,
    created_at:   new Date().toISOString(),
    _optimistic:  true,
  }
  messages.value.push(optimistic)
  await nextTick()
  scrollToBottom(true, true)

  try {
    const payload = new FormData()
    const ext     = audioBlob.type.includes('ogg') ? 'ogg' : 'webm'
    payload.append('audio', audioBlob, `voice.${ext}`)
    if (activeContact.value.type === 'group') {
      payload.append('type', 'group')
      payload.append('group_id', activeContact.value.id)
      payload.append('receiver_id', 0)
      payload.append('receiver_type', 'admin')
    } else {
      payload.append('type', 'dm')
      payload.append('receiver_id', activeContact.value.id)
      payload.append('receiver_type', activeContact.value.type)
    }

    const res = await chatService.sendMessage(payload)
    URL.revokeObjectURL(blobUrl)
    const idx = messages.value.findIndex(m => m.id === optimistic.id)
    if (idx !== -1) messages.value.splice(idx, 1, res.data)
    updateContactPreview(activeContact.value, '🎙️ Pesan suara', now)
  } catch (err) {
    messages.value = messages.value.filter(m => m.id !== optimistic.id)
    URL.revokeObjectURL(blobUrl)
    toast.apiError(err)
  } finally {
    sending.value = false
  }
}

const highlightedMsgId = ref(null)

async function jumpToMessage(msg) {
  showSearch.value = false
  // Cek apakah pesan sudah ada di daftar pesan yang dimuat
  const existing = messages.value.find(m => m.id === msg.id)
  if (existing) {
    scrollToMessageById(msg.id)
    return
  }
  // Belum ada — reload messages dari awal, pesan lama butuh load more
  // Untuk sekarang: scroll ke atas dan tampilkan hint
  toast.info('Pesan ditemukan. Scroll ke atas untuk melihat pesan lama.')
}

function scrollToMessageById(msgId) {
  nextTick(() => {
    const el = messageAreaRef.value
    if (!el) return
    const msgEl = el.querySelector(`[data-msg-id="${msgId}"]`)
    if (msgEl) {
      msgEl.scrollIntoView({ behavior: 'smooth', block: 'center' })
      // Highlight sementara
      highlightedMsgId.value = msgId
      setTimeout(() => { highlightedMsgId.value = null }, 2500)
    }
  })
}

function openCtxMenu(e, msg) {
  closeCtxMenu()
  const MENU_W = 140, MENU_H = 80
  let x = e.clientX, y = e.clientY
  if (x + MENU_W > window.innerWidth)  x = window.innerWidth  - MENU_W - 8
  if (y + MENU_H > window.innerHeight) y = window.innerHeight - MENU_H - 8
  ctxMenu.value = { visible: true, x, y, msg }
}
function closeCtxMenu() { ctxMenu.value = { visible: false, x: 0, y: 0, msg: null } }

function onBubbleLongPressStart(e, msg) {
  longPressTimer = setTimeout(() => {
    const touch = e.touches[0]
    openCtxMenu({ clientX: touch.clientX, clientY: touch.clientY }, msg)
  }, 500)
}
function onBubbleLongPressEnd() { clearTimeout(longPressTimer) }

function startEdit(msg) {
  closeCtxMenu()
  editingMsg.value  = msg
  editingText.value = msg.message
}
function cancelEdit() { editingMsg.value = null; editingText.value = '' }
async function submitEdit() {
  const msg  = editingMsg.value
  const text = editingText.value.trim()
  if (!msg || !text || text === msg.message) { cancelEdit(); return }
  cancelEdit()
  try {
    await chatService.editMessage(msg.id, text)
    const idx = messages.value.findIndex(m => m.id === msg.id)
    if (idx !== -1) messages.value[idx] = { ...messages.value[idx], message: text, is_edited: true }
  } catch (err) { toast.apiError(err) }
}

function confirmDelete(msg) { closeCtxMenu(); deleteDialog.value = { visible: true, msg, loading: false } }
async function doDelete() {
  const msg = deleteDialog.value.msg
  if (!msg) return
  deleteDialog.value.loading = true
  try {
    await chatService.deleteMessage(msg.id)
    const idx = messages.value.findIndex(m => m.id === msg.id)
    if (idx !== -1) messages.value[idx] = { ...messages.value[idx], is_deleted: true, message: '' }
    deleteDialog.value.visible = false
  } catch (err) { toast.apiError(err) }
  finally { deleteDialog.value.loading = false }
}

function startReply(msg) { closeCtxMenu(); replyingTo.value = msg }
function clearReply()     { replyingTo.value = null }

// ── Buat Grup ─────────────────────────────────────────────────────────────
function resetBuatGrup() {
  buatGrupNama.value    = ''
  buatGrupMembers.value = []
  buatGrupLoading.value = false
}

function toggleGrupMember(salesId) {
  const idx = buatGrupMembers.value.indexOf(salesId)
  if (idx === -1) buatGrupMembers.value.push(salesId)
  else buatGrupMembers.value.splice(idx, 1)
}

async function submitBuatGrup() {
  const nama = buatGrupNama.value.trim()
  if (!nama || buatGrupLoading.value) return
  buatGrupLoading.value = true
  try {
    const res = await chatService.createGroup(nama, buatGrupMembers.value)
    toast.success(`Grup "${res.data?.nama || nama}" berhasil dibuat`)
    showBuatGrupDialog.value = false
    resetBuatGrup()
    // Refresh group list
    await loadContacts()
  } catch (err) {
    toast.apiError(err)
  } finally {
    buatGrupLoading.value = false
  }
}

function openForward(msg) {
  closeCtxMenu()
  forwardMsg.value  = msg
  showForward.value = true
}

async function handleForward({ msg, targets }) {
  showForward.value = false
  try {
    await chatService.forwardMessage(msg.id, targets)
    toast.success(`Pesan diteruskan ke ${targets.length} tujuan`)
  } catch (err) {
    toast.apiError(err)
  }
}

async function handleTogglePin(msg) {
  closeCtxMenu()
  await togglePin(msg)
}

async function toggleStar(msg) {
  closeCtxMenu()
  const wasStarred = msg.is_starred
  // Optimistic update
  const idx = messages.value.findIndex(m => m.id === msg.id)
  if (idx !== -1) messages.value[idx] = { ...messages.value[idx], is_starred: !wasStarred }
  try {
    if (wasStarred) {
      await chatService.unstarMessage(msg.id)
      toast.info('Bintang dihapus')
    } else {
      await chatService.starMessage(msg.id)
      toast.success('Pesan dibintangi ⭐')
    }
  } catch (err) {
    // Revert on error
    if (idx !== -1) messages.value[idx] = { ...messages.value[idx], is_starred: wasStarred }
    toast.apiError(err)
  }
}

const resizeHandler = () => { isMobile.value = window.innerWidth < 768 }

onMounted(async () => {
  chatGlobalStore.resetChatUnread()
  chatGlobalStore.onChatViewEnter()   // store lepas channel, useChatRealtime yang pegang
  await loadContacts()
  setupDmChannel()
  requestNotifPermission()
  // Auto-select kontak jika datang dari klik banner notif
  if (history.state?.openContact) {
    try {
      const contact = JSON.parse(history.state.openContact)
      if (contact?.id && contact?.type) selectContact(contact)
    } catch {}
  }
  window.addEventListener('resize', resizeHandler)
  window.addEventListener('click', _onGlobalClick)
  window.addEventListener('keydown', _onGlobalKeydown)
})

onBeforeUnmount(() => {
  stopPolling()
  teardown()
  clearTimeout(longPressTimer)
  window.removeEventListener('resize', resizeHandler)
  window.removeEventListener('click', _onGlobalClick)
  window.removeEventListener('keydown', _onGlobalKeydown)
  chatGlobalStore.onChatViewLeave()   // store ambil channel kembali
})
</script>
