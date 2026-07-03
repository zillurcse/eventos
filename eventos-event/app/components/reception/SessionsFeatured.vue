<script setup lang="ts">
import type { ReceptionSession } from '~/stores/reception'

const props = defineProps<{ sessions: ReceptionSession[] }>()

function pill(s: ReceptionSession): string {
  if (!s.starts_at) return 'Time to be announced'
  const start = new Date(s.starts_at)
  const day = start.toLocaleDateString('en-US', { weekday: 'long' })
  const t = (d: string) => new Date(d).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
  const range = s.ends_at ? `${t(s.starts_at)} - ${t(s.ends_at)}` : t(s.starts_at)
  const tz = s.timezone ? tzAbbr(s.timezone) : ''
  return `${day} | ${range}${tz ? ` (${tz})` : ''}`
}

function tzAbbr(tz: string): string {
  try {
    const p = new Intl.DateTimeFormat('en-US', { timeZone: tz, timeZoneName: 'short' })
      .formatToParts(new Date()).find(x => x.type === 'timeZoneName')
    return p?.value ?? ''
  } catch { return '' }
}

function speakerInitials(name?: string | null): string {
  if (!name) return '?'
  const p = name.trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase()
}
</script>

<template>
  <ReceptionSectionCard title="Sessions" featured view-all>
    <ReceptionCardCarousel>
      <article v-for="s in props.sessions" :key="s.id" class="scard">
        <span class="pill">{{ pill(s) }}</span>
        <h3 class="title">{{ s.title }}</h3>
        <div class="foot">
          <div v-if="s.speakers?.length" class="avatars">
            <span
              v-for="(sp, i) in s.speakers.slice(0, 4)"
              :key="sp.id"
              class="av"
              :style="{ zIndex: 10 - i }"
            >
              <img v-if="sp.profile?.image_url" :src="sp.profile.image_url" :alt="sp.name || ''" />
              <template v-else>{{ speakerInitials(sp.name) }}</template>
            </span>
          </div>
          <div class="acts">
            <span class="act"><svg viewBox="0 0 24 24"><path d="M5 4h14v16l-7-3-7 3z" /></svg></span>
          </div>
        </div>
      </article>
    </ReceptionCardCarousel>
  </ReceptionSectionCard>
</template>

<style scoped>
.scard { flex: 0 0 calc(50% - 7px); min-width: 260px; border: 1px solid #eef0f3; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; min-height: 150px; background: #fff; }
.pill { align-self: flex-start; background: var(--brand-primary); color: #fff; font-size: .72rem; font-weight: 700; padding: 6px 14px; border-radius: 999px; }
.title { margin: 14px 0 auto; font-size: .92rem; font-weight: 700; color: #1e293b; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.foot { display: flex; align-items: center; justify-content: space-between; margin-top: 14px; padding-top: 12px; border-top: 1px solid #f1f2f6; }
.avatars { display: flex; }
.av { width: 30px; height: 30px; border-radius: 50%; margin-left: -8px; border: 2px solid #fff; background: var(--brand-primary); color: #fff; font-size: .66rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; }
.av:first-child { margin-left: 0; }
.av img { width: 100%; height: 100%; object-fit: cover; }
.acts { display: flex; gap: 6px; }
.act { width: 32px; height: 32px; border-radius: 50%; background: #f1f2f6; color: #64748b; display: inline-flex; align-items: center; justify-content: center; }
.act svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
</style>
