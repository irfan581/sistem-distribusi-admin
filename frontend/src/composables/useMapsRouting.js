import { ref } from 'vue'
// Named import lebih aman — default export @mapbox/polyline bisa berbeda di ESM vs CJS
import { decode as polylineDecode } from '@mapbox/polyline'

const OSRM_BASE     = 'https://router.project-osrm.org/route/v1/driving'
const MIN_DIST_M    = 50
const MAX_WAYPOINTS = 25

function haversineMeters([lat1, lng1], [lat2, lng2]) {
  const R    = 6371000
  const dLat = (lat2 - lat1) * Math.PI / 180
  const dLng = (lng2 - lng1) * Math.PI / 180
  const a    = Math.sin(dLat / 2) ** 2 +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
}

function dedupPoints(points) {
  if (!points.length) return []
  const result = [points[0]]
  for (let i = 1; i < points.length; i++) {
    if (haversineMeters(result[result.length - 1], points[i]) >= MIN_DIST_M) {
      result.push(points[i])
    }
  }
  return result
}

function samplePoints(points) {
  if (points.length <= MAX_WAYPOINTS) return points
  const result = [points[0]]
  const step   = (points.length - 2) / (MAX_WAYPOINTS - 2)
  for (let i = 1; i < MAX_WAYPOINTS - 1; i++) {
    result.push(points[Math.round(i * step)])
  }
  result.push(points[points.length - 1])
  return result
}

export function useMapsRouting() {
  const routeMode       = ref('straight')  // 'straight' | 'road'
  const routingLoading  = ref(false)
  const routingProgress = ref('')
  const routeCache      = new Map()        // key: `${salesId}_${tanggal}`

  async function fetchRoadRoute(points, cacheKey) {
    if (routeCache.has(cacheKey)) return routeCache.get(cacheKey)

    const clean = dedupPoints(points.filter(p => p[0] && p[1]))
    if (clean.length < 2) return points

    const sampled = samplePoints(clean)

    // OSRM format: lng,lat (longitude dulu)
    const coords = sampled.map(([lat, lng]) => `${lng},${lat}`).join(';')
    const url    = `${OSRM_BASE}/${coords}?overview=full&geometries=polyline6`

    try {
      const res = await fetch(url, { signal: AbortSignal.timeout(8000) })
      if (!res.ok) throw new Error(`OSRM HTTP ${res.status}`)

      const json = await res.json()
      if (json.code !== 'Ok' || !json.routes?.[0]?.geometry) {
        throw new Error(`OSRM code: ${json.code}`)
      }

      // decode(string, precision) — precision 6 untuk polyline6
      const decoded = polylineDecode(json.routes[0].geometry, 6)
      if (!decoded?.length) throw new Error('Decoded polyline kosong')

      routeCache.set(cacheKey, decoded)
      return decoded
    } catch (e) {
      console.error('[OSRM] Gagal fetch route, fallback ke garis lurus:', e?.message ?? e)
      return points
    }
  }

  function clearCache() {
    routeCache.clear()
  }

  return {
    routeMode,
    routingLoading,
    routingProgress,
    fetchRoadRoute,
    clearCache,
  }
}
