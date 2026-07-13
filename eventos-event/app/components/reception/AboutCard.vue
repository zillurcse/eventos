<script setup lang="ts">
import type { ReceptionPayload } from '~/stores/reception'

const props = defineProps<{ about: ReceptionPayload['about'] }>()

const expanded = ref(false)

const image = computed(() => props.about.logo_url || props.about.cover_url)

const locationText = computed(() => {
  const loc = props.about.location
  if (!loc) return ''
  if (typeof loc === 'string') return loc
  return loc.address || loc.url || ''
})

const dateRange = computed(() => {
  const s = props.about.starts_at ? new Date(props.about.starts_at) : null
  const e = props.about.ends_at ? new Date(props.about.ends_at) : null
  if (!s) return ''
  const opt: Intl.DateTimeFormatOptions = { day: '2-digit', month: 'short', year: 'numeric' }
  const sStr = s.toLocaleDateString('en-GB', opt)
  const eStr = e ? e.toLocaleDateString('en-GB', opt) : null
  return eStr && eStr !== sStr ? `${sStr.replace(/,.*/, '')} - ${eStr}` : sStr
})

const timeRange = computed(() => {
  const s = props.about.starts_at ? new Date(props.about.starts_at) : null
  const e = props.about.ends_at ? new Date(props.about.ends_at) : null
  const t = (d: Date) => d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
  if (!s) return ''
  return e ? `${t(s)} - ${t(e)}` : t(s)
})

const socials = computed(() => {
  const s = props.about.social || {}
  return ([
    ['twitter', s.twitter],
    ['instagram', s.instagram],
    ['linkedin', s.linkedin],
    ['facebook', s.facebook],
  ] as const).filter(([, url]) => !!url)
})

const ICON: Record<string, string> = {
  twitter: 'M22 5.9c-.7.3-1.5.5-2.3.6.8-.5 1.5-1.3 1.8-2.3-.8.5-1.7.8-2.6 1a4 4 0 0 0-6.9 3.7A11.4 11.4 0 0 1 3.8 4.6a4 4 0 0 0 1.2 5.4c-.6 0-1.2-.2-1.8-.5a4 4 0 0 0 3.2 4 4 4 0 0 1-1.8 0 4 4 0 0 0 3.7 2.8A8 8 0 0 1 2 18a11.3 11.3 0 0 0 17.4-10.1c.8-.6 1.5-1.3 2-2.1z',
  instagram: 'M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4zM12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7zM17.5 6.5h.01',
  linkedin: 'M4.98 3.5a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM3.5 9h3v11h-3zM9 9h2.9v1.5h.04a3.2 3.2 0 0 1 2.9-1.6c3 0 3.6 2 3.6 4.6V20h-3v-4.6c0-1.1 0-2.5-1.6-2.5s-1.8 1.2-1.8 2.4V20H9z',
  facebook: 'M14 9V7c0-.9.6-1 1-1h2V3h-3c-2.4 0-4 1.6-4 4v2H8v3h2v9h3v-9h2.5l.5-3z',
}
</script>

<template>
  <div class="about-card">
    <div class="head">
      <div v-if="image" class="mark">
        <img :src="image" :alt="about.name" />
      </div>
      <div v-else class="mark placeholder">{{ (about.name || 'E').slice(0, 3).toUpperCase() }}</div>

      <div class="head-text">
        <h3 class="ttl">{{ about.name }}</h3>

        <div class="meta">
          <div v-if="dateRange" class="meta-item">
            <svg viewBox="0 0 24 24">
              <rect x="3" y="5" width="18" height="16" rx="2" />
              <path d="M8 3v4M16 3v4M3 10h18" />
            </svg>
            <span>{{ dateRange }}<template v-if="timeRange"> | {{ timeRange }}</template></span>
          </div>
          <div v-if="locationText" class="meta-item">
            <svg viewBox="0 0 24 24">
              <path d="M12 22s7-7.4 7-12.5A7 7 0 0 0 5 9.5C5 14.6 12 22 12 22z" />
              <circle cx="12" cy="9.5" r="2.5" />
            </svg>
            <span>{{ locationText }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="divider" />

    <div v-if="socials.length" class="socials">
      <a v-for="[key, url] in socials" :key="key" :href="url as string" target="_blank" rel="noopener"
        :aria-label="key">
        <svg viewBox="0 0 24 24">
          <path :d="ICON[key]" />
        </svg>
      </a>
    </div>

    <p v-if="about.description" class="desc" :class="{ clamp: !expanded }">{{ about.description }}</p>
    <a v-if="about.description && about.description.length > 160" href="#" class="more"
      @click.prevent="expanded = !expanded">
      {{ expanded ? 'Less Details' : 'More Details' }}
      <svg viewBox="0 0 24 24" :class="{ up: expanded }">
        <path d="M6 9l6 6 6-6" />
      </svg>
    </a>
  </div>
</template>

<style scoped>
.about-card {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  border: 1px solid #E8E8EE
}

.head {
  display: flex;
  align-items: center;
  gap: 14px;
}

.mark {
  flex: 0 0 64px;
}

.mark img {
  width: 96px;
  height: 96px;
  object-fit: cover;
  border-radius: 14px;
}

.mark.placeholder {
  width: 64px;
  height: 64px;
  border-radius: 14px;
  background: var(--brand-primary);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: .8rem;
}

.head-text {
  flex: 1;
  min-width: 0;
}

.ttl {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 800;
  color: #1e293b;
  line-height: 1.3;
}

.meta {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 10px;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 7px;
  color: #475569;
  font-size: .84rem;
}

.meta-item svg {
  width: 17px;
  height: 17px;
  flex: 0 0 auto;
  fill: none;
  stroke: #94a3b8;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.divider {
  margin-top: 16px;
  border-top: 1px solid #f1f5f9;
}

.socials {
  display: flex;
  gap: 8px;
  margin-top: 16px;
}

.socials a {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  background: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #475569;
}

.socials a:hover {
  background: var(--brand-primary);
  color: #fff;
}

.socials svg {
  width: 16px;
  height: 16px;
  fill: currentColor;
  stroke: none;
}

.desc {
  margin: 16px 0 0;
  color: #64748b;
  font-size: .86rem;
  line-height: 1.6;
}

.desc.clamp {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.more {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-top: 8px;
  font-size: .82rem;
  font-weight: 700;
  color: var(--brand-primary);
}

.more svg {
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.2;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: transform .15s ease;
}

.more svg.up {
  transform: rotate(180deg);
}

@media (max-width: 640px) {
  .head {
    align-items: flex-start;
  }
}
</style>
