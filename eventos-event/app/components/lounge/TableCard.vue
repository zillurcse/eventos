<script setup lang="ts">
import type { LoungeTable, LoungeOccupant } from '~/stores/loungeTables'

const props = defineProps<{
  table: LoungeTable
  joining: boolean
  meId: string          // 'user_<uuid>' of the current viewer
  activeTableId: string // the table this viewer is currently seated at ('' = none)
}>()
const emit = defineEmits<{ join: [], leave: [] }>()

const MAX_SEATS = 10

const layout = computed(() => props.table.design || 'round')
// Per-table accent recolors everything that reads --brand-primary; presence
// (live/self green) stays fixed so it always reads as "someone's here".
const accentStyle = computed(() => props.table.accent ? { '--brand-primary': props.table.accent } : {})

const seatedHere = computed(() => props.activeTableId === props.table.id)
const shown = computed(() => Math.min(props.table.capacity, MAX_SEATS))
const overflow = computed(() => Math.max(0, props.table.capacity - MAX_SEATS))
const fillFrac = computed(() => props.table.capacity ? Math.min(props.table.occupied / props.table.capacity, 1) : 0)

// Seat coordinates (% of the square stage) per design.
const positions = computed<{ x: number, y: number }[]>(() => {
  const n = shown.value
  if (layout.value === 'boardroom') {
    const topN = Math.ceil(n / 2)
    const rowX = (count: number) => count === 1 ? [50] : Array.from({ length: count }, (_, k) => 15 + 70 * k / (count - 1))
    const out: { x: number, y: number }[] = []
    rowX(topN).forEach(x => out.push({ x, y: 15 }))
    rowX(n - topN).forEach(x => out.push({ x, y: 85 }))
    return out
  }
  if (layout.value === 'lounge') {
    const perRow = Math.min(n, 5)
    const rows = Math.ceil(n / perRow)
    return Array.from({ length: n }, (_, k) => {
      const row = Math.floor(k / perRow)
      const inRow = Math.min(perRow, n - row * perRow)
      const col = k % perRow
      const x = inRow === 1 ? 50 : 20 + 60 * col / (inRow - 1)
      const y = rows === 1 ? 60 : 46 + row * 30
      return { x, y }
    })
  }
  const R = 39 // round
  return Array.from({ length: n }, (_, i) => {
    const ang = (-90 + (360 / n) * i) * Math.PI / 180
    return { x: 50 + R * Math.cos(ang), y: 50 + R * Math.sin(ang) }
  })
})

interface Chair { i: number, occupant: LoungeOccupant | null, isMe: boolean, x: number, y: number, low: boolean }

const chairs = computed<Chair[]>(() => positions.value.map((p, i) => {
  const occupant = props.table.occupants[i] ?? null
  return { i, occupant, isMe: !!occupant && occupant.identity === props.meId, x: p.x, y: p.y, low: p.y < 42 }
}))

function onChair(c: Chair) {
  if (c.occupant) {
    if (c.isMe) emit('leave')
    return
  }
  if (!props.table.full) emit('join')
}
</script>

<template>
  <article class="table" :class="{ live: table.live, seated: seatedHere }" :style="accentStyle">
    <header class="top" :class="{ green: table.live }">
      <span class="wave" :class="{ pulse: table.live }">
        <svg viewBox="0 0 24 24"><path d="M4 12a8 8 0 0 1 16 0M7 12a5 5 0 0 1 10 0M12 12h.01" /></svg>
      </span>
      <h3 class="name">{{ table.name }}</h3>
      <span v-if="table.live" class="livetag">LIVE</span>
      <button v-if="seatedHere" type="button" class="exit" title="Leave table" @click="emit('leave')">
        <svg viewBox="0 0 24 24"><path d="M14 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-2M9 12h11m0 0-3-3m3 3-3 3" /></svg>
      </button>
    </header>

    <div class="stage">
      <div class="ring" :class="layout">
        <!-- Centrepiece per layout -->
        <div v-if="layout === 'round'" class="board round" :style="{ '--frac': fillFrac }">
          <div class="disc">
            <AppImage :src="table.image_url" :alt="table.name" />
          </div>
          <span v-if="overflow" class="more">+{{ overflow }}</span>
        </div>
        <div v-else-if="layout === 'boardroom'" class="board boardroom">
          <AppImage :src="table.image_url" :alt="table.name" />
          <span v-if="overflow" class="more">+{{ overflow }}</span>
        </div>
        <template v-else>
          <div class="art">
            <AppImage :src="table.image_url" :alt="table.name" />
          </div>
          <div class="sofa" />
        </template>

        <!-- Chairs -->
        <div
          v-for="c in chairs" :key="c.i"
          class="chair" :class="{ taken: c.occupant, me: c.isMe, sit: !c.occupant && !table.full }"
          :style="{ left: c.x + '%', top: c.y + '%' }"
          :title="c.occupant ? c.occupant.name : (table.full ? 'Table full' : 'Sit here')"
          @click="onChair(c)"
        >
          <template v-if="c.occupant">
            <UserAvatar :src="c.occupant.avatar_url" :name="c.occupant.name" />
            <span v-if="c.isMe" class="x" title="Leave" @click.stop="emit('leave')">×</span>
            <div class="pop" :class="{ below: c.low }">
              <span class="pa">
                <UserAvatar :src="c.occupant.avatar_url" :name="c.occupant.name" />
              </span>
              <strong>{{ c.occupant.name }}</strong>
              <em :class="{ self: c.isMe }">{{ c.isMe ? 'You’re seated here' : 'Available for meeting' }}</em>
            </div>
          </template>
          <svg v-else class="plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        </div>
      </div>
    </div>

    <footer class="foot">
      <span class="count"><b>{{ table.occupied }}</b> / {{ table.capacity }} seated</span>
      <button v-if="seatedHere" type="button" class="act leave" @click="emit('leave')">Leave</button>
      <span v-else-if="joining" class="hint">Joining…</span>
      <span v-else-if="table.full" class="hint muted">Table full</span>
      <span v-else class="hint">Tap a seat to join</span>
    </footer>
  </article>
</template>

<style scoped>
.table {
  position: relative; background: #fff; border-radius: 20px; border: 1px solid #eef1f5;
  box-shadow: 0 2px 4px rgba(15,23,42,.04), 0 12px 28px -18px rgba(15,23,42,.25);
  display: flex; flex-direction: column; transition: transform .18s ease, box-shadow .18s ease;
}
.table:hover { transform: translateY(-3px); box-shadow: 0 4px 8px rgba(15,23,42,.05), 0 20px 40px -20px rgba(15,23,42,.32); }
.table.seated { border-color: transparent; box-shadow: 0 0 0 2px #16a34a, 0 18px 40px -18px rgba(22,163,74,.45); }

/* Header */
.top { display: flex; align-items: center; gap: 9px; padding: 13px 15px; border-radius: 20px 20px 0 0; background: linear-gradient(120deg, color-mix(in srgb, var(--brand-primary) 8%, #f8fafc), color-mix(in srgb, var(--brand-primary) 14%, #eef2f7)); color: #475569; }
.top.green { background: linear-gradient(120deg, #16a34a, #0f9d6b 55%, #12b981); color: #fff; box-shadow: inset 0 -1px 0 rgba(255,255,255,.15); }
.wave { display: flex; }
.wave svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; opacity: .95; }
.wave.pulse { animation: wave 2s ease-in-out infinite; }
@keyframes wave { 0%,100% { opacity: .5; transform: scale(.92); } 50% { opacity: 1; transform: scale(1.06); } }
.name { margin: 0; flex: 1; font-size: .82rem; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.livetag { font-size: .58rem; font-weight: 900; letter-spacing: 1px; background: rgba(255,255,255,.25); padding: 3px 7px; border-radius: 999px; }
.exit { border: none; background: rgba(255,255,255,.22); color: #fff; width: 27px; height: 27px; border-radius: 9px; display: flex; align-items: center; justify-content: center; cursor: pointer; flex: 0 0 auto; transition: background .15s; }
.exit:hover { background: rgba(255,255,255,.38); }
.exit svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }

/* Stage */
.stage { padding: 20px 18px 10px; }
.ring { position: relative; width: 100%; aspect-ratio: 1 / 1; max-width: 260px; margin: 0 auto; }
.ring.round { background: radial-gradient(circle at 50% 42%, color-mix(in srgb, var(--brand-primary) 8%, #fff), transparent 62%); border-radius: 50%; }

/* Round table + occupancy ring */
.board.round { position: absolute; left: 50%; top: 50%; width: 42%; aspect-ratio: 1; transform: translate(-50%,-50%); border-radius: 50%; display: grid; place-items: center; padding: 5px; background: conic-gradient(var(--brand-primary) calc(var(--frac,0) * 360deg), #e9edf3 0); }
.board.round .disc { width: 100%; height: 100%; border-radius: 50%; background: #fff; box-shadow: inset 0 2px 6px rgba(15,23,42,.08); display: grid; place-items: center; overflow: hidden; }
.board.round .disc img { width: 100%; height: 100%; object-fit: cover; }

/* Boardroom rectangular table */
.board.boardroom { position: absolute; left: 50%; top: 50%; width: 60%; height: 34%; transform: translate(-50%,-50%); border-radius: 16px; background: linear-gradient(160deg, #fff, color-mix(in srgb, var(--brand-primary) 6%, #f4f6fb)); border: 1px solid color-mix(in srgb, var(--brand-primary) 22%, #e2e8f0); box-shadow: 0 6px 16px -8px rgba(15,23,42,.28); display: grid; place-items: center; overflow: hidden; }
.board.boardroom img { width: 100%; height: 100%; object-fit: cover; }

/* Lounge: framed art + a sofa the seats sit on */
.art { position: absolute; left: 50%; top: 11%; transform: translateX(-50%); width: 26%; aspect-ratio: 1; border-radius: 14px; background: #fff; border: 1px solid color-mix(in srgb, var(--brand-primary) 20%, #e2e8f0); box-shadow: 0 4px 12px -6px rgba(15,23,42,.25); display: grid; place-items: center; overflow: hidden; }
.art img { width: 100%; height: 100%; object-fit: cover; }
.sofa { position: absolute; left: 50%; bottom: 6%; transform: translateX(-50%); width: 78%; height: 30%; border-radius: 22px 22px 16px 16px; background: linear-gradient(180deg, color-mix(in srgb, var(--brand-primary) 20%, #eef1f6), color-mix(in srgb, var(--brand-primary) 12%, #e6eaf1)); box-shadow: inset 0 6px 0 -3px color-mix(in srgb, var(--brand-primary) 30%, #fff), 0 6px 14px -8px rgba(15,23,42,.3); }

.mono { font-size: 1.05rem; font-weight: 800; color: color-mix(in srgb, var(--brand-primary) 70%, #64748b); }
.more { position: absolute; bottom: -6px; left: 50%; transform: translateX(-50%); font-size: .64rem; font-weight: 800; color: #64748b; background: #fff; padding: 2px 8px; border-radius: 999px; box-shadow: 0 2px 6px rgba(15,23,42,.14); }

/* Chairs (shared across layouts) */
.chair { position: absolute; width: 15%; aspect-ratio: 1; transform: translate(-50%,-50%); border-radius: 50%; display: grid; place-items: center; transition: transform .14s ease, box-shadow .14s ease; z-index: 2; }
.chair.sit { cursor: pointer; background: #fff; border: 2px dashed #cdd5e0; }
.chair.sit:hover { transform: translate(-50%,-50%) scale(1.12); border-color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 10%, #fff); box-shadow: 0 6px 16px -6px rgba(15,23,42,.3); }
.chair.sit:hover .plus { stroke: var(--brand-primary); }
.chair.taken { background: #fff; box-shadow: 0 4px 12px -4px rgba(15,23,42,.3); cursor: default; }
.chair.taken:hover { transform: translate(-50%,-50%) scale(1.1); z-index: 6; }
.chair.me { cursor: pointer; box-shadow: 0 0 0 2px #16a34a, 0 4px 12px -4px rgba(22,163,74,.5); }
.chair img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.ini { width: 100%; height: 100%; border-radius: 50%; display: grid; place-items: center; font-size: .8rem; font-weight: 800; color: #fff; background: linear-gradient(135deg, color-mix(in srgb, var(--brand-primary) 85%, #fff), var(--brand-primary)); }
.plus { width: 42%; height: 42%; fill: none; stroke: #b6bec9; stroke-width: 2.4; stroke-linecap: round; }
.x { position: absolute; top: -5px; right: -5px; width: 18px; height: 18px; border-radius: 50%; background: #ef4444; color: #fff; font-size: .82rem; line-height: 16px; text-align: center; font-weight: 700; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,.3); }

/* Hover profile card */
.pop { position: absolute; bottom: calc(100% + 12px); left: 50%; transform: translateX(-50%) translateY(4px); width: 172px; background: #fff; border-radius: 14px; box-shadow: 0 16px 40px -12px rgba(15,23,42,.4); padding: 13px; display: flex; flex-direction: column; align-items: center; gap: 5px; text-align: center; opacity: 0; pointer-events: none; transition: opacity .16s ease, transform .16s ease; z-index: 20; }
.pop.below { bottom: auto; top: calc(100% + 12px); transform: translateX(-50%) translateY(-4px); }
.chair.taken:hover .pop { opacity: 1; transform: translateX(-50%) translateY(0); }
.pop::after { content: ''; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); border: 7px solid transparent; border-top-color: #fff; }
.pop.below::after { top: auto; bottom: 100%; border-top-color: transparent; border-bottom-color: #fff; }
.pop .pa { width: 56px; height: 56px; border-radius: 50%; overflow: hidden; background: linear-gradient(135deg, color-mix(in srgb, var(--brand-primary) 85%, #fff), var(--brand-primary)); display: grid; place-items: center; font-weight: 800; color: #fff; }
.pop .pa img { width: 100%; height: 100%; object-fit: cover; }
.pop strong { font-size: .88rem; color: #1e293b; line-height: 1.2; }
.pop em { font-style: normal; font-size: .72rem; font-weight: 700; color: var(--brand-primary); display: inline-flex; align-items: center; gap: 5px; }
.pop em::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.pop em.self { color: #16a34a; }

/* Footer */
.foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 13px 16px; }
.count { font-size: .8rem; color: #94a3b8; }
.count b { color: #334155; font-weight: 800; }
.hint { font-size: .78rem; font-weight: 700; color: var(--brand-primary); }
.hint.muted { color: #94a3b8; }
.act { border: none; border-radius: 999px; padding: 8px 18px; font: inherit; font-size: .82rem; font-weight: 700; cursor: pointer; }
.act.leave { background: #fee2e2; color: #dc2626; transition: background .15s; }
.act.leave:hover { background: #fecaca; }
</style>
