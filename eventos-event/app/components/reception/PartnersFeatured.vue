<script setup lang="ts">
import type { ReceptionPartner } from '~/stores/reception'

const props = defineProps<{ title: string, partners: ReceptionPartner[] }>()

function initials(name: string): string {
  const p = name.trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase()
}
</script>

<template>
  <ReceptionSectionCard :title="props.title" featured view-all>
    <ReceptionCardCarousel>
      <article v-for="p in props.partners" :key="p.id" class="pcard">
        <div class="logo">
          <img v-if="p.logo_url" :src="p.logo_url" :alt="p.name" />
          <span v-else class="ph">{{ initials(p.name) }}</span>
        </div>
        <h3 class="name">{{ p.name }}</h3>
        <span class="type">{{ p.type }}</span>
        <span v-if="p.booth" class="booth">{{ p.booth }}</span>
      </article>
    </ReceptionCardCarousel>
  </ReceptionSectionCard>
</template>

<style scoped>
.pcard { flex: 0 0 calc(33.333% - 10px); min-width: 170px; border: 1px solid #eef0f3; border-radius: 12px; padding: 14px; text-align: center; background: #fff; }
.logo { height: 96px; border-radius: 10px; background: #f6f7f9; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 12px; }
.logo img { max-width: 88%; max-height: 80%; object-fit: contain; }
.ph { font-size: 1.6rem; font-weight: 800; color: var(--brand-primary); }
.name { margin: 0 0 3px; font-size: .86rem; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.type { display: block; font-size: .74rem; color: var(--brand-primary); text-transform: capitalize; }
.booth { display: block; font-size: .74rem; color: #94a3b8; margin-top: 2px; }
</style>
