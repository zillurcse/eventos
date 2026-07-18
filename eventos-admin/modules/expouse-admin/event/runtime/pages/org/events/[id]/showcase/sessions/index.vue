<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route  = useRoute()
const router = useRouter()
const api    = useApi()
const id     = route.params.id as string

// ── Types ─────────────────────────────────────────────────────────────────────

interface Track { id: number; name: string; color: string }
interface SessionSpeaker { id: string; name: string; image_url?: string | null }

interface Session {
  id: string
  title: string
  description: string | null
  starts_at: string | null
  ends_at: string | null
  timezone: string | null
  status: 'scheduled' | 'live' | 'ended' | 'canceled'
  capacity: number | null
  stream_url: string | null
  session_place: string | null
  logo_url: string | null
  icon_url: string | null
  tags: string[]
  is_featured: boolean
  is_allowed_to_rate: boolean
  track: Track | null
  speakers: SessionSpeaker[]
}

// ── State ─────────────────────────────────────────────────────────────────────

const event         = ref<any>(null)
const sessions       = ref<Session[]>([])
const search         = ref('')
const drawerOpen     = ref(false)
const showOpenSlots  = ref(false)
const selectedDay    = ref('')
const slotOpenTime   = ref<string | undefined>(undefined)

// ── Helpers ───────────────────────────────────────────────────────────────────

const eventTz = computed(() => event.value?.resolved_timezone || event.value?.timezone || 'UTC')

function nextDateStr(dateStr: string): string {
  const [y, m, d] = dateStr.split('-').map(Number)
  return new Date(Date.UTC(y, m - 1, d + 1)).toISOString().slice(0, 10)
}

function ordinal(n: number): string {
  if (n % 10 === 1 && n % 100 !== 11) return `${n}st`
  if (n % 10 === 2 && n % 100 !== 12) return `${n}nd`
  if (n % 10 === 3 && n % 100 !== 13) return `${n}rd`
  return `${n}th`
}

// ── Day tabs (derived from the event's date range) ─────────────────────────────

const dayOptions = computed<{ value: string, shortLabel: string, fullLabel: string }[]>(() => {
  if (!event.value?.starts_at) return []
  const tz    = eventTz.value
  const start = tzDateInput(event.value.starts_at, tz)
  const end   = event.value.ends_at ? tzDateInput(event.value.ends_at, tz) : start
  if (!start) return []

  const out: { value: string, shortLabel: string, fullLabel: string }[] = []
  let cursor = start
  let guard = 0
  while (cursor <= end && guard++ < 366) {
    const d = new Date(`${cursor}T12:00:00Z`)
    const monthDay = d.toLocaleDateString([], { month: 'long', day: 'numeric', timeZone: 'UTC' })
    const dayNum = d.getUTCDate()
    out.push({
      value: cursor,
      shortLabel: `Day ${guard}`,
      fullLabel: `Day ${guard} - ${monthDay.replace(String(dayNum), ordinal(dayNum))}`,
    })
    cursor = nextDateStr(cursor)
  }
  return out
})

// ── Grouped / slot rows for the selected day ────────────────────────────────────

interface Row { key: string; timeLabel: string; sessions: Session[] }

function fmtSlotLabel(time: string): string {
  return new Date(`2000-01-01T${time}:00`).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
}

const daySessions = computed(() => {
  const q = search.value.toLowerCase()
  return sessions.value.filter((s) => {
    if (!s.starts_at) return false
    if (selectedDay.value && tzDateInput(s.starts_at, eventTz.value) !== selectedDay.value) return false
    if (q && !s.title.toLowerCase().includes(q)) return false
    return true
  })
})

const rows = computed<Row[]>(() => {
  const byTime = new Map<string, Session[]>()
  for (const s of daySessions.value) {
    const key = tzTimeInput(s.starts_at, eventTz.value)
    if (!byTime.has(key)) byTime.set(key, [])
    byTime.get(key)!.push(s)
  }

  let times = [...byTime.keys()]

  if (showOpenSlots.value) {
    const hours = times.map(t => Number(t.slice(0, 2)))
    const lo = Math.min(8, ...hours)
    const hi = Math.max(20, ...hours)
    for (let h = lo; h <= hi; h++) {
      const key = `${String(h).padStart(2, '0')}:00`
      if (!byTime.has(key)) byTime.set(key, [])
    }
    times = [...byTime.keys()]
  }

  return times
    .sort()
    .map(key => ({
      key,
      timeLabel: fmtSlotLabel(key),
      sessions: (byTime.get(key) || []).sort((a, b) => (a.starts_at ?? '').localeCompare(b.starts_at ?? '')),
    }))
})

// ── API ───────────────────────────────────────────────────────────────────────

async function load() {
  try {
    const [evtRes, sesRes] = await Promise.all([
      api<any>(`/events/${id}`),
      api<any>(`/sessions?event=${id}`),
    ])
    event.value = evtRes.data
    sessions.value = sesRes.data
    if (!selectedDay.value && dayOptions.value.length) {
      const today = tzDateInput(new Date().toISOString(), eventTz.value)
      const match = dayOptions.value.find(d => d.value === today)
      selectedDay.value = match ? match.value : dayOptions.value[0].value
    }
  } catch { /* */ }
}

function editSession(s: Session) {
  router.push(`/org/events/${id}/showcase/sessions/${s.id}`)
}

async function toggleStatus(s: Session) {
  const next: Session['status'] =
    s.status === 'scheduled' ? 'live'
    : s.status === 'live'    ? 'ended'
    : 'scheduled'
  try {
    const res = await api<any>(`/sessions/${s.id}`, { method: 'PUT', body: { status: next } })
    const idx = sessions.value.findIndex(x => x.id === s.id)
    if (idx >= 0) sessions.value[idx] = res.data
  } catch { /* */ }
}

async function removeSession(s: Session) {
  try {
    await api(`/sessions/${s.id}`, { method: 'DELETE' })
    sessions.value = sessions.value.filter(x => x.id !== s.id)
  } catch { /* */ }
}

function openDrawer(timeKey?: string) {
  slotOpenTime.value = timeKey
  drawerOpen.value = true
}

function onCreated(session: Session) {
  sessions.value.push(session)
  drawerOpen.value = false
}

function goManageDays() {
  router.push(`/org/events/${id}/details`)
}

onMounted(load)
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-4">
      <h2 class="section-title m-0">Sessions</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Event sessions and any live schedule.</p>
    </div>

    <!-- Toolbar -->
    <div class="flex items-center gap-3 mb-5 flex-wrap">
      <input v-model="search" placeholder="Search sessions…" class="m-0 max-w-[260px]">
      <div class="flex-1" />
      <button class="btn ghost" @click="goManageDays">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        Manage Days
      </button>
      <button class="btn" @click="openDrawer()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        Add Session
      </button>
    </div>

    <!-- Day tabs + Show Open Slots -->
    <div v-if="dayOptions.length" class="flex items-center justify-between gap-4 mb-5 flex-wrap">
      <div class="inline-flex items-center gap-1 bg-white border border-line rounded-xl p-1">
        <button
          v-for="d in dayOptions" :key="d.value"
          class="px-4 py-2 rounded-lg text-[.85rem] font-semibold whitespace-nowrap transition-colors"
          :class="selectedDay === d.value ? 'bg-brand text-white' : 'text-ink hover:bg-[#f6f7f9]'"
          @click="selectedDay = d.value"
        >{{ selectedDay === d.value ? d.fullLabel : d.shortLabel }}</button>
      </div>

      <button
        type="button"
        class="flex items-center gap-2 bg-transparent border-0 cursor-pointer"
        @click="showOpenSlots = !showOpenSlots"
      >
        <span class="text-[.85rem] font-medium text-ink">Show Open Slots</span>
        <span class="text-muted" title="Show empty time slots so you can quickly add a session at that time.">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
        </span>
        <span class="relative w-9 h-5 rounded-full shrink-0 transition-colors duration-150" :class="showOpenSlots ? 'bg-brand' : 'bg-[#cdd2dc]'">
          <i class="absolute top-[3px] left-[3px] w-3.5 h-3.5 rounded-full bg-white transition-transform duration-150 shadow-sm" :class="showOpenSlots ? 'translate-x-[16px]' : 'translate-x-0'" />
        </span>
      </button>
    </div>

    <!-- Empty state -->
    <div v-if="!sessions.length && !search" class="card text-center py-12 muted">
      No sessions yet. Click <strong>+ Add Session</strong> to add one.
    </div>

    <!-- Timeline -->
    <div v-else class="card">
      <div v-if="!rows.length" class="text-center py-10 muted">
        No sessions scheduled for this day. Toggle <strong>Show Open Slots</strong> to add one.
      </div>
      <SessionTimelineGrid
        v-else
        :rows="rows"
        @edit="editSession"
        @toggle-status="toggleStatus"
        @remove="removeSession"
        @add="openDrawer"
      />
    </div>

    <SessionFormDrawer
      v-if="drawerOpen"
      :event-id="id"
      :initial-date="selectedDay"
      :initial-time="slotOpenTime"
      @close="drawerOpen = false"
      @created="onCreated"
    />
  </div>
</template>
