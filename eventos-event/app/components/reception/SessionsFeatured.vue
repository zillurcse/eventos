<script setup lang="ts">
import type { ReceptionSession } from '~/stores/reception'

const props = defineProps<{ sessions: ReceptionSession[] }>()

const bookmarks = useBookmarksStore()

const heading = computed(() => {
  const first = props.sessions[0]
  if (!first?.starts_at) return ''
  return new Date(first.starts_at).toLocaleDateString('en-US', {
    weekday: 'long', month: 'long', day: 'numeric', year: 'numeric',
  })
})

function timeRange(s: ReceptionSession): string {
  if (!s.starts_at) return 'Time to be announced'
  const t = (d: string) => new Date(d).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
  return s.ends_at ? `${t(s.starts_at)} - ${t(s.ends_at)}` : t(s.starts_at)
}

function calendarLink(s: ReceptionSession): string | null {
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
}

function speakerInitials(name?: string | null): string {
  if (!name) return '?'
  const p = name.trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase()
}
</script>

<template>
  <section class="sessions-featured">
    <header class="head">
      <h2>Live Sessions ({{ sessions.length }})</h2>
      <p v-if="heading" class="date">{{ heading }}</p>
    </header>

    <div class="grid">
      <article v-for="s in sessions" :key="s.id" class="scard">
        <div class="top">
          <span class="badge">
            <svg viewBox="0 0 24 24"><path d="M12 2l1.8 5.8H20l-5 3.6 1.9 5.8L12 13.6 6.9 17.2l1.9-5.8-5-3.6h6.2z" /></svg>
          </span>
          <span class="time">{{ timeRange(s) }}</span>

          <div class="acts">
            <button class="act" :class="{ on: bookmarks.isOn('session', s.id) }" type="button" title="Bookmark" @click="bookmarks.toggle('session', s.id)">
              <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
            </button>
            <a v-if="calendarLink(s)" :href="calendarLink(s)!" target="_blank" rel="noopener" class="act" title="Add to Google Calendar">
              <svg viewBox="0 0 24 24"><path d="M7 4v3M17 4v3M4 9h16M5 7h14v13H5zM9 13h2M13 13h2M9 16h2M13 16h2" /></svg>
            </a>
          </div>
        </div>

        <div class="banner">
          <img v-if="s.logo_url || s.icon_url" :src="(s.logo_url || s.icon_url) as string" :alt="s.title" />
        </div>

        <h3 class="title">{{ s.title }}</h3>

        <div v-if="s.session_place" class="place">
          <svg viewBox="0 0 24 24"><path d="M12 22s7-7.4 7-12.5A7 7 0 0 0 5 9.5C5 14.6 12 22 12 22z" /><circle cx="12" cy="9.5" r="2.5" /></svg>
          <span>{{ s.session_place }}</span>
        </div>

        <div v-if="s.speakers?.length" class="speakers">
          <span class="label">Speakers ({{ s.speakers.length }})</span>
          <div class="row">
            <div class="avatars">
              <span v-for="sp in s.speakers.slice(0, 4)" :key="sp.id" class="av" :title="sp.name || ''">
                <img v-if="sp.profile?.image_url" :src="sp.profile.image_url" :alt="sp.name || ''" />
                <template v-else>{{ speakerInitials(sp.name) }}</template>
              </span>
            </div>
            <span v-if="s.speakers.length > 4" class="more">+{{ s.speakers.length - 4 }} More</span>
          </div>
        </div>

        <NuxtLink :to="`/session/${s.id}`" class="join">Join Now</NuxtLink>
        <span class="accent" />
      </article>
    </div>

    <div class="viewall">
      <span class="line" />
      <NuxtLink to="/sessions" class="viewall-btn">View all sessions</NuxtLink>
      <span class="line" />
    </div>
  </section>
</template>

<style scoped>
.sessions-featured { display: flex; flex-direction: column; gap: 20px; }

.head h2 { margin: 0; font-size: 1.3rem; font-weight: 800; color: #1e293b; }
.head .date { margin: 4px 0 0; color: #64748b; font-size: .88rem; }

.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; }

.scard { position: relative; background: #fff; border: 1px solid #eef0f3; border-radius: 16px; padding: 16px; display: flex; flex-direction: column; box-shadow: 0 1px 2px rgba(15,23,42,.05); }

.top { display: flex; align-items: center; gap: 8px; }
.badge { flex: 0 0 auto; width: 26px; height: 26px; border-radius: 50%; background: var(--brand-primary); color: #fff; display: inline-flex; align-items: center; justify-content: center; }
.badge svg { width: 14px; height: 14px; fill: currentColor; stroke: none; }
.time { flex: 1; min-width: 0; font-weight: 700; color: #1e293b; font-size: .92rem; }
.acts { display: flex; gap: 6px; flex: 0 0 auto; }
.act { width: 32px; height: 32px; border-radius: 50%; border: none; background: color-mix(in srgb, var(--brand-primary) 10%, #fff); color: var(--brand-primary); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
.act.on { background: var(--brand-primary); color: #fff; }
.act:hover { background: var(--brand-primary); color: #fff; }
.act svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.act.on svg { fill: currentColor; }

.banner { margin-top: 14px; border-radius: 12px; overflow: hidden; aspect-ratio: 16 / 10; background: #f1f5f9; }
.banner img { width: 100%; height: 100%; object-fit: cover; display: block; }

.title { margin: 14px 0 0; font-size: 1rem; font-weight: 800; color: #1e293b; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

.place { display: flex; align-items: flex-start; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid #f1f2f6; color: #64748b; font-size: .84rem; }
.place svg { width: 17px; height: 17px; flex: 0 0 auto; fill: none; stroke: #94a3b8; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }

.speakers { margin-top: 12px; padding-top: 12px; border-top: 1px solid #f1f2f6; }
.speakers .label { display: block; color: #64748b; font-size: .82rem; font-weight: 600; margin-bottom: 8px; }
.speakers .row { display: flex; align-items: center; gap: 12px; }
.avatars { display: flex; }
.av { width: 32px; height: 32px; border-radius: 50%; margin-left: -8px; border: 2px solid #fff; background: var(--brand-primary); color: #fff; font-size: .68rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; }
.av:first-child { margin-left: 0; }
.av img { width: 100%; height: 100%; object-fit: cover; }
.more { color: var(--brand-primary); font-weight: 700; font-size: .84rem; }

.join { align-self: flex-start; margin-top: 14px; padding-top: 14px; border-top: 1px solid #f1f2f6; width: 100%; box-sizing: border-box; }
.join { display: inline-flex; align-items: center; justify-content: center; margin-top: 14px; padding: 11px 26px; border-radius: 10px; background: var(--brand-primary); color: #fff; font-weight: 700; font-size: .88rem; text-decoration: none; align-self: flex-start; }
.join:hover { opacity: .92; }

.accent { position: absolute; left: 16px; bottom: -4px; width: 30%; height: 4px; border-radius: 999px; background: var(--brand-primary); }

.viewall { display: flex; align-items: center; gap: 24px; margin-top: 8px; }
.viewall .line { flex: 1; height: 1px; background: #D1D2DE; }
.viewall-btn { flex: 0 0 auto; padding: 8px 16px; border-radius: 8px; background: color-mix(in srgb, var(--brand-primary) 10%, #fff); color: var(--brand-primary); font-weight: 700; font-size: .88rem; text-decoration: none; }
.viewall-btn:hover { background: color-mix(in srgb, var(--brand-primary) 18%, #fff); }
</style>
