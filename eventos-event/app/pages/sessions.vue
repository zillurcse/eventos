<script setup lang="ts">
import type { AgendaSession } from '~/stores/sessions'

definePageMeta({ layout: 'event', middleware: 'auth' })

const store = useSessionsStore()
const bookmarks = useBookmarksStore()

// ── Filters state ────────────────────────────────────────────────────────
const view = ref<'list' | 'grid'>('list')
const search = ref('')
const activeTrack = ref<number | 'all'>('all')
const selectedTags = ref<Set<string>>(new Set())
const selectedSpeakers = ref<Set<string>>(new Set())
const selectedDay = ref<string | null>(null)
const savedOnly = ref(false)
const tz = ref('UTC')

onMounted(async () => {
  bookmarks.fetch()
  if (!store.loaded) await store.fetchSessions()
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
</script>

<template>
  <div>
    <div class="head">
      <h1>Sessions</h1>
      <p class="sub">Browse the full agenda — filter by day, track, tags or speaker.</p>
    </div>

    <!-- Day selector -->
    <div v-if="days.length" class="days">
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
    </div>

    <div class="layout">
      <!-- Sidebar -->
      <aside class="side">
        <div class="viewbar">
          <button class="vw" :class="{ on: view === 'list' }" type="button" title="List view" @click="view = 'list'">
            <svg viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" /></svg>
          </button>
          <button class="vw" :class="{ on: view === 'grid' }" type="button" title="Grid view" @click="view = 'grid'">
            <svg viewBox="0 0 24 24"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z" /></svg>
          </button>
        </div>

        <div class="box">
          <div class="search">
            <svg viewBox="0 0 24 24"><path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3" /></svg>
            <input v-model="search" type="text" placeholder="Search…">
          </div>

          <label class="tzlabel">Timezone</label>
          <select v-model="tz" class="tz">
            <option v-for="z in tzOptions" :key="z" :value="z">{{ z }}</option>
          </select>

          <p class="afhead">Advance Filter</p>

          <div class="group">
            <div class="grouphead"><span>Bookmarks</span></div>
            <div class="chips">
              <button type="button" class="chip" :class="{ on: savedOnly }" @click="savedOnly = !savedOnly">
                Saved only{{ bookmarks.count('session') ? ` (${bookmarks.count('session')})` : '' }}
              </button>
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

          <div v-if="store.speakerOptions.length" class="group">
            <div class="grouphead">
              <span>Speakers</span>
              <button v-if="selectedSpeakers.size" type="button" class="reset" @click="resetSpeakers">Reset</button>
            </div>
            <div class="chips col">
              <button
                v-for="sp in store.speakerOptions"
                :key="sp.id"
                type="button"
                class="spk"
                :class="{ on: selectedSpeakers.has(sp.id) }"
                @click="toggleSpeaker(sp.id)"
              >
                <span class="spkav">
                  <img v-if="sp.image_url" :src="sp.image_url" :alt="sp.name || ''">
                </span>
                <span class="spkname">{{ sp.name }}</span>
              </button>
            </div>
          </div>
        </div>
      </aside>

      <!-- Main -->
      <section class="main">
        <div class="tracks">
          <button
            type="button"
            class="tk"
            :class="{ on: activeTrack === 'all' }"
            @click="activeTrack = 'all'"
          >All Tracks</button>
          <button
            v-for="t in store.tracks"
            :key="t.id"
            type="button"
            class="tk"
            :class="{ on: activeTrack === t.id }"
            @click="activeTrack = t.id"
          >{{ t.name }}</button>
        </div>

        <div v-if="store.loading && !store.loaded" class="state">Loading sessions…</div>
        <div v-else-if="store.error" class="state">Couldn’t load sessions. Please try again.</div>
        <div v-else-if="!filtered.length" class="state">No sessions match your filters.</div>

        <div v-else class="list" :class="{ grid: view === 'grid' }">
          <SessionsCard v-for="s in filtered" :key="s.id" :session="s" :tz="tz" />
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.head { margin-bottom: 16px; }
.head h1 { margin: 0; font-size: 1.4rem; font-weight: 800; color: #1e293b; }
.sub { margin: 4px 0 0; color: #64748b; font-size: .9rem; }

.days { display: flex; gap: 10px; background: #fff; border-radius: 14px; padding: 10px; margin-bottom: 18px; overflow-x: auto; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.day { flex: 1 0 auto; min-width: 120px; display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 12px 16px; border: none; border-radius: 10px; background: #fff; color: #64748b; cursor: pointer; }
.day strong { font-size: .95rem; font-weight: 800; color: #334155; }
.day span { font-size: .82rem; }
.day.active { background: var(--brand-primary); }
.day.active strong, .day.active span { color: #fff; }

.layout { display: grid; grid-template-columns: 300px 1fr; gap: 18px; align-items: start; }
@media (max-width: 860px) { .layout { grid-template-columns: 1fr; } }

.side { display: flex; flex-direction: column; gap: 14px; }
.viewbar { display: flex; gap: 8px; background: #fff; border-radius: 12px; padding: 10px; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.vw { width: 40px; height: 40px; border-radius: 10px; border: none; background: #f4f5f8; color: #64748b; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.vw.on { background: var(--brand-primary); color: #fff; }
.vw svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }

.box { background: #fff; border-radius: 14px; padding: 16px; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.search { display: flex; align-items: center; gap: 8px; background: #f4f5f8; border-radius: 10px; padding: 0 12px; }
.search svg { width: 17px; height: 17px; fill: none; stroke: #94a3b8; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; flex: 0 0 auto; }
.search input { border: none; background: none; outline: none; padding: 11px 0; width: 100%; font: inherit; font-size: .88rem; color: #334155; }

.tzlabel { display: block; margin: 14px 0 6px; font-size: .78rem; font-weight: 600; color: #64748b; }
.tz { width: 100%; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; font: inherit; font-size: .86rem; color: #334155; background: #fff; }

.afhead { margin: 18px 0 8px; font-size: .82rem; font-weight: 700; color: #334155; }
.group { border-top: 1px solid #eef0f3; padding-top: 12px; margin-top: 12px; }
.grouphead { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.grouphead span { font-size: .84rem; font-weight: 700; color: #334155; }
.reset { border: none; background: none; color: var(--brand-primary); font-size: .76rem; font-weight: 600; cursor: pointer; }

.chips { display: flex; flex-wrap: wrap; gap: 7px; }
.chips.col { flex-direction: column; flex-wrap: nowrap; max-height: 280px; overflow-y: auto; }
.chip { border: 1px solid #e2e8f0; background: #fff; color: #475569; border-radius: 999px; padding: 6px 12px; font-size: .78rem; cursor: pointer; }
.chip.on { border-color: var(--brand-primary); color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 10%, #fff); }

.spk { display: flex; align-items: center; gap: 9px; border: 1px solid transparent; background: none; border-radius: 10px; padding: 6px 8px; cursor: pointer; text-align: left; width: 100%; }
.spk:hover { background: #f7f8fa; }
.spk.on { border-color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 8%, #fff); }
.spkav { width: 30px; height: 30px; border-radius: 50%; background: var(--brand-primary); overflow: hidden; flex: 0 0 auto; }
.spkav img { width: 100%; height: 100%; object-fit: cover; }
.spkname { font-size: .82rem; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.main { min-width: 0; }
.tracks { display: flex; gap: 6px; background: #fff; border-radius: 14px; padding: 8px; margin-bottom: 16px; overflow-x: auto; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.tk { flex: 0 0 auto; border: none; background: none; color: #64748b; font-weight: 700; font-size: .86rem; padding: 8px 16px; border-radius: 10px; cursor: pointer; white-space: nowrap; }
.tk:hover { color: var(--brand-primary); }
.tk.on { color: var(--brand-primary); border-bottom: 2px solid var(--brand-primary); border-radius: 0; }

.state { background: #fff; border-radius: 14px; padding: 48px 0; text-align: center; color: #64748b; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.list { display: flex; flex-direction: column; gap: 14px; }
.list.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); }
</style>
