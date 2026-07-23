<script setup lang="ts">
import type { Delegate } from '~/stores/delegates'

definePageMeta({ layout: 'event', middleware: 'auth' })

const store = useDelegatesStore()
const bookmarks = useBookmarksStore()

const search = ref('')
const sort = ref<'az' | 'za'>('az')
const savedOnly = ref(false)

// Search/sort/pagination are server-side (the list can be huge); the input
// is debounced so we don't fire a request per keystroke.
let debounce: ReturnType<typeof setTimeout> | undefined
watch(search, (q: string) => {
  clearTimeout(debounce)
  debounce = setTimeout(() => store.setQuery(q), 350)
})
watch(sort, (s: 'az' | 'za') => store.setSort(s))

// Infinite scroll: load the next page when the bottom sentinel comes into view.
const sentinel = ref<HTMLElement | null>(null)
let observer: IntersectionObserver | undefined

onMounted(() => {
  if (!store.loaded) store.fetchDelegates()
  if (!store.similarLoaded) store.fetchSimilar()
  if (!store.adsLoaded) store.fetchAds()
  bookmarks.fetch()
  observer = new IntersectionObserver(
    entries => { if (entries.some(e => e.isIntersecting) && !savedOnly.value) store.loadMore() },
    { rootMargin: '400px' },
  )
  if (sentinel.value) observer.observe(sentinel.value)
})
onBeforeUnmount(() => {
  clearTimeout(debounce)
  observer?.disconnect()
})

const sortOptions: Array<{ key: 'az' | 'za', label: string }> = [
  { key: 'az', label: 'Default' },
  { key: 'za', label: 'Z to A' },
]

// ── Advance Filter — the directory has no server-side facet search, so this
// is a client-side quick-filter over the page(s) already loaded, built from
// the company/job-title values actually present in the current list.
const activeCompanies = ref<string[]>([])
const activeTitles = ref<string[]>([])

function uniqueTop(values: Array<string | null | undefined>, limit: number): string[] {
  const seen = new Map<string, number>()
  for (const v of values) {
    const t = (v || '').trim()
    if (!t) continue
    seen.set(t, (seen.get(t) || 0) + 1)
  }
  return [...seen.entries()].sort((a, b) => b[1] - a[1]).slice(0, limit).map(([k]) => k)
}
const companyOptions = computed(() => uniqueTop(store.delegates.map(d => d.company), 12))
const titleOptions = computed(() => uniqueTop(store.delegates.map(d => d.job_title), 12))

function toggleCompany(value: string) {
  const i = activeCompanies.value.indexOf(value)
  if (i === -1) activeCompanies.value.push(value)
  else activeCompanies.value.splice(i, 1)
}
function toggleTitle(value: string) {
  const i = activeTitles.value.indexOf(value)
  if (i === -1) activeTitles.value.push(value)
  else activeTitles.value.splice(i, 1)
}
const hasFilters = computed(() => !!(activeCompanies.value.length || activeTitles.value.length))
function clearFilters() {
  activeCompanies.value = []
  activeTitles.value = []
}

const filtered = computed<Delegate[]>(() => store.delegates.filter((d: Delegate) => {
  if (savedOnly.value && !bookmarks.isOn('delegate', d.id)) return false
  if (activeCompanies.value.length && !activeCompanies.value.includes(d.company)) return false
  if (activeTitles.value.length && !activeTitles.value.includes(d.job_title)) return false
  return true
}))
</script>

<template>
  <div class="grid">
    <!-- Main column -->
    <section class="main">
      <ReceptionAdStrip v-if="store.ads.length" :ads="store.ads" />

      <!-- People who share my designation / company — above the directory. -->
      <DelegatesSimilarStrip />

      <div class="toolbar">
        <div class="search">
          <input v-model="search" type="text" placeholder="Search">
          <svg viewBox="0 0 24 24">
            <path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3" />
          </svg>
        </div>

        <div class="sortby">
          <span>Sort By:</span>
          <select v-model="sort">
            <option v-for="o in sortOptions" :key="o.key" :value="o.key">{{ o.label }}</option>
          </select>
          <svg viewBox="0 0 24 24">
            <path d="M6 9l6 6 6-6" />
          </svg>
        </div>

        <button type="button" class="saved" :class="{ on: savedOnly }" @click="savedOnly = !savedOnly">
          <svg viewBox="0 0 24 24">
            <path d="M6 3h12v18l-6-4-6 4z" />
          </svg>
          Saved{{ bookmarks.count('delegate') ? ` (${bookmarks.count('delegate')})` : '' }}
        </button>
      </div>

      <div class="head">
        <h1>Delegates ({{ filtered.length }})</h1>
      </div>

      <div v-if="store.loading" class="state">Loading delegates…</div>
      <div v-else-if="store.error" class="state">Couldn’t load delegates. Please try again.</div>
      <div v-else-if="!filtered.length" class="state">No delegates match your search.</div>

      <template v-else>
        <div class="cards">
          <DelegatesCard v-for="d in filtered" :key="d.id" :delegate="d" />
        </div>
        <div v-if="store.loadingMore" class="more-state">Loading more…</div>
        <button v-else-if="store.hasMore && !savedOnly" class="more" type="button" @click="store.loadMore()">Load
          more</button>
      </template>

      <!-- Infinite-scroll sentinel (observer loads the next page near it) -->
      <div ref="sentinel" aria-hidden="true" />

      <ReceptionAdStrip v-if="store.ads.length" :ads="store.ads" />
    </section>

    <!-- Advance Filter -->
    <aside class="rail">
      <div class="card card-filter">
        <div class="fhead">
          <h2>Advance Filter</h2>
          <button v-if="hasFilters" type="button" class="clear" @click="clearFilters">Clear All</button>
        </div>

        <div v-if="hasFilters" class="chosen">
          <span v-for="c in activeCompanies" :key="`c-${c}`" class="chip on">
            {{ c }}
            <button type="button" @click="toggleCompany(c)"><svg viewBox="0 0 24 24">
                <path d="M6 6l12 12M18 6L6 18" />
              </svg></button>
          </span>
          <span v-for="t in activeTitles" :key="`t-${t}`" class="chip on">
            {{ t }}
            <button type="button" @click="toggleTitle(t)"><svg viewBox="0 0 24 24">
                <path d="M6 6l12 12M18 6L6 18" />
              </svg></button>
          </span>
        </div>

        <template v-if="companyOptions.length">
          <div class="fsec">Companies</div>
          <div class="chipgrid">
            <button v-for="c in companyOptions" :key="c" type="button" class="chip"
              :class="{ on: activeCompanies.includes(c) }" @click="toggleCompany(c)">{{ c }}</button>
          </div>
        </template>

        <hr v-if="companyOptions.length && titleOptions.length" class="rule">

        <template v-if="titleOptions.length">
          <div class="fsec">Job Titles</div>
          <div class="chipwrap">
            <button v-for="t in titleOptions" :key="t" type="button" class="chip"
              :class="{ on: activeTitles.includes(t) }" @click="toggleTitle(t)">{{ t }}</button>
          </div>
        </template>

        <p v-if="!companyOptions.length && !titleOptions.length" class="fempty">Filters appear once delegates load.</p>
      </div>
    </aside>

    <DelegatesConnectModal />
  </div>
</template>

<style scoped>
.grid {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 20px;
  align-items: start;
}

@media (max-width: 900px) {
  .grid {
    grid-template-columns: 1fr;
  }
}

.main {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.search {
  position: relative;
  flex: 1;
  min-width: 220px;
}

.search input {
  width: 100%;
  border: none;
  background: #fff;
  border-radius: 12px;
  padding: 14px 46px 14px 18px;
  font: inherit;
  font-size: .95rem;
  color: #334155;
  outline: none;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
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

.sortby {
  position: relative;
  display: flex;
  align-items: center;
  gap: 8px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px 40px 12px 16px;
  font-size: .88rem;
  color: #475569;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
}

.sortby select {
  border: none;
  background: none;
  font: inherit;
  font-size: .88rem;
  color: #1e293b;
  font-weight: 600;
  outline: none;
  appearance: none;
  padding-right: 4px;
}

.sortby svg {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  width: 15px;
  height: 15px;
  fill: none;
  stroke: #94a3b8;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  pointer-events: none;
}

.saved {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px 16px;
  background: #fff;
  color: #475569;
  font: inherit;
  font-size: .86rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
}

.saved svg {
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.saved.on {
  background: color-mix(in srgb, var(--brand-primary) 10%, #fff);
  border-color: color-mix(in srgb, var(--brand-primary) 35%, #fff);
  color: var(--brand-primary);
}

.saved.on svg {
  fill: currentColor;
}

.head h1 {
  margin: 0;
  font-size: 1.2rem;
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
   grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.more-state {
  padding: 18px 0;
  text-align: center;
  color: #94a3b8;
  font-size: .88rem;
}

.more {
  display: block;
  margin: 0 auto;
  border: 1px solid var(--brand-primary);
  background: #fff;
  color: var(--brand-primary);
  border-radius: 999px;
  padding: 10px 28px;
  font: inherit;
  font-size: .85rem;
  font-weight: 700;
  cursor: pointer;
}

.more:hover {
  background: var(--brand-primary);
  color: #fff;
}

/* ── Advance Filter ── */
.rail {
  position: sticky;
  top: 20px;
}

.card {
  background: #fff;
  border-radius: 12px;
  padding: 0px;
  /* box-shadow: 0 1px 2px rgba(15, 23, 42, .05); */
}
.card-filter{
  padding: 16px;

}

.fhead {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.fhead h2 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 800;
  color: #1e293b;
}

.clear {
  border: none;
  background: none;
  color: var(--brand-primary);
  font: inherit;
  font-size: .84rem;
  font-weight: 700;
  cursor: pointer;
  padding: 0;
}

.clear:hover {
  text-decoration: underline;
}

.chosen {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding-bottom: 16px;
  margin-bottom: 16px;
  border-bottom: 1px solid #eef0f3;
}

.fsec {
  font-size: .84rem;
  font-weight: 700;
  color: #1e293b;
  margin: 4px 0 12px;
}

.rule {
  border: none;
  border-top: 1px solid #eef0f3;
  margin: 18px 0;
}

.chipgrid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.chipwrap {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #475569;
  border-radius: 999px;
  padding: 4px 8px;
  font: inherit;
  font-size: .8rem;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}

.chipgrid .chip {
  justify-content: center;
}

.chip:hover {
  border-color: color-mix(in srgb, var(--brand-primary) 40%, #e2e8f0);
}

.chip.on {
  background: color-mix(in srgb, var(--brand-primary) 12%, #fff);
  border-color: color-mix(in srgb, var(--brand-primary) 35%, #fff);
  color: var(--brand-primary);
  font-weight: 700;
}

.chosen .chip {
  background: color-mix(in srgb, var(--brand-primary) 12%, #fff);
  border-color: color-mix(in srgb, var(--brand-primary) 35%, #fff);
  color: var(--brand-primary);
  font-weight: 700;
}

.chosen .chip button {
  display: inline-flex;
  border: none;
  background: none;
  color: inherit;
  cursor: pointer;
  padding: 0;
}

.chosen .chip button svg {
  width: 12px;
  height: 12px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.4;
  stroke-linecap: round;
}

.fempty {
  margin: 0;
  font-size: .84rem;
  color: #94a3b8;
}
</style>
