<script setup lang="ts">
import type { ContestPhase } from '~/stores/contests'

definePageMeta({ layout: 'event', middleware: 'auth' })

const contests = useContestsStore()

const FILTERS: { key: ContestPhase | 'all', label: string }[] = [
  { key: 'ongoing', label: 'Live now' },
  { key: 'upcoming', label: 'Upcoming' },
  { key: 'ended', label: 'Ended' },
  { key: 'all', label: 'All' },
]

const emptyLabel = computed(() => {
  if (!contests.contests.length) return 'No contests have been announced yet. Check back soon.'
  if (contests.filter === 'ongoing') return 'No contest is running right now.'
  if (contests.filter === 'upcoming') return 'Nothing coming up just yet.'
  if (contests.filter === 'ended') return 'No contest has finished yet.'
  return 'No contests yet.'
})

onMounted(() => { if (!contests.loaded) contests.fetchContests() })
</script>

<template>
  <div>
    <div class="head">
      <h1>Contests</h1>
      <p class="sub">Take part, collect points and win.</p>
    </div>

    <div class="filters">
      <button
        v-for="f in FILTERS" :key="f.key"
        type="button"
        class="chip"
        :class="{ on: contests.filter === f.key }"
        @click="contests.filter = f.key"
      >
        {{ f.label }}
        <span class="n">{{ contests.counts[f.key] }}</span>
      </button>
    </div>

    <div v-if="contests.loading && !contests.loaded" class="state">Loading contests…</div>
    <div v-else-if="contests.error" class="state">Couldn’t load contests. Please try again.</div>
    <div v-else-if="!contests.shown.length" class="state">{{ emptyLabel }}</div>

    <div v-else class="grid">
      <ContestsContestCard v-for="c in contests.shown" :key="c.id" :contest="c" />
    </div>
  </div>
</template>

<style scoped>
.head { margin-bottom: 16px; }
.head h1 { margin: 0; font-size: 1.4rem; font-weight: 800; color: #1e293b; }
.sub { margin: 4px 0 0; color: #64748b; font-size: .9rem; }
.state { padding: 60px 0; text-align: center; color: #64748b; }

.filters { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
.chip {
  display: inline-flex; align-items: center; gap: 7px; background: #fff; border: 1px solid #e2e8f0;
  border-radius: 999px; padding: 7px 14px; font: inherit; font-size: .82rem; font-weight: 600;
  color: #475569; cursor: pointer;
}
.chip:hover { border-color: var(--brand-primary); color: var(--brand-primary); }
.chip.on { background: var(--brand-primary); border-color: var(--brand-primary); color: #fff; }
.chip .n {
  min-width: 18px; padding: 0 5px; border-radius: 999px; background: #f1f5f9; color: #64748b;
  font-size: .72rem; font-weight: 700; text-align: center;
}
.chip.on .n { background: rgba(255,255,255,.25); color: #fff; }

.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 18px; }
</style>
