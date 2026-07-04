<script setup lang="ts">
import type { Exhibitor } from '~/stores/exhibitors'

const props = defineProps<{ exhibitor: Exhibitor }>()
const store = useExhibitorsStore()

const bookmarked = ref(false)
const key = computed(() => `eventos:bookmark:exhibitor:${props.exhibitor.id}`)

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
</script>

<template>
  <article class="card" @click="store.open(exhibitor)">
    <button
      class="bm"
      :class="{ on: bookmarked }"
      type="button"
      :title="bookmarked ? 'Saved' : 'Save'"
      @click.stop="toggleBookmark"
    >
      <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
    </button>

    <div class="logo">
      <img v-if="exhibitor.logo_url" :src="exhibitor.logo_url" :alt="exhibitor.name">
      <span v-else class="ini">{{ initials(exhibitor.name) }}</span>
    </div>

    <div class="body">
      <div class="tags">
        <span class="tag" :class="exhibitor.type">{{ exhibitor.type === 'sponsor' ? 'Sponsor' : 'Exhibitor' }}</span>
        <span v-if="exhibitor.category" class="cat">{{ exhibitor.category }}</span>
      </div>
      <h3 class="name">{{ exhibitor.name }}</h3>
      <p v-if="exhibitor.booth" class="booth">
        <svg viewBox="0 0 24 24"><path d="M4 9l1-4h14l1 4M4 9v11h16V9M4 9h16M9 20v-6h6v6" /></svg>
        Booth {{ exhibitor.booth }}
      </p>
    </div>
  </article>
</template>

<style scoped>
.card { position: relative; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 2px rgba(15,23,42,.05); cursor: pointer; transition: box-shadow .15s, transform .15s; }
.card:hover { box-shadow: 0 8px 24px rgba(15,23,42,.1); transform: translateY(-2px); }

.bm { position: absolute; top: 10px; right: 10px; z-index: 2; width: 30px; height: 30px; border-radius: 50%; border: none; background: rgba(255,255,255,.9); color: var(--brand-primary); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 1px 3px rgba(15,23,42,.15); }
.bm svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
.bm.on { background: var(--brand-primary); color: #fff; }
.bm.on svg { fill: currentColor; }

.logo { height: 120px; background: #f8fafc; display: flex; align-items: center; justify-content: center; padding: 18px; border-bottom: 1px solid #eef0f3; }
.logo img { max-width: 100%; max-height: 100%; object-fit: contain; }
.ini { font-size: 2.4rem; font-weight: 700; color: color-mix(in srgb, var(--brand-primary) 60%, #cbd5e1); }

.body { padding: 12px 14px 16px; }
.tags { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 8px; }
.tag { font-size: .64rem; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; padding: 3px 8px; border-radius: 999px; }
.tag.exhibitor { background: color-mix(in srgb, var(--brand-primary) 12%, #fff); color: var(--brand-primary); }
.tag.sponsor { background: #fef3c7; color: #b45309; }
.cat { font-size: .72rem; color: #64748b; }
.name { margin: 0; font-size: 1rem; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.booth { display: flex; align-items: center; gap: 5px; margin: 6px 0 0; font-size: .8rem; color: #64748b; }
.booth svg { width: 15px; height: 15px; fill: none; stroke: var(--brand-primary); stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
</style>
