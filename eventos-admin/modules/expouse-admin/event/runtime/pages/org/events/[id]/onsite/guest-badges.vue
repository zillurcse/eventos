<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'

/**
 * Guest badge creation — the four-step wizard behind Onsite › Badges › Guest
 * Badges.
 *
 *   1. Badge creation  — name the batch and say what kind of guest it is.
 *   2. Upload guest list — a spreadsheet of people who were never registered.
 *   3. Badge design    — confirm the starter design, or open the canvas editor.
 *   4. Deliver badge   — print, or publish to the guests' own devices.
 *
 * Step 1 commits immediately (it creates the batch and its design server-side)
 * so the wizard is resumable: `?batch=<id>` re-enters at the guest list with
 * everything already saved. That matters because step 3 sends the organizer out
 * to the canvas editor on a different route — without a committed batch, the
 * round trip would lose the work.
 */
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface Guest {
  full_name: string
  email: string
  designation: string
  company: string
  phone: string
  photo_url: string
  /** Client-side validation outcome shown in the preview table. */
  error?: string
}

interface Batch {
  id: number
  name: string
  guest_type: string | null
  guest_count: number
  delivery: { method: string, at: string } | null
  design: any
}

const STEPS = ['Badge creation', 'Upload guest list', 'Badge design', 'Deliver badge']

/** Common guest kinds; the field stays free text for anything else. */
const GUEST_TYPES = ['Media', 'VVIP', 'VIP', 'Press', 'Partner', 'Government', 'Jury']

const step = ref(0)
const batch = ref<Batch | null>(null)
const sampleData = ref<Record<string, string> | null>(null)

// ── Step 1 ────────────────────────────────────────────────────────────────────
const form = reactive({ name: '', guest_type: 'Media' })
const creating = ref(false)

async function createBatch() {
  if (!form.name.trim()) { toast.error('Please name this badge.'); return }
  creating.value = true
  try {
    const res: any = await api(`/events/${id}/guest-badges`, {
      method: 'POST',
      body: { name: form.name.trim(), guest_type: form.guest_type.trim() || 'Guest' },
    })
    batch.value = res.data
    step.value = 1
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not create the badge.')
  } finally {
    creating.value = false
  }
}

// ── Step 2 ────────────────────────────────────────────────────────────────────
const guests = ref<Guest[]>([])
const parseError = ref('')
const importing = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const dragging = ref(false)

/** Header spellings we accept, mapped onto the fields the API takes. */
const COLUMN_ALIASES: Record<string, keyof Guest> = {
  'full name': 'full_name', 'name': 'full_name', 'full_name': 'full_name',
  'designation': 'designation', 'title': 'designation', 'job title': 'designation',
  'organisation': 'company', 'organization': 'company', 'company': 'company',
  'email': 'email', 'e-mail': 'email',
  'phone': 'phone', 'mobile': 'phone',
  'photo url': 'photo_url', 'photo_url': 'photo_url', 'photo': 'photo_url',
}

/**
 * Minimal RFC-4180 reader: quoted fields, embedded commas, doubled quotes and
 * both line endings. Enough for the spreadsheets organizers actually export,
 * and it keeps the preview instant — no upload round trip before they can see
 * whether their file parsed.
 */
function parseCsv(text: string): string[][] {
  const rows: string[][] = []
  let row: string[] = []
  let field = ''
  let quoted = false

  for (let i = 0; i < text.length; i++) {
    const c = text[i]

    if (quoted) {
      if (c === '"') {
        if (text[i + 1] === '"') { field += '"'; i++ }
        else quoted = false
      } else field += c
      continue
    }

    if (c === '"') quoted = true
    else if (c === ',') { row.push(field); field = '' }
    else if (c === '\n' || c === '\r') {
      if (c === '\r' && text[i + 1] === '\n') i++
      row.push(field); field = ''
      if (row.some(v => v.trim() !== '')) rows.push(row)
      row = []
    } else field += c
  }

  row.push(field)
  if (row.some(v => v.trim() !== '')) rows.push(row)

  return rows
}

function readFile(file: File) {
  parseError.value = ''

  if (/\.xlsx?$/i.test(file.name)) {
    parseError.value = 'Excel files are not supported yet — please save the sheet as CSV and upload that.'
    return
  }

  const reader = new FileReader()
  reader.onload = () => {
    const rows = parseCsv(String(reader.result ?? ''))
    if (rows.length < 2) {
      parseError.value = 'That file has a header row but no guests in it.'
      return
    }

    const headers = rows[0]!.map(h => h.trim().toLowerCase())
    const mapped = headers.map(h => COLUMN_ALIASES[h])

    if (!mapped.includes('full_name')) {
      parseError.value = 'No "Full name" column found. Download the sample file to see the expected columns.'
      return
    }

    guests.value = rows.slice(1).map((cells) => {
      const g: Guest = { full_name: '', email: '', designation: '', company: '', phone: '', photo_url: '' }
      mapped.forEach((field, i) => { if (field) g[field] = (cells[i] ?? '').trim() as never })
      // Surfaced per row rather than rejecting the whole file: one typo in a
      // 200-name press list should not send the organizer back to Excel.
      if (!g.full_name) g.error = 'Missing name'
      else if (g.email && !/^\S+@\S+\.\S+$/.test(g.email)) g.error = 'Invalid email'
      return g
    })
  }
  reader.onerror = () => { parseError.value = 'That file could not be read.' }
  reader.readAsText(file)
}

function onFile(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (file) readFile(file)
}

function onDrop(e: DragEvent) {
  dragging.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file) readFile(file)
}

const validGuests = computed(() => guests.value.filter(g => !g.error))

function downloadSample() {
  const csv = 'Full name,Designation,Organisation,Email,Phone,Photo URL\n'
    + 'Vikram Desai,CIO,TCS,vikram@example.com,,\n'
    + 'Ananya Sharma,VP Engineering,Google,ananya@example.com,,\n'
  const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }))
  const a = document.createElement('a')
  a.href = url
  a.download = 'guest-list-sample.csv'
  a.click()
  URL.revokeObjectURL(url)
}

async function commitGuests() {
  if (!batch.value || !validGuests.value.length) return
  importing.value = true
  try {
    const res: any = await api(`/guest-badges/${batch.value.id}/guests`, {
      method: 'POST',
      body: { guests: validGuests.value.map(({ error, ...g }) => g) },
    })
    toast.success(`${res.meta.total} guests added`)
    await loadBatch()
    step.value = 2
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not add the guests.')
  } finally {
    importing.value = false
  }
}

// ── Step 3 ────────────────────────────────────────────────────────────────────
function editDesign() {
  // Come back to this batch's design step, not to a blank wizard.
  navigateTo(`/org/events/${id}/badge?design=${batch.value?.design?.id}&return=guest-badges&batch=${batch.value?.id}`)
}

// ── Step 4 ────────────────────────────────────────────────────────────────────
const method = ref<'print' | 'email' | 'qr'>('print')
const delivering = ref(false)
const printing = ref(false)

/** Every guest's merged render payload — loaded with the batch. */
const renders = ref<{ name: string, data: Record<string, string> }[]>([])

async function deliver() {
  if (!batch.value) return
  delivering.value = true
  try {
    if (method.value === 'print') await printBadges()
    await api(`/guest-badges/${batch.value.id}/deliver`, { method: 'POST', body: { method: method.value } })
    toast.success(method.value === 'qr'
      ? 'Badges published — guests can open them in the event app'
      : 'Badges sent to the printer')
    await loadBatch()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not deliver the badges.')
  } finally {
    delivering.value = false
  }
}

/**
 * Print via the browser rather than a server-generated PDF: the badges are
 * already rendered on this page by the same component that drew the preview, so
 * what comes out of the printer is what the organizer approved in step 3.
 */
async function printBadges() {
  printing.value = true
  await nextTick()
  window.print()
  // Give the print dialog a tick to take its snapshot before we tear the
  // print-only nodes back down.
  setTimeout(() => { printing.value = false }, 500)
}

// ── Loading ───────────────────────────────────────────────────────────────────
async function loadBatch() {
  if (!batch.value) return
  const res: any = await api(`/guest-badges/${batch.value.id}`)
  batch.value = res.data
  renders.value = (res.data.guests ?? []).map((g: any) => ({ name: g.full_name, data: g.render }))
}

async function loadSample() {
  try {
    const res: any = await api(`/events/${id}/badge-designs/sample-data`, {
      query: { badge_for: 'guest', guest_type: batch.value?.guest_type ?? 'Guest' },
    })
    sampleData.value = res.data
  } catch { sampleData.value = null }
}

onMounted(async () => {
  // Resuming after a trip to the canvas editor.
  const resume = Number(route.query.batch)
  if (resume) {
    try {
      const res: any = await api(`/guest-badges/${resume}`)
      batch.value = res.data
      renders.value = (res.data.guests ?? []).map((g: any) => ({ name: g.full_name, data: g.render }))
      step.value = res.data.guest_count > 0 ? 2 : 1
      await loadSample()
    } catch {
      toast.error('That badge could not be opened.')
    }
  }
})

watch(() => batch.value?.guest_type, (v) => { if (v) loadSample() })
</script>

<template>
  <div class="max-w-275">
    <button class="btn ghost mb-4" @click="navigateTo(`/org/events/${id}/onsite/badge-templates`)">
      <AppIcon name="arrow-right" class="w-3.5 h-3.5 rotate-180" />
      Back
    </button>

    <div class="card p-0 overflow-hidden">
      <!-- Header + stepper -->
      <div class="px-6 pt-6 pb-4 border-b border-line">
        <h2 class="section-title m-0">Badge creation</h2>
        <p class="muted text-[.86rem] mt-1 mb-5">{{ batch?.name || 'Guest badges' }}</p>

        <ol class="flex items-center gap-1 flex-wrap m-0 p-0 list-none">
          <li v-for="(label, i) in STEPS" :key="label" class="flex items-center gap-1">
            <span
              class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-[.85rem] font-medium border"
              :class="i === step
                ? 'border-brand text-brand bg-[#f3f0ff]'
                : i < step ? 'border-transparent bg-[#f1f1f5] text-ink' : 'border-transparent bg-[#f7f7fa] text-faint'"
            >
              <span
                class="w-5.5 h-5.5 rounded-full grid place-items-center text-[.72rem] font-bold"
                :class="i <= step ? 'bg-brand text-white' : 'bg-[#e3e3ea] text-muted'"
              >{{ i + 1 }}</span>
              {{ label }}
            </span>
            <span v-if="i < STEPS.length - 1" class="w-6 border-t border-dotted border-line" />
          </li>
        </ol>
      </div>

      <div class="px-6 py-6">
        <!-- ── Step 1: batch details ── -->
        <template v-if="step === 0">
          <div class="max-w-125 flex flex-col gap-4">
            <AppInput v-model="form.name" label="Badge name" required autofocus placeholder="e.g. Media Passes — Day 1" />
            <AppSelect
              v-model="form.guest_type"
              label="Guest type"
              hint="Printed on the badge, and what tells one guest design from another."
              :options="GUEST_TYPES.map(t => ({ value: t, label: t }))"
            />
          </div>
        </template>

        <!-- ── Step 2: guest list ── -->
        <template v-else-if="step === 1">
          <template v-if="!guests.length">
            <p class="font-semibold text-ink m-0 mb-3">Upload guest list</p>
            <div
              class="rounded-xl border-2 border-dashed px-5 py-11 text-center cursor-pointer transition-colors"
              :class="dragging ? 'border-brand bg-[#f3f0ff]' : 'border-line hover:border-brand'"
              @click="fileInput?.click()"
              @dragover.prevent="dragging = true"
              @dragleave.prevent="dragging = false"
              @drop.prevent="onDrop"
            >
              <svg class="w-8 h-8 mx-auto mb-3 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 16V4m0 0L8 8m4-4 4 4" /><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
              </svg>
              <p class="m-0 font-semibold text-ink">
                Drag &amp; drop spreadsheet here or <span class="text-brand underline">Browse</span>
              </p>
              <p class="muted text-[.8rem] m-0 mt-1">.CSV | Max 500 rows</p>
              <input ref="fileInput" type="file" accept=".csv,text/csv" class="hidden" @change="onFile">
            </div>

            <div class="flex items-start justify-between gap-4 mt-4 flex-wrap">
              <div>
                <p class="text-[.84rem] font-semibold text-ink m-0">Required columns in your file:</p>
                <p class="muted text-[.82rem] m-0">Full name, Designation, Organisation, Email, Phone (optional), Photo URL (optional)</p>
              </div>
              <button class="text-brand text-[.84rem] font-semibold bg-transparent border-0 cursor-pointer hover:underline" @click="downloadSample">
                Download sample file
              </button>
            </div>

            <p v-if="parseError" class="error mt-3">{{ parseError }}</p>
          </template>

          <template v-else>
            <div class="flex items-center justify-between mb-3">
              <p class="font-semibold text-ink m-0">Guest data preview</p>
              <span class="muted text-[.84rem]">{{ guests.length }} rows imported</span>
            </div>

            <div class="rounded-xl border border-line overflow-hidden overflow-x-auto">
              <table class="w-full border-collapse text-[.86rem]">
                <thead>
                  <tr class="bg-[#f7f7fa] text-left">
                    <th class="px-4 py-3 font-semibold text-ink">Name</th>
                    <th class="px-4 py-3 font-semibold text-ink">Email</th>
                    <th class="px-4 py-3 font-semibold text-ink">Designation</th>
                    <th class="px-4 py-3 font-semibold text-ink">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(g, i) in guests" :key="i" class="border-t border-line">
                    <td class="px-4 py-3 text-ink">{{ g.full_name || '—' }}</td>
                    <td class="px-4 py-3 text-muted">{{ g.email || '—' }}</td>
                    <td class="px-4 py-3 text-muted">
                      {{ [g.designation, g.company].filter(Boolean).join(' at ') || '—' }}
                    </td>
                    <td class="px-4 py-3">
                      <span v-if="g.error" class="badge draft">{{ g.error }}</span>
                      <span v-else class="badge">Valid</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <button class="text-brand text-[.84rem] font-semibold bg-transparent border-0 cursor-pointer hover:underline mt-3" @click="guests = []">
              Upload a different file
            </button>
          </template>
        </template>

        <!-- ── Step 3: design ── -->
        <template v-else-if="step === 2">
          <p class="font-semibold text-ink m-0 mb-3">Badge preview</p>
          <div class="inline-flex flex-col items-center gap-3">
            <div class="border border-line rounded-xl overflow-hidden shadow-sm">
              <BadgePreview
                v-if="batch?.design"
                :badge-json="batch.design.badge_json"
                :data="renders[0]?.data ?? sampleData"
                :max-width="240"
                :max-height="340"
              />
            </div>
            <button class="btn ghost" @click="editDesign">
              <AppIcon name="pencil" class="w-3.5 h-3.5" />
              Edit
            </button>
          </div>
          <p class="muted text-[.84rem] mt-4 mb-0">
            Showing {{ renders.length ? `${renders[0]?.name}'s` : 'a sample' }} badge.
            All {{ batch?.guest_count ?? 0 }} guests print on this design.
          </p>
        </template>

        <!-- ── Step 4: delivery ── -->
        <template v-else>
          <p class="font-semibold text-ink m-0 mb-3">Choose delivery method</p>
          <div class="flex gap-3 flex-wrap">
            <button
              v-for="opt in [
                { key: 'print', label: 'Print Now', icon: 'M6 9V3h12v6M6 18H4v-6h16v6h-2M8 14h8v7H8z' },
                { key: 'email', label: 'Email PDF', icon: 'M3 6h18v12H3zM3 7l9 6 9-6' },
                { key: 'qr', label: 'Digital QR', icon: 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 14h2v2h-2zM14 18h2v2h-2zM18 18h2v2h-2z' },
              ]"
              :key="opt.key"
              class="w-49 px-4 py-6 rounded-xl border bg-white cursor-pointer flex flex-col items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
              :class="method === opt.key ? 'border-brand bg-[#f8f7ff]' : 'border-line hover:border-brand'"
              :disabled="opt.key === 'email'"
              :title="opt.key === 'email' ? 'Emailing badges is not available yet' : undefined"
              @click="method = opt.key as any"
            >
              <svg class="w-6 h-6 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path :d="opt.icon" />
              </svg>
              <span class="text-[.88rem] font-medium text-ink">{{ opt.label }}</span>
              <span v-if="opt.key === 'email'" class="text-[.72rem] text-faint">Coming soon</span>
            </button>
          </div>

          <div class="mt-4 inline-flex items-center gap-2 px-4 py-3 rounded-lg bg-[#f7f7fa] text-[.84rem] text-muted">
            <AppIcon name="users" class="w-4 h-4 shrink-0" />
            <span v-if="method === 'print'">
              {{ batch?.guest_count ?? 0 }} badges will be sent to the printer in one job.
            </span>
            <span v-else>
              {{ batch?.guest_count ?? 0 }} guests will see their badge in the event app under My Badges.
            </span>
          </div>

          <p v-if="batch?.delivery" class="muted text-[.82rem] mt-3 mb-0">
            Last delivered by {{ batch.delivery.method }} on
            {{ new Date(batch.delivery.at).toLocaleString() }}.
          </p>
        </template>
      </div>

      <!-- Footer nav -->
      <div class="px-6 py-4 border-t border-line flex items-center justify-between">
        <button class="btn ghost" :disabled="step === 0" @click="step--">Back</button>

        <button v-if="step === 0" class="btn" :disabled="creating || !form.name.trim()" @click="createBatch">
          {{ creating ? 'Creating…' : 'Next' }}
        </button>
        <button v-else-if="step === 1" class="btn" :disabled="importing || !validGuests.length" @click="commitGuests">
          {{ importing ? 'Adding…' : 'Next' }}
        </button>
        <button v-else-if="step === 2" class="btn" @click="step = 3">Create Badge</button>
        <button v-else class="btn" :disabled="delivering || !batch?.guest_count" @click="deliver">
          {{ delivering ? 'Working…' : method === 'print' ? 'Print Badge' : 'Publish Badges' }}
        </button>
      </div>
    </div>

    <!-- Print sheet: every guest's badge at authored size, screen-hidden. Built
         only while printing so hundreds of QR codes are not sitting in the DOM
         for the whole session. -->
    <div v-if="printing" class="print-sheet">
      <div v-for="(r, i) in renders" :key="i" class="print-badge">
        <BadgePreview
          :badge-json="batch?.design?.badge_json"
          :data="r.data"
          :max-width="397"
          :max-height="559"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.print-sheet { display: none; }

@media print {
  /*
   * Only the badges reach the paper. `display: none` on the wizard chrome would
   * take the print sheet down with it — the sheet is a descendant of the same
   * page — so hide by visibility instead: an element may re-show itself inside a
   * hidden ancestor, which `display` does not allow.
   */
  :global(body *) { visibility: hidden; }

  .print-sheet,
  .print-sheet :deep(*) { visibility: visible; }

  .print-sheet {
    display: block;
    position: absolute;
    top: 0;
    left: 0;
  }

  .print-badge { break-after: page; }
  .print-badge:last-child { break-after: auto; }
}
</style>
