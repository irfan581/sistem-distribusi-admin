/**
 * usePullRefresh — dipakai di setiap halaman yang ingin support pull-to-refresh.
 *
 * Cara pakai di halaman:
 *   import { usePullRefresh } from '@/composables/usePullRefresh'
 *   usePullRefresh(() => loadData())
 *
 * AdminLayout inject 'onPullRefresh' (ref counter).
 * Composable ini watch counter tersebut dan panggil callback saat berubah.
 */
import { inject, watch } from 'vue'

export function usePullRefresh(callback) {
  const refreshBus = inject('onPullRefresh', null)
  if (!refreshBus) return   // tidak di dalam AdminLayout — skip

  watch(refreshBus, (val, old) => {
    if (val > old) callback()
  })
}
