<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const { upload } = useUpload()
const id = route.params.id as string

interface AttendeeTable {
  id: string
  name: string
  capacity: number
  image_file_id: number | null
  image_url: string | null
  design: string          // round | boardroom | lounge
  accent: string | null   // hex accent, null = event brand color
}

const TABLE_DESIGNS = [
  { key: 'round', label: 'Round' },
  { key: 'boardroom', label: 'Boardroom' },
  { key: 'lounge', label: 'Lounge' },
]
interface Partner { id: string, type: string, name: string, logo_url: string | null }

// ── Lounge config state (mirrors the `lounge` jsonb on event_settings) ──
const enabled = ref(false)
const slotsOpenAll = ref(false)
const slots = reactive<Record<string, string[]>>({})
const attendeeTablesEnabled = ref(false)
const attendeeTables = ref<AttendeeTable[]>([])
const exhibitorTablesEnabled = ref(false)
const exhibitorDefaultMeetings = ref(3)
const exhibitorMeetings = reactive<Record<string, number>>({})
const exhibitorOrder = ref<string[]>([])
const sponsorTablesEnabled = ref(false)
const sponsorDefaultMeetings = ref(10)
const sponsorMeetings = reactive<Record<string, number>>({})
const sponsorOrder = ref<string[]>([])

const partners = ref<Partner[]>([])
const eventDates = ref<string[]>([])
const saving = ref(false)

// ── Drawer state ───────────────────────────────────────────────────────
const slotsOpen = ref(false)
const attendeeOpen = ref(false)
const exhibitorOpen = ref(false)
const sponsorOpen = ref(false)
const selectedDate = ref('')

const HOURS = ['10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00']

function hourLabel(h: string): string {
  const n = Number(h.split(':')[0])
  const ampm = n >= 12 ? 'PM' : 'AM'
  const h12 = n % 12 === 0 ? 12 : n % 12
  return `${String(h12).padStart(2, '0')} ${ampm}`
}

// Exhibitor / sponsor partners ordered by the saved order, new ones appended.
function orderedPartners(type: 'exhibitor' | 'sponsor'): Partner[] {
  const order = type === 'exhibitor' ? exhibitorOrder.value : sponsorOrder.value
  const pool = partners.value.filter((p: Partner) => p.type === type)
  const byId = new Map<string, Partner>(pool.map((p: Partner): [string, Partner] => [p.id, p]))
  const result: Partner[] = []
  for (const pid of order) { const p = byId.get(pid); if (p) { result.push(p); byId.delete(pid) } }
  for (const p of byId.values()) result.push(p)
  return result
}
const exhibitorList = computed(() => orderedPartners('exhibitor'))
const sponsorList = computed(() => orderedPartners('sponsor'))

function meetingsFor(type: 'exhibitor' | 'sponsor', pid: string): number {
  const map = type === 'exhibitor' ? exhibitorMeetings : sponsorMeetings
  const def = type === 'exhibitor' ? exhibitorDefaultMeetings.value : sponsorDefaultMeetings.value
  return Number.isFinite(map[pid]) ? map[pid] : def
}
function setMeetings(type: 'exhibitor' | 'sponsor', pid: string, val: number) {
  const map = type === 'exhibitor' ? exhibitorMeetings : sponsorMeetings
  map[pid] = Math.max(0, Math.trunc(Number(val) || 0))
}

// ── Load ───────────────────────────────────────────────────────────────
function buildDateRange(startIso: string | null, endIso: string | null): string[] {
  if (!startIso) return []
  const start = new Date(startIso)
  const end = endIso ? new Date(endIso) : start
  const out: string[] = []
  const d = new Date(start.getFullYear(), start.getMonth(), start.getDate())
  const last = new Date(end.getFullYear(), end.getMonth(), end.getDate())
  let guard = 0
  while (d <= last && guard++ < 60) {
    out.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`)
    d.setDate(d.getDate() + 1)
  }
  return out
}

function fmtDateTab(iso: string): string {
  const [y, m, dd] = iso.split('-').map(Number)
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  return `${String(dd).padStart(2, '0')} ${months[m - 1]} ${y}`
}

async function load() {
  try {
    const [ev, settings, prt] = await Promise.all([
      api<any>(`/events/${id}`),
      api<any>(`/events/${id}/settings`),
      api<any>(`/exhibitors?event=${id}`),
    ])

    eventDates.value = buildDateRange(ev.data?.starts_at, ev.data?.ends_at)
    selectedDate.value = eventDates.value[0] || ''
    partners.value = (prt.data || []).map((p: any) => ({ id: p.id, type: p.type, name: p.name, logo_url: p.logo_url }))

    const l = settings.data?.lounge || {}
    enabled.value = !!l.enabled
    slotsOpenAll.value = !!l.slots_open_all
    Object.assign(slots, l.slots || {})
    attendeeTablesEnabled.value = !!l.attendee_tables_enabled
    attendeeTables.value = (l.attendee_tables || []).map((t: any) => ({
      id: t.id, name: t.name || '', capacity: t.capacity ?? 4, image_file_id: t.image_file_id ?? null, image_url: t.image_url ?? null,
      design: t.design || 'round', accent: t.accent ?? null,
    }))
    exhibitorTablesEnabled.value = !!l.exhibitor_tables_enabled
    exhibitorDefaultMeetings.value = Number.isFinite(l.exhibitor_default_meetings) ? l.exhibitor_default_meetings : 3
    Object.assign(exhibitorMeetings, l.exhibitor_meetings || {})
    exhibitorOrder.value = l.exhibitor_order || []
    sponsorTablesEnabled.value = !!l.sponsor_tables_enabled
    sponsorDefaultMeetings.value = Number.isFinite(l.sponsor_default_meetings) ? l.sponsor_default_meetings : 10
    Object.assign(sponsorMeetings, l.sponsor_meetings || {})
    sponsorOrder.value = l.sponsor_order || []
  } catch { /* */ }
}

// ── Persist the whole lounge config ────────────────────────────────────
async function persist() {
  saving.value = true
  try {
    await api(`/events/${id}/settings`, {
      method: 'PUT',
      body: {
        lounge: {
          enabled: enabled.value,
          slots_open_all: slotsOpenAll.value,
          slots: JSON.parse(JSON.stringify(slots)),
          attendee_tables_enabled: attendeeTablesEnabled.value,
          attendee_tables: attendeeTables.value.map((t: AttendeeTable) => ({
            id: t.id,
            name: t.name.trim(),
            capacity: Math.max(0, Math.trunc(Number(t.capacity) || 0)),
            image_file_id: t.image_file_id,
            image_url: t.image_url,
            design: TABLE_DESIGNS.some(d => d.key === t.design) ? t.design : 'round',
            accent: t.accent || null,
          })),
          exhibitor_tables_enabled: exhibitorTablesEnabled.value,
          exhibitor_default_meetings: Math.max(0, Math.trunc(Number(exhibitorDefaultMeetings.value) || 0)),
          exhibitor_meetings: JSON.parse(JSON.stringify(exhibitorMeetings)),
          exhibitor_order: exhibitorList.value.map((p: Partner) => p.id),
          sponsor_tables_enabled: sponsorTablesEnabled.value,
          sponsor_default_meetings: Math.max(0, Math.trunc(Number(sponsorDefaultMeetings.value) || 0)),
          sponsor_meetings: JSON.parse(JSON.stringify(sponsorMeetings)),
          sponsor_order: sponsorList.value.map((p: Partner) => p.id),
        },
      },
    })
  } finally {
    saving.value = false
  }
}

// ── Slots drawer ───────────────────────────────────────────────────────
function slotsForDate(): string[] {
  const key = selectedDate.value
  if (!slots[key]) slots[key] = []
  return slots[key] as string[]
}
function addSlot(hour: string) {
  const h = Number(hour.split(':')[0] ?? 0)
  const pad = (n: number) => String(n).padStart(2, '0')
  const firstHalf = `${pad(h)}:00-${pad(h)}:30`
  const secondHalf = `${pad(h)}:30-${pad(h + 1)}:00`
  const existing = slotsForDate()
  // Add the :00–:30 slot first, then the :30–:00 slot, avoiding duplicates.
  const next = !existing.includes(firstHalf) ? firstHalf : !existing.includes(secondHalf) ? secondHalf : null
  if (next) existing.push(next)
}
function removeSlot(i: number) { slotsForDate().splice(i, 1) }
function slotsAtHour(hour: string): { slot: string, index: number }[] {
  const hh = hour.split(':')[0] ?? ''
  return slotsForDate()
    .map((slot: string, index: number) => ({ slot, index }))
    .filter((s: { slot: string, index: number }) => s.slot.startsWith(hh + ':'))
}

// ── Attendee tables drawer ─────────────────────────────────────────────
function addAttendeeTable() {
  attendeeTables.value.push({ id: 't' + Date.now(), name: 'New table', capacity: 4, image_file_id: null, image_url: null, design: 'round', accent: null })
}
function removeAttendeeTable(i: number) { attendeeTables.value.splice(i, 1) }
async function uploadTableImage(e: Event, t: AttendeeTable) {
  const f = (e.target as HTMLInputElement).files?.[0]
  if (!f) return
  const r = await upload(f, { collection: 'lounge' })
  t.image_file_id = r.id; t.image_url = r.url
}

// ── Generic drag-reorder ───────────────────────────────────────────────
const dragIndex = ref<number | null>(null)
function onDragStart(i: number) { dragIndex.value = i }
function onDragOver(i: number, e: DragEvent, arr: any[]) {
  e.preventDefault()
  if (dragIndex.value === null || dragIndex.value === i) return
  const [moved] = arr.splice(dragIndex.value, 1)
  arr.splice(i, 0, moved)
  dragIndex.value = i
}
function onDragEnd() { dragIndex.value = null }

// Reorder of exhibitor/sponsor lists works on a local copy then writes back order.
const exhibitorDrag = ref<Partner[]>([])
const sponsorDrag = ref<Partner[]>([])
watch(exhibitorOpen, (o: boolean) => { if (o) exhibitorDrag.value = [...exhibitorList.value] })
watch(sponsorOpen, (o: boolean) => { if (o) sponsorDrag.value = [...sponsorList.value] })

async function saveExhibitorOrder() {
  exhibitorOrder.value = exhibitorDrag.value.map((p: Partner) => p.id)
  await persist(); exhibitorOpen.value = false
}
async function saveSponsorOrder() {
  sponsorOrder.value = sponsorDrag.value.map((p: Partner) => p.id)
  await persist(); sponsorOpen.value = false
}

async function saveSlots() { await persist(); slotsOpen.value = false }
async function saveAttendee() { await persist(); attendeeOpen.value = false }

onMounted(load)
</script>

<template>
  <div class="max-w-[1180px]">
    <!-- Networking Lounge -->
    <div class="card mb-4">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h2 class="font-bold text-base text-[#1a1a2e] m-0">Networking Lounge</h2>
          <p class="muted text-[.86rem] mt-1 mb-0">Enable lightning fast networking between attendees with the networking lounge.</p>
        </div>
        <button
          type="button" role="switch" :aria-checked="enabled"
          class="relative w-11 h-6 rounded-full shrink-0 transition-colors duration-150"
          :class="enabled ? 'bg-[#6352e7]' : 'bg-[#d1d5db]'"
          @click="enabled = !enabled; persist()"
        >
          <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform duration-150" :class="enabled ? 'translate-x-5' : ''" />
        </button>
      </div>
    </div>

    <!-- Lounge Time Range -->
    <div class="card mb-4">
      <h3 class="font-bold text-base text-[#1a1a2e] m-0">Lounge Time Range</h3>
      <p class="muted text-[.86rem] mt-1 mb-3">Specify the time range between which networking Lounge will be open.</p>
      <button class="btn ghost" @click="slotsOpen = true">MANAGE AVAILABLE SLOTS</button>
    </div>

    <!-- Attendee Tables -->
    <div class="card mb-4">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h3 class="font-bold text-base text-[#1a1a2e] m-0">Attendee Tables</h3>
          <p class="muted text-[.86rem] mt-1 mb-3">Specify table names, discussion topics and capacity of your lounge tables.</p>
          <button class="btn ghost" @click="attendeeOpen = true">MANAGE TABLES</button>
        </div>
        <button
          type="button" role="switch" :aria-checked="attendeeTablesEnabled"
          class="relative w-11 h-6 rounded-full shrink-0 transition-colors duration-150"
          :class="attendeeTablesEnabled ? 'bg-[#6352e7]' : 'bg-[#d1d5db]'"
          @click="attendeeTablesEnabled = !attendeeTablesEnabled; persist()"
        >
          <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform duration-150" :class="attendeeTablesEnabled ? 'translate-x-5' : ''" />
        </button>
      </div>
    </div>

    <!-- Exhibitor Tables -->
    <div class="card mb-4">
      <div class="flex items-start justify-between gap-4">
        <div class="flex-1">
          <h3 class="font-bold text-base text-[#1a1a2e] m-0">Exhibitor Tables</h3>
          <p class="muted text-[.86rem] mt-1 mb-3">Enable Exhibitor Members to interact with attendees on their branded tables.</p>

          <label class="text-[.82rem] font-semibold text-[#6352e7] block mb-1">Default meetings count</label>
          <div class="flex items-center gap-2 mb-3">
            <input v-model.number="exhibitorDefaultMeetings" type="number" min="0" class="m-0 w-24">
            <button class="w-8 h-8 rounded-full border border-line bg-white grid place-items-center text-[#6352e7] hover:bg-[#f3f0ff] cursor-pointer" title="Apply" @click="persist()">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
            </button>
          </div>

          <button class="btn ghost" @click="exhibitorOpen = true">MANAGE TABLES</button>
        </div>
        <button
          type="button" role="switch" :aria-checked="exhibitorTablesEnabled"
          class="relative w-11 h-6 rounded-full shrink-0 transition-colors duration-150"
          :class="exhibitorTablesEnabled ? 'bg-[#6352e7]' : 'bg-[#d1d5db]'"
          @click="exhibitorTablesEnabled = !exhibitorTablesEnabled; persist()"
        >
          <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform duration-150" :class="exhibitorTablesEnabled ? 'translate-x-5' : ''" />
        </button>
      </div>
    </div>

    <!-- Sponsor Tables -->
    <div class="card mb-5">
      <div class="flex items-start justify-between gap-4">
        <div class="flex-1">
          <h3 class="font-bold text-base text-[#1a1a2e] m-0">Sponsor Tables</h3>
          <p class="muted text-[.86rem] mt-1 mb-3">Enable Sponsor Members to interact with attendees on their branded tables.</p>

          <label class="text-[.82rem] font-semibold text-[#6352e7] block mb-1">Default meetings count</label>
          <div class="flex items-center gap-2 mb-3">
            <input v-model.number="sponsorDefaultMeetings" type="number" min="0" class="m-0 w-24">
            <button class="w-8 h-8 rounded-full border border-line bg-white grid place-items-center text-[#6352e7] hover:bg-[#f3f0ff] cursor-pointer" title="Apply" @click="persist()">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
            </button>
          </div>

          <button class="btn ghost" @click="sponsorOpen = true">MANAGE TABLES</button>
        </div>
        <button
          type="button" role="switch" :aria-checked="sponsorTablesEnabled"
          class="relative w-11 h-6 rounded-full shrink-0 transition-colors duration-150"
          :class="sponsorTablesEnabled ? 'bg-[#6352e7]' : 'bg-[#d1d5db]'"
          @click="sponsorTablesEnabled = !sponsorTablesEnabled; persist()"
        >
          <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform duration-150" :class="sponsorTablesEnabled ? 'translate-x-5' : ''" />
        </button>
      </div>
    </div>

    <!-- ── Manage Available Slots drawer ── -->
    <Drawer v-if="slotsOpen" title="Manage Available Slots" @close="slotsOpen = false">
      <div v-if="eventDates.length" class="flex gap-2 overflow-x-auto pb-2 mb-4">
        <button
          v-for="d in eventDates" :key="d"
          class="px-4 py-2.5 rounded-lg border text-[.84rem] font-bold whitespace-nowrap transition-colors"
          :class="selectedDate === d ? 'border-[#6352e7] text-[#6352e7] bg-[#f3f0ff]' : 'border-line text-muted bg-white hover:border-[#6352e7]'"
          @click="selectedDate = d"
        >{{ fmtDateTab(d) }}</button>
      </div>
      <p v-else class="muted text-[.84rem] mb-4">Set the event start &amp; end dates first to manage slots.</p>

      <label class="flex items-center gap-2 text-[.88rem] text-ink m-0 mb-4 cursor-pointer">
        <input v-model="slotsOpenAll" type="checkbox" class="w-4 h-4 m-0 accent-[#6352e7]">
        Open all meeting slot
      </label>

      <div v-if="selectedDate && !slotsOpenAll" class="flex">
        <div class="w-16 shrink-0">
          <div v-for="h in HOURS" :key="h" class="h-14 text-[.8rem] font-semibold text-muted">{{ hourLabel(h) }}</div>
        </div>
        <div class="flex-1 border-l border-line pl-3">
          <div v-for="h in HOURS" :key="h" class="min-h-14 py-1 flex flex-wrap gap-1.5 content-start">
            <span
              v-for="s in slotsAtHour(h)" :key="s.index"
              class="inline-flex items-center gap-1 bg-[#f3f0ff] text-[#6352e7] text-[.82rem] font-semibold px-2.5 py-1 rounded-md"
            >
              {{ s.slot }}
              <button class="text-[#6352e7] font-bold leading-none cursor-pointer bg-transparent border-0 p-0" @click="removeSlot(s.index)">×</button>
            </span>
            <button class="text-[.78rem] text-[#9aa0ad] hover:text-[#6352e7] cursor-pointer bg-transparent border-0 px-1.5 py-1" title="Add slot" @click="addSlot(h)">+ add</button>
          </div>
        </div>
      </div>
      <p v-else-if="slotsOpenAll" class="muted text-[.84rem]">All meeting slots are open for the selected days.</p>

      <div class="modal-actions">
        <button class="btn" :disabled="saving" @click="saveSlots">{{ saving ? 'Saving…' : 'SAVE' }}</button>
      </div>
    </Drawer>

    <!-- ── Attendee Tables drawer ── -->
    <Drawer v-if="attendeeOpen" title="Attendee Tables" @close="attendeeOpen = false">
      <div class="flex justify-end -mt-2 mb-3">
        <button class="text-[#6352e7] font-semibold text-[.88rem] bg-transparent border-0 cursor-pointer" @click="addAttendeeTable">+ Add Table</button>
      </div>

      <div class="flex flex-col gap-2.5">
        <div
          v-for="(t, i) in attendeeTables" :key="t.id"
          class="flex items-center gap-3 border border-line rounded-xl p-3 bg-white"
          :class="{ 'opacity-50 border-[#6352e7]': dragIndex === i }"
          draggable="true" @dragstart="onDragStart(i)" @dragover="onDragOver(i, $event, attendeeTables)" @dragend="onDragEnd"
        >
          <span class="cursor-grab text-[#b8bcc6] select-none">⠿</span>
          <label class="w-12 h-12 rounded-lg overflow-hidden bg-[#f3f4f6] border border-line grid place-items-center cursor-pointer shrink-0">
            <img v-if="t.image_url" :src="t.image_url" alt="" class="w-full h-full object-cover">
            <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#9aa0ad" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
            <input type="file" accept="image/*" class="hidden" @change="uploadTableImage($event, t)">
          </label>
          <div class="flex-1 flex flex-col gap-2">
            <input v-model="t.name" class="m-0" placeholder="Table name">
            <div class="flex items-center gap-2 flex-wrap">
              <input v-model.number="t.capacity" type="number" min="0" class="m-0 w-20">
              <span class="text-[.82rem] text-muted">Chairs</span>
              <span class="text-[.82rem] text-[#d1d5db]">·</span>
              <div class="inline-flex rounded-lg border border-line overflow-hidden">
                <button
                  v-for="d in TABLE_DESIGNS" :key="d.key" type="button"
                  class="px-2.5 py-1 text-[.76rem] font-semibold cursor-pointer transition-colors"
                  :class="t.design === d.key ? 'bg-[#6352e7] text-white' : 'bg-white text-muted hover:bg-[#f3f0ff]'"
                  @click="t.design = d.key"
                >{{ d.label }}</button>
              </div>
              <input
                :value="t.accent || '#6352e7'" type="color" title="Accent color (clear to use event brand)"
                class="w-7 h-7 rounded-md border border-line cursor-pointer p-0 bg-transparent"
                @input="t.accent = ($event.target as HTMLInputElement).value"
              >
              <button v-if="t.accent" type="button" class="text-[.72rem] text-[#9aa0ad] hover:text-[#dc2626] cursor-pointer bg-transparent border-0" @click="t.accent = null">clear</button>
            </div>
          </div>
          <button class="text-[#dc2626] bg-transparent border-0 cursor-pointer p-1 self-start" title="Remove" @click="removeAttendeeTable(i)">🗑</button>
        </div>
      </div>
      <p v-if="!attendeeTables.length" class="muted text-[.84rem] py-6 text-center">No tables yet. Click <strong>+ Add Table</strong>.</p>

      <div class="modal-actions">
        <button class="btn" :disabled="saving" @click="saveAttendee">{{ saving ? 'Saving…' : 'SAVE' }}</button>
      </div>
    </Drawer>

    <!-- ── Exhibitor Tables drawer ── -->
    <Drawer v-if="exhibitorOpen" title="Exhibitors Tables" @close="exhibitorOpen = false">
      <div class="flex flex-col gap-2.5">
        <div
          v-for="(p, i) in exhibitorDrag" :key="p.id"
          class="flex items-center gap-3 border border-line rounded-xl p-3 bg-white"
          :class="{ 'opacity-50 border-[#6352e7]': dragIndex === i }"
          draggable="true" @dragstart="onDragStart(i)" @dragover="onDragOver(i, $event, exhibitorDrag)" @dragend="onDragEnd"
        >
          <span class="cursor-grab text-[#b8bcc6] select-none">⠿</span>
          <div class="w-11 h-11 rounded-lg overflow-hidden bg-[#f3f4f6] border border-line grid place-items-center shrink-0">
            <img v-if="p.logo_url" :src="p.logo_url" :alt="p.name" class="w-full h-full object-contain">
            <span v-else class="text-[.7rem] font-bold text-muted uppercase">{{ p.name.slice(0, 2) }}</span>
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-bold text-[.9rem] text-ink truncate">{{ p.name }}</div>
            <div class="flex items-center gap-1.5 mt-1">
              <input
                :value="meetingsFor('exhibitor', p.id)" type="number" min="0" class="m-0 w-20 py-1"
                @input="setMeetings('exhibitor', p.id, ($event.target as HTMLInputElement).valueAsNumber)"
              >
              <span class="text-[.82rem] text-muted">Meetings</span>
            </div>
          </div>
        </div>
      </div>
      <p v-if="!exhibitorDrag.length" class="muted text-[.84rem] py-6 text-center">No exhibitors added to this event yet.</p>

      <div class="modal-actions">
        <button class="btn" :disabled="saving" @click="saveExhibitorOrder">{{ saving ? 'Saving…' : 'SAVE' }}</button>
      </div>
    </Drawer>

    <!-- ── Sponsor Tables drawer ── -->
    <Drawer v-if="sponsorOpen" title="Sponsors Tables" @close="sponsorOpen = false">
      <div class="flex flex-col gap-2.5">
        <div
          v-for="(p, i) in sponsorDrag" :key="p.id"
          class="flex items-center gap-3 border border-line rounded-xl p-3 bg-white"
          :class="{ 'opacity-50 border-[#6352e7]': dragIndex === i }"
          draggable="true" @dragstart="onDragStart(i)" @dragover="onDragOver(i, $event, sponsorDrag)" @dragend="onDragEnd"
        >
          <span class="cursor-grab text-[#b8bcc6] select-none">⠿</span>
          <div class="w-11 h-11 rounded-lg overflow-hidden bg-[#f3f4f6] border border-line grid place-items-center shrink-0">
            <img v-if="p.logo_url" :src="p.logo_url" :alt="p.name" class="w-full h-full object-contain">
            <span v-else class="text-[.7rem] font-bold text-muted uppercase">{{ p.name.slice(0, 2) }}</span>
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-bold text-[.9rem] text-ink truncate">{{ p.name }}</div>
            <div class="flex items-center gap-1.5 mt-1">
              <input
                :value="meetingsFor('sponsor', p.id)" type="number" min="0" class="m-0 w-20 py-1"
                @input="setMeetings('sponsor', p.id, ($event.target as HTMLInputElement).valueAsNumber)"
              >
              <span class="text-[.82rem] text-muted">Meetings</span>
            </div>
          </div>
        </div>
      </div>
      <p v-if="!sponsorDrag.length" class="muted text-[.84rem] py-6 text-center">No sponsors added to this event yet.</p>

      <div class="modal-actions">
        <button class="btn" :disabled="saving" @click="saveSponsorOrder">{{ saving ? 'Saving…' : 'SAVE' }}</button>
      </div>
    </Drawer>
  </div>
</template>
