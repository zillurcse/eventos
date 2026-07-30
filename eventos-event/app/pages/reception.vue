<script setup lang="ts">
import type { JoinConfig } from '~/stores/rooms'
import type { Meeting } from '~/stores/meetings'

definePageMeta({ layout: 'event', middleware: 'auth' })

const { isMinimal, isModern } = useAppearance()
const reception = useReceptionStore()
const meetingsStore = useMeetingsStore()

const data = computed(() => reception.data)

// Ticks so a session's live/upcoming/ended phase, and a meeting's join
// window, stay accurate (derived from real start/end times) without a page
// refresh.
const now = ref(Date.now())
let ticker: ReturnType<typeof setInterval> | null = null
onMounted(() => { ticker = setInterval(() => (now.value = Date.now()), 15_000) })
onBeforeUnmount(() => { if (ticker) clearInterval(ticker) })

function isLive(s: { starts_at: string | null, ends_at: string | null }): boolean {
  if (!s.starts_at || !s.ends_at) return false
  const start = new Date(s.starts_at).getTime()
  const end = new Date(s.ends_at).getTime()
  return now.value >= start && now.value <= end
}

const liveSessions = computed(() => (data.value?.sessions ?? []).filter(isLive))
const featuredSessions = computed(() => (data.value?.sessions ?? []).filter(s => s.is_featured))

// ── Meetings widget ──────────────────────────────────────────────────────
// Mirrors MeetingsCard's join window (MeetingController::join): joinable
// from 10 minutes before starts_at through 15 minutes past the end.
const JOIN_LEAD_MS = 10 * 60_000
const DEFAULT_DURATION_MS = 30 * 60_000
const JOIN_GRACE_MS = 15 * 60_000

function isMeetingRunning(m: Meeting): boolean {
  if (m.status !== 'confirmed' || m.source !== 'delegate' || !m.starts_at) return false
  const start = new Date(m.starts_at).getTime()
  const end = m.ends_at ? new Date(m.ends_at).getTime() : start + DEFAULT_DURATION_MS
  return now.value >= start - JOIN_LEAD_MS && now.value <= end + JOIN_GRACE_MS
}

const confirmedMeetings = computed(() => meetingsStore.meetings.filter(m => m.status === 'confirmed' && m.starts_at))
const currentMeeting = computed(() => confirmedMeetings.value.find(isMeetingRunning) ?? null)

const meetingCounts = computed(() => {
  const endOfDay = new Date(now.value)
  endOfDay.setHours(23, 59, 59, 999)
  let today = 0
  let upcoming = 0
  for (const m of confirmedMeetings.value) {
    if (isMeetingRunning(m)) continue
    const start = new Date(m.starts_at!).getTime()
    if (start < now.value) continue
    if (start <= endOfDay.getTime()) today++
    else upcoming++
  }
  return { today, upcoming }
})

const activeMeetingRoom = ref<{ config: JoinConfig, title: string } | null>(null)
async function joinCurrentMeeting() {
  if (!currentMeeting.value) return
  const cfg = await meetingsStore.join(currentMeeting.value)
  if (cfg) activeMeetingRoom.value = { config: cfg, title: cfg.title }
}

const meetingsProps = computed(() => ({
  today: meetingCounts.value.today,
  upcoming: meetingCounts.value.upcoming,
  current: currentMeeting.value,
  joining: !!currentMeeting.value && meetingsStore.joining[currentMeeting.value.id] === true,
}))

onMounted(async () => {
  await Promise.all([
    reception.loaded ? Promise.resolve() : reception.fetchReception(),
    meetingsStore.loaded ? Promise.resolve() : meetingsStore.fetchMeetings(),
  ])
})
</script>

<template>
  <div v-if="reception.loading && !data" class="loading">Loading reception…</div>

  <div
    v-else-if="data"
    class="reception"
    :class="{
      'reception--minimal': isMinimal,
      'reception--modern': isModern,
    }"
  >

    <!-- ── Minimal: single column, tighter stack ─────────────────────── -->
    <template v-if="isMinimal">
      <ReceptionHeroCarousel v-if="data.banners.length" :banners="data.banners" variant="minimal" />
      <ReceptionAboutCard :about="data.about" />
      <ReceptionAdStrip v-if="data.ads.strip.length" :ads="data.ads.strip" />
      <ReceptionMeetingsWidget v-bind="meetingsProps" @join="joinCurrentMeeting" />
      <ReceptionSessionsFeatured v-if="liveSessions.length" title="Live Sessions" :sessions="liveSessions" :limit="3" />
      <ReceptionSessionsFeatured v-if="featuredSessions.length" title="Featured Sessions" :sessions="featuredSessions" :limit="3" />
      <ReceptionPartnersFeatured v-if="data.exhibitors.length" title="Exhibitors" type="exhibitor" :partners="data.exhibitors" :limit="3" />
      <ReceptionPartnersFeatured v-if="data.sponsors.length" title="Sponsors" type="sponsor" :partners="data.sponsors" :limit="3" />
      <ReceptionSpeakersFeatured v-if="data.speakers.length" :speakers="data.speakers" :limit="4" />
      <ReceptionAdSidebar v-if="data.ads.sidebar.length" :ads="data.ads.sidebar" />
    </template>

    <!-- ── Modern: soft hero, about+meetings row, then full-width stack ─ -->
    <template v-else-if="isModern">
      <ReceptionHeroCarousel v-if="data.banners.length" :banners="data.banners" variant="modern" />
      <div class="modern-top">
        <ReceptionAboutCard :about="data.about" />
        <ReceptionMeetingsWidget v-bind="meetingsProps" @join="joinCurrentMeeting" />
      </div>
      <ReceptionAdStrip v-if="data.ads.strip.length" :ads="data.ads.strip" />
      <div class="modern-stack">
        <ReceptionSessionsFeatured v-if="liveSessions.length" title="Live Sessions" :sessions="liveSessions" :limit="3" />
        <ReceptionSessionsFeatured v-if="featuredSessions.length" title="Featured Sessions" :sessions="featuredSessions" :limit="3" />
        <ReceptionPartnersFeatured v-if="data.exhibitors.length" title="Exhibitors" type="exhibitor" :partners="data.exhibitors" :limit="3" />
        <ReceptionPartnersFeatured v-if="data.sponsors.length" title="Sponsors" type="sponsor" :partners="data.sponsors" :limit="3" />
        <ReceptionSpeakersFeatured v-if="data.speakers.length" :speakers="data.speakers" :limit="4" />
        <ReceptionAdSidebar v-if="data.ads.sidebar.length" :ads="data.ads.sidebar" />
      </div>
    </template>

    <!-- ── Advanced: main + sticky rail ──────────────────────────────── -->
    <div v-else class="cols">
      <div class="main">
        <ReceptionHeroCarousel v-if="data.banners.length" :banners="data.banners" />
        <ReceptionAboutCard :about="data.about" />
        <ReceptionAdStrip v-if="data.ads.strip.length" :ads="data.ads.strip" />
        <ReceptionSessionsFeatured v-if="liveSessions.length" title="Live Sessions" :sessions="liveSessions" :limit="3" />
        <ReceptionSessionsFeatured v-if="featuredSessions.length" title="Featured Sessions" :sessions="featuredSessions" :limit="3" />
        <ReceptionPartnersFeatured v-if="data.exhibitors.length" title="Exhibitors" type="exhibitor" :partners="data.exhibitors" :limit="3" />
        <ReceptionPartnersFeatured v-if="data.sponsors.length" title="Sponsors" type="sponsor" :partners="data.sponsors" :limit="3" />
        <ReceptionSpeakersFeatured v-if="data.speakers.length" :speakers="data.speakers" :limit="4" />
      </div>

      <aside class="rail">
        <ReceptionMeetingsWidget v-bind="meetingsProps" @join="joinCurrentMeeting" />
        <ReceptionAdSidebar v-if="data.ads.sidebar.length" :ads="data.ads.sidebar" />
      </aside>
    </div>

    <RoomsRoomStage v-if="activeMeetingRoom" :config="activeMeetingRoom.config" :title="activeMeetingRoom.title" @leave="activeMeetingRoom = null" />
  </div>

  <div v-else class="loading">This event's reception isn't available.</div>
</template>

<style scoped>
.reception { display: flex; flex-direction: column; gap: 18px; }
.reception--minimal { gap: 12px; max-width: 820px; margin: 0 auto; width: 100%; }
.reception--modern { gap: 24px; }

.cols { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; align-items: start; }
.main { display: flex; flex-direction: column; gap: 18px; min-width: 0; }
.rail { display: flex; flex-direction: column; gap: 18px; position: sticky; top: 92px; }

.modern-top {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(280px, 1fr);
  gap: 24px;
  align-items: stretch;
}
.modern-stack { display: flex; flex-direction: column; gap: 24px; }

.loading { padding: 60px 0; text-align: center; color: #64748b; }

@media (max-width: 900px) {
  .cols { grid-template-columns: 1fr; }
  .rail { position: static; }
  .modern-top { grid-template-columns: 1fr; }
}
</style>
