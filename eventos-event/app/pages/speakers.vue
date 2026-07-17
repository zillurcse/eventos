<script setup lang="ts">
import type { Speaker } from '~/stores/speakers'

definePageMeta({ layout: 'event', middleware: 'auth' })

const store = useSpeakersStore()
const sessionsStore = useSessionsStore()
const bookmarks = useBookmarksStore()

const search = ref('')
const sort = ref<'default' | 'az' | 'za'>('default')
const category = ref<string>('')
const savedOnly = ref(false)

onMounted(() => {
  if (!store.loaded) store.fetchSpeakers()
  // Prefetch the agenda so the profile modal's schedule renders instantly.
  sessionsStore.fetchSessions()
  bookmarks.fetch()
})

const sortOptions: Array<{ key: 'az' | 'za' | 'default', label: string }> = [
  { key: 'az', label: 'By A to Z' },
  { key: 'za', label: 'By Z to A' },
  { key: 'default', label: 'Default' },
]

const filtered = computed<Speaker[]>(() => {
  const q = search.value.trim().toLowerCase()
  let list = store.speakers.filter((s) => {
    if (savedOnly.value && !bookmarks.isOn('speaker', s.id)) return false
    if (category.value && s.category !== category.value) return false
    if (!q) return true
    return `${s.name} ${s.designation} ${s.company} ${s.category}`.toLowerCase().includes(q)
  })

  if (sort.value !== 'default') {
    list = [...list].sort((a, b) => (a.name || '').localeCompare(b.name || ''))
    if (sort.value === 'za') list.reverse()
  }
  return list
})
</script>

<template>
  <div class="grid">
    <!-- Left rail: search + sort -->
    <aside class="rail">
      <div class="search">
        <input v-model="search" type="text" placeholder="Search">
        <svg viewBox="0 0 24 24"><path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3" /></svg>
      </div>

      <div class="card">
        <div class="ct">
          <span>Sort By</span>
          <svg viewBox="0 0 24 24"><path d="M4 6h16M7 12h10M10 18h4" /></svg>
        </div>
        <div class="opts">
          <button
            v-for="o in sortOptions"
            :key="o.key"
            type="button"
            class="opt"
            :class="{ on: sort === o.key }"
            @click="sort = o.key"
          >
            {{ o.label }}
            <svg v-if="sort === o.key" class="chk" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
          </button>
        </div>
      </div>

      <div class="card">
        <div class="ct">
          <span>Bookmarks</span>
          <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
        </div>
        <div class="opts">
          <button type="button" class="opt" :class="{ on: savedOnly }" @click="savedOnly = !savedOnly">
            Saved only{{ bookmarks.count('speaker') ? ` (${bookmarks.count('speaker')})` : '' }}
            <svg v-if="savedOnly" class="chk" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
          </button>
        </div>
      </div>

      <div v-if="store.categories.length" class="card">
        <div class="ct"><span>Category</span></div>
        <div class="opts">
          <button type="button" class="opt" :class="{ on: category === '' }" @click="category = ''">All categories</button>
          <button
            v-for="c in store.categories"
            :key="c.id"
            type="button"
            class="opt"
            :class="{ on: category === c.name }"
            @click="category = c.name"
          >{{ c.name }}</button>
        </div>
      </div>
    </aside>

    <!-- Speaker grid -->
    <section class="main">
      <div class="head">
        <h1>Speakers</h1>
        <p class="sub">Speakers at this event.</p>
      </div>

      <div v-if="store.loading && !store.loaded" class="state">Loading speakers…</div>
      <div v-else-if="store.error" class="state">Couldn’t load speakers. Please try again.</div>
      <div v-else-if="!filtered.length" class="state">No speakers match your search.</div>

      <div v-else class="cards">
        <SpeakersCard v-for="s in filtered" :key="s.id" :speaker="s" />
      </div>
    </section>

    <SpeakersDetailModal v-if="store.selected" :key="store.selected.id" :speaker="store.selected" />
  </div>
</template>

<style scoped>
.grid { display: grid; grid-template-columns: 280px 1fr; gap: 20px; align-items: start; }
@media (max-width: 860px) { .grid { grid-template-columns: 1fr; } }

.rail { display: flex; flex-direction: column; gap: 16px; }
.search { position: relative; }
.search input { width: 100%; border: none; background: #fff; border-radius: 12px; padding: 14px 46px 14px 18px; font: inherit; font-size: .95rem; color: #334155; outline: none; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.search input::placeholder { color: #94a3b8; }
.search input:focus { box-shadow: 0 0 0 2px color-mix(in srgb, var(--brand-primary) 40%, transparent); }
.search svg { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; fill: none; stroke: var(--brand-primary); stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }

.card { background: #fff; border-radius: 14px; padding: 14px; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.ct { display: flex; align-items: center; justify-content: space-between; padding: 4px 6px 12px; border-bottom: 1px solid #eef0f3; color: #334155; font-weight: 600; }
.ct svg { width: 18px; height: 18px; fill: none; stroke: var(--brand-primary); stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.opts { display: flex; flex-direction: column; gap: 6px; margin-top: 10px; }
.opt { display: flex; align-items: center; justify-content: space-between; border: 1px solid transparent; background: none; border-radius: 10px; padding: 11px 12px; cursor: pointer; font: inherit; font-size: .9rem; color: #475569; text-align: left; }
.opt:hover { background: #f7f8fa; }
.opt.on { background: color-mix(in srgb, var(--brand-primary) 10%, #fff); border-color: color-mix(in srgb, var(--brand-primary) 35%, #fff); color: var(--brand-primary); font-weight: 600; }
.chk { width: 17px; height: 17px; fill: none; stroke: var(--brand-primary); stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }

.main { min-width: 0; }
.head { margin-bottom: 16px; }
.head h1 { margin: 0; font-size: 1.4rem; font-weight: 800; color: #1e293b; }
.sub { margin: 4px 0 0; color: #64748b; font-size: .9rem; }
.state { background: #fff; border-radius: 14px; padding: 48px 0; text-align: center; color: #64748b; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 16px; }
</style>
