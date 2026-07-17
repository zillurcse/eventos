<script setup lang="ts">
import type { AgendaSession } from '~/stores/sessions'

const props = defineProps<{ session: AgendaSession, tz: string }>()

const bookmarks = useBookmarksStore()
const auth = useAuthStore()
const bookmarked = computed(() => bookmarks.isOn('session', props.session.id))

function toggleBookmark() {
  bookmarks.toggle('session', props.session.id)
}

function fmtTime(iso: string | null) {
  if (!iso) return ''
  return new Intl.DateTimeFormat('en-US', {
    hour: 'numeric', minute: '2-digit', hour12: true, timeZone: props.tz,
  }).format(new Date(iso))
}

const timeLabel = computed(() => {
  const s = props.session
  if (!s.starts_at) return 'Time TBA'
  return s.ends_at ? `${fmtTime(s.starts_at)} - ${fmtTime(s.ends_at)}` : fmtTime(s.starts_at)
})

/** Live now / ended / upcoming, from the session window in real time. */
const phase = computed<'live' | 'ended' | 'upcoming'>(() => {
  const s = props.session
  const now = Date.now()
  const start = s.starts_at ? new Date(s.starts_at).getTime() : null
  const end = s.ends_at ? new Date(s.ends_at).getTime() : null
  if (start && end) {
    if (now < start) return 'upcoming'
    if (now > end) return 'ended'
    return 'live'
  }
  if (start && now < start) return 'upcoming'
  return 'ended'
})

const replayLink = computed(() => props.session.on_demand_recording_link || null)
const liveLink = computed(() => props.session.stream_link || props.session.stream_url || null)

/** Google Calendar quick-add link for the session. */
const calendarLink = computed(() => {
  const s = props.session
  if (!s.starts_at) return null
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

const speakerPreview = computed(() => props.session.speakers.slice(0, 4))
const extraSpeakers = computed(() => Math.max(0, props.session.speakers.length - 4))
</script>

<template>
  <article class="card">
    <div class="top">
      <span class="time"><i class="dot" />{{ timeLabel }}</span>
      <div class="acts">
        <button class="act" :class="{ on: bookmarked }" type="button" :title="bookmarked ? 'Remove bookmark' : 'Bookmark'" @click="toggleBookmark">
          <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
        </button>
        <a v-if="calendarLink" :href="calendarLink" target="_blank" rel="noopener" class="act" title="Add to Google Calendar">
          <svg viewBox="0 0 24 24"><path d="M7 4v3M17 4v3M4 9h16M5 7h14v13H5zM9 13h2M13 13h2M9 16h2M13 16h2" /></svg>
        </a>
        <EventNotePopover v-if="auth.isAuthed" type="session" :id="session.id" :calendar-link="calendarLink" />
      </div>
    </div>

    <div v-if="session.logo_url" class="cover">
      <img :src="session.logo_url" :alt="session.title" loading="lazy">
    </div>

    <div class="head">
      <span v-if="session.track" class="track" :style="{ '--tc': session.track.color || 'var(--brand-primary)' }">{{ session.track.name }}</span>
      <span v-if="phase === 'live'" class="badge live"><i />LIVE</span>
      <span v-else-if="session.is_featured" class="badge feat">Featured</span>
    </div>

    <h3 class="title">{{ session.title }}</h3>
    <p v-if="session.session_place" class="place">
      <svg viewBox="0 0 24 24"><path d="M12 21s-7-6.1-7-11a7 7 0 1 1 14 0c0 4.9-7 11-7 11z" /><circle cx="12" cy="10" r="2.5" /></svg>
      {{ session.session_place }}
    </p>

    <div v-if="session.tags?.length" class="tags">
      <span v-for="t in session.tags.slice(0, 4)" :key="t" class="tag">{{ t }}</span>
    </div>

    <div v-if="speakerPreview.length" class="people">
      <span class="peoplelabel">Speakers ({{ session.speakers.length }})</span>
      <div class="avs">
        <span
          v-for="sp in speakerPreview"
          :key="sp.id"
          class="av"
          :title="sp.name || ''"
        >
          <UserAvatar :src="sp.profile?.image_url" :name="sp.name" />
        </span>
        <span v-if="extraSpeakers" class="av more">+{{ extraSpeakers }} More</span>
      </div>
    </div>
    <div v-if="session.sponsors?.length" class="sponsors">
      <span v-for="sp in session.sponsors.slice(0, 3)" :key="sp.id" class="sponsor" :title="sp.name">
        <UserAvatar :src="sp.logo_url" :name="sp.name" />
      </span>
    </div>

    <NuxtLink v-if="phase === 'live' && liveLink" :to="`/session/${session.id}`" class="cta live">Join Now</NuxtLink>
    <NuxtLink v-else-if="replayLink" :to="`/session/${session.id}`" class="cta">Watch Replay</NuxtLink>
    <span v-else-if="phase === 'upcoming'" class="cta ghost">Upcoming</span>
    <span v-else class="cta ghost">Session Ended</span>
  </article>
</template>

<style scoped>
.card { display: flex; flex-direction: column; background: #fff; border-radius: 14px; padding: 14px 16px 16px; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.top { display: flex; align-items: center; justify-content: space-between; }
.time { display: inline-flex; align-items: center; gap: 6px; color: var(--brand-primary); font-weight: 700; font-size: .86rem; }
.time .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--brand-primary); flex: 0 0 auto; }
.acts { display: flex; align-items: center; gap: 6px; }
.act { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 8px; border: none; background: #f4f5f8; color: #64748b; cursor: pointer; }
.act:hover { background: #e9ebf5; color: var(--brand-primary); }
.act.on { color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 14%, #fff); }
.act svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.act.on svg { fill: currentColor; }

.cover { margin-top: 12px; border-radius: 10px; overflow: hidden; aspect-ratio: 16 / 9; background: #f4f5f8; }
.cover img { width: 100%; height: 100%; object-fit: cover; display: block; }

.head { display: flex; align-items: center; gap: 8px; margin-top: 12px; }
.track { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--tc); background: color-mix(in srgb, var(--tc) 12%, #fff); padding: 3px 9px; border-radius: 999px; }
.badge { font-size: .66rem; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; padding: 3px 8px; border-radius: 999px; }
.badge.live { color: #ef4444; background: #fee2e2; display: inline-flex; align-items: center; gap: 5px; }
.badge.live i { width: 6px; height: 6px; border-radius: 50%; background: #ef4444; animation: pulse 1.4s infinite; }
.badge.feat { color: #b45309; background: #fef3c7; }
@keyframes pulse { 0%,100% { opacity: 1 } 50% { opacity: .3 } }

.title { margin: 8px 0 0; font-size: 1rem; font-weight: 700; color: #1e293b; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.place { display: flex; align-items: center; gap: 5px; margin: 6px 0 0; color: #94a3b8; font-size: .82rem; }
.place svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; flex: 0 0 auto; }
.tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.tag { font-size: .72rem; color: #64748b; background: #f4f5f8; padding: 3px 9px; border-radius: 999px; }

.people { margin-top: 14px; padding-top: 14px; border-top: 1px solid #eef0f3; }
.peoplelabel { display: block; margin-bottom: 8px; color: #94a3b8; font-size: .78rem; font-weight: 600; }
.avs, .sponsors { display: flex; align-items: center; }
.av { width: 34px; height: 34px; border-radius: 50%; margin-left: -8px; border: 2px solid #fff; background: var(--brand-primary); color: #fff; font-size: .72rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; }
.av:first-child { margin-left: 0; }
.av img { width: 100%; height: 100%; object-fit: cover; }
.av.more { width: auto; border-radius: 999px; padding: 0 10px; background: none; color: var(--brand-primary); font-weight: 700; }
.sponsors { gap: 6px; margin-top: 10px; }
.sponsor { width: 34px; height: 34px; border-radius: 50%; background: #f4f5f8; border: 1px solid #eef0f3; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; color: #64748b; font-weight: 700; font-size: .78rem; }
.sponsor img { width: 100%; height: 100%; object-fit: contain; padding: 4px; }

.cta { display: block; margin-top: 14px; text-align: center; border: 1px solid var(--brand-primary); color: var(--brand-primary); background: #fff; border-radius: 10px; padding: 10px 22px; font-weight: 700; font-size: .86rem; cursor: pointer; text-decoration: none; }
.cta:hover { background: var(--brand-primary); color: #fff; }
.cta.live { background: var(--brand-primary); border-color: var(--brand-primary); color: #fff; }
.cta.live:hover { opacity: .92; }
.cta.ghost { border-color: #e2e8f0; color: #94a3b8; cursor: default; }
.cta.ghost:hover { background: #fff; color: #94a3b8; }
</style>
