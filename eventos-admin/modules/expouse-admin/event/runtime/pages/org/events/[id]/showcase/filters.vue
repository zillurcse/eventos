<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface Heading { heading: string, mandatory: boolean, options: string[] }
interface Filter { id: string, title: string, headings: Heading[] }

const filters = ref<Filter[]>([])
const search = ref('')
const required = ref('all')
const REQUIRED_OPTIONS = [
  { label: 'All', value: 'all' },
  { label: 'Required', value: 'yes' },
  { label: 'Optional', value: 'no' },
]
const rowFilter = computed(() =>
  required.value === 'all'
    ? undefined
    : (f: Filter) => f.headings.some(h => h.mandatory) === (required.value === 'yes'),
)
const filtersActive = computed(() => required.value !== 'all' || !!search.value.trim())
function clearFilters() { required.value = 'all'; search.value = '' }

const drawerOpen = ref(false)
const editingId = ref<string | null>(null)
const draft = reactive<Filter>({ id: '', title: '', headings: [] })
const expanded = ref(0)
const bulkFor = ref<number | null>(null)
const bulkText = ref('')

const newHeading = (): Heading => ({ heading: '', mandatory: false, options: [''] })

async function load() {
  try { filters.value = (await api<any>(`/events/${id}/settings`)).data.filters || [] } catch { /* */ }
}
async function persist() {
  await api(`/events/${id}/settings`, { method: 'PUT', body: { filters: JSON.parse(JSON.stringify(filters.value)) } })
}

// ── table ──
const columns = [
  { key: 'title', label: 'Title', sortable: true },
  { key: 'label', label: 'Label' },
  { key: 'options', label: 'Options' },
]
function searchText(f: Filter) {
  return f.title + ' ' + f.headings.map(h => h.heading + ' ' + h.options.join(' ')).join(' ')
}
function label(f: Filter) { return f.headings[0]?.heading || '—' }
function optionsText(f: Filter) {
  const opts = f.headings.flatMap(h => h.options).filter(Boolean)
  const joined = opts.join(', ')
  return joined.length > 48 ? joined.slice(0, 48) + '…' : (joined || '—')
}

// ── drawer ──
function openAdd() {
  editingId.value = null
  Object.assign(draft, { id: 'f' + Date.now(), title: '', headings: [newHeading()] })
  expanded.value = 0; bulkFor.value = null; drawerOpen.value = true
}
function openEdit(f: Filter) {
  editingId.value = f.id
  Object.assign(draft, JSON.parse(JSON.stringify(f)))
  if (!draft.headings.length) draft.headings.push(newHeading())
  expanded.value = 0; bulkFor.value = null; drawerOpen.value = true
}
function addHeading() { draft.headings.push(newHeading()); expanded.value = draft.headings.length - 1; bulkFor.value = null }
function removeHeading(i: number) { draft.headings.splice(i, 1); if (expanded.value >= draft.headings.length) expanded.value = Math.max(0, draft.headings.length - 1) }
function addOption(hi: number) { draft.headings[hi].options.push('') }
function removeOption(hi: number, oi: number) { draft.headings[hi].options.splice(oi, 1) }
function toggleBulk(hi: number) { bulkFor.value = bulkFor.value === hi ? null : hi; bulkText.value = '' }
function applyBulk(hi: number) {
  const items = bulkText.value.split(/[\n,]/).map(s => s.trim()).filter(Boolean)
  const opts = draft.headings[hi].options.filter(Boolean)
  draft.headings[hi].options = [...opts, ...items]
  bulkFor.value = null; bulkText.value = ''
}

async function saveDraft() {
  const clean: Filter = JSON.parse(JSON.stringify(draft))
  clean.headings = clean.headings.map(h => ({ ...h, options: h.options.filter(o => o.trim()) }))
  if (editingId.value) {
    const i = filters.value.findIndex(f => f.id === editingId.value)
    if (i >= 0) filters.value[i] = clean
  } else {
    filters.value.push(clean)
  }
  await persist()
  drawerOpen.value = false
}
async function removeFilter(f: Filter) {
  if (!confirm(`Delete filter "${f.title}"?`)) return
  filters.value = filters.value.filter(x => x.id !== f.id)
  await persist()
}

onMounted(load)
</script>

<template>
  <div>
    <!-- Page header -->
    <div class="flex items-center justify-between gap-4 flex-wrap mb-6">
      <div>
        <h1 class="text-[1.35rem] font-bold text-ink mb-0.5">Manage Filters</h1>
        <p class="text-muted text-[.88rem]">Drag rows to reorder how filters appear to attendees.</p>
      </div>
      <button class="btn" @click="openAdd">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        Add filter
      </button>
    </div>

    <div class="card">
      <!-- Toolbar: search + filter pills -->
      <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
        <SearchInput v-model="search" placeholder="Search filters" class="max-w-80" />
        <div class="flex items-center gap-2">
          <FilterSelect v-model="required" label="Required" :options="REQUIRED_OPTIONS" />
          <button
            v-if="filtersActive"
            class="inline-flex items-center gap-1.5 text-[.85rem] font-semibold text-brand bg-transparent border-0 cursor-pointer hover:text-brand-dark"
            @click="clearFilters"
          >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            Clear filters
          </button>
        </div>
      </div>

      <DataTable
        v-model:items="filters"
        :columns="columns"
        :search="search"
        :search-text="searchText"
        :filter="rowFilter"
        reorderable
        storage-key="showcase-filters"
        @reorder="persist"
      >
        <template #cell-title="{ row }">
          <span class="text-brand-dark font-semibold">{{ row.title }}</span>
        </template>
        <template #cell-label="{ row }">
          <span class="text-brand-dark">{{ label(row) }}</span>
        </template>
        <template #cell-options="{ row }">
          <span class="text-muted">{{ optionsText(row) }}</span>
        </template>
        <template #actions="{ row }">
          <button class="w-8 h-8 rounded-lg grid place-items-center text-muted hover:text-brand hover:bg-brand-soft border-0 bg-transparent cursor-pointer" title="Edit" @click="openEdit(row)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
          </button>
          <button class="w-8 h-8 rounded-lg grid place-items-center text-muted hover:text-[#dc2626] hover:bg-[#fef2f2] border-0 bg-transparent cursor-pointer" title="Delete" @click="removeFilter(row)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
          </button>
        </template>
        <template #empty>
          <div class="flex flex-col items-center gap-2.5 text-muted">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-faint"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
            <p class="m-0 text-[.88rem]">No filters yet.</p>
            <button class="btn sm" @click="openAdd">Add your first filter</button>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Add / Update drawer -->
    <Drawer v-if="drawerOpen" :title="editingId ? 'Update Filter' : 'Add Filter'" @close="drawerOpen = false">
      <p class="text-muted text-[.84rem] m-0 mb-4">Modify filters to help users narrow results based on selected categories, dates, or custom preferences.</p>

      <label>Filter title</label>
      <input v-model="draft.title" placeholder="Enter filter title">

      <div v-for="(h, hi) in draft.headings" :key="hi" class="mt-3">
        <div class="rounded-xl border border-line overflow-hidden">
          <!-- collapsed bar -->
          <button
            v-if="expanded !== hi"
            class="w-full flex items-center justify-between gap-3 px-4 py-3.5 bg-[#f7f8fa] cursor-pointer text-left border-0"
            @click="expanded = hi"
          >
            <span class="flex items-center gap-2 min-w-0">
              <span class="font-semibold text-[.9rem] text-brand-dark truncate">{{ h.heading || ('Heading ' + (hi + 1)) }}</span>
              <span v-if="h.mandatory" class="badge shrink-0">Required</span>
            </span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-faint shrink-0"><path d="m6 9 6 6 6-6"/></svg>
          </button>

          <!-- expanded editor -->
          <div v-else class="p-4">
            <div class="flex items-center justify-between gap-3">
              <label class="m-0">Heading</label>
              <label class="flex items-center gap-2 text-[.82rem] m-0 cursor-pointer select-none">
                Mandatory
                <span class="relative w-10 h-[22px] rounded-full shrink-0 transition-colors duration-150" :class="h.mandatory ? 'bg-brand' : 'bg-[#cdd2dc]'">
                  <i class="absolute top-[3px] left-[3px] w-4 h-4 rounded-full bg-white shadow-sm transition-transform duration-150" :class="h.mandatory ? 'translate-x-[18px]' : 'translate-x-0'" />
                </span>
                <input v-model="h.mandatory" type="checkbox" class="sr-only">
              </label>
            </div>
            <input v-model="h.heading" placeholder="Enter heading">

            <label class="mt-3 block">Options</label>
            <div v-for="(o, oi) in h.options" :key="oi" class="flex items-center gap-2 mb-2">
              <input v-model="h.options[oi]" :placeholder="'Option #' + (oi + 1)" class="m-0">
              <button
                class="w-8 h-8 rounded-lg border border-line bg-white grid place-items-center text-muted hover:text-[#dc2626] hover:border-[#f3c9c9] shrink-0 cursor-pointer"
                title="Remove option" @click="removeOption(hi, oi)"
              >
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
              </button>
            </div>
            <button class="btn ghost sm" @click="addOption(hi)">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
              Add option
            </button>

            <div v-if="bulkFor === hi" class="mt-3">
              <textarea v-model="bulkText" rows="3" placeholder="Paste one option per line (or comma-separated)" />
              <div class="flex justify-end gap-2 mt-1.5">
                <button class="btn sm ghost" @click="bulkFor = null">Cancel</button>
                <button class="btn sm" @click="applyBulk(hi)">Add options</button>
              </div>
            </div>

            <div class="flex items-center justify-between mt-4 pt-3 border-t border-line">
              <button class="btn ghost sm" @click="toggleBulk(hi)">Bulk upload</button>
              <button
                v-if="draft.headings.length > 1"
                class="w-8 h-8 rounded-lg grid place-items-center text-muted hover:text-[#dc2626] hover:bg-[#fef2f2] border-0 bg-transparent cursor-pointer"
                title="Delete heading" @click="removeHeading(hi)"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <button
        class="w-full mt-3 py-3 rounded-xl border-2 border-dashed border-line text-muted hover:border-brand hover:text-brand hover:bg-brand-soft/30 transition-colors flex items-center justify-center gap-2 text-[.85rem] font-semibold bg-transparent cursor-pointer"
        @click="addHeading"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        Add heading
      </button>

      <div class="modal-actions border-t border-line pt-4 mt-4">
        <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
        <button class="btn" :disabled="!draft.title.trim()" @click="saveDraft">{{ editingId ? 'Update filter' : 'Add filter' }}</button>
      </div>
    </Drawer>
  </div>
</template>
