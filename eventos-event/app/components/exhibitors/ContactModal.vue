<script setup lang="ts">
import { toast } from 'vue-sonner'

const contact = useExhibitorContactStore()
const meetings = useMeetingsStore()
const site = useSiteStore()
const auth = useAuthStore()
const chatStore = useChatStore()

const exhibitorRole = computed(() =>
  contact.target?.type === 'sponsor' ? 'sponsor' : 'exhibitor',
)
const meetEnabled = computed(() =>
  auth.isAuthed
  && site.meetingsTabEnabled
  && meetings.canRequest
  && meetings.canMeetRole(exhibitorRole.value),
)
const chatEnabled = computed(() =>
  auth.isAuthed
  && site.chatModuleEnabled
  && chatStore.canChatRole(exhibitorRole.value),
)

const draft = ref('')
const agenda = ref('')
const place = ref('')
const pickedDate = ref('')
const pickedSlot = ref('')
const meetError = ref('')
const meetSuccess = ref(false)

const needsLocation = computed(() => contact.lounge?.location_required === true)
const placeOptions = computed<string[]>(() => contact.lounge?.locations ?? [])

const quick = [
  'Hello! How can I help you?',
  'Thank you for reaching out!',
  'Let me check that for you.',
]

const defaultNote = computed(() =>
  contact.target?.name
    ? `Hello ${contact.target.name}, I would like to connect with you.`
    : 'Hello, I would like to connect with you.',
)

const roleLine = computed(() => {
  const t = contact.target
  if (!t) return ''
  const kind = t.type === 'sponsor' ? 'Sponsor' : 'Exhibitor'
  if (t.booth) return `Booth ${t.booth} · ${kind}`
  if (t.category) return `${t.category} · ${kind}`
  return kind
})

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
function fmtSlot(slot: string) {
  return slot.replace('-', ' – ')
}
function statusLabel(s: string) {
  return {
    requested: 'Awaiting the exhibitor',
    assigned: 'Member assigned',
    confirmed: 'Confirmed',
    declined: 'Declined',
    canceled: 'Canceled',
  }[s] || s
}

const pastDue = computed(() => !!pickedDate.value && isPastDate(pickedDate.value))
const slotsForDay = computed(() => contact.lounge?.slots?.[pickedDate.value] ?? [])
function busy(date: string, slot: string) {
  return !!contact.lounge?.busy?.some(b => b.date === date && b.slot === slot)
}

const canSendMeeting = computed(() => {
  if (contact.requesting || pastDue.value || meetSuccess.value) return false
  if (contact.lounge?.enabled && contact.lounge.dates.length && !pickedSlot.value) return false
  if (needsLocation.value && !place.value.trim()) return false
  return true
})

watch(() => contact.target?.id, (id) => {
  draft.value = ''
  place.value = ''
  pickedSlot.value = ''
  pickedDate.value = contact.lounge?.dates?.[0] ?? ''
  meetError.value = ''
  meetSuccess.value = false
  agenda.value = id && contact.target?.name
    ? `Hello ${contact.target.name}, I would like to connect with you.`
    : ''
  if (id) {
    meetings.fetchCapabilities({ force: true })
    chatStore.fetchCapabilities()
    if (!chatEnabled.value && contact.tab === 'chat' && meetEnabled.value) contact.tab = 'meet'
    else if (!meetEnabled.value && contact.tab === 'meet' && chatEnabled.value) contact.tab = 'chat'
    else if (!chatEnabled.value && !meetEnabled.value) contact.close()
  }
})

watch(() => contact.lounge, (l) => {
  if (l && !pickedDate.value) pickedDate.value = l.dates?.[0] ?? ''
  if (l?.location_required && !place.value && l.locations?.length === 1) {
    place.value = l.locations[0] ?? ''
  }
})

async function send() {
  if (await contact.sendMessage(draft.value)) {
    draft.value = ''
    toast.success('Message sent to the exhibitor.')
  }
}

async function sendMeeting() {
  if (!canSendMeeting.value) return
  meetError.value = ''

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

  const ok = await contact.requestMeeting({
    agenda: agenda.value,
    location: needsLocation.value ? place.value.trim() : undefined,
    date: pickedDate.value || undefined,
    slot: pickedSlot.value || undefined,
  })
  if (ok) {
    meetSuccess.value = true
    toast.success('Meeting request sent.')
  }
  else if (contact.error) {
    meetError.value = contact.error
  }
}
</script>

<template>
  <div class="overlay" @click.self="contact.close()">
    <div class="modal-wrap">
      <button class="x" type="button" aria-label="Close" @click="contact.close()">
        <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
      </button>

      <div class="modal" role="dialog" aria-modal="true" :aria-label="contact.tab === 'chat' ? 'Start chat' : 'Schedule a meeting'">
        <div v-if="chatEnabled || meetEnabled" class="tabs">
          <button
            v-if="chatEnabled"
            type="button"
            class="tab"
            :class="{ on: contact.tab === 'chat' }"
            @click="contact.tab = 'chat'"
          >
            Chat
          </button>
          <button
            v-if="meetEnabled"
            type="button"
            class="tab"
            :class="{ on: contact.tab === 'meet' }"
            @click="contact.tab = 'meet'"
          >
            Meet
          </button>
        </div>

        <div class="who-block">
          <p class="kicker">{{ contact.tab === 'chat' ? 'Start chat with' : 'Schedule a meeting with' }}</p>
          <div class="who">
            <span class="av">
              <AppImage v-if="contact.target?.logo_url" :src="contact.target.logo_url" :alt="contact.target?.name || ''" />
              <span v-else class="av-fallback">{{ (contact.target?.name || '?').slice(0, 1) }}</span>
            </span>
            <div class="who-txt">
              <h2>{{ contact.target?.name }}</h2>
              <p v-if="roleLine" class="sub">{{ roleLine }}</p>
            </div>
          </div>
        </div>

        <!-- ── Chat ── -->
        <section v-if="contact.tab === 'chat' && chatEnabled" class="pane">
          <div v-if="contact.threadLoading" class="hint">Loading…</div>
          <div v-else-if="contact.messages.length" class="thread">
            <div
              v-for="m in contact.messages.filter(msg => (msg.body || '').trim())"
              :key="m.id"
              class="bubble"
              :class="{ mine: m.mine }"
            >
              {{ m.body }}
            </div>
          </div>

          <div class="ta-wrap">
            <textarea v-model="draft" maxlength="1000" rows="5" placeholder="Type your message..." />
            <span class="count">{{ 1000 - draft.length }}</span>
          </div>
          <div class="quick">
            <button v-for="q in quick" :key="q" type="button" class="chip" @click="draft = q">{{ q }}</button>
          </div>

          <div class="foot">
            <button class="btn" type="button" :disabled="contact.sending || !draft.trim()" @click="send">
              {{ contact.sending ? 'Sending…' : 'Send message' }}
            </button>
          </div>
        </section>

        <!-- ── Meet ── -->
        <section v-else-if="meetEnabled" class="pane">
          <div class="meet-scroll">
            <div v-if="contact.lounge?.enabled && contact.lounge.dates.length" class="slots">
              <div class="days">
                <button
                  v-for="d in contact.lounge.dates"
                  :key="d"
                  type="button"
                  class="day"
                  :class="{ on: pickedDate === d, past: isPastDate(d) }"
                  @click="pickedDate = d; pickedSlot = ''; meetSuccess = false; meetError = ''"
                >
                  {{ fmtDay(d) }}
                </button>
              </div>

              <label class="lbl">Time <span v-if="contact.lounge.timezone">({{ contact.lounge.timezone }})</span></label>
              <div v-if="slotsForDay.length" class="select-wrap">
                <select v-model="pickedSlot" class="select" @change="meetSuccess = false; meetError = ''">
                  <option value="">Select Time Slot</option>
                  <option v-for="s in slotsForDay" :key="s" :value="s" :disabled="busy(pickedDate, s)">
                    {{ fmtSlot(s) }}{{ busy(pickedDate, s) ? ' (Booked)' : '' }}
                  </option>
                </select>
                <svg class="chev" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6" /></svg>
              </div>
              <p v-else class="hint-sm">No slots for this day — send your request and propose a time in the note.</p>
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
                :placeholder="placeOptions.length ? 'Or type another place…' : 'e.g. Hall 4, Booth B12'"
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

            <div v-if="contact.requests.length" class="reqs">
              <label class="lbl">Your requests</label>
              <div v-for="r in contact.requests" :key="r.id" class="req">
                <div class="req-main">
                  <strong>{{ r.subject || 'Meeting request' }}</strong>
                  <span v-if="r.date" class="req-when">{{ r.date }}<template v-if="r.slot"> · {{ fmtSlot(r.slot) }}</template></span>
                  <span v-if="r.location" class="req-where">{{ r.location }}</span>
                </div>
                <span class="pill" :class="r.status">{{ statusLabel(r.status) }}<template v-if="r.assigned_to"> · {{ r.assigned_to }}</template></span>
              </div>
            </div>
          </div>

          <div class="foot">
            <button class="btn" type="button" :disabled="!canSendMeeting" @click="sendMeeting">
              {{ contact.requesting ? 'Sending…' : 'Send Meeting Request' }}
            </button>
          </div>
        </section>

        <p v-if="contact.error && contact.tab === 'chat'" class="err">{{ contact.error }}</p>
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
  z-index: 80;
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
.av :deep(img) {
  width: 100%;
  height: 100%;
  object-fit: contain;
  background: #fff;
}
.av-fallback { text-transform: uppercase; }

.who-txt { min-width: 0; }
.who-txt h2 { margin: 0; font-size: 1.05rem; font-weight: 800; color: #1e293b; }
.sub { margin: 2px 0 0; color: #94a3b8; font-size: .88rem; }

.pane {
  display: flex;
  flex-direction: column;
  min-height: 0;
  padding: 10px 24px 22px;
  gap: 12px;
  overflow: auto;
}

.thread {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 160px;
  overflow-y: auto;
  padding: 4px 0 8px;
  flex: none;
}

.bubble {
  align-self: flex-start;
  max-width: 78%;
  padding: 9px 13px;
  border-radius: 14px;
  background: #f1f5f9;
  color: #334155;
  font-size: .9rem;
  line-height: 1.45;
  border-top-left-radius: 4px;
}
.bubble.mine {
  align-self: flex-end;
  background: var(--brand-primary);
  color: #fff;
  border-top-left-radius: 14px;
  border-top-right-radius: 4px;
}

.hint {
  color: #94a3b8;
  font-size: .88rem;
  text-align: center;
  padding: 12px 8px;
}
.hint-sm {
  margin: 6px 0 0;
  color: #94a3b8;
  font-size: .82rem;
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
.day.on { background: var(--brand-primary); color: #fff; }
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

.in {
  width: 100%;
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
.in:focus { border-color: var(--brand-primary); }

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

.reqs { margin-top: 14px; display: flex; flex-direction: column; gap: 8px; }
.req {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  border: 1px solid #eef0f3;
  border-radius: 10px;
  padding: 10px 12px;
}
.req-main { display: flex; flex-direction: column; min-width: 0; }
.req-main strong { font-size: .86rem; color: #1e293b; }
.req-when, .req-where { font-size: .78rem; color: #64748b; }
.pill {
  font-size: .72rem;
  font-weight: 700;
  padding: 4px 9px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #475569;
  white-space: nowrap;
}
.pill.confirmed { background: #dcfce7; color: #15803d; }
.pill.assigned { background: color-mix(in srgb, var(--brand-primary) 14%, #fff); color: var(--brand-primary); }
.pill.declined, .pill.canceled { background: #fee2e2; color: #b91c1c; }

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

.err {
  margin: 0 24px 16px;
  color: #dc2626;
  font-size: .84rem;
}

@media (max-width: 560px) {
  .x { top: 10px; right: 10px; }
  .modal-wrap { max-width: 100%; }
}
</style>
