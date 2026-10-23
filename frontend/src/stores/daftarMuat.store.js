import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'
import { daftarMuatService } from '@/services/daftarMuat.service'

export const useDaftarMuatStore = defineStore('daftarMuat', () => {
  const list    = ref([])
  const loading = ref(false)
  const saving  = ref(false)
  const meta    = reactive({ current_page: 1, last_page: 1, per_page: 25, total: 0 })

  async function fetchAll(filters = {}) {
    loading.value = true
    try {
      const res  = await daftarMuatService.getAll(filters)
      list.value = res.data ?? []
      if (res.meta) Object.assign(meta, res.meta)
    } catch (err) {
      console.error('[DaftarMuat] fetchAll error:', err?.response?.data ?? err.message)
      throw err
    } finally {
      loading.value = false
    }
  }

  async function store(payload) {
    saving.value = true
    try {
      const res = await daftarMuatService.store(payload)
      list.value.unshift(res.data)
      meta.total++
      return res
    } finally {
      saving.value = false
    }
  }

  async function update(id, payload) {
    saving.value = true
    try {
      const res = await daftarMuatService.update(id, payload)
      _patch(id, res.data)
      return res
    } finally {
      saving.value = false
    }
  }

  async function destroy(id) {
    saving.value = true
    try {
      await daftarMuatService.destroy(id)
      list.value = list.value.filter(item => item.id !== id)
      meta.total = Math.max(0, meta.total - 1)
    } finally {
      saving.value = false
    }
  }

  function _patch(id, updated) {
    const idx = list.value.findIndex(i => i.id === id)
    if (idx !== -1 && updated) list.value[idx] = { ...list.value[idx], ...updated }
  }

  return { list, loading, saving, meta, fetchAll, store, update, destroy }
})
