<script setup lang="ts">
import type { Delegate } from '~/stores/delegates'

const props = defineProps<{ delegate: Delegate }>()
const store = useDelegatesStore()

const bookmarked = ref(false)
const key = computed(() => `eventos:bookmark:delegate:${props.delegate.id}`)

onMounted(() => {
  if (import.meta.client) bookmarked.value = localStorage.getItem(key.value) === '1'
})

function toggleBookmark() {
  bookmarked.value = !bookmarked.value
  if (import.meta.client) {
    if (bookmarked.value) localStorage.setItem(key.value, '1')
    else localStorage.removeItem(key.value)
  }
}

function initials(name?: string | null) {
  const p = (name || '?').trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?'
}

const subtitle = computed(() => {
  const d = props.delegate
  return [d.job_title, d.company].filter(Boolean).join(' · ')
})

const connectState = computed(() => store.connected[props.delegate.id])
</script>

<template>
  <article class="card">
    <div class="avatar">
      <img v-if="delegate.avatar_url" :src="delegate.avatar_url" :alt="delegate.name || ''">
      <span v-else class="ini">{{ initials(delegate.name) }}</span>

      <div class="acts">
        <button
          class="act connect"
          :class="{ on: connectState === 'pending' }"
          type="button"
          :title="connectState === 'pending' ? 'Request sent' : 'Connect'"
          :disabled="connectState === 'pending'"
          @click="store.connect(delegate)"
        >
          <svg v-if="connectState === 'pending'" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
          <svg v-else viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM19 8v6M22 11h-6" /></svg>
        </button>
        <button class="act bm" :class="{ on: bookmarked }" type="button" :title="bookmarked ? 'Saved' : 'Save'" @click="toggleBookmark">
          <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
        </button>
      </div>
    </div>

    <div class="body">
      <h3 class="name">{{ delegate.name }}</h3>
      <p v-if="subtitle" class="role">{{ subtitle }}</p>
    </div>
  </article>
</template>

<style scoped>
.card { background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.avatar { position: relative; aspect-ratio: 1 / 1; background: color-mix(in srgb, var(--brand-primary) 10%, #fff); display: flex; align-items: center; justify-content: center; }
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.ini { font-size: 3.4rem; font-weight: 700; color: color-mix(in srgb, var(--brand-primary) 75%, #fff); letter-spacing: 1px; }

.acts { position: absolute; right: 10px; bottom: 10px; display: flex; gap: 8px; }
.act { width: 34px; height: 34px; border-radius: 50%; border: none; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 1px 3px rgba(15,23,42,.18); }
.act svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
.act.connect { background: var(--brand-primary); color: #fff; }
.act.connect.on { background: #16a34a; }
.act.connect:disabled { cursor: default; }
.act.bm { background: #fff; color: var(--brand-primary); }
.act.bm.on { background: var(--brand-primary); color: #fff; }
.act.bm.on svg { fill: currentColor; }

.body { padding: 12px 14px 16px; text-align: center; }
.name { margin: 0; font-size: .96rem; font-weight: 700; color: var(--brand-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.role { margin: 4px 0 0; color: #64748b; font-size: .82rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style>
