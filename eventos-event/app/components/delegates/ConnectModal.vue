<script setup lang="ts">
import { toast } from 'vue-sonner'

const store = useDelegatesStore()
const meetings = useMeetingsStore()
const site = useSiteStore()

const target = computed(() => store.connectTarget)
const status = computed(() => (target.value ? store.connected[target.value.id] : undefined))

const message = ref('')
const agenda = ref('')
const place = ref('')
const pickedDate = ref('')
const pickedSlot = ref('')
const sending = ref(false)
const meetSuccess = ref(false)
const meetError = ref('')

const quick = [
  'Hello! How can I help you?',
  'Thank you for reaching out!',
  'Let me check that for you.',
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

const needsLocation = computed(() => lounge.value?.location_required === true)
const placeOptions = computed<string[]>(() => lounge.value?.locations ?? [])
const defaultNote = computed(() =>
  target.value?.name
    ? `Hello ${target.value.name}, I would like to connect with you.`
    : 'Hello, I would like to connect with you.',
)

const roleLine = computed(() => {
  const t = target.value
  if (!t) return ''
  if (t.job_title && t.company) return `${t.job_title} at ${t.company}`
  return t.job_title || t.company || ''
})

watch(target, (t) => {
  message.value = ''
  agenda.value = ''
  place.value = ''
  pickedSlot.value = ''
  pickedDate.value = ''
  lounge.value = null
  meetSuccess.value = false
  meetError.value = ''
  if (t) {
    agenda.value = `Hello ${t.name || 'there'}, I would like to connect with you.`
    loadLounge()
  }
}, { immediate: true })

async function loadLounge() {
  const uuid = site.event?.uuid
  if (!uuid || !target.value) return
  try {
    const api = useApi()
    const res = await api<{ data: Lounge }>(`/events/${uuid}/lounge`, { query: { with: target.value.id } })
    lounge.value = res.data
    pickedDate.value = res.data.dates?.[0] ?? ''
    if (res.data.location_required && res.data.locations?.length === 1) {
      place.value = res.data.locations[0] ?? ''
    }
  }
  catch { /* lounge optional */ }
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
function fmtDay(iso: string): string {
  const [y, m, dd] = iso.split('-').map(Number)
  const d = new Date(y ?? 1970, (m ?? 1) - 1, dd ?? 1)
  const day = d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' })
  const weekday = d.toLocaleDateString(undefined, { weekday: 'long' })
  return `${day} ${weekday}`
}

const pastDue = computed(() => !!pickedDate.value && isPastDate(pickedDate.value))
const canSendMeeting = computed(() => {
  if (sending.value || pastDue.value || meetSuccess.value) return false
  if (lounge.value?.enabled && lounge.value.dates.length && !pickedSlot.value) return false
  if (needsLocation.value && !place.value.trim()) return false
  return true
})

async function sendConnect() {
  if (!target.value || sending.value) return
  sending.value = true
  try {
    const ok = await store.connect(target.value, message.value)
    if (ok) {
      message.value = ''
      toast.success('Connection request sent!')
    }
  }
  finally { sending.value = false }
}

async function sendMeeting() {
  if (!target.value || !canSendMeeting.value) return

  if (pastDue.value) {
    meetError.value = 'You can\'t send meeting requests for past due.'
    return
  }

  if (needsLocation.value && !place.value.trim()) {
    meetError.value = placeOptions.value.length
      ? 'Choose where you want to meet.'
      : 'Enter where you want to meet, e.g. Hall 4.'
    return
  }

  sending.value = true
  meetError.value = ''
  try {
    const ok = await meetings.request({
      to: target.value.id,
      agenda: agenda.value || undefined,
      location: needsLocation.value ? place.value.trim() : undefined,
      date: pickedDate.value || undefined,
      slot: pickedSlot.value || undefined,
    })
    if (ok) {
      meetSuccess.value = true
      toast.success('Meeting request sent!')
    }
    else {
      meetError.value = meetings.lastError || 'Could not send the meeting request.'
    }
  }
  finally { sending.value = false }
}
</script>

<template>
  <div v-if="target" class="overlay" @click.self="store.closeConnect()">
    <div class="modal-wrap">
      <button class="x" type="button" aria-label="Close" @click="store.closeConnect()">
        <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
      </button>

      <div class="modal" role="dialog" aria-modal="true" :aria-label="store.connectTab === 'connect' ? 'Start chat' : 'Schedule a meeting'">
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
              <p v-if="roleLine" class="sub">{{ roleLine }}</p>
            </div>
          </div>
        </div>

        <!-- ── Chat ── -->
        <section v-if="store.connectTab === 'connect'" class="pane">
          <div v-if="status === 'pending'" class="banner ok">
            <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
            Connection request sent.
          </div>

          <template v-else>
            <div class="ta-wrap">
              <textarea v-model="message" maxlength="1000" rows="5" placeholder="Type your message..." />
              <span class="count">{{ 1000 - message.length }}</span>
            </div>
            <div class="quick">
              <button v-for="q in quick" :key="q" type="button" class="chip" @click="message = q">{{ q }}</button>
            </div>
          </template>

          <div class="foot">
            <button
              v-if="status !== 'pending'"
              class="btn"
              type="button"
              :disabled="sending"
              @click="sendConnect"
            >
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
                  @click="pickedDate = d; pickedSlot = ''; meetSuccess = false; meetError = ''"
                >
                  {{ fmtDay(d) }}
                </button>
              </div>

              <label class="lbl">Time <span v-if="lounge.timezone">({{ lounge.timezone }})</span></label>
              <div v-if="slotsForDay.length" class="select-wrap">
                <select v-model="pickedSlot" class="select" @change="meetSuccess = false; meetError = ''">
                  <option value="">Select Time Slot</option>
                  <option v-for="s in slotsForDay" :key="s" :value="s" :disabled="busy(pickedDate, s)">
                    {{ fmtSlot(s) }}{{ busy(pickedDate, s) ? ' (Booked)' : '' }}
                  </option>
                </select>
                <svg class="chev" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6" /></svg>
              </div>
              <p v-else class="hint">No slots for this day — send your request and propose a time in the note.</p>
            </div>

            <label class="lbl">Notes</label>
            <textarea v-model="agenda" class="notes" rows="3" maxlength="1000" :placeholder="defaultNote" />

            <template v-if="needsLocation">
              <label class="lbl">Meeting location</label>
              <div v-if="placeOptions.length" class="places">
                <button
                  v-for="p in placeOptions"
                  :key="p"
                  type="button"
                  class="place"
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

            <div v-if="pastDue" class="banner bad">
              <svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" /></svg>
              You can't send meeting requests for past due.
            </div>
            <div v-else-if="meetError" class="banner bad">
              <svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" /></svg>
              {{ meetError }}
            </div>
            <div v-else-if="meetSuccess" class="banner ok">
              <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" /><path d="M8 12l3 3 5-6" /></svg>
              Meeting request sent successfully.
            </div>
          </div>

          <div class="foot">
            <button class="btn" type="button" :disabled="!canSendMeeting" @click="sendMeeting">
              {{ sending ? 'Sending…' : 'Send Meeting Request' }}
            </button>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, .5);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px 16px;
  z-index: 70;
}

.modal-wrap {
  position: relative;
  width: 100%;
  max-width: 520px;
}

.modal {
  background: #fff;
  border-radius: 16px;
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 20px 50px rgba(15, 23, 42, .28);
}

.x {
  position: absolute;
  top: -14px;
  right: -14px;
  z-index: 3;
  border: none;
  background: var(--brand-primary);
  width: 40px;
  height: 40px;
  border-radius: 10px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 6px 16px color-mix(in srgb, var(--brand-primary) 45%, transparent);
}

.x:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
.x svg { width: 16px; height: 16px; fill: none; stroke: #fff; stroke-width: 2.4; stroke-linecap: round; }

.tabs {
  display: flex;
  background: #f4f5f7;
  border-bottom: 1px solid #eef0f3;
}

.tab {
  flex: 1;
  border: none;
  background: transparent;
  padding: 16px;
  font: inherit;
  font-size: .95rem;
  font-weight: 600;
  color: #94a3b8;
  cursor: pointer;
  border-bottom: 3px solid transparent;
  margin-bottom: -1px;
}

.tab.on {
  color: #1e293b;
  font-weight: 800;
  background: #fff;
  border-bottom-color: var(--brand-primary);
}

.who-block { padding: 22px 24px 8px; }
.kicker {
  margin: 0 0 14px;
  font-size: 1.05rem;
  font-weight: 800;
  color: #1e293b;
}

.who { display: flex; align-items: center; gap: 12px; }
.av {
  width: 52px;
  height: 52px;
  border-radius: 10px;
  background: color-mix(in srgb, var(--brand-primary) 12%, #fff);
  color: var(--brand-primary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 1rem;
  overflow: hidden;
  flex: 0 0 auto;
}
.av :deep(.ua),
.av :deep(img) {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 10px;
}

.who-txt { min-width: 0; }
.who-txt h2 { margin: 0; font-size: 1.05rem; font-weight: 800; color: #1e293b; }
.sub { margin: 2px 0 0; color: #94a3b8; font-size: .88rem; }

.pane {
  display: flex;
  flex-direction: column;
  min-height: 0;
  padding: 10px 24px 22px;
  gap: 12px;
}

.lbl {
  display: block;
  font-size: .84rem;
  font-weight: 600;
  color: #64748b;
  margin: 10px 0 8px;
}

.lbl span { font-weight: 500; }

.ta-wrap {
  position: relative;
  flex: none;
  width: 100%;
}
.ta-wrap textarea {
  display: block;
  width: 100%;
  min-height: 7.5rem;
  height: 7.5rem;
  padding: 12px 14px 28px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  font: inherit;
  font-size: .92rem;
  line-height: 1.45;
  resize: vertical;
  outline: none;
  color: #334155;
  background: #fff;
  box-sizing: border-box;
  overflow-y: auto;
}
.ta-wrap textarea:focus { border-color: var(--brand-primary); }
.ta-wrap .count {
  position: absolute;
  right: 14px;
  bottom: 10px;
  color: #94a3b8;
  font-size: .8rem;
  pointer-events: none;
}

textarea,
.in {
  width: 100%;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px 14px;
  font: inherit;
  font-size: .92rem;
  resize: vertical;
  outline: none;
  color: #334155;
  background: #fff;
  box-sizing: border-box;
}

textarea:focus,
.in:focus { border-color: var(--brand-primary); }

/* Keep Notes at a stable 3-line height — flex parents were shrinking it
   down to a single clipped line when Meeting location filled the pane. */
.notes {
  width: 100%;
  min-height: 5.75rem;
  height: 5.75rem;
  flex: none;
  line-height: 1.45;
  resize: vertical;
  overflow-y: auto;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px 14px;
  font: inherit;
  font-size: .92rem;
  outline: none;
  color: #334155;
  background: #fff;
  box-sizing: border-box;
}
.notes:focus { border-color: var(--brand-primary); }

.quick { display: flex; flex-wrap: wrap; gap: 8px; }
.chip {
  border: none;
  background: #f1f5f9;
  color: #475569;
  border-radius: 999px;
  padding: 8px 14px;
  font: inherit;
  font-size: .8rem;
  cursor: pointer;
}
.chip:hover { background: #e8edf3; color: var(--brand-primary); }

.meet-scroll {
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  max-height: 48vh;
  min-height: 0;
}

.days {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 4px;
  margin-top: 4px;
}

.day {
  flex: 0 0 auto;
  border: none;
  background: transparent;
  border-radius: 10px;
  padding: 10px 14px;
  font: inherit;
  font-size: .88rem;
  font-weight: 600;
  color: #64748b;
  cursor: pointer;
  white-space: nowrap;
}

.day:hover:not(.on) { color: #334155; background: #f8fafc; }
.day.on {
  background: var(--brand-primary);
  color: #fff;
}
.day.past { opacity: .4; }

.select-wrap { position: relative; }
.select {
  width: 100%;
  appearance: none;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px 40px 12px 14px;
  font: inherit;
  font-size: .9rem;
  color: #334155;
  background: #fff;
  cursor: pointer;
  outline: none;
}
.select:focus { border-color: var(--brand-primary); }
.chev {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  width: 16px;
  height: 16px;
  fill: none;
  stroke: #94a3b8;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  pointer-events: none;
}

.hint { margin: 6px 0 0; color: #94a3b8; font-size: .82rem; }

.places { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; }
.place {
  border: 1px solid #e2e8f0;
  background: #fff;
  border-radius: 10px;
  padding: 8px 12px;
  font: inherit;
  font-size: .82rem;
  font-weight: 600;
  color: #475569;
  cursor: pointer;
}
.place.on {
  border-color: var(--brand-primary);
  background: var(--brand-primary);
  color: #fff;
}

.banner {
  display: flex;
  align-items: center;
  gap: 10px;
  border-radius: 10px;
  padding: 12px 14px;
  font-size: .86rem;
  font-weight: 600;
  margin-top: 10px;
}
.banner svg {
  flex: 0 0 auto;
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.banner.ok {
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  color: #15803d;
}
.banner.bad {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
}

.foot {
  display: flex;
  justify-content: flex-end;
  margin-top: 4px;
  padding-top: 16px;
  border-top: 1px solid #eef0f3;
}

.btn {
  border: none;
  border-radius: 10px;
  padding: 12px 22px;
  background: var(--brand-primary);
  color: #fff;
  font: inherit;
  font-size: .92rem;
  font-weight: 700;
  cursor: pointer;
}
.btn:hover:not(:disabled) { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
.btn:disabled {
  background: #e2e8f0;
  color: #94a3b8;
  cursor: default;
}

@media (max-width: 560px) {
  .x { top: 10px; right: 10px; }
  .modal-wrap { max-width: 100%; }
}
</style>
