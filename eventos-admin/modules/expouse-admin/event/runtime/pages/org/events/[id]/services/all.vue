<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

// ── types ──────────────────────────────────────────────────────────────
interface Category { id: number, name: string, description?: string | null, is_active?: boolean, items_count?: number }
interface Option { id?: number, uuid?: string, name: string, unit: string, rate: number | string, image?: string | null }
interface RateCondition { from_date: string, to_date: string, rate: number | string }
interface Service {
  group_uuid: string
  category: { id: number, name: string }
  currency: string
  tax: number
  enable_discount: boolean
  discount: number
  discount_type: 'fixed' | 'percentage'
  discount_start_date: string | null
  discount_end_date: string | null
  description: string | null
  long_description: string | null
  dynamic_pricing: boolean
  rate_conditions: RateCondition[]
  is_active: boolean
  status: string
  options: Option[]
  title: string
  unit: string
  rate: number
  more_count: number
}

const CURRENCIES: { code: string, label: string }[] = [
  { code: 'USD', label: 'US Dollar' },
  { code: 'EUR', label: 'Euro' },
  { code: 'GBP', label: 'British Pound' },
  { code: 'OMR', label: 'Omani Rial' },
  { code: 'AED', label: 'UAE Dirham' },
  { code: 'SAR', label: 'Saudi Riyal' },
  { code: 'QAR', label: 'Qatari Riyal' },
  { code: 'INR', label: 'Indian Rupee' },
  { code: 'BDT', label: 'Bangladeshi Taka' },
]
function currencyLabel(code: string) {
  return CURRENCIES.find(c => c.code === code)?.label || code
}
function money(code: string, rate: number) {
  return `${currencyLabel(code)} ${Number(rate).toFixed(2)}`
}

// ── state ──────────────────────────────────────────────────────────────
const services = ref<Service[]>([])
const categories = ref<Category[]>([])
const loading = ref(true)

const search = ref('')
const categoryFilter = ref('')
const sort = ref<'new' | 'old' | 'az'>('new')
const perPage = ref(10)
const page = ref(1)

async function loadCategories() {
  categories.value = (await api<{ data: Category[] }>(`/events/${id}/service-categories`)).data
}
async function loadServices() {
  loading.value = true
  try {
    services.value = (await api<{ data: Service[] }>(`/events/${id}/services`)).data
  } finally {
    loading.value = false
  }
}

// ── table derivation ───────────────────────────────────────────────────
const filtered = computed(() => {
  let list = services.value.slice()
  const q = search.value.trim().toLowerCase()
  if (q) {
    list = list.filter(s => (s.category.name + ' ' + s.options.map(o => o.name).join(' ') + ' ' + (s.description || ''))
      .toLowerCase().includes(q))
  }
  if (categoryFilter.value) list = list.filter(s => String(s.category.id) === categoryFilter.value)
  if (sort.value === 'old') list.reverse()
  else if (sort.value === 'az') list.sort((a, b) => a.title.localeCompare(b.title))
  return list
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage.value)))
const paged = computed(() => filtered.value.slice((page.value - 1) * perPage.value, page.value * perPage.value))
const rangeText = computed(() => {
  const n = filtered.value.length
  if (!n) return '0 - 0 of 0'
  const start = (page.value - 1) * perPage.value + 1
  return `${start} - ${Math.min(page.value * perPage.value, n)} of ${n}`
})
watch([search, categoryFilter, sort, perPage], () => { page.value = 1 })
function clip(text: string | null, n = 60) {
  if (!text) return 'NA'
  return text.length > n ? text.slice(0, n) + '…' : text
}

// ── drawer / draft ─────────────────────────────────────────────────────
const drawerOpen = ref(false)
const editingGroup = ref<string | null>(null)
const saving = ref(false)
const newOption = (): Option => ({ name: '', unit: '', rate: '' })
const draft = reactive<{
  category_id: number | ''
  currency: string
  tax: number | string
  description: string
  dynamic_pricing: boolean
  rate_conditions: RateCondition[]
  enable_discount: boolean
  discount: number | string
  discount_type: 'fixed' | 'percentage'
  discount_start_date: string
  discount_end_date: string
  is_active: boolean
  options: Option[]
}>({
  category_id: '', currency: 'USD', tax: '', description: '',
  dynamic_pricing: false, rate_conditions: [],
  enable_discount: false, discount: '', discount_type: 'fixed',
  discount_start_date: '', discount_end_date: '',
  is_active: true, options: [newOption()],
})

function resetDraft() {
  Object.assign(draft, {
    category_id: '', currency: 'USD', tax: '', description: '',
    dynamic_pricing: false, rate_conditions: [],
    enable_discount: false, discount: '', discount_type: 'fixed',
    discount_start_date: '', discount_end_date: '',
    is_active: true, options: [newOption()],
  })
}
function openAdd() {
  editingGroup.value = null
  resetDraft()
  catPanelOpen.value = false
  drawerOpen.value = true
}
function openEdit(s: Service) {
  editingGroup.value = s.group_uuid
  Object.assign(draft, {
    category_id: s.category.id,
    currency: s.currency,
    tax: s.tax || '',
    description: s.description || '',
    dynamic_pricing: s.dynamic_pricing,
    rate_conditions: s.rate_conditions?.length ? JSON.parse(JSON.stringify(s.rate_conditions)) : [],
    enable_discount: s.enable_discount,
    discount: s.discount || '',
    discount_type: s.discount_type || 'fixed',
    discount_start_date: s.discount_start_date || '',
    discount_end_date: s.discount_end_date || '',
    is_active: s.is_active,
    options: s.options.map(o => ({ id: o.id, name: o.name, unit: o.unit || '', rate: o.rate, image: o.image })),
  })
  if (!draft.options.length) draft.options.push(newOption())
  catPanelOpen.value = false
  drawerOpen.value = true
}
function addOption() { draft.options.push(newOption()) }
function removeOption(i: number) { draft.options.splice(i, 1); if (!draft.options.length) draft.options.push(newOption()) }
function addCondition() { draft.rate_conditions.push({ from_date: '', to_date: '', rate: '' }) }
function removeCondition(i: number) { draft.rate_conditions.splice(i, 1) }

const canSave = computed(() =>
  draft.category_id !== '' &&
  draft.options.some(o => o.name.trim() && o.rate !== '' && Number(o.rate) >= 0),
)

async function save() {
  if (!canSave.value || saving.value) return
  saving.value = true
  try {
    const body = {
      category_id: draft.category_id,
      currency: draft.currency,
      description: draft.description || null,
      tax: draft.tax === '' ? 0 : Number(draft.tax),
      dynamic_pricing: draft.dynamic_pricing,
      rate_conditions: draft.dynamic_pricing
        ? draft.rate_conditions.filter(c => c.from_date && c.to_date && c.rate !== '')
          .map(c => ({ ...c, rate: Number(c.rate) }))
        : [],
      enable_discount: draft.enable_discount,
      discount: draft.enable_discount && draft.discount !== '' ? Number(draft.discount) : 0,
      discount_type: draft.discount_type,
      discount_start_date: draft.enable_discount ? (draft.discount_start_date || null) : null,
      discount_end_date: draft.enable_discount ? (draft.discount_end_date || null) : null,
      is_active: draft.is_active,
      options: draft.options
        .filter(o => o.name.trim() && o.rate !== '')
        .map(o => ({ id: o.id, name: o.name.trim(), unit: o.unit?.trim() || null, rate: Number(o.rate), image: o.image || null })),
    }
    if (editingGroup.value) {
      await api(`/services/${editingGroup.value}`, { method: 'PUT', body })
    } else {
      await api(`/events/${id}/services`, { method: 'POST', body })
    }
    drawerOpen.value = false
    await loadServices()
  } finally {
    saving.value = false
  }
}
async function removeService(s: Service) {
  if (!confirm(`Delete service "${s.title}"${s.more_count ? ` and ${s.more_count} more option(s)` : ''}?`)) return
  await api(`/services/${s.group_uuid}`, { method: 'DELETE' })
  await loadServices()
}

// ── inline category management (the Category dropdown panel) ────────────
const catPanelOpen = ref(false)
const newCatName = ref('')
const editingCatId = ref<number | null>(null)
const editingCatName = ref('')
const catBusy = ref(false)

function selectCategory(c: Category) { draft.category_id = c.id; catPanelOpen.value = false }
const selectedCategoryName = computed(() =>
  categories.value.find(c => c.id === draft.category_id)?.name || '',
)
async function addCategory() {
  const name = newCatName.value.trim()
  if (!name || catBusy.value) return
  catBusy.value = true
  try {
    const created = (await api<{ data: Category }>(`/events/${id}/service-categories`, { method: 'POST', body: { name } })).data
    categories.value.push(created)
    newCatName.value = ''
    draft.category_id = created.id
  } finally {
    catBusy.value = false
  }
}
function startEditCat(c: Category) { editingCatId.value = c.id; editingCatName.value = c.name }
async function saveEditCat() {
  const name = editingCatName.value.trim()
  if (!name || editingCatId.value === null) { editingCatId.value = null; return }
  const cid = editingCatId.value
  const updated = (await api<{ data: Category }>(`/service-categories/${cid}`, { method: 'PATCH', body: { name } })).data
  const idx = categories.value.findIndex(c => c.id === cid)
  if (idx >= 0) categories.value[idx] = updated
  editingCatId.value = null
  await loadServices() // category name may appear in the table
}
async function deleteCategory(c: Category) {
  if (!confirm(`Delete category "${c.name}"? Services in it will be removed.`)) return
  await api(`/service-categories/${c.id}`, { method: 'DELETE' })
  categories.value = categories.value.filter(x => x.id !== c.id)
  if (draft.category_id === c.id) draft.category_id = ''
  await loadServices()
}

onMounted(async () => { await Promise.all([loadCategories(), loadServices()]) })
</script>

<template>
  <div>
    <!-- header -->
    <div class="flex items-start justify-between gap-4 mb-4">
      <div>
        <h2 class="section-title m-0">Services</h2>
        <p class="muted text-[.86rem] mt-0.5 mb-0">Manage your event services</p>
      </div>
      <button class="btn" @click="openAdd"><AppIcon name="plus" class="w-[15px] h-[15px]" /> ADD SERVICES</button>
    </div>

    <div class="card">
      <!-- toolbar -->
      <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="search flex-1 min-w-[220px] max-w-[420px]">
          <AppIcon name="search" />
          <input v-model="search" placeholder="Search services...">
        </div>
        <select v-model="categoryFilter" class="w-auto m-0 min-w-[170px]">
          <option value="">All Category</option>
          <option v-for="c in categories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
        </select>
        <select v-model="sort" class="w-auto m-0 min-w-[150px]">
          <option value="new">Newest First</option>
          <option value="old">Oldest First</option>
          <option value="az">Title A–Z</option>
        </select>
      </div>

      <!-- table -->
      <table>
        <thead>
          <tr>
            <th>CATEGORY</th><th>TITLE</th><th>UNIT</th><th>RATE</th><th>DESCRIPTION</th>
            <th class="text-right">ACTIONS</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in paged" :key="s.group_uuid" class="align-middle">
            <td class="text-[#6352e7] font-medium">{{ s.category.name }}</td>
            <td>
              <span class="font-medium">{{ s.title }}</span>
              <span v-if="s.more_count" class="muted text-[.8rem] ml-1">(+{{ s.more_count }} more)</span>
              <span v-if="!s.is_active" class="ml-2 text-[.68rem] uppercase tracking-wide text-[#b45309] bg-[#fef3c7] px-1.5 py-0.5 rounded">inactive</span>
            </td>
            <td class="muted">{{ s.unit || '—' }}</td>
            <td class="whitespace-nowrap">{{ money(s.currency, s.rate) }}</td>
            <td class="muted">{{ clip(s.description) }}</td>
            <td class="text-right whitespace-nowrap">
              <button class="bg-transparent border-0 cursor-pointer text-base px-2 py-1 text-[#6352e7]" title="Edit" @click="openEdit(s)">✎</button>
              <button class="bg-transparent border-0 cursor-pointer text-base px-2 py-1 text-[#dc2626]" title="Delete" @click="removeService(s)">🗑</button>
            </td>
          </tr>
          <tr v-if="!loading && !paged.length">
            <td colspan="6" class="muted text-center py-7">
              No services yet. Click <strong>+ ADD SERVICES</strong> to create one.
            </td>
          </tr>
          <tr v-if="loading">
            <td colspan="6" class="muted text-center py-7">Loading…</td>
          </tr>
        </tbody>
      </table>

      <!-- pagination -->
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

    <!-- Add / Edit drawer -->
    <Drawer v-if="drawerOpen" :title="editingGroup ? 'Edit Service' : 'Add Service'" @close="drawerOpen = false">
      <!-- Category -->
      <label>Category <span class="text-[#dc2626]">*</span></label>
      <div class="relative">
        <button
          type="button"
          class="w-full text-left flex items-center justify-between gap-2 py-2.5 px-3 border rounded-lg bg-white cursor-pointer"
          :class="draft.category_id === '' ? 'border-[#dc2626]' : 'border-line'"
          @click="catPanelOpen = !catPanelOpen"
        >
          <span :class="draft.category_id === '' ? 'muted' : ''">{{ selectedCategoryName || 'Select Category' }}</span>
          <span class="muted text-xs">▾</span>
        </button>

        <div v-if="catPanelOpen" class="mt-1.5 border border-line rounded-xl p-3 bg-white shadow-sm">
          <div class="flex gap-2 mb-2">
            <input v-model="newCatName" placeholder="Enter Category Name" class="m-0" @keyup.enter="addCategory">
            <button class="btn ghost sm shrink-0" :disabled="!newCatName.trim() || catBusy" @click="addCategory">ADD</button>
          </div>
          <div class="max-h-[200px] overflow-y-auto">
            <div v-for="c in categories" :key="c.id" class="flex items-center gap-2 py-2 border-b border-line last:border-0">
              <template v-if="editingCatId === c.id">
                <input v-model="editingCatName" class="m-0" @keyup.enter="saveEditCat">
                <button class="btn sm shrink-0" @click="saveEditCat">Save</button>
                <button class="btn ghost sm shrink-0" @click="editingCatId = null">✕</button>
              </template>
              <template v-else>
                <button type="button" class="flex-1 text-left bg-transparent border-0 cursor-pointer p-0 font-medium" @click="selectCategory(c)">
                  {{ c.name }}
                </button>
                <button class="bg-transparent border-0 cursor-pointer text-[#6352e7] px-1" title="Rename" @click="startEditCat(c)">✎</button>
                <button class="bg-transparent border-0 cursor-pointer text-[#dc2626] px-1" title="Delete" @click="deleteCategory(c)">🗑</button>
              </template>
            </div>
            <div v-if="!categories.length" class="muted text-center text-[.84rem] py-3">No categories yet.</div>
          </div>
        </div>
      </div>

      <!-- Currency -->
      <label class="mt-4">Currency <span class="text-[#dc2626]">*</span></label>
      <select v-model="draft.currency" class="m-0">
        <option v-for="c in CURRENCIES" :key="c.code" :value="c.code">{{ c.label }} ({{ c.code }})</option>
      </select>

      <!-- Service Options -->
      <div class="flex items-center justify-between mt-4">
        <label class="m-0">Service Options <span class="text-[#dc2626]">*</span></label>
        <button class="bg-transparent border-0 text-[#6352e7] font-bold text-[.82rem] cursor-pointer tracking-[.02em]" @click="addOption">+ ADD SERVICE</button>
      </div>
      <div v-if="!draft.options.length" class="border border-dashed border-line rounded-lg p-4 text-center muted text-[.84rem]">
        No options added yet. Click "Add Service" to create service variations.
      </div>
      <div v-for="(o, i) in draft.options" :key="i" class="border border-line rounded-xl p-3 mt-2">
        <div class="flex items-center justify-between mb-1">
          <span class="text-[.78rem] muted font-semibold">Option #{{ i + 1 }}</span>
          <button class="bg-transparent border-0 cursor-pointer text-[#dc2626] text-sm" title="Remove option" @click="removeOption(i)">🗑</button>
        </div>
        <input v-model="o.name" placeholder="Service name (e.g. 32 amp single phase)" class="m-0 mb-2">
        <div class="flex gap-2">
          <input v-model="o.unit" placeholder="Unit (e.g. amps)" class="m-0">
          <input v-model.number="o.rate" type="number" min="0" step="0.01" placeholder="Rate" class="m-0">
        </div>
      </div>

      <!-- Description -->
      <label class="mt-4">Description</label>
      <textarea v-model="draft.description" rows="3" placeholder="Enter Description" />

      <!-- Dynamic Pricing -->
      <label class="flex items-center justify-between mt-3 cursor-pointer m-0">
        <span>Dynamic Pricing (Based on Booking Date)</span>
        <input v-model="draft.dynamic_pricing" type="checkbox" class="w-4 h-4 m-0 accent-[#6352e7]">
      </label>
      <div v-if="draft.dynamic_pricing" class="border border-line rounded-xl p-3 mt-2">
        <div v-for="(c, ci) in draft.rate_conditions" :key="ci" class="flex items-center gap-2 mb-2">
          <input v-model="c.from_date" type="date" class="m-0" title="From">
          <input v-model="c.to_date" type="date" class="m-0" title="To">
          <input v-model.number="c.rate" type="number" min="0" step="0.01" placeholder="Rate" class="m-0">
          <button class="bg-transparent border-0 cursor-pointer text-[#dc2626]" @click="removeCondition(ci)">🗑</button>
        </div>
        <button class="bg-transparent border-0 text-[#6352e7] font-bold text-[.82rem] cursor-pointer" @click="addCondition">+ ADD RULE</button>
      </div>

      <!-- Tax -->
      <label class="mt-3">Tax (%)</label>
      <input v-model.number="draft.tax" type="number" min="0" step="0.01" placeholder="Enter Tax Percentage" class="m-0">

      <!-- Enable Discount -->
      <div class="bg-[#f7f8fa] border border-line rounded-xl p-3 mt-4">
        <label class="flex items-center justify-between cursor-pointer m-0">
          <span>
            <span class="font-semibold">Enable Discount</span>
            <span class="block muted text-[.8rem]">Offer special pricing for all service options</span>
          </span>
          <input v-model="draft.enable_discount" type="checkbox" class="w-4 h-4 m-0 accent-[#6352e7]">
        </label>
        <div v-if="draft.enable_discount" class="mt-3">
          <div class="flex gap-2">
            <div class="flex-1">
              <label>Discount</label>
              <input v-model.number="draft.discount" type="number" min="0" step="0.01" placeholder="0" class="m-0">
            </div>
            <div class="flex-1">
              <label>Type</label>
              <select v-model="draft.discount_type" class="m-0">
                <option value="fixed">Fixed</option>
                <option value="percentage">Percentage</option>
              </select>
            </div>
          </div>
          <div class="flex gap-2 mt-2">
            <div class="flex-1">
              <label>Start Date</label>
              <input v-model="draft.discount_start_date" type="date" class="m-0">
            </div>
            <div class="flex-1">
              <label>End Date</label>
              <input v-model="draft.discount_end_date" type="date" class="m-0">
            </div>
          </div>
        </div>
      </div>

      <!-- Is Active -->
      <label class="flex items-center gap-2 mt-4 cursor-pointer m-0">
        <input v-model="draft.is_active" type="checkbox" class="w-4 h-4 m-0 accent-[#6352e7]">
        <span>
          <span class="font-semibold">Is Active</span>
          <span class="block muted text-[.8rem]">Make all service options available for booking</span>
        </span>
      </label>

      <div class="modal-actions border-t border-line pt-4 mt-5">
        <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
        <button class="btn" :disabled="!canSave || saving" @click="save">
          {{ saving ? 'Saving…' : (editingGroup ? 'UPDATE SERVICE' : 'ADD SERVICE') }}
        </button>
      </div>
    </Drawer>
  </div>
</template>
