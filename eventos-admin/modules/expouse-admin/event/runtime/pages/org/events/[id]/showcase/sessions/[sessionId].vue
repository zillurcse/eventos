<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { toast } from 'vue-sonner'

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

interface Sponsor { id: string; name: string; logo_url?: string | null }

interface SessionDocument { name: string; url: string }

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
  sponsors: Sponsor[]
  documents: SessionDocument[]
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
  qa_moderation: boolean
  track: Track | null
  speakers: SessionSpeaker[]
}

interface PollOption { id: string; text: string; votes: number }

interface Poll {
  id: number
  question: string
  options: PollOption[]
  total_votes: number
  status: 'draft' | 'live' | 'closed'
  is_active: boolean
  show_results: boolean
}

interface PanelMessage {
  id: number
  kind: 'chat' | 'question'
  body: string
  author: string
  upvotes: number
  status: 'published' | 'pending' | 'rejected'
  is_hidden: boolean
  is_pinned: boolean
  is_answered: boolean
  created_at: string | null
}

// ── State ─────────────────────────────────────────────────────────────────────

const session       = ref<Session | null>(null)
const tracks        = ref<Track[]>([])
const eventSpeakers = ref<EventSpeaker[]>([])
const sponsorsList    = ref<Sponsor[]>([])
const speakerModal    = ref(false)
const sponsorModal    = ref(false)
const iconChooserOpen = ref(false)

// Older sessions stored an uploaded image URL in icon_url; new ones store a
// catalog icon key from the /icons registry. Render whichever we have.
const iconIsImage = computed(() => !!basic.icon_url && /^https?:\/\//.test(basic.icon_url))
const activeTab     = ref<'basic' | 'stream' | 'engagement'>('basic')
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
  icon_url:           null as string | null,
  sponsors:           [] as Sponsor[],
  documents:          [] as SessionDocument[],
  capacity:           '' as number | '',
  tags:               [] as string[],
  is_featured:        false,
  is_allowed_to_rate: false,
})

// Stream form state
const stream = reactive({
  is_stream:                false,
  // Manual override of the schedule-driven player ("go live now" / "we're done").
  status:                   'scheduled' as Session['status'],
  who_will_host:            'self' as string,
  stream_link:              '',
  on_demand_recording_link: '',
  vimeo_live_id:            '',
  can_live_chat:            false,
  can_qa:                   false,
  can_live_polls:           false,
  can_attendee_list:        false,
  can_session:              false,
  qa_moderation:            false,
})

// Host-aware label/placeholder/help for the stream link field.
const HOST_LINK: Record<string, { label: string; placeholder: string; hint: string }> = {
  youtube: { label: 'YouTube Live Link', placeholder: 'https://www.youtube.com/live/…', hint: 'Paste your YouTube live or watch URL. It plays embedded on the event page.' },
  agora:   { label: 'Agora Channel (optional)', placeholder: 'Leave blank to auto-create a channel', hint: 'Broadcast video embedded on the event page: the speaker goes on camera, attendees watch. Best for a large audience. Needs an App ID + Certificate in Settings › Video.' },
  jitsi:   { label: 'Jitsi Room or Link (optional)', placeholder: 'Leave blank to auto-create a private room', hint: 'Free open-source video that runs embedded on the event page. Leave blank to auto-generate a room, or paste a meet.jit.si link/room name.' },
  zoom:    { label: 'Zoom Link',         placeholder: 'https://zoom.us/j/…', hint: 'Embeds inside the event page via the Zoom Web SDK (needs Zoom keys configured on the server).' },
  meet:    { label: 'Google Meet Link',  placeholder: 'https://meet.google.com/abc-defg-hij', hint: 'Attendees open Meet in a new tab because Google Meet cannot be embedded.' },
  rtmp:    { label: 'Player URL',        placeholder: 'https://…', hint: 'The public player URL for your RTMP stream.' },
  self:    { label: 'Stream Link',       placeholder: 'https://…', hint: 'The public URL where attendees watch the stream.' },
}
const hostLink = computed(() => HOST_LINK[stream.who_will_host] ?? HOST_LINK.self)

// Agora takes a channel name and Jitsi a room name — neither is a URL, so the
// browser must not demand one (the API validates the same way).
const hostLinkIsUrl = computed(() => !['agora', 'jitsi'].includes(stream.who_will_host))

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
  return session.value?.speakers?.some(s => s.id === sp.id) ?? false
}

// End must be after start when both are set.
const timeError = computed(() =>
  basic.start_time && basic.end_time && basic.end_time <= basic.start_time
    ? 'End Time must be after Start Time'
    : '',
)

// Date + Start Time + End Time are required for a valid schedule.
const canSaveBasic = computed(() =>
  !!basic.title.trim() && !!basic.date && !!basic.start_time && !!basic.end_time && !timeError.value,
)

// ── Load ──────────────────────────────────────────────────────────────────────

async function load() {
  loading.value = true
  try {
    const [sessRes, trkRes, spkRes, sponRes] = await Promise.all([
      api<any>(`/sessions/${sessionId}`),
      api<any>(`/tracks?event=${id}`),
      api<any>(`/events/${id}/speakers`),
      api<any>(`/exhibitors?event=${id}&type=sponsor`),
    ])

    const s: Session = sessRes.data
    session.value       = s
    tracks.value        = trkRes.data
    eventSpeakers.value = spkRes.data
    sponsorsList.value  = (sponRes.data || []).map((e: any) => ({ id: e.id, name: e.name, logo_url: e.logo_url ?? null }))

    // Populate basic form
    basic.title              = s.title
    basic.description        = s.description ?? ''
    basic.date               = isoToDate(s.starts_at)
    basic.start_time         = isoToTime(s.starts_at)
    basic.end_time           = isoToTime(s.ends_at)
    basic.track_id           = s.track?.id ?? ''
    basic.session_place      = s.session_place ?? ''
    basic.logo_url           = s.logo_url ?? null
    basic.icon_url           = s.icon_url ?? null
    basic.sponsors           = [...(s.sponsors ?? [])]
    basic.documents          = [...(s.documents ?? [])]
    basic.capacity           = s.capacity ?? ''
    basic.tags               = [...(s.tags ?? [])]
    basic.is_featured        = s.is_featured ?? false
    basic.is_allowed_to_rate = s.is_allowed_to_rate ?? false

    // Populate stream form
    stream.is_stream                = s.is_stream ?? false
    stream.status                   = s.status ?? 'scheduled'
    stream.who_will_host            = s.who_will_host ?? 'self'
    stream.stream_link              = s.stream_link ?? ''
    stream.on_demand_recording_link = s.on_demand_recording_link ?? ''
    stream.vimeo_live_id            = s.vimeo_live_id ?? ''
    stream.can_live_chat            = s.can_live_chat ?? false
    stream.can_qa                   = s.can_qa ?? false
    stream.can_live_polls           = s.can_live_polls ?? false
    stream.can_attendee_list        = s.can_attendee_list ?? false
    stream.can_session              = s.can_session ?? false
    stream.qa_moderation            = s.qa_moderation ?? false
  } catch { /* */ } finally {
    loading.value = false
  }
}

// ── Engagement: polls + chat/Q&A moderation ──────────────────────────────────
// The host moderates in the moment from the attendee watch page; this is the
// organizer's side — author polls before the session, clean up during or after.

const polls        = ref<Poll[]>([])
const messages     = ref<PanelMessage[]>([])
const modKind      = ref<'question' | 'chat'>('question')
const engLoading   = ref(false)
const pollSaving   = ref(false)

const pollDraft = reactive({
  question:     '',
  options:      ['', ''] as string[],
  show_results: true,
})
const composerOpen = ref(false)

const canSavePoll = computed(() =>
  !!pollDraft.question.trim() && pollDraft.options.filter(o => o.trim()).length >= 2,
)

async function loadEngagement() {
  engLoading.value = true
  try {
    const [pollRes, msgRes] = await Promise.all([
      api<any>(`/sessions/${sessionId}/polls`),
      api<any>(`/sessions/${sessionId}/messages?kind=${modKind.value}`),
    ])
    polls.value    = pollRes.data
    messages.value = msgRes.data
  } catch { /* */ } finally {
    engLoading.value = false
  }
}

function resetPollDraft() {
  pollDraft.question = ''
  pollDraft.options = ['', '']
  pollDraft.show_results = true
  composerOpen.value = false
}

// A poll saved here starts as a draft unless the organizer launches it outright,
// so writing the agenda's polls in advance never leaks them to attendees.
async function savePoll(status: 'draft' | 'live') {
  if (!canSavePoll.value || pollSaving.value) return
  pollSaving.value = true
  try {
    await api(`/sessions/${sessionId}/polls`, {
      method: 'POST',
      body: {
        question:     pollDraft.question.trim(),
        options:      pollDraft.options.map(o => o.trim()).filter(Boolean),
        status,
        show_results: pollDraft.show_results,
      },
    })
    resetPollDraft()
    await loadEngagement()
    toast.success(status === 'live' ? 'Poll launched' : 'Poll saved as draft')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not save the poll.')
  } finally {
    pollSaving.value = false
  }
}

async function patchPoll(p: Poll, patch: Record<string, unknown>, note: string) {
  try {
    await api(`/session-polls/${p.id}`, { method: 'PATCH', body: patch })
    await loadEngagement()
    toast.success(note)
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not update the poll.')
  }
}

async function deletePoll(p: Poll) {
  if (!confirm('Delete this poll and every vote cast on it?')) return
  try {
    await api(`/session-polls/${p.id}`, { method: 'DELETE' })
    await loadEngagement()
    toast.success('Poll deleted')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not delete the poll.')
  }
}

async function patchMessage(m: PanelMessage, patch: Record<string, unknown>, note: string) {
  try {
    await api(`/session-messages/${m.id}`, { method: 'PATCH', body: patch })
    await loadEngagement()
    toast.success(note)
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not update the message.')
  }
}

async function deleteMessage(m: PanelMessage) {
  if (!confirm('Delete this message? Attendees will no longer see it.')) return
  try {
    await api(`/session-messages/${m.id}`, { method: 'DELETE' })
    await loadEngagement()
    toast.success('Message deleted')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not delete the message.')
  }
}

function addPollOption() { if (pollDraft.options.length < 8) pollDraft.options.push('') }
function removePollOption(i: number) { if (pollDraft.options.length > 2) pollDraft.options.splice(i, 1) }

function pct(o: PollOption, p: Poll) {
  return p.total_votes ? Math.round((o.votes / p.total_votes) * 100) : 0
}

const pendingCount = computed(() => messages.value.filter(m => m.status === 'pending').length)

// Load the engagement data lazily — only when the organizer opens that tab.
watch(activeTab, (t) => { if (t === 'engagement') loadEngagement() })
watch(modKind, () => { if (activeTab.value === 'engagement') loadEngagement() })

// ── Save basic ────────────────────────────────────────────────────────────────

async function saveBasic() {
  if (timeError.value) { basicError.value = timeError.value; return }
  if (!basic.date || !basic.start_time || !basic.end_time) {
    basicError.value = 'Set the date, start time and end time.'
    return
  }
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
        icon_url:           basic.icon_url || null,
        sponsors:           basic.sponsors,
        documents:          basic.documents,
        capacity:           basic.capacity || null,
        tags:               basic.tags,
        is_featured:        basic.is_featured,
        is_allowed_to_rate: basic.is_allowed_to_rate,
      },
    })
    session.value = { ...res.data, speakers: res.data.speakers ?? session.value?.speakers ?? [] }
    toast.success('Session details saved')
  } catch (e: any) {
    basicError.value = e?.data?.message || 'Could not save changes.'
    toast.error(basicError.value)
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
        status:                   stream.status,
        who_will_host:            stream.who_will_host,
        stream_link:              stream.stream_link || null,
        on_demand_recording_link: stream.on_demand_recording_link || null,
        vimeo_live_id:            stream.vimeo_live_id || null,
        can_live_chat:            stream.can_live_chat,
        can_qa:                   stream.can_qa,
        can_live_polls:           stream.can_live_polls,
        can_attendee_list:        stream.can_attendee_list,
        can_session:              stream.can_session,
        qa_moderation:            stream.qa_moderation,
      },
    })
    session.value = { ...res.data, speakers: res.data.speakers ?? session.value?.speakers ?? [] }
    toast.success('Stream settings saved')
  } catch (e: any) {
    streamError.value = e?.data?.message || 'Could not save stream settings.'
    toast.error(streamError.value)
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
      toast.success(`${sp.name} removed from this session`)
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
      toast.success(`${sp.name} added to this session`)
    }
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not update speakers.')
  } finally {
    spkSaving.value = false
  }
}

// The picker emits a speaker id; add/remove runs live against the pivot.
function toggleSpeakerById(spId: string) {
  const sp = eventSpeakers.value.find(s => s.id === spId)
  if (sp) toggleSpeaker(sp)
}

// ── Sponsors (draft state — saved with SAVE CHANGES) ─────────────────────────

function toggleSponsor(s: Sponsor) {
  const i = basic.sponsors.findIndex(x => x.id === s.id)
  if (i >= 0) basic.sponsors.splice(i, 1)
  else basic.sponsors.push({ id: s.id, name: s.name, logo_url: s.logo_url ?? null })
}

function removeSponsor(sid: string) {
  basic.sponsors = basic.sponsors.filter(x => x.id !== sid)
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
          @click="router.push(`/org/events/${id}/showcase/sessions`)"
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
        <button
          class="px-4 py-2.5 text-[.9rem] font-medium border-b-2 -mb-px transition-colors"
          :class="activeTab === 'engagement'
            ? 'border-brand text-brand'
            : 'border-transparent text-muted hover:text-ink'"
          @click="activeTab = 'engagement'"
        >Engagement</button>
      </div>

      <!-- ── Basic Details Tab ───────────────────────────────────────────── -->
      <div v-if="activeTab === 'basic'" class="max-w-2xl">

        <!-- Date + time -->
        <div class="card mb-5 p-5">
          <h3 class="font-semibold text-[.9rem] text-ink mb-4 m-0">Schedule</h3>
          <div class="mb-4">
            <label class="block mb-1.5">Date <span class="text-[#dc2626]">*</span></label>
            <input v-model="basic.date" type="date" class="m-0 w-full max-w-xs">
          </div>
          <div class="flex gap-3">
            <div class="flex-1 max-w-[160px]">
              <label class="block mb-1.5">Start Time <span class="text-[#dc2626]">*</span></label>
              <input v-model="basic.start_time" type="time" class="m-0 w-full">
            </div>
            <div class="flex-1 max-w-[160px]">
              <label class="block mb-1.5">End Time <span class="text-[#dc2626]">*</span></label>
              <input v-model="basic.end_time" type="time" class="m-0 w-full">
            </div>
          </div>
          <p v-if="timeError" class="error mt-3 mb-0">{{ timeError }}</p>
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
            <SessionDescriptionEditor v-model="basic.description" />
          </div>

          <div class="flex gap-6">
            <div>
              <label class="block mb-1.5">Logo</label>
              <ImageField
                :model-value="basic.logo_url"
                :aspect="1"
                :output-width="400"
                :output-height="400"
                collection="session_logo"
                card-width="120px"
                :gallery-path="`/events/${id}/gallery`"
                @update:model-value="basic.logo_url = (Array.isArray($event) ? $event[0] : $event) || null"
              />
            </div>
            <div>
              <label class="block mb-1.5">Icon</label>
              <button
                type="button"
                class="w-[120px] h-[120px] rounded-xl border border-dashed border-[#d7dae1] flex items-center justify-center bg-[#fafbfc] cursor-pointer hover:border-brand overflow-hidden"
                @click="iconChooserOpen = true"
              >
                <img v-if="iconIsImage" :src="basic.icon_url!" alt="Session icon" class="w-full h-full object-cover">
                <AppIcon v-else-if="basic.icon_url" :name="basic.icon_url" class="w-10 h-10 text-ink" />
                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="w-9 h-9 text-muted"><rect x="3" y="5" width="18" height="14" rx="2"/><rect x="7" y="9" width="10" height="6" rx="1"/></svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Speakers + Sponsors -->
        <div class="card mb-5 p-5">
          <h3 class="font-semibold text-[.9rem] text-ink mb-4 m-0">Speakers</h3>
          <div class="flex flex-wrap gap-2 items-center mb-5">
            <button
              type="button"
              class="w-16 h-16 border-2 border-dashed border-line rounded-xl flex items-center justify-center text-2xl text-muted hover:border-brand hover:text-brand transition-colors"
              @click.stop="speakerModal = true"
            >+</button>
            <div
              v-for="sp in session?.speakers ?? []"
              :key="sp.id"
              class="relative w-16 h-16 rounded-xl overflow-hidden bg-brand-soft text-brand flex items-center justify-center text-[.85rem] font-bold border border-line"
              :title="sp.name"
            >
              <img v-if="sp.image_url" :src="sp.image_url" :alt="sp.name" class="w-full h-full object-cover">
              <span v-else>{{ initials(sp.name) }}</span>
              <button
                class="absolute top-0.5 right-0.5 w-4 h-4 bg-white text-brand border border-line rounded-full text-[.7rem] leading-none flex items-center justify-center shadow"
                :disabled="spkSaving"
                @click.stop="toggleSpeakerById(sp.id)"
              >×</button>
            </div>
          </div>
          <p v-if="!eventSpeakers.length" class="muted text-[.84rem] m-0 mb-5">
            No event speakers yet. Add them in <strong>Showcase › Speakers</strong>.
          </p>

          <h3 class="font-semibold text-[.9rem] text-ink mb-4 m-0">Session Sponsors</h3>
          <div class="flex flex-wrap gap-2 items-center">
            <button
              type="button"
              class="w-16 h-16 border-2 border-dashed border-line rounded-xl flex items-center justify-center text-2xl text-muted hover:border-brand hover:text-brand transition-colors"
              @click.stop="sponsorModal = true"
            >+</button>
            <div
              v-for="sp in basic.sponsors"
              :key="sp.id"
              class="relative w-16 h-16 rounded-xl overflow-hidden bg-[#f1f1f5] text-muted flex items-center justify-center text-[.8rem] font-bold border border-line p-1 text-center"
              :title="sp.name"
            >
              <img v-if="sp.logo_url" :src="sp.logo_url" :alt="sp.name" class="w-full h-full object-contain">
              <span v-else class="leading-tight line-clamp-2">{{ sp.name }}</span>
              <button
                class="absolute top-0.5 right-0.5 w-4 h-4 bg-white text-brand border border-line rounded-full text-[.7rem] leading-none flex items-center justify-center shadow"
                @click.stop="removeSponsor(sp.id)"
              >×</button>
            </div>
          </div>
        </div>

        <!-- Documents -->
        <div class="card mb-5 p-5">
          <h3 class="font-semibold text-[.9rem] text-ink mb-4 m-0">Documents</h3>
          <SessionDocumentUploader v-model="basic.documents" @error="basicError = $event" />
        </div>

        <!-- Tags + options -->
        <div class="card mb-5 p-5">
          <h3 class="font-semibold text-[.9rem] text-ink mb-4 m-0">Tags &amp; Options</h3>

          <div class="mb-5">
            <label class="block mb-1.5">Custom Tags</label>
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
              placeholder="Add tag &amp; press enter"
              class="m-0"
              @keydown="onTagKey"
              @blur="addTag"
            >
          </div>

          <div class="flex flex-col gap-3">
            <label class="flex items-center gap-3 cursor-pointer select-none">
              <input v-model="basic.is_allowed_to_rate" type="checkbox" class="w-4.5 h-4.5 m-0 accent-brand">
              <span class="text-[.93rem] font-medium text-ink">Attendees can rate this session</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer select-none">
              <input v-model="basic.is_featured" type="checkbox" class="w-4.5 h-4.5 m-0 accent-brand">
              <span class="text-[.93rem] font-medium text-ink">Featured schedule</span>
            </label>
          </div>
        </div>

        <p v-if="basicError" class="error mb-3">{{ basicError }}</p>

        <div class="flex justify-end">
          <button
            class="btn"
            :disabled="!canSaveBasic || basicSaving"
            @click="saveBasic"
          >
            {{ basicSaving ? 'Saving…' : 'SAVE CHANGES' }}
          </button>
        </div>

        <SessionSpeakerPicker
          v-if="speakerModal"
          :speakers="eventSpeakers"
          :selected-ids="(session?.speakers ?? []).map(s => s.id)"
          @close="speakerModal = false"
          @toggle="toggleSpeakerById"
        />

        <SessionSponsorPicker
          v-if="sponsorModal"
          :sponsors="sponsorsList"
          :selected="basic.sponsors"
          @close="sponsorModal = false"
          @toggle="toggleSponsor"
        />

        <IconChooserModal
          v-if="iconChooserOpen"
          :model-value="iconIsImage ? '' : basic.icon_url"
          title="Choose Session Icon"
          @select="basic.icon_url = $event"
          @close="iconChooserOpen = false"
        />
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
                <option value="youtube">YouTube</option>
                <option value="vimeo">Vimeo</option>
                <option value="agora">Agora (in-page broadcast)</option>
                <option value="jitsi">Jitsi (in-page video)</option>
                <option value="zoom">Zoom</option>
                <option value="meet">Google Meet</option>
                <option value="rtmp">RTMP</option>
              </select>
            </div>

            <!-- Vimeo uses a numeric Live ID; every other host uses a link. -->
            <div v-if="stream.who_will_host === 'vimeo'" class="mb-4">
              <label class="block mb-1.5">Vimeo Live ID</label>
              <input v-model="stream.vimeo_live_id" placeholder="e.g. 123456789" class="m-0">
              <p class="muted text-[.8rem] mt-1.5 mb-0">The numeric ID of your Vimeo live event. It embeds on the event page.</p>
            </div>

            <div v-else class="mb-4">
              <label class="block mb-1.5">{{ hostLink.label }}</label>
              <input v-model="stream.stream_link" :type="hostLinkIsUrl ? 'url' : 'text'" :placeholder="hostLink.placeholder" class="m-0">
              <p class="muted text-[.8rem] mt-1.5 mb-0">{{ hostLink.hint }}</p>
            </div>

            <div>
              <label class="block mb-1.5">On-Demand Recording Link</label>
              <input v-model="stream.on_demand_recording_link" type="url" placeholder="https://…" class="m-0">
              <p class="muted text-[.8rem] mt-1.5 mb-0">Optional. Shown as a replay after the session ends.</p>
            </div>
          </div>

          <!-- Broadcast state -->
          <div class="card mb-5 p-5">
            <h3 class="font-semibold text-[.9rem] text-ink mb-1 m-0">Broadcast State</h3>
            <p class="muted text-[.83rem] mt-0 mb-4">
              By schedule, the player opens 15 minutes before the start time and stays up
              30 minutes past the end. Override it here when you run early or late.
            </p>
            <select v-model="stream.status" class="m-0 w-full max-w-xs">
              <option value="scheduled">Follow the schedule</option>
              <option value="live">Live now (open the player)</option>
              <option value="ended">Ended (show the replay)</option>
              <option value="canceled">Canceled</option>
            </select>
          </div>
        </template>

        <!-- Engagement options apply to any session, streamed or in-person —
             an on-site talk still wants Q&A and polls. -->
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
              <!-- Pre-moderation only means anything if Q&A is on at all. -->
              <label v-if="stream.can_qa" class="flex items-start gap-3 cursor-pointer select-none ml-8 pl-0">
                <input v-model="stream.qa_moderation" type="checkbox" class="w-4.5 h-4.5 m-0 mt-0.5 accent-brand shrink-0">
                <div>
                  <div class="font-medium text-ink text-[.93rem]">Review questions before they appear</div>
                  <div class="muted text-[.8rem]">
                    Questions wait in a pending queue until the session host approves them.
                    The asker still sees their own while it waits.
                  </div>
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

        <p v-if="streamError" class="error mb-3">{{ streamError }}</p>

        <div class="flex justify-end">
          <button class="btn" :disabled="streamSaving" @click="saveStream">
            {{ streamSaving ? 'Saving…' : 'SAVE STREAM SETTINGS' }}
          </button>
        </div>
      </div>

      <!-- ── Engagement Tab ──────────────────────────────────────────────── -->
      <div v-else-if="activeTab === 'engagement'" class="max-w-2xl">

        <!-- Polls -->
        <div class="card mb-5 p-5">
          <div class="flex items-center justify-between mb-1">
            <h3 class="font-semibold text-[.9rem] text-ink m-0">Live Polls</h3>
            <button v-if="!composerOpen" class="btn sm" @click="composerOpen = true">+ NEW POLL</button>
          </div>
          <p class="muted text-[.83rem] mt-0 mb-4">
            Attendees vote from the session watch page. A draft stays hidden until you or the host launches it.
          </p>

          <!-- Composer -->
          <div v-if="composerOpen" class="border border-line rounded-xl p-4 mb-5 bg-[#fcfcfd]">
            <div class="mb-3">
              <label class="block mb-1.5">Question</label>
              <input v-model="pollDraft.question" placeholder="What do you want to ask?" maxlength="300" class="m-0">
            </div>

            <label class="block mb-1.5">Options</label>
            <div v-for="(_, i) in pollDraft.options" :key="i" class="flex items-center gap-2 mb-2">
              <input v-model="pollDraft.options[i]" :placeholder="`Option ${i + 1}`" maxlength="200" class="m-0 flex-1">
              <button
                v-if="pollDraft.options.length > 2"
                class="text-muted hover:text-[#dc2626] px-1 leading-none text-lg"
                title="Remove option"
                @click="removePollOption(i)"
              >×</button>
            </div>
            <button v-if="pollDraft.options.length < 8" class="text-brand text-[.8rem] font-medium mb-4" @click="addPollOption">
              + Add option
            </button>

            <label class="flex items-center gap-3 cursor-pointer select-none mb-4">
              <input v-model="pollDraft.show_results" type="checkbox" class="w-4.5 h-4.5 m-0 accent-brand">
              <span class="text-[.88rem] text-ink">Show results to attendees while voting is open</span>
            </label>

            <div class="flex justify-end gap-2">
              <button class="btn ghost sm" @click="resetPollDraft">Cancel</button>
              <button class="btn ghost sm" :disabled="!canSavePoll || pollSaving" @click="savePoll('draft')">Save draft</button>
              <button class="btn sm" :disabled="!canSavePoll || pollSaving" @click="savePoll('live')">
                {{ pollSaving ? 'Launching…' : 'Launch now' }}
              </button>
            </div>
          </div>

          <p v-if="engLoading && !polls.length" class="muted text-[.84rem]">Loading…</p>
          <p v-else-if="!polls.length" class="muted text-[.84rem]">No polls for this session yet.</p>

          <div v-for="p in polls" :key="p.id" class="border border-line rounded-xl p-4 mb-3">
            <div class="flex items-start justify-between gap-3 mb-3">
              <span class="font-semibold text-[.9rem] text-ink">{{ p.question }}</span>
              <span
                class="shrink-0 text-[.62rem] font-bold uppercase tracking-wide px-2 py-0.5 rounded"
                :class="{
                  'bg-[#fee2e2] text-[#b91c1c]': p.status === 'live',
                  'bg-[#fef3c7] text-[#b45309]': p.status === 'draft',
                  'bg-[#e2e8f0] text-[#475569]': p.status === 'closed',
                }"
              >{{ p.status }}</span>
            </div>

            <div v-for="o in p.options" :key="o.id" class="relative border border-line rounded-lg px-3 py-2 mb-1.5 overflow-hidden">
              <span class="absolute left-0 top-0 bottom-0 bg-brand-soft transition-[width]" :style="{ width: pct(o, p) + '%' }" />
              <span class="relative flex items-center justify-between text-[.85rem] text-ink">
                <span>{{ o.text }}</span>
                <span class="font-bold text-brand">{{ pct(o, p) }}% · {{ o.votes }}</span>
              </span>
            </div>

            <div class="muted text-[.76rem] mt-2">
              {{ p.total_votes }} vote{{ p.total_votes === 1 ? '' : 's' }}
              <template v-if="!p.show_results && p.status !== 'closed'"> · results hidden from attendees</template>
            </div>

            <div class="flex flex-wrap gap-2 mt-3">
              <button
                v-if="p.status !== 'live'"
                class="btn sm"
                @click="patchPoll(p, { status: 'live' }, p.status === 'draft' ? 'Poll launched' : 'Poll reopened')"
              >{{ p.status === 'draft' ? 'Launch' : 'Reopen' }}</button>
              <button v-else class="btn ghost sm" @click="patchPoll(p, { status: 'closed' }, 'Voting closed')">Close voting</button>
              <button
                class="btn ghost sm"
                @click="patchPoll(p, { show_results: !p.show_results }, p.show_results ? 'Results hidden' : 'Results shown')"
              >{{ p.show_results ? 'Hide results' : 'Show results' }}</button>
              <button class="btn ghost sm text-[#dc2626]" @click="deletePoll(p)">Delete</button>
            </div>
          </div>
        </div>

        <!-- Moderation -->
        <div class="card mb-5 p-5">
          <h3 class="font-semibold text-[.9rem] text-ink m-0 mb-1">Moderation</h3>
          <p class="muted text-[.83rem] mt-0 mb-4">
            Everything attendees posted in this session, including what's hidden or awaiting approval.
            The host can do all of this live from the watch page too.
          </p>

          <div class="flex gap-1 border-b border-line mb-4">
            <button
              class="px-3 py-2 text-[.85rem] font-medium border-b-2 -mb-px transition-colors"
              :class="modKind === 'question' ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink'"
              @click="modKind = 'question'"
            >
              Q&amp;A
              <span v-if="pendingCount" class="ml-1 px-1.5 rounded-full bg-[#dc2626] text-white text-[.62rem] font-bold">{{ pendingCount }}</span>
            </button>
            <button
              class="px-3 py-2 text-[.85rem] font-medium border-b-2 -mb-px transition-colors"
              :class="modKind === 'chat' ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink'"
              @click="modKind = 'chat'"
            >Chat</button>
          </div>

          <p v-if="engLoading && !messages.length" class="muted text-[.84rem]">Loading…</p>
          <p v-else-if="!messages.length" class="muted text-[.84rem]">
            Nothing posted here yet.
          </p>

          <div
            v-for="m in messages" :key="m.id"
            class="flex items-start gap-3 py-3 border-b border-line last:border-0"
            :class="{ 'opacity-60': m.is_hidden || m.status === 'rejected' }"
          >
            <div class="flex-1 min-w-0">
              <div class="text-[.88rem] text-ink leading-snug" :class="{ 'line-through': m.is_hidden }">{{ m.body }}</div>
              <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                <span class="muted text-[.76rem]">{{ m.author }}</span>
                <span v-if="m.kind === 'question'" class="muted text-[.76rem]">· {{ m.upvotes }} upvote{{ m.upvotes === 1 ? '' : 's' }}</span>
                <span v-if="m.status === 'pending'" class="text-[.62rem] font-bold uppercase px-1.5 py-0.5 rounded bg-[#fef3c7] text-[#b45309]">Awaiting approval</span>
                <span v-if="m.status === 'rejected'" class="text-[.62rem] font-bold uppercase px-1.5 py-0.5 rounded bg-[#e2e8f0] text-[#475569]">Rejected</span>
                <span v-if="m.is_hidden" class="text-[.62rem] font-bold uppercase px-1.5 py-0.5 rounded bg-[#e2e8f0] text-[#475569]">Hidden</span>
                <span v-if="m.is_pinned" class="text-[.62rem] font-bold uppercase px-1.5 py-0.5 rounded bg-brand-soft text-brand">Pinned</span>
                <span v-if="m.is_answered" class="text-[.62rem] font-bold uppercase px-1.5 py-0.5 rounded bg-[#dcfce7] text-[#15803d]">Answered</span>
              </div>
            </div>

            <div class="flex flex-wrap justify-end gap-1.5 shrink-0">
              <template v-if="m.status === 'pending'">
                <button class="btn sm" @click="patchMessage(m, { status: 'published' }, 'Question approved')">Approve</button>
                <button class="btn ghost sm text-[#dc2626]" @click="patchMessage(m, { status: 'rejected' }, 'Question rejected')">Reject</button>
              </template>
              <template v-else>
                <button
                  v-if="m.kind === 'question'"
                  class="btn ghost sm"
                  @click="patchMessage(m, { is_answered: !m.is_answered }, m.is_answered ? 'Question reopened' : 'Marked as answered')"
                >{{ m.is_answered ? 'Reopen' : 'Answered' }}</button>
                <button
                  class="btn ghost sm"
                  @click="patchMessage(m, { is_pinned: !m.is_pinned }, m.is_pinned ? 'Unpinned' : 'Pinned')"
                >{{ m.is_pinned ? 'Unpin' : 'Pin' }}</button>
                <button
                  class="btn ghost sm"
                  @click="patchMessage(m, { is_hidden: !m.is_hidden }, m.is_hidden ? 'Message restored' : 'Message hidden')"
                >{{ m.is_hidden ? 'Unhide' : 'Hide' }}</button>
              </template>
              <button class="btn ghost sm text-[#dc2626]" @click="deleteMessage(m)">Delete</button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Not found -->
    <div v-else class="card text-center py-12 muted">
      Session not found.
      <button class="btn ghost ml-3" @click="router.push(`/org/events/${id}/showcase/sessions`)">Back to Sessions</button>
    </div>
  </div>
</template>
