<script setup lang="ts">
import type { ReceptionPayload } from '~/stores/reception'

const props = defineProps<{ about: ReceptionPayload['about'] }>()

const expanded = ref(false)

const image = computed(() => props.about.cover_url || props.about.logo_url)

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
  <ReceptionSectionCard title="About">
    <div class="about">
      <div v-if="image" class="cover">
        <img :src="image" :alt="about.name" />
      </div>

      <div class="body">
        <h3 class="ttl">{{ about.name }}</h3>
        <p v-if="about.description" class="desc" :class="{ clamp: !expanded }">{{ about.description }}</p>
        <a v-if="about.description && about.description.length > 160" href="#" class="more" @click.prevent="expanded = !expanded">
          {{ expanded ? '- READ LESS' : '+ READ MORE' }}
        </a>

        <div v-if="dateRange" class="when">
          <strong>{{ dateRange }}</strong>
          <span v-if="timeRange" class="time">{{ timeRange }}</span>
        </div>

        <div v-if="socials.length" class="socials">
          <a v-for="[key, url] in socials" :key="key" :href="url as string" target="_blank" rel="noopener" :aria-label="key">
            <svg viewBox="0 0 24 24"><path :d="ICON[key]" /></svg>
          </a>
        </div>
      </div>
    </div>
  </ReceptionSectionCard>
</template>

<style scoped>
.about { display: flex; gap: 18px; }
.cover { flex: 0 0 190px; }
.cover img { width: 190px; height: 190px; object-fit: cover; border-radius: 12px; }
.body { flex: 1; min-width: 0; }
.ttl { margin: 0 0 8px; font-size: 1rem; font-weight: 800; color: #1e293b; }
.desc { margin: 0; color: #64748b; font-size: .86rem; line-height: 1.55; }
.desc.clamp { display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; }
.more { display: inline-block; margin-top: 8px; font-size: .78rem; font-weight: 700; color: var(--brand-primary); }
.when { margin-top: 16px; }
.when strong { display: block; color: #1e293b; font-size: .95rem; }
.time { color: #94a3b8; font-size: .82rem; }
.socials { display: flex; gap: 8px; margin-top: 14px; }
.socials a { width: 30px; height: 30px; border-radius: 50%; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; color: var(--brand-primary); }
.socials a:hover { background: var(--brand-primary); color: #fff; border-color: var(--brand-primary); }
.socials svg { width: 15px; height: 15px; fill: currentColor; stroke: none; }

@media (max-width: 640px) {
  .about { flex-direction: column; }
  .cover { flex: none; }
  .cover img { width: 100%; height: 180px; }
}
</style>
