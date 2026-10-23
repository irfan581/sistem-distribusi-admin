<template>
  <div class="chat-header">
    <button v-if="isMobile" class="back-btn btn-back" @click="props.goBack ? props.goBack() : emit('back')">
      <i class="pi pi-arrow-left" />
    </button>
    <div class="contact-avatar header-avatar" :class="`avatar-${contact.type}`">
      {{ contact.type === 'group'
        ? (contact.nama?.[0]?.toUpperCase() || 'G')
        : contact.initial }}
    </div>
    <div
      class="header-info"
      :class="{ 'header-info--clickable': contact.type === 'group' }"
      @click="contact.type === 'group' ? emit('openGroupInfo') : null"
    >
      <span class="header-name">{{ contact.nama }}</span>
      <span class="header-sub">
        <span v-if="typingUser" class="typing-text-header">
          <span class="mini-dots"><span/><span/><span/></span>
          sedang mengetik...
        </span>
        <template v-else-if="contact.type !== 'group'">
          <span v-if="contact.is_online" class="header-online">
            <span class="header-online-dot" />
            Online
          </span>
          <span v-else-if="contact.last_seen_at" class="header-lastseen">
            terakhir dilihat {{ formatLastSeen(contact.last_seen_at) }}
          </span>
          <span v-else>{{ contact.area }}</span>
        </template>
        <span v-else>{{ contact.member_count || 0 }} anggota</span>
      </span>
    </div>
    <!-- Starred button -->
    <button class="gi-header-info-btn" @click="emit('openStarred')" title="Pesan Berbintang">
      <i class="pi pi-star" />
    </button>
    <!-- Search button — semua tipe chat -->
    <button class="gi-header-info-btn" @click="emit('openSearch')" title="Cari Pesan">
      <i class="pi pi-search" />
    </button>
    <button
      v-if="contact.type === 'group'"
      class="gi-header-info-btn"
      @click="emit('openGroupInfo')"
      title="Info Grup"
    >
      <i class="pi pi-info-circle" />
    </button>
    <span class="header-type-badge" :class="`badge-${contact.type}`">
      {{ contact.type === 'sales' ? 'Sales' : contact.type === 'group' ? 'Grup' : 'Admin' }}
    </span>
  </div>
</template>

<script setup>
const props = defineProps({
  contact:     { type: Object,  required: true },
  typingUser:  { type: String,  default: null },
  isMobile:    { type: Boolean, default: false },
  goBack:      { type: Function, default: null },
})
const emit = defineEmits(['back', 'openGroupInfo', 'openSearch', 'openStarred'])

function formatLastSeen(isoStr) {
  if (!isoStr) return ''
  const diff = Math.floor((Date.now() - new Date(isoStr).getTime()) / 1000)
  if (diff < 60)    return 'baru saja'
  if (diff < 3600)  return `${Math.floor(diff / 60)} menit lalu`
  if (diff < 86400) return `${Math.floor(diff / 3600)} jam lalu`
  return `${Math.floor(diff / 86400)} hari lalu`
}
</script>
