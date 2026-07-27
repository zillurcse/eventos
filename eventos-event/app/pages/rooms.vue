<script setup lang="ts">
import type { BreakoutRoom, JoinConfig } from '~/stores/rooms'

definePageMeta({ layout: 'event', middleware: 'auth' })

const rooms = useRoomsStore()
const site = useSiteStore()
const api = useApi()

const TYPE_LABEL: Record<string, string> = {
  workshop: 'Workshop', networking: 'Networking', round_table: 'Round Table',
  sponsor_demo: 'Sponsor Demo', team: 'Team', private: 'Private', vip: 'VIP',
  interview: 'Interview', panel: 'Panel', ama: 'AMA', custom: 'Custom',
}

const search = ref('')
const typeFilter = ref('all')

// Join flow state
const active = ref<{ config: JoinConfig, title: string } | null>(null)
const joining = ref<number | null>(null)
const codeModal = ref<BreakoutRoom | null>(null)
const codeInput = ref('')
const joinError = ref('')

const typeOptions = computed(() => {
  const seen = new Set<string>()
  for (const r of rooms.rooms) seen.add(r.type)
  return [
    { key: 'all', label: 'Type: All' },
    ...Array.from(seen).sort().map(t => ({ key: t, label: `Type: ${TYPE_LABEL[t] ?? t}` })),
  ]
})

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return rooms.rooms.filter((r) => {
    if (typeFilter.value !== 'all' && r.type !== typeFilter.value) return false
    if (!q) return true
    return r.name.toLowerCase().includes(q)
      || (r.description || '').toLowerCase().includes(q)
      || (TYPE_LABEL[r.type] || r.type).toLowerCase().includes(q)
  })
})

function onJoinClick(room: BreakoutRoom) {
  joinError.value = ''
  if (room.access_type === 'coded' && room.has_access_code) {
    codeInput.value = ''
    codeModal.value = room
  } else {
    join(room)
  }
}

async function join(room: BreakoutRoom, accessCode?: string) {
  const eventUuid = site.event?.uuid
  if (!eventUuid) return
  joining.value = room.id
  joinError.value = ''
  try {
    const res = await api<{ data: JoinConfig }>(`/events/${eventUuid}/breakout-rooms/${room.id}/token`, {
      method: 'POST',
      body: accessCode ? { access_code: accessCode } : {},
    })
    active.value = { config: res.data, title: room.name }
    codeModal.value = null
  } catch (e: any) {
    joinError.value = e?.data?.errors?.access_code?.[0] || e?.data?.message || 'Could not join this room.'
  } finally {
    joining.value = null
  }
}

function onLeave() {
  active.value = null
  rooms.fetchRooms(true)
}

let poll: ReturnType<typeof setInterval> | null = null
onMounted(() => {
  if (!rooms.loaded) rooms.fetchRooms()
  rooms.fetchAds()
  poll = setInterval(() => {
    if (active.value) return
    rooms.fetchRooms(true)
  }, 15000)
})
onBeforeUnmount(() => { if (poll) clearInterval(poll) })
</script>

<template>
  <div>
    <ReceptionAdStrip v-if="rooms.ads.length" :ads="rooms.ads" class="ads" />

    <h1 class="title">Rooms ({{ filtered.length }})</h1>

    <div class="toolbar">
      <div class="search">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="11" cy="11" r="7" />
          <path d="m20 20-3.5-3.5" />
        </svg>
        <input v-model="search" type="search" placeholder="Search" aria-label="Search rooms">
      </div>
      <div class="fselect">
        <select v-model="typeFilter" title="Type">
          <option v-for="o in typeOptions" :key="o.key" :value="o.key">{{ o.label }}</option>
        </select>
      </div>
    </div>

    <div v-if="rooms.loading && !rooms.loaded" class="state">Loading rooms…</div>
    <div v-else-if="rooms.error" class="state">Couldn’t load rooms. Please try again.</div>
    <div v-else-if="!rooms.rooms.length" class="state">No rooms are open right now. Check back soon.</div>
    <div v-else-if="!filtered.length" class="state">No rooms match your search.</div>

    <div v-else class="grid">
      <RoomsRoomCard
        v-for="r in filtered"
        :key="r.id"
        :room="r"
        :joining="joining === r.id"
        @join="onJoinClick(r)"
      />
    </div>

    <!-- Access-code modal for coded rooms -->
    <div v-if="codeModal" class="overlay" @click.self="codeModal = null">
      <div class="modal">
        <h3>Enter access code</h3>
        <p class="mut">“{{ codeModal.name }}” is a private room. Enter the code shared by the organizer.</p>
        <input v-model="codeInput" type="text" placeholder="Access code" @keyup.enter="join(codeModal, codeInput)">
        <p v-if="joinError" class="err">{{ joinError }}</p>
        <div class="actions">
          <button class="ghost" type="button" @click="codeModal = null">Cancel</button>
          <button class="primary" type="button" :disabled="!codeInput || joining !== null" @click="join(codeModal, codeInput)">
            {{ joining !== null ? 'Joining…' : 'Join room' }}
          </button>
        </div>
      </div>
    </div>

    <p v-if="joinError && !codeModal" class="err floaterr">{{ joinError }}</p>

    <!-- Live room -->
    <RoomsRoomStage v-if="active" :config="active.config" :title="active.title" @leave="onLeave" />
  </div>
</template>

<style scoped>
.ads { margin-bottom: 18px; }

.title {
  margin: 0 0 16px;
  font-size: 1.65rem;
  font-weight: 800;
  color: #1e293b;
  letter-spacing: -0.02em;
}

.toolbar {
  display: flex;
  gap: 12px;
  margin-bottom: 22px;
}

.search {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 10px;
  border: 1px solid #e2e5eb;
  border-radius: 10px;
  padding: 0 14px;
  height: 44px;
  background: #fff;
}

.search svg {
  width: 18px;
  height: 18px;
  flex: 0 0 auto;
  fill: none;
  stroke: #94a3b8;
  stroke-width: 2;
  stroke-linecap: round;
}

.search input {
  flex: 1;
  border: none;
  outline: none;
  font: inherit;
  font-size: .88rem;
  color: #334155;
  background: none;
}

.search input::placeholder { color: #94a3b8; }

.fselect {
  min-width: 150px;
  height: 44px;
  border: 1px solid #e2e5eb;
  border-radius: 10px;
  padding: 0 12px;
  font: inherit;
  font-size: .86rem;
  color: #334155;
  background: #fff;
}

.fselect select {
  width: 100%;
  height: 100%;
  border: none;
  outline: none;
  background: transparent;
  font: inherit;
  color: inherit;
}

.state {
  padding: 60px 0;
  text-align: center;
  color: #64748b;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
}

@media (min-width: 1100px) {
  .grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.overlay {
  position: fixed;
  inset: 0;
  z-index: 90;
  background: rgba(15, 23, 42, .5);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.modal {
  background: #fff;
  border-radius: 16px;
  padding: 22px;
  width: 100%;
  max-width: 380px;
}

.modal h3 { margin: 0 0 6px; color: #1e293b; }
.mut { margin: 0 0 14px; color: #64748b; font-size: .86rem; }
.modal input { width: 100%; }
.actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; }
.ghost {
  background: #fff;
  border: 1px solid #e2e8f0;
  color: #475569;
  border-radius: 10px;
  padding: 9px 16px;
  cursor: pointer;
  font-weight: 600;
}
.primary {
  background: var(--brand-primary);
  color: #fff;
  border: none;
  border-radius: 10px;
  padding: 9px 18px;
  cursor: pointer;
  font-weight: 700;
}
.primary:disabled { opacity: .6; cursor: default; }
.err { color: #dc2626; font-size: .84rem; margin: 10px 0 0; }
.floaterr { text-align: center; }

@media (max-width: 640px) {
  .toolbar { flex-direction: column; }
  .fselect { width: 100%; }
}
</style>
