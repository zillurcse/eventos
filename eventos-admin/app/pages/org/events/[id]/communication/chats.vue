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

// Square matrix: { from_role => { attendee, speaker, exhibitor, sponsor } }.
// A checked cell means a "from" role may start a chat with the column role.
type Matrix = Record<Role, Record<Role, boolean>>

const saving = ref(false)
const saved = ref(false)

function buildMatrix(values: Partial<Matrix> = {}): Matrix {
  const out = {} as Matrix
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

// Seed synchronously so the template never reads an undefined row on first paint.
const matrix = reactive<Matrix>(buildMatrix())

async function load() {
  try {
    const res = await api<{ data: { chat: Partial<Matrix> } }>(`/events/${id}/settings`)
    Object.assign(matrix, buildMatrix(res.data.chat || {}))
  } catch { /* keep defaults */ }
}

async function save() {
  saving.value = true
  try {
    const clean = {} as Matrix
    for (const r of ROLES) clean[r.key] = { ...matrix[r.key] }
    await api(`/events/${id}/settings`, { method: 'PUT', body: { chat: clean } })
    saved.value = true; setTimeout(() => (saved.value = false), 1500)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="max-w-[1100px]">
    <div class="mb-4">
      <h2 class="section-title m-0">Chat</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">
        Assign user authentication to requests chat within whom.
        <span v-if="saved" class="badge active ml-2">saved ✓</span>
      </p>
    </div>

    <div class="card">
      <table class="w-full">
        <thead>
          <tr class="border-b border-line">
            <th class="text-left text-[.78rem] font-bold text-muted uppercase tracking-wide pb-2.5">Sections</th>
            <th v-for="c in ROLES" :key="c.key" class="text-center text-[.78rem] font-bold text-muted uppercase tracking-wide pb-2.5 w-36">{{ c.label }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, i) in ROLES" :key="r.key" :class="Number(i) % 2 ? 'bg-white' : 'bg-[#f7f7f9]'">
            <td class="text-[.9rem] text-ink py-3 pl-3 rounded-l-lg">{{ r.label }}</td>
            <td v-for="c in ROLES" :key="c.key" class="text-center py-3" :class="c.key === 'sponsor' ? 'rounded-r-lg' : ''">
              <input
                v-model="matrix[r.key][c.key]"
                type="checkbox"
                class="w-5 h-5 accent-[#6352e7] cursor-pointer align-middle"
                :aria-label="`${r.label} can chat with ${c.label}`"
              >
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="flex justify-end mt-5">
      <button class="btn" :disabled="saving" @click="save">
        {{ saving ? 'Saving…' : 'SAVE' }}
      </button>
    </div>
  </div>
</template>
