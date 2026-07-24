<script setup lang="ts">
import type { LoungeTable, LoungeOccupant } from '~/stores/loungeTables'

const props = defineProps<{
  table: LoungeTable
  joining: boolean
  meId: string
  activeTableId: string
}>()
const emit = defineEmits<{ join: [], leave: [] }>()

const seatedHere = computed(() => props.activeTableId === props.table.id)
// Small tables (<=4) render as a diamond: seats at top/left/right/bottom facing
// a shared centerpiece. Bigger tables render as two rows with a wide centerpiece bar.
const isDiamond = computed(() => props.table.capacity <= 4)

interface Seat { i: number, occupant: LoungeOccupant | null, isMe: boolean }

function seatAt(i: number): Seat {
  const occupant = props.table.occupants[i] ?? null
  return { i, occupant, isMe: !!occupant && occupant.identity === props.meId }
}

// Diamond order: top, left, right, bottom — matches the physical seat slots,
// so a 4-seat table maps 1:1 onto occupants[0..3], every one a real seat.
const DIAMOND_ORDER = ['top', 'left', 'right', 'bottom'] as const
const diamondSeats = computed(() => {
  const out: Partial<Record<typeof DIAMOND_ORDER[number], Seat>> = {}
  DIAMOND_ORDER.slice(0, props.table.capacity).forEach((pos, i) => { out[pos] = seatAt(i) })
  return out
})

const topRow = computed<Seat[]>(() => {
  const n = Math.ceil(props.table.capacity / 2)
  return Array.from({ length: n }, (_, i) => seatAt(i))
})
const bottomRow = computed<Seat[]>(() => {
  const topN = Math.ceil(props.table.capacity / 2)
  const n = props.table.capacity - topN
  return Array.from({ length: n }, (_, i) => seatAt(topN + i))
})

function onSeat(seat: Seat) {
  if (seat.occupant) {
    if (seat.isMe) emit('leave')
    return
  }
  if (!props.table.full) emit('join')
}
</script>

<template>
  <article class="seatcard" :class="{ seated: seatedHere }">
    <h3 class="stitle">{{ table.name }}</h3>

    <span class="pill" :class="{ full: table.full }">{{ table.occupied }}/{{ table.capacity }} seat available</span>

    <div class="seating">
      <template v-if="isDiamond">
        <img src="/lounge/seat-plant.svg" class="plant tl" alt="">

        <div v-if="diamondSeats.top" class="row">
          <LoungeSeatUnit :occupant="diamondSeats.top.occupant" :is-me="diamondSeats.top.isMe" :full="table.full"
            @click="onSeat(diamondSeats.top)" />
        </div>

        <div class="mid">
          <LoungeSeatUnit v-if="diamondSeats.left" :occupant="diamondSeats.left.occupant"
            :is-me="diamondSeats.left.isMe" :full="table.full" :rotate="-90" @click="onSeat(diamondSeats.left)" />

          <span class="centerpiece" :class="{ live: table.live }">
            <AppImage :src="table.image_url" :alt="table.name" />
          </span>

          <LoungeSeatUnit v-if="diamondSeats.right" :occupant="diamondSeats.right.occupant" 
            :is-me="diamondSeats.right.isMe" :full="table.full" :rotate="90" @click="onSeat(diamondSeats.right)" />
        </div>

        <div v-if="diamondSeats.bottom" class="row">
          <LoungeSeatUnit :occupant="diamondSeats.bottom.occupant" :is-me="diamondSeats.bottom.isMe" :full="table.full"
            :rotate="180" @click="onSeat(diamondSeats.bottom)" />
        </div>

        <img src="/lounge/seat-plant.svg" class="plant br" alt="">
      </template>

      <template v-else>
        <div class="row">
          <LoungeSeatUnit v-for="s in topRow" :key="'t' + s.i" :occupant="s.occupant" :is-me="s.isMe" :full="table.full"
            @click="onSeat(s)" />
        </div>

        <span class="centerpiece wide" :class="{ live: table.live }">
          <AppImage :src="table.image_url" :alt="table.name" />
        </span>

        <div class="row">
          <LoungeSeatUnit v-for="s in bottomRow" :key="'b' + s.i" :occupant="s.occupant" :is-me="s.isMe"
            :full="table.full" :rotate="180" @click="onSeat(s)" />
        </div>
      </template>
    </div>

    <button type="button" class="selectbtn" :disabled="joining || (table.full && !seatedHere)"
      @click="seatedHere ? emit('leave') : emit('join')">
      {{ seatedHere ? 'Leave table' : joining ? 'Joining…' : table.full ? 'Table full' : 'Select a seat' }}
    </button>
  </article>
</template>

<style scoped>
.seatcard {
  background: #fff;
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  text-align: center;
}

.seatcard.seated {
  box-shadow: 0 0 0 2px #16a34a, 0 12px 28px -18px rgba(22, 163, 74, .4);
}

.stitle {
  margin: 0;
  font-size: 1rem;
  font-weight: 800;
  color: #1e293b;
}

.pill {
  font-size: .78rem;
  font-weight: 700;
  color: #d97706;
  background: #fff6e0;
  padding: 7px 16px;
  border-radius: 999px;
}

.pill.full {
  color: #dc2626;
  background: #fdecec;
}

.seating {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  width: 100%;
}

.plant {
  position: absolute;
  width: 30px;
  height: auto;
  pointer-events: none;
}

.plant.tl {
  top: -2px;
  left: -4px;
}

.plant.br {
  bottom: -2px;
  right: -4px;
}

.row {
  display: flex;
  justify-content: center;
  gap: 10px;
}

.mid {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.centerpiece {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 98px;
  height: 82px;
  border-radius: 12px;
  border: 1px solid #E8E8EE;
  background-color: #F7F7FB;
  flex: 0 0 auto;
}


.centerpiece.wide {
  width: 100%;
  max-width: 224px;
  height: 82px;
}
.centerpiece img{
  width: 50px;
  height: 50px;
  border-radius: 8px;
}
.micicon {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: #fff;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.selectbtn {
  width: 100%;
  border: none;
  border-radius: 8px;
  /* padding: 12px; */
  height: 40px;
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  color: #fff;
  background: var(--brand-primary);
  cursor: pointer;
}

.selectbtn:disabled {
  opacity: .55;
  cursor: default;
}
</style>
