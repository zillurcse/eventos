<script setup lang="ts">
import type { Contest, ContestEntry } from '~/stores/contests'

/** The podium shown on an ended contest, above the full entry list. */
defineProps<{ contest: Contest, winners: ContestEntry[] }>()

const MEDAL = ['🥇', '🥈', '🥉']
</script>

<template>
  <section class="winners">
    <div class="head">
      <h2>Winners</h2>
      <p>
        {{ contest.winner_chooser === 'most_likes' ? 'Chosen by the most likes' : 'Chosen by the organizer' }}
        <template v-if="contest.winning_points"> · {{ contest.winning_points }} pts</template>
      </p>
    </div>

    <ol class="podium">
      <li v-for="(w, i) in winners" :key="w.id">
        <span class="place">{{ MEDAL[i] ?? `#${i + 1}` }}</span>
        <div class="av"><UserAvatar :src="w.author_avatar" :name="w.author" /></div>
        <div class="who">
          <strong>{{ w.author }}<span v-if="w.is_mine" class="you">You</span></strong>
          <span v-if="w.body" class="excerpt">{{ w.body }}</span>
        </div>
        <span class="likes">{{ w.like_count }} ♥</span>
        <div v-if="w.attachments[0]" class="shot">
          <video v-if="w.attachments[0].kind === 'video'" :src="w.attachments[0].url" muted />
          <img v-else :src="w.attachments[0].url" :alt="`${w.author}'s entry`" loading="lazy">
        </div>
      </li>
    </ol>
  </section>
</template>

<style scoped>
.winners { background: #fff; border-radius: 16px; padding: 16px 18px; box-shadow: 0 1px 2px rgba(15,23,42,.05); margin-bottom: 18px; }
.head { margin-bottom: 12px; }
.head h2 { margin: 0; font-size: .98rem; font-weight: 800; color: #1e293b; }
.head p { margin: 3px 0 0; color: #94a3b8; font-size: .8rem; }

.podium { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.podium li {
  display: flex; align-items: center; gap: 11px; background: #f8fafc;
  border-radius: 12px; padding: 10px 12px;
}
.place { font-size: 1.1rem; width: 26px; text-align: center; flex: 0 0 auto; font-weight: 800; color: #64748b; }
.av { width: 38px; height: 38px; border-radius: 50%; overflow: hidden; flex: 0 0 auto; background: #e2e8f0; }
.who { display: flex; flex-direction: column; min-width: 0; flex: 1; }
.who strong { font-size: .88rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px; }
.you { background: #e2e8f0; color: #64748b; border-radius: 999px; padding: 1px 7px; font-size: .65rem; font-weight: 700; }
.excerpt {
  color: #64748b; font-size: .8rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.likes { color: #e11d48; font-size: .8rem; font-weight: 700; flex: 0 0 auto; }
.shot { width: 48px; height: 48px; border-radius: 10px; overflow: hidden; flex: 0 0 auto; background: #e2e8f0; }
.shot img, .shot video { width: 100%; height: 100%; object-fit: cover; display: block; }
</style>
