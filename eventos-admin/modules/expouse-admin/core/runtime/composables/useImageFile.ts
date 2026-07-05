import imageCompression from 'browser-image-compression'

const DEFAULT_TYPES = ['image/jpeg', 'image/png', 'image/webp']

/** Client-side gate before any upload — returns an error message, or null when the file is fine. */
export function validateImageFile(file: File, opts: { maxMB?: number, types?: string[] } = {}): string | null {
  const maxMB = opts.maxMB ?? 10
  const types = opts.types ?? DEFAULT_TYPES
  if (!types.includes(file.type)) return 'Unsupported file type. Use a JPEG, PNG, or WebP image.'
  if (file.size > maxMB * 1024 * 1024) return `File is too large. Maximum size is ${maxMB} MB.`
  return null
}

/** Re-encode oversized crop output down to ~1 MB; small blobs pass through untouched. */
export async function maybeCompress(blob: Blob, thresholdMB = 1.5): Promise<Blob> {
  if (blob.size <= thresholdMB * 1024 * 1024) return blob
  const file = new File([blob], 'image.jpg', { type: blob.type || 'image/jpeg' })
  return imageCompression(file, { maxSizeMB: 1, useWebWorker: true, initialQuality: 0.85 })
}
