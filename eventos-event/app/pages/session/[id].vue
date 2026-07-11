<script setup lang="ts">
import type { AgendaSession } from '~/stores/sessions'
import type { PanelAttendee, PanelMessage, Poll } from '~/composables/useSessionPanel'

definePageMeta({ layout: 'event', middleware: 'auth' })

const route = useRoute()
const router = useRouter()
const store = useSessionsStore()
const bookmarks = useBookmarksStore()

const id = computed(() => route.params.id as string)

onMounted(async () => {
  bookmarks.fetch()
  if (!store.loaded) await store.fetchSessions()
})

const session = computed<AgendaSession | null>(
  () => store.sessions.find((s: AgendaSession) => s.id === id.value) ?? null,
)

const tz = computed(() => session.value?.timezone || store.eventTimezone || 'UTC')

// ── Live / upcoming / ended, evaluated in real time ────────────────────────
const now = ref(Date.now())
let ticker: ReturnType<typeof setInterval> | null = null
onMounted(() => { ticker = setInterval(() => (now.value = Date.now()), 15_000) })
onBeforeUnmount(() => { if (ticker) clearInterval(ticker) })

// Streams never start exactly on the minute: hosts open the room early and run
// over. So the player opens a little before the scheduled start and lingers
// after the scheduled end, and the organizer's manual status always wins over
// the clock — that's the "Go live" / "End" button on their side.
const PRE_ROLL_MS = 15 * 60_000
const POST_ROLL_MS = 30 * 60_000
// A session with no end time isn't over the instant it starts; assume a
// sensible slot length rather than flipping straight to "ended".
const ASSUMED_LENGTH_MS = 2 * 60 * 60_000

const phase = computed<'live' | 'ended' | 'upcoming'>(() => {
  const s = session.value
  if (!s) return 'ended'

  if (s.status === 'live') return 'live'
  if (s.status === 'ended' || s.status === 'canceled') return 'ended'

  const start = s.starts_at ? new Date(s.starts_at).getTime() : null
  if (!start) return 'ended'

  const end = s.ends_at ? new Date(s.ends_at).getTime() : start + ASSUMED_LENGTH_MS

  if (now.value < start - PRE_ROLL_MS) return 'upcoming'
  if (now.value > end + POST_ROLL_MS) return 'ended'
  return 'live'
})

// ── Stream/embed resolution ────────────────────────────────────────────────
/**
 * A pasted link sometimes arrives percent-encoded (`?` as %3F, `=` as %3D),
 * which hides the query string from the patterns below and silently demotes an
 * embeddable video to an "open in a new tab" link. Decode before matching.
 */
function decodeLink(url: string): string {
  const u = url.trim()
  if (!/%[0-9a-f]{2}/i.test(u)) return u
  try {
    return decodeURIComponent(u)
  } catch {
    return u
  }
}

function youtubeId(url: string | null): string | null {
  if (!url) return null
  const u = decodeLink(url)
  const patterns = [
    /youtu\.be\/([\w-]{11})/,
    /[?&]v=([\w-]{11})/,
    /youtube\.com\/live\/([\w-]{11})/,
    /youtube\.com\/embed\/([\w-]{11})/,
    /youtube\.com\/shorts\/([\w-]{11})/,
  ]
  for (const p of patterns) {
    const m = u.match(p)
    if (m?.[1]) return m[1]
  }
  return null
}

/** The player src for a YouTube link, or null if it isn't embeddable. */
function youtubeEmbed(url: string | null, autoplay: boolean): string | null {
  const id = youtubeId(url)
  const auto = autoplay ? '&autoplay=1' : ''
  if (id) return `https://www.youtube.com/embed/${id}?rel=0${auto}`

  // "Whatever is live on this channel right now" — YouTube embeds that by
  // channel id, so a /channel/UC…/live URL still plays in-page.
  const channel = decodeLink(url ?? '').match(/youtube\.com\/channel\/(UC[\w-]{10,})/i)
  if (channel?.[1]) return `https://www.youtube.com/embed/live_stream?channel=${channel[1]}${auto}`

  return null
}

const HOST_LABEL: Record<string, string> = {
  youtube: 'YouTube', meet: 'Google Meet', zoom: 'Zoom', rtmp: 'Live Stream', self: 'Live Stream',
}

type Player =
  | { kind: 'iframe', src: string, note?: string }
  | { kind: 'video', src: string, live: boolean }
  | { kind: 'zoom' }
  | { kind: 'jitsi' }
  | { kind: 'agora' }
  | { kind: 'join', url: string, label: string }
  | { kind: 'replay', url: string }
  | { kind: 'upcoming' }
  | { kind: 'none' }

// A self-hosted or RTMP stream is delivered as an HLS playlist (or a plain
// file); those play inline in a <video>, they are not links to open in a tab.
function mediaUrl(url: string | null): string | null {
  if (!url) return null
  return /\.(m3u8|mpd|mp4|webm|ogv|ogg)(\?.*)?$/i.test(url.trim()) ? url.trim() : null
}

const player = computed<Player>(() => {
  const s = session.value
  if (!s) return { kind: 'none' }

  if (phase.value === 'live' && s.is_stream) {
    if (s.who_will_host === 'youtube') {
      const src = youtubeEmbed(s.stream_link, true)
      if (src) return { kind: 'iframe', src }
    }
    if (s.who_will_host === 'zoom' && s.stream_link) return { kind: 'zoom' }
    if (s.who_will_host === 'jitsi') return { kind: 'jitsi' }
    if (s.who_will_host === 'agora') return { kind: 'agora' }
    if (s.vimeo_live_id) return { kind: 'iframe', src: `https://vimeo.com/event/${s.vimeo_live_id}/embed` }

    const live = mediaUrl(s.stream_link)
    if (live) return { kind: 'video', src: live, live: true }

    if (s.stream_link) {
      const host = s.who_will_host || ''
      const label = host === 'youtube' ? 'Watch on YouTube' : `Join on ${HOST_LABEL[host] || 'Live Stream'}`
      return { kind: 'join', url: s.stream_link, label }
    }
  }

  // A recording must never pre-empt the countdown — an upcoming session that
  // carries last year's replay link should still show "starts in…".
  if (phase.value === 'upcoming') return { kind: 'upcoming' }

  if (s.on_demand_recording_link) {
    const replay = youtubeEmbed(s.on_demand_recording_link, false)
    if (replay) return { kind: 'iframe', src: replay, note: 'Recording' }
    const file = mediaUrl(s.on_demand_recording_link)
    if (file) return { kind: 'video', src: file, live: false }
    return { kind: 'replay', url: s.on_demand_recording_link }
  }

  return { kind: 'none' }
})

const api = useApi()
const auth = useAuthStore()

// ── Zoom Web SDK ───────────────────────────────────────────────────────────
const zoomRoot = ref<HTMLElement | null>(null)
const zoomState = ref<'idle' | 'loading' | 'joined' | 'error'>('idle')
const zoomError = ref('')
let zoomClient: any = null

async function startZoom() {
  if (!import.meta.client) return
  if (zoomState.value === 'loading' || zoomState.value === 'joined') return
  const s = session.value
  if (!s) return
  zoomState.value = 'loading'
  zoomError.value = ''
  try {
    const { data } = await api<any>(`/public/sessions/${s.id}/zoom-signature`)
    await nextTick()
    const el = zoomRoot.value
    if (!el) throw new Error('Player container not ready.')
    const ZoomMtgEmbedded = (await import('@zoom/meetingsdk/embedded')).default
    zoomClient = ZoomMtgEmbedded.createClient()
    await zoomClient.init({ zoomAppRoot: el, language: 'en-US', patchJsMedia: true })
    await zoomClient.join({
      signature: data.signature,
      sdkKey: data.sdk_key,
      meetingNumber: data.meeting_number,
      password: data.password || '',
      userName: auth.user?.name || 'Guest',
    })
    zoomState.value = 'joined'
  } catch (e: any) {
    zoomState.value = 'error'
    zoomError.value = e?.data?.message || e?.reason || e?.message || 'Could not start the Zoom session.'
  }
}
async function stopZoom() {
  try { await zoomClient?.leaveMeeting?.() } catch { /* gone */ }
  zoomClient = null
  zoomState.value = 'idle'
}

// ── Jitsi External API ─────────────────────────────────────────────────────
// Room + JWT come from the API (see jitsiToken), not from runtime config.
const jitsiRoot = ref<HTMLElement | null>(null)
const jitsiState = ref<'idle' | 'loading' | 'joined' | 'error'>('idle')
const jitsiError = ref('')
const jitsiTabUrl = ref('')
let jitsiApi: any = null

function loadJitsi(domain: string): Promise<any> {
  return new Promise((resolve, reject) => {
    const w = window as any
    if (w.JitsiMeetExternalAPI) return resolve(w.JitsiMeetExternalAPI)
    const sc = document.createElement('script')
    sc.src = `https://${domain}/external_api.js`
    sc.async = true
    sc.onload = () => (w.JitsiMeetExternalAPI ? resolve(w.JitsiMeetExternalAPI) : reject(new Error('Jitsi failed to initialise.')))
    sc.onerror = () => reject(new Error('Could not reach the Jitsi server.'))
    document.head.appendChild(sc)
  })
}
async function startJitsi() {
  if (!import.meta.client) return
  if (jitsiState.value === 'loading' || jitsiState.value === 'joined') return
  const s = session.value
  if (!s) return
  jitsiState.value = 'loading'
  jitsiError.value = ''
  try {
    // The server resolves the room and signs a JWT: the session host joins as
    // moderator (so the room actually starts), everyone else as a guest.
    const { data } = await api<any>(`/events/${store.eventUuid}/sessions/${s.id}/jitsi-token`)
    const domain: string = data.domain
    const room: string = data.room
    const isHost: boolean = !!data.is_moderator

    jitsiTabUrl.value = `https://${domain}/${room.split('/').map(encodeURIComponent).join('/')}`

    const Api = await loadJitsi(domain)
    await nextTick()
    const el = jitsiRoot.value
    if (!el) throw new Error('Player container not ready.')

    jitsiApi = new Api(domain, {
      roomName: room,
      jwt: data.jwt || undefined,
      parentNode: el,
      width: '100%',
      height: '100%',
      userInfo: { displayName: data.display_name || auth.user?.name || 'Guest' },
      configOverwrite: {
        // `prejoinPageEnabled` is the legacy key; current Jitsi reads
        // `prejoinConfig`. Send both so we land in the room either way.
        prejoinPageEnabled: false,
        prejoinConfig: { enabled: false },
        disableDeepLinking: true,
        // An attendee is watching, not presenting: don't grab their camera or
        // mic (that's the "enable microphone and camera access" nag), and give
        // them a viewer's toolbar rather than a full conferencing one.
        startWithAudioMuted: !isHost,
        startWithVideoMuted: !isHost,
        disableInitialGUM: !isHost,
        toolbarButtons: isHost
          ? undefined
          : ['fullscreen', 'hangup', 'tileview', 'chat', 'raisehand', 'settings'],
      },
      interfaceConfigOverwrite: { MOBILE_APP_PROMO: false },
    })
    jitsiState.value = 'joined'
    jitsiApi.addListener?.('readyToClose', () => { jitsiState.value = 'idle' })
  } catch (e: any) {
    jitsiState.value = 'error'
    jitsiError.value = e?.data?.message || e?.message || 'Could not start the video session.'
  }
}
function stopJitsi() {
  try { jitsiApi?.dispose?.() } catch { /* disposed */ }
  jitsiApi = null
  jitsiState.value = 'idle'
}

// ── Agora (broadcast: host publishes, everyone else subscribes) ────────────
const agoraRoot = ref<HTMLElement | null>(null)
const agoraState = ref<'idle' | 'loading' | 'joined' | 'error'>('idle')
const agoraError = ref('')
const agoraRole = ref<'host' | 'audience'>('audience')
const agoraLiveNow = ref(false) // is a host actually publishing right now?
const agoraMicOn = ref(true)
const agoraCamOn = ref(true)
let agoraClient: any = null
let agoraTracks: any[] = []

/** Put a remote (or our own) video track into the stage element. */
function playInStage(track: any) {
  const el = agoraRoot.value
  if (el && track) track.play(el, { fit: 'contain' })
}

async function startAgora() {
  if (!import.meta.client) return
  if (agoraState.value === 'loading' || agoraState.value === 'joined') return
  const s = session.value
  if (!s) return

  // Our uid is the participation id, so a client left behind in the channel by
  // an earlier attempt would collide with this one (UID_CONFLICT). Always start
  // from a clean slate — a retry after a failed camera grab lands here.
  await stopAgora()

  agoraState.value = 'loading'
  agoraError.value = ''
  try {
    // The server decides the role and bakes it into the token's privileges —
    // an attendee's token simply cannot publish.
    const { data } = await api<any>(`/events/${store.eventUuid}/sessions/${s.id}/agora-token`)
    agoraRole.value = data.role

    const AgoraRTC = (await import('agora-rtc-sdk-ng')).default
    AgoraRTC.setLogLevel(3) // warnings and errors only

    agoraClient = AgoraRTC.createClient({ mode: 'live', codec: 'vp8' })
    await agoraClient.setClientRole(data.role === 'host' ? 'host' : 'audience')

    // Subscribe to whoever is on stage. Audience members never publish, so this
    // is how an attendee sees the host.
    agoraClient.on('user-published', async (user: any, mediaType: 'video' | 'audio') => {
      await agoraClient.subscribe(user, mediaType)
      if (mediaType === 'video') {
        agoraLiveNow.value = true
        await nextTick()
        playInStage(user.videoTrack)
      } else {
        user.audioTrack?.play()
      }
    })
    agoraClient.on('user-unpublished', (_user: any, mediaType: string) => {
      if (mediaType === 'video') agoraLiveNow.value = false
    })

    await agoraClient.join(data.app_id, data.channel, data.token, data.uid)

    if (data.role === 'host') {
      const [mic, cam] = await AgoraRTC.createMicrophoneAndCameraTracks()
      agoraTracks = [mic, cam]
      await agoraClient.publish(agoraTracks)
      agoraLiveNow.value = true
      await nextTick()
      playInStage(cam)
    }

    agoraState.value = 'joined'
  } catch (e: any) {
    // The camera/mic grab happens AFTER we are already in the channel, so a
    // host whose device is busy or blocked would otherwise stay in it holding
    // their uid. Leave before surfacing the error, or the retry collides.
    await stopAgora()
    agoraState.value = 'error'
    agoraError.value = agoraErrorMessage(e)
  }
}

/** Turn an AgoraRTCError into something a speaker can act on. */
function agoraErrorMessage(e: any): string {
  const code = e?.code || e?.name || ''

  if (code === 'PERMISSION_DENIED' || code === 'NotAllowedError') {
    return 'Your browser blocked access to the camera and microphone. Allow them for this site, then try again.'
  }
  if (code === 'DEVICE_NOT_FOUND' || code === 'NotFoundError') {
    return 'No camera or microphone was found on this device.'
  }
  if (code === 'NOT_READABLE' || code === 'NotReadableError' || code === 'TRACK_IS_DISABLED') {
    return 'Your camera or microphone is already in use by another app or tab. Close it, then try again.'
  }
  if (code === 'UID_CONFLICT') {
    return 'You are already in this session in another tab or window. Close it, then try again.'
  }

  return e?.data?.message || e?.message || 'Could not join the video session.'
}

async function stopAgora() {
  const client = agoraClient
  agoraClient = null // drop the handle first, so a re-entrant call can't leave twice

  try {
    for (const t of agoraTracks) { t.stop?.(); t.close?.() }
    agoraTracks = []
    client?.removeAllListeners?.()
    await client?.leave?.()
  } catch { /* already gone */ }

  agoraState.value = 'idle'
  agoraLiveNow.value = false
}

// Host-only stage controls.
async function toggleAgoraMic() {
  const mic = agoraTracks[0]
  if (!mic) return
  agoraMicOn.value = !agoraMicOn.value
  await mic.setEnabled(agoraMicOn.value)
}
async function toggleAgoraCam() {
  const cam = agoraTracks[1]
  if (!cam) return
  agoraCamOn.value = !agoraCamOn.value
  await cam.setEnabled(agoraCamOn.value)
}

// ── Inline video (self-hosted / RTMP output / recording file) ──────────────
const videoEl = ref<HTMLVideoElement | null>(null)
const videoError = ref('')
let hls: { destroy?: () => void } | null = null

function stopVideo() {
  try { hls?.destroy?.() } catch { /* already torn down */ }
  hls = null
}

async function startVideo(src: string) {
  if (!import.meta.client) return
  stopVideo()
  videoError.value = ''
  await nextTick()
  const el = videoEl.value
  if (!el) return

  // Safari plays HLS natively; every other browser needs hls.js.
  const isHls = /\.m3u8(\?.*)?$/i.test(src)
  if (isHls && !el.canPlayType('application/vnd.apple.mpegurl')) {
    try {
      const Hls = (await import('hls.js')).default
      if (Hls.isSupported()) {
        const instance = new Hls({ lowLatencyMode: true })
        instance.loadSource(src)
        instance.attachMedia(el)
        instance.on(Hls.Events.ERROR, (_e: unknown, data: { fatal: boolean }) => {
          if (data.fatal) videoError.value = 'The stream could not be loaded.'
        })
        hls = instance
        return
      }
    } catch {
      videoError.value = 'The stream could not be loaded.'
      return
    }
  }
  el.src = src
}

// Drive the embeds off the resolved player; reset when switching sessions.
function syncEmbed(p: Player) {
  if (p.kind === 'zoom') startZoom()
  else if (p.kind === 'jitsi') startJitsi()
  else if (p.kind === 'agora') startAgora()
  else if (p.kind === 'video') startVideo(p.src)
}
watch(player, (p, prev) => {
  // Re-attach only when the player actually changes, not on every tick.
  if (prev && p.kind === prev.kind && (p.kind !== 'video' || p.src === (prev as { src?: string }).src)) return
  syncEmbed(p)
})
watch(id, () => { stopZoom(); stopJitsi(); stopVideo(); stopAgora() })
onMounted(() => syncEmbed(player.value))
onBeforeUnmount(() => { stopZoom(); stopJitsi(); stopVideo(); stopAgora() })

// ── Formatting ─────────────────────────────────────────────────────────────
function fmtTime(iso: string | null) {
  if (!iso) return ''
  return new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', hour12: true, timeZone: tz.value }).format(new Date(iso))
}
function ordinal(n: number) {
  const s = ['th', 'st', 'nd', 'rd'], v = n % 100
  return n + (s[(v - 20) % 10] ?? s[v] ?? s[0] ?? 'th')
}
function fmtDateLong(iso: string | null) {
  if (!iso) return ''
  const d = new Date(iso)
  const day = Number(new Intl.DateTimeFormat('en-US', { day: 'numeric', timeZone: tz.value }).format(d))
  const mo = new Intl.DateTimeFormat('en-US', { month: 'short', timeZone: tz.value }).format(d)
  const yr = new Intl.DateTimeFormat('en-US', { year: 'numeric', timeZone: tz.value }).format(d)
  return `${ordinal(day)} ${mo}, ${yr}`
}
const dateTimeLabel = computed(() => {
  const s = session.value
  if (!s?.starts_at) return 'Time TBA'
  const range = s.ends_at ? `${fmtTime(s.starts_at)} - ${fmtTime(s.ends_at)}` : fmtTime(s.starts_at)
  return `${fmtDateLong(s.starts_at)} | ${range}`
})
function initials(name?: string | null) {
  const p = (name || '?').trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?'
}
const countdown = computed(() => {
  const s = session.value
  if (!s?.starts_at) return ''
  let diff = Math.max(0, new Date(s.starts_at).getTime() - now.value)
  const d = Math.floor(diff / 86_400_000); diff -= d * 86_400_000
  const h = Math.floor(diff / 3_600_000); diff -= h * 3_600_000
  const m = Math.floor(diff / 60_000)
  if (d) return `Starts in ${d}d ${h}h`
  if (h) return `Starts in ${h}h ${m}m`
  return `Starts in ${m}m`
})

// ── Info card interactions ─────────────────────────────────────────────────
const bookmarked = computed(() => bookmarks.isOn('session', id.value))
function toggleBookmark() { bookmarks.toggle('session', id.value) }

const calendarLink = computed(() => {
  const s = session.value
  if (!s?.starts_at) return null
  const fmt = (iso: string) => iso.replace(/[-:]/g, '').replace(/\.\d+/, '')
  const start = fmt(new Date(s.starts_at).toISOString())
  const end = fmt(new Date(s.ends_at || s.starts_at).toISOString())
  const params = new URLSearchParams({
    action: 'TEMPLATE',
    text: s.title,
    dates: `${start}/${end}`,
    details: (s.description || '').replace(/<[^>]+>/g, '').slice(0, 500),
  })
  return `https://calendar.google.com/calendar/render?${params.toString()}`
})

// Local, per-session rating + note (kept client-side until the API lands).
const rating = ref(0)
const hoverRating = ref(0)
function setRating(n: number) {
  rating.value = n
  if (import.meta.client) localStorage.setItem(`eventos_rating_${id.value}`, String(n))
}
const noteOpen = ref(false)
const note = ref('')
function saveNote() {
  if (import.meta.client) localStorage.setItem(`eventos_note_${id.value}`, note.value)
  noteOpen.value = false
}
watch(id, (v) => {
  if (!import.meta.client) return
  rating.value = Number(localStorage.getItem(`eventos_rating_${v}`) || 0)
  note.value = localStorage.getItem(`eventos_note_${v}`) || ''
}, { immediate: true })

const descExpanded = ref(false)

// ── Engagement side panel (tabs gated by organizer toggles) ────────────────
const ALL_TABS: { key: string, label: string, flag: keyof AgendaSession }[] = [
  { key: 'chat', label: 'Chat', flag: 'can_live_chat' },
  { key: 'qa', label: 'Q&A', flag: 'can_qa' },
  { key: 'polls', label: 'Polls', flag: 'can_live_polls' },
  { key: 'sessions', label: 'Sessions', flag: 'can_session' },
  { key: 'attendees', label: 'Attendees', flag: 'can_attendee_list' },
]
const enabledTabs = computed(() => {
  const s = session.value
  return s ? ALL_TABS.filter(t => !!s[t.flag]) : []
})
const panelOpen = ref(true)
const activeTab = ref('chat')
watch(enabledTabs, (tabs) => {
  if (tabs.length && !tabs.some(t => t.key === activeTab.value)) activeTab.value = tabs[0]!.key
}, { immediate: true })

// Sessions tab: every other session, with its own live/upcoming/ended state so
// an attendee can see what's on right now and hop straight to it.
function phaseOf(s: AgendaSession): 'live' | 'ended' | 'upcoming' {
  const start = s.starts_at ? new Date(s.starts_at).getTime() : null
  const end = s.ends_at ? new Date(s.ends_at).getTime() : null
  if (start && now.value < start) return 'upcoming'
  if (end && now.value > end) return 'ended'
  return start ? 'live' : 'ended'
}
type SessionPhase = 'live' | 'ended' | 'upcoming'
interface SessionEntry { session: AgendaSession, phase: SessionPhase }
const PHASE_RANK: Record<SessionPhase, number> = { live: 0, upcoming: 1, ended: 2 }

const otherSessions = computed<SessionEntry[]>(() => {
  const entries: SessionEntry[] = store.sessions
    .filter((s: AgendaSession) => s.id !== id.value)
    .map((s: AgendaSession) => ({ session: s, phase: phaseOf(s) }))

  return entries
    .sort((a: SessionEntry, b: SessionEntry) => PHASE_RANK[a.phase] - PHASE_RANK[b.phase])
    .slice(0, 40)
})
function goToSession(sid: string) { router.push(`/session/${sid}`) }

// ── Live panel data (Chat / Q&A / Polls / Attendees) ───────────────────────
const {
  chat, questions, polls, attendees, attendeeMeta,
  canModerate, isMuted, qaModeration, pendingCount,
  bind, loaderFor, sendChat, askQuestion, upvoteQuestion, votePoll,
  moderate, removeMessage, createPoll, updatePoll, deletePoll, toggleMute,
} = useSessionPanel()

const chatInput = ref('')
const qaInput = ref('')
const chatBody = ref<HTMLElement | null>(null)
let panelTimer: ReturnType<typeof setInterval> | null = null

function scrollChatBottom() { const el = chatBody.value; if (el) el.scrollTop = el.scrollHeight }

async function refreshActiveTab() {
  const fn = loaderFor(activeTab.value)
  if (!fn) return
  try { await fn() } catch { /* transient */ }
  if (activeTab.value === 'chat') { await nextTick(); scrollChatBottom() }
}
function restartPolling() {
  if (panelTimer) { clearInterval(panelTimer); panelTimer = null }
  if (!panelOpen.value || !enabledTabs.value.length) return
  refreshActiveTab()
  if (loaderFor(activeTab.value)) {
    panelTimer = setInterval(refreshActiveTab, activeTab.value === 'attendees' ? 15_000 : 5_000)
  }
}

watch([() => store.eventUuid, () => session.value?.id], ([ev, sid]: [string | null, string | undefined]) => {
  if (ev && sid) { bind(ev, sid); restartPolling() }
}, { immediate: true })
watch(activeTab, restartPolling)
watch(panelOpen, restartPolling)
onBeforeUnmount(() => { if (panelTimer) clearInterval(panelTimer) })

async function submitChat() {
  const b = chatInput.value.trim()
  if (!b) return
  chatInput.value = ''
  try { await sendChat(b); await nextTick(); scrollChatBottom() } catch { chatInput.value = b }
}
async function submitQuestion() {
  const b = qaInput.value.trim()
  if (!b) return
  qaInput.value = ''
  try { await askQuestion(b) } catch { qaInput.value = b }
}
function pct(o: { votes: number }, p: { total_votes: number }) {
  return p.total_votes ? Math.round((o.votes / p.total_votes) * 100) : 0
}

// ── Host moderation ────────────────────────────────────────────────────────
// The server decides all of this (it re-checks on every write); `canModerate`
// only tells us whether to draw the controls.
const pinnedChat = computed<PanelMessage[]>(() => chat.value.filter((m: PanelMessage) => m.is_pinned))

async function confirmRemove(m: PanelMessage, kind: 'chat' | 'qa') {
  const what = kind === 'chat' ? 'message' : 'question'
  if (!confirm(`Delete this ${what}? Attendees will no longer see it.`)) return
  await removeMessage(m, kind)
}

// A poll's per-option bars only render once the host reveals results (or the
// poll closes); until then attendees just see that they voted.
function pollBarsVisible(p: Poll) {
  return p.results_visible && (!!p.my_vote || p.status === 'closed' || canModerate.value)
}

// Host poll composer — two blank options is the minimum the API accepts.
const composerOpen = ref(false)
const draftQuestion = ref('')
const draftOptions = ref<string[]>(['', ''])
const draftShowResults = ref(true)
const savingPoll = ref(false)

function addDraftOption() { if (draftOptions.value.length < 8) draftOptions.value.push('') }
function removeDraftOption(i: number) { if (draftOptions.value.length > 2) draftOptions.value.splice(i, 1) }
function resetComposer() {
  draftQuestion.value = ''
  draftOptions.value = ['', '']
  draftShowResults.value = true
  composerOpen.value = false
}
const canLaunchPoll = computed(() =>
  !!draftQuestion.value.trim() && draftOptions.value.filter((o: string) => o.trim()).length >= 2,
)
async function launchPoll(status: 'live' | 'draft') {
  if (!canLaunchPoll.value || savingPoll.value) return
  savingPoll.value = true
  try {
    await createPoll({
      question: draftQuestion.value.trim(),
      options: draftOptions.value.map((o: string) => o.trim()).filter(Boolean),
      status,
      show_results: draftShowResults.value,
    })
    resetComposer()
  } finally {
    savingPoll.value = false
  }
}
async function confirmDeletePoll(p: Poll) {
  if (!confirm('Delete this poll and every vote cast on it?')) return
  await deletePoll(p.id)
}

// Attendees tab: search, and mute for the host.
const attendeeSearch = ref('')
const shownAttendees = computed<PanelAttendee[]>(() => {
  const q = attendeeSearch.value.trim().toLowerCase()
  if (!q) return attendees.value
  return attendees.value.filter((a: PanelAttendee) =>
    a.name.toLowerCase().includes(q) || (a.headline ?? '').toLowerCase().includes(q),
  )
})
async function confirmMute(a: PanelAttendee) {
  if (!a.is_muted && !confirm(`Mute ${a.name}? They can still watch and vote, but can't post in this session.`)) return
  await toggleMute(a)
}

const speakers = computed(() => session.value?.speakers ?? [])
const sponsors = computed(() => session.value?.sponsors ?? [])
</script>

<template>
  <div class="page">
    <div v-if="store.loading && !store.loaded" class="state">Loading session…</div>
    <div v-else-if="!session" class="state">
      This session isn’t available.
      <NuxtLink to="/sessions" class="link">Back to Sessions</NuxtLink>
    </div>

    <template v-else>
      <NuxtLink to="/sessions" class="back">
        <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6" /></svg>
        All Sessions
      </NuxtLink>

      <div class="wrap" :class="{ solo: !enabledTabs.length || !panelOpen }">
        <!-- ── Main column ─────────────────────────────────────────────── -->
        <main class="main">
          <div class="screen">
            <iframe
              v-if="player.kind === 'iframe'"
              :src="player.src"
              class="frame"
              allow="autoplay; fullscreen; picture-in-picture; encrypted-media"
              allowfullscreen
              referrerpolicy="strict-origin-when-cross-origin"
            />
            <div v-else-if="player.kind === 'agora'" class="fill">
              <div ref="agoraRoot" class="fill" />

              <!-- Nothing on the wire yet: the host hasn't gone on camera. -->
              <div v-if="agoraState === 'joined' && !agoraLiveNow" class="placeholder over">
                <span class="dot" />
                <p class="ph-title">Waiting for the host</p>
                <p class="ph-sub">The video will appear here as soon as they go on camera.</p>
              </div>
              <div v-else-if="agoraState !== 'joined'" class="placeholder over">
                <template v-if="agoraState === 'error'">
                  <p class="ph-title">Couldn’t join the video</p>
                  <p class="ph-sub">{{ agoraError }}</p>
                  <button class="btn danger" @click="startAgora">Try again</button>
                </template>
                <template v-else><span class="dot" /><p class="ph-title">Connecting…</p></template>
              </div>

              <!-- Only a host is publishing, so only a host gets these. -->
              <div v-if="agoraState === 'joined' && agoraRole === 'host'" class="stagebar">
                <button class="sbtn" :class="{ off: !agoraMicOn }" @click="toggleAgoraMic">
                  {{ agoraMicOn ? 'Mute' : 'Unmute' }}
                </button>
                <button class="sbtn" :class="{ off: !agoraCamOn }" @click="toggleAgoraCam">
                  {{ agoraCamOn ? 'Stop video' : 'Start video' }}
                </button>
                <button class="sbtn danger" @click="stopAgora">Leave stage</button>
              </div>
            </div>
            <div v-else-if="player.kind === 'video'" class="fill">
              <!-- Browsers only allow autoplay when muted, so a live stream
                   starts muted; a recording waits for the viewer to hit play. -->
              <video
                ref="videoEl"
                class="fill"
                controls
                playsinline
                :autoplay="player.live"
                :muted="player.live"
              />
              <div v-if="videoError" class="placeholder over">
                <p class="ph-title">Couldn’t play this stream</p>
                <p class="ph-sub">{{ videoError }}</p>
                <a :href="player.src" target="_blank" rel="noopener" class="btn danger">Open the stream directly</a>
              </div>
            </div>
            <div v-else-if="player.kind === 'zoom'" class="fill">
              <div ref="zoomRoot" class="fill" />
              <div v-if="zoomState !== 'joined'" class="placeholder over">
                <template v-if="zoomState === 'error'">
                  <p class="ph-title">Couldn’t start Zoom here</p>
                  <p class="ph-sub">{{ zoomError }}</p>
                  <a v-if="session.stream_link" :href="session.stream_link" target="_blank" rel="noopener" class="btn danger">Open in Zoom</a>
                </template>
                <template v-else><span class="dot" /><p class="ph-title">Connecting to Zoom…</p></template>
              </div>
            </div>
            <div v-else-if="player.kind === 'jitsi'" class="fill">
              <div ref="jitsiRoot" class="fill" />
              <div v-if="jitsiState === 'loading' || jitsiState === 'error'" class="placeholder over">
                <template v-if="jitsiState === 'error'">
                  <p class="ph-title">Couldn’t start the video here</p>
                  <p class="ph-sub">{{ jitsiError }}</p>
                  <a v-if="jitsiTabUrl" :href="jitsiTabUrl" target="_blank" rel="noopener" class="btn danger">Open in a new tab</a>
                </template>
                <template v-else><span class="dot" /><p class="ph-title">Starting video…</p></template>
              </div>
            </div>
            <div v-else-if="player.kind === 'join'" class="placeholder">
              <span class="dot" />
              <p class="ph-title">This session is live</p>
              <p class="ph-sub">Your host runs on a platform that opens in a new tab.</p>
              <a :href="player.url" target="_blank" rel="noopener" class="btn danger">{{ player.label }}</a>
            </div>
            <div v-else-if="player.kind === 'replay'" class="placeholder">
              <p class="ph-title">Session recording</p>
              <a :href="player.url" target="_blank" rel="noopener" class="btn">Open recording</a>
            </div>
            <div v-else-if="player.kind === 'upcoming'" class="placeholder">
              <p class="ph-title">{{ countdown }}</p>
              <p class="ph-sub">The stream will appear here when the session goes live.</p>
            </div>
            <div v-else class="placeholder">
              <p class="ph-title">No stream available</p>
              <p class="ph-sub">There’s no live stream or recording for this session yet.</p>
            </div>
          </div>

          <div class="card">
            <!-- badges + rating -->
            <div class="toprow">
              <div class="badges">
                <span v-if="phase === 'live'" class="badge live">LIVE</span>
                <span v-else-if="phase === 'upcoming'" class="badge up">Upcoming</span>
                <span v-else class="badge end">Ended</span>
                <span v-if="session.is_featured" class="badge feat">★ Featured</span>
              </div>
              <div v-if="session.is_allowed_to_rate" class="stars" @mouseleave="hoverRating = 0">
                <button
                  v-for="n in 5" :key="n" type="button" class="star"
                  :class="{ on: (hoverRating || rating) >= n }"
                  :title="`Rate ${n} / 5`"
                  @mouseenter="hoverRating = n" @click="setRating(n)"
                >★</button>
              </div>
            </div>

            <h1 class="title">{{ session.title }}</h1>

            <div class="when">
              <svg viewBox="0 0 24 24"><path d="M7 4v3M17 4v3M4 9h16M5 7h14v13H5z" /></svg>
              {{ dateTimeLabel }}
            </div>

            <div class="actions">
              <button class="act icon" :class="{ on: bookmarked }" :title="bookmarked ? 'Saved' : 'Bookmark'" @click="toggleBookmark">
                <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
              </button>
              <button class="act wide" :class="{ on: noteOpen }" @click="noteOpen = !noteOpen">
                <svg viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" /></svg>
                Add Note
              </button>
              <a v-if="calendarLink" :href="calendarLink" target="_blank" rel="noopener" class="act wide">
                <svg viewBox="0 0 24 24"><path d="M7 4v3M17 4v3M4 9h16M5 7h14v13H5zM12 12v4M10 14h4" /></svg>
                Add to Calendar
              </a>
            </div>

            <div v-if="noteOpen" class="note">
              <textarea v-model="note" rows="3" placeholder="Your private note for this session…" />
              <div class="note-actions">
                <span class="hint">Saved on this device</span>
                <button class="btn sm" @click="saveNote">Save note</button>
              </div>
            </div>

            <hr>

            <!-- About -->
            <section>
              <h3 class="h">About</h3>
              <span v-if="session.track" class="chip" :style="{ '--tc': session.track.color || 'var(--brand-primary)' }">{{ session.track.name }}</span>
              <!-- eslint-disable-next-line vue/no-v-html -->
              <div v-if="session.description" class="desc" :class="{ clamp: !descExpanded }" v-html="session.description" />
              <p v-else class="muted">No description provided.</p>
              <button v-if="session.description && session.description.length > 240" class="more" @click="descExpanded = !descExpanded">
                {{ descExpanded ? '− READ LESS' : '+ READ MORE' }}
              </button>
              <div v-if="session.tags?.length" class="tags">
                <span v-for="t in session.tags" :key="t" class="tag">{{ t }}</span>
              </div>
            </section>

            <template v-if="speakers.length">
              <hr>
              <section>
                <h3 class="h">Speakers ({{ speakers.length }})</h3>
                <div class="cards">
                  <div v-for="sp in speakers" :key="sp.id" class="pcard">
                    <div class="pimg">
                      <img v-if="sp.profile?.image_url" :src="sp.profile.image_url" :alt="sp.name || ''">
                      <span v-else class="pini">{{ initials(sp.name) }}</span>
                    </div>
                    <div class="pbody">
                      <div class="pname">{{ sp.name }}</div>
                      <div v-if="sp.profile?.designation" class="prole">{{ sp.profile.designation }}</div>
                      <div v-if="sp.profile?.company" class="prole">{{ sp.profile.company }}</div>
                    </div>
                  </div>
                </div>
              </section>
            </template>

            <template v-if="sponsors.length">
              <hr>
              <section>
                <h3 class="h">Sponsors ({{ sponsors.length }})</h3>
                <div class="cards">
                  <div v-for="sp in sponsors" :key="sp.id" class="scard">
                    <div class="sbanner">
                      <img v-if="sp.logo_url" :src="sp.logo_url" :alt="sp.name">
                      <span v-else>{{ sp.name }}</span>
                    </div>
                    <div class="sname">{{ sp.name }}</div>
                  </div>
                </div>
              </section>
            </template>
          </div>
        </main>

        <!-- ── Engagement side panel ────────────────────────────────────── -->
        <aside v-if="enabledTabs.length && panelOpen" class="panel">
          <button class="pclose" title="Close panel" @click="panelOpen = false">×</button>
          <div class="ptabs">
            <button
              v-for="t in enabledTabs" :key="t.key" type="button"
              class="ptab" :class="{ on: activeTab === t.key }"
              @click="activeTab = t.key"
            >
              {{ t.label }}
              <!-- Questions waiting on the host shouldn't need the tab open to be noticed. -->
              <span v-if="t.key === 'qa' && canModerate && pendingCount" class="tbadge">{{ pendingCount }}</span>
            </button>
          </div>

          <!-- CHAT -->
          <div v-if="activeTab === 'chat'" class="chat">
            <!-- What the host pinned stays put, above the scroll. -->
            <div v-if="pinnedChat.length" class="pinstrip">
              <div v-for="m in pinnedChat" :key="m.id" class="pinrow">
                <svg class="pinico" viewBox="0 0 24 24"><path d="M12 17v5M9 3h6l-1 6 3 3H7l3-3-1-6z" /></svg>
                <span class="pintext"><b>{{ m.author }}:</b> {{ m.body }}</span>
                <button v-if="canModerate" class="pinx" title="Unpin" @click="moderate(m, { is_pinned: false }, 'chat')">×</button>
              </div>
            </div>

            <div ref="chatBody" class="chat-scroll">
              <p v-if="!chat.length" class="empty">No chats yet.<br>Type something to start.</p>
              <div
                v-for="m in chat" :key="m.id"
                class="cmsg" :class="{ mine: m.is_mine, hidden: m.is_hidden }"
              >
                <span class="cav">
                  <img v-if="m.author_image" :src="m.author_image" :alt="m.author">
                  <template v-else>{{ initials(m.author) }}</template>
                </span>
                <div class="cbub">
                  <span class="cwho">
                    {{ m.is_mine ? 'You' : m.author }}
                    <span v-if="m.is_hidden" class="flag">Hidden</span>
                  </span>
                  <span class="ctext">{{ m.body }}</span>

                  <!-- Host tools, plus "delete my own" for everyone else. -->
                  <div v-if="canModerate || m.can_delete" class="mtools">
                    <template v-if="canModerate">
                      <button class="mbtn" :class="{ on: m.is_pinned }" :title="m.is_pinned ? 'Unpin' : 'Pin'" @click="moderate(m, { is_pinned: !m.is_pinned }, 'chat')">
                        {{ m.is_pinned ? 'Unpin' : 'Pin' }}
                      </button>
                      <button class="mbtn" :title="m.is_hidden ? 'Show to attendees' : 'Hide from attendees'" @click="moderate(m, { is_hidden: !m.is_hidden }, 'chat')">
                        {{ m.is_hidden ? 'Unhide' : 'Hide' }}
                      </button>
                    </template>
                    <button v-if="m.can_delete" class="mbtn danger" title="Delete" @click="confirmRemove(m, 'chat')">Delete</button>
                  </div>
                </div>
              </div>
            </div>

            <p v-if="isMuted" class="mutedbar">
              The host has muted you for this session. You can still watch and vote.
            </p>
            <form v-else class="pinput" @submit.prevent="submitChat">
              <input v-model="chatInput" type="text" placeholder="Type a message" maxlength="1000">
              <button type="submit" :disabled="!chatInput.trim()" aria-label="Send">
                <svg viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z" /></svg>
              </button>
            </form>
          </div>

          <!-- Q&A -->
          <div v-else-if="activeTab === 'qa'" class="qa">
            <p v-if="isMuted" class="mutedbar solid">
              The host has muted you for this session.
            </p>
            <form v-else class="pinput solid" @submit.prevent="submitQuestion">
              <input v-model="qaInput" type="text" placeholder="Ask a question…" maxlength="500">
              <button type="submit" :disabled="!qaInput.trim()">Ask</button>
            </form>
            <p v-if="qaModeration && !canModerate" class="modnote">
              Questions are reviewed by the host before everyone sees them.
            </p>

            <div class="qlist">
              <p v-if="!questions.length" class="empty">No questions yet.<br>Be the first to ask.</p>
              <div
                v-for="q in questions" :key="q.id"
                class="qrow"
                :class="{ hidden: q.is_hidden, pending: q.status === 'pending', answered: q.is_answered }"
              >
                <button
                  class="qvote" :class="{ on: q.voted }"
                  :disabled="q.status !== 'published' || q.is_hidden"
                  @click="upvoteQuestion(q.id)"
                >
                  <svg viewBox="0 0 24 24"><path d="M12 19V5M5 12l7-7 7 7" /></svg>
                  <span>{{ q.upvotes }}</span>
                </button>
                <div class="qbody">
                  <span class="qtext">{{ q.body }}</span>
                  <span class="qwho">
                    {{ q.is_mine ? 'You' : q.author }}
                    <span v-if="q.is_pinned" class="flag brand">Pinned</span>
                    <span v-if="q.is_answered" class="flag ok">Answered</span>
                    <span v-if="q.status === 'pending'" class="flag warn">Awaiting host</span>
                    <span v-if="q.status === 'rejected'" class="flag">Rejected</span>
                    <span v-if="q.is_hidden" class="flag">Hidden</span>
                  </span>

                  <div v-if="canModerate || q.can_delete" class="mtools">
                    <template v-if="canModerate">
                      <!-- Pre-moderation: a pending question is a decision, not a row. -->
                      <template v-if="q.status === 'pending'">
                        <button class="mbtn ok" @click="moderate(q, { status: 'published' }, 'qa')">Approve</button>
                        <button class="mbtn danger" @click="moderate(q, { status: 'rejected' }, 'qa')">Reject</button>
                      </template>
                      <template v-else>
                        <button class="mbtn" :class="{ on: q.is_answered }" @click="moderate(q, { is_answered: !q.is_answered }, 'qa')">
                          {{ q.is_answered ? 'Reopen' : 'Answered' }}
                        </button>
                        <button class="mbtn" :class="{ on: q.is_pinned }" @click="moderate(q, { is_pinned: !q.is_pinned }, 'qa')">
                          {{ q.is_pinned ? 'Unpin' : 'Pin' }}
                        </button>
                        <button class="mbtn" @click="moderate(q, { is_hidden: !q.is_hidden }, 'qa')">
                          {{ q.is_hidden ? 'Unhide' : 'Hide' }}
                        </button>
                      </template>
                    </template>
                    <button v-if="q.can_delete" class="mbtn danger" @click="confirmRemove(q, 'qa')">Delete</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- POLLS -->
          <div v-else-if="activeTab === 'polls'" class="polls">
            <!-- Host composes a poll and launches it mid-session. -->
            <template v-if="canModerate">
              <button v-if="!composerOpen" class="newpoll" @click="composerOpen = true">+ New poll</button>
              <div v-else class="composer">
                <label class="clab">Question</label>
                <input v-model="draftQuestion" class="cin" placeholder="What do you want to ask?" maxlength="300">

                <label class="clab">Options</label>
                <div v-for="(_, i) in draftOptions" :key="i" class="crow">
                  <input v-model="draftOptions[i]" class="cin" :placeholder="`Option ${i + 1}`" maxlength="200">
                  <button v-if="draftOptions.length > 2" class="cx" title="Remove" @click="removeDraftOption(i)">×</button>
                </div>
                <button v-if="draftOptions.length < 8" class="cadd" @click="addDraftOption">+ Add option</button>

                <label class="ccheck">
                  <input v-model="draftShowResults" type="checkbox">
                  <span>Show results to attendees while voting is open</span>
                </label>

                <div class="cbtns">
                  <button class="mbtn" @click="resetComposer">Cancel</button>
                  <button class="mbtn" :disabled="!canLaunchPoll || savingPoll" @click="launchPoll('draft')">Save draft</button>
                  <button class="mbtn go" :disabled="!canLaunchPoll || savingPoll" @click="launchPoll('live')">
                    {{ savingPoll ? 'Launching…' : 'Launch' }}
                  </button>
                </div>
              </div>
            </template>

            <p v-if="!polls.length" class="empty">
              {{ canModerate ? 'No polls yet — create one above.' : 'No polls yet.' }}
            </p>

            <div v-for="p in polls" :key="p.id" class="poll" :class="{ draft: p.status === 'draft' }">
              <div class="phead">
                <span class="pq">{{ p.question }}</span>
                <span class="pstat" :class="p.status">{{ p.status }}</span>
              </div>

              <button
                v-for="o in p.options" :key="o.id"
                class="popt" :class="{ picked: p.my_vote === o.id }"
                :disabled="!p.is_active || isMuted"
                @click="votePoll(p.id, o.id)"
              >
                <span v-if="pollBarsVisible(p)" class="pbar" :style="{ width: pct(o, p) + '%' }" />
                <span class="pot">{{ o.text }}</span>
                <span v-if="pollBarsVisible(p)" class="ppc">{{ pct(o, p) }}%</span>
              </button>

              <div class="pmeta">
                {{ p.total_votes }} vote{{ p.total_votes === 1 ? '' : 's' }}
                <template v-if="p.status === 'closed'"> · closed</template>
                <template v-else-if="!p.results_visible"> · results hidden until the host closes it</template>
              </div>

              <!-- Host runs the lifecycle: launch → close → reopen, reveal, delete. -->
              <div v-if="canModerate" class="mtools wrap">
                <button v-if="p.status !== 'live'" class="mbtn go" @click="updatePoll(p.id, { status: 'live' })">
                  {{ p.status === 'draft' ? 'Launch' : 'Reopen' }}
                </button>
                <button v-else class="mbtn" @click="updatePoll(p.id, { status: 'closed' })">Close voting</button>
                <button class="mbtn" @click="updatePoll(p.id, { show_results: !p.show_results })">
                  {{ p.show_results ? 'Hide results' : 'Show results' }}
                </button>
                <button class="mbtn danger" @click="confirmDeletePoll(p)">Delete</button>
              </div>
            </div>
          </div>

          <!-- ATTENDEES -->
          <div v-else-if="activeTab === 'attendees'" class="att">
            <div class="atthead">
              <span class="live-dot" /> {{ attendeeMeta.online }} online · {{ attendeeMeta.total }} total
            </div>
            <input v-model="attendeeSearch" class="asearch" type="search" placeholder="Search attendees…">

            <p v-if="!shownAttendees.length" class="empty">
              {{ attendeeSearch ? 'No one matches that search.' : 'No attendees to show.' }}
            </p>
            <div v-for="a in shownAttendees" :key="a.id" class="arow">
              <span class="aav">
                <img v-if="a.image_url" :src="a.image_url" :alt="a.name">
                <template v-else>{{ initials(a.name) }}</template>
                <i v-if="a.online" class="ondot" />
              </span>
              <div class="ainfo">
                <span class="aname">
                  {{ a.name }}
                  <span v-if="a.is_speaker" class="sbadge">Speaker</span>
                  <span v-if="a.is_muted" class="flag">Muted</span>
                </span>
                <span v-if="a.headline" class="ahl">{{ a.headline }}</span>
              </div>
              <button
                v-if="canModerate && !a.is_speaker"
                class="mbtn" :class="{ danger: !a.is_muted }"
                :title="a.is_muted ? 'Let them post again' : 'Stop them posting in this session'"
                @click="confirmMute(a)"
              >{{ a.is_muted ? 'Unmute' : 'Mute' }}</button>
            </div>
          </div>

          <!-- SESSIONS -->
          <div v-else class="pbody slist">
            <button v-for="e in otherSessions" :key="e.session.id" class="srow" @click="goToSession(e.session.id)">
              <span class="stime">{{ fmtTime(e.session.starts_at) }}</span>
              <span class="stitle">{{ e.session.title }}</span>
              <span class="sphase" :class="e.phase">{{ e.phase === 'live' ? 'LIVE' : e.phase }}</span>
            </button>
            <p v-if="!otherSessions.length" class="empty">No other sessions.</p>
          </div>
        </aside>

        <!-- Reopen tab when panel is collapsed -->
        <button v-if="enabledTabs.length && !panelOpen" class="preopen" title="Open engagement panel" @click="panelOpen = true">
          <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" /></svg>
        </button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.state { background: #fff; border-radius: 14px; padding: 48px 0; text-align: center; color: #64748b; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.link { display: inline-block; margin-left: 8px; color: var(--brand-primary); font-weight: 700; }

.back { display: inline-flex; align-items: center; gap: 5px; color: #64748b; font-weight: 700; font-size: .86rem; margin-bottom: 14px; }
.back svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.back:hover { color: var(--brand-primary); }

.wrap { display: grid; grid-template-columns: 1fr 360px; gap: 18px; align-items: start; position: relative; }
.wrap.solo { grid-template-columns: 1fr; }
@media (max-width: 960px) { .wrap { grid-template-columns: 1fr; } }

/* Video stage */
.main { min-width: 0; }
.screen { position: relative; width: 100%; aspect-ratio: 16 / 9; background: #0b1020; border-radius: 14px; overflow: hidden; box-shadow: 0 8px 30px rgba(15,23,42,.14); }
.frame, .fill { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
.placeholder { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; text-align: center; padding: 24px; color: #cbd5e1; }
.placeholder.over { background: rgba(11,16,32,.92); }
.ph-title { margin: 0; font-size: 1.05rem; font-weight: 800; color: #fff; }
.ph-sub { margin: 0; font-size: .86rem; color: #94a3b8; max-width: 380px; }
.dot { width: 10px; height: 10px; border-radius: 50%; background: #ef4444; box-shadow: 0 0 0 0 rgba(239,68,68,.6); animation: pulse 1.6s infinite; }
@keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(239,68,68,.6) } 70% { box-shadow: 0 0 0 12px rgba(239,68,68,0) } 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0) } }
.btn { margin-top: 8px; display: inline-block; border: 1px solid var(--brand-primary); background: var(--brand-primary); color: #fff; border-radius: 999px; padding: 10px 26px; font-weight: 800; font-size: .84rem; text-decoration: none; }
.btn.danger { background: #ef4444; border-color: #ef4444; }
.btn.sm { padding: 7px 16px; font-size: .78rem; margin: 0; }

/* Info card */
.card { background: #fff; border-radius: 14px; padding: 20px 22px; margin-top: 16px; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.toprow { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.badges { display: flex; align-items: center; gap: 8px; }
.badge { font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; padding: 4px 10px; border-radius: 6px; }
.badge.live { color: #fff; background: #ef4444; }
.badge.up { color: #1d4ed8; background: #dbeafe; }
.badge.end { color: #475569; background: #e2e8f0; }
.badge.feat { color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 12%, #fff); border: 1px solid color-mix(in srgb, var(--brand-primary) 25%, #fff); }
.stars { display: inline-flex; gap: 2px; }
.star { border: none; background: none; cursor: pointer; font-size: 1.25rem; line-height: 1; color: #e2e8f0; padding: 0; }
.star.on { color: #f59e0b; }

.title { margin: 14px 0 0; font-size: 1.3rem; font-weight: 800; color: #1e293b; line-height: 1.3; }
.when { display: flex; align-items: center; gap: 8px; margin-top: 10px; color: #64748b; font-size: .88rem; font-weight: 600; }
.when svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }

.actions { display: flex; gap: 10px; margin-top: 16px; }
.act { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 1px solid #e2e8f0; background: #fff; color: var(--brand-primary); border-radius: 10px; padding: 11px 16px; font: inherit; font-weight: 700; font-size: .86rem; cursor: pointer; text-decoration: none; }
.act.wide { flex: 1; }
.act.icon { width: 46px; padding: 11px 0; color: #64748b; }
.act:hover { border-color: var(--brand-primary); }
.act.on { border-color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 8%, #fff); }
.act svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.act.icon.on svg { fill: currentColor; }

.note { margin-top: 12px; }
.note textarea { width: 100%; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; font: inherit; font-size: .88rem; resize: vertical; }
.note-actions { display: flex; align-items: center; justify-content: space-between; margin-top: 8px; }
.hint { color: #94a3b8; font-size: .76rem; }

hr { border: none; border-top: 1px solid #eef0f3; margin: 18px 0; }
.h { margin: 0 0 12px; font-size: 1rem; font-weight: 800; color: #1e293b; }
.muted { color: #94a3b8; font-size: .88rem; }
.chip { display: inline-block; font-size: .72rem; font-weight: 700; color: var(--tc); border: 1px solid color-mix(in srgb, var(--tc) 40%, #fff); padding: 4px 10px; border-radius: 6px; margin-bottom: 10px; }
.desc { color: #334155; font-size: .92rem; line-height: 1.6; }
.desc :deep(p) { margin: 0 0 10px; }
.desc :deep(a) { color: var(--brand-primary); }
.desc.clamp { display: -webkit-box; -webkit-line-clamp: 3; line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.more { border: none; background: none; color: var(--brand-primary); font-weight: 800; font-size: .76rem; letter-spacing: .3px; cursor: pointer; padding: 8px 0 0; }
.tags { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 12px; }
.tag { font-size: .74rem; color: #64748b; background: #f4f5f8; padding: 4px 10px; border-radius: 6px; }

.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px; }
.pcard { border: 1px solid #eef0f3; border-radius: 12px; overflow: hidden; }
.pimg { position: relative; aspect-ratio: 1; background: #f1f5f9; }
.pimg img { width: 100%; height: 100%; object-fit: cover; }
.pini { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; font-weight: 800; color: #94a3b8; }
.pbody { padding: 12px; }
.pname { font-size: .92rem; font-weight: 800; color: #1e293b; }
.prole { font-size: .78rem; color: #64748b; margin-top: 3px; line-height: 1.35; }

.scard { border: 1px solid #eef0f3; border-radius: 12px; overflow: hidden; }
.sbanner { aspect-ratio: 16 / 7; background: #f8fafc; display: flex; align-items: center; justify-content: center; overflow: hidden; color: #94a3b8; font-weight: 700; padding: 10px; }
.sbanner img { max-width: 100%; max-height: 100%; object-fit: contain; }
.sname { padding: 10px 12px; font-size: .84rem; font-weight: 700; color: #334155; }

/* Engagement panel */
.panel { position: sticky; top: 12px; background: #fff; border-radius: 14px; box-shadow: 0 1px 2px rgba(15,23,42,.05); overflow: hidden; display: flex; flex-direction: column; height: min(680px, calc(100vh - 90px)); }
.pclose { position: absolute; top: 8px; right: 8px; width: 26px; height: 26px; border: none; border-radius: 50%; background: #ef4444; color: #fff; font-size: 1rem; line-height: 1; cursor: pointer; z-index: 2; }
.ptabs { display: flex; gap: 2px; padding: 10px 10px 0; border-bottom: 1px solid #eef0f3; overflow-x: auto; }
.ptab { flex: 0 0 auto; border: none; background: none; color: #94a3b8; font: inherit; font-weight: 700; font-size: .74rem; text-transform: uppercase; letter-spacing: .4px; padding: 10px 12px; border-bottom: 2px solid transparent; cursor: pointer; }
.ptab.on { color: var(--brand-primary); border-bottom-color: var(--brand-primary); }
.tbadge { display: inline-block; margin-left: 4px; min-width: 15px; padding: 0 4px; border-radius: 999px; background: #ef4444; color: #fff; font-size: .62rem; font-weight: 800; line-height: 15px; text-align: center; }
.pbody { flex: 1; overflow-y: auto; padding: 14px; }

.slist { display: flex; flex-direction: column; gap: 6px; }
.srow { display: flex; gap: 10px; align-items: baseline; text-align: left; border: none; background: none; padding: 10px; border-radius: 10px; cursor: pointer; }
.srow:hover { background: #f7f8fa; }
.stime { flex: 0 0 auto; color: var(--brand-primary); font-weight: 800; font-size: .74rem; min-width: 62px; }
.stitle { font-size: .85rem; color: #334155; font-weight: 600; }
.empty { color: #94a3b8; text-align: center; padding: 20px 0; font-size: .86rem; }

/* Chat */
.chat, .qa { flex: 1; min-height: 0; display: flex; flex-direction: column; }
.chat-scroll { flex: 1; min-height: 0; overflow-y: auto; padding: 14px; display: flex; flex-direction: column; gap: 12px; }
.cmsg { display: flex; gap: 8px; align-items: flex-start; }
.cmsg.mine { flex-direction: row-reverse; }
.cav { flex: 0 0 auto; width: 30px; height: 30px; border-radius: 50%; background: var(--brand-primary); color: #fff; font-size: .66rem; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; }
.cav img { width: 100%; height: 100%; object-fit: cover; }
.cbub { display: flex; flex-direction: column; gap: 2px; max-width: 78%; }
.cmsg.mine .cbub { align-items: flex-end; }
.cwho { font-size: .68rem; font-weight: 800; color: #94a3b8; }
.ctext { background: #f1f5f9; color: #1e293b; padding: 8px 11px; border-radius: 12px; font-size: .85rem; line-height: 1.4; word-break: break-word; }
.cmsg.mine .ctext { background: var(--brand-primary); color: #fff; }

/* Panel input row */
.pinput { display: flex; gap: 8px; padding: 10px; border-top: 1px solid #eef0f3; }
.pinput.solid { border-top: none; border-bottom: 1px solid #eef0f3; }
.pinput input { flex: 1; border: 1px solid #e2e8f0; border-radius: 999px; padding: 9px 14px; font: inherit; font-size: .85rem; outline: none; }
.pinput input:focus { border-color: var(--brand-primary); }
.pinput button { flex: 0 0 auto; border: none; background: var(--brand-primary); color: #fff; border-radius: 999px; cursor: pointer; font: inherit; font-weight: 700; font-size: .82rem; padding: 0 16px; display: inline-flex; align-items: center; justify-content: center; }
.pinput button:disabled { opacity: .5; cursor: default; }
.pinput button svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

/* Q&A */
.qlist { flex: 1; min-height: 0; overflow-y: auto; padding: 14px; display: flex; flex-direction: column; gap: 10px; }
.qrow { display: flex; gap: 10px; align-items: flex-start; }
.qvote { flex: 0 0 auto; display: flex; flex-direction: column; align-items: center; gap: 2px; border: 1px solid #e2e8f0; background: #fff; border-radius: 10px; padding: 6px 9px; cursor: pointer; color: #64748b; font: inherit; font-weight: 800; font-size: .78rem; }
.qvote.on { border-color: var(--brand-primary); color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 8%, #fff); }
.qvote svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
.qbody { display: flex; flex-direction: column; gap: 3px; }
.qtext { font-size: .85rem; color: #1e293b; line-height: 1.4; }
.qwho { font-size: .72rem; color: #94a3b8; }

/* Polls */
.polls, .att { flex: 1; min-height: 0; overflow-y: auto; padding: 14px; }
.poll { border: 1px solid #eef0f3; border-radius: 12px; padding: 12px; margin-bottom: 12px; }
.pq { font-size: .9rem; font-weight: 800; color: #1e293b; margin-bottom: 10px; }
.popt { position: relative; display: flex; align-items: center; gap: 8px; width: 100%; border: 1px solid #e2e8f0; background: #fff; border-radius: 9px; padding: 9px 12px; margin-bottom: 7px; cursor: pointer; font: inherit; font-size: .84rem; color: #334155; overflow: hidden; text-align: left; }
.popt:disabled { cursor: default; }
.popt.picked { border-color: var(--brand-primary); font-weight: 700; }
.pbar { position: absolute; left: 0; top: 0; bottom: 0; background: color-mix(in srgb, var(--brand-primary) 14%, #fff); z-index: 0; transition: width .4s; }
.pot { position: relative; z-index: 1; flex: 1; }
.ppc { position: relative; z-index: 1; font-weight: 800; color: var(--brand-primary); }
.pmeta { font-size: .74rem; color: #94a3b8; margin-top: 4px; }

/* Attendees */
.atthead { display: flex; align-items: center; gap: 7px; font-size: .8rem; font-weight: 700; color: #475569; padding-bottom: 12px; margin-bottom: 6px; border-bottom: 1px solid #eef0f3; }
.live-dot { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; }
.arow { display: flex; align-items: center; gap: 11px; padding: 8px 0; }
.aav { position: relative; flex: 0 0 auto; width: 38px; height: 38px; border-radius: 50%; background: var(--brand-primary); color: #fff; font-size: .74rem; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; }
.aav img { width: 100%; height: 100%; object-fit: cover; }
.ondot { position: absolute; right: -1px; bottom: -1px; width: 11px; height: 11px; border-radius: 50%; background: #22c55e; border: 2px solid #fff; }
.ainfo { display: flex; flex-direction: column; min-width: 0; }
.aname { font-size: .86rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px; }
.sbadge { font-size: .6rem; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 12%, #fff); padding: 2px 6px; border-radius: 5px; }
.ahl { font-size: .76rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.preopen { position: fixed; right: 18px; bottom: 18px; width: 52px; height: 52px; border-radius: 50%; border: none; background: var(--brand-primary); color: #fff; cursor: pointer; box-shadow: 0 8px 24px rgba(99,82,231,.4); z-index: 20; display: flex; align-items: center; justify-content: center; }
.preopen svg { width: 24px; height: 24px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }

/* Agora host stage controls, floating over the video. */
.stagebar { position: absolute; left: 50%; bottom: 14px; transform: translateX(-50%); display: flex; gap: 8px; padding: 7px; border-radius: 999px; background: rgba(11,16,32,.78); backdrop-filter: blur(6px); z-index: 3; }
.sbtn { border: 1px solid rgba(255,255,255,.22); background: transparent; color: #fff; border-radius: 999px; padding: 7px 14px; font: inherit; font-weight: 700; font-size: .78rem; cursor: pointer; white-space: nowrap; }
.sbtn:hover { background: rgba(255,255,255,.12); }
.sbtn.off { background: #f59e0b; border-color: #f59e0b; color: #1e293b; }
.sbtn.danger { background: #ef4444; border-color: #ef4444; }

/* ── Moderation ──────────────────────────────────────────────────────────
   Host controls stay quiet until you hover the row they belong to, so an
   attendee-facing panel doesn't turn into a control surface for everyone. */
.mtools { display: flex; gap: 5px; margin-top: 5px; opacity: 0; transition: opacity .12s; }
.mtools.wrap { flex-wrap: wrap; opacity: 1; margin-top: 9px; }
.cmsg:hover .mtools, .qrow:hover .mtools, .mtools:focus-within { opacity: 1; }
.mbtn { flex: 0 0 auto; border: 1px solid #e2e8f0; background: #fff; color: #64748b; border-radius: 7px; padding: 3px 8px; font: inherit; font-weight: 700; font-size: .68rem; cursor: pointer; white-space: nowrap; }
.mbtn:hover { border-color: var(--brand-primary); color: var(--brand-primary); }
.mbtn:disabled { opacity: .45; cursor: default; }
.mbtn.on { border-color: var(--brand-primary); color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 8%, #fff); }
.mbtn.danger:hover { border-color: #ef4444; color: #ef4444; }
.mbtn.ok:hover { border-color: #16a34a; color: #16a34a; }
.mbtn.go { border-color: var(--brand-primary); background: var(--brand-primary); color: #fff; }
.mbtn.go:hover { opacity: .9; color: #fff; }

/* Small status chips: hidden / pending / answered / muted. */
.flag { display: inline-block; font-size: .6rem; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; padding: 2px 5px; border-radius: 4px; margin-left: 5px; background: #e2e8f0; color: #475569; }
.flag.warn { background: #fef3c7; color: #b45309; }
.flag.ok { background: #dcfce7; color: #15803d; }
.flag.brand { background: color-mix(in srgb, var(--brand-primary) 12%, #fff); color: var(--brand-primary); }

/* Hidden rows stay in the host's list, visibly de-emphasised. */
.cmsg.hidden .ctext, .qrow.hidden .qtext { opacity: .5; text-decoration: line-through; }
.qrow.pending { background: #fffbeb; border-radius: 10px; padding: 8px; margin: -8px -8px 0; }
.qrow.answered .qtext { opacity: .65; }

.mutedbar { margin: 0; padding: 11px 14px; border-top: 1px solid #eef0f3; background: #fef2f2; color: #b91c1c; font-size: .78rem; font-weight: 600; text-align: center; }
.mutedbar.solid { border-top: none; border-bottom: 1px solid #eef0f3; }
.modnote { margin: 0; padding: 8px 14px; background: #f8fafc; color: #64748b; font-size: .74rem; border-bottom: 1px solid #eef0f3; }

/* Pinned strip above the chat scroll. */
.pinstrip { border-bottom: 1px solid #eef0f3; background: color-mix(in srgb, var(--brand-primary) 5%, #fff); padding: 8px 10px; display: flex; flex-direction: column; gap: 5px; }
.pinrow { display: flex; align-items: center; gap: 7px; }
.pinico { width: 13px; height: 13px; flex: 0 0 auto; fill: none; stroke: var(--brand-primary); stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.pintext { flex: 1; font-size: .78rem; color: #334155; line-height: 1.35; overflow: hidden; text-overflow: ellipsis; }
.pinx { flex: 0 0 auto; border: none; background: none; color: #94a3b8; font-size: 1rem; line-height: 1; cursor: pointer; padding: 0 2px; }
.pinx:hover { color: #ef4444; }

/* Host poll composer. */
.newpoll { width: 100%; border: 1px dashed #cbd5e1; background: #fff; color: var(--brand-primary); border-radius: 10px; padding: 10px; font: inherit; font-weight: 800; font-size: .8rem; cursor: pointer; margin-bottom: 12px; }
.newpoll:hover { border-color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 5%, #fff); }
.composer { border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; margin-bottom: 14px; background: #fcfcfd; }
.clab { display: block; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; color: #94a3b8; margin: 8px 0 5px; }
.clab:first-child { margin-top: 0; }
.cin { width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 7px 10px; font: inherit; font-size: .82rem; outline: none; }
.cin:focus { border-color: var(--brand-primary); }
.crow { display: flex; align-items: center; gap: 5px; margin-bottom: 6px; }
.cx { flex: 0 0 auto; border: none; background: none; color: #94a3b8; font-size: 1.1rem; line-height: 1; cursor: pointer; padding: 0 3px; }
.cx:hover { color: #ef4444; }
.cadd { border: none; background: none; color: var(--brand-primary); font: inherit; font-weight: 700; font-size: .75rem; cursor: pointer; padding: 2px 0; }
.ccheck { display: flex; align-items: center; gap: 7px; margin: 10px 0 12px; font-size: .76rem; color: #475569; cursor: pointer; }
.cbtns { display: flex; gap: 6px; justify-content: flex-end; }

.phead { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
.poll.draft { border-style: dashed; background: #fcfcfd; }
.pstat { flex: 0 0 auto; font-size: .6rem; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; padding: 2px 6px; border-radius: 4px; background: #e2e8f0; color: #475569; }
.pstat.live { background: #fee2e2; color: #b91c1c; }
.pstat.draft { background: #fef3c7; color: #b45309; }

.asearch { width: 100%; border: 1px solid #e2e8f0; border-radius: 999px; padding: 7px 13px; font: inherit; font-size: .82rem; outline: none; margin-bottom: 6px; }
.asearch:focus { border-color: var(--brand-primary); }
.arow .mbtn { margin-left: auto; }

.srow { align-items: center; }
.sphase { flex: 0 0 auto; margin-left: auto; font-size: .6rem; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; padding: 2px 6px; border-radius: 4px; background: #e2e8f0; color: #475569; }
.sphase.live { background: #ef4444; color: #fff; }
.sphase.upcoming { background: #dbeafe; color: #1d4ed8; }
</style>
