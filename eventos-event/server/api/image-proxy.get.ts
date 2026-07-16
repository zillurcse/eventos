/**
 * Streams a same-origin copy of a remote image so the photocard <canvas> can
 * read its pixels back out. MinIO (the `s3` disk avatars/logos live on)
 * doesn't send Access-Control-Allow-Origin, so a plain <img> tag shows the
 * photo fine but a client-side fetch()/drawImage()+toBlob() is blocked as a
 * tainted canvas. Fetching it here, server-to-server, sidesteps that.
 */
export default defineEventHandler(async (event) => {
  const { url } = getQuery(event)
  if (typeof url !== 'string' || !url) {
    throw createError({ statusCode: 400, statusMessage: 'Missing url' })
  }

  let target: URL
  try {
    target = new URL(url)
  } catch {
    throw createError({ statusCode: 400, statusMessage: 'Invalid url' })
  }
  if (target.protocol !== 'http:' && target.protocol !== 'https:') {
    throw createError({ statusCode: 400, statusMessage: 'Unsupported protocol' })
  }

  const { minioInternalBase } = useRuntimeConfig(event)
  // In the Docker dev stack the URL stored on the profile is MinIO's
  // *public* address (localhost:9000), which only resolves from the browser
  // — this server runs in its own container, so it needs MinIO's internal
  // service hostname instead. Try that first, then fall back to the URL
  // as-is (which is exactly what a real public bucket/CDN needs in prod).
  const candidates = minioInternalBase
    ? [`${minioInternalBase}${target.pathname}${target.search}`, target.toString()]
    : [target.toString()]

  for (const candidate of candidates) {
    try {
      const res = await fetch(candidate)
      const contentType = res.headers.get('content-type') || ''
      if (!res.ok || !contentType.startsWith('image/')) continue

      const buf = Buffer.from(await res.arrayBuffer())
      if (buf.byteLength > 8 * 1024 * 1024) continue

      setResponseHeader(event, 'Content-Type', contentType)
      setResponseHeader(event, 'Cache-Control', 'public, max-age=3600')
      return buf
    } catch {
      // try the next candidate
    }
  }

  throw createError({ statusCode: 502, statusMessage: 'Could not fetch image' })
})
