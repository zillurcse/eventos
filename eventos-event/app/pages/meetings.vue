<script setup lang="ts">
import type { JoinConfig } from '~/stores/rooms'
import type { Meeting } from '~/stores/meetings'

definePageMeta({ layout: 'event', middleware: 'auth' })

const store = useMeetingsStore()

const showModal = ref(false)
const active = ref<{ config: JoinConfig, title: string } | null>(null)

const showPast = ref(false)
const tz = ref(deviceTimezone())
const type = ref<'all' | 'incoming' | 'outgoing'>('all')
const selectedHosts = ref<string[]>([])
const selectedLocations = ref<string[]>([])
const selectedStatuses = ref<string[]>([])

onMounted(() => {
  if (!store.loaded) store.fetchMeetings()
  store.fetchAds()
})

const tzOptions = computed(() => {
  const base = ['UTC', 'Asia/Dhaka', 'Asia/Riyadh', 'Asia/Dubai', 'Europe/London', 'America/New_York']
  return Array.from(new Set([deviceTimezone(), ...base]))
})

function onJoin(cfg: JoinConfig & { title: string }) {
  active.value = { config: cfg, title: cfg.title }
}

const hostOptions = computed(() => {
  const names = new Set<string>()
  for (const m of store.meetings) if (m.counterpart?.name) names.add(m.counterpart.name)
  return Array.from(names).sort((a, b) => a.localeCompare(b))
})

const locationOptions = computed(() => {
  const places = new Set<string>()
  for (const m of store.meetings) if (m.location) places.add(m.location)
  return Array.from(places).sort((a, b) => a.localeCompare(b))
})

const statusOptions: Array<{ key: string, label: string }> = [
  { key: 'requested', label: 'Pending' },
  { key: 'confirmed', label: 'Accepted' },
  { key: 'rejected', label: 'Rejected' },
]

function toggle(group: 'hosts' | 'locations' | 'statuses', value: string) {
  const list = group === 'hosts' ? selectedHosts : group === 'locations' ? selectedLocations : selectedStatuses
  const i = list.value.indexOf(value)
  if (i === -1) list.value.push(value)
  else list.value.splice(i, 1)
}

function clearAll() {
  selectedHosts.value = []
  selectedLocations.value = []
  selectedStatuses.value = []
}
const hasFilters = computed(() =>
  selectedHosts.value.length > 0 || selectedLocations.value.length > 0 || selectedStatuses.value.length > 0)

const list = computed<Meeting[]>(() => {
  const now = Date.now()
  return store.meetings.filter((m: Meeting) => {
    if (!showPast.value) {
      const end = meetingEndMs(m)
      if (end !== null && end < now) return false
    }
    if (type.value !== 'all' && m.direction !== type.value) return false
    if (selectedStatuses.value.length) {
      const bucket = m.status === 'declined' || m.status === 'canceled' ? 'rejected' : m.status
      if (!selectedStatuses.value.includes(bucket)) return false
    }
    if (selectedHosts.value.length && !selectedHosts.value.includes(m.counterpart?.name || '')) return false
    if (selectedLocations.value.length && !selectedLocations.value.includes(m.location || '')) return false
    return true
  })
})

const emptyText = computed(() =>
  hasFilters.value || type.value !== 'all'
    ? 'No meetings match your filters.'
    : 'No meetings yet. Request one to start networking.')
</script>

<template>
  <div class="page">
    <div class="grid">
      <div class="col">
        <ReceptionAdStrip v-if="store.ads.length" :ads="store.ads" />

        <div class="toprow">
          <h1>Meetings ({{ list.length }})</h1>

          <label class="past">
            <span>Show past meetings</span>
            <input v-model="showPast" type="checkbox" class="sr">
            <i class="switch" :class="{ on: showPast }" />
          </label>

          <select v-model="tz" class="fselect" title="Timezone">
            <option v-for="z in tzOptions" :key="z" :value="z">{{ z.replace('_', ' ').split('/').pop() }}</option>
          </select>

          <select v-model="type" class="fselect" title="Type">
            <option value="all">Type: All</option>
            <option value="incoming">Type: Received</option>
            <option value="outgoing">Type: Sent</option>
          </select>

          <button type="button" class="new" @click="showModal = true">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
            Request a Meeting
          </button>
        </div>

        <div v-if="store.loading && !store.loaded" class="state">Loading meetings…</div>
        <div v-else-if="store.error" class="state">Couldn’t load meetings. Please try again.</div>
        <div v-else-if="!list.length" class="state">{{ emptyText }}</div>

        <div v-else class="cards">
          <MeetingsCard v-for="m in list" :key="m.id" :meeting="m" :tz="tz" @join="onJoin" />
        </div>

        <p v-if="store.joinError" class="err">{{ store.joinError }}</p>
      </div>

      <aside class="rail">
        <div class="afhead">
          <span>Advance Filter</span>
          <button v-if="hasFilters" type="button" class="clearall" @click="clearAll">Clear All</button>
        </div>

        <div class="group">
          <div class="grouphead"><span>Status</span></div>
          <div class="chips">
            <button
              v-for="s in statusOptions"
              :key="s.key"
              type="button"
              class="chip"
              :class="{ on: selectedStatuses.includes(s.key) }"
              @click="toggle('statuses', s.key)"
            >{{ s.label }}</button>
          </div>
        </div>

        <div v-if="hostOptions.length" class="group">
          <div class="grouphead"><span>Hosts</span></div>
          <div class="chips">
            <button
              v-for="h in hostOptions"
              :key="h"
              type="button"
              class="chip"
              :class="{ on: selectedHosts.includes(h) }"
              @click="toggle('hosts', h)"
            >{{ h }}</button>
          </div>
        </div>

        <div v-if="locationOptions.length" class="group">
          <div class="grouphead"><span>Location</span></div>
          <div class="chips">
            <button
              v-for="l in locationOptions"
              :key="l"
              type="button"
              class="chip"
              :class="{ on: selectedLocations.includes(l) }"
              @click="toggle('locations', l)"
            >{{ l }}</button>
          </div>
        </div>
      </aside>
    </div>

    <MeetingsNewMeetingModal v-if="showModal" @close="showModal = false" />

    <RoomsRoomStage v-if="active" :config="active.config" :title="active.title" @leave="active = null" />
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; }
.grid { display: grid; grid-template-columns: minmax(0, 1fr) 384px; gap: 24px; align-items: start; }
.col { display: flex; flex-direction: column; gap: 24px; min-width: 0; }

.toprow { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.toprow h1 { margin: 0 auto 0 0; font-size: 1.15rem; font-weight: 800; color: #1e293b; }

.past { display: inline-flex; align-items: center; gap: 10px; font-size: .86rem; color: #475569; cursor: pointer; user-select: none; }
.sr { position: absolute; opacity: 0; width: 0; height: 0; }
.switch { width: 38px; height: 20px; border-radius: 999px; background: #cbd5e1; position: relative; transition: background .15s ease; flex: 0 0 auto; }
.switch::after { content: ''; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%; background: #fff; transition: transform .15s ease; }
.switch.on { background: var(--brand-primary); }
.switch.on::after { transform: translateX(18px); }

.fselect { flex: 0 0 auto; min-width: 150px; height: 42px; border: 1px solid #e2e5eb; border-radius: 10px; padding: 0 12px; font: inherit; font-size: .86rem; color: #334155; background: #fff; }

.new { display: inline-flex; align-items: center; gap: 7px; flex: 0 0 auto; height: 42px; border: none; background: var(--brand-primary); color: #fff; border-radius: 10px; padding: 0 18px; font: inherit; font-size: .88rem; font-weight: 600; cursor: pointer; }
.new svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }

.state { background: #fff; border-radius: 14px; padding: 48px 20px; text-align: center; color: #64748b; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; align-items: stretch; }
@media (max-width: 860px) { .cards { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 560px) { .cards { grid-template-columns: 1fr; } }
.err { color: #dc2626; font-size: .86rem; margin: 0; text-align: center; }

.rail { background: #fff; border-radius: 14px; padding: 24px; box-sizing: border-box; box-shadow: 0 1px 2px rgba(15,23,42,.05); display: flex; flex-direction: column; gap: 20px; position: sticky; top: 16px; }
.afhead { display: flex; align-items: center; justify-content: space-between; font-size: .92rem; font-weight: 800; color: #1e293b; }
.clearall { border: none; background: none; color: var(--brand-primary); font-size: .8rem; font-weight: 600; cursor: pointer; }

.group { border-top: 1px solid #eef0f3; padding-top: 24px; }
.grouphead { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.grouphead span { font-size: .84rem; font-weight: 700; color: #334155; }

.chips { display: flex; flex-wrap: wrap; gap: 7px; }
.chip { border: 1px solid #e2e8f0; background: #fff; color: #475569; border-radius: 999px; padding: 6px 12px; font-size: .78rem; cursor: pointer; }
.chip.on { border-color: var(--brand-primary); color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 10%, #fff); }

@media (max-width: 920px) {
  .grid { grid-template-columns: 1fr; }
  .rail { position: static; }
}
</style>
