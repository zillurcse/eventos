<script setup lang="ts">
import type { Speaker } from '~/stores/speakers'
import type { AgendaSession } from '~/stores/sessions'

const props = defineProps<{ speaker: Speaker }>()

const store = useSpeakersStore()
const sessionsStore = useSessionsStore()
const bookmarks = useBookmarksStore()
const auth = useAuthStore()

const bioExpanded = ref(false)

onMounted(() => {
  // The schedule reuses the public agenda payload; fetch it once on demand.
  if (!sessionsStore.loaded && !sessionsStore.loading) sessionsStore.fetchSessions()
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

const connectState = computed(() => store.connected[props.speaker.id])
const bookmarked = computed(() => bookmarks.isOn('speaker', props.speaker.id))

const socialIcons: Record<string, string> = {
  linkedin: 'M4 4h4v16H4zM6 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4M10 8h4v2a4 4 0 0 1 6 3v7h-4v-6a2 2 0 0 0-4 0v6h-4z',
  twitter: 'M22 5a8 8 0 0 1-2.3.6A4 4 0 0 0 21.4 3a8 8 0 0 1-2.5 1A4 4 0 0 0 12 7.5a11 11 0 0 1-8-4 4 4 0 0 0 1.2 5.3A4 4 0 0 1 3 8.3a4 4 0 0 0 3.2 4 4 4 0 0 1-1.8.1 4 4 0 0 0 3.7 2.8A8 8 0 0 1 2 18a11 11 0 0 0 18-8.5A6 6 0 0 0 22 5z',
  facebook: 'M14 9h3V5h-3a4 4 0 0 0-4 4v2H7v4h3v6h4v-6h3l1-4h-4V9a1 1 0 0 1 1-1z',
  instagram: 'M4 8a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6M17 6.5h.01',
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

/** "NOV 18 | TUE | 11:00 AM - 01:00 PM" in the event's timezone. */
function whenLabel(s: AgendaSession) {
  if (!s.starts_at) return 'Time TBA'
  const tz = sessionsStore.eventTimezone
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
    <div class="modal" role="dialog" aria-modal="true" :aria-label="speaker.name || 'Speaker profile'">
      <button class="x" type="button" aria-label="Close" @click="store.close()">
        <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
      </button>

      <header class="head">
        <div class="photo">
          <UserAvatar :src="speaker.image_url" :name="speaker.name" />
        </div>

        <div class="ident">
          <h2>{{ speaker.name }}</h2>
          <p v-if="subtitle" class="role">{{ subtitle }}</p>

          <div class="hrow">
            <div v-if="socials.length" class="socials">
              <a
                v-for="[k, v] in socials"
                :key="k"
                :href="v"
                target="_blank"
                rel="noopener"
                class="soc"
                :title="k"
              >
                <svg viewBox="0 0 24 24"><path :d="socialIcons[k] || socialIcons.linkedin" /></svg>
              </a>
            </div>

            <div class="hacts">
              <button
                v-if="auth.isAuthed"
                class="hact"
                :class="{ on: connectState === 'pending' }"
                type="button"
                :title="connectState === 'pending' ? 'Request sent' : 'Connect'"
                :disabled="connectState === 'pending'"
                @click="store.connect(speaker)"
              >
                <svg v-if="connectState === 'pending'" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
                <svg v-else viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM19 8v6M22 11h-6" /></svg>
              </button>
              <button
                class="hact"
                :class="{ on: bookmarked }"
                type="button"
                :title="bookmarked ? 'Saved' : 'Save'"
                @click="bookmarks.toggle('speaker', speaker.id)"
              >
                <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
              </button>
            </div>
          </div>
        </div>
      </header>

      <div class="scroll">
        <div v-if="bio" class="bio">
          <p>{{ bioShown }}</p>
          <button v-if="bioIsLong" class="more" type="button" @click="bioExpanded = !bioExpanded">
            {{ bioExpanded ? '− READ LESS' : '+ READ MORE' }}
          </button>
        </div>

        <div v-if="sessionsStore.loading && !sessionsStore.loaded" class="sload">Loading schedule…</div>

        <section v-else-if="schedule.length" class="sched">
          <article v-for="s in schedule" :key="s.id" class="slot">
            <svg class="cal" viewBox="0 0 24 24"><path d="M7 3v3M15 3v3M3.5 9.5h15M4 5h14a.5.5 0 0 1 .5.5V19a1 1 0 0 1-1 1H4.5a1 1 0 0 1-1-1V5.5A.5.5 0 0 1 4 5zM19 16h4M21 14v4" /></svg>
            <div class="sbody">
              <p class="when">{{ whenLabel(s) }}</p>
              <p class="stitle">{{ s.title }}</p>
              <p v-if="s.session_place" class="splace">{{ s.session_place }}</p>
            </div>
          </article>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(15,23,42,.5); display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 60; }
.modal { position: relative; background: #fff; border-radius: 18px; width: 100%; max-width: 640px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 50px rgba(15,23,42,.28); }

.x { position: absolute; top: 14px; right: 14px; z-index: 3; border: none; background: #ef4444; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.x:hover { background: #dc2626; }
.x svg { width: 15px; height: 15px; fill: none; stroke: #fff; stroke-width: 2.2; stroke-linecap: round; }

.head { display: flex; gap: 20px; padding: 26px 26px 24px; background: var(--brand-primary); color: #fff; }
.photo { width: 168px; flex: 0 0 auto; aspect-ratio: 4 / 5; border-radius: 8px; overflow: hidden; background: rgba(255,255,255,.15); display: flex; align-items: center; justify-content: center; }
.photo img { width: 100%; height: 100%; object-fit: cover; }
.ini { font-size: 2.8rem; font-weight: 700; color: rgba(255,255,255,.85); letter-spacing: 1px; }

.ident { min-width: 0; flex: 1; display: flex; flex-direction: column; justify-content: center; }
.ident h2 { margin: 0; font-size: 1.5rem; font-weight: 800; }
.role { margin: 4px 0 0; font-size: .95rem; color: rgba(255,255,255,.85); }

.hrow { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-top: 22px; }
.socials { display: flex; align-items: center; gap: 14px; }
.soc { color: #fff; display: inline-flex; opacity: .9; }
.soc:hover { opacity: 1; }
.soc svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }

.hacts { display: flex; gap: 10px; margin-left: auto; }
.hact { width: 40px; height: 40px; border-radius: 50%; border: 1px solid rgba(255,255,255,.7); background: #fff; color: var(--brand-primary); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
.hact.on { background: #16a34a; border-color: #16a34a; color: #fff; }
.hact:disabled { cursor: default; }
.hact svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }

.scroll { padding: 22px 26px 26px; overflow-y: auto; }
.bio { margin-bottom: 18px; }
.bio p { margin: 0; color: #475569; font-size: .95rem; line-height: 1.65; white-space: pre-line; }
.more { border: none; background: none; padding: 0; margin-top: 8px; color: var(--brand-primary); font: inherit; font-size: .82rem; font-weight: 700; letter-spacing: .3px; cursor: pointer; }

.sload { color: #94a3b8; font-size: .88rem; padding: 8px 0; }

.sched { display: flex; flex-direction: column; gap: 12px; }
.slot { display: flex; gap: 14px; align-items: flex-start; border: 1.5px solid color-mix(in srgb, var(--brand-primary) 40%, #fff); border-radius: 14px; padding: 14px 16px; }
.cal { flex: 0 0 auto; width: 26px; height: 26px; margin-top: 2px; fill: none; stroke: var(--brand-primary); stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.sbody { min-width: 0; }
.when { margin: 0; color: var(--brand-primary); font-weight: 800; font-size: .98rem; letter-spacing: .2px; }
.stitle { margin: 4px 0 0; color: #1e293b; font-size: .92rem; line-height: 1.45; }
.splace { margin: 3px 0 0; color: #94a3b8; font-size: .8rem; }

@media (max-width: 560px) {
  .head { flex-direction: column; }
  .photo { width: 128px; }
}
</style>
