<script setup lang="ts">
definePageMeta({
  middleware: 'exhibitor',
  feature: 'lead_export',
  title: 'Lead Export',
  subtitle: 'Choose the columns your CRM expects, narrow the selection, and take the book with you.',
})

const api = useApi()

interface Column { key: string, label: string, default: boolean }
interface Coverage {
  total: number, never_exported: number, exported: number, recent: number, last_export_at: string | null
}

const FORMATS = [
  { key: 'csv', label: 'CSV', hint: 'Plain UTF-8 — the safe default for any CRM importer.' },
  { key: 'excel', label: 'Excel CSV', hint: 'Adds a byte-order mark so Excel keeps accents intact.' },
  { key: 'json', label: 'JSON', hint: 'One object per lead, for a custom integration.' },
]
const RATINGS = ['hot', 'warm', 'cold']
const STATUSES = ['pending', 'connected', 'contacted', 'qualified', 'won', 'lost']
const SOURCES = [
  { key: 'scan', label: 'Badge scan' },
  { key: 'manual', label: 'Added by hand' },
  { key: 'connect', label: 'Connected in app' },
  { key: 'import', label: 'Imported' },
]

const columns = ref<Column[]>([])
const team = ref<{ id: number, name: string }[]>([])
const matched = ref(0)
const sample = ref<Record<string, string | null>[]>([])
const coverage = ref<Coverage>({ total: 0, never_exported: 0, exported: 0, recent: 0, last_export_at: null })
const loading = ref(true)
const suspended = ref(false)

const filters = reactive({
  search: '', rating: '', status: '', source: '', rep: '', from: '', to: '', only_new: false,
})
const selected = ref<Set<string>>(new Set())
const format = ref('csv')
const markExported = ref(true)

// ── Selection summary ────────────────────────────────────────────────────────
function query() {
  const q = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (value === '' || value === false) return
    q.set(key, value === true ? '1' : String(value))
  })
  return q
}

async function load(first = false) {
  loading.value = true
  try {
    const res = await api<any>(`/exhibitor/leads/export/summary?${query()}`)
    matched.value = res.data.matched
    sample.value = res.data.sample
    coverage.value = res.data.coverage
    team.value = res.team
    if (first) {
      columns.value = res.columns
      selected.value = new Set(res.columns.filter((c: Column) => c.default).map((c: Column) => c.key))
    }
  } catch (e: any) {
    if (e?.response?.status === 403 || e?.status === 403) suspended.value = true
  } finally {
    loading.value = false
  }
}

let timer: ReturnType<typeof setTimeout> | undefined
watch(filters, () => {
  clearTimeout(timer)
  timer = setTimeout(() => load(), 350)
}, { deep: true })

// ── Columns ──────────────────────────────────────────────────────────────────
function toggle(key: string) {
  const next = new Set(selected.value)
  next.has(key) ? next.delete(key) : next.add(key)
  selected.value = next
}
function selectAll() {
  selected.value = new Set(columns.value.map(c => c.key))
}
function selectDefaults() {
  selected.value = new Set(columns.value.filter(c => c.default).map(c => c.key))
}

/** The sample table only shows what will actually be in the file. */
const previewColumns = computed(() => columns.value.filter(c => selected.value.has(c.key)))

// ── Download ─────────────────────────────────────────────────────────────────
const downloading = ref(false)
const done = ref('')
const error = ref('')

async function download() {
  if (!selected.value.size || !matched.value) return
  downloading.value = true
  error.value = ''
  done.value = ''
  try {
    const body: Record<string, any> = {
      columns: [...selected.value],
      format: format.value,
      mark_exported: markExported.value,
    }
    query().forEach((value, key) => { body[key] = value })

    const res = await api<any>('/exhibitor/leads/export/download', { method: 'POST', body })

    const blob = new Blob([res.data.content], { type: `${res.data.mime};charset=utf-8;` })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = res.data.filename
    a.click()
    URL.revokeObjectURL(url)

    done.value = `${res.data.count} lead${res.data.count === 1 ? '' : 's'} exported to ${res.data.filename}.`
    // Marking rows as exported changes the coverage figures the page shows.
    if (markExported.value) load()
  } catch (e: any) {
    error.value = e?.data?.message || 'The export could not be built.'
  } finally {
    downloading.value = false
  }
}

function label(s: string) { return s.charAt(0).toUpperCase() + s.slice(1) }
function when(iso: string | null) {
  return iso ? new Date(iso).toLocaleString([], { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : 'Never'
}

onMounted(() => load(true))
</script>

<template>
  <div v-if="suspended" class="card"><p class="error">This exhibitor account is suspended.</p></div>

  <div v-else>
    <!-- Export coverage: what has already left the platform -->
    <div class="grid grid-cols-4 gap-3 mb-5 max-sm:grid-cols-2">
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="users" class="w-3.5 h-3.5" /> Leads in total</div>
        <div class="stat-n">{{ coverage.total }}</div>
        <div class="stat-sub">Captured by this booth</div>
      </div>
      <div class="stat-card" :class="{ 'stat-card-alert': coverage.never_exported }">
        <div class="stat-label"><AppIcon name="flag" class="w-3.5 h-3.5" /> Never exported</div>
        <div class="stat-n">{{ coverage.never_exported }}</div>
        <div class="stat-sub">Not yet in your CRM</div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="download" class="w-3.5 h-3.5" /> Exported</div>
        <div class="stat-n">{{ coverage.exported }}</div>
        <div class="stat-sub">{{ coverage.recent }} in the last 7 days</div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="calendar" class="w-3.5 h-3.5" /> Last export</div>
        <div class="stat-n text-[1.05rem] mt-3">{{ when(coverage.last_export_at) }}</div>
        <div class="stat-sub">Across the whole booth</div>
      </div>
    </div>

    <div class="grid grid-cols-3 gap-4 max-lg:grid-cols-1">
      <!-- 1. What to export -->
      <div class="card">
        <div class="panel-head"><h3>1 · What to export</h3></div>

        <label class="lbl mt-4">Search</label>
        <input v-model="filters.search" placeholder="Name, email or company" class="w-full !m-0">

        <div class="grid grid-cols-2 gap-3 mt-3">
          <div>
            <label class="lbl">Rating</label>
            <select v-model="filters.rating" class="w-full !m-0">
              <option value="">All</option>
              <option v-for="r in RATINGS" :key="r" :value="r">{{ label(r) }}</option>
            </select>
          </div>
          <div>
            <label class="lbl">Stage</label>
            <select v-model="filters.status" class="w-full !m-0">
              <option value="">All</option>
              <option v-for="s in STATUSES" :key="s" :value="s">{{ label(s) }}</option>
            </select>
          </div>
          <div>
            <label class="lbl">Capture method</label>
            <select v-model="filters.source" class="w-full !m-0">
              <option value="">All</option>
              <option v-for="s in SOURCES" :key="s.key" :value="s.key">{{ s.label }}</option>
            </select>
          </div>
          <div>
            <label class="lbl">Owner</label>
            <select v-model="filters.rep" class="w-full !m-0">
              <option value="">Anyone</option>
              <option value="unassigned">Unassigned</option>
              <option v-for="m in team" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
          </div>
          <div>
            <label class="lbl">Captured from</label>
            <input v-model="filters.from" type="date" class="w-full !m-0">
          </div>
          <div>
            <label class="lbl">Captured to</label>
            <input v-model="filters.to" type="date" class="w-full !m-0">
          </div>
        </div>

        <label class="opt mt-3">
          <input v-model="filters.only_new" type="checkbox" class="!m-0 mt-0.5">
          <span>
            <span class="font-semibold text-ink text-[.86rem]">Only leads never exported</span>
            <span class="block text-[.78rem] text-muted">Stops the same contacts landing in your CRM twice.</span>
          </span>
        </label>
      </div>

      <!-- 2. Columns -->
      <div class="card">
        <div class="panel-head">
          <h3>2 · Columns</h3>
          <span class="muted text-[.78rem]">{{ selected.size }} of {{ columns.length }}</span>
        </div>
        <div class="flex gap-2 mt-3">
          <button class="btn ghost sm" @click="selectDefaults">Standard set</button>
          <button class="btn ghost sm" @click="selectAll">Everything</button>
        </div>
        <div class="cols mt-3">
          <label v-for="c in columns" :key="c.key" class="col-opt" :class="{ on: selected.has(c.key) }">
            <input type="checkbox" :checked="selected.has(c.key)" class="!m-0" @change="toggle(c.key)">
            <span>{{ c.label }}</span>
          </label>
        </div>
        <p v-if="!selected.size" class="error mt-2">Pick at least one column.</p>
      </div>

      <!-- 3. Format + go -->
      <div class="card">
        <div class="panel-head"><h3>3 · Format</h3></div>
        <label v-for="f in FORMATS" :key="f.key" class="opt mt-3">
          <input v-model="format" type="radio" :value="f.key" class="!m-0 mt-0.5">
          <span>
            <span class="font-semibold text-ink text-[.86rem]">{{ f.label }}</span>
            <span class="block text-[.78rem] text-muted">{{ f.hint }}</span>
          </span>
        </label>

        <label class="opt mt-4">
          <input v-model="markExported" type="checkbox" class="!m-0 mt-0.5">
          <span>
            <span class="font-semibold text-ink text-[.86rem]">Mark these leads as exported</span>
            <span class="block text-[.78rem] text-muted">Leave this off for a test run — it drives the filter above.</span>
          </span>
        </label>

        <div class="summary">
          <div>
            <div class="text-[1.4rem] font-extrabold text-ink leading-none">{{ loading ? '…' : matched }}</div>
            <div class="text-[.76rem] text-muted mt-1">lead{{ matched === 1 ? '' : 's' }} match this selection</div>
          </div>
          <button class="btn" :disabled="downloading || !matched || !selected.size" @click="download">
            <AppIcon name="download" class="w-3.5 h-3.5" />
            {{ downloading ? 'Building…' : 'Download' }}
          </button>
        </div>

        <p v-if="done" class="ok">{{ done }}</p>
        <p v-if="error" class="error mt-2">{{ error }}</p>

        <p class="text-[.74rem] text-muted mt-4">
          Exported files carry personal data the attendee shared with your booth. Handle them under the
          event's privacy terms, and delete copies you no longer need.
        </p>
      </div>
    </div>

    <!-- Preview -->
    <div class="card p-0 overflow-hidden mt-4">
      <div class="panel-head p-4">
        <h3>Preview</h3>
        <span class="muted text-[.78rem]">First {{ sample.length }} row{{ sample.length === 1 ? '' : 's' }} of {{ matched }}</span>
      </div>
      <div class="overflow-x-auto">
        <table class="t">
          <thead>
            <tr><th v-for="c in previewColumns" :key="c.key">{{ c.label }}</th></tr>
          </thead>
          <tbody>
            <tr v-if="!sample.length || !previewColumns.length">
              <td :colspan="Math.max(1, previewColumns.length)" class="py-10 text-center muted">
                {{ previewColumns.length ? 'No leads match this selection.' : 'Choose at least one column.' }}
              </td>
            </tr>
            <tr v-for="(row, i) in sample" v-else :key="i">
              <td v-for="c in previewColumns" :key="c.key">{{ row[c.key] || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.stat-card { border-radius: 12px; border: 1px solid var(--line); background: var(--card); padding: 14px 16px; }
.stat-card-alert { border-color: #fcd34d; background: #fffbeb; }
.stat-label { display: flex; align-items: center; gap: 6px; color: var(--muted); font-size: .82rem; }
.stat-n { font-size: 1.5rem; font-weight: 800; color: var(--ink); line-height: 1; margin-top: 8px; }
.stat-sub { font-size: .76rem; color: var(--muted); margin-top: 4px; }

.panel-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.panel-head h3 { font-size: .95rem; font-weight: 700; color: var(--ink); }

.lbl { display: block; font-size: .8rem; font-weight: 600; color: var(--ink); margin-bottom: 4px; }
.opt { display: flex; gap: 10px; align-items: flex-start; padding: 9px 10px; border: 1px solid var(--line); border-radius: 9px; cursor: pointer; }
.opt:hover { background: #fafbfc; }

.cols { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.col-opt { display: flex; align-items: center; gap: 7px; padding: 6px 9px; border: 1px solid var(--line); border-radius: 8px; font-size: .8rem; cursor: pointer; }
.col-opt.on { background: var(--brand-soft); border-color: var(--brand); color: var(--brand-dark); font-weight: 600; }

.summary { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--line); }
.ok { margin-top: 10px; font-size: .82rem; color: #15803d; font-weight: 600; }

.t { width: 100%; border-collapse: collapse; }
.t th { text-align: left; font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); padding: 10px 12px; border-bottom: 1px solid var(--line); background: #fafbfc; white-space: nowrap; }
.t td { padding: 10px 12px; border-bottom: 1px solid var(--line); font-size: .85rem; white-space: nowrap; max-width: 240px; overflow: hidden; text-overflow: ellipsis; }
</style>
