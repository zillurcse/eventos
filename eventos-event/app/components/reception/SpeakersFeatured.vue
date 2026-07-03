<script setup lang="ts">
import type { ReceptionSpeaker } from '~/stores/reception'

const props = defineProps<{ speakers: ReceptionSpeaker[] }>()

function initials(name?: string | null): string {
  if (!name) return '?'
  const p = name.trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase()
}
</script>

<template>
  <ReceptionSectionCard title="Speakers" featured view-all>
    <ReceptionCardCarousel>
      <article v-for="sp in props.speakers" :key="sp.id" class="spk">
        <span class="avatar">
          <img v-if="sp.image_url" :src="sp.image_url" :alt="sp.name || ''" />
          <template v-else>{{ initials(sp.name) }}</template>
        </span>
        <h3 class="name">{{ sp.name }}</h3>
        <p v-if="sp.designation" class="role">{{ sp.designation }}</p>
        <p v-if="sp.company" class="company">{{ sp.company }}</p>
      </article>
    </ReceptionCardCarousel>
  </ReceptionSectionCard>
</template>

<style scoped>
.spk { flex: 0 0 calc(33.333% - 10px); min-width: 170px; border: 1px solid #eef0f3; border-radius: 12px; padding: 20px 14px; text-align: center; background: #fff; }
.avatar { width: 78px; height: 78px; border-radius: 50%; margin: 0 auto 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: color-mix(in srgb, var(--brand-primary) 12%, #fff); color: var(--brand-primary); font-size: 1.3rem; font-weight: 800; }
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.name { margin: 0 0 4px; font-size: .88rem; font-weight: 700; color: var(--brand-primary); }
.role { margin: 0; font-size: .74rem; color: #475569; line-height: 1.3; }
.company { margin: 2px 0 0; font-size: .74rem; color: #94a3b8; }
</style>
