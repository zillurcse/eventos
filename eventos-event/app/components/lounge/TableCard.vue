<script setup lang="ts">
import type { LoungeTable, LoungeOccupant } from '~/stores/loungeTables'

const props = defineProps<{
  table: LoungeTable
  joining: boolean
  meId: string          // 'user_<uuid>' of the current viewer
  activeTableId: string // the table this viewer is currently seated at ('' = none)
}>()
const emit = defineEmits<{ join: [], leave: [] }>()

const MAX_SEATS = 8

function initials(name?: string | null) {
  const p = (name || '?').trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?'
}

const seatedHere = computed(() => props.activeTableId === props.table.id)
const shown = computed(() => Math.min(props.table.capacity, MAX_SEATS))
const overflow = computed(() => Math.max(0, props.table.capacity - MAX_SEATS))

interface Chair { i: number, occupant: LoungeOccupant | null, isMe: boolean }

// Occupants fill chairs in order; the rest are empty (clickable) seats.
const chairs = computed<Chair[]>(() =>
  Array.from({ length: shown.value }, (_, i) => {
    const occupant = props.table.occupants[i] ?? null
    return { i, occupant, isMe: !!occupant && occupant.identity === props.meId }
  }))

const leftChairs = computed(() => chairs.value.filter(c => c.i % 2 === 0))
const rightChairs = computed(() => chairs.value.filter(c => c.i % 2 === 1))

function onChair(c: Chair) {
  if (c.occupant) {
    if (c.isMe) emit('leave')       // clicking your own seat leaves the table
    return
  }
  if (!props.table.full) emit('join') // empty chair → take a seat
}
</script>

<template>
  <article class="table" :class="{ live: table.live, seated: seatedHere }">
    <header class="top" :class="{ green: table.live }">
      <svg class="wave" viewBox="0 0 24 24"><path d="M4 12a8 8 0 0 1 16 0M7 12a5 5 0 0 1 10 0M12 12h.01" /></svg>
      <h3 class="name">{{ table.name }}</h3>
      <button v-if="seatedHere" type="button" class="exit" title="Leave table" @click="emit('leave')">
        <svg viewBox="0 0 24 24"><path d="M14 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-2M9 12h11m0 0-3-3m3 3-3 3" /></svg>
      </button>
    </header>

    <div class="stage">
      <div class="col">
        <div
          v-for="c in leftChairs" :key="c.i"
          class="chair" :class="{ taken: c.occupant, me: c.isMe, sit: !c.occupant && !table.full }"
          :title="c.occupant ? c.occupant.name : (table.full ? 'Table full' : 'Sit here')"
          @click="onChair(c)"
        >
          <template v-if="c.occupant">
            <img v-if="c.occupant.avatar_url" :src="c.occupant.avatar_url" :alt="c.occupant.name">
            <span v-else class="ini">{{ initials(c.occupant.name) }}</span>
            <span v-if="c.isMe" class="x" title="Leave" @click.stop="emit('leave')">×</span>
            <div class="pop">
              <span class="pa">
                <img v-if="c.occupant.avatar_url" :src="c.occupant.avatar_url" :alt="c.occupant.name">
                <span v-else>{{ initials(c.occupant.name) }}</span>
              </span>
              <strong>{{ c.occupant.name }}</strong>
              <em>{{ c.isMe ? 'You’re seated here' : 'Available for meeting' }}</em>
            </div>
          </template>
          <svg v-else class="plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        </div>
      </div>

      <div class="board">
        <img v-if="table.image_url" :src="table.image_url" :alt="table.name">
        <span v-else class="mono">{{ initials(table.name) }}</span>
        <span v-if="overflow" class="more">+{{ overflow }}</span>
      </div>

      <div class="col">
        <div
          v-for="c in rightChairs" :key="c.i"
          class="chair" :class="{ taken: c.occupant, me: c.isMe, sit: !c.occupant && !table.full }"
          :title="c.occupant ? c.occupant.name : (table.full ? 'Table full' : 'Sit here')"
          @click="onChair(c)"
        >
          <template v-if="c.occupant">
            <img v-if="c.occupant.avatar_url" :src="c.occupant.avatar_url" :alt="c.occupant.name">
            <span v-else class="ini">{{ initials(c.occupant.name) }}</span>
            <span v-if="c.isMe" class="x" title="Leave" @click.stop="emit('leave')">×</span>
            <div class="pop">
              <span class="pa">
                <img v-if="c.occupant.avatar_url" :src="c.occupant.avatar_url" :alt="c.occupant.name">
                <span v-else>{{ initials(c.occupant.name) }}</span>
              </span>
              <strong>{{ c.occupant.name }}</strong>
              <em>{{ c.isMe ? 'You’re seated here' : 'Available for meeting' }}</em>
            </div>
          </template>
          <svg v-else class="plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        </div>
      </div>
    </div>

    <footer class="foot">
      <span class="count">{{ table.occupied }}/{{ table.capacity }} seated</span>
      <button v-if="seatedHere" type="button" class="act leave" @click="emit('leave')">Leave</button>
      <span v-else-if="joining" class="hint">Joining…</span>
      <span v-else-if="table.full" class="hint">Table full</span>
      <span v-else class="hint">Tap a seat to join</span>
    </footer>
  </article>
</template>

<style scoped>
.table { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(15,23,42,.08); display: flex; flex-direction: column; }
.table.seated { box-shadow: 0 0 0 2px #16a34a, 0 6px 20px rgba(22,163,74,.18); }

.top { display: flex; align-items: center; gap: 8px; padding: 12px 14px; background: #f8fafc; color: #475569; }
.top.green { background: #16a34a; color: #fff; }
.wave { width: 18px; height: 18px; flex: 0 0 auto; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; opacity: .9; }
.name { margin: 0; flex: 1; font-size: .82rem; font-weight: 800; letter-spacing: .4px; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.exit { border: none; background: rgba(255,255,255,.2); color: #fff; width: 26px; height: 26px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; flex: 0 0 auto; }
.exit svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }

.stage { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 16px; padding: 26px 12px; }
.col { display: flex; flex-direction: column; gap: 18px; align-items: center; }

.chair { position: relative; width: 48px; height: 48px; border-radius: 13px; display: flex; align-items: center; justify-content: center; background: #eef1f5; border: 1px solid #e2e6ec; transition: transform .12s, box-shadow .12s; }
.chair.sit { cursor: pointer; border-style: dashed; border-color: #cbd5e1; }
.chair.sit:hover { transform: translateY(-2px); border-color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 8%, #fff); box-shadow: 0 4px 12px rgba(15,23,42,.1); }
.chair.sit:hover .plus { stroke: var(--brand-primary); }
.chair.taken { background: color-mix(in srgb, var(--brand-primary) 14%, #fff); border-color: color-mix(in srgb, var(--brand-primary) 35%, #fff); overflow: visible; }
.chair.taken:hover { z-index: 5; }
.chair.me { border-color: #16a34a; cursor: pointer; }
.chair img { width: 100%; height: 100%; object-fit: cover; border-radius: 12px; }
.ini { font-size: .82rem; font-weight: 800; color: color-mix(in srgb, var(--brand-primary) 70%, #334155); }
.plus { width: 20px; height: 20px; fill: none; stroke: #b6bec9; stroke-width: 2; stroke-linecap: round; }

.x { position: absolute; top: -6px; right: -6px; width: 18px; height: 18px; border-radius: 50%; background: #ef4444; color: #fff; font-size: .8rem; line-height: 16px; text-align: center; font-weight: 700; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,.25); }

/* Hover profile card ("Available for meeting") */
.pop { position: absolute; bottom: calc(100% + 10px); left: 50%; transform: translateX(-50%) scale(.96); transform-origin: bottom center; width: 168px; background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(15,23,42,.22); padding: 12px; display: flex; flex-direction: column; align-items: center; gap: 4px; text-align: center; opacity: 0; pointer-events: none; transition: opacity .14s, transform .14s; z-index: 20; }
.chair.taken:hover .pop { opacity: 1; transform: translateX(-50%) scale(1); }
.pop::after { content: ''; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); border: 7px solid transparent; border-top-color: #fff; }
.pop .pa { width: 54px; height: 54px; border-radius: 50%; overflow: hidden; background: color-mix(in srgb, var(--brand-primary) 14%, #fff); display: flex; align-items: center; justify-content: center; font-weight: 800; color: color-mix(in srgb, var(--brand-primary) 70%, #334155); }
.pop .pa img { width: 100%; height: 100%; object-fit: cover; }
.pop strong { font-size: .86rem; color: #1e293b; line-height: 1.2; }
.pop em { font-style: normal; font-size: .72rem; font-weight: 600; color: #16a34a; }

.board { position: relative; width: 66px; min-height: 110px; border-radius: 999px; border: 2px solid #e2e8f0; background: #fafbfc; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.board img { width: 100%; height: 100%; object-fit: contain; padding: 8px; }
.mono { font-size: 1rem; font-weight: 800; color: color-mix(in srgb, var(--brand-primary) 70%, #64748b); }
.more { position: absolute; bottom: 6px; left: 50%; transform: translateX(-50%); font-size: .66rem; font-weight: 700; color: #94a3b8; background: #fff; padding: 1px 6px; border-radius: 999px; border: 1px solid #e2e8f0; }

.foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 14px; border-top: 1px solid #eef0f3; }
.count { font-size: .78rem; font-weight: 600; color: #94a3b8; }
.hint { font-size: .78rem; font-weight: 600; color: var(--brand-primary); }
.act { border: none; border-radius: 999px; padding: 9px 20px; font: inherit; font-size: .84rem; font-weight: 700; cursor: pointer; }
.act.leave { background: #fee2e2; color: #dc2626; }
</style>
