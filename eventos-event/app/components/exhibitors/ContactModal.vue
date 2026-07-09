<script setup lang="ts">
const contact = useExhibitorContactStore()

const draft = ref('')
const subject = ref('')
const agenda = ref('')
const pickedDate = ref('')
const pickedSlot = ref('')
const sentToast = ref('')

const quickReplies = [
  'Hi! I\'d love to learn more about what you do.',
  'Could you share a brochure or product details?',
  'Can we schedule a quick demo?',
]

// Reset the composer whenever a new exhibitor is opened.
watch(() => contact.target?.id, () => {
  draft.value = ''
  subject.value = ''
  agenda.value = ''
  pickedDate.value = contact.lounge?.dates?.[0] ?? ''
  pickedSlot.value = ''
  sentToast.value = ''
})
watch(() => contact.lounge, (l) => {
  if (l && !pickedDate.value) pickedDate.value = l.dates?.[0] ?? ''
})

const slotsForDay = computed(() => contact.lounge?.slots?.[pickedDate.value] ?? [])
function busy(date: string, slot: string) {
  return !!contact.lounge?.busy?.some(b => b.date === date && b.slot === slot)
}

async function send() {
  if (await contact.sendMessage(draft.value)) {
    draft.value = ''
    flash('Message sent to the exhibitor.')
  }
}

async function sendMeeting() {
  const ok = await contact.requestMeeting({
    subject: subject.value,
    agenda: agenda.value,
    date: pickedDate.value || undefined,
    slot: pickedSlot.value || undefined,
  })
  if (ok) {
    subject.value = ''
    agenda.value = ''
    pickedSlot.value = ''
    flash('Meeting request sent — the exhibitor will assign a team member.')
  }
}

let flashTimer: ReturnType<typeof setTimeout> | undefined
function flash(msg: string) {
  sentToast.value = msg
  clearTimeout(flashTimer)
  flashTimer = setTimeout(() => { sentToast.value = '' }, 4000)
}

function fmtSlot(slot: string) {
  return slot.replace('-', ' – ')
}
function statusLabel(s: string) {
  return { requested: 'Awaiting the exhibitor', assigned: 'Member assigned', confirmed: 'Confirmed', declined: 'Declined', canceled: 'Canceled' }[s] || s
}
</script>

<template>
  <div class="overlay" @click.self="contact.close()">
    <div class="modal" role="dialog" aria-modal="true">
      <button class="x" type="button" aria-label="Close" @click="contact.close()">
        <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
      </button>

      <header class="head">
        <h2>{{ contact.target?.name }}</h2>
      </header>

      <div class="tabs">
        <button type="button" class="tab" :class="{ on: contact.tab === 'chat' }" @click="contact.tab = 'chat'">Chat</button>
        <button type="button" class="tab" :class="{ on: contact.tab === 'meet' }" @click="contact.tab = 'meet'">Meet</button>
      </div>

      <p v-if="sentToast" class="toast">{{ sentToast }}</p>

      <!-- ── Chat ── -->
      <section v-if="contact.tab === 'chat'" class="pane">
        <div class="thread">
          <div v-if="contact.threadLoading" class="hint">Loading…</div>
          <template v-else-if="contact.messages.length">
            <div v-for="m in contact.messages" :key="m.id" class="bubble" :class="{ mine: m.mine }">
              {{ m.body }}
            </div>
          </template>
          <div v-else class="hint">Start the conversation — your message goes to the exhibitor’s team.</div>
        </div>

        <div class="composer">
          <textarea
            v-model="draft"
            maxlength="1000"
            placeholder="Type your message…"
            rows="3"
          />
          <div class="quick">
            <button v-for="q in quickReplies" :key="q" type="button" class="chip" @click="draft = q">{{ q }}</button>
          </div>
          <div class="composer-foot">
            <span class="count">{{ draft.length }}/1000 characters</span>
            <button class="btn" type="button" :disabled="contact.sending || !draft.trim()" @click="send">
              <svg viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z" /></svg>
              {{ contact.sending ? 'Sending…' : 'Send message' }}
            </button>
          </div>
        </div>
      </section>

      <!-- ── Meet ── -->
      <section v-else class="pane">
        <div class="meet-scroll">
          <div v-if="contact.lounge?.enabled && contact.lounge.dates.length" class="slots">
            <label class="lbl">Preferred day</label>
            <div class="days">
              <button
                v-for="d in contact.lounge.dates"
                :key="d"
                type="button"
                class="day"
                :class="{ on: pickedDate === d }"
                @click="pickedDate = d; pickedSlot = ''"
              >{{ d }}</button>
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
            <p v-else class="hint sm">No slots configured for this day — send your request and the exhibitor will propose a time.</p>
          </div>

          <label class="lbl">Subject <span class="opt">(optional)</span></label>
          <input v-model="subject" class="in" placeholder="e.g. Product demo" maxlength="200">

          <label class="lbl">What would you like to discuss? <span class="opt">(optional)</span></label>
          <textarea v-model="agenda" class="in" rows="3" maxlength="1000" placeholder="Add a short agenda…" />

          <!-- Existing requests -->
          <div v-if="contact.requests.length" class="reqs">
            <label class="lbl">Your requests</label>
            <div v-for="r in contact.requests" :key="r.id" class="req">
              <div class="req-main">
                <strong>{{ r.subject || 'Meeting request' }}</strong>
                <span v-if="r.date" class="req-when">{{ r.date }}<template v-if="r.slot"> · {{ fmtSlot(r.slot) }}</template></span>
              </div>
              <span class="pill" :class="r.status">{{ statusLabel(r.status) }}<template v-if="r.assigned_to"> · {{ r.assigned_to }}</template></span>
            </div>
          </div>
        </div>

        <div class="composer-foot meet-foot">
          <button class="btn" type="button" :disabled="contact.requesting" @click="sendMeeting">
            <svg viewBox="0 0 24 24"><path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z" /></svg>
            {{ contact.requesting ? 'Sending…' : 'Send meeting request' }}
          </button>
        </div>
      </section>

      <p v-if="contact.error" class="err">{{ contact.error }}</p>
    </div>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(15,23,42,.5); display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 70; }
.modal { position: relative; background: #fff; border-radius: 18px; width: 100%; max-width: 640px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 50px rgba(15,23,42,.28); }

.x { position: absolute; top: 14px; right: 14px; z-index: 3; border: none; background: #ef4444; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.x svg { width: 15px; height: 15px; fill: none; stroke: #fff; stroke-width: 2.4; stroke-linecap: round; }

.head { padding: 20px 22px 16px; text-align: center; border-bottom: 1px solid #eef0f3; }
.head h2 { margin: 0; font-size: 1.15rem; font-weight: 800; color: #1e293b; }

.tabs { display: flex; border-bottom: 1px solid #eef0f3; }
.tab { flex: 1; border: none; background: none; padding: 14px; font: inherit; font-size: .95rem; font-weight: 600; color: #64748b; cursor: pointer; border-bottom: 2px solid transparent; }
.tab.on { color: var(--brand-primary); border-bottom-color: var(--brand-primary); }

.toast { margin: 12px 22px 0; padding: 10px 14px; background: color-mix(in srgb, var(--brand-primary) 10%, #fff); color: var(--brand-primary); border-radius: 10px; font-size: .84rem; font-weight: 600; }
.err { margin: 10px 22px 16px; color: #dc2626; font-size: .84rem; }

.pane { display: flex; flex-direction: column; min-height: 0; padding: 16px 22px 20px; gap: 14px; }

/* Chat */
.thread { display: flex; flex-direction: column; gap: 8px; max-height: 240px; overflow-y: auto; }
.bubble { align-self: flex-start; max-width: 78%; padding: 9px 13px; border-radius: 14px; background: #f1f5f9; color: #334155; font-size: .9rem; line-height: 1.45; border-top-left-radius: 4px; }
.bubble.mine { align-self: flex-end; background: var(--brand-primary); color: #fff; border-top-left-radius: 14px; border-top-right-radius: 4px; }
.hint { color: #94a3b8; font-size: .88rem; text-align: center; padding: 24px 8px; }
.hint.sm { padding: 8px 0; text-align: left; font-size: .82rem; }

.composer { border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px; }
.composer textarea, .in { width: 100%; border: none; outline: none; resize: vertical; font: inherit; font-size: .92rem; color: #334155; background: none; }
.composer textarea::placeholder { color: #94a3b8; }
.quick { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0 4px; }
.chip { border: 1px solid #e2e8f0; background: #f8fafc; color: #475569; border-radius: 999px; padding: 7px 12px; font: inherit; font-size: .8rem; cursor: pointer; }
.chip:hover { border-color: color-mix(in srgb, var(--brand-primary) 40%, #fff); color: var(--brand-primary); }

.composer-foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 8px; }
.count { color: #94a3b8; font-size: .8rem; }
.btn { display: inline-flex; align-items: center; gap: 8px; border: none; border-radius: 999px; padding: 11px 22px; background: var(--brand-primary); color: #fff; font: inherit; font-size: .9rem; font-weight: 700; cursor: pointer; }
.btn:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
.btn:disabled { opacity: .55; cursor: default; }
.btn svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }

/* Meet */
.meet-scroll { display: flex; flex-direction: column; gap: 6px; overflow-y: auto; max-height: 46vh; }
.lbl { font-size: .82rem; font-weight: 700; color: #334155; margin-top: 10px; }
.opt { color: #94a3b8; font-weight: 500; }
.in { border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; margin-top: 4px; }
.in:focus { border-color: color-mix(in srgb, var(--brand-primary) 45%, #fff); }
.days { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
.day { border: 1px solid #e2e8f0; background: #fff; border-radius: 10px; padding: 8px 12px; font: inherit; font-size: .82rem; color: #475569; cursor: pointer; }
.day.on { border-color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 10%, #fff); color: var(--brand-primary); font-weight: 700; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(108px, 1fr)); gap: 8px; margin-top: 6px; }
.slot { border: 1px solid #e2e8f0; background: #fff; border-radius: 10px; padding: 8px; font: inherit; font-size: .8rem; color: #475569; cursor: pointer; }
.slot.on { border-color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 10%, #fff); color: var(--brand-primary); font-weight: 700; }
.slot.busy { opacity: .4; cursor: not-allowed; text-decoration: line-through; }

.reqs { margin-top: 14px; display: flex; flex-direction: column; gap: 8px; }
.req { display: flex; align-items: center; justify-content: space-between; gap: 10px; border: 1px solid #eef0f3; border-radius: 10px; padding: 10px 12px; }
.req-main { display: flex; flex-direction: column; min-width: 0; }
.req-main strong { font-size: .86rem; color: #1e293b; }
.req-when { font-size: .78rem; color: #64748b; }
.pill { font-size: .72rem; font-weight: 700; padding: 4px 9px; border-radius: 999px; background: #f1f5f9; color: #475569; white-space: nowrap; }
.pill.confirmed { background: #dcfce7; color: #15803d; }
.pill.assigned { background: color-mix(in srgb, var(--brand-primary) 14%, #fff); color: var(--brand-primary); }
.pill.declined, .pill.canceled { background: #fee2e2; color: #b91c1c; }

.meet-foot { justify-content: flex-end; border-top: 1px solid #eef0f3; padding-top: 14px; }
</style>
