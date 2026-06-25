<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '../../../../../composables/useApi'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route     = useRoute()
const router    = useRouter()
const api       = useApi()
const id        = route.params.id as string
const sessionId = route.params.sessionId as string

// ── Types ─────────────────────────────────────────────────────────────────────

interface Track { id: number; name: string; color: string }

interface SessionSpeaker { id: string; name: string; image_url?: string | null }

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
  tags: string[]
  is_featured: boolean
  is_allowed_to_rate: boolean
  is_stream: boolean
  who_will_host: string | null
  stream_link: string | null
  on_demand_recording_link: string | null
  vimeo_live_id: string | null
  can_live_chat: boolean
  can_qa: boolean
  can_live_polls: boolean
  can_attendee_list: boolean
  can_session: boolean
  track: Track | null
  speakers: SessionSpeaker[]
}

// ── State ─────────────────────────────────────────────────────────────────────

const session       = ref<Session | null>(null)
const tracks        = ref<Track[]>([])
const eventSpeakers = ref<EventSpeaker[]>([])
const activeTab     = ref<'basic' | 'stream'>('basic')
const loading       = ref(true)
const tagInput      = ref('')

// Track CRUD
const showTrackMenu    = ref(false)
const newTrackName     = ref('')
const addingTrack      = ref(false)
const editingTrackId   = ref<number | null>(null)
const editingTrackName = ref('')

// Basic form state (filled on load)
const basic = reactive({
  title:              '',
  description:        '',
  date:               '',
  start_time:         '',
  end_time:           '',
  track_id:           '' as number | '',
  session_place:      '',
  logo_url:           null as string | null,
  capacity:           '' as number | '',
  tags:               [] as string[],
  is_featured:        false,
  is_allowed_to_rate: false,
})

// Stream form state
const stream = reactive({
  is_stream:                false,
  who_will_host:            'self' as string,
  stream_link:              '',
  on_demand_recording_link: '',
  vimeo_live_id:            '',
  can_live_chat:            false,
  can_qa:                   false,
  can_live_polls:           false,
  can_attendee_list:        false,
  can_session:              false,
})

const basicSaving  = ref(false)
const streamSaving = ref(false)
const basicError   = ref('')
const streamError  = ref('')
const spkSaving    = ref(false)

// ── Helpers ───────────────────────────────────────────────────────────────────

function isoToDate(iso: string | null): string {
  if (!iso) return ''
  return iso.slice(0, 10)
}

function isoToTime(iso: string | null): string {
  if (!iso) return ''
  // ISO stored as UTC; display in local time
  return new Date(iso).toTimeString().slice(0, 5)
}

function buildDatetime(date: string, time: string): string | null {
  if (!date) return null
  return time ? `${date}T${time}:00` : `${date}T00:00:00`
}

function initials(name: string | null | undefined): string {
  if (!name) return '?'
  return name.split(' ').slice(0, 2).map(w => w[0] ?? '').join('').toUpperCase()
}

function isSessionSpeaker(sp: EventSpeaker): boolean {
  return session.value?.speakers.some(s => s.id === sp.id) ?? false
}

// ── Load ──────────────────────────────────────────────────────────────────────

async function load() {
  loading.value = true
  try {
    const [sessRes, trkRes, spkRes] = await Promise.all([
      api<any>(`/sessions/${sessionId}`),
      api<any>(`/tracks?event=${id}`),
      api<any>(`/events/${id}/speakers`),
    ])

    const s: Session = sessRes.data
    session.value       = s
    tracks.value        = trkRes.data
    eventSpeakers.value = spkRes.data

    // Populate basic form
    basic.title              = s.title
    basic.description        = s.description ?? ''
    basic.date               = isoToDate(s.starts_at)
    basic.start_time         = isoToTime(s.starts_at)
    basic.end_time           = isoToTime(s.ends_at)
    basic.track_id           = s.track?.id ?? ''
    basic.session_place      = s.session_place ?? ''
    basic.logo_url           = s.logo_url ?? null
    basic.capacity           = s.capacity ?? ''
    basic.tags               = [...(s.tags ?? [])]
    basic.is_featured        = s.is_featured ?? false
    basic.is_allowed_to_rate = s.is_allowed_to_rate ?? false

    // Populate stream form
    stream.is_stream                = s.is_stream ?? false
    stream.who_will_host            = s.who_will_host ?? 'self'
    stream.stream_link              = s.stream_link ?? ''
    stream.on_demand_recording_link = s.on_demand_recording_link ?? ''
    stream.vimeo_live_id            = s.vimeo_live_id ?? ''
    stream.can_live_chat            = s.can_live_chat ?? false
    stream.can_qa                   = s.can_qa ?? false
    stream.can_live_polls           = s.can_live_polls ?? false
    stream.can_attendee_list        = s.can_attendee_list ?? false
    stream.can_session              = s.can_session ?? false
  } catch { /* */ } finally {
    loading.value = false
  }
}

// ── Save basic ────────────────────────────────────────────────────────────────

async function saveBasic() {
  basicError.value = ''
  basicSaving.value = true
  try {
    const res = await api<any>(`/sessions/${sessionId}`, {
      method: 'PUT',
      body: {
        title:              basic.title,
        description:        basic.description || null,
        starts_at:          buildDatetime(basic.date, basic.start_time),
        ends_at:            buildDatetime(basic.date, basic.end_time),
        track_id:           basic.track_id || null,
        session_place:      basic.session_place || null,
        logo_url:           basic.logo_url || null,
        capacity:           basic.capacity || null,
        tags:               basic.tags,
        is_featured:        basic.is_featured,
        is_allowed_to_rate: basic.is_allowed_to_rate,
      },
    })
    session.value = res.data
  } catch (e: any) {
    basicError.value = e?.data?.message || 'Could not save changes.'
  } finally {
    basicSaving.value = false
  }
}

// ── Save stream ───────────────────────────────────────────────────────────────

async function saveStream() {
  streamError.value = ''
  streamSaving.value = true
  try {
    const res = await api<any>(`/sessions/${sessionId}/stream`, {
      method: 'PUT',
      body: {
        is_stream:                stream.is_stream,
        who_will_host:            stream.who_will_host,
        stream_link:              stream.stream_link || null,
        on_demand_recording_link: stream.on_demand_recording_link || null,
        vimeo_live_id:            stream.vimeo_live_id || null,
        can_live_chat:            stream.can_live_chat,
        can_qa:                   stream.can_qa,
        can_live_polls:           stream.can_live_polls,
        can_attendee_list:        stream.can_attendee_list,
        can_session:              stream.can_session,
      },
    })
    session.value = res.data
  } catch (e: any) {
    streamError.value = e?.data?.message || 'Could not save stream settings.'
  } finally {
    streamSaving.value = false
  }
}

// ── Speaker management (edit page uses live add/remove) ───────────────────────

async function toggleSpeaker(sp: EventSpeaker) {
  if (!session.value || spkSaving.value) return
  spkSaving.value = true
  try {
    if (isSessionSpeaker(sp)) {
      await api(`/sessions/${sessionId}/speakers/${sp.id}`, { method: 'DELETE' })
      session.value.speakers = session.value.speakers.filter(s => s.id !== sp.id)
    } else {
      const parts = sp.name.split(' ')
      await api(`/sessions/${sessionId}/speakers`, {
        method: 'POST',
        body: {
          email:      sp.email,
          first_name: parts[0] ?? '',
          last_name:  parts.slice(1).join(' ') || '',
          role:       'speaker',
        },
      })
      // Reload session speakers
      const fresh = await api<any>(`/sessions/${sessionId}`)
      session.value.speakers = fresh.data.speakers ?? session.value.speakers
    }
  } catch { /* */ } finally {
    spkSaving.value = false
  }
}

// ── Track inline CRUD ─────────────────────────────────────────────────────────

async function createTrack() {
  const name = newTrackName.value.trim()
  if (!name) return
  addingTrack.value = true
  try {
    const res = await api<any>('/tracks', { method: 'POST', body: { event: id, name } })
    tracks.value.push(res.data)
    basic.track_id = res.data.id
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
    if (basic.track_id === track.id) basic.track_id = ''
  } catch { /* */ }
}

// ── Tags ──────────────────────────────────────────────────────────────────────

function addTag() {
  const val = tagInput.value.replace(/,\s*$/, '').trim()
  if (val && !basic.tags.includes(val)) basic.tags.push(val)
  tagInput.value = ''
}

function removeTag(i: number) { basic.tags.splice(i, 1) }

function onTagKey(e: KeyboardEvent) {
  if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addTag() }
}

onMounted(load)
</script>

<template>
  <div @click="showTrackMenu = false">

    <!-- Loading skeleton -->
    <div v-if="loading" class="muted text-center py-16">Loading session…</div>

    <template v-else-if="session">
      <!-- Breadcrumb -->
      <div class="flex items-center gap-2 text-[.85rem] mb-5">
        <button
          class="text-brand hover:underline bg-transparent border-0 cursor-pointer p-0"
          @click="router.push(`/org/events/${id}/sessions`)"
        >
          Sessions
        </button>
        <span class="text-muted">/</span>
        <span class="text-ink font-medium truncate max-w-[280px]">{{ session.title }}</span>
      </div>

      <!-- Page header -->
      <div class="mb-5">
        <h2 class="section-title m-0">{{ session.title }}</h2>
      </div>

      <!-- Tab bar -->
      <div class="flex gap-1 border-b border-line mb-6">
        <button
          class="px-4 py-2.5 text-[.9rem] font-medium border-b-2 -mb-px transition-colors"
          :class="activeTab === 'basic'
            ? 'border-brand text-brand'
            : 'border-transparent text-muted hover:text-ink'"
          @click="activeTab = 'basic'"
        >Basic Details</button>
        <button
          class="px-4 py-2.5 text-[.9rem] font-medium border-b-2 -mb-px transition-colors"
          :class="activeTab === 'stream'
            ? 'border-brand text-brand'
            : 'border-transparent text-muted hover:text-ink'"
          @click="activeTab = 'stream'"
        >Stream</button>
      </div>

      <!-- ── Basic Details Tab ───────────────────────────────────────────── -->
      <div v-if="activeTab === 'basic'" class="max-w-2xl">

        <!-- Date + time -->
        <div class="card mb-5 p-5">
          <h3 class="font-semibold text-[.9rem] text-ink mb-4 m-0">Schedule</h3>
          <div class="mb-4">
            <label class="block mb-1.5">Date</label>
            <input v-model="basic.date" type="date" class="m-0 w-full max-w-xs">
          </div>
          <div class="flex gap-3">
            <div class="flex-1 max-w-[160px]">
              <label class="block mb-1.5">Start Time</label>
              <input v-model="basic.start_time" type="time" class="m-0 w-full">
            </div>
            <div class="flex-1 max-w-[160px]">
              <label class="block mb-1.5">End Time</label>
              <input v-model="basic.end_time" type="time" class="m-0 w-full">
            </div>
          </div>
        </div>

        <!-- Core details -->
        <div class="card mb-5 p-5">
          <h3 class="font-semibold text-[.9rem] text-ink mb-4 m-0">Details</h3>

          <div class="mb-4">
            <label class="block mb-1.5">Title <span class="text-[#dc2626]">*</span></label>
            <input v-model="basic.title" placeholder="e.g. Opening Keynote" class="m-0">
          </div>

          <div class="mb-4">
            <label class="block mb-1.5">Session Place</label>
            <input v-model="basic.session_place" placeholder="e.g. Main Hall, Room A" class="m-0">
          </div>

          <!-- Track inline CRUD -->
          <div class="mb-4 relative">
            <label class="block mb-1.5">Track</label>
            <button
              type="button"
              class="w-full flex items-center justify-between px-3 py-2 border border-line rounded-xl bg-white text-[.9rem]"
              @click.stop="showTrackMenu = !showTrackMenu; editingTrackId = null"
            >
              <span>{{ tracks.find(t => t.id === basic.track_id)?.name ?? '— No track —' }}</span>
              <span class="text-muted text-xs">▾</span>
            </button>
            <div
              v-if="showTrackMenu"
              class="absolute left-0 top-full mt-1 z-20 bg-white border border-line rounded-xl shadow-lg py-1 w-full max-h-56 overflow-y-auto"
              @click.stop
            >
              <button
                class="w-full text-left px-4 py-2 text-[.88rem] hover:bg-[#f7f7fb]"
                :class="!basic.track_id ? 'font-semibold text-brand' : ''"
                @click="basic.track_id = ''; showTrackMenu = false"
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
                    :class="basic.track_id === t.id ? 'font-semibold text-brand' : ''"
                    @click="basic.track_id = t.id; showTrackMenu = false"
                  >{{ t.name }}</button>
                  <button class="text-muted text-[.78rem] hover:text-brand px-1 leading-none" @click.stop="startEditTrack(t)" title="Rename">✎</button>
                  <button class="text-muted text-[.78rem] hover:text-[#dc2626] px-1 leading-none" @click.stop="deleteTrack(t)" title="Delete">✕</button>
                </template>
              </div>

              <div class="border-t border-line mt-1 pt-1 px-3 pb-1">
                <div class="flex gap-1.5">
                  <input
                    v-model="newTrackName"
                    placeholder="New track name…"
                    class="flex-1 m-0 py-1 text-[.87rem]"
                    @keydown.enter="createTrack"
                  >
                  <button class="btn sm" :disabled="!newTrackName.trim() || addingTrack" @click="createTrack">Add</button>
                </div>
              </div>
            </div>
          </div>

          <div class="mb-4">
            <label class="block mb-1.5">Description</label>
            <textarea v-model="basic.description" rows="4" placeholder="What is this session about?" class="w-full resize-y m-0" />
          </div>

          <div>
            <label class="block mb-1.5">Session Logo</label>
            <UploadButton
              :preview="basic.logo_url"
              collection="logo"
              @uploaded="basic.logo_url = $event.url"
            />
          </div>
        </div>

        <!-- Speakers -->
        <div class="card mb-5 p-5">
          <h3 class="font-semibold text-[.9rem] text-ink mb-4 m-0">Speakers</h3>
          <div v-if="!eventSpeakers.length" class="muted text-[.84rem]">
            No event speakers yet — add them in <strong>Showcase › Speakers</strong>.
          </div>
          <div v-else class="flex flex-col gap-2">
            <label
              v-for="sp in eventSpeakers"
              :key="sp.id"
              class="flex items-center gap-3 px-4 py-3 border border-line rounded-xl cursor-pointer select-none transition-colors"
              :class="isSessionSpeaker(sp) ? 'bg-brand-soft border-brand/20' : 'bg-white hover:bg-[#fafbfc]'"
            >
              <input
                type="checkbox"
                :checked="isSessionSpeaker(sp)"
                :disabled="spkSaving"
                class="w-4.5 h-4.5 m-0 accent-brand shrink-0"
                @change="toggleSpeaker(sp)"
              >
              <div class="w-8 h-8 rounded-full overflow-hidden shrink-0 bg-brand-soft flex items-center justify-center text-brand text-[.72rem] font-semibold">
                <img v-if="sp.image_url" :src="sp.image_url" :alt="sp.name" class="w-full h-full object-cover">
                <span v-else>{{ initials(sp.name) }}</span>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-semibold text-[.9rem] text-ink leading-tight truncate">{{ sp.name }}</div>
                <div class="muted text-[.78rem] truncate">{{ sp.designation || sp.email }}</div>
              </div>
              <span v-if="isSessionSpeaker(sp)" class="text-[.73rem] font-medium text-brand shrink-0">Added</span>
            </label>
          </div>
        </div>

        <!-- Tags + options -->
        <div class="card mb-5 p-5">
          <h3 class="font-semibold text-[.9rem] text-ink mb-4 m-0">Tags &amp; Options</h3>

          <div class="mb-5">
            <label class="block mb-1.5">Tags</label>
            <div class="flex flex-wrap gap-1.5 mb-2">
              <span
                v-for="(tag, i) in basic.tags" :key="i"
                class="flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-brand-soft text-brand text-[.8rem] font-medium"
              >
                {{ tag }}
                <button class="bg-transparent border-0 p-0 cursor-pointer text-brand leading-none text-[.9rem]" @click="removeTag(i)">×</button>
              </span>
            </div>
            <input
              v-model="tagInput"
              placeholder="Type a tag and press Enter or comma"
              class="m-0"
              @keydown="onTagKey"
              @blur="addTag"
            >
          </div>

          <div class="flex flex-col gap-3">
            <label class="flex items-center gap-3 cursor-pointer select-none">
              <input v-model="basic.is_allowed_to_rate" type="checkbox" class="w-4.5 h-4.5 m-0 accent-brand">
              <span class="text-[.93rem] font-medium text-ink">Allow attendee ratings</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer select-none">
              <input v-model="basic.is_featured" type="checkbox" class="w-4.5 h-4.5 m-0 accent-brand">
              <span class="text-[.93rem] font-medium text-ink">Featured session</span>
            </label>
          </div>
        </div>

        <p v-if="basicError" class="error mb-3">{{ basicError }}</p>

        <div class="flex justify-end">
          <button
            class="btn"
            :disabled="!basic.title.trim() || basicSaving"
            @click="saveBasic"
          >
            {{ basicSaving ? 'Saving…' : 'SAVE CHANGES' }}
          </button>
        </div>
      </div>

      <!-- ── Stream Tab ──────────────────────────────────────────────────── -->
      <div v-else-if="activeTab === 'stream'" class="max-w-2xl">

        <!-- Enable stream -->
        <div class="card mb-5 p-5">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="font-semibold text-[.9rem] text-ink m-0 mb-0.5">Enable Streaming</h3>
              <p class="muted text-[.83rem] m-0">Turn on to configure live stream settings for this session.</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer select-none">
              <input v-model="stream.is_stream" type="checkbox" class="sr-only peer">
              <div
                class="w-10 h-6 rounded-full transition-colors peer-checked:bg-brand bg-[#d1d1d8]
                       after:content-[''] after:absolute after:top-0.5 after:left-0.5
                       after:bg-white after:rounded-full after:h-5 after:w-5
                       after:transition-all peer-checked:after:translate-x-4"
              />
            </label>
          </div>
        </div>

        <template v-if="stream.is_stream">
          <!-- Host settings -->
          <div class="card mb-5 p-5">
            <h3 class="font-semibold text-[.9rem] text-ink mb-4 m-0">Streaming Settings</h3>

            <div class="mb-4">
              <label class="block mb-1.5">Who will host?</label>
              <select v-model="stream.who_will_host" class="m-0 w-full max-w-xs">
                <option value="self">Self-hosted</option>
                <option value="zoom">Zoom</option>
                <option value="rtmp">RTMP</option>
              </select>
            </div>

            <div class="mb-4">
              <label class="block mb-1.5">Stream Link</label>
              <input v-model="stream.stream_link" type="url" placeholder="https://…" class="m-0">
            </div>

            <div class="mb-4">
              <label class="block mb-1.5">On-Demand Recording Link</label>
              <input v-model="stream.on_demand_recording_link" type="url" placeholder="https://…" class="m-0">
            </div>

            <div>
              <label class="block mb-1.5">Vimeo Live ID</label>
              <input v-model="stream.vimeo_live_id" placeholder="e.g. 123456789" class="m-0">
            </div>
          </div>

          <!-- Engagement options -->
          <div class="card mb-5 p-5">
            <h3 class="font-semibold text-[.9rem] text-ink mb-4 m-0">Engagement Options</h3>
            <div class="flex flex-col gap-4">
              <label class="flex items-start gap-3 cursor-pointer select-none">
                <input v-model="stream.can_live_chat" type="checkbox" class="w-4.5 h-4.5 m-0 mt-0.5 accent-brand shrink-0">
                <div>
                  <div class="font-medium text-ink text-[.93rem]">Live Chat</div>
                  <div class="muted text-[.8rem]">Allow attendees to send messages during the session.</div>
                </div>
              </label>
              <label class="flex items-start gap-3 cursor-pointer select-none">
                <input v-model="stream.can_qa" type="checkbox" class="w-4.5 h-4.5 m-0 mt-0.5 accent-brand shrink-0">
                <div>
                  <div class="font-medium text-ink text-[.93rem]">Q&amp;A</div>
                  <div class="muted text-[.8rem]">Let attendees submit and upvote questions.</div>
                </div>
              </label>
              <label class="flex items-start gap-3 cursor-pointer select-none">
                <input v-model="stream.can_live_polls" type="checkbox" class="w-4.5 h-4.5 m-0 mt-0.5 accent-brand shrink-0">
                <div>
                  <div class="font-medium text-ink text-[.93rem]">Live Polls</div>
                  <div class="muted text-[.8rem]">Run real-time polls for audience participation.</div>
                </div>
              </label>
              <label class="flex items-start gap-3 cursor-pointer select-none">
                <input v-model="stream.can_attendee_list" type="checkbox" class="w-4.5 h-4.5 m-0 mt-0.5 accent-brand shrink-0">
                <div>
                  <div class="font-medium text-ink text-[.93rem]">Attendee List</div>
                  <div class="muted text-[.8rem]">Show who is attending this session.</div>
                </div>
              </label>
              <label class="flex items-start gap-3 cursor-pointer select-none">
                <input v-model="stream.can_session" type="checkbox" class="w-4.5 h-4.5 m-0 mt-0.5 accent-brand shrink-0">
                <div>
                  <div class="font-medium text-ink text-[.93rem]">Sessions Panel</div>
                  <div class="muted text-[.8rem]">Display related sessions panel to attendees.</div>
                </div>
              </label>
            </div>
          </div>
        </template>

        <p v-if="streamError" class="error mb-3">{{ streamError }}</p>

        <div class="flex justify-end">
          <button class="btn" :disabled="streamSaving" @click="saveStream">
            {{ streamSaving ? 'Saving…' : 'SAVE STREAM SETTINGS' }}
          </button>
        </div>
      </div>
    </template>

    <!-- Not found -->
    <div v-else class="card text-center py-12 muted">
      Session not found.
      <button class="btn ghost ml-3" @click="router.push(`/org/events/${id}/sessions`)">Back to Sessions</button>
    </div>
  </div>
</template>
