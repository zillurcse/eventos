<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

// ── types ──────────────────────────────────────────────────────────────
type Status = 'pending' | 'partial' | 'approved' | 'rejected'
type LineStatus = 'pending' | 'approved' | 'rejected'

interface Order {
  id: string
  order_number: string
  date: string | null
  status: Status
  exhibitor: { id: string | null, name: string | null }
  lines_count: number
  counts: { pending: number, approved: number, rejected: number }
  currency: string
  subtotal: number
  tax_total: number
  total: number
}
interface OrderItem {
  id: string
  name: string
  description: string | null
  image: string | null
  unit: string | null
  category: string | null
  quantity: number
  unit_price: number
  line_total: number
  tax: number
  currency: string
  status: LineStatus
}
interface OrderDetail extends Order { items: OrderItem[] }
interface Stats { total: number, pending: number, partial: number, approved: number, rejected: number }

const STATUSES: Status[] = ['pending', 'partial', 'approved', 'rejected']

const statusPillClass: Record<Status, string> = {
  pending: 'bg-[#fef3c7] text-[#b45309]',
  partial: 'bg-[#dbeafe] text-[#1d4ed8]',
  approved: 'bg-[#dcfce7] text-[#15803d]',
  rejected: 'bg-[#fee2e2] text-[#b91c1c]',
}
const statusTextClass: Record<Status, string> = {
  pending: 'text-[#b45309]',
  partial: 'text-[#1d4ed8]',
  approved: 'text-[#15803d]',
  rejected: 'text-[#dc2626]',
}
function pillClass(status: string) {
  return statusPillClass[status as Status]
}

// ── state ──────────────────────────────────────────────────────────────
const orders = ref<Order[]>([])
const stats = ref<Stats>({ total: 0, pending: 0, partial: 0, approved: 0, rejected: 0 })
const exhibitors = ref<{ id: string, name: string }[]>([])
const categories = ref<{ id: number, name: string }[]>([])
const loading = ref(true)

const statusTab = ref<'all' | Status>('all')
const view = ref<'grouped' | 'list'>('grouped')
const search = ref('')
const exhibitorFilter = ref('')
const categoryFilter = ref('')
const groupPage = ref(1)
const groupPerPage = ref(10)
const expanded = ref<string | null>(null)

const exhibitorOptions = computed(() => [
  { label: 'All Exhibitor', value: '' },
  ...exhibitors.value.map(e => ({ label: e.name, value: e.id })),
])
const categoryOptions = computed(() => [
  { label: 'All Category', value: '' },
  ...categories.value.map(c => ({ label: c.name, value: String(c.id) })),
])

async function load() {
  loading.value = true
  try {
    const query = new URLSearchParams()
    if (search.value.trim()) query.set('search', search.value.trim())
    if (exhibitorFilter.value) query.set('exhibitor', exhibitorFilter.value)
    if (categoryFilter.value) query.set('category', categoryFilter.value)

    const res = await api<{ data: Order[], stats: Stats, exhibitors: any[], categories: any[] }>(
      `/events/${id}/service-orders?${query}`,
    )
    orders.value = res.data
    stats.value = res.stats
    exhibitors.value = res.exhibitors
    categories.value = res.categories
  } finally {
    loading.value = false
  }
}

let searchTimer: ReturnType<typeof setTimeout>
watch(search, () => { clearTimeout(searchTimer); searchTimer = setTimeout(load, 350) })
watch([exhibitorFilter, categoryFilter], load)
watch([statusTab, view, groupPerPage], () => { groupPage.value = 1; expanded.value = null })

// ── derivation ─────────────────────────────────────────────────────────
const visibleOrders = computed(() =>
  statusTab.value === 'all' ? orders.value : orders.value.filter(o => o.status === statusTab.value),
)

/** Grouped view: one row per exhibitor, holding that booth's orders. */
interface Group { name: string, orders: Order[], total: number, currency: string, summary: { status: Status, count: number }[] }
const groups = computed<Group[]>(() => {
  const byExhibitor = new Map<string, Order[]>()
  for (const order of visibleOrders.value) {
    const key = order.exhibitor.name || '—'
    const bucket = byExhibitor.get(key)
    bucket ? bucket.push(order) : byExhibitor.set(key, [order])
  }

  return [...byExhibitor.entries()].map(([name, list]) => ({
    name,
    orders: list,
    total: list.reduce((sum, o) => sum + o.total, 0),
    currency: list[0]?.currency || '',
    summary: STATUSES
      .map(status => ({ status, count: list.filter(o => o.status === status).length }))
      .filter(s => s.count > 0),
  })).sort((a, b) => a.name.localeCompare(b.name))
})

const groupTotalPages = computed(() => Math.max(1, Math.ceil(groups.value.length / groupPerPage.value)))
const pagedGroups = computed(() => groups.value.slice((groupPage.value - 1) * groupPerPage.value, groupPage.value * groupPerPage.value))
const groupRangeText = computed(() => {
  const n = groups.value.length
  if (!n) return '0 - 0 of 0'
  return `${(groupPage.value - 1) * groupPerPage.value + 1} - ${Math.min(groupPage.value * groupPerPage.value, n)} of ${n}`
})

const listColumns = [
  { key: 'order_number', label: 'Order ID' },
  { key: 'exhibitor', label: 'Exhibitor' },
  { key: 'date', label: 'Date', sortable: true },
  { key: 'lines_count', label: 'Items' },
  { key: 'status', label: 'Status' },
  { key: 'total', label: 'Total Amount', sortable: true },
]

// ── formatting ─────────────────────────────────────────────────────────
function money(currency: string, amount: number) {
  return `${currency || ''} ${Number(amount).toFixed(2)}`.trim()
}
function percent(count: number) {
  if (!stats.value.total) return '0%'
  const pct = (count / stats.value.total) * 100
  return pct === 0 || pct === 100 ? `${pct}%` : `${pct.toFixed(2)}%`
}
function formatDate(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
/** Catalogue descriptions are rich text; the drawer wants a plain-text preview. */
function plainText(html: string | null, max = 140) {
  if (!html) return ''
  const text = html.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim()
  return text.length > max ? `${text.slice(0, max)}…` : text
}

// ── detail drawer ──────────────────────────────────────────────────────
const detail = ref<OrderDetail | null>(null)
const detailLoading = ref(false)
const busyLine = ref<string | null>(null)
const busyOrder = ref(false)
const downloading = ref(false)

async function openOrder(order: Order) {
  detailLoading.value = true
  detail.value = null
  try {
    detail.value = (await api<{ data: OrderDetail }>(`/service-orders/${order.id}`)).data
  } finally {
    detailLoading.value = false
  }
}

/** Approve/reject a line, then fold the fresh order back into the table. */
async function setLineStatus(item: OrderItem, status: LineStatus) {
  if (busyLine.value) return
  busyLine.value = item.id
  try {
    const updated = (await api<{ data: OrderDetail }>(`/service-requests/${item.id}`, {
      method: 'PATCH',
      body: { status },
    })).data
    detail.value = updated
    syncRow(updated)
  } finally {
    busyLine.value = null
  }
}

async function setOrderStatus(status: LineStatus) {
  if (!detail.value || busyOrder.value) return
  busyOrder.value = true
  try {
    const updated = (await api<{ data: OrderDetail }>(`/service-orders/${detail.value.id}`, {
      method: 'PATCH',
      body: { status },
    })).data
    detail.value = updated
    syncRow(updated)
  } finally {
    busyOrder.value = false
  }
}

/** Patch the table row in place and recompute the stat cards, no refetch. */
function syncRow(updated: OrderDetail) {
  const index = orders.value.findIndex(o => o.id === updated.id)
  if (index < 0) return
  const row = { ...updated } as Partial<OrderDetail>
  delete row.items
  orders.value[index] = row as Order
  stats.value = {
    total: orders.value.length,
    pending: orders.value.filter(o => o.status === 'pending').length,
    partial: orders.value.filter(o => o.status === 'partial').length,
    approved: orders.value.filter(o => o.status === 'approved').length,
    rejected: orders.value.filter(o => o.status === 'rejected').length,
  }
}

async function downloadPdf() {
  if (!detail.value || downloading.value) return
  downloading.value = true
  try {
    const blob = await api<Blob>(`/service-orders/${detail.value.id}/pdf`, { responseType: 'blob' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `${detail.value.order_number}.pdf`
    link.click()
    URL.revokeObjectURL(url)
  } finally {
    downloading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <!-- header -->
    <div class="mb-4">
      <h2 class="section-title m-0">Requested Services</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">View and manage service requests from exhibitors</p>
    </div>

    <!-- stat cards -->
    <div class="grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-4 mb-5">
      <div class="card !p-5">
        <div class="flex items-baseline gap-2">
          <span class="font-bold text-[1.02rem]">Total</span>
          <span class="muted text-[.82rem]">({{ stats.total ? '100%' : '0%' }})</span>
        </div>
        <div class="text-[1.6rem] font-bold mt-2">{{ stats.total }} / {{ stats.total }}</div>
      </div>
      <div v-for="s in STATUSES" :key="s" class="card !p-5">
        <div class="flex items-baseline gap-2">
          <span class="font-bold text-[1.02rem] capitalize" :class="statusTextClass[s]">{{ s }}</span>
          <span class="muted text-[.82rem]">({{ percent(stats[s]) }})</span>
        </div>
        <div class="text-[1.6rem] font-bold mt-2">{{ stats[s] }} / {{ stats.total }}</div>
      </div>
    </div>

    <div class="card">
      <!-- toolbar -->
      <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="inline-flex bg-[#f7f7fa] border border-line rounded-xl p-1 gap-1">
          <button
            class="px-3.5 py-1.5 rounded-lg text-[.8rem] font-semibold capitalize transition-colors"
            :class="statusTab === 'all' ? 'bg-[#6352e7] text-white' : 'text-muted hover:text-ink'"
            @click="statusTab = 'all'"
          >All</button>
          <button
            v-for="s in STATUSES" :key="s"
            class="px-3.5 py-1.5 rounded-lg text-[.8rem] font-semibold capitalize transition-colors"
            :class="statusTab === s ? 'bg-[#6352e7] text-white' : 'text-muted hover:text-ink'"
            @click="statusTab = s"
          >{{ s }}</button>
        </div>

        <div class="inline-flex bg-[#f7f7fa] border border-line rounded-xl p-1 gap-1">
          <button
            class="px-3.5 py-1.5 rounded-lg text-[.8rem] font-semibold transition-colors"
            :class="view === 'grouped' ? 'bg-white text-brand shadow-sm' : 'text-muted hover:text-ink'"
            @click="view = 'grouped'"
          >Grouped</button>
          <button
            class="px-3.5 py-1.5 rounded-lg text-[.8rem] font-semibold transition-colors"
            :class="view === 'list' ? 'bg-white text-brand shadow-sm' : 'text-muted hover:text-ink'"
            @click="view = 'list'"
          >List</button>
        </div>

        <div class="grow" />
        <SearchInput v-model="search" placeholder="Search services…" class="min-w-55 max-w-80" />
        <FilterSelect v-model="exhibitorFilter" label="Exhibitor" :options="exhibitorOptions" />
        <FilterSelect v-model="categoryFilter" label="Category" :options="categoryOptions" />
      </div>

      <div v-if="loading" class="flex items-center justify-center gap-2.5 py-14 text-muted text-[.88rem]">
        <svg class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
          <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
        </svg>
        Loading requests…
      </div>

      <template v-else-if="view === 'grouped'">
        <!-- grouped: one row per exhibitor, expandable into its orders -->
        <table>
          <thead>
            <tr>
              <th>EXHIBITOR NAME</th><th>TOTAL REQUESTS</th><th>STATUS SUMMARY</th>
              <th>TOTAL AMOUNT</th><th>ACTIONS</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="g in pagedGroups" :key="g.name">
              <tr class="align-middle">
                <td class="font-medium">{{ g.name }}</td>
                <td class="font-bold">{{ g.orders.length }}</td>
                <td>
                  <span v-for="s in g.summary" :key="s.status" class="inline-block px-2.5 py-0.5 mr-1.5 text-[.72rem] font-semibold rounded-full capitalize whitespace-nowrap" :class="pillClass(s.status)">
                    {{ s.count }} {{ s.status }}
                  </span>
                </td>
                <td class="whitespace-nowrap">{{ money(g.currency, g.total) }}</td>
                <td>
                  <button class="btn sm" @click="expanded = expanded === g.name ? null : g.name">
                    {{ expanded === g.name ? 'Hide Orders' : 'Show Orders' }}
                  </button>
                </td>
              </tr>
              <tr v-if="expanded === g.name">
                <td colspan="5" class="!p-0">
                  <table class="bg-[#fafbfc]">
                    <thead>
                      <tr><th>ORDER ID</th><th>DATE</th><th>ITEMS</th><th>STATUS</th><th>AMOUNT</th><th /></tr>
                    </thead>
                    <tbody>
                      <tr v-for="o in g.orders" :key="o.id">
                        <td class="font-medium text-brand">#{{ o.order_number }}</td>
                        <td class="muted">{{ formatDate(o.date) }}</td>
                        <td>{{ o.lines_count }}</td>
                        <td><span class="inline-block px-2.5 py-0.5 text-[.72rem] font-semibold rounded-full capitalize whitespace-nowrap" :class="pillClass(o.status)">{{ o.status }}</span></td>
                        <td class="whitespace-nowrap">{{ money(o.currency, o.total) }}</td>
                        <td class="text-right"><button class="btn ghost sm" @click="openOrder(o)">View</button></td>
                      </tr>
                    </tbody>
                  </table>
                </td>
              </tr>
            </template>
            <tr v-if="!pagedGroups.length">
              <td colspan="5" class="muted text-center py-7">No service requests yet.</td>
            </tr>
          </tbody>
        </table>

        <!-- pagination -->
        <div v-if="groups.length" class="flex items-center justify-end gap-4 mt-3.5 flex-wrap">
          <label class="flex items-center gap-1.5 text-[.84rem] m-0">Nb / page
            <select v-model.number="groupPerPage" class="w-auto m-0 py-1.5 px-2">
              <option :value="10">10</option><option :value="25">25</option><option :value="50">50</option>
            </select>
          </label>
          <span class="muted text-[.84rem]">{{ groupRangeText }}</span>
          <button class="btn ghost sm" :disabled="groupPage <= 1" @click="groupPage--">‹</button>
          <button class="btn ghost sm" :disabled="groupPage >= groupTotalPages" @click="groupPage++">›</button>
        </div>
      </template>

      <!-- list: one row per order -->
      <DataTable
        v-else
        :items="visibleOrders"
        :columns="listColumns"
        row-key="id"
        storage-key="requested-services"
        empty-text="No service requests yet."
      >
        <template #cell-order_number="{ row }">
          <span class="font-medium text-brand">#{{ row.order_number }}</span>
        </template>
        <template #cell-exhibitor="{ row }">{{ row.exhibitor.name }}</template>
        <template #cell-date="{ row }"><span class="muted">{{ formatDate(row.date) }}</span></template>
        <template #cell-status="{ row }">
          <span class="inline-block px-2.5 py-0.5 text-[.72rem] font-semibold rounded-full capitalize whitespace-nowrap" :class="pillClass(row.status)">{{ row.status }}</span>
        </template>
        <template #cell-total="{ row }"><span class="whitespace-nowrap">{{ money(row.currency, row.total) }}</span></template>
        <template #actions="{ row }">
          <button class="btn ghost sm" @click="openOrder(row)">View</button>
        </template>
      </DataTable>
    </div>

    <!-- detail drawer -->
    <Drawer v-if="detailLoading || detail" title="Service Request Details" @close="detail = null; detailLoading = false">
      <p v-if="detailLoading" class="muted text-center py-8">Loading…</p>

      <template v-else-if="detail">
        <!-- order information -->
        <div class="flex items-start justify-between gap-3">
          <div>
            <h3 class="m-0 text-[1rem]">Order Information</h3>
            <p class="muted text-[.84rem] m-0 mt-1">Order ID: #{{ detail.order_number }}</p>
            <p class="muted text-[.84rem] m-0">Date: {{ formatDate(detail.date) }}</p>
            <p class="text-[.84rem] font-semibold mt-2 mb-0">Exhibitor:</p>
            <p class="muted text-[.84rem] m-0">{{ detail.exhibitor.name }}</p>
          </div>
          <span class="inline-block px-2.5 py-0.5 text-[.72rem] font-semibold rounded-full capitalize whitespace-nowrap shrink-0" :class="pillClass(detail.status)">{{ detail.status }}</span>
        </div>

        <!-- line items -->
        <h3 class="text-[1rem] mt-5 mb-2">Requested Services</h3>
        <div v-for="item in detail.items" :key="item.id" class="border border-line rounded-xl p-3 mb-2.5">
          <div class="flex gap-3">
            <div class="w-17.5 h-17.5 rounded-lg overflow-hidden bg-[#f1f5f9] grid place-items-center shrink-0">
              <img v-if="item.image" :src="item.image" :alt="item.name || ''" class="w-full h-full object-cover">
              <AppIcon v-else name="briefcase" class="w-5 h-5 text-[#94a3b8]" />
            </div>
            <div class="min-w-0 flex-1">
              <div class="font-semibold text-[.92rem]">{{ item.name }}</div>
              <p v-if="item.description" class="muted text-[.8rem] mt-0.5 mb-1">{{ plainText(item.description) }}</p>
              <div class="text-brand text-[.84rem]">{{ item.quantity }} X {{ money(item.currency, item.unit_price) }}</div>
              <div class="text-[.84rem]">
                <strong class="text-brand">{{ money(item.currency, item.line_total) }}</strong>
                <span class="muted text-[.7rem] ml-1">{{ item.tax ? 'Excluding Tax' : 'Tax free' }}</span>
              </div>
            </div>
          </div>
          <div class="flex items-center justify-between gap-2 mt-2.5 pt-2.5 border-t border-line">
            <span class="inline-block px-2.5 py-0.5 text-[.72rem] font-semibold rounded-full capitalize whitespace-nowrap" :class="pillClass(item.status)">{{ item.status }}</span>
            <div class="flex gap-2">
              <button class="btn ghost sm" :disabled="busyLine === item.id || item.status === 'approved'" @click="setLineStatus(item, 'approved')">Approve</button>
              <button class="btn ghost sm !text-[#dc2626]" :disabled="busyLine === item.id || item.status === 'rejected'" @click="setLineStatus(item, 'rejected')">Reject</button>
            </div>
          </div>
        </div>

        <!-- totals -->
        <div class="border-t border-dashed border-line mt-4 pt-3">
          <div class="flex items-center justify-between text-[.9rem]">
            <span class="muted">Subtotal</span><span>{{ money(detail.currency, detail.subtotal) }}</span>
          </div>
          <div v-if="detail.tax_total" class="flex items-center justify-between text-[.9rem] mt-1">
            <span class="muted">Tax</span><span>{{ money(detail.currency, detail.tax_total) }}</span>
          </div>
          <div class="flex items-center justify-between mt-2.5 pt-2.5 border-t border-line">
            <strong class="text-[1.05rem]">Total Amount</strong>
            <strong class="text-[1.05rem]">{{ money(detail.currency, detail.total) }}</strong>
          </div>
        </div>

        <!-- order-wide actions -->
        <div class="flex gap-2 mt-5">
          <button class="btn ghost flex-1" :disabled="busyOrder || detail.status === 'approved'" @click="setOrderStatus('approved')">Approve all</button>
          <button class="btn ghost flex-1 !text-[#dc2626]" :disabled="busyOrder || detail.status === 'rejected'" @click="setOrderStatus('rejected')">Reject all</button>
        </div>
        <button class="btn w-full mt-2.5 py-3 justify-center" :disabled="downloading" @click="downloadPdf">
          {{ downloading ? 'Preparing…' : 'Save (PDF)' }}
        </button>
      </template>
    </Drawer>
  </div>
</template>
