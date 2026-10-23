<template>
  <div class="chat-sidebar" :class="{ 'sidebar-hidden': activeContact && isMobile }">
    <div class="sidebar-top">
      <div class="sidebar-title-row">
        <h2 class="sidebar-title">Pesan</h2>
        <div class="total-unread-badge" v-if="totalUnreadAll">
          {{ totalUnreadAll > 99 ? '99+' : totalUnreadAll }}
        </div>
      </div>
      <IconField class="sidebar-search">
        <InputIcon class="pi pi-search" />
        <InputText
          :modelValue="searchQuery"
          @update:modelValue="emit('update:searchQuery', $event)"
          placeholder="Cari kontak..."
          fluid
        />
      </IconField>
      <div class="contact-tabs">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="tab-btn"
          :class="{ active: activeTab === tab.key }"
          @click="emit('update:activeTab', tab.key)"
        >
          {{ tab.label }}
          <span v-if="tab.unread" class="tab-badge">{{ tab.unread > 99 ? '99+' : tab.unread }}</span>
        </button>
      </div>
    </div>

    <div class="contact-list">
      <div v-if="contactsLoading" class="contact-skeleton">
        <div v-for="i in 6" :key="i" class="skeleton-contact">
          <Skeleton shape="circle" size="44px" />
          <div style="flex:1; display:flex; flex-direction:column; gap:6px;">
            <Skeleton height="11px" />
            <Skeleton height="9px" width="65%" />
          </div>
        </div>
      </div>

      <template v-else>
        <div
          v-for="contact in filteredContacts"
          :key="`${contact.type}-${contact.id}`"
          class="contact-item"
          :class="{ active: isActiveContact(contact) }"
          @click="emit('contactSelected', contact)"
        >
          <div class="contact-avatar-wrap">
            <div class="contact-avatar" :class="`avatar-${contact.type}`">
              {{ contact.type === 'group'
                ? (contact.nama?.[0]?.toUpperCase() || 'G')
                : contact.initial }}
            </div>
            <!-- Online dot — hanya DM, bukan grup -->
            <span
              v-if="contact.type !== 'group' && contact.is_online"
              class="online-dot"
              title="Online"
            />
            <!-- Unread badge — tetap tampil meskipun online -->
            <span v-if="contact.unread" class="unread-badge">
              {{ contact.unread > 99 ? '99+' : contact.unread }}
            </span>
          </div>
          <div class="contact-info">
            <div class="contact-top-row">
              <span class="contact-name">{{ contact.nama }}</span>
              <span class="contact-time">{{ contact.last_time || '' }}</span>
            </div>
            <span class="contact-preview" :class="{ 'contact-area': !contact.last_message && contact.area }">
              {{ contact.last_message
                || (contact.type === 'group' ? `${contact.member_count || 0} anggota` : contact.area)
                || '' }}
            </span>
          </div>
        </div>

        <div v-if="filteredContacts.length === 0" class="contact-empty">
          <i class="pi pi-users" />
          <span>Tidak ada {{ activeTab === 'group' ? 'grup' : 'kontak' }}</span>
        </div>
      </template>
    </div>

    <div v-if="activeTab === 'group'" class="sidebar-footer">
      <button class="buat-grup-btn" @click="emit('openBuatGrup')">
        <i class="pi pi-plus" /> Buat Grup
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  contacts:        { type: Object,  required: true },
  contactsLoading: { type: Boolean, default: false },
  searchQuery:     { type: String,  required: true },
  activeTab:       { type: String,  required: true },
  tabs:            { type: Array,   required: true },
  filteredContacts: { type: Array,  required: true },
  totalUnreadAll:  { type: Number,  default: 0 },
  activeContact:   { type: Object,  default: null },
  isMobile:        { type: Boolean, default: false },
  isActiveContact: { type: Function, required: true },
})

const emit = defineEmits([
  'update:searchQuery', 'update:activeTab',
  'contactSelected', 'openBuatGrup',
])
</script>
