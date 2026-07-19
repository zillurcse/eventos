<script setup lang="ts">
definePageMeta({ middleware: 'organizer', title: 'Plans', subtitle: 'View your current plan and upgrade when you need more.' })

const api = useApi()
const plans = ref<any[]>([])
const sub = ref<any>(null)
const pendingReq = ref<any>(null)
const loading = ref(true)
const changingSlug = ref<string | null>(null)
const canceling = ref(false)
const error = ref('')

async function load() {
  loading.value = true
  try { plans.value = (await api<any>('/plans')).data } catch { /* no perm */ }
  try { sub.value = (await api<any>('/subscription')).data } catch { /* none yet */ }
  try { pendingReq.value = (await api<any>('/subscription/change-request')).data } catch { /* none */ }
  loading.value = false
}

const currentSlug = computed(() => sub.value?.plan?.slug ?? null)
const currentPlan = computed(() => sub.value?.plan ?? null)
const pendingSlug = computed(() => pendingReq.value?.requested_plan?.slug ?? null)

function price(p: any) {
  const v = (p?.price_cents ?? 0) / 100
  return v % 1 === 0 ? `$${v}` : `$${v.toFixed(2)}`
}
const INTERVAL: Record<string, string> = { month: 'Month', year: 'Year', week: 'Week', day: 'Day' }
function interval(p: any) { return INTERVAL[p?.billing_interval] || 'Month' }

const MO = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
function validTill(iso: string | null) {
  if (!iso) return '—'
  const d = new Date(iso)
  return `${MO[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`
}

function num(v: any) { return v == null ? null : Number(v) }
function humanize(key: string) {
  return key.replace(/^module\./, '').replace(/^quota\./, '').replace(/[_.]/g, ' ')
    .replace(/\b\w/g, c => c.toUpperCase())
}

/** Human-readable feature bullets derived from a plan's limits + module features. */
function bullets(p: any): string[] {
  const l = p.limits || {}
  const out: string[] = []
  const events = num(l.max_events)
  out.push(events == null ? 'Unlimited Events' : `${events} Events`)
  const seats = num(l.max_admins ?? l.max_seats)
  if (seats != null) out.push(`Up to ${seats} Admin Seats`)
  const att = num(l.max_attendees)
  out.push(att == null ? 'Unlimited attendees' : `Audience cap of ${att.toLocaleString()} per event`)
  if (l.storage_gb != null) out.push(`${l.storage_gb} GB Storage`)
  const mods = (p.features || []).filter((f: string) => f.startsWith('module.')).map(humanize)
  return out.concat(mods)
}

// The feature-list heading: first tier lists its own tools, later tiers build on
// the previous tier ("Everything in Pro, plus:").
const sorted = computed(() => plans.value)
function tierHeading(i: number) {
  return i === 0 ? 'Essential Tools' : `Everything in ${sorted.value[i - 1].name}, plus:`
}

const TAGLINES: Record<string, string> = {
  free: 'Small teams getting started',
  pro: 'Established mid-market companies',
  business: 'Growing teams that need scale',
  enterprise: 'Large organizations',
}
function tagline(p: any) { return TAGLINES[p.slug] || '' }
const DESCRIPTIONS: Record<string, string> = {
  free: 'Small teams running regular, scheduled operations.',
  pro: 'Everything a growing team needs to run great events.',
  business: 'Scale, integrations and analytics for busy organisers.',
  enterprise: 'Unlimited scale with premium support and controls.',
}
function description(p: any) { return DESCRIPTIONS[p?.slug] || 'Everything you need to run successful events.' }

function isPopular(p: any) { return p.slug === 'pro' }

// Requesting a plan doesn't switch immediately — it raises a request for a
// platform super-admin to approve, which then activates the plan. Clicking
// "Choose Plan" opens a confirmation modal rather than a native alert.
const confirmPlan = ref<any>(null)
const isUpgrade = computed(() => {
  if (!confirmPlan.value || !currentPlan.value) return true
  return (confirmPlan.value.price_cents ?? 0) >= (currentPlan.value.price_cents ?? 0)
})

function askChange(p: any) {
  if (p.slug === currentSlug.value || pendingReq.value) return
  confirmPlan.value = p
}

async function change(p: any) {
  error.value = ''
  changingSlug.value = p.slug
  try {
    pendingReq.value = (await api<any>('/subscription/change-request', { method: 'POST', body: { plan: p.slug } })).data
    confirmPlan.value = null
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not submit your request. You may not have permission.'
    confirmPlan.value = null
  } finally {
    changingSlug.value = null
  }
}

async function cancelReq() {
  if (!confirm('Withdraw your pending plan-change request?')) return
  error.value = ''
  canceling.value = true
  try {
    await api('/subscription/change-request', { method: 'DELETE' })
    pendingReq.value = null
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not cancel the request.'
  } finally {
    canceling.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <!-- Current plan ------------------------------------------------------ -->
    <h2 class="block-title">Current plan</h2>
    <div v-if="currentPlan" class="current-card">
      <div class="current-top">
        <div class="min-w-0">
          <h3 class="current-name">{{ currentPlan.name }}</h3>
          <p class="current-desc">
            {{ description(currentPlan) }}
            <a class="lede-link" href="https://help.expouse.com/plans" target="_blank" rel="noopener">Learn More</a>
          </p>
        </div>
        <div class="text-right shrink-0">
          <div class="current-price">{{ price(currentPlan) }}</div>
          <div class="valid-till">Valid till: {{ validTill(sub?.current_period_end) }}</div>
        </div>
      </div>

      <div class="current-divider" />

      <div class="current-bottom">
        <div class="chips">
          <div class="chip">
            <span class="chip-ico"><AppIcon name="calendar" /></span>
            <div><div class="chip-n">{{ num(currentPlan.limits?.max_events) ?? 'Unlimited' }}</div><div class="chip-l">Events</div></div>
          </div>
          <div class="chip">
            <span class="chip-ico"><AppIcon name="users" /></span>
            <div><div class="chip-n">{{ num(currentPlan.limits?.max_attendees)?.toLocaleString() ?? 'Unlimited' }}</div><div class="chip-l">Attendees</div></div>
          </div>
          <div class="chip">
            <span class="chip-ico"><AppIcon name="box" /></span>
            <div><div class="chip-n">{{ currentPlan.limits?.storage_gb ?? '—' }} GB</div><div class="chip-l">Storage</div></div>
          </div>
        </div>
        <button class="more-info" type="button">More Info <span class="chev">›</span></button>
      </div>
    </div>
    <div v-else-if="!loading" class="current-card">
      <p class="muted">You don't have an active subscription yet. Choose a plan below to get started.</p>
    </div>

    <p v-if="error" class="error mt-3">{{ error }}</p>

    <!-- Pending request banner ------------------------------------------- -->
    <div v-if="pendingReq" class="pending-banner">
      <span class="pending-ico"><AppIcon name="bell" /></span>
      <div class="min-w-0 flex-1">
        <div class="pending-title">Plan change requested — awaiting approval</div>
        <div class="pending-sub">
          Your request to switch to <strong>{{ pendingReq.requested_plan?.name }}</strong>
          is pending review by a platform admin.
        </div>
      </div>
      <button class="pending-cancel" type="button" :disabled="canceling" @click="cancelReq">
        {{ canceling ? 'Canceling…' : 'Cancel request' }}
      </button>
    </div>

    <!-- Upgrade plan ------------------------------------------------------ -->
    <h2 class="block-title mt-8">Upgrade plan</h2>
    <p class="block-sub">View your current plan and upgrade when you need more.</p>

    <div v-if="loading" class="muted">Loading plans…</div>
    <div v-else class="plans-grid">
      <div v-for="(p, i) in sorted" :key="p.id" class="plan-card" :class="{ current: p.slug === currentSlug }">
        <div class="plan-head">
          <div class="flex items-center gap-2">
            <h3 class="plan-name">{{ p.name }}</h3>
            <span v-if="isPopular(p)" class="popular">POPULAR</span>
          </div>
          <div class="plan-price">{{ price(p) }} <span class="per">/{{ interval(p) }}</span></div>
        </div>

        <div class="plan-divider" />

        <p class="plan-tier">{{ tierHeading(i) }}</p>
        <ul class="feat-list">
          <li v-for="(b, bi) in bullets(p)" :key="bi">{{ b }}</li>
        </ul>

        <div class="plan-foot">
          <p v-if="tagline(p)" class="plan-tagline">{{ tagline(p) }}</p>
          <button
            v-if="p.slug === currentSlug"
            class="plan-cta is-current"
            type="button"
            disabled
          >
            Current Plan
          </button>
          <button
            v-else-if="p.slug === pendingSlug"
            class="plan-cta is-pending"
            type="button"
            disabled
          >
            Requested - pending
          </button>
          <button
            v-else
            class="plan-cta"
            type="button"
            :disabled="changingSlug === p.slug || !!pendingReq"
            @click="askChange(p)"
          >
            {{ changingSlug === p.slug ? 'Requesting…' : 'Choose Plan' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Choose-plan confirmation modal ----------------------------------- -->
    <Transition name="modal-fade">
      <div v-if="confirmPlan" class="modal-overlay" @click.self="confirmPlan = null">
        <div class="modal-card" role="dialog" aria-modal="true">
          <button class="modal-x" type="button" aria-label="Close" @click="confirmPlan = null">
            <AppIcon name="x" />
          </button>

          <div class="modal-badge" :class="{ down: !isUpgrade }">
            <AppIcon :name="isUpgrade ? 'arrow-up' : 'arrow-down'" />
          </div>

          <h3 class="modal-title">{{ isUpgrade ? 'Upgrade' : 'Switch' }} to {{ confirmPlan.name }}?</h3>
          <p class="modal-sub">
            We'll send a request to switch your subscription to the
            <strong>{{ confirmPlan.name }}</strong> plan. A platform admin will
            review and approve it before it takes effect.
          </p>

          <div class="modal-compare">
            <div class="cmp-col">
              <span class="cmp-label">Current</span>
              <span class="cmp-name">{{ currentPlan?.name || '—' }}</span>
              <span class="cmp-price">{{ currentPlan ? price(currentPlan) : '—' }}</span>
            </div>
            <div class="cmp-arrow"><AppIcon name="arrow-right" /></div>
            <div class="cmp-col to">
              <span class="cmp-label">New plan</span>
              <span class="cmp-name">{{ confirmPlan.name }}</span>
              <span class="cmp-price">{{ price(confirmPlan) }} <em>/{{ interval(confirmPlan) }}</em></span>
            </div>
          </div>

          <div class="modal-actions">
            <button class="modal-btn ghost" type="button" @click="confirmPlan = null">Cancel</button>
            <button
              class="modal-btn primary"
              type="button"
              :disabled="changingSlug === confirmPlan.slug"
              @click="change(confirmPlan)"
            >
              {{ changingSlug === confirmPlan.slug ? 'Requesting…' : 'Confirm request' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.block-title { font-size: 1.15rem; font-weight: 800; color: var(--ink); margin-bottom: 14px; }
.block-sub { color: var(--muted); font-size: 0.9rem; margin: -8px 0 16px; }
.lede-link { color: var(--brand); font-weight: 600; }
.lede-link:hover { text-decoration: underline; }

/* Current plan card */
.current-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 22px 24px; }
.current-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; }
.current-name { font-size: 1.15rem; font-weight: 800; color: var(--ink); }
.current-desc { margin-top: 4px; color: var(--muted); font-size: 0.9rem; max-width: 520px; }
.current-price { font-size: 1.6rem; font-weight: 800; color: var(--brand); line-height: 1; }
.valid-till { margin-top: 8px; color: var(--muted); font-size: 0.82rem; }
.current-divider { height: 1px; background: var(--line); margin: 18px 0; }
.current-bottom { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.chips { display: flex; gap: 34px; flex-wrap: wrap; }
.chip { display: flex; align-items: center; gap: 10px; }
.chip-ico { width: 38px; height: 38px; border-radius: 10px; background: var(--brand-soft); color: var(--brand); display: grid; place-items: center; }
.chip-ico :deep(svg) { width: 18px; height: 18px; }
.chip-n { font-weight: 800; color: var(--ink); font-size: 0.98rem; }
.chip-l { color: var(--muted); font-size: 0.8rem; }
.more-info { display: inline-flex; align-items: center; gap: 6px; background: #f7f7fb; border: 1px solid var(--line); color: var(--ink); font-weight: 600; font-size: 0.85rem; padding: 9px 14px; border-radius: 10px; cursor: pointer; }
.more-info:hover { background: #f0f0f5; }
.more-info .chev { font-size: 1.1rem; line-height: 1; }

/* Upgrade grid */
.plans-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
@media (max-width: 1100px) { .plans-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 720px) { .plans-grid { grid-template-columns: 1fr; } }
.plan-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 22px; display: flex; flex-direction: column; }
.plan-card.current { border-color: var(--brand); box-shadow: 0 0 0 1px var(--brand); }
.plan-head { min-height: 78px; }
.plan-name { font-size: 1.15rem; font-weight: 800; color: var(--ink); }
.popular { font-size: 0.66rem; font-weight: 800; letter-spacing: 0.04em; color: var(--brand-dark); background: var(--brand-soft); padding: 3px 8px; border-radius: 6px; }
.plan-price { margin-top: 12px; font-size: 1.5rem; font-weight: 800; color: var(--ink); }
.plan-price .per { font-size: 0.95rem; font-weight: 600; color: var(--muted); }
.plan-divider { height: 1px; background: var(--line); margin: 4px 0 18px; }
.plan-tier { color: var(--faint); font-size: 0.85rem; margin-bottom: 12px; }
.feat-list { list-style: none; display: flex; flex-direction: column; gap: 11px; flex: 1; }
.feat-list li { position: relative; padding-left: 18px; color: var(--ink); font-size: 0.9rem; line-height: 1.35; }
.feat-list li::before { content: ""; position: absolute; left: 0; top: 7px; width: 6px; height: 6px; border-radius: 50%; background: var(--brand); }
.plan-foot { margin-top: 24px; }
.plan-tagline { color: var(--faint); font-size: 0.85rem; margin-bottom: 12px; }
.plan-cta { width: 100%; padding: 12px; border: 0; border-radius: 11px; background: var(--brand); color: #fff; font-weight: 700; font-size: 0.92rem; cursor: pointer; }
.plan-cta:hover:not(:disabled) { background: var(--brand-dark); }
.plan-cta:disabled { opacity: 0.7; cursor: default; }
.plan-cta.is-current { background: #eef0f4; color: var(--muted); }
.plan-cta.is-pending { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }

/* Pending request banner */
.pending-banner {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-top: 16px;
  padding: 14px 18px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 14px;
}
.pending-ico {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: #fef3c7;
  color: #b45309;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.pending-ico :deep(svg) { width: 18px; height: 18px; }
.pending-title { font-weight: 700; color: var(--ink); font-size: 0.92rem; }
.pending-sub { color: var(--muted); font-size: 0.85rem; margin-top: 2px; }
.pending-sub strong { color: var(--ink); }
.pending-cancel {
  flex-shrink: 0;
  background: #fff;
  border: 1px solid #fde68a;
  color: #b45309;
  font-weight: 650;
  font-size: 0.85rem;
  padding: 9px 14px;
  border-radius: 10px;
  cursor: pointer;
}
.pending-cancel:hover:not(:disabled) { background: #fef3c7; }
.pending-cancel:disabled { opacity: 0.6; cursor: default; }

/* Choose-plan confirmation modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 60;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(17, 20, 32, 0.5);
  backdrop-filter: blur(3px);
}
.modal-card {
  position: relative;
  width: 100%;
  max-width: 440px;
  background: #fff;
  border-radius: 20px;
  padding: 30px 28px 24px;
  box-shadow: 0 24px 60px -12px rgba(17, 20, 32, 0.35);
  text-align: center;
}
.modal-x {
  position: absolute;
  top: 16px;
  right: 16px;
  width: 32px;
  height: 32px;
  display: grid;
  place-items: center;
  border: 0;
  border-radius: 9px;
  background: #f4f4f7;
  color: var(--muted);
  cursor: pointer;
}
.modal-x:hover { background: #ececf1; color: var(--ink); }
.modal-x :deep(svg) { width: 16px; height: 16px; }
.modal-badge {
  width: 56px;
  height: 56px;
  margin: 0 auto 16px;
  border-radius: 16px;
  display: grid;
  place-items: center;
  background: var(--brand-soft);
  color: var(--brand);
}
.modal-badge.down { background: #fff1f2; color: #e11d48; }
.modal-badge :deep(svg) { width: 24px; height: 24px; }
.modal-title { font-size: 1.25rem; font-weight: 800; color: var(--ink); }
.modal-sub { margin: 8px auto 0; max-width: 360px; color: var(--muted); font-size: 0.9rem; line-height: 1.5; }
.modal-sub strong { color: var(--ink); }

.modal-compare {
  display: flex;
  align-items: stretch;
  gap: 10px;
  margin: 22px 0 24px;
}
.cmp-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 14px 12px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: #fafafc;
}
.cmp-col.to { border-color: var(--brand); background: var(--brand-soft); }
.cmp-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--faint); }
.cmp-name { font-size: 1rem; font-weight: 800; color: var(--ink); }
.cmp-price { font-size: 0.9rem; font-weight: 700; color: var(--brand-dark); }
.cmp-price em { font-style: normal; font-weight: 600; color: var(--muted); font-size: 0.8rem; }
.cmp-arrow { display: grid; place-items: center; color: var(--faint); }
.cmp-arrow :deep(svg) { width: 18px; height: 18px; }

.modal-actions { display: flex; gap: 12px; }
.modal-btn { flex: 1; padding: 12px; border-radius: 12px; font-weight: 700; font-size: 0.92rem; cursor: pointer; }
.modal-btn.ghost { background: #f4f4f7; border: 1px solid var(--line); color: var(--ink); }
.modal-btn.ghost:hover { background: #ececf1; }
.modal-btn.primary { background: var(--brand); border: 0; color: #fff; }
.modal-btn.primary:hover:not(:disabled) { background: var(--brand-dark); }
.modal-btn.primary:disabled { opacity: 0.7; cursor: default; }

.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.2s ease; }
.modal-fade-enter-active .modal-card, .modal-fade-leave-active .modal-card { transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
.modal-fade-enter-from .modal-card, .modal-fade-leave-to .modal-card { transform: translateY(12px) scale(0.97); opacity: 0; }
</style>
