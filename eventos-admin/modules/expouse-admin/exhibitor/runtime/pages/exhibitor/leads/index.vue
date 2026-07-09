<script setup lang="ts">
definePageMeta({
  middleware: 'exhibitor',
  feature: 'all_leads',
  title: 'All Leads',
  subtitle: "All your leads and team's connections in one place — track relationships, spot opportunities, and follow up.",
})

const api = useApi()

interface Lead {
  id: string
  name: string
  email: string | null
  phone: string | null
  company: string | null
  rating: 'hot' | 'warm' | 'cold'
  status: string
  notes: string | null
  scanned_by: string | null
  created_at: string
}
interface Stats { total: number, hot: number, warm: number, cold: number, contacted: number, recently_exported: number }

const leads = ref<Lead[]>([])
const stats = ref<Stats>({ total: 0, hot: 0, warm: 0, cold: 0, contacted: 0, recently_exported: 0 })
const meta = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0, from: 0, to: 0 })
const members = ref<any[]>([])
const loading = ref(false)
const suspended = ref(false)

const RATINGS = ['hot', 'warm', 'cold']
const STATUSES = ['pending', 'connected', 'contacted', 'qualified', 'won', 'lost']

// ── Filters ─────────────────────────────────────────────────────────────────
const filters = reactive({ search: '', rating: '', status: '', rep: '', sort: 'recent', page: 1, per_page: 10 })
const selected = ref<Set<string>>(new Set())

let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(() => filters.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { filters.page = 1; load() }, 350)
})
watch(() => [filters.rating, filters.status, filters.rep, filters.sort, filters.per_page], () => { filters.page = 1; load() })
watch(() => filters.page, load)

async function load() {
  loading.value = true
  try {
    const q = new URLSearchParams({
      search: filters.search, rating: filters.rating, status: filters.status,
      rep: filters.rep, sort: filters.sort,
      page: String(filters.page), per_page: String(filters.per_page),
    })
    const res = await api<any>(`/exhibitor/leads?${q}`)
    leads.value = res.data
    meta.value = res.meta
    stats.value = res.stats
    selected.value = new Set()
  } catch (e: any) {
    if (e?.response?.status === 403 || e?.status === 403) suspended.value = true
  } finally {
    loading.value = false
  }
}

async function loadMembers() {
  try { members.value = (await api<any>('/exhibitor/members')).data } catch { /* */ }
}

// ── Inline edits ──────────────────────────────────────────────────────────────
async function patch(lead: Lead, body: Record<string, any>) {
  const res = await api<any>(`/exhibitor/leads/${lead.id}`, { method: 'PATCH', body })
  Object.assign(lead, res.data)
  // Rating/status changes shift the headline counts — refresh them cheaply.
  if ('rating' in body || 'status' in body) refreshStats()
}
async function refreshStats() {
  try { stats.value = (await api<any>(`/exhibitor/leads?per_page=5&page=1`)).stats } catch { /* */ }
}
function saveNote(lead: Lead, e: Event) {
  const value = (e.target as HTMLElement).innerText.trim()
  if (value === (lead.notes ?? '')) return
  patch(lead, { notes: value || null })
}

async function remove(lead: Lead) {
  if (!confirm(`Remove ${lead.name}?`)) return
  await api(`/exhibitor/leads/${lead.id}`, { method: 'DELETE' })
  await load()
}

// ── Selection ─────────────────────────────────────────────────────────────────
const allChecked = computed(() => leads.value.length > 0 && leads.value.every(l => selected.value.has(l.id)))
function toggleAll() {
  const s = new Set<string>()
  if (!allChecked.value) leads.value.forEach(l => s.add(l.id))
  selected.value = s
}
function toggleOne(id: string) {
  const s = new Set(selected.value)
  s.has(id) ? s.delete(id) : s.add(id)
  selected.value = s
}

// ── Export ────────────────────────────────────────────────────────────────────
const exporting = ref(false)
async function exportLeads() {
  exporting.value = true
  try {
    const ids = [...selected.value]
    const res = await api<any>('/exhibitor/leads/export', { method: 'POST', body: ids.length ? { ids } : {} })
    const blob = new Blob([res.data.csv], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url; a.download = res.data.filename; a.click()
    URL.revokeObjectURL(url)
    if (res.data.stats) stats.value = res.data.stats
  } finally {
    exporting.value = false
  }
}

// ── Display helpers ───────────────────────────────────────────────────────────
function initials(name: string) {
  const p = name.trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?'
}
function pct(n: number) { return stats.value.total ? Math.round((n / stats.value.total) * 100) : 0 }
function label(s: string) { return s.charAt(0).toUpperCase() + s.slice(1) }
function time(iso: string) {
  const d = new Date(iso)
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

onMounted(() => { load(); loadMembers() })
</script>

<template>
  <div v-if="suspended" class="card"><p class="error">This exhibitor account is suspended.</p></div>

  <div v-else>
    <!-- Stat cards -->
    <div class="grid grid-cols-6 gap-3 mb-5 max-xl:grid-cols-3 max-sm:grid-cols-2">
      <div class="stat-card">
        <div class="flex items-center gap-1.5 text-muted text-[.82rem]"><AppIcon name="users" class="w-3.5 h-3.5" /> Total</div>
        <div class="stat-n">{{ stats.total }}</div>
        <div class="stat-sub">All leads</div>
      </div>
      <div class="stat-card">
        <div class="flex items-center gap-1.5 text-muted text-[.82rem]"><span class="dot bg-[#ef4444]" /> Hot</div>
        <div class="stat-n">{{ stats.hot }}</div>
        <div class="stat-sub">{{ pct(stats.hot) }}% of total</div>
      </div>
      <div class="stat-card">
        <div class="flex items-center gap-1.5 text-muted text-[.82rem]"><span class="dot bg-[#f59e0b]" /> Warm</div>
        <div class="stat-n">{{ stats.warm }}</div>
        <div class="stat-sub">{{ pct(stats.warm) }}% of total</div>
      </div>
      <div class="stat-card">
        <div class="flex items-center gap-1.5 text-muted text-[.82rem]"><span class="dot bg-[#3b82f6]" /> Cold</div>
        <div class="stat-n">{{ stats.cold }}</div>
        <div class="stat-sub">{{ pct(stats.cold) }}% of total</div>
      </div>
      <div class="stat-card">
        <div class="flex items-center gap-1.5 text-muted text-[.82rem]"><AppIcon name="award" class="w-3.5 h-3.5" /> Contacted</div>
        <div class="stat-n">{{ stats.contacted }}</div>
        <div class="stat-sub">{{ pct(stats.contacted) }}% follow-up</div>
      </div>
      <div class="stat-card">
        <div class="flex items-center gap-1.5 text-muted text-[.82rem]"><AppIcon name="download" class="w-3.5 h-3.5" /> Recently Exported</div>
        <div class="stat-n">{{ stats.recently_exported }}</div>
        <div class="stat-sub">{{ stats.recently_exported ? 'in last 7 days' : 'No exports yet' }}</div>
      </div>
    </div>

    <div class="card p-0 overflow-hidden">
      <!-- Toolbar -->
      <div class="flex items-center gap-2.5 p-4 flex-wrap">
        <div class="relative flex-1 min-w-[220px]">
          <AppIcon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
          <input v-model="filters.search" placeholder="Search" class="!pl-9 !m-0 w-full">
        </div>
        <div class="grow max-sm:hidden" />
        <select v-model="filters.rating" class="!m-0 !w-auto">
          <option value="">All rating</option>
          <option v-for="r in RATINGS" :key="r" :value="r">{{ label(r) }}</option>
        </select>
        <select v-model="filters.status" class="!m-0 !w-auto">
          <option value="">All statuses</option>
          <option v-for="s in STATUSES" :key="s" :value="s">{{ label(s) }}</option>
        </select>
        <select v-model="filters.rep" class="!m-0 !w-auto">
          <option value="">All reps</option>
          <option v-for="m in members" :key="m.id" :value="m.id">{{ m.contact?.name || m.contact?.email }}</option>
        </select>
        <select v-model="filters.sort" class="!m-0 !w-auto">
          <option value="recent">Sort by</option>
          <option value="name">Name</option>
          <option value="company">Company</option>
          <option value="rating">Rating</option>
          <option value="oldest">Oldest</option>
        </select>
        <button class="btn" :disabled="exporting" @click="exportLeads">
          <AppIcon name="download" class="w-3.5 h-3.5" /> {{ exporting ? 'Exporting…' : (selected.size ? `Export (${selected.size})` : 'Export') }}
        </button>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="leads-table">
          <thead>
            <tr>
              <th class="w-9"><input type="checkbox" :checked="allChecked" @change="toggleAll"></th>
              <th>Leads</th>
              <th>Contact</th>
              <th>Company</th>
              <th>Rating</th>
              <th>Status</th>
              <th>Scanned by</th>
              <th class="min-w-[180px]">Notes</th>
              <th class="w-12 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading && !leads.length"><td colspan="9" class="py-10 text-center muted">Loading…</td></tr>
            <tr v-else-if="!leads.length"><td colspan="9" class="py-10 text-center muted">No leads yet.</td></tr>
            <tr v-for="l in leads" :key="l.id">
              <td><input type="checkbox" :checked="selected.has(l.id)" @change="toggleOne(l.id)"></td>
              <td>
                <div class="flex items-center gap-2.5">
                  <span class="avatar">{{ initials(l.name) }}</span>
                  <div class="min-w-0">
                    <div class="font-semibold text-ink text-[.9rem] truncate">{{ l.name }}</div>
                    <div class="text-muted text-[.78rem] truncate">{{ l.email || '—' }}</div>
                  </div>
                </div>
              </td>
              <td class="muted">{{ l.phone || '—' }}</td>
              <td class="text-[.88rem]">{{ l.company || '—' }}</td>
              <td>
                <select :value="l.rating" class="pill" :class="`pill-${l.rating}`" @change="patch(l, { rating: ($event.target as HTMLSelectElement).value })">
                  <option v-for="r in RATINGS" :key="r" :value="r">{{ label(r) }}</option>
                </select>
              </td>
              <td>
                <select :value="l.status" class="pill" :class="l.status === 'pending' ? 'pill-pending' : 'pill-connected'" @change="patch(l, { status: ($event.target as HTMLSelectElement).value })">
                  <option v-for="s in STATUSES" :key="s" :value="s">{{ label(s) }}</option>
                </select>
              </td>
              <td class="text-[.84rem]">
                <template v-if="l.scanned_by">
                  <div class="text-ink">{{ l.scanned_by }}</div>
                  <div class="text-muted text-[.76rem]">{{ time(l.created_at) }}</div>
                </template>
                <span v-else class="muted">—</span>
              </td>
              <td>
                <div
                  class="note text-[.83rem]"
                  :class="{ empty: !l.notes }"
                  contenteditable="plaintext-only"
                  :data-ph="'Add note'"
                  @blur="saveNote(l, $event)"
                >{{ l.notes }}</div>
              </td>
              <td class="text-right">
                <button class="icon-btn" title="Remove lead" @click="remove(l)">
                  <AppIcon name="logout" class="w-4 h-4" />
                </button>
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
        <span>{{ meta.total ? `${meta.from}–${meta.to}` : 0 }} of {{ meta.total }}</span>
        <div class="flex items-center gap-1">
          <button class="page-btn" :disabled="meta.current_page <= 1" @click="filters.page--">‹</button>
          <span class="px-2">Page {{ meta.current_page }} / {{ meta.last_page }}</span>
          <button class="page-btn" :disabled="meta.current_page >= meta.last_page" @click="filters.page++">›</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.stat-card { border-radius: 12px; border: 1px solid var(--line); background: var(--card); padding: 14px 16px; }
.stat-n { font-size: 1.5rem; font-weight: 800; color: var(--ink); line-height: 1; margin-top: 8px; }
.stat-sub { font-size: .76rem; color: var(--muted); margin-top: 4px; }
.dot { width: 8px; height: 8px; border-radius: 9999px; display: inline-block; }

.avatar { width: 32px; height: 32px; border-radius: 9999px; background: var(--brand-soft); color: var(--brand-dark); display: grid; place-items: center; font-size: .72rem; font-weight: 700; flex-shrink: 0; }

.leads-table { width: 100%; border-collapse: collapse; }
.leads-table th { text-align: left; font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); padding: 10px 12px; border-bottom: 1px solid var(--line); background: #fafbfc; }
.leads-table td { padding: 10px 12px; border-bottom: 1px solid var(--line); vertical-align: middle; }
.leads-table tbody tr:hover { background: #fafbfc; }

/* Colored pill selects for rating / status */
.pill { margin: 0; width: auto; padding: 4px 22px 4px 10px; font-size: .78rem; font-weight: 600; border-radius: 9999px; border: 0; cursor: pointer; }
.pill-hot { background: #fee2e2; color: #b91c1c; }
.pill-warm { background: #fef3c7; color: #b45309; }
.pill-cold { background: #dbeafe; color: #1d4ed8; }
.pill-connected { background: #dcfce7; color: #15803d; }
.pill-pending { background: #f3f4f6; color: #4b5563; }

.note { min-height: 1.4rem; border-radius: 6px; padding: 4px 8px; margin: 0 -8px; outline: none; }
.note:focus { background: #f6f7f9; box-shadow: 0 0 0 2px var(--brand-soft); }
.note.empty:before { content: attr(data-ph); color: var(--faint); font-style: italic; }

.icon-btn { width: 32px; height: 32px; display: grid; place-items: center; border-radius: 8px; color: var(--muted); background: transparent; border: 0; cursor: pointer; }
.icon-btn:hover { background: #f6f7f9; color: #b91c1c; }
.page-btn { width: 28px; height: 28px; display: grid; place-items: center; border-radius: 6px; border: 1px solid var(--line); background: transparent; cursor: pointer; }
.page-btn:hover:not(:disabled) { background: #f6f7f9; }
.page-btn:disabled { opacity: .4; cursor: default; }
</style>
