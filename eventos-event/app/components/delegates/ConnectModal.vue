<script setup lang="ts">
const store = useDelegatesStore()
const meetings = useMeetingsStore()
const chat = useChatStore()
const site = useSiteStore()

const target = computed(() => store.connectTarget)
const status = computed(() => (target.value ? store.connected[target.value.id] : undefined))

const message = ref('')
const subject = ref('')
const agenda = ref('')
const pickedDate = ref('')
const pickedSlot = ref('')
const toast = ref('')
const sending = ref(false)

const quick = [
  'Hi, I’d like to connect.',
  'Good to meet you at the event.',
  'Keen to see if we can work together.',
]

interface Lounge { enabled: boolean, dates: string[], slots: Record<string, string[]>, busy: Array<{ date: string, slot: string }> }
const lounge = ref<Lounge | null>(null)

watch(target, (t) => {
  message.value = ''; subject.value = ''; agenda.value = ''
  pickedSlot.value = ''; pickedDate.value = ''; toast.value = ''
  lounge.value = null
  if (t) loadLounge()
}, { immediate: true })

async function loadLounge() {
  const uuid = site.event?.uuid
  if (!uuid || !target.value) return
  try {
    const api = useApi()
    const res = await api<{ data: Lounge }>(`/events/${uuid}/lounge`, { query: { with: target.value.id } })
    lounge.value = res.data
    pickedDate.value = res.data.dates?.[0] ?? ''
  } catch { /* lounge optional */ }
}

const slotsForDay = computed(() => lounge.value?.slots?.[pickedDate.value] ?? [])
function busy(date: string, slot: string) {
  return !!lounge.value?.busy?.some(b => b.date === date && b.slot === slot)
}
function fmtSlot(s: string) { return s ? s.replace('-', ' – ') : '' }

function initials(n?: string | null) {
  const p = (n || '?').trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?'
}

let flashTimer: ReturnType<typeof setTimeout> | undefined
function flash(m: string) { toast.value = m; clearTimeout(flashTimer); flashTimer = setTimeout(() => (toast.value = ''), 4000) }

async function sendConnect() {
  if (!target.value || sending.value) return
  sending.value = true
  try {
    const ok = await store.connect(target.value, message.value)
    if (ok) { message.value = ''; flash('Connection request sent!') }
  } finally { sending.value = false }
}

async function openChat() {
  if (!target.value) return
  store.closeConnect()
  if (!chat.drawerOpen) chat.toggleDrawer()
  await chat.openWith(target.value.id)
}

async function sendMeeting() {
  if (!target.value || sending.value) return
  sending.value = true
  try {
    const ok = await meetings.request({
      to: target.value.id,
      title: subject.value || undefined,
      agenda: agenda.value || undefined,
      date: pickedDate.value || undefined,
      slot: pickedSlot.value || undefined,
    })
    if (ok) { subject.value = ''; agenda.value = ''; pickedSlot.value = ''; flash('Meeting request sent!') }
    else flash(meetings.lastError || 'Could not send the meeting request.')
  } finally { sending.value = false }
}
</script>

<template>
  <div v-if="target" class="overlay" @click.self="store.closeConnect()">
    <div class="modal" role="dialog" aria-modal="true">
      <button class="x" type="button" aria-label="Close" @click="store.closeConnect()">
        <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
      </button>

      <header class="head">
        <span class="av">
          <img v-if="target.avatar_url" :src="target.avatar_url" :alt="target.name || ''">
          <template v-else>{{ initials(target.name) }}</template>
        </span>
        <h2>{{ target.name }}</h2>
        <p v-if="target.job_title || target.company" class="sub">{{ [target.job_title, target.company].filter(Boolean).join(' · ') }}</p>
      </header>

      <div class="tabs">
        <button type="button" class="tab" :class="{ on: store.connectTab === 'connect' }" @click="store.connectTab = 'connect'">Connect</button>
        <button type="button" class="tab" :class="{ on: store.connectTab === 'meet' }" @click="store.connectTab = 'meet'">Meet</button>
      </div>

      <p v-if="toast" class="toast">{{ toast }}</p>

      <!-- ── Connect ── -->
      <section v-if="store.connectTab === 'connect'" class="pane">
        <div v-if="status === 'pending'" class="sent">
          <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
          Connection request sent.
        </div>

        <template v-else>
          <label class="lbl">Add a note <span class="opt">(optional)</span></label>
          <textarea v-model="message" maxlength="500" rows="3" placeholder="Add a message…" />
          <div class="quick">
            <button v-for="q in quick" :key="q" type="button" class="chip" @click="message = q">{{ q }}</button>
          </div>
          <span class="count">{{ message.length }}/500</span>
        </template>

        <div class="foot">
          <button class="btn ghost" type="button" @click="openChat">
            <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" /></svg>
            Message
          </button>
          <button v-if="status !== 'pending'" class="btn" type="button" :disabled="sending" @click="sendConnect">
            <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM19 8v6M22 11h-6" /></svg>
            {{ sending ? 'Sending…' : 'Send Connection Request' }}
          </button>
        </div>
      </section>

      <!-- ── Meet ── -->
      <section v-else class="pane">
        <div class="meet-scroll">
          <div v-if="lounge?.enabled && lounge.dates.length" class="slots">
            <label class="lbl">Preferred day</label>
            <div class="days">
              <button v-for="d in lounge.dates" :key="d" type="button" class="day" :class="{ on: pickedDate === d }" @click="pickedDate = d; pickedSlot = ''">{{ d }}</button>
            </div>
            <label class="lbl">Preferred time <span class="opt">(optional)</span></label>
            <div v-if="slotsForDay.length" class="grid">
              <button
                v-for="s in slotsForDay"
                :key="s"
                type="button"
                class="slot"
                :class="{ on: pickedSlot === s, busy: busy(pickedDate, s) }"
                :disabled="busy(pickedDate, s)"
                @click="pickedSlot = pickedSlot === s ? '' : s"
              >{{ fmtSlot(s) }}</button>
            </div>
            <p v-else class="hint">No slots for this day — send your request and propose a time in the note.</p>
          </div>

          <label class="lbl">Subject <span class="opt">(optional)</span></label>
          <input v-model="subject" class="in" placeholder="e.g. Coffee & intro" maxlength="200">
          <label class="lbl">Agenda <span class="opt">(optional)</span></label>
          <textarea v-model="agenda" class="in" rows="3" maxlength="1000" placeholder="What would you like to discuss?" />
        </div>

        <div class="foot end">
          <button class="btn" type="button" :disabled="sending" @click="sendMeeting">
            <svg viewBox="0 0 24 24"><path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z" /></svg>
            {{ sending ? 'Sending…' : 'Send meeting request' }}
          </button>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(15,23,42,.5); display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 70; }
.modal { position: relative; background: #fff; border-radius: 18px; width: 100%; max-width: 480px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 50px rgba(15,23,42,.28); }

.x { position: absolute; top: 14px; right: 14px; z-index: 3; border: none; background: #ef4444; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.x svg { width: 15px; height: 15px; fill: none; stroke: #fff; stroke-width: 2.4; stroke-linecap: round; }

.head { padding: 22px 22px 16px; text-align: center; border-bottom: 1px solid #eef0f3; display: flex; flex-direction: column; align-items: center; gap: 3px; }
.av { width: 58px; height: 58px; border-radius: 50%; background: var(--brand-primary); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.15rem; overflow: hidden; margin-bottom: 6px; }
.av img { width: 100%; height: 100%; object-fit: cover; }
.head h2 { margin: 0; font-size: 1.15rem; font-weight: 800; color: #1e293b; }
.sub { margin: 0; color: #64748b; font-size: .84rem; }

.tabs { display: flex; border-bottom: 1px solid #eef0f3; }
.tab { flex: 1; border: none; background: none; padding: 13px; font: inherit; font-size: .92rem; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 2px solid transparent; }
.tab.on { color: var(--brand-primary); border-bottom-color: var(--brand-primary); }

.toast { margin: 12px 22px 0; padding: 10px 14px; background: color-mix(in srgb, var(--brand-primary) 10%, #fff); color: var(--brand-primary); border-radius: 10px; font-size: .84rem; font-weight: 600; }

.pane { display: flex; flex-direction: column; min-height: 0; padding: 16px 22px 20px; gap: 10px; }
.lbl { font-size: .82rem; font-weight: 700; color: #334155; margin-top: 8px; }
.opt { color: #94a3b8; font-weight: 500; }
textarea, .in { width: 100%; border: 1px solid #e2e8f0; border-radius: 12px; padding: 11px 14px; font: inherit; font-size: .92rem; resize: vertical; outline: none; color: #334155; }
textarea:focus, .in:focus { border-color: var(--brand-primary); }
.quick { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
.chip { border: 1px solid #e2e8f0; background: #f8fafc; color: #475569; border-radius: 999px; padding: 7px 12px; font: inherit; font-size: .8rem; cursor: pointer; }
.chip:hover { border-color: color-mix(in srgb, var(--brand-primary) 40%, #fff); color: var(--brand-primary); }
.count { align-self: flex-end; color: #94a3b8; font-size: .78rem; }

.sent { display: flex; align-items: center; gap: 10px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #15803d; border-radius: 12px; padding: 14px; font-size: .9rem; font-weight: 600; }
.sent svg { flex: 0 0 auto; width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 2.4; stroke-linecap: round; stroke-linejoin: round; }

.meet-scroll { display: flex; flex-direction: column; gap: 6px; overflow-y: auto; max-height: 46vh; }
.days { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
.day { border: 1px solid #e2e8f0; background: #fff; border-radius: 10px; padding: 8px 12px; font: inherit; font-size: .82rem; color: #475569; cursor: pointer; }
.day.on { border-color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 10%, #fff); color: var(--brand-primary); font-weight: 700; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(108px, 1fr)); gap: 8px; margin-top: 6px; }
.slot { border: 1px solid #e2e8f0; background: #fff; border-radius: 10px; padding: 8px; font: inherit; font-size: .8rem; color: #475569; cursor: pointer; }
.slot.on { border-color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 10%, #fff); color: var(--brand-primary); font-weight: 700; }
.slot.busy { opacity: .4; cursor: not-allowed; text-decoration: line-through; }
.hint { margin: 6px 0 0; color: #94a3b8; font-size: .82rem; }

.foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 8px; padding-top: 14px; border-top: 1px solid #eef0f3; }
.foot.end { justify-content: flex-end; }
.btn { display: inline-flex; align-items: center; gap: 8px; border: none; border-radius: 999px; padding: 11px 22px; background: var(--brand-primary); color: #fff; font: inherit; font-size: .9rem; font-weight: 700; cursor: pointer; }
.btn:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
.btn:disabled { opacity: .55; cursor: default; }
.btn.ghost { background: #f1f5f9; color: #475569; }
.btn.ghost:hover { background: #e7ebf0; }
.btn svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
</style>
