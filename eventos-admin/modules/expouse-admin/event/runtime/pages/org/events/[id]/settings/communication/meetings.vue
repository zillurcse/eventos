<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

type Role = 'attendee' | 'speaker' | 'exhibitor' | 'sponsor'

const ROLES: { key: Role, label: string }[] = [
  { key: 'attendee', label: 'Attendee' },
  { key: 'speaker', label: 'Speaker' },
  { key: 'exhibitor', label: 'Exhibitor' },
  { key: 'sponsor', label: 'Sponsor' },
]

const SLOT_DURATIONS = [10, 15, 30]

type PermMatrix = Record<Role, Record<Role, boolean>>
type Restrictions = Record<Role, { requests: number, confirmed: number }>

const intelligent = ref(false)
const slotDuration = ref(10)
const saving = ref(false)
const saved = ref(false)
const restrictionOpen = ref(false)

// Meeting locations — where one-to-one meetings physically happen ("Hall 4").
// Only meaningful on a venue/hybrid event; an online one has nowhere to meet.
const eventFormat = ref('venue')
const isPhysical = computed(() => eventFormat.value === 'venue' || eventFormat.value === 'hybrid')
const locations = ref<string[]>([])
const newLocation = ref('')

function addLocation() {
  const name = newLocation.value.trim()
  if (!name || locations.value.includes(name)) { newLocation.value = ''; return }
  locations.value.push(name)
  newLocation.value = ''
}

function removeLocation(name: string) {
  locations.value = locations.value.filter(l => l !== name)
}

function buildPerms(values: Partial<PermMatrix> = {}): PermMatrix {
  const out = {} as PermMatrix
  for (const r of ROLES) {
    const row = (values[r.key] || {}) as Partial<Record<Role, boolean>>
    out[r.key] = {
      attendee: row.attendee ?? true,
      speaker: row.speaker ?? true,
      exhibitor: row.exhibitor ?? true,
      sponsor: row.sponsor ?? true,
    }
  }
  return out
}

function buildRestrictions(values: Partial<Restrictions> = {}): Restrictions {
  const out = {} as Restrictions
  for (const r of ROLES) {
    const row = (values[r.key] || {}) as Partial<{ requests: number, confirmed: number }>
    out[r.key] = {
      requests: Number.isFinite(row.requests) ? (row.requests as number) : 10,
      confirmed: Number.isFinite(row.confirmed) ? (row.confirmed as number) : 10,
    }
  }
  return out
}

// Seeded synchronously so the template never reads an undefined row on first paint.
const permissions = reactive<PermMatrix>(buildPerms())
const restrictions = reactive<Restrictions>(buildRestrictions())

async function load() {
  try {
    const res = await api<{ data: { meeting: any } }>(`/events/${id}/settings`)
    const m = res.data.meeting || {}
    Object.assign(permissions, buildPerms(m.permissions || {}))
    Object.assign(restrictions, buildRestrictions(m.restrictions || {}))
    intelligent.value = !!m.intelligent
    locations.value = Array.isArray(m.locations) ? m.locations.filter((l: unknown) => typeof l === 'string') : []
    if (SLOT_DURATIONS.includes(Number(m.slot_duration))) slotDuration.value = Number(m.slot_duration)
  } catch { /* keep defaults */ }

  try {
    const ev = await api<{ data: { format?: string } }>(`/events/${id}`)
    eventFormat.value = ev.data.format || 'venue'
  } catch { /* assume in-person */ }
}

async function save() {
  saving.value = true
  try {
    const perms = {} as PermMatrix
    for (const r of ROLES) perms[r.key] = { ...permissions[r.key] }
    const restr = {} as Restrictions
    for (const r of ROLES) {
      restr[r.key] = {
        requests: Math.max(0, Math.trunc(Number(restrictions[r.key].requests) || 0)),
        confirmed: Math.max(0, Math.trunc(Number(restrictions[r.key].confirmed) || 0)),
      }
    }
    await api(`/events/${id}/settings`, {
      method: 'PUT',
      body: {
        meeting: {
          permissions: perms,
          intelligent: intelligent.value,
          slot_duration: slotDuration.value,
          restrictions: restr,
          locations: locations.value,
        },
      },
    })
    saved.value = true; setTimeout(() => (saved.value = false), 1500)
  } finally {
    saving.value = false
  }
}

async function saveRestriction() {
  await save()
  restrictionOpen.value = false
}

onMounted(load)
</script>

<template>
  <div>
    <!-- Meeting permission matrix -->
    <div class="card mb-4">
      <h2 class="font-bold text-base text-[#1a1a2e] m-0">
        Meeting
        <span v-if="saved" class="badge active ml-2">saved ✓</span>
      </h2>
      <p class="muted text-[.86rem] mt-1 mb-4">Define who can send meetings via chat to whom.</p>

      <table class="w-full">
        <thead>
          <tr class="border-b border-line">
            <th class="text-left text-[.78rem] font-bold text-muted uppercase tracking-wide pb-2.5">Module</th>
            <th v-for="c in ROLES" :key="c.key" class="text-center text-[.78rem] font-bold text-muted uppercase tracking-wide pb-2.5 w-36">{{ c.label }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, i) in ROLES" :key="r.key" :class="Number(i) % 2 ? 'bg-white' : 'bg-[#f7f7f9]'">
            <td class="text-[.9rem] text-ink py-3 pl-3 rounded-l-lg">{{ r.label }}</td>
            <td v-for="c in ROLES" :key="c.key" class="text-center py-3" :class="c.key === 'sponsor' ? 'rounded-r-lg' : ''">
              <AppCheckbox
                v-model="permissions[r.key][c.key]"
                :aria-label="`${r.label} can send meetings to ${c.label}`"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Intelligent Meeting toggle -->
    <div class="card mb-4">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h3 class="font-bold text-base text-[#1a1a2e] m-0">Intelligent Meeting</h3>
          <p class="muted text-[.86rem] mt-1 mb-0 max-w-[760px]">
            Make meetings better with automatic Table/Meeting Room allocation, slot management and meeting area map.
          </p>
        </div>
        <button
          type="button"
          role="switch"
          :aria-checked="intelligent"
          class="relative w-11 h-6 rounded-full shrink-0 transition-colors duration-150"
          :class="intelligent ? 'bg-brand' : 'bg-[#d1d5db]'"
          @click="intelligent = !intelligent"
        >
          <span
            class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform duration-150"
            :class="intelligent ? 'translate-x-5' : ''"
          />
        </button>
      </div>
    </div>

    <!-- Slot duration -->
    <div class="card mb-4">
      <h3 class="font-bold text-base text-[#1a1a2e] m-0">Meeting Slot Duration</h3>
      <p class="muted text-[.86rem] mt-1 mb-4">Attendees will be able to schedule meetings for the selected slot duration.</p>
      <div class="flex gap-8">
        <label v-for="d in SLOT_DURATIONS" :key="d" class="flex items-center gap-2 text-[.9rem] text-ink m-0 cursor-pointer">
          <input v-model.number="slotDuration" type="radio" :value="d" class="w-4 h-4 m-0 accent-brand">
          {{ d }} Minutes
        </label>
      </div>
    </div>

    <!-- Meeting locations — in-person / hybrid events only -->
    <div v-if="isPhysical" class="card mb-4">
      <h3 class="font-bold text-base text-[#1a1a2e] m-0">Meeting Locations</h3>
      <p class="muted text-[.86rem] mt-1 mb-4">
        Where one-to-one meetings take place, e.g. <em>Hall 4</em>. Attendees pick one of these when they
        request a meeting. Leave the list empty to let them type their own place.
      </p>

      <div v-if="locations.length" class="flex flex-wrap gap-2 mb-3">
        <span
          v-for="l in locations"
          :key="l"
          class="inline-flex items-center gap-2 rounded-full border border-line bg-[#f7f7f9] py-1.5 pl-3.5 pr-2 text-[.85rem] text-ink"
        >
          {{ l }}
          <button
            type="button"
            class="flex h-5 w-5 items-center justify-center rounded-full text-muted hover:bg-white hover:text-[#dc2626]"
            :aria-label="`Remove ${l}`"
            @click="removeLocation(l)"
          >×</button>
        </span>
      </div>

      <div class="flex gap-2">
        <input
          v-model="newLocation"
          type="text"
          maxlength="180"
          placeholder="e.g. Hall 4"
          class="m-0 max-w-xs"
          @keydown.enter.prevent="addLocation"
        >
        <button class="btn ghost shrink-0" type="button" @click="addLocation">ADD</button>
      </div>
    </div>

    <!-- Meeting restriction -->
    <div class="card mb-5">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h3 class="font-bold text-base text-[#1a1a2e] m-0">Meeting Restriction</h3>
          <p class="muted text-[.86rem] mt-1 mb-0">Set the number of meetings various types of users can send and confirm.</p>
        </div>
        <button class="btn ghost shrink-0" @click="restrictionOpen = true">MANAGE</button>
      </div>
    </div>

    <div class="flex justify-end">
      <button class="btn" :disabled="saving" @click="save">
        {{ saving ? 'Saving…' : 'SAVE' }}
      </button>
    </div>

    <!-- Meeting Restriction drawer (slides in from the right) -->
    <Drawer v-if="restrictionOpen" title="Meeting Restriction" @close="restrictionOpen = false">
      <table class="w-full">
        <thead>
          <tr>
            <th class="text-left text-[.76rem] font-bold text-muted uppercase tracking-wide pb-2">Requests</th>
            <th class="text-left text-[.76rem] font-bold text-muted uppercase tracking-wide pb-2">Requests</th>
            <th class="text-left text-[.76rem] font-bold text-muted uppercase tracking-wide pb-2">Confirmed</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in ROLES" :key="r.key">
            <td class="text-[.9rem] text-ink py-2 pr-4">{{ r.label }}</td>
            <td class="py-2 pr-4">
              <input v-model.number="restrictions[r.key].requests" type="number" min="0" class="m-0 w-28">
            </td>
            <td class="py-2">
              <input v-model.number="restrictions[r.key].confirmed" type="number" min="0" class="m-0 w-28">
            </td>
          </tr>
        </tbody>
      </table>

      <div class="modal-actions">
        <button class="btn" :disabled="saving" @click="saveRestriction">{{ saving ? 'Saving…' : 'SAVE' }}</button>
      </div>
    </Drawer>
  </div>
</template>
