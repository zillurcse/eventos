<script setup lang="ts">
import type { AgendaSession } from '~/stores/sessions'

const props = defineProps<{ session: AgendaSession, tz: string }>()

const bookmarks = useBookmarksStore()
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

function fmtDate(iso: string | null) {
  if (!iso) return ''
  return new Intl.DateTimeFormat('en-US', {
    month: 'short', day: '2-digit', timeZone: props.tz,
  }).format(new Date(iso))
}

const timeLabel = computed(() => {
  const s = props.session
  if (!s.starts_at) return 'Time TBA'
  const range = s.ends_at ? `${fmtTime(s.starts_at)} - ${fmtTime(s.ends_at)}` : fmtTime(s.starts_at)
  return `${fmtDate(s.starts_at)} | ${range}`
})

function initials(name: string | null) {
  const p = (name || '?').trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?'
}

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
      <span class="time">{{ timeLabel }}</span>
      <div class="acts">
        <a v-if="calendarLink" :href="calendarLink" target="_blank" rel="noopener" class="act" title="Add to Google Calendar">
          <svg viewBox="0 0 24 24"><path d="M7 4v3M17 4v3M4 9h16M5 7h14v13H5zM9 13h2M13 13h2M9 16h2M13 16h2" /></svg>
        </a>
        <button class="act" :class="{ on: bookmarked }" type="button" :title="bookmarked ? 'Remove bookmark' : 'Bookmark'" @click="toggleBookmark">
          <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
        </button>
      </div>
    </div>

    <div class="head">
      <span v-if="session.track" class="track" :style="{ '--tc': session.track.color || 'var(--brand-primary)' }">{{ session.track.name }}</span>
      <span v-if="phase === 'live'" class="badge live"><i />LIVE</span>
      <span v-else-if="session.is_featured" class="badge feat">Featured</span>
    </div>

    <h3 class="title">{{ session.title }}</h3>
    <p v-if="session.session_place" class="place">{{ session.session_place }}</p>

    <div v-if="session.tags?.length" class="tags">
      <span v-for="t in session.tags.slice(0, 4)" :key="t" class="tag">{{ t }}</span>
    </div>

    <div class="foot">
      <div class="people">
        <div v-if="speakerPreview.length" class="avs">
          <span
            v-for="sp in speakerPreview"
            :key="sp.id"
            class="av"
            :title="sp.name || ''"
          >
            <img v-if="sp.profile?.image_url" :src="sp.profile.image_url" :alt="sp.name || ''">
            <template v-else>{{ initials(sp.name) }}</template>
          </span>
          <span v-if="extraSpeakers" class="av more">+{{ extraSpeakers }}</span>
        </div>
        <div v-if="session.sponsors?.length" class="sponsors">
          <span v-for="sp in session.sponsors.slice(0, 3)" :key="sp.id" class="sponsor" :title="sp.name">
            <img v-if="sp.logo_url" :src="sp.logo_url" :alt="sp.name">
            <template v-else>{{ sp.name?.[0] }}</template>
          </span>
        </div>
      </div>

      <NuxtLink v-if="phase === 'live' && liveLink" :to="`/session/${session.id}`" class="cta live">Join Live</NuxtLink>
      <NuxtLink v-else-if="replayLink" :to="`/session/${session.id}`" class="cta">Replay</NuxtLink>
      <span v-else-if="phase === 'upcoming'" class="cta ghost">Upcoming</span>
    </div>
  </article>
</template>

<style scoped>
.card { background: #fff; border-radius: 14px; padding: 18px 20px; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.top { display: flex; align-items: center; justify-content: space-between; }
.time { color: var(--brand-primary); font-weight: 700; font-size: .92rem; }
.acts { display: flex; align-items: center; gap: 6px; }
.act { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 8px; border: none; background: #f4f5f8; color: #64748b; cursor: pointer; }
.act:hover { background: #e9ebf5; color: var(--brand-primary); }
.act.on { color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 14%, #fff); }
.act svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.act.on svg { fill: currentColor; }

.head { display: flex; align-items: center; gap: 8px; margin-top: 12px; }
.track { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--tc); background: color-mix(in srgb, var(--tc) 12%, #fff); padding: 3px 9px; border-radius: 999px; }
.badge { font-size: .66rem; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; padding: 3px 8px; border-radius: 999px; }
.badge.live { color: #ef4444; background: #fee2e2; display: inline-flex; align-items: center; gap: 5px; }
.badge.live i { width: 6px; height: 6px; border-radius: 50%; background: #ef4444; animation: pulse 1.4s infinite; }
.badge.feat { color: #b45309; background: #fef3c7; }
@keyframes pulse { 0%,100% { opacity: 1 } 50% { opacity: .3 } }

.title { margin: 8px 0 0; font-size: 1.02rem; font-weight: 700; color: #1e293b; line-height: 1.35; }
.place { margin: 4px 0 0; color: #94a3b8; font-size: .82rem; }
.tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.tag { font-size: .72rem; color: #64748b; background: #f4f5f8; padding: 3px 9px; border-radius: 999px; }

.foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 16px; padding-top: 14px; border-top: 1px solid #eef0f3; }
.people { display: flex; align-items: center; gap: 16px; min-width: 0; }
.avs, .sponsors { display: flex; align-items: center; }
.av { width: 34px; height: 34px; border-radius: 50%; margin-left: -8px; border: 2px solid #fff; background: var(--brand-primary); color: #fff; font-size: .72rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; }
.av:first-child { margin-left: 0; }
.av img { width: 100%; height: 100%; object-fit: cover; }
.av.more { background: #e2e8f0; color: #475569; }
.sponsors { gap: 6px; padding-left: 6px; }
.sponsor { width: 34px; height: 34px; border-radius: 50%; background: #f4f5f8; border: 1px solid #eef0f3; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; color: #64748b; font-weight: 700; font-size: .78rem; }
.sponsor img { width: 100%; height: 100%; object-fit: contain; padding: 4px; }

.cta { flex: 0 0 auto; border: 1px solid var(--brand-primary); color: var(--brand-primary); background: #fff; border-radius: 999px; padding: 8px 22px; font-weight: 700; font-size: .82rem; cursor: pointer; text-decoration: none; text-transform: uppercase; letter-spacing: .4px; }
.cta:hover { background: var(--brand-primary); color: #fff; }
.cta.live { background: #ef4444; border-color: #ef4444; color: #fff; }
.cta.live:hover { background: #dc2626; }
.cta.ghost { border-color: #e2e8f0; color: #94a3b8; cursor: default; }
.cta.ghost:hover { background: #fff; color: #94a3b8; }
</style>
