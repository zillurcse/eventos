<script setup lang="ts">
import type { JoinConfig } from '~/stores/rooms'
import type { LoungeTable, LoungeTableKind } from '~/stores/loungeTables'

definePageMeta({ layout: 'event', middleware: 'auth' })

const store = useLoungeTablesStore()
const auth = useAuthStore()

const tab = ref<LoungeTableKind>('attendee')
const active = ref<{ config: JoinConfig, title: string, tableId: string } | null>(null)

// The LiveKit identity minted for me on join — used to spot "my" seat.
const meId = computed(() => `user_${auth.user?.id ?? ''}`)
const activeTableId = computed(() => active.value?.tableId ?? '')

const tabs = computed(() => [
  { key: 'attendee' as const, label: 'Attendees', list: store.tabs.attendees },
  { key: 'exhibitor' as const, label: 'Exhibitors', list: store.tabs.exhibitors },
  { key: 'sponsor' as const, label: 'Sponsors', list: store.tabs.sponsors },
])

const list = computed<LoungeTable[]>(() => tabs.value.find(t => t.key === tab.value)?.list ?? [])

async function onJoin(table: LoungeTable) {
  const cfg = await store.join(table)
  if (cfg) active.value = { config: cfg, title: cfg.title, tableId: table.id }
}

function onLeave() {
  active.value = null
  store.fetchTables(true) // refresh seats now that we've left
}

// Keep occupancy + the live dot fresh while the page is open.
let poll: ReturnType<typeof setInterval> | null = null
onMounted(() => {
  store.fetchTables()
  poll = setInterval(() => { if (!active.value) store.fetchTables(true) }, 15000)
})
onBeforeUnmount(() => { if (poll) clearInterval(poll) })
</script>

<template>
  <div class="page">
    <div class="head">
      <h1>Lounge</h1>
      <p class="sub">Join a live video table to network.</p>
    </div>

    <div class="tabs">
      <button
        v-for="t in tabs" :key="t.key" type="button" class="tab"
        :class="{ on: tab === t.key }" @click="tab = t.key"
      >
        {{ t.label }}
        <span v-if="t.list.length" class="pill">{{ t.list.length }}</span>
      </button>
    </div>

    <div v-if="store.loading && !store.loaded" class="state">Loading lounge…</div>
    <div v-else-if="store.error" class="state">Couldn’t load the lounge. Please try again.</div>
    <div v-else-if="!store.enabled" class="state">The networking lounge isn’t open for this event yet.</div>
    <div v-else-if="!list.length" class="state">No {{ tab }} tables in the lounge yet.</div>

    <div v-else class="grid">
      <LoungeTableCard
        v-for="t in list" :key="t.id"
        :table="t" :joining="store.joining === t.id"
        :me-id="meId" :active-table-id="activeTableId"
        @join="onJoin(t)" @leave="onLeave"
      />
    </div>

    <p v-if="store.joinError" class="err">{{ store.joinError }}</p>

    <!-- Live video table -->
    <RoomsRoomStage v-if="active" :config="active.config" :title="active.title" @leave="onLeave" />
  </div>
</template>

<style scoped>
.page { max-width: 1000px; }
.head { margin-bottom: 18px; }
.head h1 { margin: 0; font-size: 1.4rem; font-weight: 800; color: #1e293b; }
.sub { margin: 4px 0 0; color: #64748b; font-size: .9rem; }

.tabs { display: flex; gap: 24px; border-bottom: 1px solid #e2e8f0; margin-bottom: 20px; }
.tab { position: relative; display: inline-flex; align-items: center; gap: 8px; border: none; background: none; padding: 0 2px 12px; font: inherit; font-size: .92rem; font-weight: 700; color: #94a3b8; cursor: pointer; margin-bottom: -1px; border-bottom: 2px solid transparent; }
.tab:hover { color: var(--brand-primary); }
.tab.on { color: var(--brand-primary); border-bottom-color: var(--brand-primary); }
.pill { min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; background: #e2e8f0; color: #64748b; font-size: .7rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
.tab.on .pill { background: color-mix(in srgb, var(--brand-primary) 15%, #fff); color: var(--brand-primary); }

.state { background: #fff; border-radius: 14px; padding: 48px 20px; text-align: center; color: #64748b; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; }
.err { color: #dc2626; font-size: .86rem; margin: 14px 0 0; text-align: center; }
</style>
