<script setup lang="ts">
import type { Speaker } from '~/stores/speakers'

definePageMeta({ layout: 'event', middleware: 'auth' })

const store = useSpeakersStore()
const sessionsStore = useSessionsStore()
const bookmarks = useBookmarksStore()

const search = ref('')
const sort = ref<'default' | 'az' | 'za'>('default')
const selectedCategories = ref<string[]>([])
const selectedSpeakerIds = ref<string[]>([])
const savedOnly = ref(false)

onMounted(() => {
  if (!store.loaded) store.fetchSpeakers()
  if (!store.adsLoaded) store.fetchAds()
  // Prefetch the agenda so the profile modal's schedule renders instantly.
  sessionsStore.fetchSessions()
  bookmarks.fetch()
})

const sortOptions: Array<{ key: 'default' | 'az' | 'za', label: string }> = [
  { key: 'default', label: 'Default' },
  { key: 'az', label: 'A to Z' },
  { key: 'za', label: 'Z to A' },
]
const currentSortLabel = computed(() => sortOptions.find((o) => o.key === sort.value)?.label ?? 'Default')

const sortOpen = ref(false)
const sortWrap = ref<HTMLElement | null>(null)
function onOutsideSort(e: MouseEvent) {
  if (sortWrap.value && !sortWrap.value.contains(e.target as Node)) sortOpen.value = false
}
watch(sortOpen, (open) => {
  if (open) document.addEventListener('click', onOutsideSort, true)
  else document.removeEventListener('click', onOutsideSort, true)
})
onBeforeUnmount(() => document.removeEventListener('click', onOutsideSort, true))
function selectSort(key: typeof sort.value) {
  sort.value = key
  sortOpen.value = false
}

function toggleCategory(name: string) {
  const i = selectedCategories.value.indexOf(name)
  if (i === -1) selectedCategories.value.push(name)
  else selectedCategories.value.splice(i, 1)
}
function toggleSpeaker(id: string) {
  const i = selectedSpeakerIds.value.indexOf(id)
  if (i === -1) selectedSpeakerIds.value.push(id)
  else selectedSpeakerIds.value.splice(i, 1)
}
function clearAll() {
  selectedCategories.value = []
  selectedSpeakerIds.value = []
  savedOnly.value = false
}

const activeChips = computed(() => {
  const chips: Array<{ key: string, label: string, clear: () => void }> = []
  if (savedOnly.value) chips.push({ key: 'saved', label: 'Saved only', clear: () => { savedOnly.value = false } })
  for (const name of selectedCategories.value) {
    chips.push({ key: `tag:${name}`, label: name, clear: () => toggleCategory(name) })
  }
  for (const id of selectedSpeakerIds.value) {
    const name = store.speakers.find((s) => s.id === id)?.name ?? id
    chips.push({ key: `sp:${id}`, label: name || id, clear: () => toggleSpeaker(id) })
  }
  return chips
})

const filtered = computed<Speaker[]>(() => {
  const q = search.value.trim().toLowerCase()
  let list = store.speakers.filter((s) => {
    if (savedOnly.value && !bookmarks.isOn('speaker', s.id)) return false
    if (selectedCategories.value.length && !selectedCategories.value.includes(s.category)) return false
    if (selectedSpeakerIds.value.length && !selectedSpeakerIds.value.includes(s.id)) return false
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
    <!-- Speaker grid -->
    <section class="main">
      <!-- <div class="head">
        <h1>Speakers</h1>
        <p class="sub">Speakers at this event.</p>
      </div> -->
      <ReceptionAdStrip v-if="store.ads.length" :ads="store.ads" />
      <div class="toolbar">
        <div class="search">
          <input v-model="search" type="text" placeholder="Search">
          <svg viewBox="0 0 24 24">
            <path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3" />
          </svg>
        </div>

        <div ref="sortWrap" class="sort">
          <button type="button" class="sort-btn" @click="sortOpen = !sortOpen">
            <span>Sort By: {{ currentSortLabel }}</span>
            <svg class="chev" :class="{ open: sortOpen }" viewBox="0 0 24 24">
              <path d="M6 9l6 6 6-6" />
            </svg>
          </button>
          <div v-if="sortOpen" class="sort-pop">
            <button v-for="o in sortOptions" :key="o.key" type="button" class="sort-opt" :class="{ on: sort === o.key }"
              @click="selectSort(o.key)">{{ o.label }}</button>
          </div>
        </div>
      </div>

      <h2 class="count">Speakers ({{ filtered.length }})</h2>

      <div v-if="store.loading && !store.loaded" class="state">Loading speakers…</div>
      <div v-else-if="store.error" class="state">Couldn’t load speakers. Please try again.</div>
      <div v-else-if="!filtered.length" class="state">No speakers match your search.</div>

      <div v-else class="cards">
        <SpeakersCard v-for="s in filtered" :key="s.id" :speaker="s" />
      </div>
    </section>

    <!-- Right rail: Advance Filter -->
    <aside class="rail">
      <div class="filter-card">
        <div class="fc-head">
          <h2>Advance Filter</h2>
          <div class="fc-actions">
            <button type="button" class="saved-icon" :class="{ on: savedOnly }"
              :title="savedOnly ? 'Showing saved only' : 'Show saved only'" @click="savedOnly = !savedOnly">
              <svg viewBox="0 0 24 24">
                <path d="M6 3h12v18l-6-4-6 4z" />
              </svg>
            </button>
            <button type="button" class="clear-all" @click="clearAll">Clear All</button>
          </div>
        </div>

        <div v-if="activeChips.length" class="chips">
          <button v-for="chip in activeChips" :key="chip.key" type="button" class="chip active" @click="chip.clear()">
            {{ chip.label }}
            <svg viewBox="0 0 24 24">
              <path d="M6 6l12 12M18 6L6 18" />
            </svg>
          </button>
        </div>

        <div v-if="store.speakers.length" class="fsection">
          <h3>Speakers</h3>
          <div class="chip-grid">
            <button v-for="s in store.speakers" :key="s.id" type="button" class="chip"
              :class="{ on: selectedSpeakerIds.includes(s.id) }" @click="toggleSpeaker(s.id)">{{ s.name }}</button>
          </div>
        </div>

        <div v-if="store.categories.length" class="fsection">
          <h3>Tags</h3>
          <div class="chip-grid">
            <button v-for="c in store.categories" :key="c.id" type="button" class="chip"
              :class="{ on: selectedCategories.includes(c.name) }" @click="toggleCategory(c.name)">{{ c.name }}</button>
          </div>
        </div>
      </div>
    </aside>

    <SpeakersDetailModal v-if="store.selected" :key="store.selected.id" :speaker="store.selected" />
  </div>
</template>

<style scoped>
.grid {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 20px;
  align-items: start;
}

@media (max-width: 960px) {
  .grid {
    grid-template-columns: 1fr;
  }
}

.main {
  min-width: 0;
}

.banner {
  width: 100%;
  margin-bottom: 16px;
}

.head {
  margin-bottom: 16px;
}

.head h1 {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 800;
  color: #1e293b;
}

.sub {
  margin: 4px 0 0;
  color: #64748b;
  font-size: .9rem;
}

.toolbar {
  display: flex;
  gap: 12px;
  align-items: stretch;
  margin-top: 30px;
}

.search {
  position: relative;
  flex: 1;
  min-width: 0;
}

.search input {
  width: 100%;
  height: 100%;
  max-height: 40px;
  box-sizing: border-box;
  border: none;
  background: #fff;
  border: 1px solid #D1D2DE;
  border-radius: 8px;
  padding: 12px;
  font: inherit;
  font-size: .95rem;
  color: #334155;
  outline: none;
  margin: 0;
}

.search input::placeholder {
  color: #94a3b8;
}

.search input:focus {
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--brand-primary) 40%, transparent);
}

.search svg {
  position: absolute;
  right: 16px;
  top: 50%;
  transform: translateY(-50%);
  width: 20px;
  height: 20px;
  fill: none;
  stroke: var(--brand-primary);
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.sort {
  position: relative;
  flex: none;
}

.sort-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  height: 100%;
  background: #fff;
  border: 1px solid #e2e5eb;
  border-radius: 8px;
  padding: 14px 16px;
  max-height: 40px;
  font: inherit;
  font-size: .9rem;
  color: #334155;
  cursor: pointer;
  white-space: nowrap;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
}

.sort-btn .chev {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: #64748b;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: transform .15s ease;
}

.sort-btn .chev.open {
  transform: rotate(180deg);
}

.sort-pop {
  position: absolute;
  z-index: 20;
  top: calc(100% + 6px);
  left: 0;
  right: 0;
  min-width: 160px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 12px 30px rgba(15, 23, 42, .15);
  border: 1px solid #eef0f3;
  overflow: hidden;
  padding: 6px;
}

.sort-opt {
  display: block;
  width: 100%;
  text-align: left;
  border: none;
  background: none;
  border-radius: 8px;
  padding: 10px 12px;
  font: inherit;
  font-size: .9rem;
  color: #475569;
  cursor: pointer;
}

.sort-opt:hover {
  background: #f7f8fa;
}

.sort-opt.on {
  color: var(--brand-primary);
  font-weight: 700;
  background: color-mix(in srgb, var(--brand-primary) 8%, #fff);
}

.count {
  margin: 20px 0 0px;
  font-size: 1.1rem;
  font-weight: 800;
  color: #1e293b;
}

.state {
  background: #fff;
  border-radius: 14px;
  padding: 48px 0;
  text-align: center;
  color: #64748b;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
}

.cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
  gap: 16px;
  align-items: start;
}

/* Right rail — Advance Filter */
.rail {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.filter-card {
  background: #fff;
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
}

.fc-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.fc-head h2 {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 800;
  color: #1e293b;
}

.fc-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.clear-all {
  border: none;
  background: none;
  color: var(--brand-primary);
  font: inherit;
  font-size: .85rem;
  font-weight: 700;
  cursor: pointer;
  padding: 0;
}

.clear-all:hover {
  text-decoration: underline;
}

.saved-icon {
  flex: none;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  border: 1px solid #dfe3ea;
  background: #fff;
  color: var(--brand-primary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.saved-icon svg {
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.saved-icon.on {
  background: var(--brand-primary);
  border-color: var(--brand-primary);
  color: #fff;
}

.saved-icon.on svg {
  fill: currentColor;
}

.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 14px;
}

.chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border-radius: 100px;
  max-height: 24px;
  padding: 7px 12px;
  font: inherit;
  font-size: 12px;
  cursor: pointer;
  white-space: nowrap;
}

.chip:not(.active) {
  background: #fff;
  border: 1px solid #9B9D9E;
  color: #64676A;
}

.chip:not(.active).on {
  background: var(--brand-primary);
  border-color: var(--brand-primary);
  color: #fff;
}

.chip.active {
  background: color-mix(in srgb, var(--brand-primary) 12%, #fff);
  border: 1px solid color-mix(in srgb, var(--brand-primary) 28%, #fff);
  color: var(--brand-primary);
  font-weight: 600;
}

.chip.active svg {
  width: 13px;
  height: 13px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.fsection {
  padding-top: 14px;
  border-top: 1px solid #eef0f3;
}

.fsection+.fsection {
  margin-top: 14px;
}

.fsection h3 {
  margin: 0 0 12px;
  font-size: 14px;
  font-weight: 600;
  color: #4D5154;
}

.chip-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
