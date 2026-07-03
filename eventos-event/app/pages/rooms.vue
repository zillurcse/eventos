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

// Join flow state
const active = ref<{ config: JoinConfig, title: string } | null>(null)
const joining = ref<number | null>(null)
const codeModal = ref<BreakoutRoom | null>(null)
const codeInput = ref('')
const joinError = ref('')

function label(t: string) { return TYPE_LABEL[t] ?? t }

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

onMounted(() => { if (!rooms.loaded) rooms.fetchRooms() })
</script>

<template>
  <div>
    <div class="head">
      <h1>Rooms</h1>
      <p class="sub">Join live breakout rooms — networking lounges, workshops and demos over video.</p>
    </div>

    <div v-if="rooms.loading && !rooms.loaded" class="state">Loading rooms…</div>
    <div v-else-if="!rooms.rooms.length" class="state">No rooms are open right now. Check back soon.</div>

    <div v-else class="grid">
      <article v-for="r in rooms.rooms" :key="r.id" class="room">
        <div class="poster">
          <img v-if="r.poster_url" :src="r.poster_url" :alt="r.name" />
          <div v-else class="poster-ph" />
          <span class="type">{{ label(r.type) }}</span>
          <span v-if="r.access_type === 'coded'" class="lock" title="Access code required">
            <svg viewBox="0 0 24 24"><path d="M6 10V8a6 6 0 0 1 12 0v2M5 10h14v10H5z" /></svg>
          </span>
        </div>

        <div class="body">
          <h3>{{ r.name }}</h3>
          <p v-if="r.description" class="desc">{{ r.description }}</p>
          <div class="foot">
            <span v-if="r.capacity" class="cap">
              <svg viewBox="0 0 24 24"><path d="M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM2 20a6 6 0 0 1 12 0M16 11a3 3 0 1 0 0-6M15 14a6 6 0 0 1 7 6" /></svg>
              {{ r.capacity }}
            </span>
            <button class="join" type="button" :disabled="joining === r.id" @click="onJoinClick(r)">
              {{ joining === r.id ? 'Joining…' : 'Join' }}
            </button>
          </div>
        </div>
      </article>
    </div>

    <!-- Access-code modal for coded rooms -->
    <div v-if="codeModal" class="overlay" @click.self="codeModal = null">
      <div class="modal">
        <h3>Enter access code</h3>
        <p class="mut">“{{ codeModal.name }}” is a private room. Enter the code shared by the organizer.</p>
        <input v-model="codeInput" type="text" placeholder="Access code" @keyup.enter="join(codeModal, codeInput)" />
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
    <RoomsRoomStage v-if="active" :config="active.config" :title="active.title" @leave="active = null" />
  </div>
</template>

<style scoped>
.head { margin-bottom: 18px; }
.head h1 { margin: 0; font-size: 1.4rem; font-weight: 800; color: #1e293b; }
.sub { margin: 4px 0 0; color: #64748b; font-size: .9rem; }
.state { padding: 60px 0; text-align: center; color: #64748b; }

.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 18px; }
.room { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 2px rgba(15,23,42,.05); display: flex; flex-direction: column; }
.poster { position: relative; aspect-ratio: 16 / 9; background: #eef0f3; }
.poster img { width: 100%; height: 100%; object-fit: cover; }
.poster-ph { position: absolute; inset: 0; background: linear-gradient(135deg, color-mix(in srgb, var(--brand-primary) 60%, #fff), var(--brand-primary)); }
.type { position: absolute; top: 10px; left: 10px; background: rgba(15,23,42,.7); color: #fff; font-size: .68rem; font-weight: 700; padding: 4px 10px; border-radius: 999px; text-transform: uppercase; letter-spacing: .4px; }
.lock { position: absolute; top: 10px; right: 10px; width: 26px; height: 26px; background: rgba(15,23,42,.7); border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.lock svg { width: 14px; height: 14px; fill: none; stroke: #fff; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

.body { padding: 14px 16px 16px; display: flex; flex-direction: column; flex: 1; }
.body h3 { margin: 0 0 6px; font-size: .98rem; font-weight: 700; color: #1e293b; }
.desc { margin: 0 0 14px; color: #64748b; font-size: .84rem; line-height: 1.45; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.foot { display: flex; align-items: center; justify-content: space-between; margin-top: auto; }
.cap { display: inline-flex; align-items: center; gap: 5px; color: #94a3b8; font-size: .8rem; font-weight: 600; }
.cap svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.join { background: var(--brand-primary); color: #fff; border: none; border-radius: 999px; padding: 9px 22px; font-weight: 700; cursor: pointer; font-size: .85rem; }
.join:disabled { opacity: .6; cursor: default; }

.overlay { position: fixed; inset: 0; z-index: 90; background: rgba(15,23,42,.5); display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal { background: #fff; border-radius: 16px; padding: 22px; width: 100%; max-width: 380px; }
.modal h3 { margin: 0 0 6px; color: #1e293b; }
.mut { margin: 0 0 14px; color: #64748b; font-size: .86rem; }
.modal input { width: 100%; }
.actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; }
.ghost { background: #fff; border: 1px solid #e2e8f0; color: #475569; border-radius: 10px; padding: 9px 16px; cursor: pointer; font-weight: 600; }
.primary { background: var(--brand-primary); color: #fff; border: none; border-radius: 10px; padding: 9px 18px; cursor: pointer; font-weight: 700; }
.primary:disabled { opacity: .6; cursor: default; }
.err { color: #dc2626; font-size: .84rem; margin: 10px 0 0; }
.floaterr { text-align: center; }
</style>
