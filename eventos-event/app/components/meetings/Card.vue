<script setup lang="ts">
import type { JoinConfig } from '~/stores/rooms'
import type { Meeting } from '~/stores/meetings'

const props = defineProps<{ meeting: Meeting, tz?: string }>()
const emit = defineEmits<{ join: [config: JoinConfig & { title: string }] }>()
const store = useMeetingsStore()

const acting = computed(() => store.acting[props.meeting.id] === true)
const joining = computed(() => store.joining[props.meeting.id] === true)

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
  return [c.job_title, c.company].filter(Boolean).join(' at ')
})

const when = computed(() => meetingTimeLabel(props.meeting, props.tz))

const badge = computed(() => {
  const m = props.meeting
  if (m.status === 'confirmed') return { label: 'Accepted', cls: 'ok' }
  if (m.status === 'declined') return { label: 'Rejected', cls: 'no' }
  if (m.status === 'canceled') return { label: 'Canceled', cls: 'no' }
  if (m.status === 'completed') return { label: 'Completed', cls: 'muted' }
  return m.direction === 'incoming'
    ? { label: 'Received - Pending', cls: 'wait' }
    : { label: 'Sent - Pending', cls: 'wait' }
})
</script>

<template>
  <article class="card">
    <header class="top">
      <span class="badge" :class="badge.cls">{{ badge.label }}</span>
      <span class="time">{{ when }}</span>
    </header>

    <div class="body">
      <h3 v-if="meeting.title" class="title">{{ meeting.title }}</h3>
      <p v-if="meeting.agenda" class="agenda">{{ meeting.agenda }}</p>

      <p class="with">
        Meeting with
        <span v-if="meeting.source === 'exhibitor'" class="booth">Booth</span>
      </p>
      <div class="who">
        <div class="avatar">
          <UserAvatar :src="person?.avatar_url" :name="person?.name" />
        </div>
        <div class="meta">
          <h4 class="name">{{ person?.name || 'Attendee' }}</h4>
          <p v-if="subtitle" class="role">{{ subtitle }}</p>
        </div>
      </div>
    </div>

    <div class="place" :class="{ empty: !meeting.location }">
      <svg viewBox="0 0 24 24">
        <path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z" />
        <circle cx="12" cy="10" r="2.6" />
      </svg>
      <span>{{ meeting.location || 'Location not added yet.' }}</span>
    </div>

    <div v-if="meeting.can_respond" class="acts">
      <button type="button" class="btn ghost" :disabled="acting"
        @click="store.respond(meeting, 'reject')">Decline</button>
      <button type="button" class="btn primary" :disabled="acting"
        @click="store.respond(meeting, 'accept')">Accept</button>
    </div>
    <div v-else-if="meeting.direction === 'outgoing' && meeting.status === 'requested'" class="acts">
      <button type="button" class="btn primary" :disabled="acting"
        @click="store.respond(meeting, 'cancel')">Withdraw</button>
    </div>
    <div v-else-if="isRunning" class="acts">
      <button type="button" class="btn primary" :disabled="joining" @click="join">{{ joining ? 'Joining…' : 'Join'
        }}</button>
    </div>
    <div v-else class="acts"> 
        <button type="button" class="btn primary" disabled @click="join">
          {{ joining ? 'Joining…' : 'Join'}}
        </button>
    </div>
  </article>
</template>

<style scoped>
.card {
  background: #fff;
  border-radius: 12px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  border: 1px solid #E8E8EE;
  /* height: 100%; */
}

.top {
  display: flex;
  align-items: center;
  gap: 10px;
  padding-bottom: 14px;
  border-bottom: 1px solid #eef0f3;
}

.badge {
  flex: 0 0 auto;
  font-size: .7rem;
  font-weight: 600;
  padding: 5px 10px;
  border-radius: 6px;
  white-space: nowrap;
}

.badge.ok {
  background: #e6f9f0;
  color: #16a34a;
}

.badge.no {
  background: #fdecec;
  color: #dc2626;
}

.badge.wait {
  background: #fff6e5;
  color: #d97706;
}

.badge.muted {
  background: #f1f5f9;
  color: #64748b;
}

.time {
  font-size: .84rem;
  font-weight: 600;
  color: #334155;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: #212529;
  line-height: 1.35;
}

.agenda {
  margin: 6px 0 0;
  font-size: 14px;
  color: #64676A;
  line-height: 1.2;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.with {
  margin: 14px 0 10px;
  font-size: .8rem;
  color: #94a3b8;
}

.body>.with:first-child {
  margin-top: 0;
}

.booth {
  margin-left: 6px;
  padding: 2px 7px;
  border-radius: 999px;
  font-size: .62rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .4px;
  color: var(--brand-primary);
  background: color-mix(in srgb, var(--brand-primary) 12%, #fff);
}

.who {
  display: flex;
  align-items: center;
  gap: 12px;
}

.avatar {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  overflow: hidden;
  flex: 0 0 auto;
  background: color-mix(in srgb, var(--brand-primary) 12%, #fff);
  display: flex;
  align-items: center;
  justify-content: center;
}

.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.meta {
  min-width: 0;
}

.name {
  margin: 0;
  font-size: .95rem;
  line-height: 1.2;
  font-weight: 700;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.role {
  margin: 2px 0 0;
  color: #64748b;
  font-size: .8rem;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.place {
  display: flex;
  align-items: center;
  gap: 8px;
  padding-top: 14px;
  border-top: 1px solid #eef0f3;
  font-size: .84rem;
  color: var(--brand-primary);
  font-weight: 600;
}

.place svg {
  width: 18px;
  height: 18px;
  flex: 0 0 auto;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.place span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.place.empty {
  color: #94a3b8;
  font-weight: 400;
  font-style: italic;
}

.acts {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.btn {
  border: none;
  border-radius: 8px;
  padding: 7px 22px;
  font: inherit;
  font-size: .85rem;
  font-weight: 600;
  cursor: pointer;
}

.btn:disabled {
  opacity: .6;
  cursor: default;
}

.btn.primary {
  background: var(--brand-primary);
  color: #fff;
}

.btn.ghost {
  background: #f1f5f9;
  color: #475569;
}

.btn.ghost:hover:not(:disabled) {
  background: #fdecec;
  color: #dc2626;
}
</style>
