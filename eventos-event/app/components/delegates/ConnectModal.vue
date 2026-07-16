<script setup lang="ts">
import { toast } from 'vue-sonner'

const store = useDelegatesStore()
const meetings = useMeetingsStore()
const chat = useChatStore()
const site = useSiteStore()

const target = computed(() => store.connectTarget)
const status = computed(() => (target.value ? store.connected[target.value.id] : undefined))

const message = ref('')
const subject = ref('')
const agenda = ref('')
const place = ref('')
const pickedDate = ref('')
const pickedSlot = ref('')
const sending = ref(false)

const quick = [
  'Hi, I’d like to connect.',
  'Good to meet you at the event.',
  'Keen to see if we can work together.',
]

interface Lounge {
  enabled: boolean
  timezone: string
  dates: string[]
  slots: Record<string, string[]>
  busy: Array<{ date: string, slot: string }>
  location_required: boolean
  locations: string[]
}
const lounge = ref<Lounge | null>(null)

// A venue/hybrid event meets somewhere physical, so the request must say where.
const needsLocation = computed(() => lounge.value?.location_required === true)
const placeOptions = computed<string[]>(() => lounge.value?.locations ?? [])

watch(target, (t) => {
  message.value = ''; subject.value = ''; agenda.value = ''
  place.value = ''
  pickedSlot.value = ''; pickedDate.value = ''
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
    // One place on offer is not a choice — pre-select it.
    if (res.data.location_required && res.data.locations?.length === 1) {
      place.value = res.data.locations[0] ?? ''
    }
  } catch { /* lounge optional */ }
}

const slotsForDay = computed(() => lounge.value?.slots?.[pickedDate.value] ?? [])
function busy(date: string, slot: string) {
  return !!lounge.value?.busy?.some(b => b.date === date && b.slot === slot)
}
function fmtSlot(s: string) { return s ? s.replace('-', ' – ') : '' }

function pad(n: number): string { return String(n).padStart(2, '0') }
function todayIso(): string {
  const d = new Date()
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}
function isPastDate(iso: string): boolean { return iso < todayIso() }
function fmtDay(iso: string): { top: string, weekday: string } {
  const [y, m, dd] = iso.split('-').map(Number)
  const d = new Date(y ?? 1970, (m ?? 1) - 1, dd ?? 1)
  return {
    top: d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' }),
    weekday: d.toLocaleDateString(undefined, { weekday: 'long' }),
  }
}
// The picked day may already be behind us on a multi-day event that's underway.
const pastDue = computed(() => !!pickedDate.value && isPastDate(pickedDate.value))

async function sendConnect() {
  if (!target.value || sending.value) return
  sending.value = true
  try {
    const ok = await store.connect(target.value, message.value)
    if (ok) { message.value = ''; toast.success('Connection request sent!') }
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

  if (pastDue.value) {
    toast.error('You can’t send meeting requests for a past due date.')
    return
  }

  if (needsLocation.value && !place.value.trim()) {
    toast.error(placeOptions.value.length ? 'Choose where you want to meet.' : 'Enter where you want to meet, e.g. Hall 4.')
    return
  }

  sending.value = true
  try {
    const ok = await meetings.request({
      to: target.value.id,
      title: subject.value || undefined,
      agenda: agenda.value || undefined,
      location: needsLocation.value ? place.value.trim() : undefined,
      date: pickedDate.value || undefined,
      slot: pickedSlot.value || undefined,
    })
    if (ok) { subject.value = ''; agenda.value = ''; pickedSlot.value = ''; toast.success('Meeting request sent!') }
    else toast.error(meetings.lastError || 'Could not send the meeting request.')
  } finally { sending.value = false }
}
</script>

<template>
  <div v-if="target" class="overlay" @click.self="store.closeConnect()">
    <div class="modal" role="dialog" aria-modal="true">
      <button class="x" type="button" aria-label="Close" @click="store.closeConnect()">
        <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
      </button>

      <div class="tabs">
        <button type="button" class="tab" :class="{ on: store.connectTab === 'connect' }" @click="store.connectTab = 'connect'">Chat</button>
        <button type="button" class="tab" :class="{ on: store.connectTab === 'meet' }" @click="store.connectTab = 'meet'">Meet</button>
      </div>

      <div class="who-block">
        <p class="kicker">{{ store.connectTab === 'connect' ? 'Start chat with' : 'Schedule a meeting with' }}</p>
        <div class="who">
          <span class="av">
            <UserAvatar :src="target.avatar_url" :name="target.name" />
          </span>
          <div class="who-txt">
            <h2>{{ target.name }}</h2>
            <p v-if="target.job_title || target.company" class="sub">{{ [target.job_title, target.company].filter(Boolean).join(' · ') }}</p>
          </div>
        </div>
      </div>

      <!-- ── Connect ── -->
      <section v-if="store.connectTab === 'connect'" class="pane">
        <div v-if="status === 'pending'" class="sent">
          <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
          Connection request sent.
        </div>

        <template v-else>
          <div class="ta-wrap">
            <textarea v-model="message" maxlength="500" rows="4" placeholder="Type your message…" />
            <span class="count">{{ 500 - message.length }}</span>
          </div>
          <div class="quick">
            <button v-for="q in quick" :key="q" type="button" class="chip" @click="message = q">{{ q }}</button>
          </div>
        </template>

        <div class="foot">
          <button class="btn ghost" type="button" @click="openChat">
            <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" /></svg>
            Message
          </button>
          <button v-if="status !== 'pending'" class="btn" type="button" :disabled="sending" @click="sendConnect">
            {{ sending ? 'Sending…' : 'Send connection request' }}
          </button>
        </div>
      </section>

      <!-- ── Meet ── -->
      <section v-else class="pane">
        <div class="meet-scroll">
          <div v-if="lounge?.enabled && lounge.dates.length" class="slots">
            <div class="days">
              <button
                v-for="d in lounge.dates"
                :key="d"
                type="button"
                class="day"
                :class="{ on: pickedDate === d, past: isPastDate(d) }"
                @click="pickedDate = d; pickedSlot = ''"
              >
                <strong>{{ fmtDay(d).top }}</strong>
                <span>{{ fmtDay(d).weekday }}</span>
              </button>
            </div>

            <label class="lbl">Time <span v-if="lounge.timezone" class="opt">({{ lounge.timezone }})</span></label>
            <div v-if="slotsForDay.length" class="select-wrap">
              <select v-model="pickedSlot" class="select">
                <option value="">Select a time</option>
                <option v-for="s in slotsForDay" :key="s" :value="s" :disabled="busy(pickedDate, s)">
                  {{ fmtSlot(s) }}{{ busy(pickedDate, s) ? ' (Booked)' : '' }}
                </option>
              </select>
              <svg class="chev" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6" /></svg>
            </div>
            <p v-else class="hint">No slots for this day — send your request and propose a time in the note.</p>
          </div>

          <label class="lbl">Subject <span class="opt">(optional)</span></label>
          <input v-model="subject" class="in" placeholder="e.g. Coffee & intro" maxlength="200">

          <label class="lbl">Notes</label>
          <textarea v-model="agenda" class="in" rows="3" maxlength="1000" :placeholder="`Hello ${target.name}, I would like to connect with you.`" />

          <!-- Meeting location — in-person / hybrid events only. The organizer's
               places are quick-fills; you can always type somewhere else. -->
          <template v-if="needsLocation">
            <label class="lbl">Meeting location</label>
            <div v-if="placeOptions.length" class="days places">
              <button
                v-for="p in placeOptions"
                :key="p"
                type="button"
                class="day"
                :class="{ on: place === p }"
                @click="place = place === p ? '' : p"
              >{{ p }}</button>
            </div>
            <input
              v-model="place"
              class="in"
              maxlength="180"
              :placeholder="placeOptions.length ? 'Or type another place…' : 'e.g. Hall 4, Meeting Room 2'"
            >
          </template>

          <div v-if="pastDue" class="warn">
            <svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" /></svg>
            You can’t send meeting requests for past due.
          </div>
        </div>

        <div class="foot end">
          <button class="btn" type="button" :disabled="sending || pastDue" @click="sendMeeting">
            {{ sending ? 'Sending…' : 'Send Meeting Request' }}
          </button>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(15,23,42,.5); display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 70; }
.modal { position: relative; background: #fff; border-radius: 18px; width: 100%; max-width: 480px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 50px rgba(15,23,42,.28); }

.x { position: absolute; top: 14px; right: 14px; z-index: 3; border: none; background: var(--brand-primary); width: 34px; height: 34px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 6px 16px color-mix(in srgb, var(--brand-primary) 45%, transparent); }
.x svg { width: 16px; height: 16px; fill: none; stroke: #fff; stroke-width: 2.4; stroke-linecap: round; }

.tabs { display: flex; background: #f4f5f7; border-bottom: 1px solid #eef0f3; }
.tab { flex: 1; border: none; background: none; padding: 15px; font: inherit; font-size: .92rem; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 3px solid transparent; }
.tab.on { color: var(--brand-primary); border-bottom-color: var(--brand-primary); background: #fff; }

.who-block { padding: 18px 22px 6px; }
.kicker { margin: 0 0 12px; font-size: .82rem; font-weight: 700; color: #334155; }
.who { display: flex; align-items: center; gap: 12px; }
.av { width: 48px; height: 48px; border-radius: 50%; background: var(--brand-primary); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1rem; overflow: hidden; flex: 0 0 auto; }
.av img { width: 100%; height: 100%; object-fit: cover; }
.who-txt { min-width: 0; }
.who-txt h2 { margin: 0; font-size: 1.02rem; font-weight: 800; color: #1e293b; }
.sub { margin: 2px 0 0; color: #64748b; font-size: .82rem; }

.pane { display: flex; flex-direction: column; min-height: 0; padding: 14px 22px 20px; gap: 10px; }
.lbl { font-size: .82rem; font-weight: 700; color: #334155; margin-top: 8px; }
.opt { color: #94a3b8; font-weight: 500; }

.ta-wrap { position: relative; }
.ta-wrap textarea { padding-bottom: 30px; }
.ta-wrap .count { position: absolute; right: 12px; bottom: 10px; color: #94a3b8; font-size: .78rem; }

textarea, .in { width: 100%; border: 1px solid #e2e8f0; border-radius: 12px; padding: 11px 14px; font: inherit; font-size: .92rem; resize: vertical; outline: none; color: #334155; }
textarea:focus, .in:focus { border-color: var(--brand-primary); }
.quick { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
.chip { border: 1px solid #e2e8f0; background: #f8fafc; color: #475569; border-radius: 999px; padding: 7px 12px; font: inherit; font-size: .8rem; cursor: pointer; }
.chip:hover { border-color: color-mix(in srgb, var(--brand-primary) 40%, #fff); color: var(--brand-primary); }

.sent { display: flex; align-items: center; gap: 10px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #15803d; border-radius: 12px; padding: 14px; font-size: .9rem; font-weight: 600; }
.sent svg { flex: 0 0 auto; width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 2.4; stroke-linecap: round; stroke-linejoin: round; }

.meet-scroll { display: flex; flex-direction: column; gap: 6px; overflow-y: auto; max-height: 46vh; }
.days { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
.day { display: flex; flex-direction: column; align-items: center; gap: 2px; border: 1px solid #e2e8f0; background: #fff; border-radius: 12px; padding: 9px 14px; font: inherit; font-size: .8rem; color: #475569; cursor: pointer; min-width: 68px; }
.day strong { font-size: .86rem; font-weight: 800; }
.day span { font-size: .72rem; color: inherit; opacity: .8; }
.day.on { border-color: var(--brand-primary); background: var(--brand-primary); color: #fff; }
.day.past { opacity: .45; }
.places .day { flex-direction: row; min-width: 0; border-radius: 10px; padding: 8px 12px; }

.select-wrap { position: relative; margin-top: 6px; }
.select { width: 100%; appearance: none; border: 1px solid #e2e8f0; border-radius: 12px; padding: 11px 40px 11px 14px; font: inherit; font-size: .9rem; color: #334155; background: #fff; cursor: pointer; outline: none; }
.select:focus { border-color: var(--brand-primary); }
.chev { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; fill: none; stroke: #64748b; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; pointer-events: none; }
.hint { margin: 6px 0 0; color: #94a3b8; font-size: .82rem; }

.warn { display: flex; align-items: center; gap: 10px; background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; border-radius: 12px; padding: 12px 14px; font-size: .84rem; font-weight: 600; margin-top: 8px; }
.warn svg { flex: 0 0 auto; width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

.foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 8px; padding-top: 14px; border-top: 1px solid #eef0f3; }
.foot.end { justify-content: flex-end; }
.btn { display: inline-flex; align-items: center; gap: 8px; border: none; border-radius: 999px; padding: 11px 22px; background: var(--brand-primary); color: #fff; font: inherit; font-size: .9rem; font-weight: 700; cursor: pointer; }
.btn:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
.btn:disabled { opacity: .55; cursor: default; }
.btn.ghost { background: #f1f5f9; color: #475569; }
.btn.ghost:hover { background: #e7ebf0; }
.btn svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
</style>
