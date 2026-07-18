<script setup lang="ts">
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface BoothRow {
  id: number
  code: string
  hall: string | null
  exhibitor_id: string | null
  exhibitor: string | null
  exhibitor_type: string | null
  scan_mode: 'staff_kiosk' | 'staff' | 'kiosk'
  lead_generation: boolean
  total_scans: number
  unique: number
  scans_today: number
  vs_gate: number
}
interface LeaderRow { id: number, code: string, exhibitor: string | null, total_scans: number }
interface HeatCell { hour: number, scans: number }
interface HeatRow { id: number, code: string, cells: HeatCell[] }
interface Totals { booths: number, total_scans: number, unique_visitors: number, scans_today: number, gate_entries: number }
interface Overview {
  totals: Totals
  leaderboard: LeaderRow[]
  heatmap: { hours: number[], rows: HeatRow[] }
  booths: BoothRow[]
  halls: string[]
  exhibitors: { id: string, name: string }[]
}

const overview = ref<Overview | null>(null)
const loading = ref(true)

async function load(silent = false) {
  if (!silent && !overview.value) loading.value = true
  try {
    overview.value = (await api<any>(`/events/${id}/booths`)).data
  } catch { if (!silent) toast.error('Could not load exhibitors scanning.') }
  finally { loading.value = false }
}

// Live numbers: refresh quietly every 30s while the page is open.
let poll: ReturnType<typeof setInterval> | undefined
onMounted(() => { load(); poll = setInterval(() => load(true), 30_000) })
onBeforeUnmount(() => clearInterval(poll))

// ── Display helpers ─────────────────────────────────────────────────────────────
const fmt = (n: number) => n.toLocaleString()
function hourLabel(h: number) {
  const period = h < 12 ? 'a' : 'p'
  const base = h % 12 === 0 ? 12 : h % 12
  return `${base}${period}`
}

const cards = computed(() => {
  const t = overview.value?.totals
  if (!t) return []
  return [
    { label: 'Active booths', value: fmt(t.booths), sub: 'registered for scanning' },
    { label: 'Total scans', value: fmt(t.total_scans), sub: `${fmt(t.scans_today)} today` },
    { label: 'Unique visitors', value: fmt(t.unique_visitors), sub: 'distinct attendees' },
    { label: 'Reach vs gates', value: t.gate_entries ? `${Math.round(t.unique_visitors / t.gate_entries * 100)}%` : '—', sub: `of ${fmt(t.gate_entries)} gate entries` },
  ]
})

// ── Leaderboard ─────────────────────────────────────────────────────────────────
const maxLeader = computed(() =>
  Math.max(1, ...(overview.value?.leaderboard.map(r => r.total_scans) ?? [0])))

// ── Heatmap ─────────────────────────────────────────────────────────────────────
const maxCell = computed(() =>
  Math.max(1, ...(overview.value?.heatmap.rows.flatMap(r => r.cells.map(c => c.scans)) ?? [0])))
function cellColor(scans: number) {
  if (!scans) return '#f1f0f7'
  const t = scans / maxCell.value
  if (t > 0.75) return '#d97706'
  if (t > 0.5) return '#f59e0b'
  if (t > 0.25) return '#fbbf24'
  return '#fde68a'
}

// ── Table filters (client-side) ─────────────────────────────────────────────────
const search = ref('')
const hall = ref('')
const hallFilterOptions = computed(() => ['All Hall', ...(overview.value?.halls ?? [])])

const filteredBooths = computed(() => {
  const rows = overview.value?.booths ?? []
  const term = search.value.trim().toLowerCase()
  return rows.filter((b) => {
    if (hall.value && hall.value !== 'All Hall' && b.hall !== hall.value) return false
    if (!term) return true
    return b.code.toLowerCase().includes(term) || (b.exhibitor ?? '').toLowerCase().includes(term)
  })
})

const SCAN_MODE_LABEL: Record<string, string> = {
  staff_kiosk: 'Staff + Kiosk', staff: 'Staff only', kiosk: 'Kiosk only',
}

// ── Add / Edit booth drawer ─────────────────────────────────────────────────────
const HALLS = ['Hall A', 'Hall B', 'Hall C', 'Outdoor / Entrance']
const SCAN_MODES = [
  { value: 'staff_kiosk', label: 'Staff + Kiosk' },
  { value: 'staff', label: 'Staff only' },
  { value: 'kiosk', label: 'Kiosk only' },
]

const drawer = reactive({ open: false, mode: 'create' as 'create' | 'edit', boothId: 0 })
const saving = ref(false)
const error = ref('')
const form = reactive({
  code: '', hall: '', exhibitor: '', scan_mode: 'staff_kiosk', lead_generation: false,
})

// A booth saved with a custom hall still shows it when edited.
const hallOptions = computed(() =>
  form.hall && !HALLS.includes(form.hall) ? [form.hall, ...HALLS] : HALLS)
const exhibitorOptions = computed(() =>
  (overview.value?.exhibitors ?? []).map(e => ({ value: e.id, label: e.name })))

function openCreate() {
  Object.assign(form, { code: '', hall: '', exhibitor: '', scan_mode: 'staff_kiosk', lead_generation: false })
  drawer.mode = 'create'; drawer.boothId = 0; error.value = ''; drawer.open = true
}
function openEdit(b: BoothRow) {
  Object.assign(form, {
    code: b.code, hall: b.hall || '', exhibitor: b.exhibitor_id || '',
    scan_mode: b.scan_mode, lead_generation: b.lead_generation,
  })
  drawer.mode = 'edit'; drawer.boothId = b.id; error.value = ''; drawer.open = true
}

const formValid = computed(() => !!form.code.trim())

async function saveBooth() {
  if (!form.code.trim()) { error.value = 'Please enter a booth code.'; return }
  error.value = ''; saving.value = true
  const body = {
    code: form.code.trim(), hall: form.hall.trim() || null,
    exhibitor: form.exhibitor || null, scan_mode: form.scan_mode,
    lead_generation: form.lead_generation,
  }
  try {
    if (drawer.mode === 'create') await api(`/events/${id}/booths`, { method: 'POST', body })
    else await api(`/events/${id}/booths/${drawer.boothId}`, { method: 'PATCH', body })
    await load(true)
    drawer.open = false
    toast.success(drawer.mode === 'create' ? 'Booth added' : 'Booth updated')
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save booth.'
  } finally { saving.value = false }
}

async function removeBooth(b: BoothRow) {
  if (!confirm(`Remove booth "${b.code}"? Its scan history is kept.`)) return
  try {
    await api(`/events/${id}/booths/${b.id}`, { method: 'DELETE' })
    await load(true)
    toast.success('Booth removed')
  } catch { toast.error('Could not remove booth.') }
}
</script>

<template>
  <div class="max-w-275">
    <!-- Header -->
    <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
      <div>
        <h2 class="section-title m-0">Exhibitors Scanning</h2>
        <p class="muted text-[.86rem] mt-0.5 mb-0">Booth-level footfall, separate from lead capture.</p>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <div class="relative">
          <AppIcon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
          <input v-model="search" placeholder="Search booth or exhibitor…" class="!pl-9 !m-0 w-64 max-w-full">
        </div>
        <select v-model="hall" class="!m-0 !w-auto">
          <option v-for="h in hallFilterOptions" :key="h" :value="h === 'All Hall' ? '' : h">{{ h }}</option>
        </select>
        <button class="btn" @click="openCreate">
          <AppIcon name="plus" class="w-3.5 h-3.5" />
          Add Booth
        </button>
      </div>
    </div>

    <div v-if="loading" class="card flex items-center justify-center gap-2.5 py-12 text-muted text-[.88rem]">
      <svg class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
        <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
      </svg>
      Loading exhibitors scanning…
    </div>

    <template v-else-if="overview">
      <!-- KPI cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <div v-for="c in cards" :key="c.label" class="card p-4">
          <div class="text-[.78rem] text-muted font-medium">{{ c.label }}</div>
          <div class="text-[1.6rem] font-bold text-ink mt-1 leading-none">{{ c.value }}</div>
          <div class="text-[.76rem] text-muted mt-1.5">{{ c.sub }}</div>
        </div>
      </div>

      <!-- Leaderboard + heatmap -->
      <div class="grid lg:grid-cols-2 gap-4 mb-5">
        <!-- Booth traffic leaderboard -->
        <div class="card">
          <div class="flex items-center justify-between mb-4">
            <div class="font-bold text-[.98rem]">Booth traffic leaderboard</div>
            <div class="text-[.76rem] text-muted font-medium">Total Scan</div>
          </div>
          <div v-if="overview.leaderboard.length" class="flex flex-col gap-4">
            <div v-for="r in overview.leaderboard" :key="r.id" class="flex items-center gap-3">
              <span class="w-40 shrink-0 text-[.86rem] text-ink truncate" :title="`${r.code} · ${r.exhibitor ?? ''}`">
                <strong>{{ r.code }}</strong><template v-if="r.exhibitor"> · {{ r.exhibitor }}</template>
              </span>
              <div class="flex-1 h-2.5 bg-[#f1f0f7] rounded-full overflow-hidden">
                <div class="h-full bg-[#6352e7] rounded-full transition-all" :style="{ width: `${Math.round(r.total_scans / maxLeader * 100)}%` }" />
              </div>
              <span class="w-12 shrink-0 text-right text-[.9rem] font-semibold text-ink">{{ fmt(r.total_scans) }}</span>
            </div>
          </div>
          <p v-else class="muted text-[.86rem] my-6 text-center">No booth scans recorded yet.</p>
        </div>

        <!-- Visit intensity heatmap -->
        <div class="card">
          <div class="font-bold text-[.98rem] mb-4">Visit intensity — booths × hour</div>
          <div v-if="overview.heatmap.rows.length" class="overflow-x-auto">
            <table class="heat">
              <thead>
                <tr>
                  <th class="text-left" />
                  <th v-for="h in overview.heatmap.hours" :key="h" class="text-[.72rem] text-muted font-medium px-1 pb-2">{{ hourLabel(h) }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in overview.heatmap.rows" :key="r.id">
                  <td class="pr-3 text-[.82rem] font-semibold text-ink whitespace-nowrap">{{ r.code }}</td>
                  <td v-for="c in r.cells" :key="c.hour" class="p-[3px]">
                    <div
                      class="h-5 rounded-[4px]"
                      :style="{ background: cellColor(c.scans) }"
                      :title="`${r.code} · ${hourLabel(c.hour)} — ${fmt(c.scans)} scans`"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="muted text-[.86rem] my-6 text-center">Not enough data to chart yet.</p>
        </div>
      </div>

      <!-- All booths -->
      <div class="mb-2 font-semibold text-[.92rem]">All booths</div>
      <div class="card p-0 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="booths-table">
            <thead>
              <tr>
                <th>Booth</th>
                <th>Exhibitor</th>
                <th>Scan mode</th>
                <th>Total scans</th>
                <th>Unique</th>
                <th>VS. Gate entries</th>
                <th class="w-24" />
              </tr>
            </thead>
            <tbody>
              <tr v-if="!filteredBooths.length">
                <td colspan="7" class="py-10 text-center muted">
                  {{ overview.booths.length ? 'No booths match this search.' : 'No booths yet — add your first exhibitor booth to start tracking footfall.' }}
                </td>
              </tr>
              <tr v-for="b in filteredBooths" :key="b.id">
                <td>
                  <div class="flex items-center gap-2">
                    <span class="font-semibold text-ink text-[.9rem]">{{ b.code }}</span>
                    <span v-if="b.lead_generation" class="lead-tag" title="This booth also captures leads">Lead</span>
                  </div>
                  <div v-if="b.hall" class="text-muted text-[.76rem]">{{ b.hall }}</div>
                </td>
                <td class="text-[.88rem]">{{ b.exhibitor || '—' }}</td>
                <td class="text-[.84rem] text-muted">{{ SCAN_MODE_LABEL[b.scan_mode] }}</td>
                <td class="text-[.88rem] font-semibold">{{ fmt(b.total_scans) }}</td>
                <td class="text-[.88rem]">{{ fmt(b.unique) }}</td>
                <td class="text-[.88rem]">
                  <span v-if="overview.totals.gate_entries">{{ b.vs_gate }}%</span>
                  <span v-else class="muted">—</span>
                </td>
                <td>
                  <div class="flex items-center justify-end gap-1.5">
                    <button class="btn ghost text-[.76rem] px-2 py-1" @click="openEdit(b)">Edit</button>
                    <button class="text-[#dc2626] text-[.76rem] font-medium px-1.5 hover:underline" @click="removeBooth(b)">Delete</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <!-- ── Add / Edit booth ─────────────────────────────────────────────── -->
    <Drawer v-if="drawer.open" :title="drawer.mode === 'create' ? 'Add Booth' : 'Edit Booth'" @close="drawer.open = false">
      <p class="muted text-[.84rem] -mt-1 mb-5">
        {{ drawer.mode === 'create' ? 'Register a new exhibitor booth for scanning.' : 'Update this booth’s scanning setup.' }}
      </p>

      <div class="mb-4">
        <AppInput v-model="form.code" label="Booth code" required placeholder="e.g. A-12" />
      </div>

      <div class="mb-4">
        <AppSelect v-model="form.hall" label="Hall" placeholder="Select" :options="hallOptions" />
      </div>

      <div class="mb-4">
        <AppSelect
          v-model="form.exhibitor" label="Exhibitor" placeholder="Select"
          :options="exhibitorOptions"
          :hint="exhibitorOptions.length ? '' : 'No exhibitors added to this event yet.'"
        />
      </div>

      <!-- Scan mode -->
      <div class="mb-4">
        <label class="block mb-1.5">Scan Mode</label>
        <div class="flex gap-2 flex-wrap">
          <label
            v-for="m in SCAN_MODES" :key="m.value"
            class="scan-pill" :class="{ active: form.scan_mode === m.value }"
          >
            <input v-model="form.scan_mode" type="radio" :value="m.value" class="sr-only">
            <span class="radio-dot" :class="{ on: form.scan_mode === m.value }" />
            {{ m.label }}
          </label>
        </div>
      </div>

      <!-- Lead generation toggle -->
      <div class="flex items-start justify-between gap-4 py-3 border-t border-line mt-1">
        <div class="min-w-0">
          <div class="text-[.9rem] font-medium text-ink">Lead generation for this booth</div>
          <div class="text-[.78rem] text-muted mt-0.5">
            When enabled, staff scans at this booth can also capture a qualified lead, not just a visit.
          </div>
        </div>
        <button
          type="button" role="switch" :aria-checked="form.lead_generation"
          class="relative w-10 h-6 rounded-full transition-colors shrink-0 mt-0.5"
          :class="form.lead_generation ? 'bg-[#6352e7]' : 'bg-gray-300'"
          @click="form.lead_generation = !form.lead_generation"
        >
          <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform" :class="form.lead_generation ? 'translate-x-4' : ''" />
        </button>
      </div>

      <p v-if="error" class="error">{{ error }}</p>

      <div class="modal-actions border-t border-line pt-4 mt-2 !justify-start">
        <button class="btn" :disabled="saving || !formValid" @click="saveBooth">
          {{ saving ? 'Saving…' : (drawer.mode === 'create' ? 'Create Booth' : 'Update Booth') }}
        </button>
        <button class="btn soft" @click="drawer.open = false">Cancel</button>
      </div>
    </Drawer>
  </div>
</template>

<style scoped>
.heat { border-collapse: collapse; }
.heat td, .heat th { text-align: center; }

.booths-table { width: 100%; border-collapse: collapse; }
.booths-table th { text-align: left; font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); padding: 10px 14px; border-bottom: 1px solid var(--line); background: #fafbfc; }
.booths-table td { padding: 12px 14px; border-bottom: 1px solid var(--line); vertical-align: middle; }
.booths-table tbody tr:hover { background: #fafbfc; }
.booths-table tbody tr:last-child td { border-bottom: 0; }

.lead-tag { display: inline-block; padding: 1px 7px; font-size: .68rem; font-weight: 600; border-radius: 9999px; background: #ecfdf5; color: #047857; }

.btn.soft { background: #efeafd; color: #6352e7; border-color: transparent; }
.btn.soft:hover { background: #e4ddfb; }

.scan-pill { display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px; border: 1px solid var(--line); border-radius: 10px; font-size: .84rem; font-weight: 500; color: var(--ink); cursor: pointer; transition: border-color .15s, background .15s; }
.scan-pill:hover { border-color: #c9c4f5; }
.scan-pill.active { border-color: #6352e7; background: #f7f6ff; }
.radio-dot { width: 15px; height: 15px; border-radius: 9999px; border: 2px solid #d7dae1; background: #fff; display: inline-grid; place-items: center; flex-shrink: 0; }
.radio-dot.on { border-color: #6352e7; }
.radio-dot.on::after { content: ''; width: 7px; height: 7px; border-radius: 9999px; background: #6352e7; }
</style>
