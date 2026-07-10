<script setup lang="ts">
import type { AgendaSession } from '~/stores/sessions'

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
  () => store.sessions.find(s => s.id === id.value) ?? null,
)

const tz = computed(() => session.value?.timezone || store.eventTimezone || 'UTC')

// ── Live / upcoming / ended, evaluated in real time ────────────────────────
const now = ref(Date.now())
let ticker: ReturnType<typeof setInterval> | null = null
onMounted(() => { ticker = setInterval(() => (now.value = Date.now()), 15_000) })
onBeforeUnmount(() => { if (ticker) clearInterval(ticker) })

const phase = computed<'live' | 'ended' | 'upcoming'>(() => {
  const s = session.value
  if (!s) return 'ended'
  const start = s.starts_at ? new Date(s.starts_at).getTime() : null
  const end = s.ends_at ? new Date(s.ends_at).getTime() : null
  if (start && end) {
    if (now.value < start) return 'upcoming'
    if (now.value > end) return 'ended'
    return 'live'
  }
  if (start && now.value < start) return 'upcoming'
  return 'ended'
})

// ── Stream/embed resolution ────────────────────────────────────────────────
function youtubeId(url: string | null): string | null {
  if (!url) return null
  const patterns = [
    /youtu\.be\/([\w-]{11})/,
    /[?&]v=([\w-]{11})/,
    /youtube\.com\/live\/([\w-]{11})/,
    /youtube\.com\/embed\/([\w-]{11})/,
    /youtube\.com\/shorts\/([\w-]{11})/,
  ]
  for (const p of patterns) {
    const m = url.match(p)
    if (m?.[1]) return m[1]
  }
  return null
}

const HOST_LABEL: Record<string, string> = {
  youtube: 'YouTube', meet: 'Google Meet', zoom: 'Zoom', rtmp: 'Live Stream', self: 'Live Stream',
}

type Player =
  | { kind: 'iframe', src: string, note?: string }
  | { kind: 'zoom' }
  | { kind: 'jitsi' }
  | { kind: 'join', url: string, label: string }
  | { kind: 'replay', url: string }
  | { kind: 'upcoming' }
  | { kind: 'none' }

const player = computed<Player>(() => {
  const s = session.value
  if (!s) return { kind: 'none' }

  if (phase.value === 'live' && s.is_stream) {
    if (s.who_will_host === 'youtube') {
      const yid = youtubeId(s.stream_link)
      if (yid) return { kind: 'iframe', src: `https://www.youtube.com/embed/${yid}?autoplay=1&rel=0` }
    }
    if (s.who_will_host === 'zoom' && s.stream_link) return { kind: 'zoom' }
    if (s.who_will_host === 'jitsi') return { kind: 'jitsi' }
    if (s.vimeo_live_id) return { kind: 'iframe', src: `https://vimeo.com/event/${s.vimeo_live_id}/embed` }
    if (s.stream_link) {
      const host = s.who_will_host || ''
      const label = host === 'youtube' ? 'Watch on YouTube' : `Join on ${HOST_LABEL[host] || 'Live Stream'}`
      return { kind: 'join', url: s.stream_link, label }
    }
  }

  if (s.on_demand_recording_link) {
    const rid = youtubeId(s.on_demand_recording_link)
    if (rid) return { kind: 'iframe', src: `https://www.youtube.com/embed/${rid}?rel=0`, note: 'Recording' }
    return { kind: 'replay', url: s.on_demand_recording_link }
  }

  if (phase.value === 'upcoming') return { kind: 'upcoming' }
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
const { public: { jitsiDomain: defaultJitsiDomain } } = useRuntimeConfig()
const jitsiRoot = ref<HTMLElement | null>(null)
const jitsiState = ref<'idle' | 'loading' | 'joined' | 'error'>('idle')
const jitsiError = ref('')
const jitsiTabUrl = ref('')
let jitsiApi: any = null

function resolveJitsi(s: AgendaSession): { domain: string, room: string } {
  const raw = (s.stream_link || '').trim()
  const url = raw.match(/^https?:\/\/([^/]+)\/(.+)$/i)
  if (url) return { domain: url[1]!, room: decodeURIComponent(url[2]!.replace(/\/+$/, '')) }
  if (raw && !raw.includes('/')) return { domain: String(defaultJitsiDomain), room: raw }
  return { domain: String(defaultJitsiDomain), room: `expouse-${s.id}` }
}
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
    const { domain, room } = resolveJitsi(s)
    jitsiTabUrl.value = `https://${domain}/${encodeURIComponent(room)}`
    const Api = await loadJitsi(domain)
    await nextTick()
    const el = jitsiRoot.value
    if (!el) throw new Error('Player container not ready.')
    jitsiApi = new Api(domain, {
      roomName: room,
      parentNode: el,
      width: '100%',
      height: '100%',
      userInfo: { displayName: auth.user?.name || 'Guest' },
      configOverwrite: { prejoinPageEnabled: false, disableDeepLinking: true },
      interfaceConfigOverwrite: { MOBILE_APP_PROMO: false },
    })
    jitsiState.value = 'joined'
    jitsiApi.addListener?.('readyToClose', () => { jitsiState.value = 'idle' })
  } catch (e: any) {
    jitsiState.value = 'error'
    jitsiError.value = e?.message || 'Could not start the video session.'
  }
}
function stopJitsi() {
  try { jitsiApi?.dispose?.() } catch { /* disposed */ }
  jitsiApi = null
  jitsiState.value = 'idle'
}

// Drive the embeds off the resolved player kind; reset when switching sessions.
function syncEmbed(kind: string) {
  if (kind === 'zoom') startZoom()
  else if (kind === 'jitsi') startJitsi()
}
watch(() => player.value.kind, syncEmbed)
watch(id, () => { stopZoom(); stopJitsi() })
onMounted(() => syncEmbed(player.value.kind))
onBeforeUnmount(() => { stopZoom(); stopJitsi() })

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

const otherSessions = computed(() =>
  store.sessions.filter(s => s.id !== id.value).slice(0, 30),
)
function goToSession(sid: string) { router.push(`/session/${sid}`) }

// ── Live panel data (Chat / Q&A / Polls / Attendees) ───────────────────────
const {
  chat, questions, polls, attendees, attendeeMeta,
  bind, loaderFor, sendChat, askQuestion, upvoteQuestion, votePoll,
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
            >{{ t.label }}</button>
          </div>

          <!-- CHAT -->
          <div v-if="activeTab === 'chat'" class="chat">
            <div ref="chatBody" class="chat-scroll">
              <p v-if="!chat.length" class="empty">No chats yet.<br>Type something to start.</p>
              <div v-for="m in chat" :key="m.id" class="cmsg" :class="{ mine: m.is_mine }">
                <span class="cav">
                  <img v-if="m.author_image" :src="m.author_image" :alt="m.author">
                  <template v-else>{{ initials(m.author) }}</template>
                </span>
                <div class="cbub">
                  <span class="cwho">{{ m.is_mine ? 'You' : m.author }}</span>
                  <span class="ctext">{{ m.body }}</span>
                </div>
              </div>
            </div>
            <form class="pinput" @submit.prevent="submitChat">
              <input v-model="chatInput" type="text" placeholder="Type a message" maxlength="1000">
              <button type="submit" :disabled="!chatInput.trim()" aria-label="Send">
                <svg viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z" /></svg>
              </button>
            </form>
          </div>

          <!-- Q&A -->
          <div v-else-if="activeTab === 'qa'" class="qa">
            <form class="pinput solid" @submit.prevent="submitQuestion">
              <input v-model="qaInput" type="text" placeholder="Ask a question…" maxlength="500">
              <button type="submit" :disabled="!qaInput.trim()">Ask</button>
            </form>
            <div class="qlist">
              <p v-if="!questions.length" class="empty">No questions yet.<br>Be the first to ask.</p>
              <div v-for="q in questions" :key="q.id" class="qrow">
                <button class="qvote" :class="{ on: q.voted }" @click="upvoteQuestion(q.id)">
                  <svg viewBox="0 0 24 24"><path d="M12 19V5M5 12l7-7 7 7" /></svg>
                  <span>{{ q.upvotes }}</span>
                </button>
                <div class="qbody">
                  <span class="qtext">{{ q.body }}</span>
                  <span class="qwho">{{ q.is_mine ? 'You' : q.author }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- POLLS -->
          <div v-else-if="activeTab === 'polls'" class="polls">
            <p v-if="!polls.length" class="empty">No polls yet.</p>
            <div v-for="p in polls" :key="p.id" class="poll">
              <div class="pq">{{ p.question }}</div>
              <button
                v-for="o in p.options" :key="o.id"
                class="popt" :class="{ picked: p.my_vote === o.id }"
                :disabled="!p.is_active"
                @click="votePoll(p.id, o.id)"
              >
                <span v-if="p.my_vote || !p.is_active" class="pbar" :style="{ width: pct(o, p) + '%' }" />
                <span class="pot">{{ o.text }}</span>
                <span v-if="p.my_vote || !p.is_active" class="ppc">{{ pct(o, p) }}%</span>
              </button>
              <div class="pmeta">
                {{ p.total_votes }} vote{{ p.total_votes === 1 ? '' : 's' }}
                <template v-if="!p.is_active"> · closed</template>
              </div>
            </div>
          </div>

          <!-- ATTENDEES -->
          <div v-else-if="activeTab === 'attendees'" class="att">
            <div class="atthead">
              <span class="live-dot" /> {{ attendeeMeta.online }} online · {{ attendeeMeta.total }} total
            </div>
            <p v-if="!attendees.length" class="empty">No attendees to show.</p>
            <div v-for="a in attendees" :key="a.id" class="arow">
              <span class="aav">
                <img v-if="a.image_url" :src="a.image_url" :alt="a.name">
                <template v-else>{{ initials(a.name) }}</template>
                <i v-if="a.online" class="ondot" />
              </span>
              <div class="ainfo">
                <span class="aname">{{ a.name }}<span v-if="a.is_speaker" class="sbadge">Speaker</span></span>
                <span v-if="a.headline" class="ahl">{{ a.headline }}</span>
              </div>
            </div>
          </div>

          <!-- SESSIONS -->
          <div v-else class="pbody slist">
            <button v-for="s in otherSessions" :key="s.id" class="srow" @click="goToSession(s.id)">
              <span class="stime">{{ fmtTime(s.starts_at) }}</span>
              <span class="stitle">{{ s.title }}</span>
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
</style>
