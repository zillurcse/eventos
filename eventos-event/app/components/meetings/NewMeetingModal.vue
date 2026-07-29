<script setup lang="ts">
import type { MeetingPartner } from '~/stores/meetings'

const emit = defineEmits<{ close: [] }>()

const meetings = useMeetingsStore()
const lounge = useLoungeStore()

const step = ref<'pick' | 'form'>('pick')
const search = ref('')
const roleFilter = ref('')
const people = ref<MeetingPartner[]>([])
const peopleLoading = ref(false)
const selected = ref<MeetingPartner | null>(null)
const title = ref('')
const agenda = ref('')
const location = ref('')
const errorMsg = ref('')

const ROLE_LABEL: Record<string, string> = {
  attendee: 'Attendees', speaker: 'Speakers', exhibitor: 'Exhibitors', sponsor: 'Sponsors',
}

// ── Fallback date/time picker (no lounge slots configured) ────────────────
const fallbackDate = ref('')
const fallbackTime = ref('')

// ── Lounge slot picker state ──────────────────────────────────────────────
const avail = ref<Awaited<ReturnType<typeof lounge.fetchFor>> | null>(null)
const loadingSlots = ref(false)
const selectedDate = ref('')
const selectedSlot = ref('')

onMounted(() => {
  meetings.fetchCapabilities({ force: true })
  searchPeople()
})

async function searchPeople() {
  peopleLoading.value = true
  try {
    people.value = await meetings.fetchPartners(search.value, roleFilter.value || undefined)
  } finally {
    peopleLoading.value = false
  }
}

let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(searchPeople, 300)
})
watch(roleFilter, () => searchPeople())
onBeforeUnmount(() => clearTimeout(searchTimer))

// Dates that actually have at least one bookable slot configured.
const slotDates = computed<string[]>(() => {
  const a = avail.value
  if (!a) return []
  return a.dates.filter(d => (a.slots[d]?.length ?? 0) > 0)
})

const useSlots = computed(() => !!avail.value?.enabled && slotDates.value.length > 0)
const isIntelligent = computed(() => avail.value?.intelligent === true || meetings.capabilities?.intelligent === true)
const isPhysical = computed(() => ['venue', 'hybrid'].includes(avail.value?.format ?? ''))
const needsLocation = computed(() => !isIntelligent.value && avail.value?.location_required === true)
const locationOptions = computed<string[]>(() => avail.value?.locations ?? [])

const busyKeys = computed<Set<string>>(() =>
  new Set((avail.value?.busy ?? []).map(b => `${b.date}|${b.slot}`)))

const daySlots = computed<string[]>(() => avail.value?.slots[selectedDate.value] ?? [])

function isBusy(slot: string): boolean {
  return busyKeys.value.has(`${selectedDate.value}|${slot}`)
}

function fmtDateTab(iso: string): string {
  const [y, m, dd] = iso.split('-').map(Number)
  const d = new Date(y ?? 1970, (m ?? 1) - 1, dd ?? 1)
  return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })
}

function pad(n: number): string { return String(n).padStart(2, '0') }

function todayIso(): string {
  const d = new Date()
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

const fallbackDates = computed<string[]>(() => avail.value?.dates ?? [])

function isPastDate(d: string): boolean {
  return d < todayIso()
}

const timeOptions = computed<string[]>(() => {
  const out: string[] = []
  for (let h = 0; h < 24; h++) {
    out.push(`${pad(h)}:00`)
    out.push(`${pad(h)}:30`)
  }
  return out
})

function isPastTime(time: string): boolean {
  if (!fallbackDate.value || fallbackDate.value !== todayIso()) return false
  const [hh, mm] = time.split(':').map(Number)
  const cand = new Date()
  cand.setHours(hh ?? 0, mm ?? 0, 0, 0)
  return cand.getTime() < Date.now()
}

function pickFallbackDate(d: string) {
  if (isPastDate(d)) return
  fallbackDate.value = fallbackDate.value === d ? '' : d
  fallbackTime.value = ''
}

function pickFallbackTime(t: string) {
  if (isPastTime(t)) return
  fallbackTime.value = fallbackTime.value === t ? '' : t
}

async function choose(d: MeetingPartner) {
  selected.value = d
  title.value = `Meeting with ${d.name || 'you'}`
  step.value = 'form'
  selectedDate.value = ''
  selectedSlot.value = ''
  fallbackDate.value = ''
  fallbackTime.value = ''
  location.value = ''
  errorMsg.value = ''

  loadingSlots.value = true
  avail.value = await lounge.fetchFor(d.id)
  loadingSlots.value = false
  selectedDate.value = slotDates.value[0] ?? ''
  if (needsLocation.value && locationOptions.value.length === 1) {
    location.value = locationOptions.value[0] ?? ''
  }
}

function pickSlot(slot: string) {
  if (isBusy(slot)) return
  selectedSlot.value = selectedSlot.value === slot ? '' : slot
}

async function submit() {
  if (!selected.value) return
  errorMsg.value = ''

  if (!meetings.canRequest) {
    errorMsg.value = 'You have reached the maximum number of meeting requests.'
    return
  }

  if (isIntelligent.value && isPhysical.value) {
    if (!useSlots.value) {
      errorMsg.value = 'No meeting slots are available right now.'
      return
    }
    if (!selectedSlot.value) {
      errorMsg.value = 'Pick an available time slot.'
      return
    }
  } else if (useSlots.value && !selectedSlot.value) {
    errorMsg.value = 'Pick an available time slot.'
    return
  }

  if (needsLocation.value && !location.value.trim()) {
    errorMsg.value = locationOptions.value.length
      ? 'Choose where you want to meet.'
      : 'Enter where you want to meet, e.g. Hall 4.'
    return
  }

  let startsAtIso: string | undefined
  if (!useSlots.value && (fallbackDate.value || fallbackTime.value)) {
    if (!fallbackDate.value || !fallbackTime.value) {
      errorMsg.value = 'Pick both a date and a time.'
      return
    }
    const candidate = new Date(`${fallbackDate.value}T${fallbackTime.value}`)
    if (Number.isNaN(candidate.getTime()) || candidate.getTime() < Date.now()) {
      errorMsg.value = 'Pick a time that is now or later.'
      return
    }
    startsAtIso = candidate.toISOString()
  }

  const ok = await meetings.request({
    to: selected.value.id,
    title: title.value.trim() || undefined,
    agenda: agenda.value.trim() || undefined,
    location: needsLocation.value ? location.value.trim() : undefined,
    ...(useSlots.value || (isIntelligent.value && isPhysical.value)
      ? { date: selectedDate.value, slot: selectedSlot.value }
      : { starts_at: startsAtIso }),
  })

  if (ok) { emit('close'); return }

  if (useSlots.value && selected.value) {
    avail.value = await lounge.fetchFor(selected.value.id)
    if (selectedSlot.value && isBusy(selectedSlot.value)) selectedSlot.value = ''
  }
  errorMsg.value = meetings.lastError || 'Could not send the request. Please try again.'
}
</script>

<template>
  <div class="overlay" @click.self="emit('close')">
    <div class="modal" role="dialog" aria-modal="true">
      <header class="head">
        <h2>{{ step === 'pick' ? 'Request a meeting' : 'Meeting details' }}</h2>
        <button class="x" type="button" aria-label="Close" @click="emit('close')">
          <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
        </button>
      </header>

      <!-- Step 1: choose someone -->
      <div v-if="step === 'pick'" class="body">
        <div class="search">
          <input v-model="search" type="text" placeholder="Search people">
          <svg viewBox="0 0 24 24"><path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3" /></svg>
        </div>

        <div v-if="meetings.allowedRoles.length > 1" class="chips">
          <button type="button" class="chip" :class="{ on: !roleFilter }" @click="roleFilter = ''">All</button>
          <button
            v-for="r in meetings.allowedRoles" :key="r"
            type="button" class="chip" :class="{ on: roleFilter === r }"
            @click="roleFilter = roleFilter === r ? '' : r"
          >{{ ROLE_LABEL[r] || r }}</button>
        </div>

        <div v-if="peopleLoading && !people.length" class="state">Loading people…</div>
        <div v-else-if="!meetings.canRequest" class="state">You have reached your meeting request limit.</div>
        <div v-else-if="!people.length" class="state">No one matches your search.</div>

        <ul v-else class="people">
          <li v-for="d in people" :key="d.id">
            <button type="button" class="person" @click="choose(d)">
              <span class="pa">
                <UserAvatar :src="d.avatar_url" :name="d.name" />
              </span>
              <span class="pi">
                <strong>{{ d.name }}</strong>
                <small v-if="d.job_title || d.company">{{ [d.job_title, d.company].filter(Boolean).join(' · ') }}</small>
              </span>
              <svg class="chev" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
            </button>
          </li>
        </ul>
      </div>

      <!-- Step 2: fill in the details -->
      <div v-else class="body">
        <div class="chosen">
          <span class="pa">
            <UserAvatar :src="selected?.avatar_url" :name="selected?.name" />
          </span>
          <div>
            <strong>{{ selected?.name }}</strong>
            <button type="button" class="change" @click="step = 'pick'">Change</button>
          </div>
        </div>

        <label class="field">
          <span>Title</span>
          <input v-model="title" type="text" maxlength="200" placeholder="What's this meeting about?">
        </label>

        <label class="field">
          <span>Agenda <em>(optional)</em></span>
          <textarea v-model="agenda" rows="3" maxlength="1000" placeholder="Add a note for the invitee" />
        </label>

        <div v-if="isIntelligent" class="intel">
          <svg viewBox="0 0 24 24"><path d="M12 2l2.4 7.4H22l-6 4.6 2.3 7-6.3-4.6L5.7 21l2.3-7-6-4.6h7.6z" /></svg>
          <p>A table will be assigned automatically when your invite is accepted.</p>
        </div>

        <div v-if="needsLocation" class="field">
          <span>Meeting location</span>

          <div v-if="locationOptions.length" class="places">
            <button
              v-for="p in locationOptions" :key="p" type="button" class="place"
              :class="{ on: location === p }"
              @click="location = location === p ? '' : p"
            >
              <svg viewBox="0 0 24 24"><path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z" /><circle cx="12" cy="10" r="2.6" /></svg>
              {{ p }}
            </button>
          </div>

          <input
            v-model="location" type="text" maxlength="180"
            :class="{ spaced: locationOptions.length }"
            :placeholder="locationOptions.length ? 'Or type another place…' : 'e.g. Hall 4, Meeting Room 2'"
          >
        </div>

        <div v-if="loadingSlots" class="field"><span>Pick a time</span><p class="hint">Loading available slots…</p></div>

        <div v-else-if="useSlots" class="field">
          <span>Pick a time slot</span>

          <div class="dates">
            <button
              v-for="d in slotDates" :key="d" type="button" class="date"
              :class="{ on: selectedDate === d }"
              @click="selectedDate = d; selectedSlot = ''"
            >{{ fmtDateTab(d) }}</button>
          </div>

          <div v-if="daySlots.length" class="slots">
            <button
              v-for="s in daySlots" :key="s" type="button" class="slot"
              :class="{ on: selectedSlot === s, taken: isBusy(s) }"
              :disabled="isBusy(s)"
              :title="isBusy(s) ? 'Already booked' : ''"
              @click="pickSlot(s)"
            >{{ s }}</button>
          </div>
          <p v-else class="hint">No slots on this day.</p>
        </div>

        <div v-else class="field">
          <span>Proposed time <em>(optional)</em></span>

          <div v-if="fallbackDates.length" class="dates">
            <button
              v-for="d in fallbackDates" :key="d" type="button" class="date"
              :class="{ on: fallbackDate === d }"
              :disabled="isPastDate(d)"
              @click="pickFallbackDate(d)"
            >{{ fmtDateTab(d) }}</button>
          </div>
          <input v-else v-model="fallbackDate" type="date" :min="todayIso()">

          <div v-if="fallbackDate" class="slots times">
            <button
              v-for="t in timeOptions" :key="t" type="button" class="slot"
              :class="{ on: fallbackTime === t, taken: isPastTime(t) }"
              :disabled="isPastTime(t)"
              :title="isPastTime(t) ? 'Already passed' : ''"
              @click="pickFallbackTime(t)"
            >{{ t }}</button>
          </div>
        </div>

        <p v-if="errorMsg" class="err">{{ errorMsg }}</p>

        <div class="foot">
          <button type="button" class="btn ghost" @click="step = 'pick'">Back</button>
          <button type="button" class="btn primary" :disabled="meetings.sending" @click="submit">
            {{ meetings.sending ? 'Sending…' : 'Send request' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(15,23,42,.5); display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 60; }
.modal { background: #fff; border-radius: 18px; width: 100%; max-width: 460px; max-height: 88vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 50px rgba(15,23,42,.28); }

.head { display: flex; align-items: center; justify-content: space-between; padding: 18px 20px; border-bottom: 1px solid #eef0f3; }
.head h2 { margin: 0; font-size: 1.05rem; font-weight: 800; color: #1e293b; }
.x { border: none; background: #f1f5f9; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.x svg { width: 16px; height: 16px; fill: none; stroke: #64748b; stroke-width: 2; stroke-linecap: round; }

.body { padding: 18px 20px; overflow-y: auto; }

.search { position: relative; margin-bottom: 12px; }
.search input { width: 100%; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 42px 12px 14px; font: inherit; font-size: .92rem; outline: none; }
.search input:focus { border-color: var(--brand-primary); }
.search svg { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; fill: none; stroke: var(--brand-primary); stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }

.chips { display: flex; flex-wrap: wrap; gap: 7px; margin-bottom: 10px; }
.chip { border: 1px solid #e2e8f0; background: #fff; color: #64748b; font: inherit; font-size: .76rem; font-weight: 700; border-radius: 999px; padding: 5px 13px; cursor: pointer; }
.chip.on { background: var(--brand-primary); border-color: var(--brand-primary); color: #fff; }

.state { padding: 28px 0; text-align: center; color: #94a3b8; font-size: .88rem; }

.people { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 2px; }
.person { width: 100%; display: flex; align-items: center; gap: 12px; border: none; background: none; padding: 10px; border-radius: 12px; cursor: pointer; text-align: left; }
.person:hover { background: #f7f8fa; }
.pa { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; flex: 0 0 auto; background: color-mix(in srgb, var(--brand-primary) 12%, #fff); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .85rem; color: color-mix(in srgb, var(--brand-primary) 75%, #fff); }
.pa img { width: 100%; height: 100%; object-fit: cover; }
.pi { min-width: 0; flex: 1; display: flex; flex-direction: column; }
.pi strong { font-size: .9rem; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pi small { color: #64748b; font-size: .78rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chev { width: 18px; height: 18px; fill: none; stroke: #cbd5e1; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

.chosen { display: flex; align-items: center; gap: 12px; padding: 10px 12px; background: #f7f8fa; border-radius: 12px; margin-bottom: 16px; }
.chosen strong { display: block; font-size: .92rem; color: #1e293b; }
.change { border: none; background: none; color: var(--brand-primary); font: inherit; font-size: .78rem; font-weight: 600; cursor: pointer; padding: 0; }

.intel { display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; background: color-mix(in srgb, var(--brand-primary) 8%, #fff); border: 1px solid color-mix(in srgb, var(--brand-primary) 20%, #fff); border-radius: 12px; margin-bottom: 14px; }
.intel svg { width: 18px; height: 18px; flex: 0 0 auto; fill: var(--brand-primary); margin-top: 1px; }
.intel p { margin: 0; font-size: .82rem; color: #475569; line-height: 1.45; }

.field { display: block; margin-bottom: 14px; }
.field span { display: block; font-size: .82rem; font-weight: 600; color: #334155; margin-bottom: 6px; }
.field em { color: #94a3b8; font-style: normal; font-weight: 400; }
.field input, .field textarea { width: 100%; border: 1px solid #e2e8f0; border-radius: 10px; padding: 11px 13px; font: inherit; font-size: .9rem; outline: none; resize: vertical; }
.field input:focus, .field textarea:focus { border-color: var(--brand-primary); }
.hint { margin: 0; font-size: .82rem; color: #94a3b8; }

.dates { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 4px; margin-bottom: 10px; }
.date { flex: 0 0 auto; border: 1px solid #e2e8f0; background: #fff; border-radius: 10px; padding: 8px 12px; font: inherit; font-size: .8rem; font-weight: 600; color: #475569; cursor: pointer; white-space: nowrap; }
.date:hover { border-color: var(--brand-primary); }
.date.on { border-color: var(--brand-primary); color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 10%, #fff); }

.places { display: flex; flex-wrap: wrap; gap: 8px; }
.field input.spaced { margin-top: 8px; }
.place { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #e2e8f0; background: #fff; border-radius: 999px; padding: 8px 13px; font: inherit; font-size: .82rem; font-weight: 600; color: #334155; cursor: pointer; }
.place:hover { border-color: var(--brand-primary); color: var(--brand-primary); }
.place.on { border-color: var(--brand-primary); background: var(--brand-primary); color: #fff; }
.place svg { width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

.slots { display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: 8px; }
.slots.times { max-height: 168px; overflow-y: auto; margin-top: 10px; padding-right: 2px; }
.slot { border: 1px solid #e2e8f0; background: #fff; border-radius: 9px; padding: 9px 6px; font: inherit; font-size: .8rem; font-weight: 600; color: #334155; cursor: pointer; }
.slot:hover:not(:disabled) { border-color: var(--brand-primary); color: var(--brand-primary); }
.slot.on { border-color: var(--brand-primary); background: var(--brand-primary); color: #fff; }
.slot.taken { background: #f1f5f9; color: #cbd5e1; cursor: not-allowed; text-decoration: line-through; }

.err { color: #dc2626; font-size: .84rem; margin: 0 0 12px; }

.foot { display: flex; gap: 10px; margin-top: 4px; }
.btn { flex: 1; border: none; border-radius: 10px; padding: 12px; font: inherit; font-size: .9rem; font-weight: 600; cursor: pointer; }
.btn:disabled { opacity: .6; cursor: default; }
.btn.primary { background: var(--brand-primary); color: #fff; }
.btn.ghost { background: #f1f5f9; color: #475569; }
</style>
