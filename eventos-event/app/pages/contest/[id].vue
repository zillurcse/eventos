<script setup lang="ts">
definePageMeta({ layout: 'event', middleware: 'auth' })

const route = useRoute()
const contests = useContestsStore()
const id = route.params.id as string

const c = computed(() => contests.current)

/** How the entry list is titled depends on who is allowed to see it. */
const listTitle = computed(() => {
  if (!c.value) return 'Entries'
  if (contests.mineOnly) return 'My entries'
  if (!c.value.can_see_others_entries) return 'My entries'
  return c.value.contest_type === 'entry' ? 'Entries' : 'Responses'
})

const listEmpty = computed(() => {
  if (!c.value) return ''
  if (contests.mineOnly || !c.value.can_see_others_entries) {
    return c.value.phase === 'ongoing'
      ? 'You haven’t entered yet.'
      : 'You didn’t take part in this one.'
  }
  return c.value.phase === 'upcoming' ? 'Entries open when the contest starts.' : 'No entries yet — be the first.'
})

const remaining = computed(() =>
  c.value?.phase === 'ongoing' ? timeLeft(c.value.ends_at) : '',
)

async function load() {
  await contests.fetchContest(id)
  if (contests.current) await contests.fetchEntries(id)
}

onMounted(load)

// Leaving the page shouldn't leave a stale contest behind for the next one.
onBeforeUnmount(() => {
  contests.current = null
  contests.entries = []
  contests.comments = {}
  contests.mineOnly = false
  contests.sort = 'recent'
})
</script>

<template>
  <div>
    <NuxtLink to="/contests" class="back">
      <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6" /></svg>
      All contests
    </NuxtLink>

    <div v-if="contests.loading && !c" class="state">Loading contest…</div>
    <div v-else-if="!c" class="state">This contest is no longer available.</div>

    <template v-else>
      <header class="hero">
        <div class="banner">
          <AppImage :src="c.banner_url" :alt="c.title" />
          <span class="phase" :class="c.phase">
            {{ c.phase === 'ongoing' ? 'Live now' : c.phase === 'upcoming' ? 'Upcoming' : 'Ended' }}
          </span>
        </div>

        <div class="intro">
          <h1>{{ c.title }}</h1>
          <p v-if="c.caption" class="caption">{{ c.caption }}</p>

          <div class="facts">
            <span class="pill">{{ c.contest_type === 'entry' ? 'Entry contest' : 'Response contest' }}</span>
            <span v-if="c.points" class="pill pts">+{{ c.points }} pts per {{ c.contest_type === 'entry' ? 'entry' : 'response' }}</span>
            <span v-if="c.winning_points" class="pill pts">{{ c.winning_points }} pts to win</span>
            <span v-if="remaining" class="pill live">{{ remaining }}</span>
          </div>

          <p v-if="contestWindow(c.starts_at, c.ends_at)" class="when">
            {{ contestWindow(c.starts_at, c.ends_at) }}
          </p>

          <p v-if="c.description" class="desc">{{ c.description }}</p>

          <a v-if="c.description_file_url" :href="c.description_file_url" target="_blank" rel="noopener" class="file">
            <svg viewBox="0 0 24 24"><path d="M14 3v5h5M14 3H6v18h12V8z" /></svg>
            {{ c.description_file_name || 'Contest details' }}
          </a>

          <p class="rules">
            {{ c.winner_chooser === 'most_likes'
              ? `The ${c.winner_number} most-liked ${c.contest_type === 'entry' ? 'entries' : 'responses'} win.`
              : `The organizer picks ${c.winner_number} winner${c.winner_number === 1 ? '' : 's'}.` }}
            <template v-if="c.moderated"> Entries are reviewed before they appear.</template>
            <template v-if="!c.can_see_others_entries"> Entries stay private until the winners are announced.</template>
          </p>
        </div>
      </header>

      <ContestsWinners v-if="c.phase === 'ended' && c.winners?.length" :contest="c" :winners="c.winners" />

      <ContestsEntryComposer v-if="c.can_enter" :contest="c" />
      <p v-else-if="c.phase === 'ongoing' && c.my_entry_count" class="notice">
        You’ve already entered this contest. Good luck!
      </p>
      <p v-else-if="c.phase === 'upcoming'" class="notice">
        This contest opens {{ contestWhen(c.starts_at) }}.
      </p>

      <section class="entries">
        <div class="bar">
          <h2>{{ listTitle }}</h2>

          <div class="controls">
            <button
              v-if="c.can_see_others_entries"
              type="button" class="tgl" :class="{ on: contests.mineOnly }"
              @click="contests.setMineOnly(!contests.mineOnly, id)"
            >Mine</button>
            <div class="sort">
              <button
                v-for="s in (['recent', 'top'] as const)" :key="s"
                type="button" :class="{ on: contests.sort === s }"
                @click="contests.setSort(s, id)"
              >{{ s === 'recent' ? 'Recent' : 'Most liked' }}</button>
            </div>
          </div>
        </div>

        <div v-if="contests.entriesLoading" class="state small">Loading entries…</div>
        <div v-else-if="!contests.entries.length" class="state small">{{ listEmpty }}</div>

        <div v-else class="list">
          <ContestsEntryCard
            v-for="e in contests.entries" :key="e.id"
            :entry="e" :contest="c"
          />
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.back {
  display: inline-flex; align-items: center; gap: 4px; color: #64748b; font-size: .84rem;
  font-weight: 600; text-decoration: none; margin-bottom: 14px;
}
.back:hover { color: var(--brand-primary); }
.back svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

.state { padding: 60px 0; text-align: center; color: #64748b; }
.state.small { padding: 36px 0; font-size: .88rem; }

.hero { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 2px rgba(15,23,42,.05); margin-bottom: 18px; }
.banner { position: relative; aspect-ratio: 1036 / 350; background: #eef0f3; }
.phase {
  position: absolute; top: 12px; left: 12px; padding: 5px 12px; border-radius: 999px; color: #fff;
  font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; background: rgba(15,23,42,.7);
}
.phase.ongoing { background: #16a34a; }
.phase.upcoming { background: #2563eb; }

.intro { padding: 18px 20px 20px; }
.intro h1 { margin: 0; font-size: 1.3rem; font-weight: 800; color: #1e293b; }
.caption { margin: 6px 0 0; color: #64748b; font-size: .9rem; }

.facts { display: flex; flex-wrap: wrap; gap: 7px; margin: 12px 0 0; }
.pill { background: #f1f5f9; color: #475569; border-radius: 999px; padding: 4px 11px; font-size: .74rem; font-weight: 700; }
.pill.pts { background: color-mix(in srgb, var(--brand-primary) 12%, #fff); color: var(--brand-primary); }
.pill.live { background: #dcfce7; color: #15803d; }

.when { margin: 10px 0 0; color: #94a3b8; font-size: .8rem; font-weight: 600; }
.desc { margin: 12px 0 0; color: #475569; font-size: .9rem; line-height: 1.6; white-space: pre-line; }

.file {
  display: inline-flex; align-items: center; gap: 7px; margin-top: 12px; padding: 8px 14px;
  border: 1px solid #e2e8f0; border-radius: 10px; color: #475569; font-size: .84rem;
  font-weight: 600; text-decoration: none;
}
.file:hover { border-color: var(--brand-primary); color: var(--brand-primary); }
.file svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }

.rules { margin: 14px 0 0; color: #94a3b8; font-size: .8rem; line-height: 1.5; }

.notice {
  background: #fff; border-radius: 14px; padding: 16px 18px; margin: 0 0 18px;
  color: #64748b; font-size: .88rem; box-shadow: 0 1px 2px rgba(15,23,42,.05);
}

.entries { margin-top: 4px; }
.bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
.bar h2 { margin: 0; font-size: 1rem; font-weight: 800; color: #1e293b; }
.controls { display: flex; align-items: center; gap: 8px; }

.tgl {
  background: #fff; border: 1px solid #e2e8f0; border-radius: 999px; padding: 6px 14px;
  font: inherit; font-size: .78rem; font-weight: 700; color: #64748b; cursor: pointer;
}
.tgl.on { background: var(--brand-primary); border-color: var(--brand-primary); color: #fff; }

.sort { display: inline-flex; background: #fff; border: 1px solid #e2e8f0; border-radius: 999px; padding: 3px; }
.sort button {
  background: none; border: none; border-radius: 999px; padding: 5px 12px; font: inherit;
  font-size: .78rem; font-weight: 700; color: #64748b; cursor: pointer;
}
.sort button.on { background: #f1f5f9; color: #1e293b; }

.list { display: flex; flex-direction: column; gap: 14px; }
</style>
