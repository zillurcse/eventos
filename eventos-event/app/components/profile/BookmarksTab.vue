<script setup lang="ts">
/**
 * "My Bookmarks" section of the Profile page: everything the attendee has
 * saved across the event, browsable as the same cards used on the
 * Speakers / Sessions / Exhibitors / Delegates pages (so unbookmarking here
 * behaves identically to unbookmarking there). This is the full, in-page
 * counterpart to the quick slide-over opened from the header's bookmark icon.
 */
const bookmarks = useBookmarksStore()
const speakers = useSpeakersStore()
const sessions = useSessionsStore()
const exhibitors = useExhibitorsStore()
const delegates = useDelegatesStore()

type Tab = 'speakers' | 'sessions' | 'exhibitors' | 'delegates'
const tab = ref<Tab>('speakers')

const tabs: Array<{ key: Tab, label: string }> = [
  { key: 'speakers', label: 'Speakers' },
  { key: 'sessions', label: 'Sessions' },
  { key: 'exhibitors', label: 'Exhibitors' },
  { key: 'delegates', label: 'Delegates' },
]

const savedDelegates = ref<Awaited<ReturnType<typeof delegates.resolveByIds>>>([])
const delegatesLoading = ref(false)

watch(
  [() => bookmarks.saved.delegate, tab],
  async () => {
    if (tab.value !== 'delegates') return
    const ids = Object.entries(bookmarks.saved.delegate).filter(([, on]) => on).map(([id]) => id)
    delegatesLoading.value = true
    try {
      savedDelegates.value = await delegates.resolveByIds(ids)
    } finally {
      delegatesLoading.value = false
    }
  },
  { deep: true, immediate: true },
)

onMounted(() => {
  bookmarks.fetch()
  if (!speakers.loaded && !speakers.loading) speakers.fetchSpeakers()
  if (!sessions.loaded && !sessions.loading) sessions.fetchSessions()
  if (!exhibitors.loaded && !exhibitors.loading) exhibitors.fetchExhibitors()
})

const savedSpeakers = computed(() => speakers.speakers.filter(s => bookmarks.isOn('speaker', s.id)))
const savedSessions = computed(() => sessions.sessions.filter(s => bookmarks.isOn('session', s.id)))
const savedExhibitors = computed(() => exhibitors.all.filter(e => bookmarks.isOn('exhibitor', e.id)))

const loading = computed(() => {
  if (!bookmarks.loaded) return true
  if (tab.value === 'speakers') return speakers.loading && !speakers.loaded
  if (tab.value === 'sessions') return sessions.loading && !sessions.loaded
  if (tab.value === 'exhibitors') return exhibitors.loading && !exhibitors.loaded
  return delegatesLoading.value
})

const empty = computed(() => {
  if (loading.value) return false
  if (tab.value === 'speakers') return !savedSpeakers.value.length
  if (tab.value === 'sessions') return !savedSessions.value.length
  if (tab.value === 'exhibitors') return !savedExhibitors.value.length
  return !savedDelegates.value.length
})
</script>

<template>
  <div class="bookmarks-tab">
    <nav class="tabs">
      <button
        v-for="t in tabs" :key="t.key" type="button"
        class="tab" :class="{ on: tab === t.key }" @click="tab = t.key"
      >
        {{ t.label }}
      </button>
    </nav>

    <p v-if="loading" class="state">Loading…</p>
    <p v-else-if="empty" class="state">Nothing saved here yet — tap the bookmark icon on a card to save it.</p>

    <template v-else>
      <div v-if="tab === 'speakers'" class="cards">
        <SpeakersCard v-for="s in savedSpeakers" :key="s.id" :speaker="s" />
      </div>

      <div v-else-if="tab === 'sessions'" class="sessions">
        <SessionsCard v-for="s in savedSessions" :key="s.id" :session="s" :tz="sessions.eventTimezone" />
      </div>

      <div v-else-if="tab === 'exhibitors'" class="cards exhibitors">
        <ExhibitorsCard v-for="e in savedExhibitors" :key="e.id" :exhibitor="e" />
      </div>

      <div v-else class="cards delegates">
        <DelegatesCard v-for="d in savedDelegates" :key="d.id" :delegate="d" />
      </div>
    </template>

    <SpeakersDetailModal v-if="speakers.selected" :key="speakers.selected.id" :speaker="speakers.selected" />
  </div>
</template>

<style scoped>
.tabs { display: flex; gap: 6px; border-bottom: 1px solid #eef0f3; margin-bottom: 24px; }
.tab { border: none; background: none; padding: 10px 6px; margin-right: 18px; font: inherit; font-size: .9rem; font-weight: 600; color: #94a3b8; cursor: pointer; border-bottom: 2px solid transparent; }
.tab:hover { color: #475569; }
.tab.on { color: var(--brand-primary); border-bottom-color: var(--brand-primary); font-weight: 700; }

.state { color: #94a3b8; font-size: .9rem; padding: 24px 0; text-align: center; }

.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 16px; }
.cards.exhibitors { grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); }
.cards.delegates { grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); }

.sessions { display: flex; flex-direction: column; gap: 14px; }
</style>
