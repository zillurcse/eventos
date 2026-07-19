<script setup lang="ts">
definePageMeta({ middleware: 'platform', title: 'Plan Requests', subtitle: 'Approve or reject organizer plan changes' })

const api = useApi()
const tab = ref<'pending' | 'approved' | 'rejected'>('pending')
const all = ref<any[]>([])
const loading = ref(true)
const error = ref('')

// Load every request once, then filter/count client-side so the tab badges stay
// live without extra round-trips. Approve/reject reload the whole list.
async function load() {
  loading.value = true
  error.value = ''
  try {
    all.value = (await api<any>('/admin/plan-change-requests?status=all')).data
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not load requests.'
  } finally {
    loading.value = false
  }
}

const counts = computed(() => ({
  pending: all.value.filter(r => r.status === 'pending').length,
  approved: all.value.filter(r => r.status === 'approved').length,
  rejected: all.value.filter(r => r.status === 'rejected').length,
}))
const requests = computed(() => all.value.filter(r => r.status === tab.value))

// ── Approve / reject via in-app modals (no native confirm/prompt) ──────────
const approveTarget = ref<any>(null)
const rejectTarget = ref<any>(null)
const rejectNote = ref('')
const busy = ref(false)

function askApprove(r: any) { approveTarget.value = r }
function askReject(r: any) { rejectTarget.value = r; rejectNote.value = '' }

async function doApprove() {
  const r = approveTarget.value
  if (!r) return
  busy.value = true
  error.value = ''
  try {
    await api(`/admin/plan-change-requests/${r.id}/approve`, { method: 'POST' })
    approveTarget.value = null
    await load()
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not approve the request.'
    approveTarget.value = null
  } finally {
    busy.value = false
  }
}

async function doReject() {
  const r = rejectTarget.value
  if (!r) return
  busy.value = true
  error.value = ''
  try {
    await api(`/admin/plan-change-requests/${r.id}/reject`, {
      method: 'POST',
      body: { review_note: rejectNote.value.trim() || undefined },
    })
    rejectTarget.value = null
    await load()
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not reject the request.'
    rejectTarget.value = null
  } finally {
    busy.value = false
  }
}

function money(cents: number | undefined) {
  const v = (cents ?? 0) / 100
  return v % 1 === 0 ? `$${v}` : `$${v.toFixed(2)}`
}
function when(iso: string | null) {
  if (!iso) return '—'
  const d = new Date(iso)
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}
function initials(name: string | undefined) {
  if (!name) return '—'
  return name.trim().split(/\s+/).slice(0, 2).map(w => w[0]?.toUpperCase()).join('')
}
// Is the requested plan a step up or down (by price) from the current one?
function isUpgrade(r: any) {
  return (r.requested_plan?.price_cents ?? 0) >= (r.current_plan?.price_cents ?? 0)
}

onMounted(load)
</script>

<template>
  <div>
    <!-- Summary tiles ---------------------------------------------------- -->
    <div class="stat-row">
      <button class="tile" :class="{ active: tab === 'pending' }" @click="tab = 'pending'">
        <span class="tile-ico pending"><AppIcon name="bell" /></span>
        <div>
          <div class="tile-n">{{ counts.pending }}</div>
          <div class="tile-l">Pending review</div>
        </div>
      </button>
      <button class="tile" :class="{ active: tab === 'approved' }" @click="tab = 'approved'">
        <span class="tile-ico approved"><AppIcon name="shield" /></span>
        <div>
          <div class="tile-n">{{ counts.approved }}</div>
          <div class="tile-l">Approved</div>
        </div>
      </button>
      <button class="tile" :class="{ active: tab === 'rejected' }" @click="tab = 'rejected'">
        <span class="tile-ico rejected"><AppIcon name="x" /></span>
        <div>
          <div class="tile-n">{{ counts.rejected }}</div>
          <div class="tile-l">Rejected</div>
        </div>
      </button>
    </div>

    <p v-if="error" class="error mb-3">{{ error }}</p>

    <!-- Requests table --------------------------------------------------- -->
    <div class="dt-wrap">
      <table>
        <thead>
          <tr>
            <th>Organization</th>
            <th>Plan change</th>
            <th>Requested by</th>
            <th>Date</th>
            <th class="text-right">{{ tab === 'pending' ? 'Action' : 'Status' }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in requests" :key="r.id">
            <!-- Org -->
            <td>
              <div class="org-cell">
                <span class="org-avatar">{{ initials(r.organization?.name) }}</span>
                <strong>{{ r.organization?.name || '—' }}</strong>
              </div>
            </td>

            <!-- From → To -->
            <td>
              <div class="change-cell">
                <span class="from">{{ r.current_plan?.name || 'No plan' }}</span>
                <span class="arrow" :class="{ down: !isUpgrade(r) }"><AppIcon name="arrow-right" /></span>
                <span class="to">
                  {{ r.requested_plan?.name }}
                  <em>{{ money(r.requested_plan?.price_cents) }}</em>
                </span>
              </div>
              <div v-if="r.note" class="note">“{{ r.note }}”</div>
            </td>

            <!-- Requester -->
            <td class="muted">{{ r.requested_by || '—' }}</td>

            <!-- Date -->
            <td class="muted whitespace-nowrap">{{ when(r.created_at) }}</td>

            <!-- Action / status -->
            <td class="text-right whitespace-nowrap">
              <template v-if="tab === 'pending'">
                <button class="btn sm ghost" :disabled="busy" @click="askReject(r)">Reject</button>
                <button class="btn sm ml-1.5" :disabled="busy" @click="askApprove(r)">Approve</button>
              </template>
              <template v-else>
                <span class="badge" :class="{ published: r.status === 'approved', suspended: r.status === 'rejected' }">
                  {{ r.status }}
                </span>
                <div v-if="r.review_note" class="note mt-1">“{{ r.review_note }}”</div>
                <div v-if="r.reviewed_at" class="muted text-[.78rem] mt-0.5">{{ when(r.reviewed_at) }}</div>
              </template>
            </td>
          </tr>

          <tr v-if="!loading && !requests.length" class="dt-empty">
            <td colspan="5">
              <div class="empty">
                <span class="empty-ico"><AppIcon name="clipboard" /></span>
                <p>No {{ tab }} requests.</p>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="loading" class="muted p-4">Loading…</p>
    </div>

    <!-- Approve modal ---------------------------------------------------- -->
    <Transition name="modal-fade">
      <div v-if="approveTarget" class="modal-overlay" @click.self="approveTarget = null">
        <div class="modal-card" role="dialog" aria-modal="true">
          <div class="modal-badge ok"><AppIcon name="shield" /></div>
          <h3 class="modal-title">Approve plan change?</h3>
          <p class="modal-sub">
            This activates the <strong>{{ approveTarget.requested_plan?.name }}</strong> plan for
            <strong>{{ approveTarget.organization?.name }}</strong> immediately.
          </p>

          <div class="modal-compare">
            <div class="cmp-col">
              <span class="cmp-label">Current</span>
              <span class="cmp-name">{{ approveTarget.current_plan?.name || 'No plan' }}</span>
            </div>
            <div class="cmp-arrow"><AppIcon name="arrow-right" /></div>
            <div class="cmp-col to">
              <span class="cmp-label">New plan</span>
              <span class="cmp-name">{{ approveTarget.requested_plan?.name }}</span>
              <span class="cmp-price">{{ money(approveTarget.requested_plan?.price_cents) }}</span>
            </div>
          </div>

          <div class="modal-actions">
            <button class="modal-btn ghost" type="button" :disabled="busy" @click="approveTarget = null">Cancel</button>
            <button class="modal-btn primary" type="button" :disabled="busy" @click="doApprove">
              {{ busy ? 'Approving…' : 'Approve & activate' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Reject modal ----------------------------------------------------- -->
    <Transition name="modal-fade">
      <div v-if="rejectTarget" class="modal-overlay" @click.self="rejectTarget = null">
        <div class="modal-card" role="dialog" aria-modal="true">
          <div class="modal-badge bad"><AppIcon name="x" /></div>
          <h3 class="modal-title">Reject this request?</h3>
          <p class="modal-sub">
            <strong>{{ rejectTarget.organization?.name }}</strong> stays on their current plan.
            Add an optional reason they'll see.
          </p>

          <textarea
            v-model="rejectNote"
            class="reject-note"
            rows="3"
            maxlength="500"
            placeholder="Reason (optional)…"
          />

          <div class="modal-actions">
            <button class="modal-btn ghost" type="button" :disabled="busy" @click="rejectTarget = null">Cancel</button>
            <button class="modal-btn danger" type="button" :disabled="busy" @click="doReject">
              {{ busy ? 'Rejecting…' : 'Reject request' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
/* ── Summary tiles ── */
.stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 22px; }
@media (max-width: 720px) { .stat-row { grid-template-columns: 1fr; } }
.tile {
  display: flex; align-items: center; gap: 14px;
  padding: 16px 18px; text-align: left;
  background: #fff; border: 1px solid var(--line); border-radius: 14px;
  cursor: pointer; transition: border-color .15s, box-shadow .15s, transform .15s;
}
.tile:hover { border-color: #c9cdd6; }
.tile.active { border-color: var(--brand); box-shadow: 0 0 0 1px var(--brand); }
.tile-ico { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; flex-shrink: 0; }
.tile-ico :deep(svg) { width: 20px; height: 20px; }
.tile-ico.pending { background: #fef3c7; color: #b45309; }
.tile-ico.approved { background: #dcfce7; color: #15803d; }
.tile-ico.rejected { background: #fee2e2; color: #b91c1c; }
.tile-n { font-size: 1.6rem; font-weight: 800; color: var(--ink); line-height: 1; }
.tile-l { color: var(--muted); font-size: .82rem; margin-top: 3px; }

/* ── Table cells ── */
.org-cell { display: flex; align-items: center; gap: 10px; }
.org-avatar {
  width: 34px; height: 34px; flex-shrink: 0; border-radius: 9px;
  background: var(--brand-soft); color: var(--brand-dark);
  display: grid; place-items: center; font-weight: 800; font-size: .78rem;
}
.change-cell { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.change-cell .from { color: var(--muted); font-size: .9rem; }
.change-cell .arrow { color: var(--brand); display: grid; place-items: center; }
.change-cell .arrow.down { color: #e11d48; transform: rotate(90deg); }
.change-cell .arrow :deep(svg) { width: 16px; height: 16px; }
.change-cell .to { font-weight: 700; color: var(--ink); font-size: .92rem; }
.change-cell .to em { font-style: normal; font-weight: 600; color: var(--brand-dark); font-size: .82rem; margin-left: 4px; }
.note { color: var(--muted); font-size: .8rem; margin-top: 4px; font-style: italic; }

/* ── Empty state ── */
.empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 40px 0; color: var(--faint); }
.empty-ico { width: 44px; height: 44px; border-radius: 12px; background: #f4f5f8; display: grid; place-items: center; }
.empty-ico :deep(svg) { width: 22px; height: 22px; }

/* ── Modals (shared with organizer plans styling) ── */
.modal-overlay {
  position: fixed; inset: 0; z-index: 60; display: grid; place-items: center; padding: 20px;
  background: rgba(17, 20, 32, .5); backdrop-filter: blur(3px);
}
.modal-card {
  position: relative; width: 100%; max-width: 440px; background: #fff; border-radius: 20px;
  padding: 30px 28px 24px; box-shadow: 0 24px 60px -12px rgba(17, 20, 32, .35); text-align: center;
}
.modal-badge { width: 56px; height: 56px; margin: 0 auto 16px; border-radius: 16px; display: grid; place-items: center; }
.modal-badge :deep(svg) { width: 24px; height: 24px; }
.modal-badge.ok { background: #dcfce7; color: #15803d; }
.modal-badge.bad { background: #fee2e2; color: #b91c1c; }
.modal-title { font-size: 1.25rem; font-weight: 800; color: var(--ink); }
.modal-sub { margin: 8px auto 0; max-width: 360px; color: var(--muted); font-size: .9rem; line-height: 1.5; }
.modal-sub strong { color: var(--ink); }

.modal-compare { display: flex; align-items: stretch; gap: 10px; margin: 22px 0 24px; }
.cmp-col {
  flex: 1; display: flex; flex-direction: column; gap: 3px; padding: 14px 12px;
  border: 1px solid var(--line); border-radius: 14px; background: #fafafc;
}
.cmp-col.to { border-color: var(--brand); background: var(--brand-soft); }
.cmp-label { font-size: .7rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--faint); }
.cmp-name { font-size: 1rem; font-weight: 800; color: var(--ink); }
.cmp-price { font-size: .9rem; font-weight: 700; color: var(--brand-dark); }
.cmp-arrow { display: grid; place-items: center; color: var(--faint); }
.cmp-arrow :deep(svg) { width: 18px; height: 18px; }

.reject-note {
  width: 100%; margin: 20px 0 22px; padding: 12px 14px; border: 1px solid var(--line);
  border-radius: 12px; font: inherit; font-size: .9rem; resize: vertical; text-align: left;
}
.reject-note:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-soft); }

.modal-actions { display: flex; gap: 12px; }
.modal-btn { flex: 1; padding: 12px; border-radius: 12px; font-weight: 700; font-size: .92rem; cursor: pointer; border: 0; }
.modal-btn.ghost { background: #f4f4f7; border: 1px solid var(--line); color: var(--ink); }
.modal-btn.ghost:hover:not(:disabled) { background: #ececf1; }
.modal-btn.primary { background: var(--brand); color: #fff; }
.modal-btn.primary:hover:not(:disabled) { background: var(--brand-dark); }
.modal-btn.danger { background: #dc2626; color: #fff; }
.modal-btn.danger:hover:not(:disabled) { background: #b91c1c; }
.modal-btn:disabled { opacity: .7; cursor: default; }

.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity .2s ease; }
.modal-fade-enter-active .modal-card, .modal-fade-leave-active .modal-card { transition: transform .22s cubic-bezier(.16, 1, .3, 1), opacity .2s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
.modal-fade-enter-from .modal-card, .modal-fade-leave-to .modal-card { transform: translateY(12px) scale(.97); opacity: 0; }
</style>
