import { ref, computed } from 'vue'
import { chatService } from '@/services/chat.service'

export function useChatContacts() {
  const contacts        = ref({ admins: [], sales: [], groups: [] })
  const contactsLoading = ref(true)
  const searchQuery     = ref('')
  const activeTab       = ref('sales')

  const totalUnreadSales = computed(() => contacts.value.sales.reduce((s, c) => s + (c.unread || 0), 0))
  const totalUnreadAdmin = computed(() => contacts.value.admins.reduce((s, c) => s + (c.unread || 0), 0))
  const totalUnreadGroup = computed(() => contacts.value.groups.reduce((s, c) => s + (c.unread || 0), 0))
  const totalUnreadAll   = computed(() => totalUnreadSales.value + totalUnreadAdmin.value + totalUnreadGroup.value)

  const tabs = computed(() => [
    { key: 'sales', label: 'Sales', unread: totalUnreadSales.value },
    { key: 'admin', label: 'Admin', unread: totalUnreadAdmin.value },
    { key: 'group', label: 'Grup',  unread: totalUnreadGroup.value },
  ])

  const filteredContacts = computed(() => {
    const map  = { sales: contacts.value.sales, admin: contacts.value.admins, group: contacts.value.groups }
    const list = map[activeTab.value] || []
    if (!searchQuery.value) return list
    const q = searchQuery.value.toLowerCase()
    return list.filter(c => c.nama.toLowerCase().includes(q))
  })

  function isActiveContact(contact, activeContact) {
    return activeContact?.id === contact.id && activeContact?.type === contact.type
  }

  function updateContactPreview(contact, text, time) {
    const listMap = { sales: contacts.value.sales, admin: contacts.value.admins, group: contacts.value.groups }
    const list = listMap[contact.type] || listMap['user']
    const c = list.find(c => c.id === contact.id)
    if (c) { c.last_message = text; c.last_time = time }
  }

  async function loadContacts() {
    contactsLoading.value = true
    try {
      const [contactsRes, groupsRes] = await Promise.all([
        chatService.getContacts(),
        chatService.getGroups(),
      ])
      contacts.value.admins = (contactsRes.data?.admins || []).map(c => ({ ...c, type: 'admin' }))
      contacts.value.sales  = (contactsRes.data?.sales  || []).map(c => ({ ...c, type: 'sales' }))
      contacts.value.groups = (groupsRes.data || []).map(g => ({ ...g, type: 'group' }))
    } catch { /* silent */ }
    finally { contactsLoading.value = false }
  }

  return {
    contacts,
    contactsLoading,
    searchQuery,
    activeTab,
    tabs,
    filteredContacts,
    totalUnreadAll,
    isActiveContact,
    updateContactPreview,
    loadContacts,
  }
}
