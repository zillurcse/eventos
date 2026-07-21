<script setup lang="ts">
definePageMeta({
  middleware: 'exhibitor',
  feature: 'lead_qualification',
  title: 'Lead Qualification',
  subtitle: 'Work every captured lead through Budget, Authority, Need and Timeline — then move it down the pipeline.',
})

const api = useApi()

interface Card {
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
  owner: string | null
  owner_id: number | null
  criteria: Record<string, boolean>
  met: number
  score: number
  grade: 'qualified' | 'developing' | 'early' | 'unscored'
  next_step: string | null
  follow_up_at: string | null
  follow_up_due: boolean
  qualified_at: string | null
  ready_to_advance: boolean
  created_at: string
}
interface Column { status: string, label: string, count: number, leads: Card[] }
interface Stats {
  total: number, unscored: number, in_progress: number, qualified: number
  ready_to_advance: number, follow_ups_due: number, no_next_step: number
  won: number, lost: number, avg_score: number, win_rate: number
}

/** What each BANT question actually asks a rep to confirm. */
const CRITERIA = [
  { key: 'budget', label: 'Budget', hint: 'They can fund this, or know who signs it off.' },
  { key: 'authority', label: 'Authority', hint: 'We are talking to a decision maker or their champion.' },
  { key: 'need', label: 'Need', hint: 'There is a real problem we solve, not just curiosity.' },
  { key: 'timeline', label: 'Timeline', hint: 'There is a date or a quarter attached to it.' },
]
const RATINGS = ['hot', 'warm', 'cold']

const columns = ref<Column[]>([])
const stats = ref<Stats>({
  total: 0, unscored: 0, in_progress: 0, qualified: 0, ready_to_advance: 0,
  follow_ups_due: 0, no_next_step: 0, won: 0, lost: 0, avg_score: 0, win_rate: 0,
})
const team = ref<{ id: number, name: string }[]>([])
const loading = ref(true)
const suspended = ref(false)

const filters = reactive({ search: '', rating: '', rep: '', source: '', due: false })

async function load() {
  loading.value = true
  try {
    const q = new URLSearchParams({
      search: filters.search, rating: filters.rating, rep: filters.rep,
      source: filters.source, due: filters.due ? '1' : '',
    })
    const res = await api<any>(`/exhibitor/leads/pipeline?${q}`)
    columns.value = res.data
    stats.value = res.stats
    team.value = res.team
  } catch (e: any) {
    if (e?.response?.status === 403 || e?.status === 403) suspended.value = true
  } finally {
    loading.value = false
  }
}

let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(() => filters.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(load, 350)
})
watch(() => [filters.rating, filters.rep, filters.source, filters.due], load)

// ── Qualifying ───────────────────────────────────────────────────────────────
const editing = ref<Card | null>(null)
const draft = reactive({
  budget: false, authority: false, need: false, timeline: false,
  next_step: '', follow_up_at: '', notes: '', rating: 'cold', status: 'pending',
})
const saving = ref(false)

function open(card: Card) {
  editing.value = card
  Object.assign(draft, {
    budget: card.criteria.budget, authority: card.criteria.authority,
    need: card.criteria.need, timeline: card.criteria.timeline,
    next_step: card.next_step ?? '', follow_up_at: card.follow_up_at ?? '',
    notes: card.notes ?? '', rating: card.rating, status: card.status,
  })
}

/** Live preview of the score while the rep ticks boxes. */
const draftScore = computed(() =>
  Math.round((CRITERIA.filter(c => (draft as any)[c.key]).length / CRITERIA.length) * 100))

const draftGrade = computed(() =>
  draftScore.value === 100 ? 'qualified' : draftScore.value >= 50 ? 'developing' : draftScore.value ? 'early' : 'unscored')

async function save() {
  if (!editing.value) return
  saving.value = true
  try {
    await api(`/exhibitor/leads/${editing.value.id}/qualification`, {
      method: 'PATCH',
      body: {
        budget: draft.budget, authority: draft.authority, need: draft.need, timeline: draft.timeline,
        next_step: draft.next_step || null,
        follow_up_at: draft.follow_up_at || null,
        notes: draft.notes || null,
        rating: draft.rating,
        status: draft.status,
      },
    })
    editing.value = null
    // A saved card usually changes column, so the board is reloaded rather
    // than patched in place.
    await load()
  } finally {
    saving.value = false
  }
}

/** The one-click move offered when all four boxes are ticked. */
async function advance(card: Card) {
  await api(`/exhibitor/leads/${card.id}/qualification`, { method: 'PATCH', body: { status: 'qualified' } })
  await load()
}

// ── Display helpers ──────────────────────────────────────────────────────────
function initials(name: string) {
  const p = name.trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?'
}
function shortDate(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString([], { day: 'numeric', month: 'short' }) : ''
}
function label(s: string) { return s.charAt(0).toUpperCase() + s.slice(1) }

const stageOptions = computed(() => columns.value.map(c => ({ value: c.status, label: c.label })))

onMounted(load)
</script>

<template>
  <div v-if="suspended" class="card"><p class="error">This exhibitor account is suspended.</p></div>

  <div v-else>
    <!-- Pipeline health -->
    <div class="grid grid-cols-6 gap-3 mb-5 max-xl:grid-cols-3 max-sm:grid-cols-2">
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="clipboard" class="w-3.5 h-3.5" /> To qualify</div>
        <div class="stat-n">{{ stats.unscored }}</div>
        <div class="stat-sub">Not scored yet</div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="layers" class="w-3.5 h-3.5" /> In progress</div>
        <div class="stat-n">{{ stats.in_progress }}</div>
        <div class="stat-sub">Partly answered</div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="award" class="w-3.5 h-3.5" /> Fully qualified</div>
        <div class="stat-n">{{ stats.qualified }}</div>
        <div class="stat-sub">All four confirmed</div>
      </div>
      <div class="stat-card" :class="{ 'stat-card-alert': stats.ready_to_advance }">
        <div class="stat-label"><AppIcon name="arrow-right" class="w-3.5 h-3.5" /> Ready to move</div>
        <div class="stat-n">{{ stats.ready_to_advance }}</div>
        <div class="stat-sub">Qualified but not staged</div>
      </div>
      <div class="stat-card" :class="{ 'stat-card-alert': stats.follow_ups_due }">
        <div class="stat-label"><AppIcon name="bell" class="w-3.5 h-3.5" /> Follow-ups due</div>
        <div class="stat-n">{{ stats.follow_ups_due }}</div>
        <div class="stat-sub">{{ stats.no_next_step }} with no next step</div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="target" class="w-3.5 h-3.5" /> Win rate</div>
        <div class="stat-n">{{ stats.win_rate }}%</div>
        <div class="stat-sub">{{ stats.won }} won · {{ stats.lost }} lost</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card p-4 mb-4 flex items-center gap-2.5 flex-wrap">
      <div class="relative flex-1 min-w-[220px]">
        <AppIcon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
        <input v-model="filters.search" placeholder="Search name, email or company" class="!pl-9 !m-0 w-full">
      </div>
      <select v-model="filters.rating" class="!m-0 !w-auto">
        <option value="">All ratings</option>
        <option v-for="r in RATINGS" :key="r" :value="r">{{ label(r) }}</option>
      </select>
      <select v-model="filters.rep" class="!m-0 !w-auto">
        <option value="">All owners</option>
        <option value="unassigned">Unassigned</option>
        <option v-for="m in team" :key="m.id" :value="m.id">{{ m.name }}</option>
      </select>
      <select v-model="filters.source" class="!m-0 !w-auto">
        <option value="">Any capture method</option>
        <option value="scan">Badge scan</option>
        <option value="manual">Added by hand</option>
        <option value="connect">Connected in app</option>
        <option value="import">Imported</option>
      </select>
      <label class="flex items-center gap-2 text-[.85rem] cursor-pointer">
        <input v-model="filters.due" type="checkbox" class="!m-0"> Follow-up due
      </label>
    </div>

    <!-- Board -->
    <div v-if="loading && !columns.length" class="card py-12 text-center muted">Loading the pipeline…</div>

    <div v-else class="board">
      <div v-for="col in columns" :key="col.status" class="col">
        <div class="col-head">
          <span class="col-title">{{ col.label }}</span>
          <span class="col-count">{{ col.count }}</span>
        </div>

        <div class="col-body">
          <p v-if="!col.leads.length" class="empty">Nothing here.</p>

          <article v-for="l in col.leads" :key="l.id" class="lead-card" @click="open(l)">
            <div class="flex items-start gap-2.5">
              <span class="avatar">{{ initials(l.name) }}</span>
              <div class="min-w-0 flex-1">
                <div class="font-semibold text-ink text-[.88rem] truncate">{{ l.name }}</div>
                <div class="text-muted text-[.76rem] truncate">{{ l.company || 'No company' }}</div>
              </div>
              <span class="pill" :class="`pill-${l.rating}`">{{ l.rating }}</span>
            </div>

            <!-- BANT at a glance -->
            <div class="flex items-center gap-2 mt-2.5">
              <div class="bant">
                <span
                  v-for="c in CRITERIA" :key="c.key"
                  class="bant-box" :class="{ on: l.criteria[c.key] }"
                  :title="`${c.label}: ${l.criteria[c.key] ? 'confirmed' : 'not confirmed'}`"
                >{{ c.label[0] }}</span>
              </div>
              <span class="grade" :class="`grade-${l.grade}`">{{ l.score }}%</span>
            </div>

            <p v-if="l.next_step" class="next">
              <AppIcon name="arrow-right" class="w-3 h-3 shrink-0" /> {{ l.next_step }}
            </p>

            <div class="flex items-center gap-2 mt-2 text-[.72rem] text-muted flex-wrap">
              <span v-if="l.follow_up_at" class="due" :class="{ 'due-now': l.follow_up_due }">
                <AppIcon name="calendar" class="w-3 h-3" /> {{ shortDate(l.follow_up_at) }}
              </span>
              <span>{{ l.owner || 'Unassigned' }}</span>
            </div>

            <button v-if="l.ready_to_advance" class="advance" @click.stop="advance(l)">
              All four confirmed — mark qualified
            </button>
          </article>
        </div>
      </div>
    </div>

    <!-- Qualify one lead -->
    <Drawer v-if="editing" :title="editing.name" @close="editing = null">
      <div class="flex items-center gap-3 mb-4">
        <span class="avatar avatar-lg">{{ initials(editing.name) }}</span>
        <div class="min-w-0">
          <div class="font-semibold text-ink">{{ [editing.job_title, editing.company].filter(Boolean).join(' · ') || 'No company' }}</div>
          <div class="text-muted text-[.8rem] truncate">{{ editing.email || 'No email' }}{{ editing.phone ? ` · ${editing.phone}` : '' }}</div>
        </div>
        <div class="grow" />
        <span class="grade grade-lg" :class="`grade-${draftGrade}`">{{ draftScore }}%</span>
      </div>

      <h4 class="sec">Qualification</h4>
      <label v-for="c in CRITERIA" :key="c.key" class="crit">
        <input v-model="(draft as any)[c.key]" type="checkbox" class="!m-0 mt-0.5">
        <span>
          <span class="font-semibold text-ink text-[.88rem]">{{ c.label }}</span>
          <span class="block text-[.78rem] text-muted">{{ c.hint }}</span>
        </span>
      </label>

      <h4 class="sec mt-5">Next step</h4>
      <input v-model="draft.next_step" placeholder="e.g. Send the two-site quote" class="w-full !m-0" maxlength="200">

      <div class="grid grid-cols-2 gap-3 mt-3">
        <div>
          <label class="lbl">Follow up on</label>
          <input v-model="draft.follow_up_at" type="date" class="w-full !m-0">
        </div>
        <div>
          <label class="lbl">Rating</label>
          <select v-model="draft.rating" class="w-full !m-0">
            <option v-for="r in RATINGS" :key="r" :value="r">{{ label(r) }}</option>
          </select>
        </div>
      </div>

      <label class="lbl mt-3">Pipeline stage</label>
      <select v-model="draft.status" class="w-full !m-0">
        <option v-for="s in stageOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
      </select>

      <label class="lbl mt-3">Notes</label>
      <textarea v-model="draft.notes" rows="4" maxlength="2000" class="w-full !m-0" placeholder="What was said at the stand?" />

      <p v-if="editing.qualified_at" class="text-[.75rem] text-muted mt-3">
        Last qualified {{ shortDate(editing.qualified_at) }}.
      </p>

      <div class="flex justify-end gap-2 mt-5">
        <button class="btn ghost" @click="editing = null">Cancel</button>
        <button class="btn" :disabled="saving" @click="save">{{ saving ? 'Saving…' : 'Save' }}</button>
      </div>
    </Drawer>
  </div>
</template>

<style scoped>
.stat-card { border-radius: 12px; border: 1px solid var(--line); background: var(--card); padding: 14px 16px; }
.stat-card-alert { border-color: #fcd34d; background: #fffbeb; }
.stat-label { display: flex; align-items: center; gap: 6px; color: var(--muted); font-size: .82rem; }
.stat-n { font-size: 1.5rem; font-weight: 800; color: var(--ink); line-height: 1; margin-top: 8px; }
.stat-sub { font-size: .76rem; color: var(--muted); margin-top: 4px; }

.board { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 8px; align-items: flex-start; }
.col { width: 268px; flex-shrink: 0; background: #f7f8fa; border: 1px solid var(--line); border-radius: 12px; }
.col-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-bottom: 1px solid var(--line); }
.col-title { font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
.col-count { font-size: .74rem; font-weight: 700; padding: 1px 8px; border-radius: 9999px; background: #fff; border: 1px solid var(--line); color: var(--ink); }
.col-body { padding: 10px; display: grid; gap: 10px; max-height: 68vh; overflow-y: auto; }
.empty { font-size: .8rem; color: var(--faint); text-align: center; padding: 14px 0; }

.lead-card { background: #fff; border: 1px solid var(--line); border-radius: 10px; padding: 11px; cursor: pointer; }
.lead-card:hover { border-color: var(--brand); box-shadow: 0 2px 8px rgba(17, 20, 36, .06); }

.avatar { width: 30px; height: 30px; border-radius: 9999px; background: var(--brand-soft); color: var(--brand-dark); display: grid; place-items: center; font-size: .68rem; font-weight: 700; flex-shrink: 0; }
.avatar-lg { width: 46px; height: 46px; font-size: .9rem; }

.pill { font-size: .68rem; font-weight: 700; padding: 2px 7px; border-radius: 9999px; text-transform: capitalize; }
.pill-hot { background: #fee2e2; color: #b91c1c; }
.pill-warm { background: #fef3c7; color: #b45309; }
.pill-cold { background: #dbeafe; color: #1d4ed8; }

.bant { display: flex; gap: 3px; }
.bant-box { width: 20px; height: 20px; border-radius: 5px; display: grid; place-items: center; font-size: .64rem; font-weight: 800; background: #f1f3f7; color: #9aa3af; border: 1px solid var(--line); }
.bant-box.on { background: #dcfce7; color: #15803d; border-color: #86efac; }

.grade { font-size: .72rem; font-weight: 800; padding: 2px 8px; border-radius: 9999px; }
.grade-lg { font-size: .95rem; padding: 5px 12px; }
.grade-qualified { background: #dcfce7; color: #15803d; }
.grade-developing { background: #fef3c7; color: #b45309; }
.grade-early { background: #e0f2fe; color: #0369a1; }
.grade-unscored { background: #f3f4f6; color: #6b7280; }

.next { display: flex; align-items: center; gap: 5px; margin-top: 8px; font-size: .78rem; color: var(--ink); }
.due { display: inline-flex; align-items: center; gap: 4px; }
.due-now { color: #b91c1c; font-weight: 700; }

.advance { width: 100%; margin-top: 9px; padding: 6px; border-radius: 8px; border: 1px dashed #86efac; background: #f0fdf4; color: #15803d; font-size: .74rem; font-weight: 700; cursor: pointer; }
.advance:hover { background: #dcfce7; }

.sec { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); margin-bottom: 8px; }
.crit { display: flex; gap: 10px; align-items: flex-start; padding: 9px 10px; border: 1px solid var(--line); border-radius: 9px; margin-bottom: 7px; cursor: pointer; }
.crit:hover { background: #fafbfc; }
.lbl { display: block; font-size: .8rem; font-weight: 600; color: var(--ink); margin-bottom: 4px; }
</style>
