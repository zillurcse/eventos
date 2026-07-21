<script setup lang="ts">
definePageMeta({
  middleware: 'exhibitor',
  feature: 'recommended_leads',
  title: 'Recommended Leads',
  subtitle: 'Discover participants genuinely interested in your company. Review their interactions, assign them to team members, and send connection requests.',
})

const api = useApi()

interface Reason { key: string, text: string }
interface Row {
  id: string
  name: string
  job_title: string | null
  company: string | null
  avatar_url: string | null
  email: string | null
  phone: string | null
  contact_locked: boolean
  score: number
  temperature: 'hot' | 'warm' | 'cold'
  reasons: Reason[]
  signals: { meetings: number, messages: number, visits: number, bookmarked: boolean, fit: string[] }
  last_message: string | null
  last_signal_at: string | null
  state: 'new' | 'assigned' | 'requested' | 'dismissed'
  assigned_member_id: number | null
  assigned_to: string | null
  requested_at: string | null
  dismiss_reason: string | null
  responded: boolean
  lead: { id: string, status: string, rating: string } | null
}
interface Detail extends Row {
  bio: string | null
  city: string | null
  country: string | null
  interests: string[]
  looking_for: string[]
  purpose_of_visit: string | null
  timeline: { type: string, side: string, title: string, detail: string | null, at: string | null }[]
}
interface Stats {
  total: number, hot: number, high_intent: number, requested: number
  responded: number, assigned: number, in_crm: number, dismissed: number, active_today: number
}

const SIGNALS = [
  { key: 'meeting', label: 'Asked for a meeting', icon: 'calendar' },
  { key: 'message', label: 'Messaged the booth', icon: 'mail' },
  { key: 'visit', label: 'Visited the booth', icon: 'store' },
  { key: 'bookmark', label: 'Saved the booth', icon: 'star' },
  { key: 'fit', label: 'Profile match', icon: 'tag' },
]
const ICONS: Record<string, string> = {
  meeting: 'calendar', message: 'mail', visit: 'store', bookmark: 'star', fit: 'tag', fresh: 'bell',
}

const rows = ref<Row[]>([])
const meta = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 })
const stats = ref<Stats>({ total: 0, hot: 0, high_intent: 0, requested: 0, responded: 0, assigned: 0, in_crm: 0, dismissed: 0, active_today: 0 })
const team = ref<{ id: number, name: string, role: string }[]>([])
const boothName = ref('')
const loading = ref(true)
const suspended = ref(false)

const filters = reactive({ search: '', signal: '', temperature: '', state: 'open', sort: 'score', page: 1, per_page: 10 })

// ── Loading ──────────────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  try {
    const q = new URLSearchParams({
      search: filters.search, signal: filters.signal, temperature: filters.temperature,
      state: filters.state, sort: filters.sort,
      page: String(filters.page), per_page: String(filters.per_page),
    })
    const res = await api<any>(`/exhibitor/leads/recommended?${q}`)
    rows.value = res.data
    meta.value = res.meta
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
  searchTimer = setTimeout(() => { filters.page = 1; load() }, 350)
})
watch(() => [filters.signal, filters.temperature, filters.state, filters.sort], () => { filters.page = 1; load() })
watch(() => filters.page, load)

/** Replace one card in place — an action shouldn't reshuffle the list under the cursor. */
function apply(updated: Row | null, fallbackId: string) {
  const i = rows.value.findIndex(r => r.id === fallbackId)
  if (i === -1) return
  if (updated) rows.value.splice(i, 1, updated)
  else rows.value.splice(i, 1)
  if (detail.value?.id === fallbackId && updated) Object.assign(detail.value, updated)
}

async function refreshStats() {
  try {
    const q = new URLSearchParams({ state: filters.state, per_page: '5', page: '1' })
    stats.value = (await api<any>(`/exhibitor/leads/recommended?${q}`)).stats
  } catch { /* headline counts are not worth an error toast */ }
}

// ── Review drawer ────────────────────────────────────────────────────────────
const detail = ref<Detail | null>(null)
const detailLoading = ref(false)

async function review(row: Row) {
  detailLoading.value = true
  detail.value = { ...row } as Detail
  try {
    detail.value = (await api<any>(`/exhibitor/leads/recommended/${row.id}`)).data
  } finally {
    detailLoading.value = false
  }
}

// ── Actions ──────────────────────────────────────────────────────────────────
const busy = ref<string | null>(null)

async function assign(row: Row, value: string) {
  busy.value = row.id
  try {
    const res = await api<any>(`/exhibitor/leads/recommended/${row.id}/assign`, {
      method: 'POST',
      body: { member_id: value ? Number(value) : null },
    })
    apply(res.data, row.id)
    refreshStats()
  } finally {
    busy.value = null
  }
}

async function dismiss(row: Row) {
  const reason = prompt(`Dismiss ${row.name}? Add a reason (optional):`, '')
  if (reason === null) return
  busy.value = row.id
  try {
    const res = await api<any>(`/exhibitor/leads/recommended/${row.id}/dismiss`, {
      method: 'POST',
      body: { reason: reason || null },
    })
    // Dismissed rows leave the default view, but stay when you're browsing them.
    apply(filters.state === 'open' ? null : res.data, row.id)
    if (detail.value?.id === row.id) detail.value = null
    refreshStats()
  } finally {
    busy.value = null
  }
}

async function restore(row: Row) {
  busy.value = row.id
  try {
    const res = await api<any>(`/exhibitor/leads/recommended/${row.id}/dismiss`, { method: 'DELETE' })
    apply(filters.state === 'dismissed' ? null : res.data, row.id)
    refreshStats()
  } finally {
    busy.value = null
  }
}

// ── Connection request ───────────────────────────────────────────────────────
const connecting = ref<Row | null>(null)
const connectForm = reactive({ message: '', member_id: '' })
const sending = ref(false)
const connectError = ref('')

function openConnect(row: Row) {
  connecting.value = row
  connectError.value = ''
  connectForm.member_id = row.assigned_member_id ? String(row.assigned_member_id) : ''
  connectForm.message = template(row)
}

/**
 * A first message that names why we're writing. "We noticed you" with nothing
 * behind it is the reason booth outreach gets ignored; the strongest signal is
 * the one thing the attendee will recognise.
 */
function template(row: Row) {
  const first = row.name.split(/\s+/)[0]
  const from = boothName.value || 'our team'
  const opener = row.signals.meetings
    ? 'thanks for requesting a meeting with us'
    : row.signals.messages
      ? 'thanks for getting in touch with us'
      : row.signals.visits
        ? 'thanks for stopping by our stand'
        : row.signals.bookmarked
          ? 'we saw you saved our booth'
          : row.signals.fit.length
            ? `we work with teams looking at ${row.signals.fit[0]}`
            : 'we would love to connect at the event'

  return `Hi ${first}, ${opener}. I'm with ${from} — would you like to book a short slot at our stand to talk it through?`
}

async function sendConnect() {
  if (!connecting.value) return
  sending.value = true
  connectError.value = ''
  try {
    const res = await api<any>(`/exhibitor/leads/recommended/${connecting.value.id}/connect`, {
      method: 'POST',
      body: { message: connectForm.message, member_id: connectForm.member_id ? Number(connectForm.member_id) : null },
    })
    apply(res.data, connecting.value.id)
    connecting.value = null
    refreshStats()
  } catch (e: any) {
    connectError.value = e?.data?.message || 'Could not send the request.'
  } finally {
    sending.value = false
  }
}

// ── Display helpers ──────────────────────────────────────────────────────────
function initials(name: string) {
  const p = name.trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?'
}
function ago(iso: string | null) {
  if (!iso) return 'No recent activity'
  const mins = Math.round((Date.now() - new Date(iso).getTime()) / 60000)
  if (mins < 1) return 'Just now'
  if (mins < 60) return `${mins}m ago`
  if (mins < 1440) return `${Math.floor(mins / 60)}h ago`
  const days = Math.floor(mins / 1440)
  return days === 1 ? 'Yesterday' : `${days}d ago`
}
function stateLabel(row: Row) {
  if (row.state === 'dismissed') return 'Dismissed'
  if (row.responded) return 'Replied to you'
  if (row.state === 'requested') return `Requested ${ago(row.requested_at).toLowerCase()}`
  if (row.state === 'assigned') return `Assigned to ${row.assigned_to}`
  return 'New suggestion'
}
function pct(n: number) { return stats.value.total ? Math.round((n / stats.value.total) * 100) : 0 }

onMounted(async () => {
  load()
  try { boothName.value = (await api<any>('/exhibitor/space')).data?.name ?? '' } catch { /* optional */ }
})
</script>

<template>
  <div v-if="suspended" class="card"><p class="error">This exhibitor account is suspended.</p></div>

  <div v-else>
    <!-- Headline: what the queue is worth right now -->
    <div class="grid grid-cols-6 gap-3 mb-5 max-xl:grid-cols-3 max-sm:grid-cols-2">
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="users" class="w-3.5 h-3.5" /> Suggested</div>
        <div class="stat-n">{{ stats.total }}</div>
        <div class="stat-sub">{{ stats.active_today }} active today</div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="target" class="w-3.5 h-3.5" /> High intent</div>
        <div class="stat-n">{{ stats.high_intent }}</div>
        <div class="stat-sub">Messaged or asked to meet</div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><span class="dot bg-[#ef4444]" /> Hot</div>
        <div class="stat-n">{{ stats.hot }}</div>
        <div class="stat-sub">{{ pct(stats.hot) }}% of the queue</div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="link" class="w-3.5 h-3.5" /> Requests sent</div>
        <div class="stat-n">{{ stats.requested }}</div>
        <div class="stat-sub">{{ stats.responded }} replied so far</div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="briefcase" class="w-3.5 h-3.5" /> Assigned</div>
        <div class="stat-n">{{ stats.assigned }}</div>
        <div class="stat-sub">Owned by a teammate</div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><AppIcon name="clipboard" class="w-3.5 h-3.5" /> In your CRM</div>
        <div class="stat-n">{{ stats.in_crm }}</div>
        <div class="stat-sub">Promoted to leads</div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="card p-4 mb-4 flex items-center gap-2.5 flex-wrap">
      <div class="relative flex-1 min-w-[220px]">
        <AppIcon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
        <input v-model="filters.search" placeholder="Search name, company or job title" class="!pl-9 !m-0 w-full">
      </div>
      <select v-model="filters.signal" class="!m-0 !w-auto">
        <option value="">Any signal</option>
        <option v-for="s in SIGNALS" :key="s.key" :value="s.key">{{ s.label }}</option>
      </select>
      <select v-model="filters.temperature" class="!m-0 !w-auto">
        <option value="">Any interest level</option>
        <option value="hot">Hot</option>
        <option value="warm">Warm</option>
        <option value="cold">Cold</option>
      </select>
      <select v-model="filters.state" class="!m-0 !w-auto">
        <option value="open">To action</option>
        <option value="assigned">Assigned</option>
        <option value="requested">Requested</option>
        <option value="dismissed">Dismissed</option>
        <option value="all">Everyone</option>
      </select>
      <select v-model="filters.sort" class="!m-0 !w-auto">
        <option value="score">Best match</option>
        <option value="recent">Most recent activity</option>
        <option value="name">Name</option>
      </select>
    </div>

    <!-- The queue -->
    <div v-if="loading && !rows.length" class="card py-12 text-center muted">Looking for interested participants…</div>

    <div v-else-if="!rows.length" class="card py-12 text-center">
      <p class="font-semibold text-ink">Nothing to recommend yet.</p>
      <p class="muted text-[.85rem] mt-1 max-w-[460px] mx-auto">
        Suggestions appear as attendees interact with your booth — messaging you, requesting meetings,
        saving your profile or having their badge scanned at your stand.
      </p>
    </div>

    <div v-else class="space-y-3">
      <div v-for="r in rows" :key="r.id" class="card sug !mb-0" :class="{ 'sug-dim': r.state === 'dismissed' }">
        <!-- Who -->
        <div class="flex items-start gap-3 min-w-0 flex-1">
          <img v-if="r.avatar_url" :src="r.avatar_url" class="avatar-img" alt="">
          <span v-else class="avatar">{{ initials(r.name) }}</span>
          <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <button class="name-btn" @click="review(r)">{{ r.name }}</button>
              <span class="temp" :class="`temp-${r.temperature}`">{{ r.score }} · {{ r.temperature }}</span>
              <span v-if="r.lead" class="tag">In leads</span>
            </div>
            <div class="text-muted text-[.82rem] truncate">
              {{ [r.job_title, r.company].filter(Boolean).join(' · ') || 'Attendee' }}
            </div>

            <!-- Why we are showing them -->
            <div class="flex items-center gap-1.5 flex-wrap mt-2">
              <span v-for="reason in r.reasons" :key="reason.key" class="chip" :class="`chip-${reason.key}`">
                <AppIcon :name="ICONS[reason.key] || 'star'" class="w-3 h-3" /> {{ reason.text }}
              </span>
            </div>

            <p v-if="r.last_message" class="quote">“{{ r.last_message }}”</p>

            <div class="text-[.76rem] text-muted mt-2 flex items-center gap-2 flex-wrap">
              <span class="state" :class="{ 'state-live': r.responded }">{{ stateLabel(r) }}</span>
              <span>·</span>
              <span>{{ ago(r.last_signal_at) }}</span>
              <template v-if="r.contact_locked">
                <span>·</span>
                <span title="Contact details unlock once they connect with you or become a lead">Contact details locked</span>
              </template>
            </div>
          </div>
        </div>

        <!-- Act -->
        <div class="actions">
          <select
            :value="r.assigned_member_id ?? ''"
            class="!m-0 !w-full !py-1.5 text-[.82rem]"
            :disabled="busy === r.id"
            @change="assign(r, ($event.target as HTMLSelectElement).value)"
          >
            <option value="">Assign to…</option>
            <option v-for="m in team" :key="m.id" :value="m.id">{{ m.name }}</option>
          </select>

          <button class="btn sm w-full" :disabled="busy === r.id" @click="openConnect(r)">
            <AppIcon name="link" class="w-3.5 h-3.5" />
            {{ r.state === 'requested' ? 'Follow up' : 'Send request' }}
          </button>

          <div class="flex gap-2">
            <button class="btn ghost sm flex-1" @click="review(r)">Review</button>
            <button v-if="r.state !== 'dismissed'" class="btn ghost sm" title="Not relevant" :disabled="busy === r.id" @click="dismiss(r)">
              <AppIcon name="x" class="w-3.5 h-3.5" />
            </button>
            <button v-else class="btn ghost sm" title="Bring back" :disabled="busy === r.id" @click="restore(r)">
              <AppIcon name="arrow-up" class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div class="card flex items-center justify-end gap-4 p-4 text-[.84rem] text-muted flex-wrap">
        <span>{{ meta.total ? `${meta.from}–${meta.to}` : 0 }} of {{ meta.total }}</span>
        <div class="flex items-center gap-1">
          <button class="page-btn" :disabled="meta.current_page <= 1" @click="filters.page--">‹</button>
          <span class="px-2">Page {{ meta.current_page }} / {{ meta.last_page }}</span>
          <button class="page-btn" :disabled="meta.current_page >= meta.last_page" @click="filters.page++">›</button>
        </div>
      </div>
    </div>

    <!-- Review: everything this person did, before you spend a rep on them -->
    <Drawer v-if="detail" :title="detail.name" @close="detail = null">
      <div class="flex items-center gap-3 mb-4">
        <img v-if="detail.avatar_url" :src="detail.avatar_url" class="avatar-img avatar-lg" alt="">
        <span v-else class="avatar avatar-lg">{{ initials(detail.name) }}</span>
        <div class="min-w-0">
          <div class="font-semibold text-ink">{{ [detail.job_title, detail.company].filter(Boolean).join(' · ') || 'Attendee' }}</div>
          <div class="text-muted text-[.8rem]">{{ [detail.city, detail.country].filter(Boolean).join(', ') || '—' }}</div>
        </div>
        <div class="grow" />
        <span class="temp" :class="`temp-${detail.temperature}`">{{ detail.score }}</span>
      </div>

      <div class="grid grid-cols-2 gap-2 mb-4">
        <div class="mini"><span class="mini-n">{{ detail.signals.meetings }}</span><span class="mini-l">Meeting requests</span></div>
        <div class="mini"><span class="mini-n">{{ detail.signals.messages }}</span><span class="mini-l">Messages sent</span></div>
        <div class="mini"><span class="mini-n">{{ detail.signals.visits }}</span><span class="mini-l">Booth visits</span></div>
        <div class="mini"><span class="mini-n">{{ detail.signals.bookmarked ? 'Yes' : 'No' }}</span><span class="mini-l">Saved your booth</span></div>
      </div>

      <div v-if="detail.contact_locked" class="note-box">
        Contact details stay hidden until this attendee connects with you or becomes a lead.
      </div>
      <div v-else class="grid gap-1 mb-4 text-[.85rem]">
        <div class="flex items-center gap-2"><AppIcon name="mail" class="w-3.5 h-3.5 text-muted" /> {{ detail.email || '—' }}</div>
        <div class="flex items-center gap-2"><AppIcon name="phone" class="w-3.5 h-3.5 text-muted" /> {{ detail.phone || '—' }}</div>
      </div>

      <template v-if="detail.interests?.length || detail.looking_for?.length">
        <h4 class="sec">Interests</h4>
        <div class="flex flex-wrap gap-1.5 mb-4">
          <span v-for="t in [...(detail.interests || []), ...(detail.looking_for || [])]" :key="t" class="chip chip-fit">{{ t }}</span>
        </div>
      </template>

      <p v-if="detail.purpose_of_visit" class="text-[.85rem] text-muted mb-4">
        <span class="font-semibold text-ink">Here to:</span> {{ detail.purpose_of_visit }}
      </p>

      <h4 class="sec">Interactions with your booth</h4>
      <p v-if="detailLoading" class="muted text-[.85rem]">Loading history…</p>
      <ul v-else-if="detail.timeline?.length" class="timeline">
        <li v-for="(t, i) in detail.timeline" :key="i">
          <span class="tl-dot" :class="t.side === 'exhibitor' ? 'tl-dot-us' : ''">
            <AppIcon :name="ICONS[t.type] || 'star'" class="w-3 h-3" />
          </span>
          <div class="min-w-0">
            <div class="text-[.85rem] font-semibold text-ink">{{ t.title }}</div>
            <div v-if="t.detail" class="text-[.8rem] text-muted">{{ t.detail }}</div>
            <div class="text-[.72rem] text-muted mt-0.5">{{ t.at ? ago(t.at) : 'In the event app' }}</div>
          </div>
        </li>
      </ul>
      <p v-else class="muted text-[.85rem]">No booth interactions recorded — this is a profile match.</p>

      <div class="flex gap-2 mt-5">
        <button class="btn flex-1" @click="openConnect(detail as Row)">
          <AppIcon name="link" class="w-3.5 h-3.5" /> Send connection request
        </button>
        <button v-if="detail.state !== 'dismissed'" class="btn ghost" @click="dismiss(detail as Row)">Dismiss</button>
      </div>
    </Drawer>

    <!-- Outreach -->
    <Modal
      v-if="connecting"
      title="Send a connection request"
      :subtitle="`${connecting.name} will get this as a message from your booth, in their event app.`"
      @close="connecting = null"
    >
      <label class="lbl">Owner</label>
      <select v-model="connectForm.member_id" class="w-full">
        <option value="">Nobody yet</option>
        <option v-for="m in team" :key="m.id" :value="String(m.id)">{{ m.name }}</option>
      </select>

      <label class="lbl">Message</label>
      <textarea v-model="connectForm.message" rows="5" maxlength="1000" class="w-full" />
      <p class="text-[.76rem] text-muted">{{ connectForm.message.length }}/1000 · replies land in your Contact inbox.</p>

      <p v-if="connectError" class="error">{{ connectError }}</p>

      <div class="flex justify-end gap-2 mt-4">
        <button class="btn ghost" @click="connecting = null">Cancel</button>
        <button class="btn" :disabled="sending || !connectForm.message.trim()" @click="sendConnect">
          {{ sending ? 'Sending…' : 'Send request' }}
        </button>
      </div>
    </Modal>
  </div>
</template>

<style scoped>
.stat-card { border-radius: 12px; border: 1px solid var(--line); background: var(--card); padding: 14px 16px; }
.stat-label { display: flex; align-items: center; gap: 6px; color: var(--muted); font-size: .82rem; }
.stat-n { font-size: 1.5rem; font-weight: 800; color: var(--ink); line-height: 1; margin-top: 8px; }
.stat-sub { font-size: .76rem; color: var(--muted); margin-top: 4px; }
.dot { width: 8px; height: 8px; border-radius: 9999px; display: inline-block; }

.sug { display: flex; align-items: flex-start; gap: 20px; padding: 16px; }
.sug-dim { opacity: .6; }
@media (max-width: 900px) { .sug { flex-direction: column; } }

.avatar { width: 40px; height: 40px; border-radius: 9999px; background: var(--brand-soft); color: var(--brand-dark); display: grid; place-items: center; font-size: .78rem; font-weight: 700; flex-shrink: 0; }
.avatar-img { width: 40px; height: 40px; border-radius: 9999px; object-fit: cover; flex-shrink: 0; }
.avatar-lg { width: 52px; height: 52px; font-size: .95rem; }

.name-btn { background: none; border: 0; padding: 0; cursor: pointer; font-size: .95rem; font-weight: 700; color: var(--ink); }
.name-btn:hover { color: var(--brand-dark); text-decoration: underline; }

.temp { font-size: .74rem; font-weight: 700; padding: 2px 9px; border-radius: 9999px; text-transform: capitalize; }
.temp-hot { background: #fee2e2; color: #b91c1c; }
.temp-warm { background: #fef3c7; color: #b45309; }
.temp-cold { background: #dbeafe; color: #1d4ed8; }
.tag { font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: 2px 6px; border-radius: 4px; background: #eef2ff; color: #4338ca; }

.chip { display: inline-flex; align-items: center; gap: 4px; font-size: .74rem; font-weight: 600; padding: 3px 9px; border-radius: 9999px; background: #f3f4f6; color: #4b5563; }
.chip-meeting { background: #ede9fe; color: #6d28d9; }
.chip-message { background: #dcfce7; color: #15803d; }
.chip-visit { background: #ffedd5; color: #c2410c; }
.chip-bookmark { background: #fef3c7; color: #b45309; }
.chip-fit { background: var(--brand-soft); color: var(--brand-dark); }
.chip-fresh { background: #e0f2fe; color: #0369a1; }

.quote { margin-top: 8px; font-size: .82rem; color: var(--muted); font-style: italic; border-left: 2px solid var(--line); padding-left: 10px; }
.state { font-weight: 600; color: var(--muted); }
.state-live { color: #15803d; }

.actions { width: 200px; display: grid; gap: 8px; flex-shrink: 0; }
@media (max-width: 900px) { .actions { width: 100%; } }

.mini { border-radius: 8px; background: #f6f7f9; padding: 10px; display: grid; gap: 2px; text-align: center; }
.mini-n { font-size: 1.05rem; font-weight: 700; color: var(--ink); }
.mini-l { font-size: .7rem; color: var(--muted); }

.sec { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); margin-bottom: 8px; }
.note-box { border-radius: 8px; background: #fffbeb; border: 1px solid #fcd34d; padding: 10px 12px; font-size: .8rem; color: #92400e; margin-bottom: 16px; }

.timeline { display: grid; gap: 14px; }
.timeline li { display: flex; gap: 10px; }
.tl-dot { width: 24px; height: 24px; border-radius: 9999px; background: var(--brand-soft); color: var(--brand-dark); display: grid; place-items: center; flex-shrink: 0; }
.tl-dot-us { background: #f3f4f6; color: #6b7280; }

.lbl { display: block; font-size: .8rem; font-weight: 600; color: var(--ink); margin-top: 14px; margin-bottom: 4px; }

.page-btn { width: 28px; height: 28px; display: grid; place-items: center; border-radius: 6px; border: 1px solid var(--line); background: transparent; cursor: pointer; }
.page-btn:hover:not(:disabled) { background: #f6f7f9; }
.page-btn:disabled { opacity: .4; cursor: default; }
</style>
