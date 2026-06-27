<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface Heading { heading: string, mandatory: boolean, options: string[] }
interface Filter { id: string, title: string, headings: Heading[] }

const filters = ref<Filter[]>([])
const search = ref('')
const perPage = ref(10)
const page = ref(1)
const dragIndex = ref<number | null>(null)

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
const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return filters.value
  return filters.value.filter(f => (f.title + ' ' + f.headings.map(h => h.heading + ' ' + h.options.join(' ')).join(' ')).toLowerCase().includes(q))
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage.value)))
const paged = computed(() => filtered.value.slice((page.value - 1) * perPage.value, page.value * perPage.value))
const rangeText = computed(() => {
  const n = filtered.value.length
  if (!n) return '0 of 0'
  const start = (page.value - 1) * perPage.value + 1
  return `${start} - ${Math.min(page.value * perPage.value, n)} of ${n}`
})
function label(f: Filter) { return f.headings[0]?.heading || '—' }
function optionsText(f: Filter) {
  const opts = f.headings.flatMap(h => h.options).filter(Boolean)
  const joined = opts.join(', ')
  return joined.length > 48 ? joined.slice(0, 48) + '…' : (joined || '—')
}

// drag reorder (operates on the underlying filters array)
function onDragStart(i: number) { dragIndex.value = i }
function onDragOver(i: number, e: DragEvent) {
  e.preventDefault()
  if (dragIndex.value === null || dragIndex.value === i) return
  const realFrom = filters.value.indexOf(paged.value[dragIndex.value])
  const realTo = filters.value.indexOf(paged.value[i])
  if (realFrom < 0 || realTo < 0) return
  const arr = [...filters.value]
  const [m] = arr.splice(realFrom, 1)
  arr.splice(realTo, 0, m)
  filters.value = arr
  dragIndex.value = i
}
function onDragEnd() { if (dragIndex.value !== null) { dragIndex.value = null; persist() } }

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
    <div class="mb-4">
      <h2 class="section-title m-0">Manage Filter</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Events filter. Use drag and drop to rearrange the position.</p>
    </div>

    <div class="card">
      <div class="flex items-center justify-between gap-4 mb-3.5">
        <div>
          <div class="font-bold text-base">Filter</div>
          <div class="muted text-[.84rem]">Drag and drop rows to rearrange the position.</div>
        </div>
        <button class="btn" @click="openAdd"><Icon name="plus" class="w-[15px] h-[15px]" /> FILTER</button>
      </div>

      <div class="search max-w-[380px] mb-3.5">
        <Icon name="search" />
        <input v-model="search" placeholder="Search">
      </div>

      <table>
        <thead>
          <tr>
            <th>TITLE</th><th>LABEL</th><th>OPTIONS</th>
            <th class="text-right">ACTIONS</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(f, i) in paged" :key="f.id"
            class="cursor-grab align-middle"
            :class="{ 'opacity-50 bg-[#f3f0ff]': dragIndex === i }"
            draggable="true" @dragstart="onDragStart(i)" @dragover="onDragOver(i, $event)" @dragend="onDragEnd"
          >
            <td class="text-[#6352e7] font-semibold">{{ f.title }}</td>
            <td class="text-[#6352e7]">{{ label(f) }}</td>
            <td class="muted">{{ optionsText(f) }}</td>
            <td class="text-right whitespace-nowrap">
              <button
                class="bg-transparent border-0 cursor-pointer text-base px-2 py-1 text-[#6352e7]"
                title="Edit" @click="openEdit(f)"
              >✎</button>
              <button
                class="bg-transparent border-0 cursor-pointer text-base px-2 py-1 text-[#dc2626]"
                title="Delete" @click="removeFilter(f)"
              >🗑</button>
            </td>
          </tr>
          <tr v-if="!paged.length">
            <td colspan="4" class="muted text-center py-7">No filters yet. Click <strong>+ FILTER</strong> to add one.</td>
          </tr>
        </tbody>
      </table>

      <div class="flex items-center justify-end gap-4 mt-3.5 flex-wrap">
        <label class="flex items-center gap-1.5 text-[.84rem] m-0">Nb / page
          <select v-model.number="perPage" class="w-auto m-0 py-1.5 px-2">
            <option :value="10">10</option><option :value="25">25</option><option :value="50">50</option>
          </select>
        </label>
        <label class="flex items-center gap-1.5 text-[.84rem] m-0">Page
          <select v-model.number="page" class="w-auto m-0 py-1.5 px-2">
            <option v-for="p in totalPages" :key="p" :value="p">{{ p }}</option>
          </select>
        </label>
        <span class="muted text-[.84rem]">{{ rangeText }}</span>
        <button class="btn ghost sm" :disabled="page <= 1" @click="page--">‹</button>
        <button class="btn ghost sm" :disabled="page >= totalPages" @click="page++">›</button>
      </div>
    </div>

    <!-- Add / Update drawer -->
    <Drawer v-if="drawerOpen" :title="editingId ? 'Update Filter' : 'Add Filter'" @close="drawerOpen = false">
      <h2 class="text-[1.1rem] m-0 mb-1">Filter Details</h2>
      <p class="muted text-[.84rem] m-0 mb-4">Modify filters to help users narrow results based on selected categories, dates, or custom preferences.</p>

      <label>Filter</label>
      <input v-model="draft.title" placeholder="Enter Filter Title">

      <div v-for="(h, hi) in draft.headings" :key="hi" class="mt-3.5">
        <!-- collapsed bar -->
        <button
          v-if="expanded !== hi"
          class="w-full text-left py-3.5 px-4 border border-line rounded-lg bg-[#f7f8fa] cursor-pointer font-semibold text-[#6352e7]"
          @click="expanded = hi"
        >+ {{ h.heading || ('Heading ' + (hi + 1)) }}</button>
        <!-- expanded editor -->
        <div v-else class="border border-line rounded-xl p-4">
          <div class="flex justify-between items-center">
            <label class="m-0">Heading</label>
            <label class="flex items-center gap-1.5 text-[.82rem] m-0 cursor-pointer">
              <input v-model="h.mandatory" type="checkbox" class="w-4 h-4 m-0 accent-[#6352e7]"> Mandatory
            </label>
          </div>
          <input v-model="h.heading" placeholder="Enter Heading">

          <label>Options</label>
          <div v-for="(o, oi) in h.options" :key="oi" class="flex items-center gap-2 mb-1.5">
            <input v-model="h.options[oi]" :placeholder="'Option #' + (oi + 1)" class="m-0">
            <button
              class="w-[30px] h-[30px] rounded-full border border-line bg-white cursor-pointer text-[#6352e7] shrink-0"
              title="Remove" @click="removeOption(hi, oi)"
            >✕</button>
          </div>
          <div class="text-right">
            <button
              class="bg-transparent border-0 text-[#6352e7] font-bold text-[.82rem] cursor-pointer tracking-[.02em]"
              @click="addOption(hi)"
            >+ ADD OPTION</button>
          </div>

          <div v-if="bulkFor === hi" class="my-2">
            <textarea v-model="bulkText" rows="3" placeholder="Paste one option per line (or comma-separated)" />
            <div class="text-right">
              <button class="btn sm ghost" @click="bulkFor = null">Cancel</button>
              <button class="btn sm" @click="applyBulk(hi)">Add options</button>
            </div>
          </div>

          <div class="flex justify-between items-center mt-2">
            <button class="btn ghost sm" @click="toggleBulk(hi)">+ BULK UPLOAD</button>
            <button
              v-if="draft.headings.length > 1"
              class="bg-transparent border-0 cursor-pointer text-base px-2 py-1 text-[#dc2626]"
              title="Delete heading" @click="removeHeading(hi)"
            >🗑</button>
          </div>
        </div>
      </div>

      <div class="text-right mt-3">
        <button
          class="bg-transparent border-0 text-[#6352e7] font-bold text-[.82rem] cursor-pointer tracking-[.02em]"
          @click="addHeading"
        >+ ADD HEADING</button>
      </div>

      <div class="modal-actions border-t border-line pt-4 mt-2">
        <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
        <button class="btn" :disabled="!draft.title.trim()" @click="saveDraft">{{ editingId ? 'UPDATE' : 'ADD' }}</button>
      </div>
    </Drawer>
  </div>
</template>
