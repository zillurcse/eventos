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

const selected = ref<(string | number)[]>([])
const actionsFor = ref<string | null>(null)
// The actions menu is teleported to <body> (DataTable clips overflow), so track
// the trigger button's viewport position to anchor the menu there.
const actionsAnchor = ref<{ top: number; right: number } | null>(null)

function toggleActions(id: string, ev: MouseEvent) {
  if (actionsFor.value === id) {
    actionsFor.value = null
    actionsAnchor.value = null
    return
  }
  actionsFor.value = id
  const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
  actionsAnchor.value = { top: rect.bottom + 4, right: window.innerWidth - rect.right }
}
function closeActions() { actionsFor.value = null; actionsAnchor.value = null }

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

// Fixed-position teleported menu lives outside the page root, so close it on
// any window click/scroll rather than relying solely on the root div's handler.
const onWindowClick = () => closeActions()
const onWindowScroll = () => closeActions()
onMounted(() => {
  load()
  window.addEventListener('click', onWindowClick)
  window.addEventListener('scroll', onWindowScroll, true)
})
onBeforeUnmount(() => {
  window.removeEventListener('click', onWindowClick)
  window.removeEventListener('scroll', onWindowScroll, true)
})
</script>

<template>
  <div @click="closeActions">
    <!-- Page header -->
    <div class="mb-5">
      <h1 class="text-[1.35rem] font-bold text-ink mb-0.5">Manage Filter</h1>
      <p class="text-muted text-[.88rem]">Events Filter. Use drag and drop to rearrange the position.</p>
    </div>

    <!-- Toolbar: search + filter pills + add -->
    <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
      <SearchInput v-model="search" placeholder="Search" class="max-w-80" />
      <div class="flex items-center gap-2">
        <!-- <FilterSelect v-model="required" label="Required" :options="REQUIRED_OPTIONS" /> -->
        <button
          v-if="filtersActive"
          class="inline-flex items-center gap-1.5 text-[.85rem] font-semibold text-brand bg-transparent border-0 cursor-pointer hover:text-brand-dark"
          @click="clearFilters"
        >
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
          Clear filters
        </button>
        <button class="btn" @click="openAdd">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
          Add Filter
        </button>
      </div>
    </div>

    <DataTable
      v-model:items="filters"
      v-model:selected="selected"
      :columns="columns"
      :search="search"
      :search-text="searchText"
      :filter="rowFilter"
      selectable
      storage-key="showcase-filters"
      @reorder="persist"
    >
      <template #cell-title="{ row }">
        <span class="text-[#212529] font-semibold text-sm">{{ row.title }}</span>
      </template>
      <template #cell-label="{ row }">
        <span class="text-[#212529] font-semibold text-sm">{{ label(row) }}</span>
      </template>
      <template #cell-options="{ row }">
        <span class="text-[#212529] font-semibold text-sm">{{ optionsText(row) }}</span>
      </template>
      <template #actions="{ row }">
        <div class="relative inline-block" @click.stop>
          <button class="w-8 h-8 rounded-lg grid place-items-center text-muted hover:bg-[#f1f2f6] border-0 bg-transparent cursor-pointer" aria-label="Actions" @click="toggleActions(row.id, $event)">
            <svg viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
          </button>
          <Teleport to="body">
            <div
              v-if="actionsFor === row.id && actionsAnchor"
              class="fixed bg-white rounded-xl border border-[#E8E8EE] shadow-xl z-30 min-w-40 overflow-hidden p-2"
              :style="{ top: `${actionsAnchor.top}px`, right: `${actionsAnchor.right}px` }"
              @click.stop
            >
              <button
                class="w-full flex items-center gap-3 px-3.5 py-2.5 max-h-10 rounded-lg text-[.92rem] font-medium text-brand hover:bg-[#F7F7FB] cursor-pointer bg-transparent border-0 text-left"
                @click="closeActions(); openEdit(row)"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 15 15" fill="none">
                    <path d="M2.25 12.0043H5.43C5.5287 12.0049 5.62655 11.986 5.71793 11.9487C5.80931 11.9114 5.89242 11.8564 5.9625 11.7868L11.1525 6.58935L13.2825 4.50435C13.3528 4.43463 13.4086 4.35168 13.4467 4.26028C13.4847 4.16889 13.5043 4.07086 13.5043 3.97185C13.5043 3.87284 13.4847 3.77481 13.4467 3.68342C13.4086 3.59202 13.3528 3.50907 13.2825 3.43935L10.1025 0.221849C10.0328 0.151552 9.94983 0.0957567 9.85843 0.0576802C9.76704 0.0196037 9.66901 0 9.57 0C9.47099 0 9.37296 0.0196037 9.28157 0.0576802C9.19017 0.0957567 9.10722 0.151552 9.0375 0.221849L6.9225 2.34435L1.7175 7.54185C1.64799 7.61193 1.593 7.69504 1.55567 7.78642C1.51835 7.8778 1.49943 7.97564 1.5 8.07435V11.2543C1.5 11.4533 1.57902 11.644 1.71967 11.7847C1.86032 11.9253 2.05109 12.0043 2.25 12.0043ZM9.57 1.81185L11.6925 3.93435L10.6275 4.99935L8.505 2.87685L9.57 1.81185ZM3 8.38185L7.4475 3.93435L9.57 6.05685L5.1225 10.5043H3V8.38185ZM14.25 13.5043H0.75C0.551088 13.5043 0.360322 13.5834 0.21967 13.724C0.0790176 13.8647 0 14.0554 0 14.2543C0 14.4533 0.0790176 14.644 0.21967 14.7847C0.360322 14.9253 0.551088 15.0043 0.75 15.0043H14.25C14.4489 15.0043 14.6397 14.9253 14.7803 14.7847C14.921 14.644 15 14.4533 15 14.2543C15 14.0554 14.921 13.8647 14.7803 13.724C14.6397 13.5834 14.4489 13.5043 14.25 13.5043Z" fill="#6452E7"/>
                  </svg>
                Edit
              </button>
              <button
                class="w-full flex items-center gap-3 px-3.5 py-2.5 max-h-10 rounded-lg text-[.92rem] font-medium text-ink hover:bg-[#f7f8fa] cursor-pointer bg-transparent border-0 text-left"
                @click="closeActions(); removeFilter(row)"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="15" viewBox="0 0 14 15" fill="none">
                  <path d="M5.25 12C5.44891 12 5.63968 11.921 5.78033 11.7803C5.92098 11.6397 6 11.4489 6 11.25V6.75C6 6.55109 5.92098 6.36032 5.78033 6.21967C5.63968 6.07902 5.44891 6 5.25 6C5.05109 6 4.86032 6.07902 4.71967 6.21967C4.57902 6.36032 4.5 6.55109 4.5 6.75V11.25C4.5 11.4489 4.57902 11.6397 4.71967 11.7803C4.86032 11.921 5.05109 12 5.25 12ZM12.75 3H9.75V2.25C9.75 1.65326 9.51295 1.08097 9.09099 0.65901C8.66903 0.237053 8.09674 0 7.5 0H6C5.40326 0 4.83097 0.237053 4.40901 0.65901C3.98705 1.08097 3.75 1.65326 3.75 2.25V3H0.75C0.551088 3 0.360322 3.07902 0.21967 3.21967C0.0790176 3.36032 0 3.55109 0 3.75C0 3.94891 0.0790176 4.13968 0.21967 4.28033C0.360322 4.42098 0.551088 4.5 0.75 4.5H1.5V12.75C1.5 13.3467 1.73705 13.919 2.15901 14.341C2.58097 14.7629 3.15326 15 3.75 15H9.75C10.3467 15 10.919 14.7629 11.341 14.341C11.7629 13.919 12 13.3467 12 12.75V4.5H12.75C12.9489 4.5 13.1397 4.42098 13.2803 4.28033C13.421 4.13968 13.5 3.94891 13.5 3.75C13.5 3.55109 13.421 3.36032 13.2803 3.21967C13.1397 3.07902 12.9489 3 12.75 3ZM5.25 2.25C5.25 2.05109 5.32902 1.86032 5.46967 1.71967C5.61032 1.57902 5.80109 1.5 6 1.5H7.5C7.69891 1.5 7.88968 1.57902 8.03033 1.71967C8.17098 1.86032 8.25 2.05109 8.25 2.25V3H5.25V2.25ZM10.5 12.75C10.5 12.9489 10.421 13.1397 10.2803 13.2803C10.1397 13.421 9.94891 13.5 9.75 13.5H3.75C3.55109 13.5 3.36032 13.421 3.21967 13.2803C3.07902 13.1397 3 12.9489 3 12.75V4.5H10.5V12.75ZM8.25 12C8.44891 12 8.63968 11.921 8.78033 11.7803C8.92098 11.6397 9 11.4489 9 11.25V6.75C9 6.55109 8.92098 6.36032 8.78033 6.21967C8.63968 6.07902 8.44891 6 8.25 6C8.05109 6 7.86032 6.07902 7.71967 6.21967C7.57902 6.36032 7.5 6.55109 7.5 6.75V11.25C7.5 11.4489 7.57902 11.6397 7.71967 11.7803C7.86032 11.921 8.05109 12 8.25 12Z" fill="#64676A"/>
                </svg>
                Delete
              </button>
            </div>
          </Teleport>
        </div>
      </template>
      <template #empty>
        <div class="flex flex-col items-center gap-2.5 text-muted">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-faint"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
          <p class="m-0 text-[.88rem]">No filters yet.</p>
          <button class="btn sm" @click="openAdd">Add your first filter</button>
        </div>
      </template>
    </DataTable>

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
        class="w-full mt-3 py-3 rounded-xl border-2 border-dashed border-line text-muted hover:border-brand hover:text-brand hover:bg-[#F0EEFD]/30 transition-colors flex items-center justify-center gap-2 text-[.85rem] font-semibold bg-transparent cursor-pointer"
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
