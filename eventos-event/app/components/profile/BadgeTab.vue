<script setup lang="ts">
/**
 * Profile › My Badge — the same live pass(es) as the top-level /badges page,
 * shown inside the profile card layout. The heavy lifting (design merge, PDF
 * export, scan overlay) is a straight port of `pages/badges.vue`; see that file
 * for why the download captures a hidden 1:1 node and never leaks artwork
 * through a third-party proxy.
 */
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

/** Which badge is showing its scan-me overlay. */
const scanning = ref<Badge | null>(null)

/** Per-badge face, so flipping one card doesn't flip the others. */
const flipped = ref<Record<string, boolean>>({})

function hasBack(b: Badge) {
  return (b.design?.badge_json?.backBoxes ?? []).length > 0
}

function sideOf(b: Badge) {
  return flipped.value[b.participation_id] ? 'back' : 'front'
}

// ── Download (see pages/badges.vue for the full rationale) ────────────────────
const downloading = ref<string | null>(null)
const capturing = ref<Badge | null>(null)
const captureFront = ref<HTMLElement | null>(null)
const captureBack = ref<HTMLElement | null>(null)

function pageSize(b: Badge) {
  return badgePageSize(b.design?.badge_json)
}

async function download(b: Badge) {
  if (downloading.value) return
  downloading.value = b.participation_id
  capturing.value = b

  try {
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
          <button class="btn primary" @click="download(b)" :disabled="!!downloading">
            {{ downloading === b.participation_id ? 'Preparing…' : 'Download' }}
          </button>
          <button class="btn" @click="scanning = b">Show QR</button>
          <button
            v-if="hasBack(b)"
            class="btn"
            @click="flipped[b.participation_id] = !flipped[b.participation_id]"
          >
            {{ flipped[b.participation_id] ? 'Show front' : 'Show back' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Off-screen 1:1 render used only as the source bitmap for the PDF. -->
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
.state { color: var(--text-muted, #6b7280); padding: 24px 0; }

.grid { display: grid; gap: 20px; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }

.card { display: flex; flex-direction: column; align-items: center; gap: 14px; }

.paper { border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0, 0, 0, .12); }

.meta { text-align: center; }
.role {
  display: inline-block; font-size: .72rem; font-weight: 700; letter-spacing: .04em;
  text-transform: uppercase; color: var(--brand-primary, #6352e7);
}
.name { display: block; font-weight: 600; }

.actions { display: flex; flex-direction: column; gap: 8px; width: 100%; max-width: 300px; }

.btn {
  padding: 11px 16px; border-radius: 10px; border: 1px solid var(--line, #e5e7eb);
  background: #fff; font-size: .9rem; font-weight: 600; cursor: pointer;
}
.btn:disabled { opacity: .6; cursor: default; }
.btn.primary { background: var(--brand-primary, #6352e7); border-color: var(--brand-primary, #6352e7); color: #fff; }

.scan {
  position: fixed; inset: 0; z-index: 100;
  background: #fff; display: grid; place-items: center; padding: 24px;
}
.capture { position: fixed; top: 0; left: -10000px; pointer-events: none; }

.scan-inner { text-align: center; display: flex; flex-direction: column; align-items: center; gap: 8px; }
.scan-qr { width: min(78vw, 380px); height: min(78vw, 380px); }
.scan-name { margin: 12px 0 0; font-size: 1.15rem; font-weight: 700; }
.scan-role { margin: 0 0 12px; color: var(--text-muted, #6b7280); font-size: .88rem; }
</style>
