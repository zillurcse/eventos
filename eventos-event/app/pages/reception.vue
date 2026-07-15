<script setup lang="ts">
definePageMeta({ layout: 'event', middleware: 'auth' })

const reception = useReceptionStore()
const site = useSiteStore()
const api = useApi()

// Per-user meetings (loaded separately from the authed participant endpoint).
const meetings = reactive({ today: 0, upcoming: 0, hasCurrent: false })

const data = computed(() => reception.data)

const liveSessions = computed(() => (data.value?.sessions ?? []).filter(s => s.status === 'live'))
const featuredSessions = computed(() => (data.value?.sessions ?? []).filter(s => s.is_featured))

async function loadMeetings() {
  const eventUuid = site.event?.uuid || reception.data?.event?.uuid
  if (!eventUuid) return
  try {
    const res = await api<{ data: Array<{ starts_at?: string, status?: string }> }>(`/events/${eventUuid}/meetings`)
    const list = res.data ?? []
    const now = new Date()
    const endOfDay = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59)
    for (const m of list) {
      if (m.status === 'canceled') continue
      const s = m.starts_at ? new Date(m.starts_at) : null
      if (!s) continue
      if (s <= endOfDay && s >= now) meetings.today++
      else if (s > endOfDay) meetings.upcoming++
    }
    meetings.hasCurrent = list.some(m => m.status !== 'canceled')
  } catch {
    // Attendee without meeting access (403) or none yet — widget shows 0 / 0.
  }
}

onMounted(async () => {
  if (!reception.loaded) await reception.fetchReception()
  await loadMeetings()
})
</script>

<template>
  <div v-if="reception.loading && !data" class="loading">Loading reception…</div>

  <div v-else-if="data" class="reception">
    

    <!-- Row: About (main) + Meetings (rail) -->
    <div class="cols">
      <div class="main">
        <!-- Hero banner carousel -->
    <ReceptionHeroCarousel v-if="data.banners.length" :banners="data.banners" class="span2" />

    <!-- Sponsor / ad strip -->
    <ReceptionAboutCard :about="data.about" />
    <ReceptionAdStrip v-if="data.ads.strip.length" :ads="data.ads.strip" class="span2" />
        <ReceptionSessionsFeatured v-if="liveSessions.length" title="Live Sessions" :sessions="liveSessions" :limit="3" />
        <ReceptionSessionsFeatured v-if="featuredSessions.length" title="Featured Sessions" :sessions="featuredSessions" :limit="3" />
        <ReceptionPartnersFeatured v-if="data.exhibitors.length" title="Exhibitors" type="exhibitor" :partners="data.exhibitors" :limit="3" />
        <ReceptionPartnersFeatured v-if="data.sponsors.length" title="Sponsors" type="sponsor" :partners="data.sponsors" :limit="3" />
        <ReceptionSpeakersFeatured v-if="data.speakers.length" :speakers="data.speakers" :limit="4" />
      </div>

      <aside class="rail">
        <ReceptionMeetingsWidget :today="meetings.today" :upcoming="meetings.upcoming" :has-current="meetings.hasCurrent" />
        <ReceptionAdSidebar v-if="data.ads.sidebar.length" :ads="data.ads.sidebar" />
      </aside>
    </div>
  </div>

  <div v-else class="loading">This event's reception isn't available.</div>
</template>

<style scoped>
.reception { display: flex; flex-direction: column; gap: 18px; }
.cols { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; align-items: start; }
.main { display: flex; flex-direction: column; gap: 18px; min-width: 0; }
.rail { display: flex; flex-direction: column; gap: 18px; position: sticky; top: 92px; }
.loading { padding: 60px 0; text-align: center; color: #64748b; }

@media (max-width: 900px) {
  .cols { grid-template-columns: 1fr; }
  .rail { position: static; }
}
</style>
