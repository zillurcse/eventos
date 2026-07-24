<script setup lang="ts">
import type { Contest } from '~/stores/contests'

/** One contest in the Contests grid. Links through to the contest page. */
const props = defineProps<{ contest: Contest }>()

// Countdown ticks off a shared clock ref so every card re-renders together,
// refreshed every 30s — plenty for a days/hours/minutes display.
const now = ref(Date.now())
let timer: ReturnType<typeof setInterval> | null = null
onMounted(() => { timer = setInterval(() => { now.value = Date.now() }, 30000) })
onBeforeUnmount(() => { if (timer) clearInterval(timer) })

const countdownTarget = computed(() => props.contest.phase === 'upcoming' ? props.contest.starts_at : props.contest.ends_at)
const countdown = computed(() => contestCountdown(countdownTarget.value, now.value))

const statusLabel = computed(() => {
  const c = props.contest
  if (c.phase === 'ended') return 'Contest ended'
  if (c.phase === 'upcoming') return 'Contest starts in'
  return 'Contest ends in'
})

const hasWinner = computed(() => (props.contest.winners?.length ?? 0) > 0)
</script>

<template>
  <NuxtLink :to="`/contest/${contest.id}`" class="card">
    <div class="banner">
      <AppImage :src="contest.banner_url" :alt="contest.title" />
    </div>

    <div class="body">
      <h3>{{ contest.title }}</h3>
      <p v-if="contest.can_see_others_entries" class="entries">
        {{ contest.entry_count }} {{ contest.entry_count === 1 ? 'Entry' : 'Entries' }}
      </p>

      <p class="status">{{ statusLabel }}</p>

      <div v-if="contest.phase !== 'ended' && countdown" class="countdown">
        <div class="cbox"><strong>{{ countdown.days }}</strong><span>days</span></div>
        <div class="cbox"><strong>{{ countdown.hours }}</strong><span>hours</span></div>
        <div class="cbox"><strong>{{ countdown.mins }}</strong><span>mins</span></div>
      </div>

      <div v-else-if="contest.phase === 'ended'" class="endedpill">
        <svg viewBox="0 0 24 24">
          <circle cx="12" cy="13" r="8" />
          <path d="M12 9v4l3 2M10 2h4M12 2v3" />
        </svg>
        <span>{{ hasWinner ? 'Winner has been announced' : 'Winner has to be announced' }}</span>
      </div>
    </div>
  </NuxtLink>
</template>

<style scoped>
.card {
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  text-decoration: none;
  color: inherit;
  transition: box-shadow .15s, transform .15s;
  padding: 0;
}

.card:hover {
  box-shadow: 0 6px 18px rgba(15, 23, 42, .09);
  transform: translateY(-2px);
}

.banner {
  position: relative;
  min-height: 160px;
  background: #eef0f3;
}
.banner img{
  min-height: 160px;
  max-height: 160px;
}
.body {
  padding: 16px;
  display: flex;
  flex-direction: column;
}

.body h3 {
  margin: 0;
  font-size: 1.05rem;
  line-height: 1.2;
  font-weight: 800;
  color: #1e293b;
  margin-bottom: 5px;
}

.entries {
  margin: 0;
  color: #64748b;
  font-size: .88rem;
  line-height: 1.2;
}

.status {
  margin: 6px 0 2px;
  color: #64748b;
  font-size: .88rem;
}

.countdown {
  display: flex;
  gap: 10px;
}

.cbox {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 5px 6px;
}

.cbox strong {
  font-size: 1.05rem;
  line-height: 1.2;
  font-weight: 500;
  color: #1e293b;
}

.cbox span {
  font-size: .78rem;
  line-height: 1.2;
  color: #94a3b8;
}

.endedpill {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #f4f4fb;
  border-radius: 8px;
  padding: 12px 14px;
  color: #334155;
  font-size: .88rem;
  font-weight: 600;
  max-height: 40px;
}

.endedpill svg {
  width: 20px;
  height: 20px;
  flex: 0 0 auto;
  fill: none;
  stroke: var(--brand-primary);
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}
</style>
