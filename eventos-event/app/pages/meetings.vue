<script setup lang="ts">
import type { JoinConfig } from '~/stores/rooms'
import type { Meeting } from '~/stores/meetings'

definePageMeta({ layout: 'event', middleware: 'auth' })

const store = useMeetingsStore()

const tab = ref<'pending' | 'approved' | 'rejected'>('pending')
const showModal = ref(false)
const active = ref<{ config: JoinConfig, title: string } | null>(null)

function onJoin(cfg: JoinConfig & { title: string }) {
  active.value = { config: cfg, title: cfg.title }
}

onMounted(() => { if (!store.loaded) store.fetchMeetings() })

const tabs = computed(() => [
  { key: 'pending' as const, label: 'Pending', count: store.pending.length },
  { key: 'approved' as const, label: 'Approved', count: store.approved.length },
  { key: 'rejected' as const, label: 'Rejected', count: store.rejected.length },
])

const list = computed<Meeting[]>(() => {
  if (tab.value === 'approved') return store.approved
  if (tab.value === 'rejected') return store.rejected
  return store.pending
})

const emptyText = computed(() => ({
  pending: 'No pending meeting requests. Send one to start networking.',
  approved: 'No confirmed meetings yet.',
  rejected: 'No declined or canceled meetings.',
}[tab.value]))
</script>

<template>
  <div class="page">
    <div class="head">
      <div>
        <h1>Meetings</h1>
        <p class="sub">Your meeting requests.</p>
      </div>
      <button type="button" class="new" @click="showModal = true">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        Request a meeting
      </button>
    </div>

    <div class="tabs">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        class="tab"
        :class="{ on: tab === t.key }"
        @click="tab = t.key"
      >
        {{ t.label }}
        <span v-if="t.count" class="pill">{{ t.count }}</span>
      </button>
    </div>

    <div v-if="store.loading && !store.loaded" class="state">Loading meetings…</div>
    <div v-else-if="store.error" class="state">Couldn’t load meetings. Please try again.</div>
    <div v-else-if="!list.length" class="state">{{ emptyText }}</div>

    <div v-else class="cards">
      <MeetingsCard v-for="m in list" :key="m.id" :meeting="m" @join="onJoin" />
    </div>

    <p v-if="store.joinError" class="err">{{ store.joinError }}</p>

    <MeetingsNewMeetingModal v-if="showModal" @close="showModal = false" />

    <!-- Live one-to-one video room -->
    <RoomsRoomStage v-if="active" :config="active.config" :title="active.title" @leave="active = null" />
  </div>
</template>

<style scoped>
.page { max-width: 900px; }
.head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
.head h1 { margin: 0; font-size: 1.4rem; font-weight: 800; color: #1e293b; }
.sub { margin: 4px 0 0; color: #64748b; font-size: .9rem; }
.new { display: inline-flex; align-items: center; gap: 7px; flex: 0 0 auto; border: none; background: var(--brand-primary); color: #fff; border-radius: 11px; padding: 11px 16px; font: inherit; font-size: .88rem; font-weight: 600; cursor: pointer; }
.new svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }

.tabs { display: flex; gap: 4px; background: #fff; border-radius: 12px; padding: 5px; box-shadow: 0 1px 2px rgba(15,23,42,.05); margin-bottom: 18px; }
.tab { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 7px; border: none; background: none; border-radius: 9px; padding: 10px; font: inherit; font-size: .88rem; font-weight: 600; color: #64748b; cursor: pointer; }
.tab:hover { color: var(--brand-primary); }
.tab.on { background: color-mix(in srgb, var(--brand-primary) 12%, #fff); color: var(--brand-primary); }
.pill { min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; background: var(--brand-primary); color: #fff; font-size: .7rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
.tab:not(.on) .pill { background: #cbd5e1; }

.state { background: #fff; border-radius: 14px; padding: 48px 20px; text-align: center; color: #64748b; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
.err { color: #dc2626; font-size: .86rem; margin: 14px 0 0; text-align: center; }
</style>
