<script setup lang="ts">
/**
 * "My Schedule" section of the Profile page: a single chronological agenda
 * built from things the attendee has actually committed to — bookmarked
 * sessions (from the Sessions tab) and confirmed one-to-one meetings (from
 * Meetings) — grouped by calendar day. Sessions/meetings without a known
 * time land in a trailing "Unscheduled" group instead of being dropped.
 */
import type { JoinConfig } from '~/stores/rooms'
import type { AgendaSession } from '~/stores/sessions'
import type { Meeting } from '~/stores/meetings'

const bookmarks = useBookmarksStore()
const sessions = useSessionsStore()
const meetings = useMeetingsStore()

onMounted(() => {
  bookmarks.fetch()
  if (!sessions.loaded && !sessions.loading) sessions.fetchSessions()
  if (!meetings.loaded && !meetings.loading) meetings.fetchMeetings()
})

const savedSessions = computed(() => sessions.sessions.filter(s => bookmarks.isOn('session', s.id)))
const confirmedMeetings = computed(() => meetings.approved)

/** A confirmed meeting's start, from either a proposed time or a booked lounge slot. */
function meetingAt(m: Meeting): Date | null {
  if (m.starts_at) return new Date(m.starts_at)
  if (m.date && m.slot) {
    const start = m.slot.split('-')[0]?.trim()
    const [hh, mm] = (start || '').split(':').map(Number)
    const [y, mo, d] = m.date.split('-').map(Number)
    if (y && mo && d && hh !== undefined && !Number.isNaN(hh)) return new Date(y, mo - 1, d, hh, mm || 0)
  }
  return null
}

type Entry =
  | { key: string, at: Date | null, title: string, kind: 'session', item: AgendaSession }
  | { key: string, at: Date | null, title: string, kind: 'meeting', item: Meeting }

const entries = computed<Entry[]>(() => [
  ...savedSessions.value.map((s): Entry => ({
    key: `session-${s.id}`, at: s.starts_at ? new Date(s.starts_at) : null, title: s.title, kind: 'session', item: s,
  })),
  ...confirmedMeetings.value.map((m): Entry => ({
    key: `meeting-${m.id}`, at: meetingAt(m), title: m.title || m.counterpart?.name || 'Meeting', kind: 'meeting', item: m,
  })),
])

function dayKey(d: Date) {
  return `${d.getFullYear()}-${`${d.getMonth() + 1}`.padStart(2, '0')}-${`${d.getDate()}`.padStart(2, '0')}`
}
function dayLabel(key: string) {
  const [y, m, d] = key.split('-').map(Number)
  return new Intl.DateTimeFormat(undefined, { weekday: 'long', month: 'short', day: 'numeric' }).format(new Date(y!, m! - 1, d!))
}

const groups = computed(() => {
  const byDay = new Map<string, Entry[]>()
  const unscheduled: Entry[] = []
  for (const e of entries.value) {
    if (!e.at) { unscheduled.push(e); continue }
    const key = dayKey(e.at)
    if (!byDay.has(key)) byDay.set(key, [])
    byDay.get(key)!.push(e)
  }
  const result = Array.from(byDay.keys()).sort().map(key => ({
    key,
    label: dayLabel(key),
    items: byDay.get(key)!.sort((a, b) => (a.at?.getTime() ?? 0) - (b.at?.getTime() ?? 0)),
  }))
  if (unscheduled.length) {
    result.push({ key: 'unscheduled', label: 'Unscheduled', items: unscheduled.sort((a, b) => a.title.localeCompare(b.title)) })
  }
  return result
})

const loading = computed(() =>
  (bookmarks.loading && !bookmarks.loaded) || (sessions.loading && !sessions.loaded) || (meetings.loading && !meetings.loaded))
const empty = computed(() => !loading.value && !groups.value.length)

// Confirmed + running meetings can be joined straight from the agenda.
const active = ref<{ config: JoinConfig, title: string } | null>(null)
function onJoin(cfg: JoinConfig & { title: string }) {
  active.value = { config: cfg, title: cfg.title }
}
</script>

<template>
  <div class="schedule-tab">
    <p v-if="loading" class="state">Loading your schedule…</p>
    <p v-else-if="empty" class="state">
      Nothing on your schedule yet — bookmark sessions from the
      <NuxtLink to="/sessions">agenda</NuxtLink> and confirm requests in
      <NuxtLink to="/meetings">Meetings</NuxtLink> to build it out.
    </p>

    <template v-else>
      <section v-for="g in groups" :key="g.key" class="day">
        <h2 class="day-label">{{ g.label }}</h2>
        <div class="items">
          <template v-for="e in g.items" :key="e.key">
            <SessionsCard v-if="e.kind === 'session'" :session="(e.item as AgendaSession)" :tz="deviceTimezone()" />
            <MeetingsCard v-else :meeting="(e.item as Meeting)" @join="onJoin" />
          </template>
        </div>
      </section>
    </template>

    <RoomsRoomStage v-if="active" :config="active.config" :title="active.title" @leave="active = null" />
  </div>
</template>

<style scoped>
.state { color: #94a3b8; font-size: .9rem; padding: 24px 0; text-align: center; line-height: 1.6; }
.state a { color: var(--brand-primary); font-weight: 600; text-decoration: none; }
.state a:hover { text-decoration: underline; }

.day + .day { margin-top: 26px; }
.day-label { margin: 0 0 12px; font-size: 1rem; font-weight: 800; color: #1e293b; }
.items { display: flex; flex-direction: column; gap: 14px; }
</style>
