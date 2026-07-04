<script setup lang="ts">
import type { FeedFilter } from '~/stores/feed'

const feed = useFeedStore()

const term = ref(feed.search)
let timer: ReturnType<typeof setTimeout> | null = null

// Debounce search input → refetch after the user pauses typing.
watch(term, (v) => {
  if (timer) clearTimeout(timer)
  timer = setTimeout(() => feed.setSearch(v), 350)
})

onBeforeUnmount(() => { if (timer) clearTimeout(timer) })

const filters: Array<{ key: FeedFilter, label: string, icon: string }> = [
  { key: 'all', label: 'All', icon: 'M4 8h16M4 14h16' },
  { key: 'image', label: 'Images', icon: 'M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5M9 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z' },
  { key: 'video', label: 'Video', icon: 'M3 6h13v12H3zM16 10l5-3v10l-5-3z' },
  { key: 'pdf', label: 'Pdf', icon: 'M7 3h8l4 4v14H7zM15 3v4h4M9 13h6M9 17h6' },
  { key: 'poll', label: 'Polls', icon: 'M5 21V10M12 21V4M19 21v-7' },
  { key: 'offering', label: 'Offers', icon: 'M20 12v9H4v-9M2 7h20v5H2zM12 22V7' },
  { key: 'looking_for', label: 'Looking For', icon: 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM21 21l-5-5' },
  { key: 'mine', label: 'My Posts', icon: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0' },
]
</script>

<template>
  <aside class="rail">
    <div class="search">
      <input v-model="term" type="text" placeholder="Search">
      <svg viewBox="0 0 24 24"><path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3" /></svg>
    </div>

    <div class="card">
      <p class="title">Filter By</p>
      <nav class="list">
        <button
          v-for="f in filters"
          :key="f.key"
          type="button"
          class="item"
          :class="{ on: feed.filter === f.key }"
          @click="feed.setFilter(f.key)"
        >
          <span class="ic"><svg viewBox="0 0 24 24"><path :d="f.icon" /></svg></span>
          {{ f.label }}
        </button>
      </nav>
    </div>
  </aside>
</template>

<style scoped>
.rail { display: flex; flex-direction: column; gap: 16px; }
.search { position: relative; }
.search input { width: 100%; border: none; background: #fff; border-radius: 12px; padding: 14px 46px 14px 18px; font: inherit; font-size: .95rem; color: #334155; outline: none; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.search input::placeholder { color: #94a3b8; }
.search input:focus { box-shadow: 0 0 0 2px color-mix(in srgb, var(--brand-primary) 40%, transparent); }
.search svg { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; fill: none; stroke: var(--brand-primary); stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }

.card { background: #fff; border-radius: 14px; padding: 18px 14px; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.title { margin: 0 6px 6px; padding-bottom: 12px; border-bottom: 1px solid #eef0f3; color: #334155; font-weight: 700; font-size: 1rem; }
.list { display: flex; flex-direction: column; gap: 2px; margin-top: 6px; }
.item { display: flex; align-items: center; gap: 14px; border: none; background: none; border-radius: 10px; padding: 11px 10px; cursor: pointer; font: inherit; font-size: .98rem; color: #475569; text-align: left; }
.item:hover { background: #f7f8fa; }
.ic { flex: 0 0 auto; width: 34px; height: 34px; border-radius: 9px; background: #f1f5f9; color: #64748b; display: inline-flex; align-items: center; justify-content: center; }
.ic svg { width: 19px; height: 19px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.item.on { color: var(--brand-primary); font-weight: 700; }
.item.on .ic { background: var(--brand-primary); color: #fff; }
</style>
