<script setup lang="ts">
import type { FeedAttachment, FeedPost, FeedType, NewPostPayload } from '~/stores/feed'

const feed = useFeedStore()
const auth = useAuthStore()

type Mode = 'compose' | 'poll' | 'looking_for' | 'offering'
const mode = ref<Mode>('compose')

const body = ref('')
const visibility = ref<FeedPost['visibility']>('attendees')

// Poll
const pollOptions = ref<string[]>(['', ''])
const allowMultiple = ref(false)

// Networking tags (looking for / offering)
const tags = ref<string[]>([])
const tagInput = ref('')

// ── Media upload ─────────────────────────────────────────────────────────────
type Kind = 'image' | 'video' | 'pdf'

interface MediaItem {
  id: string
  kind: Kind
  name: string
  preview: string        // object URL for an instant local preview
  status: 'uploading' | 'done' | 'error'
  progress: number
  url?: string           // remote URL once uploaded
  error?: string
  xhr?: XMLHttpRequest
}

// Client-side guardrails — mirror the server so users get instant feedback and
// oversized/wrong files never hit the network (defense in depth).
const MAX_ITEMS = 10
const LIMITS: Record<Kind, { mimes: string[], maxMB: number }> = {
  image: { mimes: ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], maxMB: 12 },
  video: { mimes: ['video/mp4', 'video/webm', 'video/quicktime'], maxMB: 80 },
  pdf: { mimes: ['application/pdf'], maxMB: 20 },
}
const ACCEPT_MEDIA = [...LIMITS.image.mimes, ...LIMITS.video.mimes].join(',')
const ACCEPT_IMAGE = LIMITS.image.mimes.join(',')
const ACCEPT_VIDEO = LIMITS.video.mimes.join(',')
const ACCEPT_PDF = 'application/pdf'

const media = ref<MediaItem[]>([])
const errors = ref<string[]>([])
const dragging = ref(false)
let dragDepth = 0

const fileInput = ref<HTMLInputElement | null>(null)
let pickAccept = ACCEPT_MEDIA

function kindOf(file: File): Kind | null {
  if (LIMITS.image.mimes.includes(file.type)) return 'image'
  if (LIMITS.video.mimes.includes(file.type)) return 'video'
  if (LIMITS.pdf.mimes.includes(file.type)) return 'pdf'
  return null
}

function flashError(msg: string) {
  errors.value.push(msg)
  setTimeout(() => { errors.value.shift() }, 5000)
}

function pick(accept: string) {
  pickAccept = accept
  if (fileInput.value) {
    fileInput.value.accept = accept
    fileInput.value.value = ''
    fileInput.value.click()
  }
}

function onInput(e: Event) {
  const input = e.target as HTMLInputElement
  addFiles(input.files)
  input.value = ''
}

function addFiles(list: FileList | null) {
  if (!list?.length) return
  for (const file of Array.from(list)) {
    if (media.value.length >= MAX_ITEMS) { flashError(`You can attach up to ${MAX_ITEMS} files.`); break }
    const kind = kindOf(file)
    if (!kind) { flashError(`“${file.name}” isn’t a supported type — use an image, video or PDF.`); continue }
    const lim = LIMITS[kind]
    if (file.size > lim.maxMB * 1024 * 1024) { flashError(`“${file.name}” is too large — ${kind} must be under ${lim.maxMB} MB.`); continue }
    startUpload(file, kind)
  }
}

function startUpload(file: File, kind: Kind) {
  const item = reactive<MediaItem>({
    id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    kind,
    name: file.name,
    preview: URL.createObjectURL(file),
    status: 'uploading',
    progress: 0,
  })
  media.value.push(item)

  const { xhr, promise } = feed.uploadMediaProgress(file, (pct) => { item.progress = pct })
  item.xhr = xhr
  promise
    .then((uploaded) => { item.url = uploaded.url; item.status = 'done'; item.progress = 100 })
    .catch((err: any) => {
      if (err?.name === 'AbortError') return // user removed it mid-flight
      item.status = 'error'
      item.error = err?.message || 'Upload failed.'
    })
}

function removeItem(item: MediaItem) {
  if (item.status === 'uploading') item.xhr?.abort()
  URL.revokeObjectURL(item.preview)
  media.value = media.value.filter(m => m.id !== item.id)
}

function retry(item: MediaItem) {
  // Re-pick is simplest; just drop the failed tile and let the user re-add.
  removeItem(item)
  pick(ACCEPT_MEDIA)
}

// Drag & drop over the whole composer.
function onDragEnter(e: DragEvent) {
  if (!e.dataTransfer?.types.includes('Files')) return
  dragDepth++
  dragging.value = true
}
function onDragLeave() {
  dragDepth = Math.max(0, dragDepth - 1)
  if (dragDepth === 0) dragging.value = false
}
function onDrop(e: DragEvent) {
  dragDepth = 0
  dragging.value = false
  addFiles(e.dataTransfer?.files ?? null)
}

const uploadingCount = computed(() => media.value.filter(m => m.status === 'uploading').length)
const doneAttachments = computed<FeedAttachment[]>(() =>
  media.value.filter(m => m.status === 'done' && m.url).map(m => ({ kind: m.kind, url: m.url!, name: m.name })))

onBeforeUnmount(() => { for (const m of media.value) URL.revokeObjectURL(m.preview) })

// ── Modes ────────────────────────────────────────────────────────────────────
const placeholder = computed(() => {
  if (mode.value === 'looking_for') return 'What are you looking for?'
  if (mode.value === 'offering') return 'What are you offering?'
  return 'Got a spark of an idea? Let the community feel your energy!'
})

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
  return doneAttachments.value[0]?.kind ?? 'text'
})

const validPollOptions = computed(() => pollOptions.value.map(o => o.trim()).filter(Boolean))

const canPost = computed(() => {
  if (feed.posting || uploadingCount.value > 0) return false
  if (mode.value === 'poll') return validPollOptions.value.length >= 2 && !!body.value.trim()
  return !!body.value.trim() || doneAttachments.value.length > 0
})

function reset() {
  mode.value = 'compose'
  body.value = ''
  for (const m of media.value) URL.revokeObjectURL(m.preview)
  media.value = []
  errors.value = []
  pollOptions.value = ['', '']
  allowMultiple.value = false
  tags.value = []
  tagInput.value = ''
}

const pendingNotice = ref(false)
let noticeTimer: ReturnType<typeof setTimeout> | undefined

async function submit() {
  if (!canPost.value) return
  const payload: NewPostPayload = {
    type: postType.value,
    body: body.value,
    visibility: visibility.value,
    attachments: doneAttachments.value,
  }
  if (mode.value === 'poll') payload.poll = { options: validPollOptions.value, allow_multiple: allowMultiple.value }
  if (mode.value === 'looking_for' || mode.value === 'offering') payload.tags = tags.value

  const post = await feed.createPost(payload)
  reset()
  if (post && post.status !== 'published') {
    pendingNotice.value = true
    clearTimeout(noticeTimer)
    noticeTimer = setTimeout(() => { pendingNotice.value = false }, 8000)
  }
}
</script>

<template>
  <div class="composer" :class="{ dragging }" @dragenter.prevent="onDragEnter" @dragover.prevent
    @dragleave="onDragLeave" @drop.prevent="onDrop">
    <div class="row">
      <span class="me">
        <UserAvatar :name="auth.user?.name" />
      </span>
      <textarea v-model="body" rows="2" :placeholder="placeholder" />
    </div>

    <!-- Poll editor -->
    <div v-if="mode === 'poll'" class="panel">
      <div class="panel-head">Poll options</div>
      <div v-for="(_, i) in pollOptions" :key="i" class="polrow">
        <input v-model="pollOptions[i]" type="text" :placeholder="`Option ${i + 1}`" maxlength="200">
        <button v-if="pollOptions.length > 2" class="x" type="button" title="Remove"
          @click="removePollOption(i)">×</button>
      </div>
      <div class="pollfoot">
        <button v-if="pollOptions.length < 8" class="addopt" type="button" @click="addPollOption">+ Add option</button>
        <label class="multi"><input v-model="allowMultiple" type="checkbox"> Allow multiple answers</label>
      </div>
    </div>

    <!-- Networking tags -->
    <div v-if="mode === 'looking_for' || mode === 'offering'" class="panel">
      <div class="panel-head">{{ mode === 'looking_for' ? 'Looking for' : 'Offering' }} — add tags</div>
      <div class="tagbox">
        <span v-for="(t, i) in tags" :key="t" class="tagchip">{{ t }}<button type="button"
            @click="removeTag(i)">×</button></span>
        <input v-model="tagInput" type="text" placeholder="Type a tag and press Enter" @keyup.enter="addTag">
      </div>
    </div>

    <!-- Media previews -->
    <div v-if="media.length" class="media-grid">
      <div v-for="m in media" :key="m.id" class="tile" :class="[m.kind, { error: m.status === 'error' }]">
        <img v-if="m.kind === 'image'" :src="m.preview" alt="">
        <video v-else-if="m.kind === 'video'" :src="m.preview" muted playsinline />
        <div v-else class="pdf">
          <svg viewBox="0 0 24 24">
            <path d="M7 3h8l4 4v14H7zM15 3v4h4" />
          </svg>
          <span>{{ m.name }}</span>
        </div>

        <!-- video play badge -->
        <span v-if="m.kind === 'video' && m.status === 'done'" class="play"><svg viewBox="0 0 24 24">
            <path d="M8 5v14l11-7z" />
          </svg></span>

        <!-- uploading overlay with a progress ring -->
        <div v-if="m.status === 'uploading'" class="ovl">
          <div class="ring" :style="{ '--p': m.progress }"><span>{{ m.progress }}%</span></div>
        </div>

        <!-- error overlay -->
        <div v-else-if="m.status === 'error'" class="ovl err" @click="retry(m)">
          <svg viewBox="0 0 24 24">
            <path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" />
          </svg>
          <small>Failed — retry</small>
        </div>

        <button class="tx" type="button" title="Remove" @click="removeItem(m)">×</button>
      </div>
    </div>

    <!-- validation errors -->
    <div v-if="errors.length" class="errs">
      <p v-for="(e, i) in errors" :key="i" class="errline">
        <svg viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="9" />
          <path d="M12 8v4M12 16h.01" />
        </svg>
        {{ e }}
      </p>
    </div>

    <div v-if="pendingNotice" class="pending-note">
      <svg viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9" />
        <path d="M12 7v5l3 3" />
      </svg>
      Your post was submitted and is awaiting organizer approval. It will appear on the feed once approved.
    </div>

    <div class="toolbar">
      <div class="tools">
        <button class="tool" type="button" title="Photo" @click="pick(ACCEPT_IMAGE)">
          <svg viewBox="0 0 24 24">
            <path d="M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5M9 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z" />
          </svg>
        </button>
        <button class="tool" type="button" title="Video" @click="pick(ACCEPT_VIDEO)">
          <svg viewBox="0 0 24 24">
            <path d="M3 6h13v12H3zM16 10l5-3v10l-5-3z" />
          </svg>
        </button>
        <button class="tool" type="button" title="PDF" @click="pick(ACCEPT_PDF)">
          <svg viewBox="0 0 24 24">
            <path d="M7 3h8l4 4v14H7zM15 3v4h4M9 13h6M9 17h6" />
          </svg>
        </button>
        <span class="sep" />
        <button class="tool" :class="{ on: mode === 'poll' }" type="button" title="Poll" @click="setMode('poll')">
          <svg viewBox="0 0 24 24">
            <path d="M5 21V10M12 21V4M19 21v-7" />
          </svg>
        </button>
        <button class="tool" :class="{ on: mode === 'looking_for' }" type="button" title="Looking for"
          @click="setMode('looking_for')">
          <svg viewBox="0 0 24 24">
            <path d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM21 21l-5-5" />
          </svg>
        </button>
        <button class="tool" :class="{ on: mode === 'offering' }" type="button" title="Offering"
          @click="setMode('offering')">
          <svg viewBox="0 0 24 24">
            <path
              d="M20 12v9H4v-9M2 7h20v5H2zM12 22V7M12 7H8a2 2 0 1 1 0-4c3 0 4 4 4 4zM12 7h4a2 2 0 1 0 0-4c-3 0-4 4-4 4z" />
          </svg>
        </button>
      </div>

      <div class="right">
        <select v-model="visibility" class="vis" title="Who can see this">
          <option value="attendees">All attendees</option>
          <option value="public">Public</option>
        </select>
        <button class="post" type="button" :disabled="!canPost" @click="submit">
          <svg v-if="uploadingCount" class="spin" viewBox="0 0 24 24">
            <path d="M12 3a9 9 0 1 0 9 9" />
          </svg>
          {{ feed.posting ? 'Posting…' : uploadingCount ? 'Uploading…' : 'Post' }}
        </button>
      </div>
    </div>

    <!-- Drag & drop overlay -->
    <div v-if="dragging" class="dropzone">
      <svg viewBox="0 0 24 24">
        <path d="M12 16V4M7 9l5-5 5 5M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3" />
      </svg>
      <strong>Drop to upload</strong>
      <small>Images, videos or PDF</small>
    </div>

    <input ref="fileInput" type="file" multiple class="hidden" @change="onInput">
  </div>
</template>

<style scoped>
.composer {
  position: relative;
  background: #fff;
  border: 1px solid #e6e7ed;
  border-radius: 12px;
  padding: 20px;
  transition: box-shadow .15s, border-color .15s;
}

.composer.dragging {
  border-color: var(--brand-primary);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand-primary) 13%, transparent);
}

.row {
  display: flex;
  gap: 14px;
  min-height: 62px;
}

.me {
  flex: 0 0 auto;
  width: 42px;
  height: 42px;
  border-radius: 10px;
  background: var(--brand-primary);
  color: #fff;
  font-weight: 700;
  font-size: .9rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

textarea {
  flex: 1;
  border: none;
  border-radius: 8px;
  padding: 10px 0;
  font: inherit;
  font-size: .92rem;
  resize: vertical;
  outline: none;
  color: #353942;
  min-height: 42px;
  padding-top: 0;
}

textarea::placeholder {
  color: #a3a5ab;
}

textarea:focus {
  background: #fcfcfe;
}

.panel {
  margin-top: 12px;
  border: 1px solid #eef0f3;
  border-radius: 12px;
  padding: 12px;
  background: #fafbfc;
}

.panel-head {
  font-size: .78rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .4px;
  margin-bottom: 10px;
}

.polrow {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.polrow input {
  flex: 1;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  padding: 9px 12px;
  font: inherit;
  font-size: .88rem;
  outline: none;
}

.polrow input:focus {
  border-color: var(--brand-primary);
}

.x {
  width: 28px;
  height: 28px;
  border: none;
  background: #f1f5f9;
  color: #64748b;
  border-radius: 8px;
  font-size: 1.1rem;
  line-height: 1;
  cursor: pointer;
}

.pollfoot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 4px;
}

.addopt {
  border: none;
  background: none;
  color: var(--brand-primary);
  font-weight: 700;
  font-size: .84rem;
  cursor: pointer;
}

.multi {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  color: #475569;
  font-size: .82rem;
}

.tagbox {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
  align-items: center;
}

.tagchip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: color-mix(in srgb, var(--brand-primary) 12%, #fff);
  color: var(--brand-primary);
  border-radius: 999px;
  padding: 4px 6px 4px 12px;
  font-size: .8rem;
  font-weight: 600;
}

.tagchip button {
  border: none;
  background: none;
  color: inherit;
  cursor: pointer;
  font-size: 1rem;
  line-height: 1;
}

.tagbox input {
  flex: 1;
  min-width: 160px;
  border: none;
  outline: none;
  font: inherit;
  font-size: .86rem;
  padding: 6px 4px;
}

/* ── Media grid ── */
.media-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(118px, 1fr));
  gap: 10px;
  margin-top: 14px;
}

.tile {
  position: relative;
  aspect-ratio: 1 / 1;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid #eef0f3;
  background: #f4f5f8;
}

.tile.error {
  border-color: #fecaca;
}

.tile img,
.tile video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.tile .pdf {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 100%;
  padding: 10px;
  color: #64748b;
  text-align: center;
}

.tile .pdf svg {
  width: 30px;
  height: 30px;
  fill: none;
  stroke: #ef4444;
  stroke-width: 1.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.tile .pdf span {
  font-size: .68rem;
  word-break: break-word;
  line-height: 1.2;
  max-height: 2.4em;
  overflow: hidden;
}

.play {
  position: absolute;
  inset: 0;
  margin: auto;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(15, 23, 42, .55);
  display: grid;
  place-items: center;
  pointer-events: none;
}

.play svg {
  width: 20px;
  height: 20px;
  fill: #fff;
  margin-left: 2px;
}

.ovl {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  background: rgba(15, 23, 42, .55);
}

.ring {
  position: relative;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: conic-gradient(#fff calc(var(--p, 0) * 1%), rgba(255, 255, 255, .3) 0);
}

.ring::before {
  content: '';
  position: absolute;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: rgba(15, 23, 42, .85);
}

.ring span {
  position: relative;
  color: #fff;
  font-size: .64rem;
  font-weight: 800;
}

.ovl.err {
  flex-direction: column;
  gap: 5px;
  background: rgba(153, 27, 27, .72);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.ovl.err svg {
  width: 24px;
  height: 24px;
  fill: none;
  stroke: #fff;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.ovl.err small {
  color: #fff;
  font-size: .7rem;
  font-weight: 700;
}

.tx {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 24px;
  height: 24px;
  border: none;
  border-radius: 50%;
  background: rgba(15, 23, 42, .7);
  color: #fff;
  cursor: pointer;
  font-size: 1rem;
  line-height: 1;
  z-index: 2;
}

.tx:hover {
  background: rgba(15, 23, 42, .9);
}

.errs {
  margin-top: 10px;
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.errline {
  display: flex;
  align-items: center;
  gap: 7px;
  margin: 0;
  color: #b91c1c;
  font-size: .82rem;
}

.errline svg {
  flex: 0 0 auto;
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.pending-note {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 12px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  color: #92400e;
  border-radius: 10px;
  padding: 10px 14px;
  font-size: .85rem;
}

.pending-note svg {
  flex: 0 0 auto;
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 10px;
  padding-top: 20px;
  border-top: 1px solid #e9eaf0;
  flex-wrap: wrap;
}

.tools {
  display: flex;
  align-items: center;
  gap: 16px;
}

.tool {
  width: 40px;
  height: 40px;
  padding: 0;
  border: none;
  background: #f7f7fb;
  border-radius: 10px;
  color: #6e7278;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: background .15s, color .15s;
}

.tool:hover {
  background: color-mix(in srgb, var(--brand-primary) 10%, #fff);
  color: var(--brand-primary);
}

.tool.on {
  background: var(--brand-primary);
  color: #fff;
}

.tool svg {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.sep {
  display: none;
}

.right {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-left: auto;
}

.vis {
  display: none;
}

.post {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 78px;
  height: 40px;
  padding: 0 18px;
  background: var(--brand-primary);
  color: #fff;
  border: none;
  border-radius: 10px;
  font: inherit;
  font-size: .9rem;
  font-weight: 700;
  cursor: pointer;
}

.post:disabled {
  background: #e6e7ed;
  color: #a4a6ac;
  cursor: default;
}

.spin {
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.2;
  stroke-linecap: round;
  animation: spin .8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.dropzone {
  position: absolute;
  inset: 0;
  z-index: 5;
  border-radius: 14px;
  background: color-mix(in srgb, var(--brand-primary) 8%, #fff);
  border: 2px dashed var(--brand-primary);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  color: var(--brand-primary);
  pointer-events: none;
}

.dropzone svg {
  width: 34px;
  height: 34px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.dropzone strong {
  font-size: 1rem;
}

.dropzone small {
  font-size: .8rem;
  opacity: .8;
}

.hidden {
  display: none;
}
</style>
