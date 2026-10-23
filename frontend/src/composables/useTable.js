import { ref, reactive } from 'vue'
import { PAGINATION } from '@/utils/constants'

export function useTable(fetchFn) {
  const items = ref([])
  const loading = ref(false)
  const totalRecords = ref(0)

  const filters = reactive({
    search: '',
    page: 1,
    per_page: PAGINATION.DEFAULT_ROWS,
  })

  async function load(extra = {}) {
    loading.value = true
    try {
      const response = await fetchFn({ ...filters, ...extra })
      items.value = response.data?.data || response.data || []
      totalRecords.value = response.data?.total || items.value.length
    } finally {
      loading.value = false
    }
  }

  function onPage(event) {
    filters.page = event.page + 1
    filters.per_page = event.rows
    load()
  }

  function onSearch(val) {
    filters.search = val
    filters.page = 1
    load()
  }

  return {
    items,
    loading,
    totalRecords,
    filters,
    load,
    onPage,
    onSearch,
  }
}
