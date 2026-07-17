<script setup lang="ts">
import type { AgendaSession } from '~/stores/sessions'

definePageMeta({ layout: 'event', middleware: 'auth' })

const store = useSessionsStore()
const bookmarks = useBookmarksStore()

// ── Filters state ────────────────────────────────────────────────────────
const search = ref('')
const activeTrack = ref<number | 'all'>('all')
const selectedTags = ref<Set<string>>(new Set())
const selectedSpeakers = ref<Set<string>>(new Set())
const selectedDay = ref<string | null>(null)
const savedOnly = ref(false)
const tz = ref('UTC')

onMounted(async () => {
  bookmarks.fetch()
  store.fetchAds()
  await store.fetchSessions()
  tz.value = store.eventTimezone
  buildDays()
  // Default to the event's first day (or today if it's within the range).
  if (days.value.length) {
    const today = dayKey(new Date().toISOString(), tz.value)
    selectedDay.value = days.value.includes(today) ? today : days.value[0]!
  }
})

// ── Timezone options ─────────────────────────────────────────────────────
const tzOptions = computed(() => {
  const base = ['UTC', 'Asia/Dhaka', 'Asia/Riyadh', 'Asia/Dubai', 'Europe/London', 'America/New_York']
  return Array.from(new Set([store.eventTimezone, ...base]))
})

watch(tz, () => { buildDays(); if (days.value.length && !days.value.includes(selectedDay.value || '')) selectedDay.value = days.value[0]! })

// ── Day helpers ──────────────────────────────────────────────────────────
/** Calendar day (YYYY-MM-DD) for an instant, in the chosen timezone. */
function dayKey(iso: string, zone: string) {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: zone, year: 'numeric', month: '2-digit', day: '2-digit',
  }).format(new Date(iso))
}

const days = ref<string[]>([])

function buildDays() {
  const ev = store.data?.event
  const start = ev?.starts_at, end = ev?.ends_at
  const keys: string[] = []
  if (start && end) {
    const [sy, sm, sd] = dayKey(start, tz.value).split('-').map(Number)
    const [ey, em, ed] = dayKey(end, tz.value).split('-').map(Number)
    const cur = new Date(Date.UTC(sy!, sm! - 1, sd!))
    const last = new Date(Date.UTC(ey!, em! - 1, ed!))
    while (cur <= last && keys.length < 60) {
      keys.push(cur.toISOString().slice(0, 10))
      cur.setUTCDate(cur.getUTCDate() + 1)
    }
  } else {
    // No event range → derive distinct days from the sessions themselves.
    const set = new Set<string>()
    for (const s of store.sessions) if (s.starts_at) set.add(dayKey(s.starts_at, tz.value))
    keys.push(...Array.from(set).sort())
  }
  days.value = keys
}

function dayLabel(key: string, part: 'day' | 'weekday') {
  const [y, m, d] = key.split('-').map(Number)
  const date = new Date(Date.UTC(y!, m! - 1, d!, 12))
  if (part === 'weekday') return new Intl.DateTimeFormat('en-US', { timeZone: 'UTC', weekday: 'long' }).format(date)
  return new Intl.DateTimeFormat('en-US', { timeZone: 'UTC', month: 'short', day: 'numeric' }).format(date)
}

// ── Filtering ────────────────────────────────────────────────────────────
function toggleTag(t: string) {
  const next = new Set(selectedTags.value)
  next.has(t) ? next.delete(t) : next.add(t)
  selectedTags.value = next
}

function toggleSpeaker(id: string) {
  const next = new Set(selectedSpeakers.value)
  next.has(id) ? next.delete(id) : next.add(id)
  selectedSpeakers.value = next
}

const filtered = computed<AgendaSession[]>(() => {
  const q = search.value.trim().toLowerCase()
  return store.sessions.filter((s) => {
    // Bookmarks
    if (savedOnly.value && !bookmarks.isOn('session', s.id)) return false
    // Day
    if (selectedDay.value && s.starts_at && dayKey(s.starts_at, tz.value) !== selectedDay.value) return false
    // Track
    if (activeTrack.value !== 'all' && s.track?.id !== activeTrack.value) return false
    // Tags
    if (selectedTags.value.size && !(s.tags || []).some(t => selectedTags.value.has(t))) return false
    // Speakers
    if (selectedSpeakers.value.size && !s.speakers.some(sp => selectedSpeakers.value.has(sp.id))) return false
    // Search
    if (q) {
      const hay = `${s.title} ${(s.description || '').replace(/<[^>]+>/g, '')}`.toLowerCase()
      if (!hay.includes(q)) return false
    }
    return true
  })
})

function resetTags() { selectedTags.value = new Set() }
function resetSpeakers() { selectedSpeakers.value = new Set() }
function clearAdvanceFilters() {
  selectedTags.value = new Set()
  selectedSpeakers.value = new Set()
  savedOnly.value = false
}
const hasAdvanceFilters = computed(() => selectedTags.value.size > 0 || selectedSpeakers.value.size > 0 || savedOnly.value)

// ── Time-of-day grouping (matches the reference layout: a header per start
// time, up to 3 cards, "View all sessions" to reveal the rest) ────────────
function fmtGroupTime(iso: string) {
  return new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', hour12: true, timeZone: tz.value }).format(new Date(iso))
}

interface TimeGroup { key: string, label: string, sessions: AgendaSession[], live: boolean }

const groups = computed<TimeGroup[]>(() => {
  const map = new Map<string, AgendaSession[]>()
  const order: string[] = []
  for (const s of filtered.value) {
    const key = s.starts_at || 'tba'
    if (!map.has(key)) { map.set(key, []); order.push(key) }
    map.get(key)!.push(s)
  }
  order.sort((a, b) => {
    if (a === 'tba') return 1
    if (b === 'tba') return -1
    return new Date(a).getTime() - new Date(b).getTime()
  })
  const now = Date.now()
  return order.map((key) => {
    const list = map.get(key)!
    const live = key !== 'tba' && list.some((s) => {
      const start = s.starts_at ? new Date(s.starts_at).getTime() : null
      const end = s.ends_at ? new Date(s.ends_at).getTime() : null
      return start !== null && end !== null && now >= start && now <= end
    })
    return { key, label: key === 'tba' ? 'Time TBA' : fmtGroupTime(key), sessions: list, live }
  })
})

const expandedGroups = ref<Set<string>>(new Set())
function expandGroup(key: string) {
  const next = new Set(expandedGroups.value)
  next.add(key)
  expandedGroups.value = next
}
</script>

<template>
  <div class="page">
    <div class="grid">
      <!-- All main content -->
      <div class="col">
        <ReceptionAdStrip v-if="store.ads.length" :ads="store.ads" class="banner" />

        <!-- Day selector -->
        <div v-if="days.length" class="days">
          <ReceptionCardCarousel>
            <button
              v-for="d in days"
              :key="d"
              type="button"
              class="day"
              :class="{ active: selectedDay === d }"
              @click="selectedDay = d"
            >
              <strong>{{ dayLabel(d, 'day') }}</strong>
              <span>{{ dayLabel(d, 'weekday') }}</span>
            </button>
          </ReceptionCardCarousel>
        </div>

        <div class="toprow">
          <div class="search">
            <svg viewBox="0 0 24 24"><path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3" /></svg>
            <input v-model="search" type="text" placeholder="Search…">
          </div>
          <select v-model="tz" class="fselect" title="Timezone">
            <option v-for="z in tzOptions" :key="z" :value="z">{{ z }}</option>
          </select>
          <select v-model="activeTrack" class="fselect" title="Track">
            <option value="all">Tracks : All</option>
            <option v-for="t in store.tracks" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
        </div>

        <div v-if="store.loading && !store.loaded" class="state">Loading sessions…</div>
        <div v-else-if="store.error" class="state">Couldn’t load sessions. Please try again.</div>
        <div v-else-if="!filtered.length" class="state">No sessions match your filters.</div>

        <template v-else>
          <div v-for="g in groups" :key="g.key" class="tgroup">
            <div class="tghead">
              <span v-if="g.live" class="live"><i />LIVE</span>
              <strong>{{ g.label }}</strong>
              <span class="count">| {{ g.sessions.length }} Session{{ g.sessions.length === 1 ? '' : 's' }}</span>
            </div>
            <div class="tgrid">
              <SessionsCard
                v-for="s in (expandedGroups.has(g.key) ? g.sessions : g.sessions.slice(0, 3))"
                :key="s.id"
                :session="s"
                :tz="tz"
              />
            </div>
            <button
              v-if="g.sessions.length > 3 && !expandedGroups.has(g.key)"
              type="button"
              class="viewall"
              @click="expandGroup(g.key)"
            >View all sessions</button>
          </div>
        </template>
      </div>

      <!-- Advance Filter box, and nothing else -->
      <aside class="rail">
        <div class="afhead">
          <span>Advance Filter</span>
          <button v-if="hasAdvanceFilters" type="button" class="clearall" @click="clearAdvanceFilters">Clear All</button>
        </div>

        <div class="group">
          <div class="grouphead"><span>Bookmarks</span></div>
          <div class="chips">
            <button type="button" class="chip" :class="{ on: savedOnly }" @click="savedOnly = !savedOnly">
              Saved only{{ bookmarks.count('session') ? ` (${bookmarks.count('session')})` : '' }}
            </button>
          </div>
        </div>

        <div v-if="store.speakerOptions.length" class="group">
          <div class="grouphead">
            <span>Speakers</span>
            <button v-if="selectedSpeakers.size" type="button" class="reset" @click="resetSpeakers">Reset</button>
          </div>
          <div class="chips">
            <button
              v-for="sp in store.speakerOptions"
              :key="sp.id"
              type="button"
              class="chip"
              :class="{ on: selectedSpeakers.has(sp.id) }"
              @click="toggleSpeaker(sp.id)"
            >{{ sp.name }}</button>
          </div>
        </div>

        <div v-if="store.tags.length" class="group">
          <div class="grouphead">
            <span>Tags</span>
            <button v-if="selectedTags.size" type="button" class="reset" @click="resetTags">Reset</button>
          </div>
          <div class="chips">
            <button
              v-for="t in store.tags"
              :key="t"
              type="button"
              class="chip"
              :class="{ on: selectedTags.has(t) }"
              @click="toggleTag(t)"
            >{{ t }}</button>
          </div>
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.days { background: #fff; border-radius: 14px; padding: 10px; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.day { flex: 1 0 auto; min-width: 120px; display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 12px 16px; border: none; border-radius: 10px; background: #fff; color: #64748b; cursor: pointer; }
.day strong { font-size: .95rem; font-weight: 800; color: #334155; }
.day span { font-size: .82rem; }
.day.active { background: var(--brand-primary); }
.day.active strong, .day.active span { color: #fff; }

.page { display: flex; flex-direction: column; }
.grid { display: grid; grid-template-columns: minmax(0, 1008px) 432px; justify-content: center; align-items: start; }
.col { display: flex; flex-direction: column; gap: 32px; padding: 32px; box-sizing: border-box; min-width: 0; }

.toprow { display: flex; gap: 10px; }
.search { flex: 1; display: flex; align-items: center; gap: 8px; background: #fff; border-radius: 12px; padding: 0 14px; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.search svg { width: 18px; height: 18px; fill: none; stroke: #94a3b8; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; flex: 0 0 auto; }
.search input { border: none; background: none; outline: none; padding: 13px 0; width: 100%; font: inherit; font-size: .9rem; color: #334155; }
.fselect { flex: 0 0 auto; min-width: 170px; border: none; border-radius: 12px; padding: 0 14px; font: inherit; font-size: .86rem; color: #334155; background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
@media (max-width: 640px) { .toprow { flex-wrap: wrap; } .fselect { flex: 1 1 auto; min-width: 0; } }
.tghead { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
.tghead strong { font-size: .92rem; font-weight: 800; color: #1e293b; }
.tghead .count { color: #64748b; font-size: .86rem; }
.live { display: inline-flex; align-items: center; gap: 5px; background: #ef4444; color: #fff; font-size: .68rem; font-weight: 800; letter-spacing: .4px; padding: 3px 9px; border-radius: 6px; }
.live i { width: 6px; height: 6px; border-radius: 50%; background: #fff; animation: pulse 1.4s infinite; }
@keyframes pulse { 0%,100% { opacity: 1 } 50% { opacity: .3 } }

.tgrid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
@media (max-width: 860px) { .tgrid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 560px) { .tgrid { grid-template-columns: 1fr; } }

.viewall { display: block; width: 100%; margin-top: 14px; padding: 10px 0; border: none; border-top: 1px solid #e6e8ec; background: none; color: var(--brand-primary); font-weight: 700; font-size: .86rem; cursor: pointer; }
.viewall:hover { color: color-mix(in srgb, var(--brand-primary) 80%, #000); }

.state { background: #fff; border-radius: 14px; padding: 48px 0; text-align: center; color: #64748b; box-shadow: 0 1px 2px rgba(15,23,42,.05); }

.rail { background: #fff; border-radius: 14px; padding: 32px; box-sizing: border-box; box-shadow: 0 1px 2px rgba(15,23,42,.05); display: flex; flex-direction: column; gap: 24px; position: sticky; top: 16px; }
.afhead { display: flex; align-items: center; justify-content: space-between; font-size: .92rem; font-weight: 800; color: #1e293b; }
.clearall { border: none; background: none; color: var(--brand-primary); font-size: .8rem; font-weight: 600; cursor: pointer; }

.group { border-top: 1px solid #eef0f3; padding-top: 24px; }
.grouphead { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.grouphead span { font-size: .84rem; font-weight: 700; color: #334155; }
.reset { border: none; background: none; color: var(--brand-primary); font-size: .76rem; font-weight: 600; cursor: pointer; }

.chips { display: flex; flex-wrap: wrap; gap: 7px; }
.chip { border: 1px solid #e2e8f0; background: #fff; color: #475569; border-radius: 999px; padding: 6px 12px; font-size: .78rem; cursor: pointer; }
.chip.on { border-color: var(--brand-primary); color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 10%, #fff); }

@media (max-width: 920px) {
  .grid { grid-template-columns: 1fr; }
  .col { padding: 16px; }
  .rail { position: static; margin: 0 16px 16px; }
}
</style>
