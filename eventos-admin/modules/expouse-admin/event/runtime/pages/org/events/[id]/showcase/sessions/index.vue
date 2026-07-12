<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route  = useRoute()
const router = useRouter()
const api    = useApi()
const id     = route.params.id as string

// ── Types ─────────────────────────────────────────────────────────────────────

interface Track { id: number; name: string; color: string }
interface SessionSpeaker { id: string; name: string; image_url?: string | null }

interface Session {
  id: string
  title: string
  description: string | null
  starts_at: string | null
  ends_at: string | null
  timezone: string | null
  status: 'scheduled' | 'live' | 'ended' | 'canceled'
  capacity: number | null
  stream_url: string | null
  session_place: string | null
  logo_url: string | null
  icon_url: string | null
  tags: string[]
  is_featured: boolean
  is_allowed_to_rate: boolean
  track: Track | null
  speakers: SessionSpeaker[]
}

// ── State ─────────────────────────────────────────────────────────────────────

const sessions   = ref<Session[]>([])
const search     = ref('')
const drawerOpen = ref(false)

// ── Helpers ───────────────────────────────────────────────────────────────────

function fmtDateHeading(dateKey: string): string {
  return new Date(dateKey + 'T12:00:00').toLocaleDateString([], {
    weekday: 'short', month: 'short', day: 'numeric', year: 'numeric',
  })
}

// ── Grouped sessions ──────────────────────────────────────────────────────────

const grouped = computed(() => {
  const q = search.value.toLowerCase()
  const list = q
    ? sessions.value.filter(s => s.title.toLowerCase().includes(q))
    : sessions.value

  const map = new Map<string, Session[]>()
  for (const s of list) {
    const key = s.starts_at ? s.starts_at.slice(0, 10) : '__none__'
    if (!map.has(key)) map.set(key, [])
    map.get(key)!.push(s)
  }

  return [...map.entries()]
    .sort(([a], [b]) => {
      if (a === '__none__') return 1
      if (b === '__none__') return -1
      return a.localeCompare(b)
    })
    .map(([key, items]) => ({
      key,
      label: key === '__none__' ? 'Unscheduled' : fmtDateHeading(key),
      items: [...items].sort((a, b) => (a.starts_at ?? '').localeCompare(b.starts_at ?? '')),
    }))
})

// ── API ───────────────────────────────────────────────────────────────────────

async function load() {
  try {
    const res = await api<any>(`/sessions?event=${id}`)
    sessions.value = res.data
  } catch { /* */ }
}

function editSession(s: Session) {
  router.push(`/org/events/${id}/showcase/sessions/${s.id}`)
}

async function toggleStatus(s: Session) {
  const next: Session['status'] =
    s.status === 'scheduled' ? 'live'
    : s.status === 'live'    ? 'ended'
    : 'scheduled'
  try {
    const res = await api<any>(`/sessions/${s.id}`, { method: 'PUT', body: { status: next } })
    const idx = sessions.value.findIndex(x => x.id === s.id)
    if (idx >= 0) sessions.value[idx] = res.data
  } catch { /* */ }
}

async function removeSession(s: Session) {
  if (!confirm(`Delete session "${s.title}"?`)) return
  try {
    await api(`/sessions/${s.id}`, { method: 'DELETE' })
    sessions.value = sessions.value.filter(x => x.id !== s.id)
  } catch { /* */ }
}

function onCreated(session: Session) {
  sessions.value.push(session)
  drawerOpen.value = false
}

onMounted(load)
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-4">
      <h2 class="section-title m-0">Sessions</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Manage agenda sessions for this event.</p>
    </div>

    <!-- Toolbar -->
    <div class="flex items-center gap-3 mb-5">
      <input v-model="search" placeholder="Search sessions…" class="m-0 max-w-[260px]">
      <div class="flex-1" />
      <button class="btn" @click="drawerOpen = true">
        + SCHEDULE
      </button>
    </div>

    <!-- Empty state -->
    <div v-if="!sessions.length && !search" class="card text-center py-12 muted">
      No sessions yet. Click <strong>+ SCHEDULE</strong> to add one.
    </div>

    <SessionGrid
      :groups="grouped"
      @edit="editSession"
      @toggle-status="toggleStatus"
      @remove="removeSession"
    />

    <SessionFormDrawer
      v-if="drawerOpen"
      :event-id="id"
      @close="drawerOpen = false"
      @created="onCreated"
    />
  </div>
</template>
