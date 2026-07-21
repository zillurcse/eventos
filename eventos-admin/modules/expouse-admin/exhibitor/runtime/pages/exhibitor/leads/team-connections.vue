<script setup lang="ts">
definePageMeta({
  middleware: 'exhibitor',
  feature: 'team_connections',
  title: 'Team Connections',
  subtitle: 'All your team\'s connections in one place — track relationships, spot opportunities, collaborate.',
})

const api = useApi()

interface Row {
  member_id: number | null
  name: string
  email: string | null
  role: string | null
  is_lead_capturer: boolean
  total: number
  hot: number
  warm: number
  cold: number
  pending: number
  contacted: number
  qualified: number
  won: number
  lost: number
  companies: number
  scanned: number
  conversion_rate: number
  share: number
  last_connection_at: string | null
}
interface Totals {
  connections: number
  members: number
  active_members: number
  hot: number
  contacted: number
  won: number
  unassigned: number
  companies: number
  today: number
  conversion_rate: number
  avg_per_member: number
}
interface Connection {
  id: string
  name: string
  email: string | null
  phone: string | null
  company: string | null
  rating: 'hot' | 'warm' | 'cold'
  status: string
  notes: string | null
  scanned_by: string | null
  scanned_by_member_id: number | null
  created_at: string
}

const RATINGS = ['hot', 'warm', 'cold']
const STATUSES = ['pending', 'connected', 'contacted', 'qualified', 'won', 'lost']

const rows = ref<Row[]>([])
const unassigned = ref<Row | null>(null)
const totals = ref<Totals>({
  connections: 0, members: 0, active_members: 0, hot: 0, contacted: 0, won: 0,
  unassigned: 0, companies: 0, today: 0, conversion_rate: 0, avg_per_member: 0,
})
const timeline = ref<{ date: string, label: string, count: number }[]>([])
const overlaps = ref<{ company: string, leads: number, members: string[] }[]>([])
const loading = ref(true)
const suspended = ref(false)

/** null = the whole team; a member id or 'unassigned' drills into one owner. */
const owner = ref<number | 'unassigned' | null>(null)

const connections = ref<Connection[]>([])
const meta = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 })
const listLoading = ref(false)
const listFilters = reactive({ search: '', rating: '', status: '', sort: 'recent', page: 1 })

// ── Loading ──────────────────────────────────────────────────────────────────
async function loadTeam() {
  loading.value = true
  try {
    const res = await api<any>('/exhibitor/leads/team')
    rows.value = res.data
    unassigned.value = res.unassigned
    totals.value = res.totals
    timeline.value = res.timeline
    overlaps.value = res.overlaps
  } catch (e: any) {
    if (e?.response?.status === 403 || e?.status === 403) suspended.value = true
  } finally {
    loading.value = false
  }
}

async function loadConnections() {
  listLoading.value = true
  try {
    const q = new URLSearchParams({
      search: listFilters.search,
      rating: listFilters.rating,
      status: listFilters.status,
      sort: listFilters.sort,
      rep: owner.value === null ? '' : String(owner.value),
      page: String(listFilters.page),
      per_page: '10',
    })
    const res = await api<any>(`/exhibitor/leads?${q}`)
    connections.value = res.data
    meta.value = res.meta
  } finally {
    listLoading.value = false
  }
}

let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(() => listFilters.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { listFilters.page = 1; loadConnections() }, 350)
})
watch(() => [listFilters.rating, listFilters.status, listFilters.sort], () => { listFilters.page = 1; loadConnections() })
watch(() => listFilters.page, loadConnections)
watch(owner, () => { listFilters.page = 1; loadConnections() })

function selectOwner(id: number | 'unassigned' | null) {
  owner.value = owner.value === id ? null : id
}

// ── Collaboration actions ────────────────────────────────────────────────────
const reassigning = ref<string | null>(null)

/** Hand a connection to another teammate — the point of a shared book. */
async function reassign(c: Connection, value: string) {
  reassigning.value = c.id
  try {
    const res = await api<any>(`/exhibitor/leads/${c.id}`, {
      method: 'PATCH',
      body: { scanned_by_member_id: value ? Number(value) : null },
    })
    Object.assign(c, res.data)
    // Ownership moved, so every roll-up on the page is now stale.
    await loadTeam()
    // Drilled into one owner? The row may no longer belong in this list.
    if (owner.value !== null) await loadConnections()
  } finally {
    reassigning.value = null
  }
}

async function patch(c: Connection, body: Record<string, any>) {
  const res = await api<any>(`/exhibitor/leads/${c.id}`, { method: 'PATCH', body })
  Object.assign(c, res.data)
  loadTeam()
}

const exporting = ref(false)
async function exportConnections() {
  exporting.value = true
  try {
    // Export follows the drill-down: one rep's book, or the whole team's.
    const ids = owner.value === null ? [] : connections.value.map(c => c.id)
    const res = await api<any>('/exhibitor/leads/export', { method: 'POST', body: ids.length ? { ids } : {} })
    const blob = new Blob([res.data.csv], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = res.data.filename
    a.click()
    URL.revokeObjectURL(url)
  } finally {
    exporting.value = false
  }
}

// ── Display helpers ──────────────────────────────────────────────────────────
const ownerLabel = computed(() => {
  if (owner.value === null) return 'the whole team'
  if (owner.value === 'unassigned') return 'unassigned connections'
  return rows.value.find(r => r.member_id === owner.value)?.name ?? 'this teammate'
})
const peak = computed(() => Math.max(1, ...timeline.value.map(d => d.count)))
const topPerformer = computed(() => rows.value.find(r => r.total > 0) ?? null)

function initials(name?: string | null, email?: string | null) {
  const p = (name || email || '?').trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?'
}
function label(s: string) { return s.charAt(0).toUpperCase() + s.slice(1) }
function pct(n: number) { return totals.value.connections ? Math.round((n / totals.value.connections) * 100) : 0 }
function ago(iso: string | null) {
  if (!iso) return 'No connections yet'
  const mins = Math.round((Date.now() - new Date(iso).getTime()) / 60000)
  if (mins < 1) return 'Just now'
  if (mins < 60) return `${mins}m ago`
  if (mins < 1440) return `${Math.floor(mins / 60)}h ago`
  const days = Math.floor(mins / 1440)
  return days === 1 ? 'Yesterday' : `${days}d ago`
}
function date(iso: string) {
  return new Date(iso).toLocaleDateString([], { month: 'short', day: 'numeric' })
}

onMounted(async () => { await loadTeam(); loadConnections() })
</script>

<template>
  <div v-if="suspended" class="card"><p class="error">This exhibitor account is suspended.</p></div>

  <div v-else>
    <!-- Team headline -->
    <div class="grid grid-cols-6 gap-3 mb-5 max-xl:grid-cols-3 max-sm:grid-cols-2">
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="link" class="w-3.5 h-3.5" /> Connections</div>
        <div class="stat-n">{{ totals.connections }}</div>
        <div class="stat-sub">{{ totals.today }} captured today</div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="users" class="w-3.5 h-3.5" /> Team</div>
        <div class="stat-n">{{ totals.active_members }}<span class="stat-of">/{{ totals.members }}</span></div>
        <div class="stat-sub">{{ totals.avg_per_member }} avg per member</div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="briefcase" class="w-3.5 h-3.5" /> Companies</div>
        <div class="stat-n">{{ totals.companies }}</div>
        <div class="stat-sub">Distinct organisations</div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><span class="dot bg-[#ef4444]" /> Hot</div>
        <div class="stat-n">{{ totals.hot }}</div>
        <div class="stat-sub">{{ pct(totals.hot) }}% of connections</div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="target" class="w-3.5 h-3.5" /> Conversion</div>
        <div class="stat-n">{{ totals.conversion_rate }}%</div>
        <div class="stat-sub">{{ totals.won }} won · {{ totals.contacted }} followed up</div>
      </div>
      <button class="stat-card text-left" :class="{ 'stat-card-alert': totals.unassigned }" @click="selectOwner('unassigned')">
        <div class="stat-label"><AppIcon name="flag" class="w-3.5 h-3.5" /> Unassigned</div>
        <div class="stat-n">{{ totals.unassigned }}</div>
        <div class="stat-sub">{{ totals.unassigned ? 'Needs an owner' : 'Everything is owned' }}</div>
      </button>
    </div>

    <div class="grid grid-cols-3 gap-4 mb-5 max-lg:grid-cols-1">
      <!-- 7-day capture trend -->
      <div class="card">
        <div class="panel-head">
          <h3>Connection activity</h3>
          <span class="muted text-[.78rem]">Last 7 days</span>
        </div>
        <div class="flex items-end gap-2 h-[92px] mt-4">
          <div v-for="d in timeline" :key="d.date" class="flex-1 flex flex-col items-center gap-1.5" :title="`${d.count} on ${d.date}`">
            <span class="text-[.7rem] font-semibold text-ink">{{ d.count || '' }}</span>
            <div class="bar" :style="{ height: `${Math.max(4, (d.count / peak) * 62)}px` }" />
            <span class="text-[.68rem] text-muted">{{ d.label }}</span>
          </div>
        </div>
      </div>

      <!-- Top performer -->
      <div class="card">
        <div class="panel-head"><h3>Leading the booth</h3></div>
        <div v-if="topPerformer" class="flex items-center gap-3 mt-4">
          <span class="avatar avatar-lg">{{ initials(topPerformer.name, topPerformer.email) }}</span>
          <div class="min-w-0">
            <div class="font-semibold text-ink truncate">{{ topPerformer.name }}</div>
            <div class="text-muted text-[.78rem] truncate">{{ topPerformer.total }} connections · {{ topPerformer.share }}% of the team's book</div>
          </div>
        </div>
        <div v-else class="muted text-[.85rem] mt-4">No connections captured yet.</div>
        <div v-if="topPerformer" class="grid grid-cols-3 gap-2 mt-4">
          <div class="mini"><span class="mini-n">{{ topPerformer.hot }}</span><span class="mini-l">Hot</span></div>
          <div class="mini"><span class="mini-n">{{ topPerformer.companies }}</span><span class="mini-l">Companies</span></div>
          <div class="mini"><span class="mini-n">{{ topPerformer.conversion_rate }}%</span><span class="mini-l">Conversion</span></div>
        </div>
      </div>

      <!-- Overlaps: two reps working the same account -->
      <div class="card">
        <div class="panel-head">
          <h3>Shared accounts</h3>
          <span class="muted text-[.78rem]">Multi-threaded</span>
        </div>
        <p v-if="!overlaps.length" class="muted text-[.85rem] mt-4">
          No company has been connected with by more than one teammate.
        </p>
        <ul v-else class="mt-3 space-y-2.5 max-h-[130px] overflow-y-auto">
          <li v-for="o in overlaps" :key="o.company" class="flex items-center justify-between gap-3">
            <div class="min-w-0">
              <div class="text-[.85rem] font-medium text-ink truncate">{{ o.company }}</div>
              <div class="text-[.74rem] text-muted truncate">{{ o.members.join(', ') }}</div>
            </div>
            <span class="chip">{{ o.leads }}</span>
          </li>
        </ul>
      </div>
    </div>

    <!-- Per-teammate roll-up -->
    <div class="card p-0 overflow-hidden mb-5">
      <div class="panel-head p-4">
        <h3>Connections by teammate</h3>
        <span class="muted text-[.78rem]">Select a teammate to see their connections</span>
      </div>
      <div class="overflow-x-auto">
        <table class="t">
          <thead>
            <tr>
              <th>Teammate</th>
              <th>Connections</th>
              <th class="min-w-[150px]">Rating mix</th>
              <th>Companies</th>
              <th>Followed up</th>
              <th>Won</th>
              <th>Conversion</th>
              <th>Last connection</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading"><td colspan="8" class="py-10 text-center muted">Loading…</td></tr>
            <tr v-else-if="!rows.length"><td colspan="8" class="py-10 text-center muted">Invite teammates to start capturing connections.</td></tr>
            <tr
              v-for="r in rows" :key="r.member_id ?? 'none'"
              class="cursor-pointer"
              :class="{ 'row-active': owner === r.member_id }"
              @click="selectOwner(r.member_id)"
            >
              <td>
                <div class="flex items-center gap-2.5">
                  <span class="avatar">{{ initials(r.name, r.email) }}</span>
                  <div class="min-w-0">
                    <div class="font-semibold text-ink text-[.9rem] truncate">
                      {{ r.name }}
                      <span v-if="r.role === 'admin'" class="tag">Admin</span>
                      <span v-else-if="r.is_lead_capturer" class="tag tag-soft">Capturer</span>
                    </div>
                    <div class="text-muted text-[.78rem] truncate">{{ r.email || '—' }}</div>
                  </div>
                </div>
              </td>
              <td>
                <div class="font-semibold text-ink">{{ r.total }}</div>
                <div class="text-[.72rem] text-muted">{{ r.share }}% of team</div>
              </td>
              <td>
                <div v-if="r.total" class="mix">
                  <span v-if="r.hot" class="mix-seg bg-[#ef4444]" :style="{ flex: r.hot }" :title="`${r.hot} hot`" />
                  <span v-if="r.warm" class="mix-seg bg-[#f59e0b]" :style="{ flex: r.warm }" :title="`${r.warm} warm`" />
                  <span v-if="r.cold" class="mix-seg bg-[#3b82f6]" :style="{ flex: r.cold }" :title="`${r.cold} cold`" />
                </div>
                <div v-if="r.total" class="text-[.72rem] text-muted mt-1">{{ r.hot }} hot · {{ r.warm }} warm · {{ r.cold }} cold</div>
                <span v-else class="muted text-[.8rem]">—</span>
              </td>
              <td class="text-[.88rem]">{{ r.companies }}</td>
              <td class="text-[.88rem]">{{ r.contacted }}</td>
              <td class="text-[.88rem]">{{ r.won }}</td>
              <td>
                <span class="rate" :class="r.conversion_rate >= 25 ? 'rate-good' : r.conversion_rate > 0 ? 'rate-ok' : 'rate-none'">
                  {{ r.conversion_rate }}%
                </span>
              </td>
              <td class="text-[.84rem] muted">{{ ago(r.last_connection_at) }}</td>
            </tr>
            <tr
              v-if="unassigned && unassigned.total"
              class="cursor-pointer bg-[#fffbeb]"
              :class="{ 'row-active': owner === 'unassigned' }"
              @click="selectOwner('unassigned')"
            >
              <td>
                <div class="flex items-center gap-2.5">
                  <span class="avatar avatar-warn"><AppIcon name="flag" class="w-3.5 h-3.5" /></span>
                  <div>
                    <div class="font-semibold text-ink text-[.9rem]">Unassigned</div>
                    <div class="text-muted text-[.78rem]">Captured without an owner</div>
                  </div>
                </div>
              </td>
              <td>
                <div class="font-semibold text-ink">{{ unassigned.total }}</div>
                <div class="text-[.72rem] text-muted">{{ unassigned.share }}% of team</div>
              </td>
              <td class="text-[.72rem] text-muted">{{ unassigned.hot }} hot · {{ unassigned.warm }} warm · {{ unassigned.cold }} cold</td>
              <td class="text-[.88rem]">{{ unassigned.companies }}</td>
              <td class="text-[.88rem]">{{ unassigned.contacted }}</td>
              <td class="text-[.88rem]">{{ unassigned.won }}</td>
              <td class="muted text-[.88rem]">—</td>
              <td class="text-[.84rem] muted">{{ ago(unassigned.last_connection_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Drill-down: the actual connections -->
    <div class="card p-0 overflow-hidden">
      <div class="flex items-center gap-2.5 p-4 flex-wrap">
        <div>
          <h3 class="font-semibold text-ink">Connections</h3>
          <p class="text-muted text-[.78rem]">Showing {{ ownerLabel }}</p>
        </div>
        <button v-if="owner !== null" class="btn ghost sm" @click="owner = null">
          <AppIcon name="x" class="w-3.5 h-3.5" /> Clear
        </button>
        <div class="grow" />
        <div class="relative min-w-[200px]">
          <AppIcon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
          <input v-model="listFilters.search" placeholder="Search name, email, company" class="!pl-9 !m-0 w-full">
        </div>
        <select v-model="listFilters.rating" class="!m-0 !w-auto">
          <option value="">All ratings</option>
          <option v-for="r in RATINGS" :key="r" :value="r">{{ label(r) }}</option>
        </select>
        <select v-model="listFilters.status" class="!m-0 !w-auto">
          <option value="">All statuses</option>
          <option v-for="s in STATUSES" :key="s" :value="s">{{ label(s) }}</option>
        </select>
        <button class="btn" :disabled="exporting" @click="exportConnections">
          <AppIcon name="download" class="w-3.5 h-3.5" /> {{ exporting ? 'Exporting…' : 'Export' }}
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="t">
          <thead>
            <tr>
              <th>Connection</th>
              <th>Company</th>
              <th>Contact</th>
              <th>Rating</th>
              <th>Status</th>
              <th class="min-w-[170px]">Owner</th>
              <th>Captured</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="listLoading && !connections.length"><td colspan="7" class="py-10 text-center muted">Loading…</td></tr>
            <tr v-else-if="!connections.length"><td colspan="7" class="py-10 text-center muted">No connections match these filters.</td></tr>
            <tr v-for="c in connections" :key="c.id">
              <td>
                <div class="flex items-center gap-2.5">
                  <span class="avatar">{{ initials(c.name) }}</span>
                  <div class="min-w-0">
                    <div class="font-semibold text-ink text-[.9rem] truncate">{{ c.name }}</div>
                    <div class="text-muted text-[.78rem] truncate">{{ c.email || '—' }}</div>
                  </div>
                </div>
              </td>
              <td class="text-[.88rem]">{{ c.company || '—' }}</td>
              <td class="muted text-[.85rem]">{{ c.phone || '—' }}</td>
              <td>
                <select :value="c.rating" class="pill" :class="`pill-${c.rating}`" @change="patch(c, { rating: ($event.target as HTMLSelectElement).value })">
                  <option v-for="r in RATINGS" :key="r" :value="r">{{ label(r) }}</option>
                </select>
              </td>
              <td>
                <select :value="c.status" class="pill" :class="c.status === 'pending' ? 'pill-pending' : 'pill-connected'" @change="patch(c, { status: ($event.target as HTMLSelectElement).value })">
                  <option v-for="s in STATUSES" :key="s" :value="s">{{ label(s) }}</option>
                </select>
              </td>
              <td>
                <select
                  :value="c.scanned_by_member_id ?? ''"
                  class="!m-0 !w-full !py-1.5 text-[.82rem]"
                  :disabled="reassigning === c.id"
                  @change="reassign(c, ($event.target as HTMLSelectElement).value)"
                >
                  <option value="">Unassigned</option>
                  <option v-for="r in rows" :key="r.member_id!" :value="r.member_id!">{{ r.name }}</option>
                </select>
              </td>
              <td class="text-[.84rem] muted">{{ date(c.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex items-center justify-end gap-4 p-4 border-t border-line text-[.84rem] text-muted flex-wrap">
        <span>{{ meta.total ? `${meta.from}–${meta.to}` : 0 }} of {{ meta.total }}</span>
        <div class="flex items-center gap-1">
          <button class="page-btn" :disabled="meta.current_page <= 1" @click="listFilters.page--">‹</button>
          <span class="px-2">Page {{ meta.current_page }} / {{ meta.last_page }}</span>
          <button class="page-btn" :disabled="meta.current_page >= meta.last_page" @click="listFilters.page++">›</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.stat-card { border-radius: 12px; border: 1px solid var(--line); background: var(--card); padding: 14px 16px; }
.stat-card-alert { border-color: #fcd34d; background: #fffbeb; }
.stat-label { display: flex; align-items: center; gap: 6px; color: var(--muted); font-size: .82rem; }
.stat-n { font-size: 1.5rem; font-weight: 800; color: var(--ink); line-height: 1; margin-top: 8px; }
.stat-of { font-size: .95rem; font-weight: 600; color: var(--muted); }
.stat-sub { font-size: .76rem; color: var(--muted); margin-top: 4px; }
.dot { width: 8px; height: 8px; border-radius: 9999px; display: inline-block; }

.panel-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.panel-head h3 { font-size: .95rem; font-weight: 700; color: var(--ink); }

.bar { width: 100%; border-radius: 4px 4px 0 0; background: var(--brand); opacity: .85; }

.mini { border-radius: 8px; background: #f6f7f9; padding: 8px; display: grid; gap: 2px; text-align: center; }
.mini-n { font-size: 1rem; font-weight: 700; color: var(--ink); }
.mini-l { font-size: .7rem; color: var(--muted); }

.chip { flex-shrink: 0; font-size: .72rem; font-weight: 700; padding: 2px 8px; border-radius: 9999px; background: var(--brand-soft); color: var(--brand-dark); }
.tag { font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: 1px 6px; border-radius: 4px; background: #eef2ff; color: #4338ca; margin-left: 4px; }
.tag-soft { background: #f3f4f6; color: #4b5563; }

.avatar { width: 32px; height: 32px; border-radius: 9999px; background: var(--brand-soft); color: var(--brand-dark); display: grid; place-items: center; font-size: .72rem; font-weight: 700; flex-shrink: 0; }
.avatar-lg { width: 44px; height: 44px; font-size: .9rem; }
.avatar-warn { background: #fef3c7; color: #b45309; }

.t { width: 100%; border-collapse: collapse; }
.t th { text-align: left; font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); padding: 10px 12px; border-bottom: 1px solid var(--line); background: #fafbfc; }
.t td { padding: 10px 12px; border-bottom: 1px solid var(--line); vertical-align: middle; }
.t tbody tr:hover { background: #fafbfc; }
.row-active, .t tbody tr.row-active:hover { background: var(--brand-soft); }

.mix { display: flex; gap: 2px; height: 6px; border-radius: 9999px; overflow: hidden; }
.mix-seg { display: block; }

.rate { font-size: .8rem; font-weight: 700; padding: 3px 8px; border-radius: 9999px; }
.rate-good { background: #dcfce7; color: #15803d; }
.rate-ok { background: #fef3c7; color: #b45309; }
.rate-none { background: #f3f4f6; color: #6b7280; }

.pill { margin: 0; width: auto; padding: 4px 22px 4px 10px; font-size: .78rem; font-weight: 600; border-radius: 9999px; border: 0; cursor: pointer; }
.pill-hot { background: #fee2e2; color: #b91c1c; }
.pill-warm { background: #fef3c7; color: #b45309; }
.pill-cold { background: #dbeafe; color: #1d4ed8; }
.pill-connected { background: #dcfce7; color: #15803d; }
.pill-pending { background: #f3f4f6; color: #4b5563; }

.page-btn { width: 28px; height: 28px; display: grid; place-items: center; border-radius: 6px; border: 1px solid var(--line); background: transparent; cursor: pointer; }
.page-btn:hover:not(:disabled) { background: #f6f7f9; }
.page-btn:disabled { opacity: .4; cursor: default; }
</style>
