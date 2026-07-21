<script setup lang="ts">
definePageMeta({
  middleware: 'exhibitor',
  feature: 'lead_analytics',
  title: 'Leads Analytics',
  subtitle: 'How the stand is performing: capture volume, pipeline conversion, what is working and when.',
})

const api = useApi()

interface Bucket { date: string, label: string, count: number, hot: number }
interface Stage { key: string, label: string, count: number, share: number, from_previous: number }
interface Split { key: string, label: string, count: number, share: number, won: number }
interface Report {
  range: { days: number, from: string, to: string }
  totals: {
    total: number, in_range: number, per_day: number, today: number, hot: number
    companies: number, contacted: number, qualified: number, won: number, lost: number
    conversion_rate: number, win_rate: number, exported: number
    best_day: { date: string, count: number } | null
  }
  timeline: Bucket[]
  funnel: Stage[]
  sources: Split[]
  ratings: Split[]
  hours: { hour: number, label: string, count: number }[]
  companies: { company: string, leads: number, hot: number, won: number }[]
  reps: { member_id: number, name: string, total: number, hot: number, won: number, conversion_rate: number }[]
  qualification: {
    scored: number, unscored: number, fully_qualified: number, coverage: number, avg_score: number
    criteria: { key: string, label: string, count: number }[]
  }
}

const RANGES = [
  { days: 7, label: 'Last 7 days' },
  { days: 30, label: 'Last 30 days' },
  { days: 90, label: 'Last 90 days' },
]
const RATING_COLORS: Record<string, string> = { hot: '#ef4444', warm: '#f59e0b', cold: '#3b82f6' }

const report = ref<Report | null>(null)
const days = ref(30)
const loading = ref(true)
const suspended = ref(false)

async function load() {
  loading.value = true
  try {
    report.value = (await api<any>(`/exhibitor/leads/analytics?days=${days.value}`)).data
  } catch (e: any) {
    if (e?.response?.status === 403 || e?.status === 403) suspended.value = true
  } finally {
    loading.value = false
  }
}
watch(days, load)

// ── Chart scaling ────────────────────────────────────────────────────────────
// Bars are drawn against the tallest bar in their own series, so a quiet week
// still reads as a shape rather than a flat line.
const peakCapture = computed(() => Math.max(1, ...(report.value?.timeline ?? []).map(b => b.count)))
const peakHour = computed(() => Math.max(1, ...(report.value?.hours ?? []).map(h => h.count)))
const busiestHour = computed(() => {
  const hours = report.value?.hours ?? []
  const best = hours.reduce((a, b) => (b.count > a.count ? b : a), hours[0] ?? { label: '', count: 0 })
  return best.count ? best : null
})

function fullDate(iso: string | null | undefined) {
  return iso ? new Date(iso).toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
}

onMounted(load)
</script>

<template>
  <div v-if="suspended" class="card"><p class="error">This exhibitor account is suspended.</p></div>

  <div v-else-if="loading && !report" class="card py-12 text-center muted">Crunching your lead data…</div>

  <div v-else-if="report">
    <!-- Range -->
    <div class="flex items-center gap-2 mb-4 flex-wrap">
      <button
        v-for="r in RANGES" :key="r.days"
        class="range" :class="{ 'range-on': days === r.days }"
        @click="days = r.days"
      >{{ r.label }}</button>
      <div class="grow" />
      <span class="muted text-[.8rem]">{{ fullDate(report.range.from) }} – {{ fullDate(report.range.to) }}</span>
    </div>

    <!-- Headline -->
    <div class="grid grid-cols-6 gap-3 mb-5 max-xl:grid-cols-3 max-sm:grid-cols-2">
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="users" class="w-3.5 h-3.5" /> Leads captured</div>
        <div class="stat-n">{{ report.totals.total }}</div>
        <div class="stat-sub">{{ report.totals.in_range }} in this window</div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="calendar" class="w-3.5 h-3.5" /> Per day</div>
        <div class="stat-n">{{ report.totals.per_day }}</div>
        <div class="stat-sub">{{ report.totals.today }} captured today</div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><span class="dot" style="background:#ef4444" /> Hot leads</div>
        <div class="stat-n">{{ report.totals.hot }}</div>
        <div class="stat-sub">{{ report.totals.companies }} companies reached</div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="award" class="w-3.5 h-3.5" /> Qualified</div>
        <div class="stat-n">{{ report.totals.qualified }}</div>
        <div class="stat-sub">{{ report.totals.contacted }} followed up</div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="target" class="w-3.5 h-3.5" /> Conversion</div>
        <div class="stat-n">{{ report.totals.conversion_rate }}%</div>
        <div class="stat-sub">{{ report.totals.win_rate }}% of closed deals won</div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="star" class="w-3.5 h-3.5" /> Best day</div>
        <div class="stat-n">{{ report.totals.best_day?.count ?? 0 }}</div>
        <div class="stat-sub">{{ report.totals.best_day ? fullDate(report.totals.best_day.date) : 'No captures yet' }}</div>
      </div>
    </div>

    <div class="grid grid-cols-3 gap-4 mb-5 max-lg:grid-cols-1">
      <!-- Capture trend -->
      <div class="card col-span-2 max-lg:col-span-1">
        <div class="panel-head">
          <h3>Capture volume</h3>
          <span class="muted text-[.78rem]">Darker part of each bar is hot leads</span>
        </div>
        <div class="chart">
          <div v-for="b in report.timeline" :key="b.date" class="chart-col" :title="`${b.count} leads (${b.hot} hot) · ${b.label}`">
            <span class="chart-n">{{ b.count || '' }}</span>
            <div class="bar" :style="{ height: `${Math.max(3, (b.count / peakCapture) * 120)}px` }">
              <div class="bar-hot" :style="{ height: b.count ? `${(b.hot / b.count) * 100}%` : '0%' }" />
            </div>
            <span class="chart-l">{{ b.label }}</span>
          </div>
        </div>
      </div>

      <!-- Funnel -->
      <div class="card">
        <div class="panel-head"><h3>Pipeline funnel</h3></div>
        <div class="mt-4 space-y-3">
          <div v-for="(s, i) in report.funnel" :key="s.key">
            <div class="flex items-center justify-between text-[.82rem] mb-1">
              <span class="font-semibold text-ink">{{ s.label }}</span>
              <span class="text-muted">{{ s.count }} · {{ s.share }}%</span>
            </div>
            <div class="track"><div class="fill" :style="{ width: `${s.share}%` }" /></div>
            <p v-if="i > 0" class="text-[.72rem] text-muted mt-1">
              {{ s.from_previous }}% of {{ report.funnel[i - 1].label.toLowerCase() }} made it here
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-3 gap-4 mb-5 max-lg:grid-cols-1">
      <!-- Where leads come from -->
      <div class="card">
        <div class="panel-head"><h3>Capture method</h3></div>
        <p v-if="!report.sources.length" class="muted text-[.85rem] mt-4">No leads captured yet.</p>
        <div v-else class="mt-4 space-y-3">
          <div v-for="s in report.sources" :key="s.key">
            <div class="flex items-center justify-between text-[.82rem] mb-1">
              <span class="text-ink">{{ s.label }}</span>
              <span class="text-muted">{{ s.count }} · {{ s.share }}%</span>
            </div>
            <div class="track"><div class="fill" :style="{ width: `${s.share}%` }" /></div>
          </div>
        </div>
      </div>

      <!-- Rating mix -->
      <div class="card">
        <div class="panel-head"><h3>Rating mix</h3></div>
        <p v-if="!report.ratings.length" class="muted text-[.85rem] mt-4">Nothing rated yet.</p>
        <template v-else>
          <div class="mix mt-4">
            <span
              v-for="r in report.ratings" :key="r.key"
              class="mix-seg" :style="{ flex: r.count, background: RATING_COLORS[r.key] }"
              :title="`${r.count} ${r.label}`"
            />
          </div>
          <ul class="mt-3 space-y-2">
            <li v-for="r in report.ratings" :key="r.key" class="flex items-center justify-between text-[.82rem]">
              <span class="flex items-center gap-2">
                <span class="dot" :style="{ background: RATING_COLORS[r.key] }" /> {{ r.label }}
              </span>
              <span class="text-muted">{{ r.count }} · {{ r.share }}%</span>
            </li>
          </ul>
        </template>
      </div>

      <!-- Busiest hours -->
      <div class="card">
        <div class="panel-head">
          <h3>Busiest hours</h3>
          <span v-if="busiestHour" class="muted text-[.78rem]">Peak {{ busiestHour.label }}</span>
        </div>
        <div class="hours mt-4">
          <div v-for="h in report.hours" :key="h.hour" class="hour" :title="`${h.count} leads at ${h.label}`">
            <div class="hour-bar" :style="{ height: `${Math.max(2, (h.count / peakHour) * 76)}px` }" />
            <span class="hour-l">{{ h.hour }}</span>
          </div>
        </div>
        <p class="text-[.74rem] text-muted mt-2">Staff the stand heaviest around your peak.</p>
      </div>
    </div>

    <div class="grid grid-cols-3 gap-4 max-lg:grid-cols-1">
      <!-- Qualification coverage -->
      <div class="card">
        <div class="panel-head">
          <h3>Qualification coverage</h3>
          <span class="muted text-[.78rem]">{{ report.qualification.coverage }}% worked</span>
        </div>
        <div class="grid grid-cols-3 gap-2 mt-4">
          <div class="mini"><span class="mini-n">{{ report.qualification.unscored }}</span><span class="mini-l">Untouched</span></div>
          <div class="mini"><span class="mini-n">{{ report.qualification.scored }}</span><span class="mini-l">Scored</span></div>
          <div class="mini"><span class="mini-n">{{ report.qualification.fully_qualified }}</span><span class="mini-l">Full BANT</span></div>
        </div>
        <div class="mt-4 space-y-2.5">
          <div v-for="c in report.qualification.criteria" :key="c.key">
            <div class="flex items-center justify-between text-[.8rem] mb-1">
              <span class="text-ink">{{ c.label }} confirmed</span>
              <span class="text-muted">{{ c.count }}</span>
            </div>
            <div class="track">
              <div class="fill" :style="{ width: `${report.totals.total ? (c.count / report.totals.total) * 100 : 0}%` }" />
            </div>
          </div>
        </div>
      </div>

      <!-- Accounts -->
      <div class="card">
        <div class="panel-head"><h3>Top accounts</h3></div>
        <p v-if="!report.companies.length" class="muted text-[.85rem] mt-4">No company names captured yet.</p>
        <ul v-else class="mt-3 space-y-2.5">
          <li v-for="c in report.companies" :key="c.company" class="flex items-center justify-between gap-3">
            <div class="min-w-0">
              <div class="text-[.85rem] font-medium text-ink truncate">{{ c.company }}</div>
              <div class="text-[.74rem] text-muted">{{ c.hot }} hot · {{ c.won }} won</div>
            </div>
            <span class="chip">{{ c.leads }}</span>
          </li>
        </ul>
      </div>

      <!-- Who captured what -->
      <div class="card">
        <div class="panel-head">
          <h3>By teammate</h3>
          <NuxtLink to="/exhibitor/leads/team-connections" class="text-[.78rem] text-brand-dark">Team view →</NuxtLink>
        </div>
        <p v-if="!report.reps.length" class="muted text-[.85rem] mt-4">No leads attributed to a teammate yet.</p>
        <ul v-else class="mt-3 space-y-2.5">
          <li v-for="r in report.reps" :key="r.member_id" class="flex items-center justify-between gap-3">
            <div class="min-w-0">
              <div class="text-[.85rem] font-medium text-ink truncate">{{ r.name }}</div>
              <div class="text-[.74rem] text-muted">{{ r.hot }} hot · {{ r.won }} won</div>
            </div>
            <div class="text-right">
              <div class="font-semibold text-ink text-[.9rem]">{{ r.total }}</div>
              <div class="text-[.72rem] text-muted">{{ r.conversion_rate }}% conv.</div>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<style scoped>
.range { padding: 6px 13px; border-radius: 9999px; border: 1px solid var(--line); background: #fff; font-size: .82rem; font-weight: 600; color: var(--muted); cursor: pointer; }
.range-on { background: var(--brand-soft); border-color: var(--brand); color: var(--brand-dark); }

.stat-card { border-radius: 12px; border: 1px solid var(--line); background: var(--card); padding: 14px 16px; }
.stat-label { display: flex; align-items: center; gap: 6px; color: var(--muted); font-size: .82rem; }
.stat-n { font-size: 1.5rem; font-weight: 800; color: var(--ink); line-height: 1; margin-top: 8px; }
.stat-sub { font-size: .76rem; color: var(--muted); margin-top: 4px; }
.dot { width: 8px; height: 8px; border-radius: 9999px; display: inline-block; }

.panel-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.panel-head h3 { font-size: .95rem; font-weight: 700; color: var(--ink); }

.chart { display: flex; align-items: flex-end; gap: 4px; height: 165px; margin-top: 16px; overflow-x: auto; }
.chart-col { flex: 1; min-width: 22px; display: flex; flex-direction: column; align-items: center; gap: 4px; }
.chart-n { font-size: .68rem; font-weight: 700; color: var(--ink); }
.bar { width: 100%; max-width: 30px; border-radius: 4px 4px 0 0; background: var(--brand-soft); display: flex; flex-direction: column; justify-content: flex-end; }
.bar-hot { width: 100%; background: var(--brand); border-radius: 4px 4px 0 0; }
.chart-l { font-size: .64rem; color: var(--muted); white-space: nowrap; }

.track { height: 7px; border-radius: 9999px; background: #f1f3f7; overflow: hidden; }
.fill { height: 100%; border-radius: 9999px; background: var(--brand); }

.mix { display: flex; gap: 2px; height: 10px; border-radius: 9999px; overflow: hidden; }
.mix-seg { display: block; }

.hours { display: flex; align-items: flex-end; gap: 3px; height: 92px; }
.hour { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 3px; }
.hour-bar { width: 100%; border-radius: 3px 3px 0 0; background: var(--brand); opacity: .8; }
.hour-l { font-size: .6rem; color: var(--muted); }

.mini { border-radius: 8px; background: #f6f7f9; padding: 9px; display: grid; gap: 2px; text-align: center; }
.mini-n { font-size: 1.05rem; font-weight: 700; color: var(--ink); }
.mini-l { font-size: .68rem; color: var(--muted); }

.chip { flex-shrink: 0; font-size: .72rem; font-weight: 700; padding: 2px 9px; border-radius: 9999px; background: var(--brand-soft); color: var(--brand-dark); }
</style>
