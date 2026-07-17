<script setup lang="ts">
import type { Exhibitor } from '~/stores/exhibitors'

definePageMeta({ layout: 'event', middleware: 'auth' })

const store = useExhibitorsStore()
const bookmarks = useBookmarksStore()
const route = useRoute()

const search = ref('')
const sort = ref<'default' | 'az' | 'za'>('default')
const initialType = route.query.type === 'exhibitor' || route.query.type === 'sponsor' ? route.query.type : 'all'
const type = ref<'all' | 'exhibitor' | 'sponsor'>(initialType)
const category = ref<string>('')
const savedOnly = ref(false)

// Attendee-chosen options per configured filter: filterId → option strings.
const selections = reactive<Record<string, string[]>>({})
// Which filter accordions are expanded in the rail.
const openFilters = reactive<Record<string, boolean>>({})

function toggleFilterOpen(id: string) { openFilters[id] = !openFilters[id] }
function isOptOn(fid: string, opt: string) { return !!selections[fid]?.includes(opt) }
function toggleOpt(fid: string, opt: string) {
  const arr = (selections[fid] ||= [])
  const i = arr.indexOf(opt)
  if (i >= 0) arr.splice(i, 1)
  else arr.push(opt)
  if (!arr.length) delete selections[fid]
}
function filterOnCount(fid: string) { return selections[fid]?.length ?? 0 }

// Options an exhibitor carries for a given filter, flattened across headings.
function exhibitorOptions(e: Exhibitor, fid: string): string[] {
  const group = e.filter_selections?.[fid]
  return group ? Object.values(group).flat() : []
}

const anyFilterActive = computed(() => (Object.values(selections) as string[][]).some(a => a.length > 0))
function clearAll() {
  for (const k of Object.keys(selections)) delete selections[k]
  category.value = ''
  type.value = 'all'
  savedOnly.value = false
  search.value = ''
}

onMounted(() => {
  if (!store.loaded) store.fetchExhibitors()
  if (!store.adsLoaded) store.fetchAds()
  bookmarks.fetch()
})

const sortOptions: Array<{ key: 'az' | 'za' | 'default', label: string }> = [
  { key: 'az', label: 'By A to Z' },
  { key: 'za', label: 'By Z to A' },
  { key: 'default', label: 'Default' },
]

const typeOptions: Array<{ key: 'all' | 'exhibitor' | 'sponsor', label: string }> = [
  { key: 'all', label: 'All' },
  { key: 'exhibitor', label: 'Exhibitors' },
  { key: 'sponsor', label: 'Sponsors' },
]

const filtered = computed<Exhibitor[]>(() => {
  const q = search.value.trim().toLowerCase()
  let list = store.all.filter((e) => {
    if (savedOnly.value && !bookmarks.isOn('exhibitor', e.id)) return false
    if (type.value !== 'all' && e.type !== type.value) return false
    if (category.value && e.category !== category.value) return false
    // Configured filters: OR within a filter, AND across filters.
    for (const [fid, opts] of Object.entries(selections) as [string, string[]][]) {
      if (!opts.length) continue
      const has = exhibitorOptions(e, fid)
      if (!opts.some(o => has.includes(o))) return false
    }
    if (!q) return true
    return `${e.name} ${e.category} ${e.description}`.toLowerCase().includes(q)
  })

  if (sort.value === 'default') {
    // Curated order: featured first, then tier_rank desc.
    list = [...list].sort((a, b) => Number(b.is_featured) - Number(a.is_featured) || b.tier_rank - a.tier_rank)
  } else {
    list = [...list].sort((a, b) => (a.name || '').localeCompare(b.name || ''))
    if (sort.value === 'za') list.reverse()
  }
  return list
})
</script>

<template>
  <div class="grid">
    <!-- Exhibitor grid -->
    <section class="main">
      <ReceptionAdStrip v-if="store.ads.length" :ads="store.ads" class="banner" />

      <div class="toprow">
        <div class="search">
          <svg viewBox="0 0 24 24"><path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3" /></svg>
          <input v-model="search" type="text" placeholder="Search">
        </div>
        <select v-model="type" class="fselect" title="Type">
          <option v-for="t in typeOptions" :key="t.key" :value="t.key">Type : {{ t.label }}</option>
        </select>
      </div>

      <div class="head"><h1>Exhibitors ({{ filtered.length }})</h1></div>

      <div v-if="store.loading && !store.loaded" class="state">Loading exhibitors…</div>
      <div v-else-if="store.error" class="state">Couldn’t load exhibitors. Please try again.</div>
      <div v-else-if="!filtered.length" class="state">No exhibitors match your filters.</div>

      <div v-else class="cards">
        <ExhibitorsCard v-for="e in filtered" :key="e.id" :exhibitor="e" />
      </div>
    </section>

    <!-- Right rail: filters -->
    <aside class="rail">
      <div v-if="anyFilterActive || category || type !== 'all' || savedOnly || search" class="rail-head">
        <button type="button" class="clear" @click="clearAll">Clear All</button>
      </div>

      <!-- Configured "Manage Filters" facets -->
      <div v-for="f in store.filters" :key="f.id" class="card">
        <button type="button" class="ct ct-btn" @click="toggleFilterOpen(f.id)">
          <span class="ct-title">
            {{ f.title }}
            <span v-if="filterOnCount(f.id)" class="cnt">{{ filterOnCount(f.id) }}</span>
          </span>
          <svg class="chev" :class="{ open: openFilters[f.id] }" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6" /></svg>
        </button>
        <div v-if="openFilters[f.id]" class="opts">
          <template v-for="(h, hi) in f.headings" :key="hi">
            <div v-if="h.heading" class="ghead">{{ h.heading }}</div>
            <label v-for="opt in h.options" :key="hi + '::' + opt" class="cb">
              <input type="checkbox" :checked="isOptOn(f.id, opt)" @change="toggleOpt(f.id, opt)">
              <span class="box"><svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg></span>
              {{ opt }}
            </label>
          </template>
        </div>
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
            Saved only{{ bookmarks.count('exhibitor') ? ` (${bookmarks.count('exhibitor')})` : '' }}
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
            :key="c"
            type="button"
            class="opt"
            :class="{ on: category === c }"
            @click="category = c"
          >{{ c }}</button>
        </div>
      </div>
    </aside>

    <ExhibitorsDetailModal v-if="store.selected" :exhibitor="store.selected" />
  </div>
</template>

<style scoped>
.grid { display: grid; grid-template-columns: 1fr 300px; gap: 20px; align-items: start; }
@media (max-width: 860px) { .grid { grid-template-columns: 1fr; } }

.main { min-width: 0; }
.banner { width: 100%; margin-bottom: 16px; }
.toprow { display: flex; gap: 10px; }
.search { flex: 1; display: flex; align-items: center; gap: 8px; background: #fff; border-radius: 12px; padding: 0 14px; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.search svg { width: 18px; height: 18px; fill: none; stroke: #94a3b8; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; flex: 0 0 auto; }
.search input { border: none; background: none; outline: none; padding: 13px 0; width: 100%; font: inherit; font-size: .9rem; color: #334155; }
.fselect { flex: 0 0 auto; min-width: 170px; border: none; border-radius: 12px; padding: 0 14px; font: inherit; font-size: .86rem; color: #334155; background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
@media (max-width: 640px) { .toprow { flex-wrap: wrap; } .fselect { flex: 1 1 auto; min-width: 0; } }

.head { margin: 18px 0 16px; }
.head h1 { margin: 0; font-size: 1.4rem; font-weight: 800; color: #1e293b; }
.state { background: #fff; border-radius: 14px; padding: 48px 0; text-align: center; color: #64748b; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }

/* ── Right rail: filters ── */
.rail { display: flex; flex-direction: column; gap: 16px; }
.rail-head { display: flex; justify-content: flex-end; }
.clear { border: none; background: none; cursor: pointer; color: var(--brand-primary); font: inherit; font-size: .85rem; font-weight: 700; padding: 2px 6px; }

.card { background: #fff; border-radius: 14px; padding: 14px; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.ct { display: flex; align-items: center; justify-content: space-between; padding: 4px 6px 12px; border-bottom: 1px solid #eef0f3; color: #334155; font-weight: 600; }
.ct svg { width: 18px; height: 18px; fill: none; stroke: var(--brand-primary); stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.opts { display: flex; flex-direction: column; gap: 6px; margin-top: 10px; }
.opt { display: flex; align-items: center; justify-content: space-between; border: 1px solid transparent; background: none; border-radius: 10px; padding: 11px 12px; cursor: pointer; font: inherit; font-size: .9rem; color: #475569; text-align: left; }
.opt:hover { background: #f7f8fa; }
.opt.on { background: color-mix(in srgb, var(--brand-primary) 10%, #fff); border-color: color-mix(in srgb, var(--brand-primary) 35%, #fff); color: var(--brand-primary); font-weight: 600; }

/* Collapsible filter facets */
.ct-btn { width: 100%; border: none; background: none; cursor: pointer; padding: 4px 6px; border-bottom: none; font: inherit; font-weight: 600; }
.ct-title { display: inline-flex; align-items: center; gap: 8px; color: #334155; }
.cnt { display: inline-grid; place-items: center; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: var(--brand-primary); color: #fff; font-size: .68rem; font-weight: 700; }
.chev { transition: transform .18s ease; }
.chev.open { transform: rotate(180deg); }
.ghead { padding: 8px 6px 2px; color: #94a3b8; font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
.chk { width: 17px; height: 17px; fill: none; stroke: var(--brand-primary); stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }

/* Checkbox rows (configured filter facets) */
.cb { display: flex; align-items: center; gap: 10px; padding: 8px 6px; cursor: pointer; font-size: .88rem; color: #334155; }
.cb input { position: absolute; opacity: 0; width: 0; height: 0; }
.box { flex: 0 0 auto; width: 19px; height: 19px; border-radius: 5px; border: 1.5px solid #cbd5e1; display: inline-flex; align-items: center; justify-content: center; transition: background .15s, border-color .15s; }
.box svg { width: 13px; height: 13px; fill: none; stroke: #fff; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; opacity: 0; transition: opacity .1s; }
.cb input:checked + .box { background: var(--brand-primary); border-color: var(--brand-primary); }
.cb input:checked + .box svg { opacity: 1; }
</style>
