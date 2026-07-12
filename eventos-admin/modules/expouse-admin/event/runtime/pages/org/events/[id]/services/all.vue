<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'

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

const categoryOptions = computed(() => [
  { label: 'All Category', value: '' },
  ...categories.value.map(c => ({ label: c.name, value: String(c.id) })),
])

const columns = [
  { key: 'category', label: 'Category' },
  { key: 'title', label: 'Title', sortable: true },
  { key: 'unit', label: 'Unit' },
  { key: 'rate', label: 'Rate', sortable: true },
  { key: 'description', label: 'Description' },
]
function searchText(s: Service) {
  return `${s.category.name} ${s.options.map(o => o.name).join(' ')} ${s.description || ''}`
}
function rowFilter(s: Service) {
  return !categoryFilter.value || String(s.category.id) === categoryFilter.value
}

async function loadCategories() {
  categories.value = (await api<{ data: Category[] }>(`/events/${id}/service-categories`)).data
}
async function loadServices() {
  loading.value = true
  try {
    services.value = (await api<{ data: Service[] }>(`/events/${id}/services`)).data
  } catch {
    toast.error('Could not load services.')
  } finally {
    loading.value = false
  }
}

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
    toast.success(editingGroup.value ? 'Service updated' : 'Service added')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not save service.')
  } finally {
    saving.value = false
  }
}
async function removeService(s: Service) {
  if (!confirm(`Delete service "${s.title}"${s.more_count ? ` and ${s.more_count} more option(s)` : ''}?`)) return
  try {
    await api(`/services/${s.group_uuid}`, { method: 'DELETE' })
    await loadServices()
    toast.success('Service deleted')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not delete service.')
  }
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
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not add category.')
  } finally {
    catBusy.value = false
  }
}
function startEditCat(c: Category) { editingCatId.value = c.id; editingCatName.value = c.name }
async function saveEditCat() {
  const name = editingCatName.value.trim()
  if (!name || editingCatId.value === null) { editingCatId.value = null; return }
  const cid = editingCatId.value
  try {
    const updated = (await api<{ data: Category }>(`/service-categories/${cid}`, { method: 'PATCH', body: { name } })).data
    const idx = categories.value.findIndex(c => c.id === cid)
    if (idx >= 0) categories.value[idx] = updated
    editingCatId.value = null
    await loadServices() // category name may appear in the table
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not rename category.')
  }
}
async function deleteCategory(c: Category) {
  if (!confirm(`Delete category "${c.name}"? Services in it will be removed.`)) return
  try {
    await api(`/service-categories/${c.id}`, { method: 'DELETE' })
    categories.value = categories.value.filter(x => x.id !== c.id)
    if (draft.category_id === c.id) draft.category_id = ''
    await loadServices()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not delete category.')
  }
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
      <button class="btn" @click="openAdd"><AppIcon name="plus" class="w-3.75 h-3.75" /> ADD SERVICES</button>
    </div>

    <div class="card">
      <!-- toolbar -->
      <div class="flex flex-wrap items-center gap-3 mb-4">
        <SearchInput v-model="search" placeholder="Search services…" class="flex-1 min-w-55 max-w-105" />
        <FilterSelect v-model="categoryFilter" label="Category" :options="categoryOptions" />
      </div>

      <div v-if="loading" class="flex items-center justify-center gap-2.5 py-14 text-muted text-[.88rem]">
        <svg class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
          <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
        </svg>
        Loading services…
      </div>

      <DataTable
        v-else
        :items="services"
        :columns="columns"
        :search="search"
        :search-text="searchText"
        :filter="rowFilter"
        row-key="group_uuid"
        storage-key="services-all"
      >
        <template #cell-category="{ row }">
          <span class="text-brand font-medium">{{ row.category.name }}</span>
        </template>
        <template #cell-title="{ row }">
          <span class="font-medium">{{ row.title }}</span>
          <span v-if="row.more_count" class="muted text-[.8rem] ml-1">(+{{ row.more_count }} more)</span>
          <span v-if="!row.is_active" class="ml-2 text-[.68rem] uppercase tracking-wide text-[#b45309] bg-[#fef3c7] px-1.5 py-0.5 rounded">inactive</span>
        </template>
        <template #cell-unit="{ row }">
          <span class="muted">{{ row.unit || '—' }}</span>
        </template>
        <template #cell-rate="{ row }">
          <span class="whitespace-nowrap">{{ money(row.currency, row.rate) }}</span>
        </template>
        <template #cell-description="{ row }">
          <span class="muted">{{ clip(row.description) }}</span>
        </template>
        <template #actions="{ row }">
          <button class="bg-transparent border-0 cursor-pointer text-base px-2 py-1 text-brand" title="Edit" @click="openEdit(row)">✎</button>
          <button class="bg-transparent border-0 cursor-pointer text-base px-2 py-1 text-[#dc2626]" title="Delete" @click="removeService(row)">🗑</button>
        </template>
        <template #empty>
          <span class="muted">No services yet. Click <strong>+ ADD SERVICES</strong> to create one.</span>
        </template>
      </DataTable>
    </div>

    <!-- Add / Edit drawer -->
    <Drawer v-if="drawerOpen" :title="editingGroup ? 'Edit Service' : 'Add Service'" @close="drawerOpen = false">
      <!-- Category -->
      <FormField label="Category" required>
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
            <div class="flex gap-2 mb-2 items-start">
              <AppInput v-model="newCatName" placeholder="Enter Category Name" class="flex-1" @keyup.enter="addCategory" />
              <button class="btn ghost sm shrink-0" :disabled="!newCatName.trim() || catBusy" @click="addCategory">ADD</button>
            </div>
            <div class="max-h-50 overflow-y-auto">
              <div v-for="c in categories" :key="c.id" class="flex items-center gap-2 py-2 border-b border-line last:border-0">
                <template v-if="editingCatId === c.id">
                  <AppInput v-model="editingCatName" class="flex-1" @keyup.enter="saveEditCat" />
                  <button class="btn sm shrink-0" @click="saveEditCat">Save</button>
                  <button class="btn ghost sm shrink-0" @click="editingCatId = null">✕</button>
                </template>
                <template v-else>
                  <button type="button" class="flex-1 text-left bg-transparent border-0 cursor-pointer p-0 font-medium" @click="selectCategory(c)">
                    {{ c.name }}
                  </button>
                  <button class="bg-transparent border-0 cursor-pointer text-brand px-1" title="Rename" @click="startEditCat(c)">✎</button>
                  <button class="bg-transparent border-0 cursor-pointer text-[#dc2626] px-1" title="Delete" @click="deleteCategory(c)">🗑</button>
                </template>
              </div>
              <div v-if="!categories.length" class="muted text-center text-[.84rem] py-3">No categories yet.</div>
            </div>
          </div>
        </div>
      </FormField>

      <!-- Currency -->
      <div class="mt-4">
        <AppSelect
          v-model="draft.currency"
          label="Currency"
          required
          :options="CURRENCIES.map(c => ({ value: c.code, label: `${c.label} (${c.code})` }))"
        />
      </div>

      <!-- Service Options -->
      <div class="flex items-center justify-between mt-4">
        <label class="m-0">Service Options <span class="text-[#dc2626]">*</span></label>
        <button class="bg-transparent border-0 text-brand font-bold text-[.82rem] cursor-pointer tracking-[.02em]" @click="addOption">+ ADD SERVICE</button>
      </div>
      <div v-if="!draft.options.length" class="border border-dashed border-line rounded-lg p-4 text-center muted text-[.84rem]">
        No options added yet. Click "Add Service" to create service variations.
      </div>
      <div v-for="(o, i) in draft.options" :key="i" class="border border-line rounded-xl p-3 mt-2">
        <div class="flex items-center justify-between mb-1">
          <span class="text-[.78rem] muted font-semibold">Option #{{ i + 1 }}</span>
          <button class="bg-transparent border-0 cursor-pointer text-[#dc2626] text-sm" title="Remove option" @click="removeOption(i)">🗑</button>
        </div>
        <div class="mb-2">
          <AppInput v-model="o.name" placeholder="Service name (e.g. 32 amp single phase)" />
        </div>
        <div class="flex gap-2">
          <AppInput v-model="o.unit" placeholder="Unit (e.g. amps)" class="flex-1" />
          <AppInput v-model.number="o.rate" type="number" min="0" step="0.01" placeholder="Rate" class="flex-1" />
        </div>
      </div>

      <!-- Description -->
      <div class="mt-4">
        <AppTextarea v-model="draft.description" label="Description" :rows="3" placeholder="Enter Description" />
      </div>

      <!-- Dynamic Pricing -->
      <div class="mt-4">
        <AppCheckbox v-model="draft.dynamic_pricing" label="Dynamic Pricing" description="Vary the rate by booking date" />
      </div>
      <div v-if="draft.dynamic_pricing" class="border border-line rounded-xl p-3 mt-2">
        <div v-for="(c, ci) in draft.rate_conditions" :key="ci" class="flex items-center gap-2 mb-2">
          <input v-model="c.from_date" type="date" class="m-0" title="From">
          <input v-model="c.to_date" type="date" class="m-0" title="To">
          <AppInput v-model.number="c.rate" type="number" min="0" step="0.01" placeholder="Rate" class="flex-1" />
          <button class="bg-transparent border-0 cursor-pointer text-[#dc2626]" @click="removeCondition(ci)">🗑</button>
        </div>
        <button class="bg-transparent border-0 text-brand font-bold text-[.82rem] cursor-pointer" @click="addCondition">+ ADD RULE</button>
      </div>

      <!-- Tax -->
      <div class="mt-3">
        <AppInput v-model.number="draft.tax" type="number" label="Tax (%)" min="0" step="0.01" placeholder="Enter Tax Percentage" />
      </div>

      <!-- Enable Discount -->
      <div class="bg-[#f7f8fa] border border-line rounded-xl p-3 mt-4">
        <AppCheckbox v-model="draft.enable_discount" label="Enable Discount" description="Offer special pricing for all service options" />
        <div v-if="draft.enable_discount" class="mt-3">
          <div class="flex gap-2">
            <AppInput v-model.number="draft.discount" type="number" label="Discount" min="0" step="0.01" placeholder="0" class="flex-1" />
            <AppSelect
              v-model="draft.discount_type"
              label="Type"
              class="flex-1"
              :options="[{ value: 'fixed', label: 'Fixed' }, { value: 'percentage', label: 'Percentage' }]"
            />
          </div>
          <div class="flex gap-2 mt-2">
            <FormField label="Start Date" class="flex-1">
              <input v-model="draft.discount_start_date" type="date" class="m-0 w-full">
            </FormField>
            <FormField label="End Date" class="flex-1">
              <input v-model="draft.discount_end_date" type="date" class="m-0 w-full">
            </FormField>
          </div>
        </div>
      </div>

      <!-- Is Active -->
      <div class="mt-4">
        <AppCheckbox v-model="draft.is_active" label="Is Active" description="Make all service options available for booking" />
      </div>

      <div class="modal-actions border-t border-line pt-4 mt-5">
        <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
        <button class="btn" :disabled="!canSave || saving" @click="save">
          {{ saving ? 'Saving…' : (editingGroup ? 'UPDATE SERVICE' : 'ADD SERVICE') }}
        </button>
      </div>
    </Drawer>
  </div>
</template>
