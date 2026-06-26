<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

type Channel = 'web' | 'email' | 'sms'
interface Action { key: string, label: string }

const CHANNELS: { key: Channel, label: string }[] = [
  { key: 'web', label: 'Web' },
  { key: 'email', label: 'Email' },
  { key: 'sms', label: 'SMS' },
]

// Fixed catalogue of triggers; the matrix is stored as
// { action_key => { web, email, sms } }.
const ACTIONS: Action[] = [
  { key: 'profile_view', label: 'Profile view' },
  { key: 'message', label: 'Message' },
  { key: 'meeting', label: 'Metting' },
  { key: 'post', label: 'Post' },
  { key: 'poll', label: 'Poll' },
  { key: 'session_registration', label: 'Session Registration' },
  { key: 'meeting_reminder', label: 'Metting reminder' },
]

type Matrix = Record<string, Record<Channel, boolean>>

const saving = ref(false)
const saved = ref(false)

function buildMatrix(values: Matrix = {}): Matrix {
  const out: Matrix = {}
  for (const a of ACTIONS) {
    const row = values[a.key] || ({} as Record<Channel, boolean>)
    out[a.key] = {
      web: row.web ?? true,
      email: row.email ?? true,
      sms: row.sms ?? true,
    }
  }
  return out
}

// Seed synchronously so the template never reads an undefined row on first paint.
const matrix = reactive<Matrix>(buildMatrix())

function seed(values: Matrix = {}) {
  Object.assign(matrix, buildMatrix(values))
}

async function load() {
  try {
    const res = await api<{ data: { notifications: Matrix } }>(`/events/${id}/settings`)
    seed(res.data.notifications || {})
  } catch {
    seed()
  }
}

async function save() {
  saving.value = true
  try {
    const clean: Matrix = {}
    for (const a of ACTIONS) clean[a.key] = { ...matrix[a.key] }
    await api(`/events/${id}/settings`, { method: 'PUT', body: { notifications: clean } })
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
      <h2 class="section-title m-0">Notification</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">
        Configure and select automatic notification option to be triggered for attendees for your web/mobile app.
        <span v-if="saved" class="badge active ml-2">saved ✓</span>
      </p>
    </div>

    <div class="card">
      <table class="w-full">
        <thead>
          <tr class="border-b border-line">
            <th class="text-left text-[.78rem] font-bold text-muted uppercase tracking-wide pb-2.5">Action</th>
            <th v-for="c in CHANNELS" :key="c.key" class="text-center text-[.78rem] font-bold text-muted uppercase tracking-wide pb-2.5 w-32">{{ c.label }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(a, i) in ACTIONS" :key="a.key" :class="Number(i) % 2 ? 'bg-white' : 'bg-[#f7f7f9]'">
            <td class="text-[.9rem] text-ink py-3 pl-3 rounded-l-lg">{{ a.label }}</td>
            <td v-for="c in CHANNELS" :key="c.key" class="text-center py-3" :class="c.key === 'sms' ? 'rounded-r-lg' : ''">
              <input
                v-model="matrix[a.key][c.key]"
                type="checkbox"
                class="w-5 h-5 accent-[#6352e7] cursor-pointer align-middle"
                :aria-label="`${a.label} – ${c.label}`"
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
