<script setup lang="ts">
// @ts-expect-error project already uses vue-sonner; this file hits a local ts-plugin false positive
import { toast } from 'vue-sonner'
import type { Speaker } from '~/stores/speakers'
import type { AgendaSession } from '~/stores/sessions'

const props = defineProps<{ speaker: Speaker }>()

const store = useSpeakersStore()
const delegates = useDelegatesStore()
const sessionsStore = useSessionsStore()
const bookmarks = useBookmarksStore()
const auth = useAuthStore()
const site = useSiteStore()
const chat = useChatStore()
const api = useApi()

const rating = ref(0)
const hoverRating = ref(0)
const ratingSaving = ref(false)

async function loadRating() {
  if (!site.event?.uuid || !props.speaker?.id || !auth.isAuthed) return
  try {
    const res = await api<{ data: { score: number | null } }>(`/events/${site.event.uuid}/speakers/${props.speaker.id}/rating`)
    rating.value = res.data.score || 0
  } catch {
    rating.value = 0
  }
}

async function setRating(n: number) {
  if (!site.event?.uuid || !props.speaker?.id || ratingSaving.value) return
  ratingSaving.value = true
  const prev = rating.value
  rating.value = n
  try {
    const res = await api<{ data: { score: number } }>(`/events/${site.event.uuid}/speakers/${props.speaker.id}/rating`, {
      method: 'POST',
      body: { score: n },
    })
    rating.value = res.data.score
    toast.success(`You rated this speaker ${res.data.score} out of 5.`)
  } catch (e: any) {
    rating.value = prev
    toast.error(e?.data?.message || 'Could not save your rating.')
  } finally {
    ratingSaving.value = false
  }
}

watch([() => site.event?.uuid, () => props.speaker.id, () => auth.isAuthed], ([eventUuid, speakerId, authed]: [string | undefined, string, boolean]) => {
  if (!eventUuid || !speakerId || !authed) {
    rating.value = 0
    hoverRating.value = 0
    return
  }
  loadRating()
}, { immediate: true })

const chatEnabled = computed(() =>
  auth.isAuthed
  && site.chatModuleEnabled
  && chat.canChatRole('speaker'),
)

const bioExpanded = ref(false)

onMounted(() => {
  if (!sessionsStore.loaded && !sessionsStore.loading) sessionsStore.fetchSessions()
  useMeetingsStore().fetchCapabilities({ force: true })
  if (auth.isAuthed) chat.fetchCapabilities()
  window.addEventListener('keydown', onKey)
})
onUnmounted(() => window.removeEventListener('keydown', onKey))

function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape') store.close()
}

const subtitle = computed(() => {
  const s = props.speaker
  return [s.designation, s.company].filter(Boolean).join(' · ')
})

const bookmarked = computed(() => bookmarks.isOn('speaker', props.speaker.id))

function openConnect() {
  delegates.openConnect({
    id: props.speaker.id,
    name: props.speaker.name,
    company: props.speaker.company,
    job_title: props.speaker.designation,
    avatar_url: props.speaker.image_url,
    online: false,
    role: 'speaker',
  }, 'connect')
}

function openMeet() {
  delegates.openConnect({
    id: props.speaker.id,
    name: props.speaker.name,
    company: props.speaker.company,
    job_title: props.speaker.designation,
    avatar_url: props.speaker.image_url,
    online: false,
    role: 'speaker',
  }, 'meet')
}

const meetEnabled = computed(() => {
  const meetings = useMeetingsStore()
  return auth.isAuthed
    && site.meetingsTabEnabled
    && meetings.canRequest
    && meetings.canMeetRole('speaker')
})

const socialIcons: Record<string, string> = {
  linkedin: 'M4 4h4v16H4zM6 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4M10 8h4v2a4 4 0 0 1 6 3v7h-4v-6a2 2 0 0 0-4 0v6h-4z',
  twitter: 'M22 5a8 8 0 0 1-2.3.6A4 4 0 0 0 21.4 3a8 8 0 0 1-2.5 1A4 4 0 0 0 12 7.5a11 11 0 0 1-8-4 4 4 0 0 0 1.2 5.3A4 4 0 0 1 3 8.3a4 4 0 0 0 3.2 4 4 4 0 0 1-1.8.1 4 4 0 0 0 3.7 2.8A8 8 0 0 1 2 18a11 11 0 0 0 18-8.5A6 6 0 0 0 22 5z',
  facebook: 'M14 9h3V5h-3a4 4 0 0 0-4 4v2H7v4h3v6h4v-6h3l1-4h-4V9a1 1 0 0 1 1-1z',
  instagram: 'M4 8a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6M17 6.5h.01',
  whatsapp: 'M12 3a9 9 0 0 0-7.8 13.5L3 21l4.6-1.2A9 9 0 1 0 12 3zM8.5 8.3c.2-.5.4-.5.6-.5h.5c.2 0 .4 0 .5.4l.7 1.7c0 .2 0 .3-.1.5l-.4.5c-.1.1-.2.3-.1.5.4.7 1 1.4 1.7 1.8.2.1.3.1.5-.1l.5-.5c.1-.1.3-.2.5-.1l1.6.8c.2.1.3.2.3.4v.6c0 .5-.6 1-1.1 1.1-1 .2-2.3 0-4-1.4-1.4-1.1-2.3-2.5-2.6-3.4-.2-.6-.2-1.2 0-1.6z',
  website: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18',
}
const socials = computed(() => Object.entries(props.speaker.social || {}).filter(([, v]) => v))

const bio = computed(() => (props.speaker.bio || '').replace(/<[^>]+>/g, '').trim())
const bioIsLong = computed(() => bio.value.length > 180)
const bioShown = computed(() =>
  bioExpanded.value || !bioIsLong.value ? bio.value : `${bio.value.slice(0, 180).trimEnd()}…`,
)

/** Agenda sessions this speaker is assigned to, soonest first (TBA last). */
const schedule = computed<AgendaSession[]>(() =>
  sessionsStore.sessions
    .filter(s => s.speakers.some(sp => sp.id === props.speaker.id))
    .sort((a, b) => {
      if (!a.starts_at) return 1
      if (!b.starts_at) return -1
      return a.starts_at.localeCompare(b.starts_at)
    }),
)

/** "NOV 18 | TUE | 11:00 AM - 01:00 PM" in the viewer's own timezone. */
function whenLabel(s: AgendaSession) {
  if (!s.starts_at) return 'Time TBA'
  const tz = deviceTimezone()
  const d = new Date(s.starts_at)
  const part = (opts: Intl.DateTimeFormatOptions, date: Date) =>
    new Intl.DateTimeFormat('en-US', { ...opts, timeZone: tz }).format(date)
  const month = part({ month: 'short', day: 'numeric' }, d).toUpperCase()
  const day = part({ weekday: 'short' }, d).toUpperCase()
  const time = (date: Date) => part({ hour: '2-digit', minute: '2-digit', hour12: true }, date)
  const range = s.ends_at ? `${time(d)} - ${time(new Date(s.ends_at))}` : time(d)
  return `${month} | ${day} | ${range}`
}
</script>

<template>
  <div class="overlay" @click.self="store.close()">
    <div class="modal-wrap">
      <button class="x" type="button" aria-label="Close" @click="store.close()">
        <svg viewBox="0 0 24 24">
          <path d="M6 6l12 12M18 6L6 18" />
        </svg>
      </button>

      <div class="modal" role="dialog" aria-modal="true" :aria-label="speaker.name || 'Speaker profile'">
        <header class="head">
          <div class="photo">
            <UserAvatar :src="speaker.image_url" :name="speaker.name" />
          </div>

          <div class="ident">
            <h2>{{ speaker.name }}</h2>
            <p v-if="subtitle" class="role">{{ subtitle }}</p>
            <span v-if="speaker.is_featured" class="featured">
              <svg viewBox="0 0 24 24">
                <path d="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.4 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8z" />
              </svg>
              Featured
            </span>
            <div
              v-if="speaker.can_rate && auth.isAuthed"
              class="stars"
              title="Rate this speaker"
              @mouseleave="hoverRating = 0"
            >
              <button
                v-for="n in 5" :key="n" type="button" class="star"
                :class="{ on: (hoverRating || rating) >= n }"
                :title="`Rate ${n} / 5`"
                :aria-label="`Rate ${n} out of 5`"
                :disabled="ratingSaving"
                @mouseenter="hoverRating = n"
                @click="setRating(n)"
              >
                <svg viewBox="0 0 21 21" aria-hidden="true">
                  <path
                    d="M10.5 0.5L13.59 6.76L20.5 7.77L15.5 12.64L16.68 19.52L10.5 16.27L4.32 19.52L5.5 12.64L0.5 7.77L7.41 6.76L10.5 0.5Z"
                    fill="currentColor"
                    stroke="currentColor"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </button>
            </div>
          </div>
        </header>

        <div class="hacts">
          <button class="hact bm" :class="{ on: bookmarked }" type="button" :title="bookmarked ? 'Saved' : 'Save'"
            @click="bookmarks.toggle('speaker', speaker.id)">
            <svg viewBox="0 0 24 24">
              <path d="M6 3h12v18l-6-4-6 4z" />
            </svg>
          </button>
          <EventNotePopover v-if="auth.isAuthed" type="speaker" :id="speaker.id" block class="hact-note" />
          <button v-if="chatEnabled" class="hact chat" type="button" @click="openConnect">
            <svg viewBox="0 0 24 24"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" /></svg>
            Chat
          </button>
          <button v-if="meetEnabled" class="hact meet" type="button" @click="openMeet">
            <svg viewBox="0 0 24 24"><path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z" /></svg>
            Meet
          </button>
        </div>

        <div class="scroll">
          <template v-if="bio">
            <h3 class="sech">About</h3>
            <div class="bio">
              <p>{{ bioShown }}</p>
              <button v-if="bioIsLong" class="more" type="button" @click="bioExpanded = !bioExpanded">
                {{ bioExpanded ? '− Read less' : '+ Read more' }}
              </button>
            </div>
          </template>

          <div v-if="socials.length" class="socials">
            <a v-for="[k, v] in socials" :key="k" :href="v" target="_blank" rel="noopener" class="soc" :title="k">
              <svg viewBox="0 0 24 24">
                <path :d="socialIcons[k] || socialIcons.website" />
              </svg>
            </a>
          </div>

          <div v-if="sessionsStore.loading && !sessionsStore.loaded" class="sload">Loading schedule…</div>

          <template v-else-if="schedule.length">
            <h3 class="sech">Sessions</h3>
            <section class="sched">
              <article v-for="s in schedule" :key="s.id" class="slot">
                <p class="when">{{ whenLabel(s) }}</p>
                <p class="stitle">{{ s.title }}</p>
                <p v-if="s.session_place" class="splace">
                  <svg viewBox="0 0 24 24">
                    <path d="M12 21s7-6.5 7-11.5A7 7 0 0 0 5 9.5C5 14.5 12 21 12 21z" />
                    <circle cx="12" cy="9.5" r="2.3" />
                  </svg>
                  {{ s.session_place }}
                </p>

                <div v-if="s.speakers.length" class="sspeakers">
                  <p class="scount">Speakers ({{ s.speakers.length }})</p>
                  <div class="savatars">
                    <span v-for="sp in s.speakers.slice(0, 4)" :key="sp.id" class="savatar">
                      <UserAvatar :src="sp.profile?.image_url" :name="sp.name" />
                    </span>
                    <span v-if="s.speakers.length > 4" class="smore">+{{ s.speakers.length - 4 }} More</span>
                  </div>
                </div>
              </article>
            </section>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, .5);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  z-index: 60;
}

.modal-wrap {
  position: relative;
  width: 100%;
  max-width: 640px;
  max-height: 90vh;
}

.modal {
  position: relative;
  background: #fff;
  border-radius: 18px;
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 20px 50px rgba(15, 23, 42, .28);
}

.x {
  position: absolute;
  top: -14px;
  right: -14px;
  z-index: 3;
  border: none;
  background: var(--brand-primary);
  width: 40px;
  height: 40px;
  border-radius: 10px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 6px 16px color-mix(in srgb, var(--brand-primary) 45%, transparent);
}

.x:hover {
  background: color-mix(in srgb, var(--brand-primary) 88%, #000);
}

.x svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: #fff;
  stroke-width: 2.4;
  stroke-linecap: round;
}

.head {
  display: flex;
  gap: 20px;
  padding: 26px 26px 0;
  background: #fff;
}

.photo {
  width: 128px;
  flex: 0 0 auto;
  aspect-ratio: 4 / 5;
  border-radius: 8px;
  overflow: hidden;
  background: color-mix(in srgb, var(--brand-primary) 10%, #fff);
}

.photo :deep(.ua) {
  border-radius: 8px;
}

.ident {
  min-width: 0;
  flex: 1;
  padding-top: 4px;
}

.ident h2 {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 800;
  color: #1e293b;
}

.role {
  margin: 4px 0 0;
  font-size: .92rem;
  color: #64748b;
  line-height: 1.4;
}

.featured {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 12px;
  background: color-mix(in srgb, var(--brand-primary) 12%, #fff);
  color: var(--brand-primary);
  border-radius: 999px;
  padding: 6px 12px;
  font-size: .8rem;
  font-weight: 700;
}

.featured svg {
  width: 14px;
  height: 14px;
  fill: var(--brand-primary);
  stroke: none;
}

.stars {
  display: flex;
  gap: 4px;
  margin-top: 12px;
}

.star {
  border: 0;
  background: transparent;
  padding: 0;
  color: #cbd5e1;
  cursor: pointer;
  transition: color .16s ease, transform .16s ease;
}

.star svg {
  display: block;
  width: 21px;
  height: 21px;
}

.star:hover,
.star.on {
  color: var(--brand-primary);
}

.star:focus-visible {
  outline: 2px solid color-mix(in srgb, var(--brand-primary) 45%, white);
  outline-offset: 3px;
  border-radius: 4px;
}

.star:hover {
  transform: scale(1.06);
}

.hacts {
  display: flex;
  gap: 12px;
  padding: 24px 26px;
}

.hact {
  flex: none;
  width: 52px;
  height: 40px;
  border-radius: 10px;
  border: 1.5px solid var(--brand-primary);
  background: #fff;
  color: var(--brand-primary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.hact svg {
  width: 19px;
  height: 19px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.hact.on {
  background: var(--brand-primary);
  color: #fff;
}

.hact.on svg {
  fill: currentColor;
}

.hact.chat,
.hact.meet {
  flex: 1;
  gap: 10px;
  font: inherit;
  font-size: .92rem;
  font-weight: 700;
}

.hact-note {
  flex: 1;
  height: 48px !important;
  border-radius: 10px !important;
  border: 1.5px solid var(--brand-primary) !important;
  background: #fff !important;
  font-size: .92rem !important;
}

.scroll {
  padding: 0 26px 26px;
  overflow-y: auto;
}

.sech {
  margin: 0 0 12px;
  font-size: 1.1rem;
  font-weight: 800;
  color: #1e293b;
}

.bio {
  margin-bottom: 22px;
  padding-bottom: 22px;
  border-bottom: 1px solid #eef0f3;
}

.bio p {
  margin: 0;
  color: #475569;
  font-size: .95rem;
  line-height: 1.65;
  white-space: pre-line;
}

.more {
  border: none;
  background: none;
  padding: 0;
  margin-top: 8px;
  color: var(--brand-primary);
  font: inherit;
  font-size: .82rem;
  font-weight: 700;
  letter-spacing: .3px;
  cursor: pointer;
}

.socials {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 22px;
  padding-bottom: 22px;
  border-bottom: 1px solid #eef0f3;
}

.soc {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  background: #f4f5f8;
  color: #475569;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.soc:hover {
  background: #eceef2;
}

.soc svg {
  width: 19px;
  height: 19px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.sload {
  color: #94a3b8;
  font-size: .88rem;
  padding: 8px 0;
}

.sched {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.slot {
  border: 1.5px solid color-mix(in srgb, var(--brand-primary) 30%, #fff);
  border-radius: 14px;
  padding: 16px 18px;
}

.when {
  margin: 0;
  color: var(--brand-primary);
  font-weight: 800;
  font-size: .92rem;
  letter-spacing: .2px;
}

.stitle {
  margin: 8px 0 0;
  color: #1e293b;
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.4;
}

.splace {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 8px 0 0;
  color: #94a3b8;
  font-size: .84rem;
}

.splace svg {
  flex: none;
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.sspeakers {
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid #eef0f3;
}

.scount {
  margin: 0 0 10px;
  color: #64748b;
  font-size: .84rem;
}

.savatars {
  display: flex;
  align-items: center;
}

.savatar {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid #fff;
  box-shadow: 0 0 0 1px #eef0f3;
  margin-right: -10px;
  flex: none;
}

.smore {
  margin-left: 18px;
  color: var(--brand-primary);
  font-size: .84rem;
  font-weight: 700;
}

@media (max-width: 560px) {
  .head {
    flex-direction: column;
  }

  .photo {
    width: 108px;
  }

  .hacts {
    flex-wrap: wrap;
  }
}
</style>
