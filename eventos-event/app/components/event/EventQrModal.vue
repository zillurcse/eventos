<script setup lang="ts">
import { renderSVG } from 'uqr'

const emit = defineEmits<{ (e: 'close'): void }>()

const chat = useChatStore()
const auth = useAuthStore()
const site = useSiteStore()
const profile = useProfileStore()

// The current attendee's participation uuid = the connect target. The chat
// store loads it (res.me) with the inbox; fetch it if we arrived before that.
// Profile is the live source for name/photo (same store the header avatar and
// Edit Profile page share) — chat.profile is only a one-time inbox snapshot
// and goes stale the moment the attendee changes their photo.
onMounted(() => {
  if (!chat.me) chat.fetchInbox()
  if (!profile.loaded) profile.fetch()
})

const me = computed(() => chat.me)
const name = computed(() => profile.data?.name || chat.profile?.name || auth.user?.name || 'Me')
const subtitle = computed(() => [
  profile.data?.job_title || chat.profile?.job_title,
  profile.data?.company || chat.profile?.company,
].filter(Boolean).join(' · '))
const avatar = computed(() => profile.data?.avatar_url || chat.profile?.avatar_url || null)

/** Scannable link to my connect page, preserving the event subdomain in dev. */
const link = computed(() => {
  if (!import.meta.client || !me.value) return ''
  const origin = window.location.origin
  const host = window.location.hostname
  const isDevHost = host === 'localhost' || /^\d+\.\d+\.\d+\.\d+$/.test(host)
  const sub = useEventSubdomain()
  const url = `${origin}/connect/${me.value}`
  return isDevHost && sub ? `${url}?subdomain=${sub}` : url
})

const qr = computed(() => (link.value ? renderSVG(link.value, { border: 2 }) : ''))

const copied = ref(false)
async function copyLink() {
  if (!link.value) return
  try {
    await navigator.clipboard.writeText(link.value)
    copied.value = true
    setTimeout(() => (copied.value = false), 1600)
  } catch { /* clipboard blocked */ }
}

async function share() {
  if (!link.value) return
  try {
    if (navigator.share) await navigator.share({ title: `Connect with ${name.value}`, url: link.value })
    else await copyLink()
  } catch { /* dismissed */ }
}

/** Draws `img` into (x,y,w,h) cropped like CSS `object-fit: cover`. */
function drawCover(ctx: CanvasRenderingContext2D, img: CanvasImageSource, x: number, y: number, w: number, h: number) {
  const iw = (img as HTMLImageElement).naturalWidth || (img as any).width
  const ih = (img as HTMLImageElement).naturalHeight || (img as any).height
  const scale = Math.max(w / iw, h / ih)
  const sw = w / scale
  const sh = h / scale
  const sx = (iw - sw) / 2
  const sy = (ih - sh) / 2
  ctx.drawImage(img, sx, sy, sw, sh, x, y, w, h)
}

function roundRect(ctx: CanvasRenderingContext2D, x: number, y: number, w: number, h: number, r: number) {
  ctx.beginPath()
  ctx.moveTo(x + r, y)
  ctx.arcTo(x + w, y, x + w, y + h, r)
  ctx.arcTo(x + w, y + h, x, y + h, r)
  ctx.arcTo(x, y + h, x, y, r)
  ctx.arcTo(x, y, x + w, y, r)
  ctx.closePath()
}

/** Loads an <img>, going through a same-origin blob so a remote photo never
 *  taints the canvas — resolves null (never rejects) so a failed photo just
 *  falls back to the initials disc instead of aborting the card. Remote http(s)
 *  sources go through the image proxy since MinIO sends no CORS headers. */
async function loadImage(src: string): Promise<HTMLImageElement | null> {
  try {
    const fetchSrc = src.startsWith('http') ? `/api/image-proxy?url=${encodeURIComponent(src)}` : src
    const res = await fetch(fetchSrc)
    const blob = await res.blob()
    const url = URL.createObjectURL(blob)
    try {
      return await new Promise<HTMLImageElement>((resolve, reject) => {
        const img = new Image()
        img.onload = () => resolve(img)
        img.onerror = reject
        img.src = url
      })
    } finally {
      URL.revokeObjectURL(url)
    }
  } catch {
    return null
  }
}

function svgToDataUrl(svg: string): string {
  return `data:image/svg+xml;base64,${window.btoa(unescape(encodeURIComponent(svg)))}`
}

/** Shortens `text` to fit `maxWidth` using the ctx's current font, breaking on
 *  a word boundary where possible so it never lands mid-word ("Softw…"). */
function fitOneLine(ctx: CanvasRenderingContext2D, text: string, maxWidth: number): string {
  if (ctx.measureText(text).width <= maxWidth) return text

  let lo = 0
  let hi = text.length
  const fits = (n: number) => ctx.measureText(`${text.slice(0, n).trimEnd()}…`).width <= maxWidth
  while (lo < hi) {
    const mid = Math.ceil((lo + hi) / 2)
    if (fits(mid)) lo = mid
    else hi = mid - 1
  }

  let cut = text.slice(0, lo).trimEnd()
  const lastSpace = cut.lastIndexOf(' ')
  if (lastSpace > cut.length * 0.5) cut = cut.slice(0, lastSpace)
  return `${cut}…`
}

/** Small L-shaped corner marks around a rect — a "scan me" viewfinder cue. */
function drawScanCorners(ctx: CanvasRenderingContext2D, x: number, y: number, w: number, h: number, len: number, color: string) {
  ctx.save()
  ctx.strokeStyle = color
  ctx.lineWidth = 5
  ctx.lineCap = 'round'
  const corners: [number, number, number, number][] = [
    [x, y, 1, 1],
    [x + w, y, -1, 1],
    [x, y + h, 1, -1],
    [x + w, y + h, -1, -1],
  ]
  for (const [cx, cy, dx, dy] of corners) {
    ctx.beginPath()
    ctx.moveTo(cx, cy + dy * len)
    ctx.lineTo(cx, cy)
    ctx.lineTo(cx + dx * len, cy)
    ctx.stroke()
  }
  ctx.restore()
}

const cardBusy = ref(false)
const cardError = ref(false)

/** Renders the attendee's badge — a colour-blocked header/footer framing a
 *  photo, name and QR — as a single portrait PNG so it reads well shared
 *  as-is on Instagram/LinkedIn stories, not just as a bare link. */
async function buildPhotocard(): Promise<Blob | null> {
  if (!link.value || !qr.value) return null

  const W = 1080
  const H = 1350
  const canvas = document.createElement('canvas')
  canvas.width = W
  canvas.height = H
  const ctx = canvas.getContext('2d')
  if (!ctx) return null

  await document.fonts?.ready?.catch(() => {})

  const primary = site.branding?.primary || '#6d28d9'
  const accent = site.branding?.accent || primary

  // Full-bleed gradient behind the card reads as a branded frame.
  const bg = ctx.createLinearGradient(0, 0, W, H)
  bg.addColorStop(0, primary)
  bg.addColorStop(1, accent)
  ctx.fillStyle = bg
  ctx.fillRect(0, 0, W, H)

  const pad = 54
  const panelX = pad
  const panelY = pad
  const panelW = W - pad * 2
  const panelH = H - pad * 2
  const radius = 40

  roundRect(ctx, panelX, panelY, panelW, panelH, radius)
  ctx.fillStyle = '#ffffff'
  ctx.fill()

  // Header/footer colour bands, clipped to the panel's rounded corners.
  const headerH = 124
  const footerH = 110
  ctx.save()
  roundRect(ctx, panelX, panelY, panelW, panelH, radius)
  ctx.clip()

  const headerGrad = ctx.createLinearGradient(panelX, panelY, panelX + panelW, panelY + headerH)
  headerGrad.addColorStop(0, primary)
  headerGrad.addColorStop(1, accent)
  ctx.fillStyle = headerGrad
  ctx.fillRect(panelX, panelY, panelW, headerH)

  ctx.fillStyle = primary
  ctx.fillRect(panelX, panelY + panelH - footerH, panelW, footerH)
  ctx.restore()

  // Header: event name, white on the gradient band.
  ctx.textAlign = 'center'
  ctx.fillStyle = '#ffffff'
  ctx.font = '700 38px Cairo, sans-serif'
  ctx.fillText(fitOneLine(ctx, site.name, panelW - 140), W / 2, panelY + 78)

  // Avatar — white ring + soft shadow, sitting clear of both bands.
  const avR = 155
  const avCx = W / 2
  const avCy = panelY + headerH + 24 + avR

  ctx.save()
  ctx.shadowColor = 'rgba(15,23,42,.28)'
  ctx.shadowBlur = 28
  ctx.shadowOffsetY = 10
  ctx.beginPath()
  ctx.arc(avCx, avCy, avR + 10, 0, Math.PI * 2)
  ctx.fillStyle = '#ffffff'
  ctx.fill()
  ctx.restore()

  ctx.save()
  ctx.beginPath()
  ctx.arc(avCx, avCy, avR, 0, Math.PI * 2)
  ctx.closePath()
  ctx.clip()
  const avImg = avatar.value ? await loadImage(avatar.value) : null
  if (avImg) {
    drawCover(ctx, avImg, avCx - avR, avCy - avR, avR * 2, avR * 2)
  } else {
    ctx.fillStyle = avatarColor(name.value, primary)
    ctx.fillRect(avCx - avR, avCy - avR, avR * 2, avR * 2)
    ctx.fillStyle = '#fff'
    ctx.font = '700 100px Cairo, sans-serif'
    ctx.textBaseline = 'middle'
    ctx.fillText(initials(name.value), avCx, avCy + 6)
    ctx.textBaseline = 'alphabetic'
  }
  ctx.restore()

  ctx.beginPath()
  ctx.arc(avCx, avCy, avR + 10, 0, Math.PI * 2)
  ctx.lineWidth = 2
  ctx.strokeStyle = '#e2e8f0'
  ctx.stroke()

  // Name, then a running cursor for everything below it — spacing derives
  // from what's actually drawn instead of fixed offsets, so a missing
  // subtitle (or a wrapped one) can't collide with the QR block.
  let cursorY = avCy + avR + 70
  ctx.fillStyle = '#0f172a'
  ctx.font = '800 50px Cairo, sans-serif'
  ctx.fillText(fitOneLine(ctx, name.value, panelW - 160), W / 2, cursorY)

  if (subtitle.value) {
    cursorY += 44
    ctx.font = '600 26px Cairo, sans-serif'
    const chipText = fitOneLine(ctx, subtitle.value, panelW - 220)
    const chipW = Math.min(panelW - 120, ctx.measureText(chipText).width + 56)
    const chipH = 52
    roundRect(ctx, W / 2 - chipW / 2, cursorY, chipW, chipH, chipH / 2)
    ctx.fillStyle = '#f1f5f9'
    ctx.fill()
    ctx.fillStyle = primary
    ctx.fillText(chipText, W / 2, cursorY + chipH / 2 + 9)
    cursorY += chipH + 60
  } else {
    cursorY += 70
  }

  // QR block.
  const qrImg = await loadImage(svgToDataUrl(qr.value))
  const qrSize = 300
  const qrBoxSize = qrSize + 56
  const qrBoxX = W / 2 - qrBoxSize / 2
  const qrBoxY = cursorY

  roundRect(ctx, qrBoxX, qrBoxY, qrBoxSize, qrBoxSize, 24)
  ctx.fillStyle = '#f8fafc'
  ctx.fill()
  ctx.lineWidth = 1.5
  ctx.strokeStyle = '#e2e8f0'
  ctx.stroke()
  const qrImgX = W / 2 - qrSize / 2
  const qrImgY = qrBoxY + (qrBoxSize - qrSize) / 2
  if (qrImg) ctx.drawImage(qrImg, qrImgX, qrImgY, qrSize, qrSize)
  drawScanCorners(ctx, qrImgX - 12, qrImgY - 12, qrSize + 24, qrSize + 24, 26, primary)

  ctx.fillStyle = '#475569'
  ctx.font = '600 27px Cairo, sans-serif'
  ctx.fillText('Scan to connect with me', W / 2, qrBoxY + qrBoxSize + 46)

  // Footer, white on the solid brand band.
  ctx.fillStyle = 'rgba(255,255,255,.92)'
  ctx.font = '600 24px Cairo, sans-serif'
  ctx.fillText(`Powered by ${site.poweredBy}`, W / 2, panelY + panelH - footerH / 2 + 9)

  return new Promise(resolve => canvas.toBlob(b => resolve(b), 'image/png', 0.95))
}

/** Download button doubles as the "share to social" action: on a phone with
 *  the Web Share Level 2 API, sharing a real image file opens the native
 *  sheet (Instagram/WhatsApp/etc.); everywhere else it just saves the PNG. */
async function downloadPhotocard() {
  if (cardBusy.value) return
  cardBusy.value = true
  cardError.value = false
  try {
    const blob = await buildPhotocard()
    if (!blob) throw new Error('render failed')

    const file = new File([blob], `${(name.value || 'photocard').replace(/\s+/g, '-').toLowerCase()}-photocard.png`, { type: 'image/png' })

    if (navigator.canShare?.({ files: [file] })) {
      await navigator.share({ files: [file], title: `Connect with ${name.value}` })
      return
    }

    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = file.name
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  } catch (e) {
    if ((e as Error)?.name === 'AbortError') return // user dismissed the share sheet
    cardError.value = true
    setTimeout(() => (cardError.value = false), 2400)
  } finally {
    cardBusy.value = false
  }
}
</script>

<template>
  <div class="overlay" @click.self="emit('close')">
    <div class="modal" role="dialog" aria-modal="true">
      <button class="x" type="button" aria-label="Close" @click="emit('close')">
        <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
      </button>

      <div class="who">
        <span class="av">
          <UserAvatar :src="avatar" :name="name" />
        </span>
        <strong class="name">{{ name }}</strong>
        <span v-if="subtitle" class="sub">{{ subtitle }}</span>
      </div>

      <div class="qr-wrap">
        <div v-if="qr" class="qr" v-html="qr" />
        <div v-else class="qr loading">Generating…</div>
      </div>

      <p class="hint">Scan to connect with me</p>

      <div class="acts">
        <button class="btn ghost" type="button" @click="copyLink">{{ copied ? 'Copied!' : 'Copy link' }}</button>
        <button class="btn" type="button" @click="share">
          <svg viewBox="0 0 24 24"><circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" /><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4" /></svg>
          Share
        </button>
      </div>

      <button class="btn card-btn" type="button" :disabled="cardBusy || !qr" @click="downloadPhotocard">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3" /><circle cx="9" cy="10" r="2" /><path d="M21 16l-5.5-5.5L3 21" /><path d="M12 21h9" /></svg>
        {{ cardBusy ? 'Preparing…' : 'Download Photocard' }}
      </button>
      <p v-if="cardError" class="card-error">Couldn't generate the photocard — try again.</p>
    </div>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(15,23,42,.5); display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 80; }
.modal { position: relative; background: #fff; border-radius: 20px; width: 100%; max-width: 360px; padding: 30px 24px 24px; text-align: center; box-shadow: 0 20px 50px rgba(15,23,42,.28); }

.x { position: absolute; top: 14px; right: 14px; border: none; background: #f1f5f9; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.x svg { width: 15px; height: 15px; fill: none; stroke: #64748b; stroke-width: 2.2; stroke-linecap: round; }

.who { display: flex; flex-direction: column; align-items: center; gap: 4px; margin-bottom: 18px; }
.av { width: 60px; height: 60px; border-radius: 50%; background: var(--brand-primary); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem; overflow: hidden; }
.av img { width: 100%; height: 100%; object-fit: cover; }
.name { font-size: 1.1rem; font-weight: 800; color: #1e293b; margin-top: 6px; }
.sub { font-size: .82rem; color: #64748b; }

.qr-wrap { display: flex; justify-content: center; }
.qr { width: 220px; height: 220px; padding: 12px; border: 1px solid #eef0f3; border-radius: 16px; background: #fff; }
.qr :deep(svg) { width: 100%; height: 100%; display: block; }
.qr.loading { display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: .85rem; }

.hint { margin: 14px 0 18px; color: #475569; font-size: .9rem; font-weight: 600; }

.acts { display: flex; gap: 10px; }
.btn { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 7px; border: none; border-radius: 12px; padding: 12px; font: inherit; font-size: .88rem; font-weight: 700; cursor: pointer; background: var(--brand-primary); color: #fff; }
.btn:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
.btn.ghost { background: #f1f5f9; color: #475569; }
.btn.ghost:hover { background: #e7ebf0; }
.btn svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
.btn:disabled { opacity: .6; cursor: default; }

.card-btn { width: 100%; margin-top: 10px; }
.card-error { margin: 10px 0 0; color: #dc2626; font-size: .8rem; font-weight: 600; }
</style>
