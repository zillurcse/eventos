<script setup lang="ts">
import type { Contest, ContestEntry } from '~/stores/contests'

/** One entry (or response) in a contest, with its likes and comment thread. */
const props = defineProps<{ entry: ContestEntry, contest: Contest }>()

const contests = useContestsStore()

const showComments = ref(false)
const commentBody = ref('')
const commenting = ref(false)
const error = ref('')

const thread = computed(() => contests.comments[props.entry.id] ?? [])

// Comments are an entry-contest affordance; a response contest is already a
// flat conversation on the organizer's post.
const commentable = computed(() =>
  props.contest.contest_type === 'entry'
  && (props.contest.can_see_other_comments || props.entry.is_mine || props.contest.phase === 'ongoing'),
)

const likeable = computed(() => !props.entry.is_mine && props.contest.phase !== 'ended')

async function like() {
  if (!likeable.value) return
  error.value = ''
  try {
    await contests.toggleLike(props.entry)
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not register that like.'
  }
}

async function toggleComments() {
  showComments.value = !showComments.value
  if (showComments.value && !contests.comments[props.entry.id]) {
    try { await contests.fetchComments(props.entry.id) } catch { /* thread stays empty */ }
  }
}

async function postComment() {
  const body = commentBody.value.trim()
  if (!body || commenting.value) return

  commenting.value = true
  error.value = ''
  try {
    await contests.addComment(props.entry, body)
    commentBody.value = ''
  } catch (e: any) {
    error.value = e?.data?.errors?.body?.[0] || e?.data?.message || 'Could not post that comment.'
  } finally {
    commenting.value = false
  }
}

async function remove() {
  if (!confirm('Remove your entry from this contest?')) return
  try {
    await contests.removeEntry(props.entry.id)
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not remove that entry.'
  }
}
</script>

<template>
  <article class="entry" :class="{ winner: entry.is_winner }">
    <header>
      <div class="av"><UserAvatar :src="entry.author_avatar" :name="entry.author" /></div>
      <div class="who">
        <strong>{{ entry.author }}<span v-if="entry.is_mine" class="you">You</span></strong>
        <span class="sub">
          {{ entry.author_headline || 'Attendee' }}<template v-if="entry.created_at"> · {{ timeAgo(entry.created_at) }}</template>
        </span>
      </div>

      <span v-if="entry.is_winner" class="badge win">
        Winner<template v-if="entry.rank"> #{{ entry.rank }}</template>
      </span>
      <span v-else-if="entry.status === 'pending'" class="badge pending">In review</span>
      <span v-else-if="entry.status === 'rejected'" class="badge rejected">Not accepted</span>
    </header>

    <p v-if="entry.body" class="body">{{ entry.body }}</p>

    <div v-if="entry.attachments.length" class="media" :class="{ multi: entry.attachments.length > 1 }">
      <template v-for="a in entry.attachments" :key="a.url">
        <video v-if="a.kind === 'video'" :src="a.url" controls preload="metadata" />
        <img v-else :src="a.url" :alt="a.name || 'Contest entry'" loading="lazy">
      </template>
    </div>

    <footer>
      <button
        type="button" class="act" :class="{ on: entry.liked }"
        :disabled="!likeable"
        :title="entry.is_mine ? 'You can’t like your own entry' : undefined"
        @click="like"
      >
        <svg viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.8 1-1a5.5 5.5 0 0 0 0-7.8z" /></svg>
        {{ entry.like_count }}
      </button>

      <button v-if="commentable" type="button" class="act" @click="toggleComments">
        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" /></svg>
        {{ entry.comment_count }}
      </button>

      <button v-if="entry.is_mine && contest.phase === 'ongoing'" type="button" class="del" @click="remove">
        Remove
      </button>
    </footer>

    <div v-if="showComments" class="thread">
      <div v-for="cm in thread" :key="cm.id" class="cm">
        <div class="cav"><UserAvatar :src="cm.author_avatar" :name="cm.author" /></div>
        <div class="cbody">
          <strong>{{ cm.author }}</strong>
          <p>{{ cm.body }}</p>
          <span class="cwhen">{{ timeAgo(cm.created_at) }}</span>
        </div>
      </div>

      <p v-if="!thread.length" class="empty">No comments yet.</p>

      <div v-if="contest.phase === 'ongoing'" class="cform">
        <input
          v-model="commentBody"
          type="text"
          :maxlength="contest.character_limit"
          placeholder="Add a comment…"
          @keyup.enter="postComment"
        >
        <button type="button" :disabled="!commentBody.trim() || commenting" @click="postComment">
          {{ commenting ? '…' : 'Post' }}
        </button>
      </div>
    </div>

    <p v-if="error" class="err">{{ error }}</p>
  </article>
</template>

<style scoped>
.entry { background: #fff; border-radius: 16px; padding: 15px 17px 13px; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.entry.winner { box-shadow: 0 0 0 2px #f59e0b, 0 1px 2px rgba(15,23,42,.05); }

header { display: flex; align-items: center; gap: 10px; }
.av { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; flex: 0 0 auto; background: #f1f5f9; }
.who { display: flex; flex-direction: column; min-width: 0; }
.who strong { font-size: .9rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px; }
.you { background: #f1f5f9; color: #64748b; border-radius: 999px; padding: 1px 7px; font-size: .65rem; font-weight: 700; }
.sub { color: #94a3b8; font-size: .76rem; }

.badge { margin-left: auto; border-radius: 999px; padding: 4px 11px; font-size: .7rem; font-weight: 700; }
.badge.win { background: #fef3c7; color: #b45309; }
.badge.pending { background: #e0f2fe; color: #0369a1; }
.badge.rejected { background: #fee2e2; color: #b91c1c; }

.body { margin: 11px 0 0; color: #334155; font-size: .9rem; line-height: 1.6; white-space: pre-line; }

.media { margin-top: 12px; border-radius: 12px; overflow: hidden; display: grid; gap: 4px; }
.media.multi { grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }
.media img, .media video { width: 100%; max-height: 420px; object-fit: cover; display: block; background: #0f172a; }
.media.multi img, .media.multi video { aspect-ratio: 1; max-height: none; }

footer { display: flex; align-items: center; gap: 6px; margin-top: 12px; padding-top: 10px; border-top: 1px solid #f1f5f9; }
.act {
  display: inline-flex; align-items: center; gap: 6px; background: none; border: none; border-radius: 8px;
  padding: 6px 10px; font: inherit; font-size: .82rem; font-weight: 700; color: #64748b; cursor: pointer;
}
.act:hover:not(:disabled) { background: #f8fafc; color: var(--brand-primary); }
.act:disabled { cursor: default; opacity: .7; }
.act.on { color: #e11d48; }
.act.on svg { fill: #e11d48; }
.act svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.del { margin-left: auto; background: none; border: none; font: inherit; font-size: .8rem; font-weight: 600; color: #dc2626; cursor: pointer; }

.thread { margin-top: 12px; border-top: 1px solid #f1f5f9; padding-top: 12px; display: flex; flex-direction: column; gap: 11px; }
.cm { display: flex; gap: 9px; }
.cav { width: 30px; height: 30px; border-radius: 50%; overflow: hidden; flex: 0 0 auto; background: #f1f5f9; }
.cbody { background: #f8fafc; border-radius: 12px; padding: 8px 12px; flex: 1; min-width: 0; }
.cbody strong { font-size: .82rem; color: #1e293b; }
.cbody p { margin: 2px 0 0; font-size: .85rem; color: #334155; line-height: 1.5; }
.cwhen { color: #94a3b8; font-size: .72rem; }
.empty { margin: 0; color: #94a3b8; font-size: .82rem; }

.cform { display: flex; gap: 8px; }
.cform input {
  flex: 1; border: 1px solid #e2e8f0; border-radius: 999px; padding: 8px 14px;
  font: inherit; font-size: .85rem; color: #1e293b;
}
.cform input:focus { outline: none; border-color: var(--brand-primary); }
.cform button {
  background: var(--brand-primary); color: #fff; border: none; border-radius: 999px;
  padding: 8px 18px; font: inherit; font-weight: 700; font-size: .82rem; cursor: pointer;
}
.cform button:disabled { opacity: .55; cursor: default; }

.err { margin: 8px 0 0; color: #dc2626; font-size: .8rem; }
</style>
