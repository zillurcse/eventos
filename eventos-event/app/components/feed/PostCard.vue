<script setup lang="ts">
import type { FeedComment, FeedPost } from '~/stores/feed'

const props = defineProps<{ post: FeedPost }>()
const feed = useFeedStore()

const open = ref(false)
const comments = ref<FeedComment[]>([])
const loadingComments = ref(false)
const commentsLoaded = ref(false)
const commentBody = ref('')
const sending = ref(false)

async function toggleComments() {
  open.value = !open.value
  if (open.value && !commentsLoaded.value) {
    loadingComments.value = true
    try {
      comments.value = await feed.fetchComments(props.post)
      commentsLoaded.value = true
    } finally {
      loadingComments.value = false
    }
  }
}

async function send() {
  if (!commentBody.value.trim() || sending.value) return
  sending.value = true
  try {
    const c = await feed.addComment(props.post, commentBody.value)
    if (c) comments.value.push(c)
    commentBody.value = ''
  } finally {
    sending.value = false
  }
}

// ── Type helpers ─────────────────────────────────────────────────────────
const banner = computed(() => {
  if (props.post.type === 'looking_for') return { label: 'Looking for', icon: 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM21 21l-5-5', cls: 'lf' }
  if (props.post.type === 'offering') return { label: 'Offering', icon: 'M20 12v9H4v-9M2 7h20v5H2zM12 22V7', cls: 'of' }
  return null
})

// Images/videos tile in the media grid; PDFs render as full-width
// LinkedIn-style paged documents below it.
const visualMedia = computed(() => (props.post.attachments ?? []).filter(a => a.kind !== 'pdf'))
const docs = computed(() => (props.post.attachments ?? []).filter(a => a.kind === 'pdf'))

function pctOf(votes: number) {
  const total = props.post.poll?.total_votes ?? 0
  return total > 0 ? Math.round((votes / total) * 100) : 0
}
function votedFor(optId: string) { return props.post.poll?.my_vote?.includes(optId) ?? false }
const hasVoted = computed(() => (props.post.poll?.my_vote?.length ?? 0) > 0)
</script>

<template>
  <article class="post">
    <span v-if="post.is_pinned" class="pin">
      <svg viewBox="0 0 24 24">
        <path d="M12 17v5M9 3h6l-1 6 4 3H6l4-3z" />
      </svg>
      Pinned
    </span>

    <header class="ph">
      <span class="av" :class="{ org: post.author_role === 'organizer' }">
        <UserAvatar :src="post.author_avatar" :name="post.author" />
      </span>
      <div class="who">
        <span class="name">
          {{ post.author }}
          <span v-if="post.author_role === 'organizer'" class="tag">Organizer</span>
        </span>
        <span class="time">{{ timeAgo(post.created_at) }}</span>
      </div>

      <!-- Moderation state — only ever non-published on the author's own posts ("My Posts") -->
      <span v-if="post.status === 'pending'" class="mod pending" title="Waiting for organizer approval">
        <svg viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="9" />
          <path d="M12 7v5l3 3" />
        </svg>
        Pending approval
      </span>
      <span v-else-if="post.status === 'rejected'" class="mod rejected" title="Rejected by the organizer">
        <svg viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="9" />
          <path d="M15 9l-6 6M9 9l6 6" />
        </svg>
        Rejected
      </span>
      <svg class="more" viewBox="0 0 24 24" aria-label="Post options">
        <circle cx="12" cy="5" r="1.5" />
        <circle cx="12" cy="12" r="1.5" />
        <circle cx="12" cy="19" r="1.5" />
      </svg>
    </header>

    <span v-if="banner" class="banner" :class="banner.cls">
      <svg viewBox="0 0 24 24">
        <path :d="banner.icon" />
      </svg>{{ banner.label }}
    </span>

    <p v-if="post.body" class="body">{{ post.body }}</p>

    <!-- Looking-for / offering tags -->
    <div v-if="post.tags?.length" class="ptags">
      <span v-for="t in post.tags" :key="t" class="ptag">{{ t }}</span>
    </div>

    <!-- Media attachments -->
    <div v-if="visualMedia.length" class="media" :class="`n${Math.min(visualMedia.length, 4)}`">
      <template v-for="(a, i) in visualMedia" :key="i">
        <a v-if="a.kind === 'image'" :href="a.url" target="_blank" rel="noopener" class="mtile">
          <img :src="a.url" :alt="a.name || ''">
        </a>
        <video v-else :src="a.url" controls preload="metadata" class="mtile vid" />
      </template>
    </div>

    <!-- PDF documents — inline page-wise viewer -->
    <div v-if="docs.length" class="docstack">
      <FeedPdfPages v-for="(a, i) in docs" :key="i" :url="a.url" :name="a.name" />
    </div>

    <!-- Poll -->
    <div v-if="post.poll" class="poll">
      <button v-for="o in post.poll.options" :key="o.id" type="button" class="opt" :class="{ mine: votedFor(o.id) }"
        @click="feed.votePoll(post, o.id)">
        <span class="fill" :style="{ width: (hasVoted ? pctOf(o.votes) : 0) + '%' }" />
        <span class="otext">{{ o.text }}</span>
        <span v-if="hasVoted" class="opct">{{ pctOf(o.votes) }}%</span>
      </button>
      <div class="pmeta">
        {{ post.poll.total_votes }} {{ post.poll.total_votes === 1 ? 'vote' : 'votes' }}
        <span v-if="post.poll.allow_multiple"> · choose more than one</span>
      </div>
    </div>

    <!-- No engagement on posts that aren't live on the wall -->
    <div v-if="post.status === 'published'" class="stats">
      <button class="stat" :class="{ on: post.reacted }" type="button" @click="feed.toggleReaction(post)">
        <svg viewBox="0 0 24 24">
          <path
            d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8z" />
        </svg>
        {{ post.reaction_count }} {{ post.reaction_count === 1 ? 'Like' : 'Likes' }}
      </button>
      <button class="stat" :class="{ on: open }" type="button" @click="toggleComments">
        <svg viewBox="0 0 24 24">
          <path d="M20 11.5a8 8 0 0 1-9.5 7.9L5 20l1.1-4.2A8 8 0 1 1 20 11.5zM8 11h8M8 15h5" />
        </svg>
        {{ post.comment_count }} {{ post.comment_count === 1 ? 'Comment' : 'Comments' }}
      </button>
    </div>

    <div v-if="post.status === 'published'" class="comments" :class="{ expanded: open }">
      <template v-if="open">
        <div v-if="loadingComments" class="cnote">Loading comments…</div>
        <div v-else-if="!comments.length" class="cnote">Be the first to comment.</div>

        <div v-for="c in comments" :key="c.id" class="comment">
          <span class="cav" :class="{ org: c.author_role === 'organizer' }">
            <UserAvatar :src="c.author_avatar" :name="c.author" />
          </span>
          <div class="cbubble">
            <span class="cname">{{ c.author }}<span v-if="c.author_role === 'organizer'"
                class="tag">Organizer</span></span>
            <p>{{ c.body }}</p>
            <span class="ctime">{{ timeAgo(c.created_at) }}</span>
          </div>
        </div>
      </template>

      <div class="cadd">
          <span class="av" :class="{ org: post.author_role === 'organizer' }">
            <UserAvatar :src="post.author_avatar" :name="post.author" />
          </span>
        <input v-model="commentBody" type="text" placeholder="Write a comment…" @keyup.enter="send">
        <button type="button" :disabled="!commentBody.trim() || sending" @click="send">Send</button>
      </div>
    </div>
  </article>
</template>

<style scoped>
.post {
  background: #fff;
  border: 1px solid #e5e6ec;
  border-radius: 12px;
  padding: 16px;
}

.pin {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: #b45309;
  background: #fef3c7;
  font-size: .7rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 999px;
  margin-bottom: 12px;
}

.pin svg {
  width: 13px;
  height: 13px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.ph {
  display: flex;
  align-items: center;
  gap: 11px;
}

.av {
  flex: 0 0 auto;
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: var(--brand-primary);
  color: #fff;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.av.org {
  background: #0f766e;
}

.av img,
.cav img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.who {
  display: flex;
  flex-direction: column;
}

.name {
  font-weight: 750;
  color: #282c34;
  font-size: 1rem;
  line-height: 1.2;
  display: flex;
  align-items: center;
  gap: 7px;
}

.tag {
  font-size: .62rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .4px;
  color: #0f766e;
  background: #ccfbf1;
  padding: 2px 7px;
  border-radius: 999px;
}

.time {
  color: #777b84;
  font-size: 12px;
  line-height: 1.2;
  margin-top: 3px;
}

.mod {
  margin-left: auto;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: .7rem;
  font-weight: 700;
  padding: 4px 11px;
  border-radius: 999px;
  white-space: nowrap;
}

.mod svg {
  width: 13px;
  height: 13px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.mod.pending {
  color: #92400e;
  background: #fef3c7;
}

.mod.rejected {
  color: #b91c1c;
  background: #fee2e2;
}

.more {
  flex: 0 0 auto;
  width: 24px;
  height: 24px;
  margin-left: auto;
  fill: #9096ad;
}

.banner {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 12px;
  font-size: .7rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .4px;
  padding: 4px 11px;
  border-radius: 999px;
}

.banner svg {
  width: 13px;
  height: 13px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.banner.lf {
  color: #7c3aed;
  background: #ede9fe;
}

.banner.of {
  color: #0f766e;
  background: #ccfbf1;
}

.body {
  margin: 20px 0 0;
  color: #575b64;
  font-size: .94rem;
  line-height: 1.5;
  white-space: pre-wrap;
  word-break: break-word;
}

.ptags {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
  margin-top: 10px;
}

.ptag {
  font-size: .76rem;
  font-weight: 600;
  color: #475569;
  background: #f1f5f9;
  padding: 4px 11px;
  border-radius: 999px;
}

.media {
  display: grid;
  gap: 6px;
  margin-top: 18px;
  border-radius: 10px;
  overflow: hidden;
}

.media.n1 {
  grid-template-columns: 1fr;
}

.media.n2 {
  grid-template-columns: 1fr 1fr;
}

.media.n3,
.media.n4 {
  grid-template-columns: 1fr 1fr;
}

.mtile {
  display: block;
  background: #f4f5f8;
  overflow: hidden;
  border-radius: 10px;
}

.mtile img,
.mtile.vid {
  width: 100%;
  height: 100%;
  max-height: 180px;
  object-fit: cover;
  display: block;
}

.media.n1 .mtile img {
  max-height: 180px;
}

.mtile.vid {
  background: #000;
}

.docstack {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 12px;
}

.poll {
  margin-top: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.opt {
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  gap: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  border-radius: 10px;
  padding: 11px 14px;
  cursor: pointer;
  font: inherit;
  text-align: left;
}

.opt:hover {
  border-color: var(--brand-primary);
}

.opt.mine {
  border-color: var(--brand-primary);
}

.opt .fill {
  position: absolute;
  inset: 0 auto 0 0;
  background: color-mix(in srgb, var(--brand-primary) 14%, #fff);
  transition: width .35s ease;
  z-index: 0;
}

.opt.mine .fill {
  background: color-mix(in srgb, var(--brand-primary) 24%, #fff);
}

.otext {
  position: relative;
  z-index: 1;
  flex: 1;
  color: #334155;
  font-size: .9rem;
  font-weight: 600;
}

.opct {
  position: relative;
  z-index: 1;
  color: #475569;
  font-size: .84rem;
  font-weight: 700;
}

.pmeta {
  color: #94a3b8;
  font-size: .78rem;
  margin-top: 2px;
}

.stats {
  display: flex;
  gap: 10px;
  margin-top: 20px;
}

.stat {
  flex: none;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  border: none;
  background: none;
  padding: 0;
  color: #686c74;
  font: inherit;
  font-size: .88rem;
  cursor: pointer;
}

.stat svg {
  width: 27px;
  height: 27px;
  fill: none;
  stroke: var(--brand-primary);
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.stat.on {
  color: var(--brand-primary);
}

.stat.on svg {
  fill: color-mix(in srgb, var(--brand-primary) 16%, transparent);
}

.comments {
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid #e8e9ee;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.cnote {
  color: #94a3b8;
  font-size: .84rem;
}

.comment {
  display: flex;
  gap: 9px;
}

.cav {
  flex: 0 0 auto;
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: var(--brand-primary);
  color: #fff;
  font-size: .68rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.cav.org {
  background: #0f766e;
}

.cbubble {
  background: #f4f5f8;
  border-radius: 12px;
  padding: 8px 12px;
}

.cname {
  font-weight: 700;
  color: #1e293b;
  font-size: .84rem;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.cbubble p {
  margin: 3px 0 0;
  color: #334155;
  font-size: .88rem;
  line-height: 1.45;
  white-space: pre-wrap;
  word-break: break-word;
}

.ctime {
  color: #94a3b8;
  font-size: .74rem;
}

.cadd {
  display: flex;
  align-items: center;
  gap: 10px;
}



.cadd input {
  flex: 1;
  box-sizing: border-box;
  border: 1px solid #d8dbe4;
  border-radius: 10px;
  padding: 9px 18px;
  font: inherit;
  font-size: .86rem;
  outline: none;
  color: #334155;
  max-height: 40px;
}

.cadd input:focus {
  border-color: var(--brand-primary);
}

.cadd button {
  background: var(--brand-primary);
  color: #fff;
  border: none;
  border-radius: 999px;
  padding: 0 18px;
  font-weight: 700;
  font-size: .84rem;
  cursor: pointer;
  max-height: 40px;
  min-height: 40px;
}

.cadd button:disabled {
  opacity: .5;
  cursor: default;
}
</style>
