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
  qa_answer_policy: 'organizers' | 'hosts' | 'everyone'
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
  kind: 'chat' | 'question' | 'answer'
  body: string
  author: string
  author_role: 'organizer' | 'speaker' | 'attendee'
  is_official: boolean
  upvotes: number
  status: 'published' | 'pending' | 'rejected'
  is_hidden: boolean
  is_pinned: boolean
  is_answered: boolean
  created_at: string | null
  /** Answers threaded under a question, oldest first. */
  replies: PanelMessage[]
}

interface SessionRatingRow {
  id: string
  score: number
  rated_at: string | null
  participation: {
    id: string | null
    role: string | null
    status: string | null
    name: string | null
    email: string | null
  }
}

interface SessionRatingsSummary {
  ratings_count: number
  average_score: number | null
  distribution: Array<{ score: number, count: number }>
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
const activeTab     = ref<'basic' | 'stream' | 'engagement' | 'ratings'>('basic')
const loading       = ref(true)
const tagInput      = ref('')

const TABS: { key: 'basic' | 'stream' | 'engagement' | 'ratings'; label: string }[] = [
  { key: 'basic', label: 'Basic Details' },
  { key: 'stream', label: 'Stream' },
  { key: 'engagement', label: 'Engagement' },
  { key: 'ratings', label: 'Ratings' },
]

// Header "Deactivate" toggle — reuses the session's own status field (the same
// one the Stream tab's Broadcast State override edits), so cancelling here is
// just another way of setting status: 'canceled'.
const bannerField    = ref<any>(null)
const docUploaderRef = ref<any>(null)
const activeToggling  = ref(false)
const isActive = computed(() => session.value?.status !== 'canceled')

async function toggleActive() {
  if (!session.value || activeToggling.value) return
  const wasActive = isActive.value
  activeToggling.value = true
  try {
    const nextStatus = wasActive ? 'canceled' : 'scheduled'
    const res = await api<any>(`/sessions/${sessionId}`, { method: 'PUT', body: { status: nextStatus } })
    const status = res.data?.status ?? nextStatus
    session.value = { ...session.value, status }
    stream.status = status
    toast.success(wasActive ? 'Session deactivated' : 'Session activated')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not update session status.')
  } finally {
    activeToggling.value = false
  }
}

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
  qa_answer_policy:         'hosts' as Session['qa_answer_policy'],
})

const HOST_OPTIONS = [
  { value: 'self', label: 'Self-hosted' },
  { value: 'youtube', label: 'YouTube' },
  { value: 'vimeo', label: 'Vimeo' },
  { value: 'agora', label: 'Agora (in-page broadcast)' },
  { value: 'jitsi', label: 'Jitsi (in-page video)' },
  { value: 'zoom', label: 'Zoom' },
  { value: 'meet', label: 'Google Meet' },
  { value: 'rtmp', label: 'RTMP' },
] as const

const BROADCAST_STATE_OPTIONS = [
  { value: 'scheduled', label: 'Follow the schedule' },
  { value: 'live', label: 'Live now (open the player)' },
  { value: 'ended', label: 'Ended (show the replay)' },
  { value: 'canceled', label: 'Canceled' },
] as const

const QA_REPLY_OPTIONS = [
  { value: 'organizers', label: 'Organizers only' },
  { value: 'hosts', label: 'Organizers and this session’s speakers' },
  { value: 'everyone', label: 'Anyone in the session' },
] as const

function toBool(value: unknown): boolean {
  if (typeof value === 'boolean') return value
  if (typeof value === 'number') return value === 1
  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase()
    if (['1', 'true', 'yes', 'on'].includes(normalized)) return true
    if (['0', 'false', 'no', 'off', ''].includes(normalized)) return false
  }
  return !!value
}

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

// Stored as a UTC instant; the form always shows/edits wall-clock time in the
// session's own timezone (falls back to the event's), not the browser's.
function isoToDate(iso: string | null, tz: string): string {
  return tzDateInput(iso, tz)
}

function isoToTime(iso: string | null, tz: string): string {
  return tzTimeInput(iso, tz)
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
    const tz = s.timezone || 'UTC'
    basic.date               = isoToDate(s.starts_at, tz)
    basic.start_time         = isoToTime(s.starts_at, tz)
    basic.end_time           = isoToTime(s.ends_at, tz)
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
    stream.is_stream                = toBool(s.is_stream)
    stream.status                   = s.status ?? 'scheduled'
    stream.who_will_host            = s.who_will_host ?? 'self'
    stream.stream_link              = s.stream_link ?? ''
    stream.on_demand_recording_link = s.on_demand_recording_link ?? ''
    stream.vimeo_live_id            = s.vimeo_live_id ?? ''
    stream.can_live_chat            = toBool(s.can_live_chat)
    stream.can_qa                   = toBool(s.can_qa)
    stream.can_live_polls           = toBool(s.can_live_polls)
    stream.can_attendee_list        = toBool(s.can_attendee_list)
    stream.can_session              = toBool(s.can_session)
    stream.qa_moderation            = toBool(s.qa_moderation)
    stream.qa_answer_policy         = s.qa_answer_policy ?? 'hosts'
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
const ratingsLoading = ref(false)
const ratingsLoaded = ref(false)
const ratings = ref<SessionRatingRow[]>([])
const ratingsSummary = ref<SessionRatingsSummary>({
  ratings_count: 0,
  average_score: null,
  distribution: [
    { score: 1, count: 0 },
    { score: 2, count: 0 },
    { score: 3, count: 0 },
    { score: 4, count: 0 },
    { score: 5, count: 0 },
  ],
})

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

async function loadRatings(force = false) {
  if (ratingsLoading.value || (ratingsLoaded.value && !force)) return
  ratingsLoading.value = true
  try {
    const res = await api<any>(`/sessions/${sessionId}/ratings`)
    ratings.value = res.data?.ratings ?? []
    ratingsSummary.value = res.data?.summary ?? ratingsSummary.value
    ratingsLoaded.value = true
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not load session ratings.')
  } finally {
    ratingsLoading.value = false
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

// ── Answering a question from the console ────────────────────────────────────
// The organizer rarely sits in the room with the panel open; the questions that
// land late, or that the speaker never got to, get answered from here. The reply
// posts as the organizer, badged, and marks the question answered.
const replyingTo  = ref<number | null>(null)
const replyInput  = ref('')
const replySaving = ref(false)

function openReply(m: PanelMessage) {
  replyingTo.value = replyingTo.value === m.id ? null : m.id
  replyInput.value = ''
}

async function sendReply(m: PanelMessage) {
  const body = replyInput.value.trim()
  if (!body || replySaving.value) return
  replySaving.value = true
  try {
    await api(`/session-messages/${m.id}/replies`, { method: 'POST', body: { body } })
    replyingTo.value = null
    replyInput.value = ''
    await loadEngagement()
    toast.success('Reply posted')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not post the reply.')
  } finally {
    replySaving.value = false
  }
}

async function deleteMessage(m: PanelMessage) {
  const n = m.replies?.length ?? 0
  const what = m.kind === 'answer'
    ? 'Delete this reply?'
    : n
      ? `Delete this message and its ${n} ${n === 1 ? 'reply' : 'replies'}?`
      : 'Delete this message?'
  if (!confirm(`${what} Attendees will no longer see it.`)) return
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

// Replies can queue for approval too (attendee answers, when both "anyone can
// reply" and pre-moderation are on), so the badge counts the whole thread.
const pendingCount = computed(() => messages.value.reduce(
  (n, m) => n + (m.status === 'pending' ? 1 : 0) + (m.replies?.filter(r => r.status === 'pending').length ?? 0),
  0,
))

// Load the engagement data lazily — only when the organizer opens that tab.
watch(activeTab, (t) => { if (t === 'engagement') loadEngagement() })
watch(activeTab, (t) => { if (t === 'ratings') loadRatings() })
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
    basicError.value = e?.data?.errors?.track_id?.[0]
      || e?.data?.message
      || 'Could not save changes.'
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
        qa_answer_policy:         stream.qa_answer_policy,
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
      <!-- Breadcrumb + Deactivate -->
      <div class="flex items-center justify-between gap-4 flex-wrap mb-5">
        <div class="flex items-center gap-2 text-[.9rem] min-w-0">
          <button
            class="text-brand font-semibold hover:underline bg-transparent border-0 cursor-pointer p-0"
            @click="router.push(`/org/events/${id}/showcase/sessions`)"
          >
            Sessions
          </button>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5 text-muted shrink-0"><path d="M9 18l6-6-6-6"/></svg>
          <span class="text-ink font-semibold truncate">{{ session.title }}</span>
        </div>

        <label class="flex items-center gap-2 m-0 cursor-pointer select-none shrink-0" title="Toggle whether this session is active">
          <span class="text-[.86rem] font-medium text-muted">Deactivate</span>
          <button
            type="button"
            class="relative w-10 h-5.5 rounded-full transition-colors shrink-0"
            :class="isActive ? 'bg-brand' : 'bg-[#d7dae1]'"
            :disabled="activeToggling"
            @click="toggleActive"
          >
            <span class="absolute top-0.75 left-0.75 w-4 h-4 rounded-full bg-white transition-transform" :class="isActive ? 'translate-x-4.5' : ''" />
          </button>
        </label>
      </div>

      <!-- Rail + content -->
      <div class="flex items-start gap-5">
        <!-- Vertical tab rail -->
        <aside class="w-52 shrink-0 bg-white border border-line rounded-lg p-3 flex flex-col gap-2 sticky top-19.5">
          <button
            v-for="t in TABS" :key="t.key"
            class="w-full text-left px-3.5 py-2.5 rounded-lg border text-[.88rem] font-medium transition-colors"
            :class="activeTab === t.key
              ? 'bg-[#F0EEFD] border-brand text-brand font-semibold'
              : 'text-ink bg-[#F7F7FB] border-transparent hover:bg-[#eef0f4]'"
            @click="activeTab = t.key"
          >{{ t.label }}</button>
        </aside>

        <!-- Active panel -->
        <div class="flex-1 min-w-0 bg-white border border-line rounded-2xl p-6">

      <!-- ── Basic Details Tab ───────────────────────────────────────────── -->
      <div v-if="activeTab === 'basic'">
        <h3 class="font-bold text-[1.05rem] text-ink m-0 mb-0.5">Basic Details</h3>
        <p class="muted text-[.85rem] mt-0 mb-5">Basic Details of the session</p>

        <!-- Date + time -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
          <AppInput v-model="basic.date" type="date" label="Session Date" placeholder="Session Date" required />
          <AppInput v-model="basic.start_time" type="time" label="Session Start" placeholder="Session Start Time" required>
            <template #suffix>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="w-4 h-4"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
            </template>
          </AppInput>
          <AppInput v-model="basic.end_time" type="time" label="Session End" placeholder="Session Start Time" required>
            <template #suffix>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="w-4 h-4"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
            </template>
          </AppInput>
        </div>
        <p class="muted text-[.76rem] -mt-2 mb-4">Times shown in {{ session.timezone || 'UTC' }}</p>
        <p v-if="timeError" class="error mb-4">{{ timeError }}</p>

        <!-- Core details -->
        <div class="mb-4">
          <AppInput v-model="basic.title" label="Session Title" placeholder="Enter Session Title" required />
        </div>

        <div class="mb-4">
          <AppInput v-model="basic.session_place" label="Session Place" placeholder="Enter Session Place" />
        </div>

        <!-- Track inline CRUD -->
        <div class="mb-4 relative">
          <label class="block mb-1.5">Assign Track</label>
          <button
            type="button"
              class="w-full flex items-center justify-between px-3 py-2 border border-line rounded-lg h-12 bg-white text-[.9rem]"
              @click.stop="showTrackMenu = !showTrackMenu; editingTrackId = null"
            >
              <span>{{ tracks.find(t => t.id === basic.track_id)?.name ?? '— No track —' }}</span>
              <span class="text-muted text-xs">▾</span>
            </button>
            <div
              v-if="showTrackMenu"
              class="absolute left-0 top-full mt-1 z-20 bg-white border border-line rounded-lg shadow-lg py-1 w-full max-h-56 overflow-y-auto"
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

          <div class="mb-5">
            <label class="block mb-1.5">Session Description</label>
            <SessionDescriptionEditor v-model="basic.description" />
          </div>

          <div class="flex gap-6">
            <div>
              <label class="block mb-1.5">Icon <span class="text-faint font-normal">(optional)</span></label>
              <button
                type="button"
                class="w-20 h-20 rounded-lg border border-dashed border-[#d7dae1] flex items-center justify-center bg-[#fafbfc] cursor-pointer hover:border-brand overflow-hidden"
                @click="iconChooserOpen = true"
              >
                <img v-if="iconIsImage" :src="basic.icon_url ?? ''" alt="Session icon" class="w-full h-full object-cover">
                <AppIcon v-else-if="basic.icon_url" :name="basic.icon_url" class="w-8 h-8 text-ink" />
                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="w-7 h-7 text-muted"><rect x="3" y="5" width="18" height="14" rx="2"/><rect x="7" y="9" width="10" height="6" rx="1"/></svg>
              </button>
            </div>
          </div>

        <hr class="border-line my-6">

        <!-- Session Banner -->
        <div class="flex items-start justify-between gap-4 mb-1">
          <div>
            <h3 class="font-bold text-[1rem] text-ink m-0">
              Session Banner <span class="text-muted font-normal text-[.78rem] ml-1">800x368px | 5MB(Maximum).</span>
            </h3>
            <p class="muted text-[.85rem] mt-1 mb-0">Add Session banner to your session. They will be highlighted on the session page.</p>
          </div>
          <button type="button" class="btn px-6 rounded-lg py-2.5  bg-[#F0EEFD] text-brand sm shrink-0" @click="bannerField?.open()">Add Banner</button>
        </div>
        <div class="mt-4">
          <ImageField
            ref="bannerField"
            :model-value="basic.logo_url"
            :aspect="800 / 168"
            :output-width="800"
            :output-height="368"
            collection="session_logo"
            card-width="100%"
            hide-empty
            :gallery-path="`/events/${id}/gallery`"
            @update:model-value="basic.logo_url = (Array.isArray($event) ? $event[0] : $event) || null"
          />
        </div>

        <hr class="border-line my-6">

        <!-- Speakers -->
        <h3 class="font-bold text-[1rem] text-ink m-0">Session Speakers</h3>
        <p class="muted text-[.85rem] mt-1 mb-4">Give your audience a closer look at your speakers.</p>
        <div class="flex flex-wrap gap-3 items-center mb-2">
          <button
            type="button"
            class="w-25 h-25 border-2 border-dashed border-line rounded-lg flex items-center justify-center text-muted hover:border-brand hover:text-brand transition-colors bg-[#fafbfc] shrink-0"
            @click.stop="speakerModal = true"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" class="w-5 h-5"><path d="M12 5v14M5 12h14"/></svg>
          </button>
          <div
            v-for="sp in session?.speakers ?? []"
            :key="sp.id"
            class="group relative w-25 h-25 rounded-lg overflow-hidden bg-[#F0EEFD] text-brand flex items-center justify-center text-[.85rem] font-bold border border-line shrink-0"
            :title="sp.name"
          >
            <img v-if="sp.profile.image_url" :src="sp.profile.image_url" :alt="sp.name" class="w-full h-full object-cover">
            <span v-else>{{ initials(sp.name) }}</span>
            <button
              class="absolute inset-0 m-auto w-6 h-6 cursor-pointer bg-white text-[#dc2626] border border-line rounded-lg opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center shadow"
              :disabled="spkSaving"
              @click.stop="toggleSpeakerById(sp.id)"
              title="Remove speaker"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 18 20" fill="none">
                  <path d="M7 16C7.26522 16 7.51957 15.8946 7.70711 15.7071C7.89464 15.5196 8 15.2652 8 15V9C8 8.73478 7.89464 8.48043 7.70711 8.29289C7.51957 8.10536 7.26522 8 7 8C6.73478 8 6.48043 8.10536 6.29289 8.29289C6.10536 8.48043 6 8.73478 6 9V15C6 15.2652 6.10536 15.5196 6.29289 15.7071C6.48043 15.8946 6.73478 16 7 16ZM17 4H13V3C13 2.20435 12.6839 1.44129 12.1213 0.87868C11.5587 0.316071 10.7956 0 10 0H8C7.20435 0 6.44129 0.316071 5.87868 0.87868C5.31607 1.44129 5 2.20435 5 3V4H1C0.734784 4 0.48043 4.10536 0.292893 4.29289C0.105357 4.48043 0 4.73478 0 5C0 5.26522 0.105357 5.51957 0.292893 5.70711C0.48043 5.89464 0.734784 6 1 6H2V17C2 17.7956 2.31607 18.5587 2.87868 19.1213C3.44129 19.6839 4.20435 20 5 20H13C13.7956 20 14.5587 19.6839 15.1213 19.1213C15.6839 18.5587 16 17.7956 16 17V6H17C17.2652 6 17.5196 5.89464 17.7071 5.70711C17.8946 5.51957 18 5.26522 18 5C18 4.73478 17.8946 4.48043 17.7071 4.29289C17.5196 4.10536 17.2652 4 17 4ZM7 3C7 2.73478 7.10536 2.48043 7.29289 2.29289C7.48043 2.10536 7.73478 2 8 2H10C10.2652 2 10.5196 2.10536 10.7071 2.29289C10.8946 2.48043 11 2.73478 11 3V4H7V3ZM14 17C14 17.2652 13.8946 17.5196 13.7071 17.7071C13.5196 17.8946 13.2652 18 13 18H5C4.73478 18 4.48043 17.8946 4.29289 17.7071C4.10536 17.5196 4 17.2652 4 17V6H14V17ZM11 16C11.2652 16 11.5196 15.8946 11.7071 15.7071C11.8946 15.5196 12 15.2652 12 15V9C12 8.73478 11.8946 8.48043 11.7071 8.29289C11.5196 8.10536 11.2652 8 11 8C10.7348 8 10.4804 8.10536 10.2929 8.29289C10.1054 8.48043 10 8.73478 10 9V15C10 15.2652 10.1054 15.5196 10.2929 15.7071C10.4804 15.8946 10.7348 16 11 16Z" fill="#F24822"/>
              </svg>
            </button>
          </div>
        </div>
        <p v-if="!eventSpeakers.length" class="muted text-[.84rem] m-0 mb-2">
          No event speakers yet. Add them in <strong>Showcase › Speakers</strong>.
        </p>

        <hr class="border-line my-6">

        <!-- Sponsors -->
        <h3 class="font-bold text-[1rem] text-ink m-0">Session Sponsors</h3>
        <p class="muted text-[.85rem] mt-1 mb-4">Showcase your session sponsors and connect your brand with the audience.</p>
        <div class="flex flex-wrap gap-3 items-center">
          <button
            type="button"
            class="w-25 h-25 border-2 border-dashed border-line rounded-lg flex items-center justify-center text-muted hover:border-brand hover:text-brand transition-colors bg-[#fafbfc] shrink-0"
            @click.stop="sponsorModal = true"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" class="w-5 h-5"><path d="M12 5v14M5 12h14"/></svg>
          </button>
          <div
            v-for="sp in basic.sponsors"
            :key="sp.id"
            class="group relative w-25 h-25 rounded-lg overflow-hidden bg-white text-muted flex items-center justify-center text-[.8rem] font-bold border border-line text-center shrink-0"
            :title="sp.name"
          >
            <img v-if="sp.logo_url" :src="sp.logo_url" :alt="sp.name" class="w-full h-full object-cover">
            <span v-else class="leading-tight line-clamp-2">{{ sp.name }}</span>
            <button
              class="absolute inset-0 m-auto w-8 h-8 bg-white text-[#dc2626] border border-line rounded-lg opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center shadow"
              @click.stop="removeSponsor(sp.id)"
              title="Remove sponsor"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
            </button>
          </div>
        </div>

        <hr class="border-line my-6">

        <!-- Documents -->
        <div class="flex items-start justify-between gap-4 mb-1">
          <div>
            <h3 class="font-bold text-[1rem] text-ink m-0">
              Documents <span class="text-muted font-normal text-[.78rem] ml-1">DOC, PPT, PDF | 5MB (Maximum) | Up to 10 files</span>
            </h3>
            <p class="muted text-[.85rem] mt-1 mb-0">Provide your attendees with supporting documents they can download during the sessions.</p>
          </div>
          <button type="button" class="btn px-6 rounded-lg py-2.5  bg-[#F0EEFD] text-brand sm shrink-0" @click="docUploaderRef?.browse()">Add Document</button>
        </div>
        <div class="mt-4">
          <SessionDocumentUploader ref="docUploaderRef" v-model="basic.documents" @error="basicError = $event" />
        </div>

        <hr class="border-line my-6">

        <!-- Tags -->
        <div class="mb-5">
          <label class="block mb-1.5">Custom Tags</label>
          <div class="flex flex-wrap items-center gap-1.5 border border-line rounded-lg px-3 py-2 min-h-12 focus-within:border-brand">
            <span
              v-for="(tag, i) in basic.tags" :key="i"
              class="flex items-center gap-2 px-2.5 py-1 rounded-sm bg-[#F0EEFD] "
            >
              <span class="text-brand text-sm font-semibold">{{ tag }}</span>
              <button class="bg-transparent border-0 p-0 cursor-pointer text-brand leading-none text-[.9rem]" @click="removeTag(i)">
                <svg xmlns="http://www.w3.org/2000/svg" width="8" height="8" viewBox="0 0 8 8" fill="none">
                    <mask id="path-1-inside-1_3426_47349" fill="white">
                    <path d="M4.7079 4.00205L7.8579 0.857046C7.95205 0.762894 8.00494 0.635197 8.00494 0.502046C8.00494 0.368895 7.95205 0.241198 7.8579 0.147046C7.76375 0.052894 7.63605 0 7.5029 0C7.36975 0 7.24205 0.052894 7.1479 0.147046L4.0029 3.29705L0.857899 0.147046C0.763747 0.052894 0.63605 1.18217e-07 0.502899 1.19209e-07C0.369748 1.20201e-07 0.242051 0.052894 0.147899 0.147046C0.0537473 0.241198 0.00085342 0.368895 0.000853419 0.502046C0.000853418 0.635197 0.0537473 0.762894 0.147899 0.857046L3.2979 4.00205L0.147899 7.14705C0.101035 7.19353 0.0638379 7.24883 0.0384536 7.30976C0.0130692 7.37069 0 7.43604 0 7.50205C0 7.56805 0.0130692 7.6334 0.0384536 7.69433C0.0638379 7.75526 0.101035 7.81056 0.147899 7.85705C0.194381 7.90391 0.249681 7.94111 0.310611 7.96649C0.37154 7.99188 0.436893 8.00494 0.502899 8.00494C0.568905 8.00494 0.634258 7.99188 0.695188 7.96649C0.756117 7.94111 0.811418 7.90391 0.857899 7.85705L4.0029 4.70705L7.1479 7.85705C7.19438 7.90391 7.24968 7.94111 7.31061 7.96649C7.37154 7.99188 7.43689 8.00494 7.5029 8.00494C7.5689 8.00494 7.63426 7.99188 7.69519 7.96649C7.75612 7.94111 7.81142 7.90391 7.8579 7.85705C7.90476 7.81056 7.94196 7.75526 7.96735 7.69433C7.99273 7.6334 8.0058 7.56805 8.0058 7.50205C8.0058 7.43604 7.99273 7.37069 7.96735 7.30976C7.94196 7.24883 7.90476 7.19353 7.8579 7.14705L4.7079 4.00205Z"/>
                    </mask>
                    <path d="M4.7079 4.00205L7.8579 0.857046C7.95205 0.762894 8.00494 0.635197 8.00494 0.502046C8.00494 0.368895 7.95205 0.241198 7.8579 0.147046C7.76375 0.052894 7.63605 0 7.5029 0C7.36975 0 7.24205 0.052894 7.1479 0.147046L4.0029 3.29705L0.857899 0.147046C0.763747 0.052894 0.63605 1.18217e-07 0.502899 1.19209e-07C0.369748 1.20201e-07 0.242051 0.052894 0.147899 0.147046C0.0537473 0.241198 0.00085342 0.368895 0.000853419 0.502046C0.000853418 0.635197 0.0537473 0.762894 0.147899 0.857046L3.2979 4.00205L0.147899 7.14705C0.101035 7.19353 0.0638379 7.24883 0.0384536 7.30976C0.0130692 7.37069 0 7.43604 0 7.50205C0 7.56805 0.0130692 7.6334 0.0384536 7.69433C0.0638379 7.75526 0.101035 7.81056 0.147899 7.85705C0.194381 7.90391 0.249681 7.94111 0.310611 7.96649C0.37154 7.99188 0.436893 8.00494 0.502899 8.00494C0.568905 8.00494 0.634258 7.99188 0.695188 7.96649C0.756117 7.94111 0.811418 7.90391 0.857899 7.85705L4.0029 4.70705L7.1479 7.85705C7.19438 7.90391 7.24968 7.94111 7.31061 7.96649C7.37154 7.99188 7.43689 8.00494 7.5029 8.00494C7.5689 8.00494 7.63426 7.99188 7.69519 7.96649C7.75612 7.94111 7.81142 7.90391 7.8579 7.85705C7.90476 7.81056 7.94196 7.75526 7.96735 7.69433C7.99273 7.6334 8.0058 7.56805 8.0058 7.50205C8.0058 7.43604 7.99273 7.37069 7.96735 7.30976C7.94196 7.24883 7.90476 7.19353 7.8579 7.14705L4.7079 4.00205Z" fill="#6452E7"/>
                    <path d="M4.7079 4.00205L3.64808 2.94054L2.58489 4.00205L3.64808 5.06355L4.7079 4.00205ZM7.8579 0.857046L8.91772 1.91855L8.91856 1.91771L7.8579 0.857046ZM8.00494 0.502046H9.50494H8.00494ZM7.5029 0V1.5V0ZM7.1479 0.147046L6.08724 -0.913615L6.0864 -0.912772L7.1479 0.147046ZM4.0029 3.29705L2.9414 4.35686L4.0029 5.42005L5.0644 4.35686L4.0029 3.29705ZM0.857899 0.147046L1.9194 -0.912772L1.91856 -0.913614L0.857899 0.147046ZM0.502899 1.19209e-07V1.5V1.19209e-07ZM0.000853419 0.502046H-1.49915H0.000853419ZM0.147899 0.857046L-0.912761 1.91771L-0.911918 1.91855L0.147899 0.857046ZM3.2979 4.00205L4.35772 5.06355L5.42091 4.00205L4.35772 2.94054L3.2979 4.00205ZM0.147899 7.14705L1.20421 8.21205L1.20772 8.20855L0.147899 7.14705ZM0.147899 7.85705L1.21293 6.80071L1.2042 6.79205L0.147899 7.85705ZM0.857899 7.85705L-0.203609 6.79722L-0.207101 6.80074L0.857899 7.85705ZM4.0029 4.70705L5.0644 3.64723L4.0029 2.58404L2.9414 3.64723L4.0029 4.70705ZM7.1479 7.85705L8.21291 6.80074L8.2094 6.79723L7.1479 7.85705ZM7.8579 7.85705L6.80156 6.79201L6.7929 6.80074L7.8579 7.85705ZM7.8579 7.14705L6.79808 8.20855L6.8016 8.21205L7.8579 7.14705ZM4.7079 4.00205L5.76772 5.06355L8.91772 1.91855L7.8579 0.857046L6.79808 -0.204456L3.64808 2.94054L4.7079 4.00205ZM7.8579 0.857046L8.91856 1.91771C9.29401 1.54225 9.50494 1.03302 9.50494 0.502046H8.00494H6.50494C6.50494 0.23737 6.61009 -0.016463 6.79724 -0.203614L7.8579 0.857046ZM8.00494 0.502046H9.50494C9.50494 -0.0289317 9.29401 -0.538159 8.91856 -0.913614L7.8579 0.147046L6.79724 1.20771C6.61009 1.02055 6.50494 0.766722 6.50494 0.502046H8.00494ZM7.8579 0.147046L8.91856 -0.913614C8.5431 -1.28907 8.03388 -1.5 7.5029 -1.5V0V1.5C7.23822 1.5 6.98439 1.39486 6.79724 1.20771L7.8579 0.147046ZM7.5029 0V-1.5C6.97192 -1.5 6.46269 -1.28907 6.08724 -0.913614L7.1479 0.147046L8.20856 1.20771C8.02141 1.39486 7.76757 1.5 7.5029 1.5V0ZM7.1479 0.147046L6.0864 -0.912772L2.9414 2.23723L4.0029 3.29705L5.0644 4.35686L8.2094 1.20686L7.1479 0.147046ZM4.0029 3.29705L5.0644 2.23723L1.9194 -0.912772L0.857899 0.147046L-0.203603 1.20686L2.9414 4.35686L4.0029 3.29705ZM0.857899 0.147046L1.91856 -0.913614C1.5431 -1.28907 1.03387 -1.5 0.502899 -1.5V1.19209e-07V1.5C0.238226 1.5 -0.0156078 1.39486 -0.202761 1.20771L0.857899 0.147046ZM0.502899 1.19209e-07V-1.5C-0.0280757 -1.5 -0.537304 -1.28907 -0.912761 -0.913614L0.147899 0.147046L1.20856 1.20771C1.02141 1.39486 0.767572 1.5 0.502899 1.5V1.19209e-07ZM0.147899 0.147046L-0.912761 -0.913614C-1.28822 -0.538157 -1.49915 -0.028929 -1.49915 0.502046H0.000853419H1.50085C1.50085 0.766719 1.39571 1.02055 1.20856 1.20771L0.147899 0.147046ZM0.000853419 0.502046H-1.49915C-1.49915 1.03302 -1.28822 1.54225 -0.912761 1.91771L0.147899 0.857046L1.20856 -0.203614C1.39571 -0.0164611 1.50085 0.237373 1.50085 0.502046H0.000853419ZM0.147899 0.857046L-0.911918 1.91855L2.23808 5.06355L3.2979 4.00205L4.35772 2.94054L1.20772 -0.204456L0.147899 0.857046ZM3.2979 4.00205L2.23808 2.94054L-0.911918 6.08554L0.147899 7.14705L1.20772 8.20855L4.35772 5.06355L3.2979 4.00205ZM0.147899 7.14705L-0.908403 6.08205C-1.09586 6.26797 -1.24465 6.48917 -1.34619 6.73289L0.0384536 7.30976L1.42309 7.88662C1.37232 8.00848 1.29793 8.11909 1.2042 8.21205L0.147899 7.14705ZM0.0384536 7.30976L-1.34619 6.73289C-1.44772 6.97661 -1.5 7.23802 -1.5 7.50205H0H1.5C1.5 7.63406 1.47386 7.76476 1.42309 7.88662L0.0384536 7.30976ZM0 7.50205H-1.5C-1.5 7.76607 -1.44772 8.02748 -1.34619 8.2712L0.0384536 7.69433L1.42309 7.11747C1.47386 7.23933 1.5 7.37003 1.5 7.50205H0ZM0.0384536 7.69433L-1.34619 8.2712C-1.24465 8.51492 -1.09586 8.73612 -0.908403 8.92204L0.147899 7.85705L1.2042 6.79205C1.29793 6.88501 1.37232 6.99561 1.42309 7.11747L0.0384536 7.69433ZM0.147899 7.85705L-0.917101 8.91335C-0.731183 9.1008 -0.509983 9.24959 -0.266254 9.35113L0.310611 7.96649L0.887476 6.58185C1.00935 6.63263 1.11994 6.70702 1.2129 6.80074L0.147899 7.85705ZM0.310611 7.96649L-0.266254 9.35113C-0.0225303 9.45267 0.238881 9.50494 0.502899 9.50494V8.00494V6.50494C0.634905 6.50494 0.765611 6.53108 0.887476 6.58185L0.310611 7.96649ZM0.502899 8.00494V9.50494C0.766917 9.50494 1.02833 9.45267 1.27205 9.35113L0.695188 7.96649L0.118323 6.58185C0.240187 6.53108 0.370893 6.50494 0.502899 6.50494V8.00494ZM0.695188 7.96649L1.27205 9.35113C1.51578 9.24959 1.73698 9.1008 1.9229 8.91335L0.857899 7.85705L-0.207101 6.80074C-0.114146 6.70702 -0.00354703 6.63263 0.118323 6.58185L0.695188 7.96649ZM0.857899 7.85705L1.9194 8.91686L5.0644 5.76686L4.0029 4.70705L2.9414 3.64723L-0.203603 6.79723L0.857899 7.85705ZM4.0029 4.70705L2.9414 5.76686L6.0864 8.91686L7.1479 7.85705L8.2094 6.79723L5.0644 3.64723L4.0029 4.70705ZM7.1479 7.85705L6.0829 8.91335C6.26881 9.10079 6.49001 9.24959 6.73375 9.35113L7.31061 7.96649L7.88748 6.58185C8.00935 6.63263 8.11995 6.70703 8.2129 6.80074L7.1479 7.85705ZM7.31061 7.96649L6.73375 9.35113C6.97747 9.45267 7.23888 9.50494 7.5029 9.50494V8.00494V6.50494C7.63491 6.50494 7.76561 6.53108 7.88748 6.58185L7.31061 7.96649ZM7.5029 8.00494V9.50494C7.76692 9.50494 8.02833 9.45267 8.27205 9.35113L7.69519 7.96649L7.11832 6.58185C7.24019 6.53108 7.37089 6.50494 7.5029 6.50494V8.00494ZM7.69519 7.96649L8.27205 9.35113C8.51579 9.24959 8.73698 9.10079 8.9229 8.91335L7.8579 7.85705L6.7929 6.80074C6.88585 6.70703 6.99645 6.63263 7.11832 6.58185L7.69519 7.96649ZM7.8579 7.85705L8.9142 8.92204C9.10165 8.73613 9.25044 8.51493 9.35198 8.2712L7.96735 7.69433L6.58271 7.11747C6.63348 6.9956 6.70788 6.885 6.8016 6.79205L7.8579 7.85705ZM7.96735 7.69433L9.35198 8.2712C9.45352 8.02748 9.5058 7.76606 9.5058 7.50205H8.0058H6.5058C6.5058 7.37004 6.53193 7.23933 6.58271 7.11747L7.96735 7.69433ZM8.0058 7.50205H9.5058C9.5058 7.23803 9.45352 6.97662 9.35198 6.73289L7.96735 7.30976L6.58271 7.88662C6.53193 7.76476 6.5058 7.63405 6.5058 7.50205H8.0058ZM7.96735 7.30976L9.35198 6.73289C9.25044 6.48916 9.10165 6.26796 8.9142 6.08205L7.8579 7.14705L6.8016 8.21205C6.70788 8.11909 6.63348 8.0085 6.58271 7.88662L7.96735 7.30976ZM7.8579 7.14705L8.91772 6.08554L5.76772 2.94054L4.7079 4.00205L3.64808 5.06355L6.79808 8.20855L7.8579 7.14705Z" fill="#6452E7" mask="url(#path-1-inside-1_3426_47349)"/>
                </svg>
              </button>
            </span>
            <input
              v-model="tagInput"
              placeholder="Add tag &amp; press enter"
              class="flex-1 min-w-30 border-0! p-0! m-0! h-auto! focus:outline-none focus:shadow-none!"
              @keydown="onTagKey"
              @blur="addTag"
            >
          </div>
          <p class="muted text-[.78rem] mt-1.5 mb-0">Add tags relevant to the sessions, so attendees can filter sessions based on these tags.</p>
        </div>

        <hr class="border-line my-6">

        <div class="flex flex-col gap-3 mb-2">
          <label class="flex items-center gap-3 cursor-pointer select-none">
            <input v-model="basic.is_allowed_to_rate" type="checkbox" class="w-4.5 h-4.5 m-0 accent-brand">
            <span class="text-[.93rem] font-medium text-ink">Attendees can rate this session</span>
          </label>
          <label class="flex items-center gap-3 cursor-pointer select-none">
            <input v-model="basic.is_featured" type="checkbox" class="w-4.5 h-4.5 m-0 accent-brand">
            <span class="text-[.93rem] font-medium text-ink">Featured Session</span>
          </label>
        </div>

        <hr class="border-line my-6">

        <p v-if="basicError" class="error mb-3">{{ basicError }}</p>

        <div class="flex gap-3">
          <button
            class="btn px-8 py-4.5"
            :disabled="!canSaveBasic || basicSaving"
            @click="saveBasic"
          >
            {{ basicSaving ? 'Saving…' : 'Save' }}
          </button> 
          <button class="btn ghost px-8 py-4.5"  @click="router.push(`/org/events/${id}/showcase/sessions`)">Cancel</button>
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
      <div v-else-if="activeTab === 'stream'" class="w-full">

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
              <AppSelect
                v-model="stream.who_will_host"
                label="Who will host?"
                :options="HOST_OPTIONS"
                class="w-full max-w-xs"
              />
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
            <AppSelect
              v-model="stream.status"
              :options="BROADCAST_STATE_OPTIONS"
              class="w-full max-w-xs"
            />
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
              <!-- Who may post an answer under a question. You can always answer
                   yourself — this is about who else may. -->
              <div v-if="stream.can_qa" class="ml-8">
                <div class="font-medium text-ink text-[.93rem] mb-1">Who can reply to questions</div>
                <AppSelect
                  v-model="stream.qa_answer_policy"
                  :options="QA_REPLY_OPTIONS"
                  class="w-full max-w-sm"
                />
                <p class="muted text-[.8rem] mt-1.5 mb-0">
                  <template v-if="stream.qa_answer_policy === 'organizers'">
                    Speakers can still ask and upvote, but answers come from your team only.
                  </template>
                  <template v-else-if="stream.qa_answer_policy === 'everyone'">
                    Attendees can answer each other. Their replies go through the same
                    review queue as questions when the option above is on.
                  </template>
                  <template v-else>
                    The default — the person on stage answers what they are asked.
                  </template>
                  A reply from you or a speaker is badged and marks the question answered.
                </p>
              </div>
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
      <div v-else-if="activeTab === 'engagement'" class="w-full">

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
          <div v-if="composerOpen" class="border border-line rounded-lg p-4 mb-5 bg-[#fcfcfd]">
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

          <div v-for="p in polls" :key="p.id" class="border border-line rounded-lg p-4 mb-3">
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
              <span class="absolute left-0 top-0 bottom-0 bg-[#F0EEFD] transition-[width]" :style="{ width: pct(o, p) + '%' }" />
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
            class="py-3 border-b border-line last:border-0"
            :class="{ 'opacity-60': m.is_hidden || m.status === 'rejected' }"
          >
            <div class="flex items-start gap-3">
              <div class="flex-1 min-w-0">
                <div class="text-[.88rem] text-ink leading-snug" :class="{ 'line-through': m.is_hidden }">{{ m.body }}</div>
                <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                  <span class="muted text-[.76rem]">{{ m.author }}</span>
                  <span v-if="m.kind === 'question'" class="muted text-[.76rem]">· {{ m.upvotes }} upvote{{ m.upvotes === 1 ? '' : 's' }}</span>
                  <span v-if="m.status === 'pending'" class="text-[.62rem] font-bold uppercase px-1.5 py-0.5 rounded bg-[#fef3c7] text-[#b45309]">Awaiting approval</span>
                  <span v-if="m.status === 'rejected'" class="text-[.62rem] font-bold uppercase px-1.5 py-0.5 rounded bg-[#e2e8f0] text-[#475569]">Rejected</span>
                  <span v-if="m.is_hidden" class="text-[.62rem] font-bold uppercase px-1.5 py-0.5 rounded bg-[#e2e8f0] text-[#475569]">Hidden</span>
                  <span v-if="m.is_pinned" class="text-[.62rem] font-bold uppercase px-1.5 py-0.5 rounded bg-[#F0EEFD] text-brand">Pinned</span>
                  <span v-if="m.is_answered" class="text-[.62rem] font-bold uppercase px-1.5 py-0.5 rounded bg-[#dcfce7] text-[#15803d]">Answered</span>
                </div>
              </div>

              <div class="flex flex-wrap justify-end gap-1.5 shrink-0">
                <template v-if="m.status === 'pending'">
                  <button class="btn sm" @click="patchMessage(m, { status: 'published' }, 'Question approved')">Approve</button>
                  <button class="btn ghost sm text-[#dc2626]" @click="patchMessage(m, { status: 'rejected' }, 'Question rejected')">Reject</button>
                </template>
                <template v-else>
                  <!-- Answering a live question is the point of this queue, so it
                       leads: the rest are clean-up. -->
                  <button
                    v-if="m.kind === 'question' && !m.is_hidden"
                    class="btn sm"
                    @click="openReply(m)"
                  >{{ replyingTo === m.id ? 'Close' : 'Reply' }}</button>
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

            <!-- The answers so far, threaded under the question. -->
            <div v-if="m.replies?.length" class="mt-3 pl-4 border-l-2 border-line flex flex-col gap-3">
              <div v-for="r in m.replies" :key="r.id" class="flex items-start gap-3">
                <div class="flex-1 min-w-0">
                  <div class="text-[.85rem] text-ink leading-snug" :class="{ 'line-through': r.is_hidden }">{{ r.body }}</div>
                  <div class="flex flex-wrap items-center gap-1.5 mt-1">
                    <span class="muted text-[.76rem]">{{ r.author }}</span>
                    <span
                      v-if="r.author_role !== 'attendee'"
                      class="text-[.62rem] font-bold uppercase px-1.5 py-0.5 rounded"
                      :class="r.author_role === 'speaker' ? 'bg-[#F0EEFD] text-brand' : 'bg-[#dcfce7] text-[#15803d]'"
                    >{{ r.author_role === 'speaker' ? 'Speaker' : 'Organizer' }}</span>
                    <span v-if="r.status === 'pending'" class="text-[.62rem] font-bold uppercase px-1.5 py-0.5 rounded bg-[#fef3c7] text-[#b45309]">Awaiting approval</span>
                    <span v-if="r.is_hidden" class="text-[.62rem] font-bold uppercase px-1.5 py-0.5 rounded bg-[#e2e8f0] text-[#475569]">Hidden</span>
                  </div>
                </div>
                <div class="flex flex-wrap justify-end gap-1.5 shrink-0">
                  <template v-if="r.status === 'pending'">
                    <button class="btn sm" @click="patchMessage(r, { status: 'published' }, 'Reply approved')">Approve</button>
                    <button class="btn ghost sm text-[#dc2626]" @click="patchMessage(r, { status: 'rejected' }, 'Reply rejected')">Reject</button>
                  </template>
                  <button
                    v-else class="btn ghost sm"
                    @click="patchMessage(r, { is_hidden: !r.is_hidden }, r.is_hidden ? 'Reply restored' : 'Reply hidden')"
                  >{{ r.is_hidden ? 'Unhide' : 'Hide' }}</button>
                  <button class="btn ghost sm text-[#dc2626]" @click="deleteMessage(r)">Delete</button>
                </div>
              </div>
            </div>

            <form v-if="replyingTo === m.id" class="mt-3 pl-4 border-l-2 border-brand flex gap-2" @submit.prevent="sendReply(m)">
              <input
                v-model="replyInput" type="text" maxlength="1000" autofocus
                placeholder="Write an answer — it posts as the organizer"
                class="m-0 flex-1"
              >
              <button type="submit" class="btn" :disabled="!replyInput.trim() || replySaving">
                {{ replySaving ? 'Posting…' : 'Post reply' }}
              </button>
              <button type="button" class="btn ghost" @click="replyingTo = null">Cancel</button>
            </form>
          </div>
        </div>
      </div>

      <!-- ── Ratings Tab ─────────────────────────────────────────────────── -->
      <div v-else-if="activeTab === 'ratings'" class="w-full">
        <div class="card mb-5 p-5">
          <div class="flex items-start justify-between gap-4 mb-4">
            <div>
              <h3 class="font-semibold text-[.9rem] text-ink m-0">Session Ratings</h3>
              <p class="muted text-[.83rem] mt-1 mb-0">
                See who rated this session and how the scores are distributed.
              </p>
            </div>
            <button class="btn ghost sm" :disabled="ratingsLoading" @click="loadRatings(true)">
              {{ ratingsLoading ? 'Refreshing…' : 'Refresh' }}
            </button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
            <div class="border border-line rounded-xl p-4 bg-[#fcfcfd]">
              <div class="muted text-[.76rem] uppercase tracking-wide mb-1">Average Rating</div>
              <div class="text-[1.7rem] font-bold text-ink">
                {{ ratingsSummary.average_score ?? '—' }}
                <span class="text-[1rem] text-muted font-medium">/ 5</span>
              </div>
            </div>
            <div class="border border-line rounded-xl p-4 bg-[#fcfcfd]">
              <div class="muted text-[.76rem] uppercase tracking-wide mb-1">Total Ratings</div>
              <div class="text-[1.7rem] font-bold text-ink">{{ ratingsSummary.ratings_count }}</div>
            </div>
            <div class="border border-line rounded-xl p-4 bg-[#fcfcfd]">
              <div class="muted text-[.76rem] uppercase tracking-wide mb-1">Top Score</div>
              <div class="text-[1.7rem] font-bold text-ink">
                {{ [...ratingsSummary.distribution].sort((a, b) => b.count - a.count || b.score - a.score)[0]?.score ?? '—' }}
                <span class="text-[1rem] text-muted font-medium">star</span>
              </div>
            </div>
          </div>

          <div class="border border-line rounded-xl p-4 mb-5">
            <div class="font-semibold text-[.88rem] text-ink mb-3">Distribution</div>
            <div class="flex flex-col gap-2">
              <div v-for="bucket in [...ratingsSummary.distribution].sort((a, b) => b.score - a.score)" :key="bucket.score" class="flex items-center gap-3">
                <div class="w-12 text-[.84rem] text-ink font-medium">{{ bucket.score }} star</div>
                <div class="flex-1 h-2.5 rounded-full bg-[#eef0f4] overflow-hidden">
                  <div
                    class="h-full bg-brand rounded-full transition-[width]"
                    :style="{ width: `${ratingsSummary.ratings_count ? (bucket.count / ratingsSummary.ratings_count) * 100 : 0}%` }"
                  />
                </div>
                <div class="w-10 text-right text-[.82rem] text-muted">{{ bucket.count }}</div>
              </div>
            </div>
          </div>

          <div class="border border-line rounded-xl overflow-hidden">
            <div class="grid grid-cols-[minmax(0,1.4fr)_120px_160px_120px] gap-3 px-4 py-3 bg-[#f8f9fc] text-[.76rem] font-semibold uppercase tracking-wide text-muted">
              <div>Attendee</div>
              <div>Score</div>
              <div>Rated At</div>
              <div>Status</div>
            </div>

            <div v-if="ratingsLoading && !ratings.length" class="px-4 py-8 text-center muted text-[.84rem]">
              Loading ratings…
            </div>
            <div v-else-if="!ratings.length" class="px-4 py-8 text-center muted text-[.84rem]">
              No one has rated this session yet.
            </div>

            <div
              v-for="row in ratings" :key="row.id"
              class="grid grid-cols-[minmax(0,1.4fr)_120px_160px_120px] gap-3 px-4 py-3 border-t border-line items-center"
            >
              <div class="min-w-0">
                <div class="text-[.88rem] text-ink font-medium truncate">{{ row.participation.name || 'Unnamed attendee' }}</div>
                <div class="text-[.8rem] text-muted truncate">{{ row.participation.email || 'No email' }}</div>
              </div>
              <div class="text-[.88rem] text-ink font-semibold">{{ row.score }} / 5</div>
              <div class="text-[.82rem] text-muted">
                {{ row.rated_at ? new Date(row.rated_at).toLocaleString() : '—' }}
              </div>
              <div>
                <span
                  class="inline-flex items-center px-2 py-1 rounded-full text-[.72rem] font-semibold"
                  :class="row.participation.status === 'confirmed'
                    ? 'bg-[#dcfce7] text-[#15803d]'
                    : 'bg-[#eef0f4] text-[#475569]'"
                >
                  {{ row.participation.status || 'unknown' }}
                </span>
              </div>
            </div>
          </div>
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
