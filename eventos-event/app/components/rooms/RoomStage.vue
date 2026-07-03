<script setup lang="ts">
import { Room, RoomEvent, Track, type RemoteTrack, type LocalTrackPublication, type Participant } from 'livekit-client'
import type { JoinConfig } from '~/stores/rooms'

const props = defineProps<{ config: JoinConfig, title: string }>()
const emit = defineEmits<{ leave: [] }>()

interface Tile { id: string, name: string, isLocal: boolean, camOn: boolean, micOn: boolean }

const room = new Room({ adaptiveStream: true, dynacast: true })
const tiles = ref<Tile[]>([])
const videoTracks = new Map<string, Track>()        // identity → current video track (non-reactive)
const videoEls = new Map<string, HTMLVideoElement>() // identity → mounted <video>
const audioBin = ref<HTMLElement | null>(null)

const status = ref<'connecting' | 'connected' | 'error'>('connecting')
const errorMsg = ref('')
const canPublish = ref(false)
const camOn = ref(false)
const micOn = ref(false)
const activeSpeakers = ref<Set<string>>(new Set())

const count = computed(() => tiles.value.length)

function initials(name: string) {
  const p = (name || '?').trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?'
}

function upsertTile(p: Participant, isLocal = false) {
  const id = p.identity
  const existing = tiles.value.find(t => t.id === id)
  const name = p.name || (isLocal ? 'You' : id)
  if (existing) { existing.name = name }
  else tiles.value.push({ id, name, isLocal, camOn: false, micOn: false })
}

function removeTile(id: string) {
  tiles.value = tiles.value.filter(t => t.id !== id)
  videoTracks.delete(id)
  videoEls.delete(id)
}

/** Function ref on each tile's <video> — attach as soon as element + track exist. */
function setVideoEl(id: string, el: Element | null) {
  if (el) { videoEls.set(id, el as HTMLVideoElement); attachVideo(id) }
  else videoEls.delete(id)
}

function attachVideo(id: string) {
  const el = videoEls.get(id)
  const trk = videoTracks.get(id)
  if (el && trk) trk.attach(el)
}

function setTileFlag(id: string, key: 'camOn' | 'micOn', value: boolean) {
  const t = tiles.value.find(x => x.id === id)
  if (t) t[key] = value
}

function handleVideo(track: Track, id: string) {
  videoTracks.set(id, track)
  setTileFlag(id, 'camOn', true)
  nextTick(() => attachVideo(id))
}

function handleAudio(track: Track) {
  const el = track.attach() as HTMLAudioElement
  el.autoplay = true
  audioBin.value?.appendChild(el)
}

async function connect() {
  room
    .on(RoomEvent.ParticipantConnected, p => upsertTile(p))
    .on(RoomEvent.ParticipantDisconnected, p => removeTile(p.identity))
    .on(RoomEvent.TrackSubscribed, (track: RemoteTrack, _pub, participant) => {
      if (track.kind === Track.Kind.Video) handleVideo(track, participant.identity)
      else if (track.kind === Track.Kind.Audio) handleAudio(track)
    })
    .on(RoomEvent.TrackUnsubscribed, (track, _pub, participant) => {
      track.detach()
      if (track.kind === Track.Kind.Video) {
        videoTracks.delete(participant.identity)
        setTileFlag(participant.identity, 'camOn', false)
      }
    })
    .on(RoomEvent.LocalTrackPublished, (pub: LocalTrackPublication) => {
      const id = room.localParticipant.identity
      if (pub.track?.kind === Track.Kind.Video) handleVideo(pub.track, id)
      if (pub.track?.kind === Track.Kind.Audio) setTileFlag(id, 'micOn', true)
    })
    .on(RoomEvent.LocalTrackUnpublished, (pub) => {
      const id = room.localParticipant.identity
      if (pub.track?.kind === Track.Kind.Video) { videoTracks.delete(id); setTileFlag(id, 'camOn', false) }
      if (pub.track?.kind === Track.Kind.Audio) setTileFlag(id, 'micOn', false)
    })
    .on(RoomEvent.ActiveSpeakersChanged, (speakers) => {
      activeSpeakers.value = new Set(speakers.map(s => s.identity))
    })
    .on(RoomEvent.Disconnected, () => emit('leave'))

  try {
    await room.connect(props.config.url, props.config.token)
    status.value = 'connected'

    upsertTile(room.localParticipant, true)
    canPublish.value = room.localParticipant.permissions?.canPublish ?? false

    // Show anyone already in the room (and their live tracks).
    room.remoteParticipants.forEach((p) => {
      upsertTile(p)
      p.trackPublications.forEach((pub) => {
        if (pub.isSubscribed && pub.track) {
          if (pub.track.kind === Track.Kind.Video) handleVideo(pub.track, p.identity)
          else if (pub.track.kind === Track.Kind.Audio) handleAudio(pub.track)
        }
      })
    })
  } catch (e: any) {
    status.value = 'error'
    errorMsg.value = e?.message || 'Could not connect to the room.'
  }
}

async function toggleCam() {
  if (!canPublish.value) return
  const next = !camOn.value
  try { await room.localParticipant.setCameraEnabled(next); camOn.value = next } catch { /* device denied */ }
}

async function toggleMic() {
  if (!canPublish.value) return
  const next = !micOn.value
  try { await room.localParticipant.setMicrophoneEnabled(next); micOn.value = next } catch { /* device denied */ }
}

async function leave() {
  await room.disconnect()
  emit('leave')
}

onMounted(connect)
onBeforeUnmount(() => { room.disconnect() })
</script>

<template>
  <div class="stage">
    <header class="bar">
      <div class="meta">
        <span class="live"><i />LIVE</span>
        <h2>{{ title }}</h2>
        <span class="count">{{ count }} in room</span>
      </div>
      <button class="leave" type="button" @click="leave">Leave</button>
    </header>

    <div v-if="status === 'connecting'" class="notice">Connecting to the room…</div>
    <div v-else-if="status === 'error'" class="notice err">{{ errorMsg }}</div>

    <div v-show="status === 'connected'" class="grid" :style="{ '--n': Math.min(count, 3) }">
      <div v-for="t in tiles" :key="t.id" class="tile" :class="{ speaking: activeSpeakers.has(t.id) }">
        <video :ref="el => setVideoEl(t.id, el)" autoplay playsinline :muted="t.isLocal" :class="{ mirror: t.isLocal }" />
        <div v-if="!t.camOn" class="off"><span class="av">{{ initials(t.name) }}</span></div>
        <div class="label">
          <span class="nm">{{ t.name }}<template v-if="t.isLocal"> (you)</template></span>
          <svg v-if="!t.micOn" class="mute" viewBox="0 0 24 24"><path d="M4 4l16 16M9 5a3 3 0 0 1 6 0v5m-6 1a3 3 0 0 0 5 2M5 11a7 7 0 0 0 10 6m4-6a7 7 0 0 1-1 3M12 19v3" /></svg>
        </div>
      </div>
    </div>

    <footer v-if="status === 'connected'" class="controls">
      <template v-if="canPublish">
        <button class="ctl" :class="{ on: micOn }" type="button" @click="toggleMic">
          <svg viewBox="0 0 24 24"><path d="M12 2a3 3 0 0 1 3 3v6a3 3 0 0 1-6 0V5a3 3 0 0 1 3-3zM5 11a7 7 0 0 0 14 0M12 18v4" /></svg>
          <span>{{ micOn ? 'Mute' : 'Unmute' }}</span>
        </button>
        <button class="ctl" :class="{ on: camOn }" type="button" @click="toggleCam">
          <svg viewBox="0 0 24 24"><path d="M3 7h11v10H3zM14 10l7-3v10l-7-3z" /></svg>
          <span>{{ camOn ? 'Stop video' : 'Start video' }}</span>
        </button>
      </template>
      <span v-else class="viewer">You joined as a viewer — sit back and watch.</span>

      <button class="ctl danger" type="button" @click="leave">
        <svg viewBox="0 0 24 24"><path d="M16 17l5-5-5-5M21 12H9M12 19H5V5h7" /></svg>
        <span>Leave</span>
      </button>
    </footer>

    <div ref="audioBin" style="display:none" />
  </div>
</template>

<style scoped>
.stage { position: fixed; inset: 0; z-index: 100; background: #0f1115; color: #fff; display: flex; flex-direction: column; }
.bar { display: flex; align-items: center; justify-content: space-between; padding: 12px 18px; border-bottom: 1px solid #23262e; }
.meta { display: flex; align-items: center; gap: 14px; }
.meta h2 { margin: 0; font-size: 1rem; font-weight: 700; }
.live { display: inline-flex; align-items: center; gap: 6px; font-size: .7rem; font-weight: 800; letter-spacing: .5px; color: #f87171; }
.live i { width: 8px; height: 8px; border-radius: 50%; background: #ef4444; display: inline-block; animation: pulse 1.4s infinite; }
@keyframes pulse { 0%,100% { opacity: 1 } 50% { opacity: .3 } }
.count { color: #94a3b8; font-size: .82rem; }
.leave { background: #ef4444; color: #fff; border: none; border-radius: 8px; padding: 8px 16px; font-weight: 700; cursor: pointer; }

.notice { flex: 1; display: flex; align-items: center; justify-content: center; color: #94a3b8; }
.notice.err { color: #fca5a5; }

.grid { flex: 1; display: grid; grid-template-columns: repeat(var(--n, 2), 1fr); gap: 12px; padding: 16px; overflow: auto; align-content: start; }
.tile { position: relative; aspect-ratio: 16 / 9; background: #1a1d24; border-radius: 12px; overflow: hidden; border: 2px solid transparent; }
.tile.speaking { border-color: #22d3ee; }
.tile video { width: 100%; height: 100%; object-fit: cover; display: block; background: #1a1d24; }
.tile video.mirror { transform: scaleX(-1); }
.off { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: #1a1d24; }
.off .av { width: 72px; height: 72px; border-radius: 50%; background: var(--brand-primary, #6352e7); display: flex; align-items: center; justify-content: center; font-size: 1.6rem; font-weight: 800; }
.label { position: absolute; left: 10px; bottom: 10px; display: flex; align-items: center; gap: 6px; background: rgba(0,0,0,.55); padding: 4px 10px; border-radius: 999px; font-size: .8rem; }
.mute { width: 15px; height: 15px; fill: none; stroke: #fca5a5; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

.controls { display: flex; align-items: center; justify-content: center; gap: 14px; padding: 16px; border-top: 1px solid #23262e; }
.ctl { display: inline-flex; flex-direction: column; align-items: center; gap: 4px; background: #23262e; color: #fff; border: none; border-radius: 12px; padding: 10px 16px; cursor: pointer; font: inherit; font-size: .7rem; min-width: 74px; }
.ctl:hover { background: #2d313b; }
.ctl.on { background: var(--brand-primary, #6352e7); }
.ctl.danger { background: #ef4444; }
.ctl svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.viewer { color: #94a3b8; font-size: .85rem; }
</style>
