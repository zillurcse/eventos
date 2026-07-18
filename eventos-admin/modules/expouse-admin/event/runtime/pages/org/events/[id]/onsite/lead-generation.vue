<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface LeadExhibitor { id: string, name: string, type: string | null }
interface Lead {
  id: string
  name: string
  email: string | null
  phone: string | null
  company: string | null
  job_title: string | null
  rating: 'hot' | 'warm' | 'cold'
  status: string
  source: string
  notes: string | null
  scanned_by: string | null
  created_at: string
  exhibitor: LeadExhibitor | null
}
interface Totals {
  leads: number, hot: number, hot_pct: number, warm: number, cold: number
  consented: number, consent_rate: number
  exhibitors: number, exhibitors_capturing: number, capture_rate: number
}
interface ExhibitorRow { id: string, name: string, type: string | null, leads: number, hot: number }
interface Insights { totals: Totals, by_exhibitor: ExhibitorRow[], exhibitors: { id: string, name: string }[] }

const RATINGS = ['hot', 'warm', 'cold'] as const
const STATUSES = ['pending', 'connected', 'contacted', 'qualified', 'won', 'lost']

const leads = ref<Lead[]>([])
const insights = ref<Insights | null>(null)
const meta = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0, from: 0, to: 0 })
const loading = ref(true)
const tableLoading = ref(false)

// ── Filters (server-side) ─────────────────────────────────────────────────────
const filters = reactive({ search: '', rating: '', status: '', exhibitor: '', sort: 'recent', page: 1, per_page: 10 })

let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(() => filters.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { filters.page = 1; load() }, 350)
})
watch(() => [filters.rating, filters.status, filters.exhibitor, filters.sort, filters.per_page], () => { filters.page = 1; load() })
watch(() => filters.page, () => load())

function query() {
  return new URLSearchParams({
    search: filters.search, rating: filters.rating, status: filters.status,
    exhibitor: filters.exhibitor, sort: filters.sort,
    page: String(filters.page), per_page: String(filters.per_page),
  })
}

async function load() {
  tableLoading.value = true
  try {
    const res = await api<any>(`/events/${id}/leads?${query()}`)
    leads.value = res.data
    meta.value = res.meta
    insights.value = res.insights
  } catch { /* keep last good state */ }
  finally { loading.value = false; tableLoading.value = false }
}

// ── Export ────────────────────────────────────────────────────────────────────
const exporting = ref(false)
async function exportCsv() {
  exporting.value = true
  try {
    const res = await api<any>(`/events/${id}/leads/export?${query()}`, { method: 'POST' })
    const blob = new Blob([res.data.csv], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url; a.download = res.data.filename; a.click()
    URL.revokeObjectURL(url)
  } finally {
    exporting.value = false
  }
}

// ── Display helpers ───────────────────────────────────────────────────────────
const totals = computed<Totals>(() => insights.value?.totals ?? {
  leads: 0, hot: 0, hot_pct: 0, warm: 0, cold: 0,
  consented: 0, consent_rate: 0, exhibitors: 0, exhibitors_capturing: 0, capture_rate: 0,
})
const maxExhibitorLeads = computed(() =>
  Math.max(1, ...(insights.value?.by_exhibitor.map(e => e.leads) ?? [0])))

const QUALITY = [
  { key: 'hot' as const, label: 'Hot', bar: '#ef4444', pill: 'pill-hot' },
  { key: 'warm' as const, label: 'Warm', bar: '#f59e0b', pill: 'pill-warm' },
  { key: 'cold' as const, label: 'Cold', bar: '#3b82f6', pill: 'pill-cold' },
]

function fmt(n: number) { return n.toLocaleString() }
function label(s: string) { return s.charAt(0).toUpperCase() + s.slice(1) }
function initials(name: string) {
  const p = name.trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?'
}
function time(iso: string) {
  return new Date(iso).toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const cards = computed(() => [
  { label: 'Total leads', value: fmt(totals.value.leads), sub: `across ${totals.value.exhibitors_capturing} exhibitor${totals.value.exhibitors_capturing === 1 ? '' : 's'}` },
  { label: 'Hot leads', value: fmt(totals.value.hot), sub: `${totals.value.hot_pct}% of total` },
  { label: 'Consent rate', value: `${totals.value.consent_rate}%`, sub: 'opted in to share data' },
  { label: 'Capture rate', value: `${totals.value.capture_rate}%`, sub: `${totals.value.exhibitors_capturing} of ${totals.value.exhibitors} exhibitors logging leads` },
])

onMounted(load)
</script>

<template>
  <div class="max-w-275">
    <!-- Header -->
    <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
      <div>
        <h2 class="section-title m-0">Lead Generation</h2>
        <p class="muted text-[.86rem] mt-0.5 mb-0">Contact capture and qualification by exhibitors.</p>
      </div>
      <button class="btn" :disabled="exporting || !totals.leads" @click="exportCsv">
        <AppIcon name="download" class="w-3.5 h-3.5" />
        {{ exporting ? 'Exporting…' : 'Export All (.CSV)' }}
      </button>
    </div>

    <div v-if="loading" class="card flex items-center justify-center gap-2.5 py-12 text-muted text-[.88rem]">
      <svg class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
        <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
      </svg>
      Loading lead generation…
    </div>

    <template v-else>
      <!-- KPI cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <div v-for="c in cards" :key="c.label" class="card p-4">
          <div class="text-[.78rem] text-muted font-medium">{{ c.label }}</div>
          <div class="text-[1.6rem] font-bold text-ink mt-1 leading-none">{{ c.value }}</div>
          <div class="text-[.76rem] text-muted mt-1.5">{{ c.sub }}</div>
        </div>
      </div>

      <!-- Breakdown panels -->
      <div class="grid lg:grid-cols-2 gap-4 mb-5">
        <!-- Leads by exhibitor -->
        <div class="card">
          <div class="font-bold text-[.98rem] mb-4">Leads by exhibitor</div>
          <div v-if="insights?.by_exhibitor.length" class="flex flex-col gap-3.5">
            <div v-for="e in insights.by_exhibitor" :key="e.id" class="flex items-center gap-3">
              <span class="w-30 shrink-0 text-[.86rem] text-ink font-medium truncate" :title="e.name">{{ e.name }}</span>
              <div class="flex-1 h-2 bg-[#f1f1f5] rounded-full overflow-hidden">
                <div class="h-full bg-[#6352e7] rounded-full transition-all" :style="{ width: `${Math.round(e.leads / maxExhibitorLeads * 100)}%` }" />
              </div>
              <span class="w-9 shrink-0 text-right text-[.86rem] font-semibold text-ink">{{ fmt(e.leads) }}</span>
            </div>
          </div>
          <p v-else class="muted text-[.86rem] my-6 text-center">No leads captured yet.</p>
        </div>

        <!-- Lead quality -->
        <div class="card">
          <div class="font-bold text-[.98rem] mb-4">Lead quality</div>
          <div class="flex flex-col gap-4">
            <div v-for="q in QUALITY" :key="q.key">
              <div class="flex items-center justify-between mb-1.5">
                <span class="px-2 py-0.5 rounded-full text-[.74rem] font-semibold" :class="q.pill">{{ q.label }}</span>
                <span class="text-[.86rem] font-semibold text-ink">{{ fmt(totals[q.key]) }}</span>
              </div>
              <div class="h-2 bg-[#f1f1f5] rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all" :style="{ width: `${totals.leads ? Math.round(totals[q.key] / totals.leads * 100) : 0}%`, background: q.bar }" />
              </div>
            </div>
          </div>
          <p class="muted text-[.8rem] mt-4 mb-0">
            <strong class="text-ink">Note:</strong> leads with no consent flag show a masked contact card to
            exhibitors until the attendee unlocks it.
          </p>
        </div>
      </div>

      <!-- Recent leads -->
      <div class="mb-2 font-semibold text-[.92rem]">Recent leads</div>
      <div class="card p-0 overflow-hidden">
        <!-- Toolbar -->
        <div class="flex items-center gap-2.5 p-4 flex-wrap">
          <div class="relative flex-1 min-w-[220px]">
            <AppIcon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
            <input v-model="filters.search" placeholder="Search name, email, company or exhibitor" class="!pl-9 !m-0 w-full">
          </div>
          <div class="grow max-sm:hidden" />
          <select v-model="filters.exhibitor" class="!m-0 !w-auto">
            <option value="">All exhibitors</option>
            <option v-for="e in insights?.exhibitors ?? []" :key="e.id" :value="e.id">{{ e.name }}</option>
          </select>
          <select v-model="filters.rating" class="!m-0 !w-auto">
            <option value="">All ratings</option>
            <option v-for="r in RATINGS" :key="r" :value="r">{{ label(r) }}</option>
          </select>
          <select v-model="filters.status" class="!m-0 !w-auto">
            <option value="">All statuses</option>
            <option v-for="s in STATUSES" :key="s" :value="s">{{ label(s) }}</option>
          </select>
          <select v-model="filters.sort" class="!m-0 !w-auto">
            <option value="recent">Most recent</option>
            <option value="name">Name</option>
            <option value="company">Company</option>
            <option value="rating">Rating</option>
            <option value="oldest">Oldest</option>
          </select>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="leads-table">
            <thead>
              <tr>
                <th>Contact</th>
                <th>Phone</th>
                <th>Organization</th>
                <th>Exhibitor</th>
                <th>Rating</th>
                <th>Scanned by</th>
                <th>Status</th>
                <th class="min-w-[180px]">Notes</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="tableLoading && !leads.length"><td colspan="8" class="py-10 text-center muted">Loading…</td></tr>
              <tr v-else-if="!leads.length"><td colspan="8" class="py-10 text-center muted">No leads match these filters.</td></tr>
              <tr v-for="l in leads" :key="l.id">
                <td>
                  <div class="flex items-center gap-2.5">
                    <span class="avatar">{{ initials(l.name) }}</span>
                    <div class="min-w-0">
                      <div class="font-semibold text-ink text-[.9rem] truncate">{{ l.name }}</div>
                      <div class="text-muted text-[.78rem] truncate">{{ l.email || '—' }}</div>
                    </div>
                  </div>
                </td>
                <td class="muted text-[.86rem] whitespace-nowrap">{{ l.phone || '—' }}</td>
                <td class="text-[.88rem]">{{ l.company || '—' }}</td>
                <td class="text-[.86rem]">{{ l.exhibitor?.name || '—' }}</td>
                <td>
                  <span class="pill" :class="`pill-${l.rating}`">{{ label(l.rating) }}</span>
                </td>
                <td class="text-[.84rem]">
                  <template v-if="l.scanned_by">
                    <div class="text-ink">{{ l.scanned_by }}</div>
                    <div class="text-muted text-[.76rem]">{{ time(l.created_at) }}</div>
                  </template>
                  <span v-else class="muted">—</span>
                </td>
                <td>
                  <span class="pill" :class="l.status === 'pending' ? 'pill-pending' : 'pill-done'">{{ label(l.status) }}</span>
                </td>
                <td class="text-[.83rem] text-muted max-w-60"><div class="truncate" :title="l.notes || ''">{{ l.notes || '—' }}</div></td>
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
          <span>{{ meta.total ? `${meta.from}–${meta.to}` : 0 }} of {{ meta.total }}</span>
          <div class="flex items-center gap-1">
            <button class="page-btn" :disabled="meta.current_page <= 1" @click="filters.page--">‹</button>
            <span class="px-2">Page {{ meta.current_page }} / {{ meta.last_page }}</span>
            <button class="page-btn" :disabled="meta.current_page >= meta.last_page" @click="filters.page++">›</button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.avatar { width: 32px; height: 32px; border-radius: 9999px; background: var(--brand-soft); color: var(--brand-dark); display: grid; place-items: center; font-size: .72rem; font-weight: 700; flex-shrink: 0; }

.leads-table { width: 100%; border-collapse: collapse; }
.leads-table th { text-align: left; font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); padding: 10px 12px; border-bottom: 1px solid var(--line); background: #fafbfc; }
.leads-table td { padding: 10px 12px; border-bottom: 1px solid var(--line); vertical-align: middle; }
.leads-table tbody tr:hover { background: #fafbfc; }
.leads-table tbody tr:last-child td { border-bottom: 0; }

.pill { display: inline-block; padding: 3px 10px; font-size: .74rem; font-weight: 600; border-radius: 9999px; white-space: nowrap; }
.pill-hot { background: #fee2e2; color: #b91c1c; }
.pill-warm { background: #fef3c7; color: #b45309; }
.pill-cold { background: #dbeafe; color: #1d4ed8; }
.pill-done { background: #dcfce7; color: #15803d; }
.pill-pending { background: #f3f4f6; color: #4b5563; }

.page-btn { width: 28px; height: 28px; display: grid; place-items: center; border-radius: 6px; border: 1px solid var(--line); background: transparent; cursor: pointer; }
.page-btn:hover:not(:disabled) { background: #f6f7f9; }
.page-btn:disabled { opacity: .4; cursor: default; }
</style>
