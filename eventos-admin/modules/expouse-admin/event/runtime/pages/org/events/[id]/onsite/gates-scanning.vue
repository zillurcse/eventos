<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface GateRow {
  id: number
  name: string
  location: string | null
  staff: number
  kiosks: number
  mode: string
  direction: 'both' | 'in' | 'out'
  reentry: 'unlimited' | 'single' | 'daily'
  entries: number
  exits: number
  inside: number
  entries_today: number
  rating: 'following' | 'slow' | 'idle'
}
interface HourPoint { hour: number, entries: number }
interface DayGroup { date: string, label: string, hours: HourPoint[] }
interface Totals {
  inside: number, entries_today: number, exits_today: number
  registered: number, no_shows: number, no_show_rate: number
}
interface Overview { totals: Totals, by_hour: DayGroup[], gates: GateRow[] }

const overview = ref<Overview | null>(null)
const loading = ref(true)

async function load(silent = false) {
  if (!silent && !overview.value) loading.value = true
  try {
    overview.value = (await api<any>(`/events/${id}/gates`)).data
  } catch { if (!silent) toast.error('Could not load gates scanning.') }
  finally { loading.value = false }
}

// Live numbers: refresh quietly every 30s while the page is open.
let poll: ReturnType<typeof setInterval> | undefined
onMounted(() => { load(); poll = setInterval(() => load(true), 30_000) })
onBeforeUnmount(() => clearInterval(poll))

// ── KPI cards ─────────────────────────────────────────────────────────────────
const fmt = (n: number) => n.toLocaleString()
const cards = computed(() => {
  const t = overview.value?.totals
  if (!t) return []
  return [
    { label: 'Currently inside', value: fmt(t.inside), sub: 'entries − exits, live' },
    { label: 'Total entries today', value: fmt(t.entries_today), sub: `of ${fmt(t.registered)} registered` },
    { label: 'Total exits today', value: fmt(t.exits_today), sub: 're-entries allowed' },
    { label: 'No-shows', value: `${t.no_show_rate}%`, sub: 'never scanned in' },
  ]
})

// ── Entries-by-hour chart ─────────────────────────────────────────────────────
const maxHourEntries = computed(() =>
  Math.max(1, ...(overview.value?.by_hour.flatMap(d => d.hours.map(h => h.entries)) ?? [0])))

const RATING = {
  following: { label: 'Following', cls: 'pill-following' },
  slow: { label: 'Slow scan', cls: 'pill-slow' },
  idle: { label: 'Idle', cls: 'pill-idle' },
} as const

function staffKiosk(g: GateRow) {
  const bits = []
  if (g.staff) bits.push(`${g.staff} Staff`)
  if (g.kiosks) bits.push(`${g.kiosks} Kiosk`)
  return bits.join(', ') || '—'
}

// ── Add / Edit gate drawer ────────────────────────────────────────────────────
const HALLS = ['Hall A', 'Hall B', 'Hall C', 'Outdoor / Entrance']
const DIRECTIONS = [
  { value: 'both', label: 'Entry + Exit' },
  { value: 'in', label: 'Entry only' },
  { value: 'out', label: 'Exit only' },
]
const REENTRY = [
  { value: 'unlimited', label: 'Allow unlimited re-entry' },
  { value: 'single', label: 'Single use - No re-entry' },
  { value: 'daily', label: 'Reset daily (multi day event)' },
]
const SCAN_MODES = [
  { value: 'staff_kiosk', label: 'Staff + Kiosk' },
  { value: 'staff', label: 'Staff only' },
  { value: 'kiosk', label: 'Kiosk only' },
]
const directionLabel = (v: string) => DIRECTIONS.find(d => d.value === v)?.label || v
const reentryShort = (v: string) =>
  ({ unlimited: 'Unlimited re-entry', single: 'Single use', daily: 'Resets daily' } as Record<string, string>)[v] || v

const drawer = reactive({ open: false, mode: 'create' as 'create' | 'edit', gateId: 0 })
const saving = ref(false)
const error = ref('')
const form = reactive({
  name: '', location: '', direction: 'both', reentry: 'unlimited',
  scan_mode: 'staff_kiosk', staff: 1, kiosks: 0,
})

// A gate saved with a custom location still shows it when edited.
const hallOptions = computed(() =>
  form.location && !HALLS.includes(form.location) ? [form.location, ...HALLS] : HALLS)

// Counts follow the chosen scan mode.
watch(() => form.scan_mode, (m) => {
  if (m === 'staff') { form.kiosks = 0; form.staff = form.staff || 1 }
  else if (m === 'kiosk') { form.staff = 0; form.kiosks = form.kiosks || 1 }
})

function openCreate() {
  Object.assign(form, {
    name: '', location: '', direction: 'both', reentry: 'unlimited',
    scan_mode: 'staff_kiosk', staff: 1, kiosks: 0,
  })
  drawer.mode = 'create'; drawer.gateId = 0; error.value = ''; drawer.open = true
}
function openEdit(g: GateRow) {
  Object.assign(form, {
    name: g.name, location: g.location || '', direction: g.direction, reentry: g.reentry,
    scan_mode: g.staff > 0 && g.kiosks > 0 ? 'staff_kiosk' : (g.kiosks > 0 ? 'kiosk' : 'staff'),
    staff: g.staff, kiosks: g.kiosks,
  })
  drawer.mode = 'edit'; drawer.gateId = g.id; error.value = ''; drawer.open = true
}

const formValid = computed(() =>
  !!form.name.trim() && (form.staff > 0 || form.kiosks > 0))

async function saveGate() {
  if (!form.name.trim()) { error.value = 'Please name the gate.'; return }
  if (!form.staff && !form.kiosks) { error.value = 'A gate needs at least one staff member or kiosk.'; return }
  error.value = ''; saving.value = true
  const body = {
    name: form.name.trim(), location: form.location.trim() || null,
    scan_mode: form.scan_mode, staff: form.staff || 0, kiosks: form.kiosks || 0,
    direction: form.direction, reentry: form.reentry,
  }
  try {
    if (drawer.mode === 'create') await api(`/events/${id}/gates`, { method: 'POST', body })
    else await api(`/events/${id}/gates/${drawer.gateId}`, { method: 'PATCH', body })
    await load(true)
    drawer.open = false
    toast.success(drawer.mode === 'create' ? 'Gate added' : 'Gate updated')
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save gate.'
  } finally { saving.value = false }
}

async function removeGate(g: GateRow) {
  if (!confirm(`Remove "${g.name}"? Its scan history is kept.`)) return
  try {
    await api(`/events/${id}/gates/${g.id}`, { method: 'DELETE' })
    await load(true)
    toast.success('Gate removed')
  } catch { toast.error('Could not remove gate.') }
}

// ── No-show list drawer ───────────────────────────────────────────────────────
interface NoShow {
  id: string, name: string, email: string | null, phone: string | null
  company: string | null, job_title: string | null, registered_at: string | null
}
const noShow = reactive({
  open: false, loading: false, exporting: false,
  rows: [] as NoShow[], search: '', page: 1, per_page: 10,
  meta: { current_page: 1, last_page: 1, total: 0, from: 0, to: 0 },
})

async function loadNoShows() {
  noShow.loading = true
  try {
    const q = new URLSearchParams({ search: noShow.search, page: String(noShow.page), per_page: String(noShow.per_page) })
    const res = await api<any>(`/events/${id}/gates/no-shows?${q}`)
    noShow.rows = res.data
    noShow.meta = res.meta
  } catch { toast.error('Could not load the no-show list.') }
  finally { noShow.loading = false }
}

function openNoShows() {
  noShow.open = true; noShow.search = ''; noShow.page = 1
  loadNoShows()
}

let noShowTimer: ReturnType<typeof setTimeout> | undefined
watch(() => noShow.search, () => {
  clearTimeout(noShowTimer)
  noShowTimer = setTimeout(() => { noShow.page = 1; loadNoShows() }, 350)
})
watch(() => noShow.page, () => loadNoShows())

async function exportNoShows() {
  noShow.exporting = true
  try {
    const res = await api<any>(`/events/${id}/gates/no-shows/export`, { method: 'POST' })
    const blob = new Blob([res.data.csv], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url; a.download = res.data.filename; a.click()
    URL.revokeObjectURL(url)
  } catch { toast.error('Export failed.') }
  finally { noShow.exporting = false }
}

function regDate(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }) : '—'
}
</script>

<template>
  <div class="max-w-275">
    <!-- Header -->
    <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
      <div>
        <h2 class="section-title m-0">Gates Scanning</h2>
        <p class="muted text-[.86rem] mt-0.5 mb-0">Entry / exit tracking across all venue gates.</p>
      </div>
      <div class="flex items-center gap-2">
        <button class="btn soft" @click="openNoShows">No-show List</button>
        <button class="btn" @click="openCreate">
          <AppIcon name="plus" class="w-3.5 h-3.5" />
          Add Gate
        </button>
      </div>
    </div>

    <div v-if="loading" class="card flex items-center justify-center gap-2.5 py-12 text-muted text-[.88rem]">
      <svg class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
        <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
      </svg>
      Loading gates scanning…
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

      <!-- Entries by hour -->
      <div class="card mb-5">
        <div class="font-bold text-[.98rem] mb-4">Entries by hour</div>
        <div v-if="overview.by_hour.length" class="chart-panel">
          <div class="flex items-end gap-5 min-w-max">
            <div v-for="day in overview.by_hour" :key="day.date" class="flex flex-col">
              <div class="flex items-end gap-2.5">
                <div v-for="h in day.hours" :key="h.hour" class="flex flex-col items-center gap-1.5 w-7">
                  <div class="chart-track">
                    <div
                      class="chart-bar" :style="{ height: `${Math.max(h.entries ? 6 : 0, Math.round(h.entries / maxHourEntries * 100))}%` }"
                      :title="`${day.label}, ${h.hour}:00 — ${fmt(h.entries)} entries`"
                    />
                  </div>
                  <span class="text-[.7rem] text-muted">{{ h.hour }}h</span>
                </div>
              </div>
              <div class="text-center text-[.72rem] font-semibold text-muted mt-2 border-t border-[#dcd8f5] pt-1.5">{{ day.label }}</div>
            </div>
          </div>
        </div>
        <p v-else class="muted text-[.86rem] my-6 text-center">No gate scans recorded yet.</p>
      </div>

      <!-- Gate-wise detail -->
      <div class="mb-2 font-semibold text-[.92rem]">Gate-wise detail</div>
      <div class="card p-0 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="gates-table">
            <thead>
              <tr>
                <th>Gate</th>
                <th>Type</th>
                <th>Entries</th>
                <th>Exits</th>
                <th>Inside</th>
                <th>Staff/Kiosk</th>
                <th>Rating</th>
                <th class="w-24" />
              </tr>
            </thead>
            <tbody>
              <tr v-if="!overview.gates.length">
                <td colspan="8" class="py-10 text-center muted">
                  No gates yet — add your first venue gate to start tracking entries.
                </td>
              </tr>
              <tr v-for="g in overview.gates" :key="g.id">
                <td>
                  <div class="font-semibold text-ink text-[.9rem]">{{ g.name }}</div>
                  <div v-if="g.location" class="text-muted text-[.76rem]">{{ g.location }}</div>
                </td>
                <td>
                  <div class="text-[.88rem]">{{ g.mode }}</div>
                  <div class="text-muted text-[.74rem]">{{ directionLabel(g.direction) }} · {{ reentryShort(g.reentry) }}</div>
                </td>
                <td class="text-[.88rem] font-semibold">{{ fmt(g.entries) }}</td>
                <td class="text-[.88rem] font-semibold">{{ fmt(g.exits) }}</td>
                <td class="text-[.88rem] font-semibold">{{ fmt(g.inside) }}</td>
                <td class="text-[.86rem]">{{ staffKiosk(g) }}</td>
                <td><span class="pill" :class="RATING[g.rating].cls">{{ RATING[g.rating].label }}</span></td>
                <td>
                  <div class="flex items-center justify-end gap-1.5">
                    <button class="btn ghost text-[.76rem] px-2 py-1" @click="openEdit(g)">Edit</button>
                    <button class="text-[#dc2626] text-[.76rem] font-medium px-1.5 hover:underline" @click="removeGate(g)">Delete</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <!-- ── Add / Edit gate ──────────────────────────────────────────────── -->
    <Drawer v-if="drawer.open" :title="drawer.mode === 'create' ? 'Add Gate' : 'Edit Gate'" @close="drawer.open = false">
      <p class="muted text-[.84rem] -mt-1 mb-5">
        {{ drawer.mode === 'create' ? 'Set up a new entry/exit point for this event.' : 'Update this entry/exit point.' }}
      </p>

      <div class="mb-4">
        <AppInput v-model="form.name" label="Gate Name" required placeholder="Enter Gate Name" />
      </div>

      <div class="mb-4">
        <AppSelect v-model="form.location" label="Hall / Location" placeholder="Select" :options="hallOptions" />
      </div>

      <div class="mb-4">
        <AppSelect v-model="form.direction" label="Direction" placeholder="Select" :options="DIRECTIONS" />
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

      <div class="flex gap-3 mb-4 flex-wrap">
        <div class="flex-1 min-w-[120px]">
          <AppInput v-model.number="form.staff" type="number" label="Staff assigned" min="0" :disabled="form.scan_mode === 'kiosk'" />
        </div>
        <div class="flex-1 min-w-[120px]">
          <AppInput v-model.number="form.kiosks" type="number" label="Kiosks assigned" min="0" :disabled="form.scan_mode === 'staff'" />
        </div>
      </div>

      <div class="mb-4">
        <AppSelect v-model="form.reentry" label="Re-entry Policy" placeholder="Select" :options="REENTRY" />
      </div>

      <p v-if="error" class="error">{{ error }}</p>

      <div class="modal-actions border-t border-line pt-4 mt-2 !justify-start">
        <button class="btn" :disabled="saving || !formValid" @click="saveGate">
          {{ saving ? 'Saving…' : (drawer.mode === 'create' ? 'Create Gate' : 'Update Gate') }}
        </button>
        <button class="btn soft" @click="drawer.open = false">Cancel</button>
      </div>
    </Drawer>

    <!-- ── No-show list ─────────────────────────────────────────────────── -->
    <Drawer v-if="noShow.open" title="No-show list" @close="noShow.open = false">
      <p class="muted text-[.84rem] mt-0 mb-3">
        Registered attendees who never scanned in at any gate
        <template v-if="noShow.meta.total"> — {{ fmt(noShow.meta.total) }} total</template>.
      </p>

      <div class="flex items-center gap-2 mb-3">
        <div class="relative flex-1">
          <AppIcon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
          <input v-model="noShow.search" placeholder="Search name, email or company" class="!pl-9 !m-0 w-full">
        </div>
        <button class="btn soft whitespace-nowrap" :disabled="noShow.exporting || !noShow.meta.total" @click="exportNoShows">
          <AppIcon name="download" class="w-3.5 h-3.5" />
          {{ noShow.exporting ? 'Exporting…' : 'Export (.CSV)' }}
        </button>
      </div>

      <div v-if="noShow.loading && !noShow.rows.length" class="py-10 text-center muted text-[.86rem]">Loading…</div>
      <div v-else-if="!noShow.rows.length" class="py-10 text-center muted text-[.86rem]">
        {{ noShow.search ? 'No matches.' : 'Everyone registered has scanned in. 🎉' }}
      </div>
      <div v-else class="flex flex-col">
        <div v-for="p in noShow.rows" :key="p.id" class="flex items-center justify-between gap-3 py-2.5 border-b border-line last:border-b-0">
          <div class="min-w-0">
            <div class="font-semibold text-ink text-[.88rem] truncate">{{ p.name }}</div>
            <div class="text-muted text-[.78rem] truncate">
              {{ p.email || '—' }}<template v-if="p.company"> · {{ p.company }}</template>
            </div>
          </div>
          <div class="text-right shrink-0">
            <div class="text-[.76rem] text-muted">registered</div>
            <div class="text-[.8rem] text-ink">{{ regDate(p.registered_at) }}</div>
          </div>
        </div>
      </div>

      <div v-if="noShow.meta.last_page > 1" class="flex items-center justify-end gap-2 pt-3 text-[.84rem] text-muted">
        <button class="page-btn" :disabled="noShow.meta.current_page <= 1" @click="noShow.page--">‹</button>
        <span>Page {{ noShow.meta.current_page }} / {{ noShow.meta.last_page }}</span>
        <button class="page-btn" :disabled="noShow.meta.current_page >= noShow.meta.last_page" @click="noShow.page++">›</button>
      </div>
    </Drawer>
  </div>
</template>

<style scoped>
.chart-panel { background: #efedfb; border-radius: 12px; padding: 18px 20px 12px; overflow-x: auto; }
.chart-track { height: 150px; width: 100%; display: flex; align-items: flex-end; }
.chart-bar { width: 100%; border-radius: 6px 6px 2px 2px; background: #4b7cf9; transition: height .3s ease; min-height: 0; }

.gates-table { width: 100%; border-collapse: collapse; }
.gates-table th { text-align: left; font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); padding: 10px 14px; border-bottom: 1px solid var(--line); background: #fafbfc; }
.gates-table td { padding: 12px 14px; border-bottom: 1px solid var(--line); vertical-align: middle; }
.gates-table tbody tr:hover { background: #fafbfc; }
.gates-table tbody tr:last-child td { border-bottom: 0; }

.pill { display: inline-block; padding: 3px 10px; font-size: .74rem; font-weight: 600; border-radius: 9999px; white-space: nowrap; }
.pill-following { background: #dcfce7; color: #15803d; }
.pill-slow { background: #fef3c7; color: #b45309; }
.pill-idle { background: #f3f4f6; color: #4b5563; }

.btn.soft { background: #efeafd; color: #6352e7; border-color: transparent; }
.btn.soft:hover { background: #e4ddfb; }

.scan-pill { display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px; border: 1px solid var(--line); border-radius: 10px; font-size: .84rem; font-weight: 500; color: var(--ink); cursor: pointer; transition: border-color .15s, background .15s; }
.scan-pill:hover { border-color: #c9c4f5; }
.scan-pill.active { border-color: #6352e7; background: #f7f6ff; }
.radio-dot { width: 15px; height: 15px; border-radius: 9999px; border: 2px solid #d7dae1; background: #fff; display: inline-grid; place-items: center; flex-shrink: 0; }
.radio-dot.on { border-color: #6352e7; }
.radio-dot.on::after { content: ''; width: 7px; height: 7px; border-radius: 9999px; background: #6352e7; }

.page-btn { width: 28px; height: 28px; display: grid; place-items: center; border-radius: 6px; border: 1px solid var(--line); background: transparent; cursor: pointer; }
.page-btn:hover:not(:disabled) { background: #f6f7f9; }
.page-btn:disabled { opacity: .4; cursor: default; }
</style>
