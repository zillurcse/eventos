<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
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

const TABLE_DESIGNS = ['round', 'boardroom', 'lounge']
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
            design: TABLE_DESIGNS.includes(t.design) ? t.design : 'round',
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
  <div>
    <!-- Networking Lounge -->
    <CommunicationLoungeSettingCard
      title="Networking Lounge" class="mb-4"
      description="Enable lightning fast networking between attendees with the networking lounge."
      :model-value="enabled" @update:model-value="(v: boolean) => { enabled = v; persist() }"
    />

    <!-- Lounge Time Range -->
    <CommunicationLoungeSettingCard
      title="Lounge Time Range" class="mb-4"
      description="Specify the time range between which networking Lounge will be open."
    >
      <button class="btn ghost" @click="slotsOpen = true">MANAGE AVAILABLE SLOTS</button>
    </CommunicationLoungeSettingCard>

    <!-- Attendee Tables -->
    <CommunicationLoungeSettingCard
      title="Attendee Tables" class="mb-4"
      description="Specify table names, discussion topics and capacity of your lounge tables."
      :model-value="attendeeTablesEnabled" @update:model-value="(v: boolean) => { attendeeTablesEnabled = v; persist() }"
    >
      <button class="btn ghost" @click="attendeeOpen = true">MANAGE TABLES</button>
    </CommunicationLoungeSettingCard>

    <!-- Exhibitor Tables -->
    <CommunicationLoungeSettingCard
      title="Exhibitor Tables" class="mb-4"
      description="Enable Exhibitor Members to interact with attendees on their branded tables."
      :model-value="exhibitorTablesEnabled" @update:model-value="(v: boolean) => { exhibitorTablesEnabled = v; persist() }"
    >
      <label class="text-[.82rem] font-semibold text-brand block mb-1">Default meetings count</label>
      <div class="flex items-center gap-2 mb-3">
        <input v-model.number="exhibitorDefaultMeetings" type="number" min="0" class="m-0 w-24">
        <button class="w-8 h-8 rounded-full border border-line bg-white grid place-items-center text-brand hover:bg-brand-soft cursor-pointer" title="Apply" @click="persist()">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
        </button>
      </div>
      <button class="btn ghost" @click="exhibitorOpen = true">MANAGE TABLES</button>
    </CommunicationLoungeSettingCard>

    <!-- Sponsor Tables -->
    <CommunicationLoungeSettingCard
      title="Sponsor Tables" class="mb-5"
      description="Enable Sponsor Members to interact with attendees on their branded tables."
      :model-value="sponsorTablesEnabled" @update:model-value="(v: boolean) => { sponsorTablesEnabled = v; persist() }"
    >
      <label class="text-[.82rem] font-semibold text-brand block mb-1">Default meetings count</label>
      <div class="flex items-center gap-2 mb-3">
        <input v-model.number="sponsorDefaultMeetings" type="number" min="0" class="m-0 w-24">
        <button class="w-8 h-8 rounded-full border border-line bg-white grid place-items-center text-brand hover:bg-brand-soft cursor-pointer" title="Apply" @click="persist()">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
        </button>
      </div>
      <button class="btn ghost" @click="sponsorOpen = true">MANAGE TABLES</button>
    </CommunicationLoungeSettingCard>

    <!-- ── Manage Available Slots drawer ── -->
    <CommunicationLoungeSlotsDrawer
      v-if="slotsOpen"
      :event-dates="eventDates" :selected-date="selectedDate" :slots-open-all="slotsOpenAll" :slots="slots" :saving="saving"
      @update:selected-date="selectedDate = $event" @update:slots-open-all="slotsOpenAll = $event"
      @save="saveSlots" @close="slotsOpen = false"
    />

    <!-- ── Attendee Tables drawer ── -->
    <CommunicationLoungeAttendeeTablesDrawer
      v-if="attendeeOpen" :tables="attendeeTables" :saving="saving"
      @save="saveAttendee" @close="attendeeOpen = false"
    />

    <!-- ── Exhibitor Tables drawer ── -->
    <CommunicationLoungePartnerTablesDrawer
      v-if="exhibitorOpen" title="Exhibitors Tables"
      :items="exhibitorDrag" :meetings="exhibitorMeetings" :default-meetings="exhibitorDefaultMeetings" :saving="saving"
      empty-text="No exhibitors added to this event yet."
      @save="saveExhibitorOrder" @close="exhibitorOpen = false"
    />

    <!-- ── Sponsor Tables drawer ── -->
    <CommunicationLoungePartnerTablesDrawer
      v-if="sponsorOpen" title="Sponsors Tables"
      :items="sponsorDrag" :meetings="sponsorMeetings" :default-meetings="sponsorDefaultMeetings" :saving="saving"
      empty-text="No sponsors added to this event yet."
      @save="saveSponsorOrder" @close="sponsorOpen = false"
    />
  </div>
</template>
