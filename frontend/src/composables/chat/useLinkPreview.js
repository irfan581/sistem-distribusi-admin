import { chatService } from '@/services/chat.service'

// In-memory cache — URL → preview data
const previewCache = new Map()

// URL regex — cukup strict untuk hindari false positive
const URL_RE = /https?:\/\/(www\.)?[-a-zA-Z0-9@:%._+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_+.~#?&/=]*)/g

/**
 * Extract first URL dari teks pesan.
 */
export function extractFirstUrl(text) {
  if (!text || typeof text !== 'string') return null
  const match = text.match(URL_RE)
  return match ? match[0] : null
}

/**
 * Fetch link preview dengan in-memory cache.
 * Returns null jika URL tidak valid atau fetch gagal.
 */
export async function fetchLinkPreview(url) {
  if (!url) return null

  if (previewCache.has(url)) {
    return previewCache.get(url)
  }

  // Set null dulu untuk hindari duplicate request
  previewCache.set(url, null)

  try {
    const res = await chatService.getLinkPreview(url)
    const data = res.data ?? null
    previewCache.set(url, data)
    return data
  } catch {
    previewCache.set(url, null)
    return null
  }
}
