<script setup lang="ts">
import type { FeedAttachment, FeedPost, FeedType, NewPostPayload } from '~/stores/feed'

const feed = useFeedStore()
const auth = useAuthStore()

type Mode = 'compose' | 'poll' | 'looking_for' | 'offering'
const mode = ref<Mode>('compose')

const body = ref('')
const visibility = ref<FeedPost['visibility']>('attendees')
const attachments = ref<FeedAttachment[]>([])
const uploading = ref(false)

// Poll
const pollOptions = ref<string[]>(['', ''])
const allowMultiple = ref(false)

// Networking tags (looking for / offering)
const tags = ref<string[]>([])
const tagInput = ref('')

const fileInput = ref<HTMLInputElement | null>(null)
const pendingKind = ref<FeedAttachment['kind']>('image')

const ACCEPT: Record<FeedAttachment['kind'], string> = {
  image: 'image/*',
  video: 'video/mp4,video/webm,video/quicktime',
  pdf: 'application/pdf',
}

const placeholder = computed(() => {
  if (mode.value === 'looking_for') return 'What are you looking for? e.g. a co-founder, investors, a mentor…'
  if (mode.value === 'offering') return 'What are you offering? e.g. mentorship, a demo, free consulting…'
  return 'Got a spark of an idea? Let the community feel your energy!'
})

function pickMedia(kind: FeedAttachment['kind']) {
  pendingKind.value = kind
  if (fileInput.value) {
    fileInput.value.accept = ACCEPT[kind]
    fileInput.value.value = ''
    fileInput.value.click()
  }
}

async function onFiles(e: Event) {
  const input = e.target as HTMLInputElement
  const files = Array.from(input.files ?? [])
  if (!files.length) return
  uploading.value = true
  try {
    for (const file of files) {
      const media = await feed.uploadMedia(file)
      attachments.value.push({ kind: pendingKind.value, url: media.url, name: media.filename })
    }
  } catch {
    // surfaced via the disabled/empty state; keep it quiet
  } finally {
    uploading.value = false
  }
}

function removeAttachment(i: number) { attachments.value.splice(i, 1) }

function setMode(m: Mode) { mode.value = mode.value === m ? 'compose' : m }

function addPollOption() { if (pollOptions.value.length < 8) pollOptions.value.push('') }
function removePollOption(i: number) { if (pollOptions.value.length > 2) pollOptions.value.splice(i, 1) }

function addTag() {
  const t = tagInput.value.trim().replace(/,$/, '')
  if (t && !tags.value.includes(t) && tags.value.length < 12) tags.value.push(t)
  tagInput.value = ''
}
function removeTag(i: number) { tags.value.splice(i, 1) }

const postType = computed<FeedType>(() => {
  if (mode.value === 'poll') return 'poll'
  if (mode.value === 'looking_for') return 'looking_for'
  if (mode.value === 'offering') return 'offering'
  if (attachments.value.length) return attachments.value[0]!.kind
  return 'text'
})

const validPollOptions = computed(() => pollOptions.value.map(o => o.trim()).filter(Boolean))

const canPost = computed(() => {
  if (feed.posting || uploading.value) return false
  if (mode.value === 'poll') return validPollOptions.value.length >= 2 && !!body.value.trim()
  return !!body.value.trim() || attachments.value.length > 0
})

function reset() {
  mode.value = 'compose'
  body.value = ''
  attachments.value = []
  pollOptions.value = ['', '']
  allowMultiple.value = false
  tags.value = []
  tagInput.value = ''
}

/** Shown when the event moderates its feed and the new post starts pending. */
const pendingNotice = ref(false)
let noticeTimer: ReturnType<typeof setTimeout> | undefined

async function submit() {
  if (!canPost.value) return
  const payload: NewPostPayload = {
    type: postType.value,
    body: body.value,
    visibility: visibility.value,
    attachments: attachments.value,
  }
  if (mode.value === 'poll') {
    payload.poll = { options: validPollOptions.value, allow_multiple: allowMultiple.value }
  }
  if (mode.value === 'looking_for' || mode.value === 'offering') {
    payload.tags = tags.value
  }
  const post = await feed.createPost(payload)
  reset()
  if (post && post.status !== 'published') {
    pendingNotice.value = true
    clearTimeout(noticeTimer)
    noticeTimer = setTimeout(() => { pendingNotice.value = false }, 8000)
  }
}

const tools: Array<{ kind: 'image' | 'video' | 'pdf', mode?: Mode, label: string, icon: string }> = [
  { kind: 'image', label: 'Photo', icon: 'M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5M9 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z' },
  { kind: 'video', label: 'Video', icon: 'M3 6h13v12H3zM16 10l5-3v10l-5-3z' },
  { kind: 'pdf', label: 'PDF', icon: 'M7 3h8l4 4v14H7zM15 3v4h4M9 13h6M9 17h6' },
]
</script>

<template>
  <div class="composer">
    <div class="row">
      <span class="me">
        <img v-if="false" src="" alt="">
        <template v-else>{{ initials(auth.user?.name) }}</template>
      </span>
      <textarea v-model="body" rows="2" :placeholder="placeholder" />
    </div>

    <!-- Poll editor -->
    <div v-if="mode === 'poll'" class="panel">
      <div class="panel-head">Poll options</div>
      <div v-for="(_, i) in pollOptions" :key="i" class="polrow">
        <input v-model="pollOptions[i]" type="text" :placeholder="`Option ${i + 1}`" maxlength="200">
        <button v-if="pollOptions.length > 2" class="x" type="button" title="Remove" @click="removePollOption(i)">×</button>
      </div>
      <div class="pollfoot">
        <button v-if="pollOptions.length < 8" class="addopt" type="button" @click="addPollOption">+ Add option</button>
        <label class="multi"><input v-model="allowMultiple" type="checkbox"> Allow multiple answers</label>
      </div>
    </div>

    <!-- Networking tags (looking for / offering) -->
    <div v-if="mode === 'looking_for' || mode === 'offering'" class="panel">
      <div class="panel-head">{{ mode === 'looking_for' ? 'Looking for' : 'Offering' }} — add tags</div>
      <div class="tagbox">
        <span v-for="(t, i) in tags" :key="t" class="tagchip">{{ t }}<button type="button" @click="removeTag(i)">×</button></span>
        <input
          v-model="tagInput"
          type="text"
          placeholder="Type a tag and press Enter"
          @keyup.enter="addTag"
        >
      </div>
    </div>

    <!-- Attachment previews -->
    <div v-if="attachments.length" class="previews">
      <div v-for="(a, i) in attachments" :key="a.url" class="pv">
        <img v-if="a.kind === 'image'" :src="a.url" alt="">
        <video v-else-if="a.kind === 'video'" :src="a.url" muted />
        <div v-else class="pdf"><svg viewBox="0 0 24 24"><path d="M7 3h8l4 4v14H7zM15 3v4h4" /></svg><span>{{ a.name || 'PDF' }}</span></div>
        <button class="pvx" type="button" title="Remove" @click="removeAttachment(i)">×</button>
      </div>
    </div>

    <div v-if="uploading" class="uploading">Uploading…</div>

    <div v-if="pendingNotice" class="pending-note">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 3" /></svg>
      Your post was submitted and is awaiting organizer approval. It will appear on the feed once approved.
    </div>

    <div class="toolbar">
      <div class="tools">
        <button v-for="t in tools" :key="t.kind" class="tool" type="button" :title="t.label" @click="pickMedia(t.kind)">
          <svg viewBox="0 0 24 24"><path :d="t.icon" /></svg>
        </button>
        <span class="sep" />
        <button class="tool" :class="{ on: mode === 'poll' }" type="button" title="Poll" @click="setMode('poll')">
          <svg viewBox="0 0 24 24"><path d="M5 21V10M12 21V4M19 21v-7" /></svg>
        </button>
        <button class="tool" :class="{ on: mode === 'looking_for' }" type="button" title="Looking for" @click="setMode('looking_for')">
          <svg viewBox="0 0 24 24"><path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM21 21l-5-5" /></svg>
        </button>
        <button class="tool" :class="{ on: mode === 'offering' }" type="button" title="Offering" @click="setMode('offering')">
          <svg viewBox="0 0 24 24"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 22V7M12 7H8a2 2 0 1 1 0-4c3 0 4 4 4 4zM12 7h4a2 2 0 1 0 0-4c-3 0-4 4-4 4z" /></svg>
        </button>
      </div>

      <div class="right">
        <select v-model="visibility" class="vis" title="Who can see this">
          <option value="attendees">All attendees</option>
          <option value="public">Public</option>
        </select>
        <button class="post" type="button" :disabled="!canPost" @click="submit">
          {{ feed.posting ? 'Posting…' : 'Post' }}
        </button>
      </div>
    </div>

    <input ref="fileInput" type="file" multiple class="hidden" @change="onFiles">
  </div>
</template>

<style scoped>
.composer { background: #fff; border-radius: 14px; padding: 16px; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.row { display: flex; gap: 12px; }
.me { flex: 0 0 auto; width: 42px; height: 42px; border-radius: 50%; background: var(--brand-primary); color: #fff; font-weight: 700; font-size: .9rem; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; }
.me img { width: 100%; height: 100%; object-fit: cover; }
textarea { flex: 1; border: 1px solid #e2e8f0; border-radius: 12px; padding: 11px 14px; font: inherit; font-size: .95rem; resize: vertical; outline: none; color: #334155; min-height: 48px; }
textarea:focus { border-color: var(--brand-primary); }

.panel { margin-top: 12px; border: 1px solid #eef0f3; border-radius: 12px; padding: 12px; background: #fafbfc; }
.panel-head { font-size: .78rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 10px; }
.polrow { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.polrow input { flex: 1; border: 1px solid #e2e8f0; border-radius: 9px; padding: 9px 12px; font: inherit; font-size: .88rem; outline: none; }
.polrow input:focus { border-color: var(--brand-primary); }
.x { width: 28px; height: 28px; border: none; background: #f1f5f9; color: #64748b; border-radius: 8px; font-size: 1.1rem; line-height: 1; cursor: pointer; }
.pollfoot { display: flex; align-items: center; justify-content: space-between; margin-top: 4px; }
.addopt { border: none; background: none; color: var(--brand-primary); font-weight: 700; font-size: .84rem; cursor: pointer; }
.multi { display: inline-flex; align-items: center; gap: 7px; color: #475569; font-size: .82rem; }

.tagbox { display: flex; flex-wrap: wrap; gap: 7px; align-items: center; }
.tagchip { display: inline-flex; align-items: center; gap: 6px; background: color-mix(in srgb, var(--brand-primary) 12%, #fff); color: var(--brand-primary); border-radius: 999px; padding: 4px 6px 4px 12px; font-size: .8rem; font-weight: 600; }
.tagchip button { border: none; background: none; color: inherit; cursor: pointer; font-size: 1rem; line-height: 1; }
.tagbox input { flex: 1; min-width: 160px; border: none; outline: none; font: inherit; font-size: .86rem; padding: 6px 4px; }

.previews { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
.pv { position: relative; width: 108px; height: 108px; border-radius: 10px; overflow: hidden; border: 1px solid #eef0f3; background: #f4f5f8; }
.pv img, .pv video { width: 100%; height: 100%; object-fit: cover; }
.pv .pdf { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; height: 100%; padding: 8px; color: #64748b; text-align: center; }
.pv .pdf svg { width: 26px; height: 26px; fill: none; stroke: #ef4444; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.pv .pdf span { font-size: .68rem; word-break: break-word; line-height: 1.2; }
.pvx { position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; border: none; border-radius: 50%; background: rgba(15,23,42,.65); color: #fff; cursor: pointer; font-size: .95rem; line-height: 1; }
.uploading { margin-top: 10px; color: #64748b; font-size: .84rem; }
.pending-note { display: flex; align-items: center; gap: 8px; margin-top: 12px; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; border-radius: 10px; padding: 10px 14px; font-size: .85rem; }
.pending-note svg { flex: 0 0 auto; width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }

.toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 14px; padding-top: 12px; border-top: 1px solid #eef0f3; flex-wrap: wrap; }
.tools { display: flex; align-items: center; gap: 4px; }
.tool { width: 38px; height: 38px; border: none; background: none; border-radius: 10px; color: var(--brand-primary); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.tool:hover { background: color-mix(in srgb, var(--brand-primary) 10%, #fff); }
.tool.on { background: var(--brand-primary); color: #fff; }
.tool svg { width: 21px; height: 21px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.sep { width: 1px; height: 22px; background: #e2e8f0; margin: 0 4px; }

.right { display: flex; align-items: center; gap: 10px; margin-left: auto; }
.vis { border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 12px; font: inherit; font-size: .84rem; color: #475569; background: #fff; }
.post { background: var(--brand-primary); color: #fff; border: none; border-radius: 999px; padding: 9px 26px; font-weight: 700; cursor: pointer; }
.post:disabled { opacity: .5; cursor: default; }
.hidden { display: none; }
</style>
