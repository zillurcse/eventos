<script setup lang="ts">
/**
 * "My Badges" — the attendee's own pass(es) for this event.
 *
 * One card per participation, not per person: someone who is both a speaker and
 * an exhibitor's team member holds two passes here and needs whichever the door
 * in front of them wants. The organizer's canvas design is rendered as-is, with
 * this person's details merged in, so the badge on screen is the badge that
 * comes out of the printer.
 *
 * The "Show QR" mode blows the code up to fill the screen and pins brightness
 * to white — scanning a small QR off a dimmed phone at a busy gate is the one
 * thing this page has to get right.
 */
definePageMeta({ layout: 'event', middleware: 'auth' })

interface Badge {
  participation_id: string
  role_label: string
  design: { id: number, name: string, badge_json: any }
  data: Record<string, string>
}

const badges = ref<Badge[]>([])
const loading = ref(true)
const failed = ref(false)
const site = useSiteStore()

/** Which badge is showing its scan-me overlay, by participation id. */
const scanning = ref<Badge | null>(null)

/** Per-badge face, so flipping one card doesn't flip the others. */
const flipped = ref<Record<string, boolean>>({})

function hasBack(b: Badge) {
  return (b.design?.badge_json?.backBoxes ?? []).length > 0
}

function sideOf(b: Badge) {
  return flipped.value[b.participation_id] ? 'back' : 'front'
}

// ── Download ─────────────────────────────────────────────────────────────────
/**
 * PDF export, the same technique the organizer's badge editor uses
 * (modules/badge.expouse/app/pages/my-badge/preview-badge.vue): rasterise the
 * rendered badge with html2canvas, then drop that bitmap into a jsPDF page cut
 * to the design's real millimetre size, so what prints is physically correct.
 *
 * Two deliberate differences from that page:
 *
 *  - It captures a *hidden, full-size* copy rather than the on-screen card. The
 *    original mutates the live node's transform mid-capture and puts it back
 *    afterwards; rendering a dedicated 1:1 node instead means the visible page
 *    never flickers and the output does not depend on the current card size.
 *  - No third-party image proxy. The original falls back to `api.allorigins.win`
 *    to dodge CORS, which would send this event's artwork — and the attendee's
 *    own photo — through someone else's server. `useCORS` is the whole story
 *    here; if an image host refuses CORS, that image is omitted from the PDF
 *    rather than leaked to fix it.
 */
const downloading = ref<string | null>(null)
const capturing = ref<Badge | null>(null)
const captureFront = ref<HTMLElement | null>(null)
const captureBack = ref<HTMLElement | null>(null)

/**
 * Print size in mm (what the PDF page is cut to) and the pixel canvas the
 * design was authored at, both from the one helper the renderer uses — see
 * `badgePageSize` for why `page_config.pageWidth` is not read directly.
 */
function pageSize(b: Badge) {
  return badgePageSize(b.design?.badge_json)
}

async function download(b: Badge) {
  if (downloading.value) return
  downloading.value = b.participation_id
  capturing.value = b

  try {
    // Loaded on demand: these two are ~600 kB together, and most people open
    // this page to show a QR at a door, not to save a PDF.
    const [{ default: html2canvas }, { jsPDF }] = await Promise.all([
      import('html2canvas'),
      import('jspdf'),
    ])

    await nextTick()

    const mm = { width: pageSize(b).widthMm, height: pageSize(b).heightMm }
    const shoot = (el: HTMLElement) => html2canvas(el, {
      scale: 3, // ~300dpi at these sizes, enough for a printed badge
      backgroundColor: null,
      useCORS: true,
      logging: false,
    })

    const front = await shoot(captureFront.value!)
    const back = hasBack(b) && captureBack.value ? await shoot(captureBack.value) : null

    const pdf = new jsPDF({
      orientation: mm.width > mm.height ? 'landscape' : 'portrait',
      unit: 'mm',
      format: [mm.width, mm.height],
      compress: true,
    })

    pdf.addImage(front.toDataURL('image/png'), 'PNG', 0, 0, mm.width, mm.height, undefined, 'MEDIUM')

    // The back is a second page, not a second panel: it is the reverse of the
    // same piece of card, so it has to print at the same size.
    if (back) {
      pdf.addPage([mm.width, mm.height], mm.width > mm.height ? 'landscape' : 'portrait')
      pdf.addImage(back.toDataURL('image/png'), 'PNG', 0, 0, mm.width, mm.height, undefined, 'MEDIUM')
    }

    const name = (b.data.full_name || 'badge').toLowerCase().replace(/\s+/g, '_')
    pdf.save(`${name}-badge.pdf`)
  } catch {
    // eslint-disable-next-line no-alert
    alert('Your badge could not be downloaded. Please try again.')
  } finally {
    downloading.value = null
    capturing.value = null
  }
}

async function load() {
  const uuid = site.event?.uuid
  if (!uuid) { failed.value = true; loading.value = false; return }

  try {
    const res = await useApi()<{ data: Badge[] }>(`/events/${uuid}/my/badges`)
    badges.value = res.data
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="head">
      <h1>My Badges</h1>
      <p class="sub">Show this at the entrance — the code is scanned at the gates.</p>
    </div>

    <p v-if="loading" class="state">Loading your badge…</p>

    <p v-else-if="failed" class="state">
      Your badge couldn’t be loaded right now. Please try again in a moment.
    </p>

    <p v-else-if="!badges.length" class="state">
      The organizers haven’t published a badge for this event yet.
    </p>

    <div v-else class="grid">
      <div v-for="b in badges" :key="b.participation_id" class="card">
        <div class="paper">
          <BadgeRender
            :badge-json="b.design.badge_json"
            :data="b.data"
            :side="sideOf(b)"
            :max-width="300"
            :max-height="420"
          />
        </div>

        <div class="meta">
          <span class="role">{{ b.role_label }}</span>
          <span class="name">{{ b.data.full_name }}</span>
        </div>

        <div class="actions">
          <button class="btn primary" @click="scanning = b">Show QR</button>
          <button
            v-if="hasBack(b)"
            class="btn"
            @click="flipped[b.participation_id] = !flipped[b.participation_id]"
          >
            {{ flipped[b.participation_id] ? 'Show front' : 'Show back' }}
          </button>
          <button class="btn" :disabled="!!downloading" @click="download(b)">
            {{ downloading === b.participation_id ? 'Preparing…' : 'Download PDF' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Off-screen 1:1 render used only as the source bitmap for the PDF. Kept
         out of the layout with a transform rather than `display: none`, which
         html2canvas cannot rasterise. -->
    <div v-if="capturing" class="capture" aria-hidden="true">
      <div ref="captureFront">
        <BadgeRender
          :badge-json="capturing.design.badge_json"
          :data="capturing.data"
          side="front"
          :max-width="pageSize(capturing).width"
          :max-height="pageSize(capturing).height"
        />
      </div>
      <div v-if="hasBack(capturing)" ref="captureBack">
        <BadgeRender
          :badge-json="capturing.design.badge_json"
          :data="capturing.data"
          side="back"
          :max-width="pageSize(capturing).width"
          :max-height="pageSize(capturing).height"
        />
      </div>
    </div>

    <!-- Scan overlay: nothing but the code, as large and as bright as we can. -->
    <div v-if="scanning" class="scan" @click="scanning = null">
      <div class="scan-inner" @click.stop>
        <Qrcode :value="scanning.data.qrcode" class="scan-qr" />
        <p class="scan-name">{{ scanning.data.full_name }}</p>
        <p class="scan-role">{{ scanning.role_label }} · {{ scanning.data.event_name }}</p>
        <button class="btn" @click="scanning = null">Close</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.head { margin-bottom: 16px; }
.head h1 { margin: 0; font-size: 1.35rem; }
.sub { margin: 4px 0 0; color: var(--text-muted, #6b7280); font-size: .9rem; }

.state { color: var(--text-muted, #6b7280); padding: 32px 0; }

.grid { display: grid; gap: 20px; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }

.card {
  background: #fff;
  border: 1px solid var(--line, #e5e7eb);
  border-radius: 16px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.paper { border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0, 0, 0, .12); }

.meta { text-align: center; }
.role {
  display: inline-block;
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: var(--brand-primary, #6352e7);
}
.name { display: block; font-weight: 600; }

.actions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; }

.btn {
  padding: 8px 16px;
  border-radius: 999px;
  border: 1px solid var(--line, #e5e7eb);
  background: #fff;
  font-size: .86rem;
  font-weight: 600;
  cursor: pointer;
}
.btn.primary { background: var(--brand-primary, #6352e7); border-color: var(--brand-primary, #6352e7); color: #fff; }

.scan {
  position: fixed;
  inset: 0;
  z-index: 100;
  /* Opaque white, not a dim scrim: scanners read a bright, high-contrast code. */
  background: #fff;
  display: grid;
  place-items: center;
  padding: 24px;
}
/* Parked off-screen but still laid out and painted — html2canvas needs real
   boxes, so `display: none` or `visibility: hidden` would capture nothing. */
.capture { position: fixed; top: 0; left: -10000px; pointer-events: none; }

.scan-inner { text-align: center; display: flex; flex-direction: column; align-items: center; gap: 8px; }
.scan-qr { width: min(78vw, 380px); height: min(78vw, 380px); }
.scan-name { margin: 12px 0 0; font-size: 1.15rem; font-weight: 700; }
.scan-role { margin: 0 0 12px; color: var(--text-muted, #6b7280); font-size: .88rem; }
</style>
