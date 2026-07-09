<script setup lang="ts">
definePageMeta({ middleware: 'exhibitor', title: 'Request Service', subtitle: 'Manage your event services' })

const api = useApi()

interface RequestLine {
  id: string
  name: string
  unit: string | null
  image: string | null
  category: string | null
  unit_price: number
  quantity: number
  total: number
  currency: string
  status: string
}
interface CatalogItem {
  id: number
  name: string
  unit: string | null
  rate: number
  currency: string
  tax: number
  image: string | null
  description: string | null
  category_id: number
  category: string | null
  added: number
}

const rows = ref<RequestLine[]>([])
const meta = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0, from: 0, to: 0 })
const loading = ref(false)
const suspended = ref(false)

const filters = reactive({ search: '', sort: 'recent', page: 1, per_page: 10 })

let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(() => filters.search, () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => { filters.page = 1; load() }, 350) })
watch(() => [filters.sort, filters.per_page], () => { filters.page = 1; load() })
watch(() => filters.page, load)

async function load() {
  loading.value = true
  try {
    const q = new URLSearchParams({ search: filters.search, sort: filters.sort, page: String(filters.page), per_page: String(filters.per_page) })
    const res = await api<any>(`/exhibitor/services/requests?${q}`)
    rows.value = res.data
    meta.value = res.meta
  } catch (e: any) {
    if (e?.response?.status === 403 || e?.status === 403) suspended.value = true
  } finally {
    loading.value = false
  }
}

function money(cur: string, n: number) { return `${cur || ''} ${Number(n).toFixed(2)}`.trim() }

// ── Row actions ───────────────────────────────────────────────────────────────
const openMenu = ref<string | null>(null)
async function removeLine(l: RequestLine) {
  openMenu.value = null
  if (!confirm(`Remove "${l.name}"?`)) return
  await api(`/exhibitor/services/requests/${l.id}`, { method: 'DELETE' })
  await load()
}

// ── Add Services modal ────────────────────────────────────────────────────────
const modalOpen = ref(false)
const step = ref<1 | 2>(1)
const catalog = ref<CatalogItem[]>([])
const categories = ref<{ id: number, name: string }[]>([])
const basket = reactive<Record<number, number>>({})
const original = reactive<Record<number, number>>({})
const modalSearch = ref('')
const modalCategory = ref<number | ''>('')
const submitting = ref(false)

const filteredCatalog = computed(() => catalog.value.filter((i) => {
  if (modalCategory.value && i.category_id !== modalCategory.value) return false
  if (modalSearch.value && !i.name.toLowerCase().includes(modalSearch.value.toLowerCase())) return false
  return true
}))
const basketLines = computed(() => catalog.value.filter(i => (basket[i.id] ?? 0) > 0))
const basketTotal = computed(() => basketLines.value.reduce((s, i) => s + i.rate * basket[i.id], 0))
const basketCurrency = computed(() => basketLines.value[0]?.currency || '')
const hasChanges = computed(() => catalog.value.some(i => (basket[i.id] ?? 0) !== (original[i.id] ?? 0)))

async function openModal() {
  modalOpen.value = true
  step.value = 1
  modalSearch.value = ''
  modalCategory.value = ''
  try {
    const [cat, cats] = await Promise.all([
      api<any>('/exhibitor/services/catalog'),
      api<any>('/exhibitor/services/categories'),
    ])
    catalog.value = cat.data
    categories.value = cats.data
    for (const i of catalog.value) { basket[i.id] = i.added; original[i.id] = i.added }
  } catch { /* */ }
}
function closeModal() { modalOpen.value = false }
function inc(i: CatalogItem) { basket[i.id] = (basket[i.id] ?? 0) + 1 }
function dec(i: CatalogItem) { basket[i.id] = Math.max(0, (basket[i.id] ?? 0) - 1) }

async function submit() {
  const items = catalog.value
    .filter(i => (basket[i.id] ?? 0) !== (original[i.id] ?? 0) || (basket[i.id] ?? 0) > 0)
    .map(i => ({ service_item_id: i.id, quantity: basket[i.id] ?? 0 }))
  if (!items.length) return
  submitting.value = true
  try {
    await api('/exhibitor/services/requests', { method: 'POST', body: { items } })
    modalOpen.value = false
    filters.page = 1
    await load()
  } finally {
    submitting.value = false
  }
}

onMounted(load)
</script>

<template>
  <div v-if="suspended" class="card"><p class="error">This exhibitor account is suspended.</p></div>

  <div v-else>
    <div class="card p-0 overflow-hidden">
      <!-- Toolbar -->
      <div class="flex items-center gap-2.5 p-4 flex-wrap">
        <div class="relative flex-1 min-w-[220px]">
          <AppIcon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
          <input v-model="filters.search" placeholder="Search" class="!pl-9 !m-0 w-full">
        </div>
        <div class="grow max-sm:hidden" />
        <select v-model="filters.sort" class="!m-0 !w-auto">
          <option value="recent">Newest First</option>
          <option value="oldest">Oldest First</option>
        </select>
        <button class="btn" @click="openModal">Add Services</button>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="svc-table">
          <thead>
            <tr>
              <th>Services</th><th>Unit</th><th>Price</th><th>Quantity</th><th>Total</th><th>Status</th><th class="w-12 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading && !rows.length"><td colspan="7" class="py-10 text-center muted">Loading…</td></tr>
            <tr v-else-if="!rows.length"><td colspan="7" class="py-12 text-center muted">No services requested yet. Click <strong>Add Services</strong> to get started.</td></tr>
            <tr v-for="l in rows" :key="l.id">
              <td><strong class="text-ink">{{ l.name }}</strong></td>
              <td class="text-brand">{{ l.unit || '—' }}</td>
              <td>{{ money(l.currency, l.unit_price) }}</td>
              <td>{{ l.quantity }}</td>
              <td><strong class="text-ink">{{ money(l.currency, l.total) }}</strong></td>
              <td><span class="pill" :class="`pill-${l.status}`">{{ l.status }}</span></td>
              <td class="text-right relative">
                <button class="icon-btn" @click="openMenu = openMenu === l.id ? null : l.id">⋮</button>
                <div v-if="openMenu === l.id" class="menu" @mouseleave="openMenu = null">
                  <button class="menu-item danger" @click="removeLine(l)">Remove</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex items-center justify-end gap-4 p-4 border-t border-line text-[.84rem] text-muted flex-wrap">
        <label class="flex items-center gap-2">Nb / page
          <select v-model.number="filters.per_page" class="!m-0 !w-auto !py-1.5">
            <option :value="10">10</option><option :value="25">25</option><option :value="50">50</option>
          </select>
        </label>
        <label class="flex items-center gap-2">Page
          <select v-model.number="filters.page" class="!m-0 !w-auto !py-1.5">
            <option v-for="p in meta.last_page" :key="p" :value="p">{{ p }}</option>
          </select>
        </label>
        <span>{{ meta.total ? `${meta.from} - ${meta.to} of ${meta.total}` : '0 of 0' }}</span>
        <div class="flex items-center gap-1">
          <button class="page-btn" :disabled="meta.current_page <= 1" @click="filters.page--">‹</button>
          <button class="page-btn" :disabled="meta.current_page >= meta.last_page" @click="filters.page++">›</button>
        </div>
      </div>
    </div>

    <!-- ── Add Services modal ── -->
    <div v-if="modalOpen" class="overlay" @click.self="closeModal">
      <div class="drawer">
        <div class="flex items-center justify-between px-5 py-4 border-b border-line">
          <h2 class="!m-0 text-[1.15rem]">Request Service</h2>
          <button class="icon-btn" @click="closeModal">✕</button>
        </div>

        <!-- Step 1: pick -->
        <template v-if="step === 1">
          <div class="flex gap-2.5 px-5 py-4">
            <div class="relative flex-1">
              <AppIcon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
              <input v-model="modalSearch" placeholder="Search" class="!pl-9 !m-0 w-full">
            </div>
            <select v-model="modalCategory" class="!m-0 !w-auto">
              <option value="">All Categories</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>

          <div class="flex-1 overflow-y-auto px-5 pb-4 flex flex-col gap-3">
            <p v-if="!filteredCatalog.length" class="muted text-center py-8">No services available.</p>
            <div v-for="i in filteredCatalog" :key="i.id" class="svc-card">
              <div class="thumb">
                <img v-if="i.image" :src="i.image" :alt="i.name">
                <AppIcon v-else name="briefcase" class="w-6 h-6 text-muted" />
                <span v-if="original[i.id] > 0" class="added">ADDED: {{ original[i.id] }}</span>
              </div>
              <div class="min-w-0 flex-1">
                <div class="font-bold text-ink">{{ i.name }}</div>
                <p v-if="i.description" class="muted text-[.83rem] mt-0.5 line-clamp-2">{{ i.description }}</p>
                <div class="text-brand font-bold mt-1.5">{{ money(i.currency, i.rate) }}</div>
                <div class="text-faint text-[.76rem]">Excluding VAT</div>
              </div>
              <div class="stepper">
                <button @click="dec(i)">−</button>
                <span>{{ basket[i.id] ?? 0 }}</span>
                <button @click="inc(i)">+</button>
              </div>
            </div>
          </div>

          <div class="px-5 py-4 border-t border-line">
            <button class="btn w-full py-3" :disabled="!hasChanges" @click="step = 2">Next Step</button>
          </div>
        </template>

        <!-- Step 2: review -->
        <template v-else>
          <div class="flex-1 overflow-y-auto px-5 py-4">
            <button class="text-brand text-[.86rem] font-semibold mb-3" @click="step = 1">‹ Back to services</button>
            <p v-if="!basketLines.length" class="muted text-center py-8">Nothing selected.</p>
            <div v-else class="flex flex-col gap-2">
              <div v-for="i in basketLines" :key="i.id" class="flex items-center justify-between gap-3 px-3.5 py-3 rounded-lg border border-line">
                <div class="min-w-0">
                  <div class="font-semibold text-ink text-[.9rem] truncate">{{ i.name }}</div>
                  <div class="muted text-[.8rem]">{{ money(i.currency, i.rate) }} × {{ basket[i.id] }}</div>
                </div>
                <strong class="text-ink shrink-0">{{ money(i.currency, i.rate * basket[i.id]) }}</strong>
              </div>
              <div class="flex items-center justify-between pt-3 mt-1 border-t border-line">
                <span class="font-semibold text-ink">Total (excl. VAT)</span>
                <strong class="text-brand text-lg">{{ money(basketCurrency, basketTotal) }}</strong>
              </div>
            </div>
          </div>

          <div class="px-5 py-4 border-t border-line flex gap-2">
            <button class="btn ghost flex-1 py-3" @click="step = 1">Back</button>
            <button class="btn flex-1 py-3" :disabled="submitting || !hasChanges" @click="submit">{{ submitting ? 'Submitting…' : 'Submit Request' }}</button>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.svc-table { width: 100%; border-collapse: collapse; }
.svc-table th { text-align: left; font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); padding: 12px 16px; border-bottom: 1px solid var(--line); background: #fafbfc; }
.svc-table td { padding: 14px 16px; border-bottom: 1px solid var(--line); vertical-align: middle; }
.svc-table tbody tr:hover { background: #fafbfc; }

.pill { display: inline-block; padding: 4px 12px; font-size: .76rem; font-weight: 600; border-radius: 9999px; text-transform: capitalize; }
.pill-pending { background: #fef3c7; color: #b45309; }
.pill-approved { background: #dcfce7; color: #15803d; }
.pill-rejected { background: #fee2e2; color: #b91c1c; }

.icon-btn { width: 32px; height: 32px; display: inline-grid; place-items: center; border-radius: 8px; color: var(--muted); background: transparent; border: 0; cursor: pointer; font-size: 1.1rem; }
.icon-btn:hover { background: #f6f7f9; color: var(--ink); }
.page-btn { width: 28px; height: 28px; display: grid; place-items: center; border-radius: 6px; border: 1px solid var(--line); background: transparent; cursor: pointer; }
.page-btn:hover:not(:disabled) { background: #f6f7f9; }
.page-btn:disabled { opacity: .4; cursor: default; }

.menu { position: absolute; right: 12px; top: 42px; z-index: 20; background: #fff; border: 1px solid var(--line); border-radius: 10px; box-shadow: 0 12px 30px rgba(15,23,42,.14); overflow: hidden; min-width: 140px; }
.menu-item { display: block; width: 100%; text-align: left; padding: 10px 14px; font-size: .86rem; background: transparent; border: 0; cursor: pointer; }
.menu-item:hover { background: #f6f7f9; }
.menu-item.danger { color: #b91c1c; }

/* Modal */
.overlay { position: fixed; inset: 0; background: rgba(15,23,42,.45); display: flex; justify-content: flex-end; z-index: 80; }
.drawer { width: 100%; max-width: 460px; height: 100%; background: var(--bg); display: flex; flex-direction: column; box-shadow: -20px 0 50px rgba(15,23,42,.2); }

.svc-card { display: flex; gap: 14px; align-items: flex-start; background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 14px; }
.thumb { position: relative; width: 84px; height: 84px; border-radius: 10px; overflow: hidden; background: #f1f5f9; display: grid; place-items: center; flex-shrink: 0; }
.thumb img { width: 100%; height: 100%; object-fit: cover; }
.added { position: absolute; left: 0; bottom: 0; background: rgba(15,23,42,.72); color: #fff; font-size: .62rem; font-weight: 700; padding: 2px 6px; border-top-right-radius: 6px; }

.stepper { display: flex; align-items: center; border: 1px solid var(--line); border-radius: 10px; overflow: hidden; flex-shrink: 0; align-self: center; }
.stepper button { width: 34px; height: 34px; border: 0; background: var(--brand-soft); color: var(--brand-dark); font-size: 1.05rem; cursor: pointer; }
.stepper button:hover { filter: brightness(.97); }
.stepper span { width: 40px; text-align: center; font-weight: 700; font-size: .92rem; }

.line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>
