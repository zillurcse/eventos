<script setup lang="ts">
import type { Contest } from '~/stores/contests'

/** One contest in the Contests grid. Links through to the contest page. */
const props = defineProps<{ contest: Contest }>()

const PHASE_LABEL = { ongoing: 'Live now', upcoming: 'Upcoming', ended: 'Ended' } as const

const when = computed(() => contestWindow(props.contest.starts_at, props.contest.ends_at))

/** The one line that tells the attendee what to do next. */
const status = computed(() => {
  const c = props.contest
  if (c.phase === 'upcoming') return `Opens ${contestWhen(c.starts_at)}`
  if (c.phase === 'ended') return c.my_entry_count ? 'You took part — see the winners' : 'Results are in'
  if (c.my_entry_count && !c.allow_multiple_entries) return 'You’ve entered'
  if (c.my_entry_count) return `${c.my_entry_count} ${c.my_entry_count === 1 ? 'entry' : 'entries'} submitted`
  return c.contest_type === 'entry' ? 'Post your entry' : 'Add your response'
})
</script>

<template>
  <NuxtLink :to="`/contest/${contest.id}`" class="card">
    <div class="banner">
      <AppImage :src="contest.banner_url" :alt="contest.title" />
      <span class="phase" :class="contest.phase">{{ PHASE_LABEL[contest.phase] }}</span>
    </div>

    <div class="body">
      <h3>{{ contest.title }}</h3>
      <p v-if="contest.description" class="desc">{{ contest.description }}</p>

      <div class="meta">
        <span class="pill">{{ contest.contest_type === 'entry' ? 'Entry contest' : 'Response contest' }}</span>
        <span v-if="contest.points" class="pill pts">+{{ contest.points }} pts</span>
      </div>

      <p v-if="when" class="when">{{ when }}</p>

      <div class="foot">
        <span v-if="contest.can_see_others_entries" class="count">
          {{ contest.entry_count }} {{ contest.entry_count === 1 ? 'entry' : 'entries' }}
        </span>
        <span class="cta" :class="{ live: contest.phase === 'ongoing' }">{{ status }}</span>
      </div>
    </div>
  </NuxtLink>
</template>

<style scoped>
.card {
  background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 2px rgba(15,23,42,.05);
  display: flex; flex-direction: column; text-decoration: none; color: inherit; transition: box-shadow .15s, transform .15s;
}
.card:hover { box-shadow: 0 6px 18px rgba(15,23,42,.09); transform: translateY(-2px); }

.banner { position: relative; aspect-ratio: 1036 / 350; background: #eef0f3; }
.phase {
  position: absolute; top: 10px; left: 10px; padding: 4px 10px; border-radius: 999px;
  font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: #fff;
  background: rgba(15,23,42,.7);
}
.phase.ongoing { background: #16a34a; }
.phase.upcoming { background: #2563eb; }

.body { padding: 14px 16px 16px; display: flex; flex-direction: column; flex: 1; }
.body h3 { margin: 0 0 6px; font-size: 1rem; font-weight: 700; color: #1e293b; }
.desc {
  margin: 0 0 10px; color: #64748b; font-size: .84rem; line-height: 1.45;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}

.meta { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
.pill { background: #f1f5f9; color: #475569; border-radius: 999px; padding: 3px 10px; font-size: .72rem; font-weight: 700; }
.pill.pts { background: color-mix(in srgb, var(--brand-primary) 12%, #fff); color: var(--brand-primary); }

.when { margin: 0 0 12px; color: #94a3b8; font-size: .78rem; font-weight: 600; }

.foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: auto; }
.count { color: #94a3b8; font-size: .78rem; font-weight: 600; }
.cta { margin-left: auto; color: #64748b; font-size: .8rem; font-weight: 700; }
.cta.live { color: var(--brand-primary); }
</style>
