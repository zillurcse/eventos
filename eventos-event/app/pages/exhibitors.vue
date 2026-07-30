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

const selections = reactive<Record<string, string[]>>({})

function isOptOn(fid: string, opt: string) { return !!selections[fid]?.includes(opt) }
function toggleOpt(fid: string, opt: string) {
  const arr = (selections[fid] ||= [])
  const i = arr.indexOf(opt)
  if (i >= 0) arr.splice(i, 1)
  else arr.push(opt)
  if (!arr.length) delete selections[fid]
}

function exhibitorOptions(e: Exhibitor, fid: string): string[] {
  const group = e.filter_selections?.[fid]
  return group ? Object.values(group).flat() : []
}

const anyFilterActive = computed(() =>
  (Object.values(selections) as string[][]).some(a => a.length > 0)
  || category.value !== ''
  || type.value !== 'all'
  || savedOnly.value,
)

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

const currentTypeLabel = computed(() => typeOptions.find(t => t.key === type.value)?.label ?? 'All')

const typeOpen = ref(false)
const typeWrap = ref<HTMLElement | null>(null)
function onOutsideType(e: MouseEvent) {
  if (typeWrap.value && !typeWrap.value.contains(e.target as Node)) typeOpen.value = false
}
watch(typeOpen, (open) => {
  if (open) document.addEventListener('click', onOutsideType, true)
  else document.removeEventListener('click', onOutsideType, true)
})
onBeforeUnmount(() => document.removeEventListener('click', onOutsideType, true))
function selectType(key: typeof type.value) {
  type.value = key
  typeOpen.value = false
}

const currentSortLabel = computed(() => sortOptions.find(o => o.key === sort.value)?.label ?? 'Default')

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

const filterPanelTitle = computed(() => store.filters[0]?.title ?? 'Filters')

const activeChips = computed(() => {
  const chips: Array<{ key: string, label: string, clear: () => void }> = []
  if (savedOnly.value) chips.push({ key: 'saved', label: 'Saved only', clear: () => { savedOnly.value = false } })
  if (type.value !== 'all') {
    chips.push({
      key: 'type',
      label: typeOptions.find(t => t.key === type.value)?.label ?? type.value,
      clear: () => { type.value = 'all' },
    })
  }
  if (category.value) chips.push({ key: 'cat', label: category.value, clear: () => { category.value = '' } })
  for (const [fid, opts] of Object.entries(selections) as [string, string[]][]) {
    for (const opt of opts) {
      chips.push({ key: `${fid}::${opt}`, label: opt, clear: () => toggleOpt(fid, opt) })
    }
  }
  return chips
})

const filtered = computed<Exhibitor[]>(() => {
  const q = search.value.trim().toLowerCase()
  let list = store.all.filter((e) => {
    if (savedOnly.value && !bookmarks.isOn('exhibitor', e.id)) return false
    if (type.value !== 'all' && e.type !== type.value) return false
    if (category.value && e.category !== category.value) return false
    for (const [fid, opts] of Object.entries(selections) as [string, string[]][]) {
      if (!opts.length) continue
      const has = exhibitorOptions(e, fid)
      if (!opts.some(o => has.includes(o))) return false
    }
    if (!q) return true
    return `${e.name} ${e.category} ${e.description}`.toLowerCase().includes(q)
  })

  if (sort.value === 'default') {
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
    <section class="main">
      <ReceptionAdStrip v-if="store.ads.length" :ads="store.ads"  />

      <div class="toolbar">
        <div class="search">
          <svg viewBox="0 0 24 24"><path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3" /></svg>
          <input v-model="search" type="text" placeholder="Search">
        </div>

        <div ref="typeWrap" class="type-select">
          <button type="button" class="type-btn" @click="typeOpen = !typeOpen">
            <span>Type : {{ currentTypeLabel }}</span>
            <svg class="chev" :class="{ open: typeOpen }" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6" /></svg>
          </button>
          <div v-if="typeOpen" class="type-pop">
            <button
              v-for="t in typeOptions"
              :key="t.key"
              type="button"
              class="type-opt"
              :class="{ on: type === t.key }"
              @click="selectType(t.key)"
            >{{ t.label }}</button>
          </div>
        </div>
      </div>

      <h2 class="count">Exhibitors ({{ filtered.length }})</h2>

      <div v-if="store.loading && !store.loaded" class="state">Loading exhibitors…</div>
      <div v-else-if="store.error" class="state">Couldn't load exhibitors. Please try again.</div>
      <div v-else-if="!filtered.length" class="state">No exhibitors match your filters.</div>

      <div v-else class="cards">
        <ExhibitorsCard v-for="e in filtered" :key="e.id" :exhibitor="e" />
      </div>
    </section>

    <aside class="rail">
      <div class="filter-card">
        <div class="fc-head">
          <h2>{{ filterPanelTitle }}</h2>
          <div class="fc-actions">
            <!-- <button
              type="button"
              class="saved-icon"
              :class="{ on: savedOnly }"
              :title="savedOnly ? 'Showing saved only' : 'Show saved only'"
              @click="savedOnly = !savedOnly"
            >
              <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
            </button> -->
            <button v-if="anyFilterActive || search" type="button" class="clear-all" @click="clearAll">
              <svg xmlns="http://www.w3.org/2000/svg" width="17" height="16" viewBox="0 0 17 16" fill="none">
                <path d="M9.4158 8.00409L15.7158 1.71409C15.9041 1.52579 16.0099 1.27039 16.0099 1.00409C16.0099 0.73779 15.9041 0.482395 15.7158 0.294092C15.5275 0.105788 15.2721 0 15.0058 0C14.7395 0 14.4841 0.105788 14.2958 0.294092L8.0058 6.59409L1.7158 0.294092C1.52749 0.105788 1.2721 2.36434e-07 1.0058 2.38419e-07C0.739497 2.40403e-07 0.484102 0.105788 0.295798 0.294092C0.107495 0.482395 0.00170684 0.73779 0.00170684 1.00409C0.00170684 1.27039 0.107495 1.52579 0.295798 1.71409L6.5958 8.00409L0.295798 14.2941C0.20207 14.3871 0.127676 14.4977 0.0769072 14.6195C0.0261385 14.7414 0 14.8721 0 15.0041C0 15.1361 0.0261385 15.2668 0.0769072 15.3887C0.127676 15.5105 0.20207 15.6211 0.295798 15.7141C0.388761 15.8078 0.499362 15.8822 0.621222 15.933C0.743081 15.9838 0.873786 16.0099 1.0058 16.0099C1.13781 16.0099 1.26852 15.9838 1.39038 15.933C1.51223 15.8822 1.62284 15.8078 1.7158 15.7141L8.0058 9.41409L14.2958 15.7141C14.3888 15.8078 14.4994 15.8822 14.6212 15.933C14.7431 15.9838 14.8738 16.0099 15.0058 16.0099C15.1378 16.0099 15.2685 15.9838 15.3904 15.933C15.5122 15.8822 15.6228 15.8078 15.7158 15.7141C15.8095 15.6211 15.8839 15.5105 15.9347 15.3887C15.9855 15.2668 16.0116 15.1361 16.0116 15.0041C16.0116 14.8721 15.9855 14.7414 15.9347 14.6195C15.8839 14.4977 15.8095 14.3871 15.7158 14.2941L9.4158 8.00409Z" fill="#64676A"/>
                </svg>
            </button>
          </div>
        </div>

        <div v-if="activeChips.length" class="chips">
          <button v-for="chip in activeChips" :key="chip.key" type="button" class="chip active" @click="chip.clear()">
            {{ chip.label }}
            <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
          </button>
        </div>

        <div
          v-for="f in store.filters"
          :key="f.id"
          class="filter-group"
        >
          <div v-for="(h, hi) in f.headings" :key="f.id + '-' + hi" class="fsection">
            <h3 v-if="h.heading">{{ h.heading }}</h3>
            <label v-for="opt in h.options" :key="f.id + '::' + opt" class="cb">
              <input type="checkbox" :checked="isOptOn(f.id, opt)" @change="toggleOpt(f.id, opt)">
              <span class="box"><svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg></span>
              {{ opt }}
            </label>
          </div>
        </div>

        <div class="fsection">
          <h3>Sort By</h3>
          <div ref="sortWrap" class="type-select full">
            <button type="button" class="type-btn" @click="sortOpen = !sortOpen">
              <span>{{ currentSortLabel }}</span>
              <svg class="chev" :class="{ open: sortOpen }" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6" /></svg>
            </button>
            <div v-if="sortOpen" class="type-pop">
              <button
                v-for="o in sortOptions"
                :key="o.key"
                type="button"
                class="type-opt"
                :class="{ on: sort === o.key }"
                @click="selectSort(o.key)"
              >{{ o.label }}</button>
            </div>
          </div>
        </div>

        <div v-if="store.categories.length" class="fsection">
          <h3>Category</h3>
          <div class="sort-opts">
            <button type="button" class="sort-opt" :class="{ on: category === '' }" @click="category = ''">All categories</button>
            <button
              v-for="c in store.categories"
              :key="c"
              type="button"
              class="sort-opt"
              :class="{ on: category === c }"
              @click="category = c"
            >{{ c }}</button>
          </div>
        </div>
      </div>
    </aside>

    <ExhibitorsDetailModal v-if="store.selected" :exhibitor="store.selected" />
  </div>
</template>

<style scoped>
.grid {
  display: grid;
  grid-template-columns: 1fr 368px;
  gap: 24px;
  align-items: start;
}

@media (max-width: 960px) {
  .grid { grid-template-columns: 1fr; }
}

.main { min-width: 0; }

.banner { width: 100%; margin-bottom: 0; }

.toolbar {
  display: flex;
  gap: 12px;
  align-items: stretch;
  margin-top: 20px;
}

.search {
  position: relative;
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  max-height: 40px;

}

.search svg {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  width: 18px;
  height: 18px;
  fill: none;
  stroke: #94a3b8;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
  pointer-events: none;

}

.search input {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid #D1D2DE;
  background: #fff;
  border-radius: 8px;
  padding: 11px 14px 11px 40px;
  max-height: 40px;
  font: inherit;
  font-size: .95rem;
  color: #334155;
  outline: none;
}

.search input::placeholder { color: #94a3b8; }

.search input:focus {
  border-color: color-mix(in srgb, var(--brand-primary) 50%, #D1D2DE);
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--brand-primary) 15%, transparent);
}

.type-select { position: relative; flex: none;
  max-height: 40px;
}

.type-select.full {
  width: 100%;
  max-height: none;
}

.type-select.full .type-pop {
  left: 0;
  right: 0;
}

.type-btn {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  width: 100%;
  height: 100%;
  box-sizing: border-box;
  background: #fff;
  border: 1px solid #D1D2DE;
  border-radius: 8px;
  padding: 11px 16px;
  max-height: 40px;
  font: inherit;
  font-size: .9rem;
  color: #334155;
  cursor: pointer;
  white-space: nowrap;
}

.type-btn .chev {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: #64748b;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: transform .15s ease;
}

.type-btn .chev.open { transform: rotate(180deg); }

.type-pop {
  position: absolute;
  z-index: 20;
  top: calc(100% + 6px);
  right: 0;
  min-width: 160px;
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 12px 30px rgba(15, 23, 42, .15);
  border: 1px solid #eef0f3;
  overflow: hidden;
  padding: 6px;
  width: 100%;
}

.type-opt {
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

.type-opt:hover { background: #f7f8fa; }

.type-opt.on {
  color: var(--brand-primary);
  font-weight: 700;
  background: color-mix(in srgb, var(--brand-primary) 8%, #fff);
}

.count {
  margin: 20px 0 16px;
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
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
  align-items: start;
}

@media (max-width: 640px) {
  .cards { grid-template-columns: 1fr; }
  .toolbar { flex-wrap: wrap; }
  .type-select { flex: 1; min-width: 0; }
  .type-btn { width: 100%; justify-content: space-between; }
}

/* Right rail */
.rail {
  position: sticky;
  top: 20px;
  display: flex;
  flex-direction: column;
}

.filter-card {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  border: 1px solid #E8E8EE
}

.fc-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 22px;
}

.fc-head h2 {
  margin: 0;
  font-size: 18px;
  line-height: 1.2;
  font-weight: 700;
  color: #4D5154;
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
  background: color-mix(in srgb, var(--brand-primary) 10%, #fff);
  width: 30px;
  height: 30px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 0;
}
.clear-all svg{
  width: 14px;
}

.clear-all:hover { text-decoration: underline; }

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

.saved-icon.on svg { fill: currentColor; }

.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 22px;
}

.chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border-radius: 100px;
  padding: 6px 8px;
  max-height: 24px;
  font: inherit;
  font-size: 12px;
  cursor: pointer;
  white-space: nowrap;
}

.chip.active {
  background: color-mix(in srgb, var(--brand-primary) 10%, #fff);
  border: none;
  color: var(--brand-primary);
  font-weight: 500;
}

.chip.active svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.fsection {
  padding-top: 12px;
  padding-bottom: 12px;
  border-top: 1px solid #eef0f3;
}

.fsection + .fsection { margin-top: 0; }

.fsection h3 {
  margin: 0 0 6px;
  font-size: 14px;
  font-weight: 500;
  color: #4D5154;
  letter-spacing: normal;
  text-transform: none;
}

.cb {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
  padding-bottom: 8px;
  cursor: pointer;
  font-size: 14px;
  line-height: 1.2;
  color: #4D5154;
}

.cb input { position: absolute; opacity: 0; width: 0; height: 0; }

.box {
  flex: 0 0 auto;
  width: 16px;
  height: 16px;
  border-radius: 4px;
  border: 2px solid #9B9D9E;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: background .15s, border-color .15s;
}

.box svg {
  width: 15px;
  height: 15px;
  fill: none;
  stroke: #fff;
  stroke-width: 3;
  stroke-linecap: round;
  stroke-linejoin: round;
  opacity: 0;
  transition: opacity .1s;
}

.cb input:checked + .box {
  background: var(--brand-primary);
  border-color: var(--brand-primary);
}

.cb input:checked + .box svg { opacity: 1; }

.sort-opts {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.sort-opt {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border: 1px solid transparent;
  background: none;
  border-radius: 8px;
  padding: 9px 10px;
  cursor: pointer;
  font: inherit;
  font-size: .88rem;
  color: #475569;
  text-align: left;
}

.sort-opt:hover { background: #f7f8fa; }

.sort-opt.on {
  background: color-mix(in srgb, var(--brand-primary) 10%, #fff);
  border-color: color-mix(in srgb, var(--brand-primary) 30%, #fff);
  color: var(--brand-primary);
  font-weight: 600;
}

.chk {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: var(--brand-primary);
  stroke-width: 2.2;
  stroke-linecap: round;
  stroke-linejoin: round;
}
</style>
