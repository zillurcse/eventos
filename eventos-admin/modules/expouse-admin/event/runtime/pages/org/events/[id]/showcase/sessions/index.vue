<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route  = useRoute()
const router = useRouter()
const api    = useApi()
const { upload } = useUpload()
const id     = route.params.id as string

// ── Types ─────────────────────────────────────────────────────────────────────

interface Track { id: number; name: string; color: string }

interface SessionSpeaker { id: string; name: string; image_url?: string | null }
interface Sponsor { id: string; name: string; logo_url?: string | null }
interface SessionDocument { name: string; url: string }

interface EventSpeaker {
  id: string; name: string; email: string
  designation: string; image_url: string | null
}

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
  sponsors: Sponsor[]
  documents: SessionDocument[]
  is_featured: boolean
  is_allowed_to_rate: boolean
  track: Track | null
  speakers: SessionSpeaker[]
}

interface DraftShape {
  title: string
  description: string
  date: string
  start_time: string
  end_time: string
  track_id: number | ''
  session_place: string
  logo_url: string | null
  icon_url: string | null
  capacity: number | ''
  speaker_ids: string[]
  sponsors: Sponsor[]
  documents: SessionDocument[]
  tags: string[]
  is_featured: boolean
  is_allowed_to_rate: boolean
}

// ── State ─────────────────────────────────────────────────────────────────────

const event         = ref<any>(null)
const sessions      = ref<Session[]>([])
const tracks        = ref<Track[]>([])
const eventSpeakers = ref<EventSpeaker[]>([])
const sponsorsList  = ref<Sponsor[]>([])
const search        = ref('')

// Create drawer
const drawerOpen = ref(false)
const saving     = ref(false)
const error      = ref('')
const tagInput   = ref('')

// Track inline CRUD
const showTrackMenu    = ref(false)
const newTrackName     = ref('')
const addingTrack      = ref(false)
const editingTrackId   = ref<number | null>(null)
const editingTrackName = ref('')

// Pickers
const speakerModal  = ref(false)
const speakerSearch = ref('')
const sponsorModal  = ref(false)
const sponsorSearch = ref('')

// Documents
const DOC_EXT = ['doc', 'docx', 'ppt', 'pptx', 'pdf']
const docUploading = ref(false)

// Rich-text description editor
const descEditor = ref<HTMLElement | null>(null)

// Three-dot menus
const openMenuId = ref<string | null>(null)

function freshDraft(): DraftShape {
  return {
    title: '', description: '', date: '', start_time: '', end_time: '',
    track_id: '', session_place: '', logo_url: null, icon_url: null, capacity: '',
    speaker_ids: [], sponsors: [], documents: [], tags: [],
    is_featured: false, is_allowed_to_rate: false,
  }
}

const draft = reactive<DraftShape>(freshDraft())

// ── Helpers ───────────────────────────────────────────────────────────────────

function fmtTime(iso: string | null): string {
  if (!iso) return ''
  return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

function fmtDateHeading(dateKey: string): string {
  return new Date(dateKey + 'T12:00:00').toLocaleDateString([], {
    weekday: 'short', month: 'short', day: 'numeric', year: 'numeric',
  })
}

function duration(s: Session): string {
  if (!s.starts_at || !s.ends_at) return ''
  const mins = Math.round(
    (new Date(s.ends_at).getTime() - new Date(s.starts_at).getTime()) / 60000,
  )
  if (mins <= 0) return ''
  if (mins < 60) return `${mins}m`
  const h = Math.floor(mins / 60), m = mins % 60
  return m ? `${h}h ${m}m` : `${h}h`
}

function initials(name: string | null | undefined): string {
  if (!name) return '?'
  return name.split(' ').slice(0, 2).map(w => w[0] ?? '').join('').toUpperCase()
}

function buildDatetime(date: string, time: string): string | null {
  if (!date) return null
  return time ? `${date}T${time}:00` : `${date}T00:00:00`
}

// ── Choose Day options (derived from the event's date range) ───────────────────

const dayOptions = computed<{ value: string, label: string }[]>(() => {
  if (!event.value?.starts_at) return []
  const start = new Date(event.value.starts_at)
  const end   = event.value.ends_at ? new Date(event.value.ends_at) : start
  if (Number.isNaN(start.getTime())) return []

  const out: { value: string, label: string }[] = []
  const cursor = new Date(start.getFullYear(), start.getMonth(), start.getDate())
  const last   = new Date(end.getFullYear(), end.getMonth(), end.getDate())
  let guard = 0
  while (cursor <= last && guard++ < 366) {
    const value = [
      cursor.getFullYear(),
      String(cursor.getMonth() + 1).padStart(2, '0'),
      String(cursor.getDate()).padStart(2, '0'),
    ].join('-')
    out.push({
      value,
      label: cursor.toLocaleDateString([], { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' }),
    })
    cursor.setDate(cursor.getDate() + 1)
  }
  return out
})

// End must be after start when both are set.
const timeError = computed(() =>
  draft.start_time && draft.end_time && draft.end_time <= draft.start_time
    ? 'Schedule End must be after Schedule Start'
    : '',
)

// ── Grouped sessions ──────────────────────────────────────────────────────────

const grouped = computed(() => {
  const q = search.value.toLowerCase()
  const list = q
    ? sessions.value.filter(s => s.title.toLowerCase().includes(q))
    : sessions.value

  const map = new Map<string, Session[]>()
  for (const s of list) {
    const key = s.starts_at ? s.starts_at.slice(0, 10) : '__none__'
    if (!map.has(key)) map.set(key, [])
    map.get(key)!.push(s)
  }

  return [...map.entries()]
    .sort(([a], [b]) => {
      if (a === '__none__') return 1
      if (b === '__none__') return -1
      return a.localeCompare(b)
    })
    .map(([key, items]) => ({
      key,
      label: key === '__none__' ? 'Unscheduled' : fmtDateHeading(key),
      items: [...items].sort((a, b) => (a.starts_at ?? '').localeCompare(b.starts_at ?? '')),
    }))
})

// ── Status styling ────────────────────────────────────────────────────────────

const STATUS_BORDER: Record<string, string> = {
  scheduled: 'border-l-blue-400',
  live:      'border-l-green-500',
  ended:     'border-l-[#ccc]',
  canceled:  'border-l-red-400',
}

const STATUS_DOT: Record<string, string> = {
  scheduled: 'bg-blue-400',
  live:      'bg-green-500',
  ended:     'bg-[#ccc]',
  canceled:  'bg-red-400',
}

// ── API: Load ─────────────────────────────────────────────────────────────────

async function load() {
  try {
    const [sessRes, trkRes, spkRes, evtRes, sponRes] = await Promise.all([
      api<any>(`/sessions?event=${id}`),
      api<any>(`/tracks?event=${id}`),
      api<any>(`/events/${id}/speakers`),
      api<any>(`/events/${id}`),
      api<any>(`/exhibitors?event=${id}&type=sponsor`),
    ])
    sessions.value      = sessRes.data
    tracks.value        = trkRes.data
    eventSpeakers.value = spkRes.data
    event.value         = evtRes.data
    sponsorsList.value  = (sponRes.data || []).map((e: any) => ({ id: e.id, name: e.name, logo_url: e.logo_url ?? null }))
  } catch { /* */ }
}

// ── Three-dot menu ────────────────────────────────────────────────────────────

function toggleMenu(sessionId: string, e: Event) {
  e.stopPropagation()
  openMenuId.value = openMenuId.value === sessionId ? null : sessionId
}

function closeOverlays() {
  openMenuId.value = null
  showTrackMenu.value = false
}

// ── Session actions ───────────────────────────────────────────────────────────

function editSession(s: Session) {
  openMenuId.value = null
  router.push(`/org/events/${id}/showcase/sessions/${s.id}`)
}

async function toggleStatus(s: Session) {
  openMenuId.value = null
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
  if (!confirm(`Delete session "${s.title}"?`)) return
  openMenuId.value = null
  try {
    await api(`/sessions/${s.id}`, { method: 'DELETE' })
    sessions.value = sessions.value.filter(x => x.id !== s.id)
  } catch { /* */ }
}

// ── Create drawer ─────────────────────────────────────────────────────────────

function openAdd() {
  Object.assign(draft, freshDraft())
  error.value = ''
  tagInput.value = ''
  newTrackName.value = ''
  showTrackMenu.value = false
  editingTrackId.value = null
  drawerOpen.value = true
}

async function saveDraft() {
  if (timeError.value) { error.value = timeError.value; return }
  error.value = ''
  saving.value = true
  try {
    const body: any = {
      event:              id,
      title:              draft.title,
      description:        draft.description || null,
      starts_at:          buildDatetime(draft.date, draft.start_time),
      ends_at:            buildDatetime(draft.date, draft.end_time),
      track_id:           draft.track_id    || null,
      session_place:      draft.session_place || null,
      logo_url:           draft.logo_url    || null,
      icon_url:           draft.icon_url    || null,
      capacity:           draft.capacity    || null,
      tags:               draft.tags,
      sponsors:           draft.sponsors,
      documents:          draft.documents,
      is_featured:        draft.is_featured,
      is_allowed_to_rate: draft.is_allowed_to_rate,
    }

    const res = await api<any>('/sessions', { method: 'POST', body })
    const newSession: Session = res.data

    // Link selected speakers (relational — session_speaker pivot).
    for (const spkId of draft.speaker_ids) {
      const sp = eventSpeakers.value.find(s => s.id === spkId)
      if (!sp) continue
      try {
        const parts = sp.name.split(' ')
        await api(`/sessions/${newSession.id}/speakers`, {
          method: 'POST',
          body: {
            email:      sp.email,
            first_name: parts[0] ?? '',
            last_name:  parts.slice(1).join(' ') || '',
            role:       'speaker',
          },
        })
      } catch { /* */ }
    }

    // Reload session to include linked speakers
    if (draft.speaker_ids.length) {
      try {
        const fresh = await api<any>(`/sessions/${newSession.id}`)
        sessions.value.push(fresh.data)
      } catch {
        sessions.value.push(newSession)
      }
    } else {
      sessions.value.push(newSession)
    }

    drawerOpen.value = false
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save session.'
  } finally {
    saving.value = false
  }
}

// ── Rich-text description ──────────────────────────────────────────────────────

function fmtText(cmd: string) {
  document.execCommand(cmd, false)
  syncDesc()
}
function syncDesc() {
  if (descEditor.value) draft.description = descEditor.value.innerHTML
}

// ── Track inline CRUD ─────────────────────────────────────────────────────────

async function createTrack() {
  const name = newTrackName.value.trim()
  if (!name) return
  addingTrack.value = true
  try {
    const res = await api<any>('/tracks', { method: 'POST', body: { event: id, name } })
    tracks.value.push(res.data)
    draft.track_id = res.data.id
    newTrackName.value = ''
    showTrackMenu.value = false
  } catch { /* */ } finally {
    addingTrack.value = false
  }
}

function startEditTrack(track: Track) {
  editingTrackId.value = track.id
  editingTrackName.value = track.name
}

async function updateTrack(track: Track) {
  const name = editingTrackName.value.trim()
  if (!name) { editingTrackId.value = null; return }
  try {
    const res = await api<any>(`/tracks/${track.id}`, { method: 'PUT', body: { name } })
    const idx = tracks.value.findIndex(t => t.id === track.id)
    if (idx >= 0) tracks.value[idx] = res.data
  } catch { /* */ } finally {
    editingTrackId.value = null
  }
}

async function deleteTrack(track: Track) {
  if (!confirm(`Delete track "${track.name}"?`)) return
  try {
    await api(`/tracks/${track.id}`, { method: 'DELETE' })
    tracks.value = tracks.value.filter(t => t.id !== track.id)
    if (draft.track_id === track.id) draft.track_id = ''
  } catch { /* */ }
}

// ── Tags ──────────────────────────────────────────────────────────────────────

function addTag() {
  const val = tagInput.value.replace(/,\s*$/, '').trim()
  if (val && !draft.tags.includes(val)) draft.tags.push(val)
  tagInput.value = ''
}

function removeTag(i: number) { draft.tags.splice(i, 1) }

function onTagKey(e: KeyboardEvent) {
  if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addTag() }
}

// ── Speakers picker ───────────────────────────────────────────────────────────

const filteredEventSpeakers = computed(() => {
  const q = speakerSearch.value.toLowerCase()
  return q ? eventSpeakers.value.filter(s => s.name?.toLowerCase().includes(q)) : eventSpeakers.value
})
const selectedSpeakers = computed(() => eventSpeakers.value.filter(s => draft.speaker_ids.includes(s.id)))

function toggleSpeakerDraft(spkId: string) {
  const idx = draft.speaker_ids.indexOf(spkId)
  if (idx >= 0) draft.speaker_ids.splice(idx, 1)
  else draft.speaker_ids.push(spkId)
}

// ── Sponsors picker ───────────────────────────────────────────────────────────

const filteredSponsors = computed(() => {
  const q = sponsorSearch.value.toLowerCase()
  return q ? sponsorsList.value.filter(s => s.name?.toLowerCase().includes(q)) : sponsorsList.value
})
function isSponsorSelected(s: Sponsor) { return draft.sponsors.some(x => x.id === s.id) }
function toggleSponsor(s: Sponsor) {
  const i = draft.sponsors.findIndex(x => x.id === s.id)
  if (i >= 0) draft.sponsors.splice(i, 1)
  else draft.sponsors.push({ id: s.id, name: s.name, logo_url: s.logo_url ?? null })
}
function removeSponsor(sid: string) {
  draft.sponsors = draft.sponsors.filter(x => x.id !== sid)
}

// ── Documents (multi-upload, doc/ppt/pdf, max 10MB, max 10 files) ─────────────

async function onDocsPick(e: Event) {
  const input = e.target as HTMLInputElement
  const files = Array.from(input.files || [])
  input.value = ''
  error.value = ''
  for (const file of files) {
    if (draft.documents.length >= 10) { error.value = 'Maximum 10 files allowed.'; break }
    const ext = file.name.split('.').pop()?.toLowerCase() || ''
    if (!DOC_EXT.includes(ext)) { error.value = `Only doc, ppt and pdf files allowed (${file.name}).`; continue }
    if (file.size > 10 * 1024 * 1024) { error.value = `${file.name} exceeds 10 MB.`; continue }
    docUploading.value = true
    try {
      const r = await upload(file, { collection: 'session_doc' })
      draft.documents.push({ name: file.name, url: r.url })
    } catch { error.value = `Could not upload ${file.name}.` }
    finally { docUploading.value = false }
  }
}
function removeDoc(i: number) { draft.documents.splice(i, 1) }

onMounted(load)
</script>

<template>
  <div @click="closeOverlays">
    <!-- Header -->
    <div class="mb-4">
      <h2 class="section-title m-0">Sessions</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Manage agenda sessions for this event.</p>
    </div>

    <!-- Toolbar -->
    <div class="flex items-center gap-3 mb-5">
      <input
        v-model="search"
        placeholder="Search sessions…"
        class="m-0 max-w-[260px]"
        @click.stop
      >
      <div class="flex-1" />
      <button class="btn" @click.stop="openAdd">
        <Icon name="plus" class="w-3.75 h-3.75" /> SCHEDULE
      </button>
    </div>

    <!-- Empty state -->
    <div v-if="!sessions.length && !search" class="card text-center py-12 muted">
      No sessions yet — click <strong>+ SCHEDULE</strong> to add one.
    </div>

    <!-- Grouped card grid -->
    <div v-for="group in grouped" :key="group.key" class="mb-8">
      <!-- Day heading -->
      <div class="flex items-center gap-3 mb-3">
        <span class="font-bold text-[.78rem] tracking-widest uppercase text-muted">{{ group.label }}</span>
        <div class="flex-1 h-px bg-line" />
        <span class="text-[.78rem] text-muted font-medium">
          {{ group.items.length }} session{{ group.items.length !== 1 ? 's' : '' }}
        </span>
      </div>

      <!-- Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="s in group.items"
          :key="s.id"
          class="card p-0 overflow-hidden border-l-4 relative"
          :class="STATUS_BORDER[s.status] ?? 'border-l-[#ccc]'"
        >
          <!-- Three-dot trigger -->
          <button
            class="absolute top-3 right-3 w-7 h-7 flex items-center justify-center rounded-lg text-muted hover:bg-[#f1f1f5] text-[1.2rem] leading-none transition-colors z-10"
            @click.stop="toggleMenu(s.id, $event)"
          >⋮</button>

          <!-- Dropdown menu -->
          <div
            v-if="openMenuId === s.id"
            class="absolute top-11 right-3 z-20 bg-white border border-line rounded-xl shadow-lg py-1 min-w-[168px]"
            @click.stop
          >
            <button
              class="w-full text-left px-4 py-2 text-[.88rem] hover:bg-[#f7f7fb]"
              @click="editSession(s)"
            >Edit Session</button>
            <button
              class="w-full text-left px-4 py-2 text-[.88rem] hover:bg-[#f7f7fb]"
              @click="toggleStatus(s)"
            >
              Mark as
              {{ s.status === 'scheduled' ? 'Live' : s.status === 'live' ? 'Ended' : 'Scheduled' }}
            </button>
            <div class="border-t border-line my-1" />
            <button
              class="w-full text-left px-4 py-2 text-[.88rem] text-[#dc2626] hover:bg-red-50"
              @click="removeSession(s)"
            >Delete</button>
          </div>

          <!-- Card body -->
          <div class="p-4 pr-10">
            <!-- Time range + duration -->
            <div class="flex items-center gap-2 mb-2 flex-wrap">
              <span class="text-[.82rem] font-semibold text-muted">
                {{ s.starts_at ? fmtTime(s.starts_at) : 'TBD' }}
                <template v-if="s.ends_at"> — {{ fmtTime(s.ends_at) }}</template>
              </span>
              <span
                v-if="duration(s)"
                class="px-1.5 py-0.5 bg-brand-soft text-brand rounded text-[.7rem] font-semibold"
              >{{ duration(s) }}</span>
            </div>

            <!-- Title -->
            <h3 class="font-bold text-[.95rem] text-ink leading-snug mb-1 line-clamp-2 m-0">
              {{ s.title }}
            </h3>

            <!-- Place -->
            <p v-if="s.session_place" class="text-[.8rem] text-muted mb-1">
              📍 {{ s.session_place }}
            </p>

            <!-- Badges -->
            <div class="flex flex-wrap gap-1.5 mt-2">
              <span
                v-if="s.track"
                class="px-2 py-0.5 rounded-full text-[.7rem] font-medium text-white"
                :style="{ background: s.track.color || '#6352e7' }"
              >{{ s.track.name }}</span>
              <span class="flex items-center gap-1 px-2 py-0.5 rounded-full text-[.7rem] font-medium bg-[#f1f1f5] text-muted capitalize">
                <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="STATUS_DOT[s.status]" />
                {{ s.status }}
              </span>
              <span
                v-if="s.is_featured"
                class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-[.7rem] font-medium"
              >Featured</span>
            </div>

            <!-- Speaker avatars -->
            <div v-if="s.speakers?.length" class="flex items-center mt-3">
              <div
                v-for="sp in s.speakers.slice(0, 4)"
                :key="sp.id"
                class="w-6 h-6 rounded-full overflow-hidden bg-brand-soft text-brand flex items-center justify-center text-[.6rem] font-bold shrink-0 border-2 border-white -ml-1 first:ml-0"
                :title="sp.name"
              >
                <img v-if="sp.image_url" :src="sp.image_url" :alt="sp.name" class="w-full h-full object-cover">
                <span v-else>{{ initials(sp.name) }}</span>
              </div>
              <span v-if="s.speakers.length > 4" class="text-[.73rem] text-muted ml-1.5">
                +{{ s.speakers.length - 4 }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Create Drawer ─────────────────────────────────────────────────── -->
    <Drawer v-if="drawerOpen" title="Schedule Session" @close="drawerOpen = false">

      <!-- Choose Day -->
      <div class="mb-4">
        <label class="block mb-1.5">Choose Day</label>
        <select v-model="draft.date" class="m-0 w-full">
          <option value="">Select</option>
          <option v-for="d in dayOptions" :key="d.value" :value="d.value">{{ d.label }}</option>
        </select>
        <p v-if="!dayOptions.length" class="text-[.78rem] text-muted mt-1">
          Set the event start &amp; end dates to choose a day.
        </p>
      </div>

      <!-- Schedule Start / End -->
      <div class="flex gap-3 mb-1">
        <div class="flex-1">
          <label class="block mb-1.5">Schedule Start</label>
          <input v-model="draft.start_time" type="time" class="m-0 w-full">
        </div>
        <div class="flex-1">
          <label class="block mb-1.5">Schedule End</label>
          <input v-model="draft.end_time" type="time" class="m-0 w-full">
        </div>
      </div>
      <p v-if="timeError" class="error mb-3">{{ timeError }}</p>
      <div v-else class="mb-3" />

      <!-- Title -->
      <div class="mb-4">
        <label class="block mb-1.5">Title <span class="text-[#dc2626]">*</span></label>
        <input v-model="draft.title" placeholder="Enter Title" class="m-0">
      </div>

      <!-- Session Place -->
      <div class="mb-4">
        <label class="block mb-1.5">Session Place</label>
        <input v-model="draft.session_place" placeholder="Enter Session Place" class="m-0">
      </div>

      <!-- Track (inline CRUD) -->
      <div class="mb-4 relative">
        <label class="block mb-1.5">Select Track</label>
        <button
          type="button"
          class="w-full flex items-center justify-between px-3 py-2 border border-line rounded-xl bg-white text-[.9rem]"
          @click.stop="showTrackMenu = !showTrackMenu; editingTrackId = null"
        >
          <span>{{ tracks.find(t => t.id === draft.track_id)?.name ?? 'Select' }}</span>
          <span class="text-muted text-xs">▾</span>
        </button>
        <div
          v-if="showTrackMenu"
          class="absolute left-0 right-0 top-full mt-1 z-20 bg-white border border-line rounded-xl shadow-lg py-1 max-h-56 overflow-y-auto"
          @click.stop
        >
          <button
            class="w-full text-left px-4 py-2 text-[.88rem] hover:bg-[#f7f7fb]"
            :class="!draft.track_id ? 'font-semibold text-brand' : ''"
            @click="draft.track_id = ''; showTrackMenu = false"
          >— No track —</button>

          <div
            v-for="t in tracks"
            :key="t.id"
            class="flex items-center gap-1.5 px-3 py-1 hover:bg-[#f7f7fb]"
          >
            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: t.color || '#6352e7' }" />
            <template v-if="editingTrackId === t.id">
              <input
                v-model="editingTrackName"
                class="flex-1 m-0 py-0.5 text-[.87rem] border-b border-brand focus:outline-none bg-transparent"
                @keydown.enter="updateTrack(t)"
                @keydown.escape="editingTrackId = null"
              >
              <button class="text-brand text-[.85rem] px-1 hover:opacity-70" @click="updateTrack(t)">✓</button>
            </template>
            <template v-else>
              <button
                class="flex-1 text-left text-[.88rem] py-0.5"
                :class="draft.track_id === t.id ? 'font-semibold text-brand' : ''"
                @click="draft.track_id = t.id; showTrackMenu = false"
              >{{ t.name }}</button>
              <button class="text-muted text-[.78rem] hover:text-brand px-1 leading-none" @click.stop="startEditTrack(t)" title="Rename">✎</button>
              <button class="text-muted text-[.78rem] hover:text-[#dc2626] px-1 leading-none" @click.stop="deleteTrack(t)" title="Delete">✕</button>
            </template>
          </div>

          <!-- New track input -->
          <div class="border-t border-line mt-1 pt-1 px-3 pb-1">
            <div class="flex gap-1.5">
              <input
                v-model="newTrackName"
                placeholder="Enter track name"
                class="flex-1 m-0 py-1 text-[.87rem]"
                @keydown.enter="createTrack"
              >
              <button
                class="btn sm"
                :disabled="!newTrackName.trim() || addingTrack"
                @click="createTrack"
              >ADD</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Description (rich text) -->
      <div class="mb-4">
        <label class="block mb-1.5">Description</label>
        <div class="border border-line rounded-xl overflow-hidden">
          <div class="flex items-center gap-1 px-2 py-1.5 border-b border-line bg-[#fafbfc]">
            <button type="button" class="rt-btn font-bold" title="Bold" @mousedown.prevent="fmtText('bold')">B</button>
            <button type="button" class="rt-btn italic" title="Italic" @mousedown.prevent="fmtText('italic')">I</button>
            <button type="button" class="rt-btn underline" title="Underline" @mousedown.prevent="fmtText('underline')">U</button>
            <button type="button" class="rt-btn line-through" title="Strikethrough" @mousedown.prevent="fmtText('strikeThrough')">S</button>
          </div>
          <div
            ref="descEditor"
            class="rt-area px-3 py-2 min-h-[120px] text-[.9rem] focus:outline-none"
            contenteditable="true"
            data-ph="Let's write an awesome story!"
            @input="syncDesc"
          />
        </div>
      </div>

      <!-- Logo + Icon -->
      <div class="flex gap-6 mb-4">
        <div>
          <label class="block mb-1.5">Logo</label>
          <UploadButton :preview="draft.logo_url" collection="session_logo" @uploaded="draft.logo_url = $event.url" />
        </div>
        <div>
          <label class="block mb-1.5">Icon</label>
          <UploadButton :preview="draft.icon_url" collection="session_icon" @uploaded="draft.icon_url = $event.url" />
        </div>
      </div>

      <!-- Speakers -->
      <div class="mb-4">
        <label class="block mb-2">Speakers</label>
        <div class="flex flex-wrap gap-2 items-center">
          <div
            v-for="sp in selectedSpeakers"
            :key="sp.id"
            class="relative w-16 h-16 rounded-xl overflow-hidden bg-brand-soft text-brand flex items-center justify-center text-[.85rem] font-bold border border-line"
            :title="sp.name"
          >
            <img v-if="sp.image_url" :src="sp.image_url" :alt="sp.name" class="w-full h-full object-cover">
            <span v-else>{{ initials(sp.name) }}</span>
            <button
              class="absolute -top-1 -right-1 w-4 h-4 bg-[#dc2626] text-white rounded-full text-[.7rem] leading-none flex items-center justify-center"
              @click.stop="toggleSpeakerDraft(sp.id)"
            >×</button>
          </div>
          <button
            type="button"
            class="w-16 h-16 border-2 border-dashed border-line rounded-xl flex items-center justify-center text-2xl text-muted hover:border-brand hover:text-brand transition-colors"
            @click.stop="speakerModal = true"
          >+</button>
        </div>
      </div>

      <!-- Session Sponsors -->
      <div class="mb-4">
        <label class="block mb-2">Session Sponsors</label>
        <div class="flex flex-wrap gap-2 items-center">
          <div
            v-for="sp in draft.sponsors"
            :key="sp.id"
            class="relative w-16 h-16 rounded-xl overflow-hidden bg-[#f1f1f5] text-muted flex items-center justify-center text-[.8rem] font-bold border border-line p-1 text-center"
            :title="sp.name"
          >
            <img v-if="sp.logo_url" :src="sp.logo_url" :alt="sp.name" class="w-full h-full object-contain">
            <span v-else class="leading-tight line-clamp-2">{{ sp.name }}</span>
            <button
              class="absolute -top-1 -right-1 w-4 h-4 bg-[#dc2626] text-white rounded-full text-[.7rem] leading-none flex items-center justify-center"
              @click.stop="removeSponsor(sp.id)"
            >×</button>
          </div>
          <button
            type="button"
            class="w-16 h-16 border-2 border-dashed border-line rounded-xl flex items-center justify-center text-2xl text-muted hover:border-brand hover:text-brand transition-colors"
            @click.stop="sponsorModal = true"
          >+</button>
        </div>
      </div>

      <!-- Documents -->
      <div class="mb-4">
        <label class="block mb-1.5">Documents</label>
        <label class="flex items-center justify-center gap-2 border-2 border-dashed border-line rounded-xl p-4 cursor-pointer hover:border-brand bg-[#fafbfc] transition-colors">
          <input type="file" multiple accept=".doc,.docx,.ppt,.pptx,.pdf" class="hidden" @change="onDocsPick">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5 text-muted"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/></svg>
          <span class="text-muted text-[.86rem]">{{ docUploading ? 'Uploading…' : 'Only doc, ppt and pdf file allowed' }}</span>
        </label>
        <p class="text-[.76rem] text-muted mt-1">Maximum : 10 MB and 10 files are only allowed to upload</p>
        <div v-if="draft.documents.length" class="mt-2 flex flex-col gap-1">
          <div
            v-for="(d, i) in draft.documents"
            :key="i"
            class="flex items-center justify-between px-3 py-1.5 bg-[#f4f5f7] rounded-lg text-[.84rem]"
          >
            <span class="truncate flex items-center gap-2">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 text-muted shrink-0"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
              {{ d.name }}
            </span>
            <button class="text-[#dc2626] leading-none text-[1.1rem] px-1" @click="removeDoc(i)">×</button>
          </div>
        </div>
      </div>

      <!-- Custom Tags -->
      <div class="mb-4">
        <label class="block mb-1.5">Custom Tags</label>
        <div v-if="draft.tags.length" class="flex flex-wrap gap-1.5 mb-2">
          <span
            v-for="(tag, i) in draft.tags" :key="i"
            class="flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-brand-soft text-brand text-[.8rem] font-medium"
          >
            {{ tag }}
            <button
              class="bg-transparent border-0 p-0 cursor-pointer text-brand leading-none text-[.9rem]"
              @click="removeTag(i)"
            >×</button>
          </span>
        </div>
        <input
          v-model="tagInput"
          placeholder="Add tag &amp; press enter"
          class="m-0"
          @keydown="onTagKey"
          @blur="addTag"
        >
      </div>

      <!-- Checkboxes -->
      <div class="mb-5 flex flex-col gap-3">
        <label class="flex items-center gap-3 cursor-pointer select-none">
          <input v-model="draft.is_allowed_to_rate" type="checkbox" class="w-4.5 h-4.5 m-0 accent-brand">
          <span class="text-[.93rem] font-medium text-ink">Attendees can rate this session</span>
        </label>
        <label class="flex items-center gap-3 cursor-pointer select-none">
          <input v-model="draft.is_featured" type="checkbox" class="w-4.5 h-4.5 m-0 accent-brand">
          <span class="text-[.93rem] font-medium text-ink">Featured schedule</span>
        </label>
      </div>

      <p v-if="error" class="error mt-3">{{ error }}</p>

      <div class="modal-actions border-t border-line pt-4 mt-5">
        <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
        <button
          class="btn"
          :disabled="!draft.title.trim() || !!timeError || saving"
          @click="saveDraft"
        >
          {{ saving ? 'Saving…' : 'ADD' }}
        </button>
      </div>
    </Drawer>

    <!-- ── Speakers picker modal ─────────────────────────────────────────── -->
    <div
      v-if="speakerModal"
      class="fixed inset-0 bg-black/40 flex items-center justify-center z-[60] p-4"
      @click.self="speakerModal = false"
    >
      <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[80vh] flex flex-col overflow-hidden">
        <div class="flex items-start justify-between p-5 border-b border-line">
          <div>
            <div class="font-bold text-[1.05rem] text-ink">Speakers</div>
            <div class="muted text-[.84rem]">Choose speakers</div>
          </div>
          <button class="text-muted hover:text-ink text-[1.3rem] leading-none" @click="speakerModal = false">×</button>
        </div>
        <div class="p-5 pb-3">
          <input v-model="speakerSearch" placeholder="Search" class="m-0 w-full">
        </div>
        <div class="px-5 overflow-y-auto flex-1">
          <div v-if="!filteredEventSpeakers.length" class="text-center muted py-10 text-[.88rem]">
            No speakers found. Add them in <strong>Showcase › Speakers</strong> first.
          </div>
          <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 pb-4">
            <button
              v-for="sp in filteredEventSpeakers"
              :key="sp.id"
              type="button"
              class="border-2 rounded-xl p-2 flex flex-col items-center gap-2 transition-colors"
              :class="draft.speaker_ids.includes(sp.id) ? 'border-brand bg-brand-soft' : 'border-line hover:bg-[#fafbfc]'"
              @click="toggleSpeakerDraft(sp.id)"
            >
              <div class="w-full aspect-square rounded-lg overflow-hidden bg-brand-soft text-brand flex items-center justify-center text-[1.4rem] font-bold">
                <img v-if="sp.image_url" :src="sp.image_url" :alt="sp.name" class="w-full h-full object-cover">
                <span v-else>{{ initials(sp.name) }}</span>
              </div>
              <span class="text-[.8rem] font-medium text-center truncate w-full">{{ sp.name }}</span>
            </button>
          </div>
        </div>
        <div class="flex justify-end gap-3 p-4 border-t border-line">
          <button class="btn ghost" @click="speakerModal = false">Cancel</button>
          <button class="btn" @click="speakerModal = false">SELECT</button>
        </div>
      </div>
    </div>

    <!-- ── Session Sponsors picker modal ─────────────────────────────────── -->
    <div
      v-if="sponsorModal"
      class="fixed inset-0 bg-black/40 flex items-center justify-center z-[60] p-4"
      @click.self="sponsorModal = false"
    >
      <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[80vh] flex flex-col overflow-hidden">
        <div class="flex items-start justify-between p-5 border-b border-line">
          <div>
            <div class="font-bold text-[1.05rem] text-ink">Session Sponsors</div>
            <div class="muted text-[.84rem]">Choose from exhibitors marked as sponsor</div>
          </div>
          <button class="text-muted hover:text-ink text-[1.3rem] leading-none" @click="sponsorModal = false">×</button>
        </div>
        <div class="p-5 pb-3">
          <input v-model="sponsorSearch" placeholder="Search" class="m-0 w-full">
        </div>
        <div class="px-5 overflow-y-auto flex-1">
          <div v-if="!filteredSponsors.length" class="text-center muted py-10 text-[.88rem]">
            No sponsors yet — add exhibitors of type <strong>Sponsor</strong> first.
          </div>
          <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 pb-4">
            <button
              v-for="sp in filteredSponsors"
              :key="sp.id"
              type="button"
              class="border-2 rounded-xl p-2 flex flex-col items-center gap-2 transition-colors"
              :class="isSponsorSelected(sp) ? 'border-brand bg-brand-soft' : 'border-line hover:bg-[#fafbfc]'"
              @click="toggleSponsor(sp)"
            >
              <div class="w-full aspect-square rounded-lg overflow-hidden bg-[#f1f1f5] text-muted flex items-center justify-center text-[.95rem] font-bold p-2 text-center">
                <img v-if="sp.logo_url" :src="sp.logo_url" :alt="sp.name" class="w-full h-full object-contain">
                <span v-else class="line-clamp-3">{{ sp.name }}</span>
              </div>
              <span class="text-[.8rem] font-medium text-center truncate w-full">{{ sp.name }}</span>
            </button>
          </div>
        </div>
        <div class="flex justify-end gap-3 p-4 border-t border-line">
          <button class="btn ghost" @click="sponsorModal = false">Cancel</button>
          <button class="btn" @click="sponsorModal = false">SELECT</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.rt-btn {
  width: 28px; height: 28px; border-radius: 6px; color: var(--ink);
  display: flex; align-items: center; justify-content: center; font-size: .9rem;
}
.rt-btn:hover { background: #eceef1; }
.rt-area:empty::before {
  content: attr(data-ph);
  color: var(--faint);
  font-style: italic;
}
</style>
