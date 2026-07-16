<script setup lang="ts">
import type { JoinConfig } from '~/stores/rooms'
import type { Meeting } from '~/stores/meetings'

const props = defineProps<{ meeting: Meeting }>()
const emit = defineEmits<{ join: [config: JoinConfig & { title: string }] }>()
const store = useMeetingsStore()

const acting = computed(() => store.acting[props.meeting.id] === true)
const joining = computed(() => store.joining[props.meeting.id] === true)

// Mirrors the server-side join window (MeetingController::join): joinable
// from 10 minutes before starts_at through 15 minutes past the end — an
// untimed meeting (no proposed time was ever set) has no window at all.
const JOIN_LEAD_MS = 10 * 60_000
const DEFAULT_DURATION_MS = 30 * 60_000
const JOIN_GRACE_MS = 15 * 60_000

const isRunning = computed(() => {
  const m = props.meeting
  if (m.status !== 'confirmed' || m.source !== 'delegate') return false
  if (!m.starts_at) return true

  const start = new Date(m.starts_at).getTime()
  const end = m.ends_at ? new Date(m.ends_at).getTime() : start + DEFAULT_DURATION_MS
  const now = Date.now()
  return now >= start - JOIN_LEAD_MS && now <= end + JOIN_GRACE_MS
})

async function join() {
  const cfg = await store.join(props.meeting)
  if (cfg) emit('join', cfg)
}

const person = computed(() => props.meeting.counterpart)

const subtitle = computed(() => {
  const c = person.value
  if (!c) return ''
  return [c.job_title, c.company].filter(Boolean).join(' · ')
})

const when = computed(() => {
  const m = props.meeting
  // Lounge booking: show the exact slot day + range in the event's local terms.
  if (m.date && m.slot) {
    const [y, mo, dd] = m.date.split('-').map(Number)
    const day = new Date(y ?? 1970, (mo ?? 1) - 1, dd ?? 1)
      .toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })
    return `${day} · ${m.slot}`
  }
  const iso = m.starts_at
  if (!iso) return 'Time to be arranged'
  const d = new Date(iso)
  return d.toLocaleString(undefined, {
    weekday: 'short', month: 'short', day: 'numeric',
    hour: 'numeric', minute: '2-digit',
  })
})

// A booth meeting reads differently from a delegate one: incoming means the
// exhibitor's team assigned it to you, not that someone wants to meet you.
const relation = computed(() => {
  const m = props.meeting
  if (m.source === 'exhibitor') {
    return m.direction === 'incoming'
      ? `Assigned to you${m.exhibitor ? ` · ${m.exhibitor}` : ''}`
      : `You requested this${m.exhibitor ? ` · ${m.exhibitor}` : ''}`
  }
  return m.direction === 'incoming' ? 'Wants to meet you' : 'You invited'
})

const badge = computed(() => {
  const m = props.meeting
  if (m.status === 'confirmed') return { label: 'Approved', cls: 'ok' }
  if (m.status === 'declined') return { label: 'Rejected', cls: 'no' }
  if (m.status === 'canceled') return { label: 'Canceled', cls: 'no' }
  if (m.status === 'completed') return { label: 'Completed', cls: 'muted' }
  return m.direction === 'incoming'
    ? { label: 'Awaiting your reply', cls: 'wait' }
    : { label: 'Request sent', cls: 'wait' }
})
</script>

<template>
  <article class="card">
    <div class="who">
      <div class="avatar">
        <UserAvatar :src="person?.avatar_url" :name="person?.name" />
      </div>
      <div class="meta">
        <h3 class="name">
          {{ person?.name || 'Attendee' }}
          <span v-if="meeting.source === 'exhibitor'" class="booth">Booth</span>
        </h3>
        <p v-if="subtitle" class="role">{{ subtitle }}</p>
        <span class="dir" :class="meeting.direction">{{ relation }}</span>
      </div>
      <span class="badge" :class="badge.cls">{{ badge.label }}</span>
    </div>

    <div class="detail">
      <p v-if="meeting.title" class="title">{{ meeting.title }}</p>
      <p v-if="meeting.agenda" class="agenda">{{ meeting.agenda }}</p>
      <p class="when">
        <svg viewBox="0 0 24 24"><path d="M7 4v3M17 4v3M4 9h16M5 7h14v13H5z" /></svg>
        {{ when }}
      </p>
      <!-- In-person / hybrid events: where to actually turn up. -->
      <p v-if="meeting.location" class="when">
        <svg viewBox="0 0 24 24"><path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z" /><circle cx="12" cy="10" r="2.6" /></svg>
        {{ meeting.location }}
      </p>
    </div>

    <!-- Incoming pending → approve / reject -->
    <div v-if="meeting.can_respond" class="acts">
      <button type="button" class="btn reject" :disabled="acting" @click="store.respond(meeting, 'reject')">Reject</button>
      <button type="button" class="btn approve" :disabled="acting" @click="store.respond(meeting, 'accept')">Approve</button>
    </div>
    <!-- Outgoing pending → cancel -->
    <div v-else-if="meeting.direction === 'outgoing' && meeting.status === 'requested'" class="acts">
      <button type="button" class="btn cancel" :disabled="acting" @click="store.respond(meeting, 'cancel')">Cancel request</button>
    </div>
    <!-- Confirmed and running → join the live video room -->
    <div v-else-if="isRunning" class="acts">
      <button type="button" class="btn join" :disabled="joining" @click="join">
        <svg viewBox="0 0 24 24"><path d="M3 7h11v10H3zM14 10l7-3v10l-7-3z" /></svg>
        {{ joining ? 'Joining…' : 'Join meeting' }}
      </button>
    </div>
  </article>
</template>

<style scoped>
.card { background: #fff; border-radius: 14px; padding: 16px; box-shadow: 0 1px 2px rgba(15,23,42,.05); display: flex; flex-direction: column; gap: 14px; }

.who { display: flex; align-items: flex-start; gap: 12px; }
.avatar { width: 48px; height: 48px; border-radius: 50%; overflow: hidden; flex: 0 0 auto; background: color-mix(in srgb, var(--brand-primary) 12%, #fff); display: flex; align-items: center; justify-content: center; }
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.ini { font-size: 1.1rem; font-weight: 700; color: color-mix(in srgb, var(--brand-primary) 75%, #fff); }
.meta { min-width: 0; flex: 1; }
.name { margin: 0; font-size: .98rem; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.role { margin: 2px 0 0; color: #64748b; font-size: .8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dir { display: inline-block; margin-top: 6px; font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .3px; color: #94a3b8; }
.booth { margin-left: 7px; padding: 2px 7px; border-radius: 999px; font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; vertical-align: middle; color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 12%, #fff); }

.badge { flex: 0 0 auto; font-size: .68rem; font-weight: 700; padding: 4px 9px; border-radius: 999px; white-space: nowrap; }
.badge.ok { background: #e6f9f0; color: #16a34a; }
.badge.no { background: #fdecec; color: #dc2626; }
.badge.wait { background: #fff3e0; color: #d97706; }
.badge.muted { background: #f1f5f9; color: #64748b; }

.detail { border-top: 1px solid #eef0f3; padding-top: 12px; }
.title { margin: 0; font-size: .9rem; font-weight: 600; color: #334155; }
.agenda { margin: 4px 0 0; font-size: .84rem; color: #64748b; line-height: 1.4; }
.when { display: flex; align-items: center; gap: 6px; margin: 8px 0 0; font-size: .8rem; color: #475569; }
.when svg { width: 15px; height: 15px; fill: none; stroke: var(--brand-primary); stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

.acts { display: flex; gap: 10px; }
.btn { flex: 1; border: none; border-radius: 10px; padding: 10px; font: inherit; font-size: .85rem; font-weight: 600; cursor: pointer; }
.btn:disabled { opacity: .6; cursor: default; }
.btn.approve { background: var(--brand-primary); color: #fff; }
.btn.reject { background: #f1f5f9; color: #475569; }
.btn.reject:hover:not(:disabled) { background: #fdecec; color: #dc2626; }
.btn.cancel { background: #f1f5f9; color: #475569; }
.btn.cancel:hover:not(:disabled) { background: #fdecec; color: #dc2626; }
.btn.join { display: inline-flex; align-items: center; justify-content: center; gap: 7px; background: var(--brand-primary); color: #fff; }
.btn.join svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
</style>
