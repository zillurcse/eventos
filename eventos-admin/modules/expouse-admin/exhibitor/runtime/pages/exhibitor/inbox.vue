<script setup lang="ts">
definePageMeta({ middleware: 'exhibitor', title: 'Inbox', subtitle: 'Messages & meeting requests from attendees' })

const api = useApi()

const tab = ref<'messages' | 'meetings'>('messages')
const suspended = ref(false)

// ── Members (for meeting assignment) ─────────────────────────────────────
const members = ref<any[]>([])
async function loadMembers() {
  try { members.value = (await api<any>('/exhibitor/members')).data } catch { /* */ }
}

// ── Conversations ────────────────────────────────────────────────────────
const conversations = ref<any[]>([])
const activeId = ref<string | null>(null)
const messages = ref<any[]>([])
const threadLoading = ref(false)
const reply = ref('')
const sending = ref(false)

const active = computed(() => conversations.value.find(c => c.id === activeId.value) || null)

async function loadConversations() {
  try {
    conversations.value = (await api<any>('/exhibitor/inbox/conversations')).data
  } catch (e: any) {
    if (e?.response?.status === 403) suspended.value = true
  }
}

async function openThread(c: any) {
  activeId.value = c.id
  messages.value = []
  threadLoading.value = true
  try {
    const res = await api<any>(`/exhibitor/inbox/conversations/${c.id}/messages`)
    messages.value = res.data.messages
    c.unread = 0
  } finally {
    threadLoading.value = false
  }
}

async function sendReply() {
  const body = reply.value.trim()
  if (!body || !activeId.value || sending.value) return
  sending.value = true
  try {
    const res = await api<any>(`/exhibitor/inbox/conversations/${activeId.value}/messages`, {
      method: 'POST', body: { body },
    })
    messages.value.push(res.data)
    reply.value = ''
    const c = active.value
    if (c) c.last_message = { body, mine: true, created_at: res.data.created_at }
  } finally {
    sending.value = false
  }
}

// ── Meeting requests ─────────────────────────────────────────────────────
const requests = ref<any[]>([])
const assignPick = reactive<Record<string, number | ''>>({})
const acting = reactive<Record<string, boolean>>({})

async function loadRequests() {
  try { requests.value = (await api<any>('/exhibitor/inbox/meeting-requests')).data } catch { /* */ }
}

async function respond(r: any, action: 'assign' | 'decline') {
  if (acting[r.id]) return
  if (action === 'assign' && !assignPick[r.id]) return
  acting[r.id] = true
  try {
    const res = await api<any>(`/exhibitor/inbox/meeting-requests/${r.id}`, {
      method: 'PATCH',
      body: { action, member_id: action === 'assign' ? assignPick[r.id] : undefined },
    })
    const i = requests.value.findIndex(x => x.id === r.id)
    if (i >= 0) requests.value[i] = res.data
  } finally {
    acting[r.id] = false
  }
}

const pendingCount = computed(() => requests.value.filter(r => r.status === 'requested').length)
const unreadCount = computed(() => conversations.value.reduce((n, c) => n + (c.unread || 0), 0))

// ── Live-ish refresh (admin SPA has no WebSocket; poll while visible) ─────
async function refreshActiveThread() {
  if (!activeId.value) return
  try {
    const res = await api<any>(`/exhibitor/inbox/conversations/${activeId.value}/messages`)
    messages.value = res.data.messages
  } catch { /* */ }
}
async function poll() {
  if (typeof document !== 'undefined' && document.visibilityState === 'hidden') return
  await Promise.all([loadConversations(), loadRequests()])
  await refreshActiveThread()
}
let pollTimer: ReturnType<typeof setInterval> | undefined
onMounted(() => { pollTimer = setInterval(poll, 12000) })
onBeforeUnmount(() => clearInterval(pollTimer))

function fmtSlot(s: string) { return s ? s.replace('-', ' – ') : '' }
function statusLabel(s: string) {
  return { requested: 'Awaiting assignment', assigned: 'Assigned', confirmed: 'Confirmed', declined: 'Declined', canceled: 'Canceled' }[s] || s
}

onMounted(() => { loadConversations(); loadRequests(); loadMembers() })
</script>

<template>
  <div>
    <div v-if="suspended" class="card"><p class="error">This exhibitor account is suspended.</p></div>

    <template v-else>
      <!-- Tabs -->
      <div class="flex gap-1 mb-4 border-b border-line">
        <button
          class="px-4 py-2.5 text-[.9rem] font-semibold border-b-2 -mb-px"
          :class="tab === 'messages' ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink'"
          @click="tab = 'messages'"
        >Messages <span v-if="unreadCount" class="badge active ml-1">{{ unreadCount }}</span></button>
        <button
          class="px-4 py-2.5 text-[.9rem] font-semibold border-b-2 -mb-px"
          :class="tab === 'meetings' ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink'"
          @click="tab = 'meetings'"
        >Meeting Requests <span v-if="pendingCount" class="badge active ml-1">{{ pendingCount }}</span></button>
      </div>

      <!-- ── Messages ── -->
      <div v-if="tab === 'messages'" class="grid grid-cols-[300px_1fr] gap-4 max-md:grid-cols-1">
        <div class="card p-0 overflow-hidden">
          <div v-if="!conversations.length" class="p-6 text-center muted text-[.86rem]">No messages yet.</div>
          <button
            v-for="c in conversations"
            :key="c.id"
            class="w-full text-left px-4 py-3 border-b border-line flex flex-col gap-0.5 hover:bg-[#f7f8fa] cursor-pointer"
            :class="{ 'bg-[#f2f1fb]': activeId === c.id }"
            @click="openThread(c)"
          >
            <div class="flex items-center justify-between gap-2">
              <strong class="text-[.9rem] text-ink truncate">{{ c.attendee.name }}</strong>
              <span v-if="c.unread" class="badge active shrink-0">{{ c.unread }}</span>
            </div>
            <span v-if="c.last_message" class="muted text-[.8rem] truncate">
              {{ c.last_message.mine ? 'You: ' : '' }}{{ c.last_message.body }}
            </span>
          </button>
        </div>

        <div class="card flex flex-col min-h-[420px]">
          <div v-if="!activeId" class="flex-1 grid place-items-center muted text-[.88rem]">Select a conversation.</div>
          <template v-else>
            <div class="pb-3 mb-3 border-b border-line">
              <strong class="text-ink">{{ active?.attendee.name }}</strong>
              <span v-if="active?.attendee.company" class="muted text-[.82rem]"> · {{ active?.attendee.company }}</span>
            </div>
            <div class="flex-1 flex flex-col gap-2 overflow-y-auto max-h-[46vh]">
              <div v-if="threadLoading" class="muted text-[.85rem]">Loading…</div>
              <div
                v-for="m in messages"
                :key="m.id"
                class="max-w-[78%] px-3.5 py-2 rounded-[14px] text-[.9rem] leading-snug"
                :class="m.mine ? 'self-end bg-brand text-white rounded-tr-[4px]' : 'self-start bg-[#f1f5f9] text-ink rounded-tl-[4px]'"
              >{{ m.body }}</div>
            </div>
            <div class="mt-3 pt-3 border-t border-line flex gap-2">
              <input
                v-model="reply"
                maxlength="1000"
                placeholder="Type a reply…"
                class="flex-1"
                @keydown.enter="sendReply"
              >
              <button class="btn" :disabled="sending || !reply.trim()" @click="sendReply">{{ sending ? '…' : 'Send' }}</button>
            </div>
          </template>
        </div>
      </div>

      <!-- ── Meeting Requests ── -->
      <div v-else class="flex flex-col gap-3">
        <div v-if="!requests.length" class="card"><p class="muted">No meeting requests yet.</p></div>

        <div v-for="r in requests" :key="r.id" class="card">
          <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <strong class="text-ink">{{ r.attendee.name }}</strong>
                <span v-if="r.attendee.company" class="muted text-[.82rem]">{{ r.attendee.company }}</span>
                <span class="badge" :class="{ active: r.status === 'confirmed', danger: r.status === 'declined' }">{{ statusLabel(r.status) }}</span>
              </div>
              <p v-if="r.subject" class="mt-1 text-[.92rem] font-semibold text-ink">{{ r.subject }}</p>
              <p v-if="r.agenda" class="mt-0.5 text-[.86rem] text-muted">{{ r.agenda }}</p>
              <p v-if="r.date" class="mt-1 text-[.82rem] text-muted">
                Preferred: {{ r.date }}<template v-if="r.slot"> · {{ fmtSlot(r.slot) }}</template>
              </p>
              <p v-if="r.assigned_to" class="mt-1 text-[.82rem] text-brand font-semibold">Assigned to {{ r.assigned_to }}</p>
            </div>

            <div v-if="r.status === 'requested'" class="flex items-center gap-2 shrink-0">
              <select v-model="assignPick[r.id]" class="py-[9px] px-3 rounded-[10px] border border-[#cbd5e1] text-[.85rem]">
                <option value="">Assign member…</option>
                <option v-for="m in members" :key="m.id" :value="m.id">{{ m.contact?.name || m.contact?.email }}</option>
              </select>
              <button class="btn sm" :disabled="acting[r.id] || !assignPick[r.id]" @click="respond(r, 'assign')">Assign</button>
              <button class="btn sm ghost" :disabled="acting[r.id]" @click="respond(r, 'decline')">Decline</button>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
