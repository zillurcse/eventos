<script setup lang="ts">
import type { Contest, ContestAttachment } from '~/stores/contests'

/**
 * Where the attendee enters a contest. In an "entry" contest that's a post with
 * photo/video; in a "response" contest it's a plain reply to the organizer's
 * post, so the media controls are hidden entirely.
 */
const props = defineProps<{ contest: Contest }>()

const contests = useContestsStore()

const body = ref('')
const media = ref<{ url: string, kind: 'image' | 'video', name: string | null }[]>([])
const uploading = ref(0)
const error = ref('')
const submitted = ref('')

const fileInput = ref<HTMLInputElement | null>(null)
const cameraInput = ref<HTMLInputElement | null>(null)

const takesMedia = computed(() =>
  props.contest.contest_type === 'entry'
  && (props.contest.allow_photos || props.contest.allow_videos || props.contest.allow_selfie),
)

const accept = computed(() => {
  const types: string[] = []
  if (props.contest.allow_photos || props.contest.allow_selfie) types.push('image/*')
  if (props.contest.allow_videos) types.push('video/*')
  return types.join(',')
})

const left = computed(() => props.contest.character_limit - body.value.length)

const canSubmit = computed(() =>
  !contests.submitting
  && uploading.value === 0
  && left.value >= 0
  && (body.value.trim().length > 0 || media.value.length > 0)
  && (!props.contest.attach_mandatory || media.value.length > 0),
)

const placeholder = computed(() =>
  props.contest.contest_type === 'entry'
    ? 'Describe your entry…'
    : 'Write your response…',
)

async function pick(e: Event) {
  const input = e.target as HTMLInputElement
  const files = Array.from(input.files ?? [])
  input.value = ''
  if (!files.length) return

  error.value = ''
  for (const file of files.slice(0, 5 - media.value.length)) {
    uploading.value++
    try {
      const up = await contests.uploadMedia(file)
      media.value.push({
        url: up.url,
        kind: (up.mime_type || file.type).startsWith('video') ? 'video' : 'image',
        name: up.filename ?? file.name,
      })
    } catch {
      error.value = 'That file couldn’t be uploaded.'
    } finally {
      uploading.value--
    }
  }
}

function drop(index: number) {
  media.value.splice(index, 1)
}

async function submit() {
  if (!canSubmit.value) return
  error.value = ''
  try {
    const entry = await contests.submitEntry(props.contest.id, {
      body: body.value.trim() || undefined,
      attachments: media.value as ContestAttachment[],
    })
    body.value = ''
    media.value = []
    if (entry.status === 'pending') {
      error.value = ''
      submitted.value = 'Submitted — the organizer will review it before it appears.'
    } else {
      submitted.value = 'Your entry is in. Good luck!'
    }
    setTimeout(() => { submitted.value = '' }, 6000)
  } catch (e: any) {
    error.value = e?.data?.errors?.body?.[0]
      || e?.data?.errors?.attachments?.[0]
      || e?.data?.message
      || 'Could not submit your entry.'
  }
}
</script>

<template>
  <section class="composer">
    <h2>{{ contest.contest_type === 'entry' ? 'Your entry' : 'Your response' }}</h2>

    <textarea
      v-model="body"
      :placeholder="placeholder"
      :maxlength="contest.character_limit"
      rows="3"
    />

    <div v-if="media.length" class="thumbs">
      <div v-for="(m, i) in media" :key="m.url" class="thumb">
        <video v-if="m.kind === 'video'" :src="m.url" muted />
        <img v-else :src="m.url" :alt="m.name || 'Attachment'">
        <button type="button" class="x" aria-label="Remove attachment" @click="drop(i)">×</button>
      </div>
    </div>

    <p v-if="contest.attach_mandatory && !media.length" class="hint req">
      This contest requires {{ contest.allow_videos ? 'a photo or video' : 'a photo' }}.
    </p>

    <div class="foot">
      <div class="tools">
        <template v-if="takesMedia">
          <button type="button" class="tool" :disabled="media.length >= 5" @click="fileInput?.click()">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="8.5" cy="9.5" r="1.5" /><path d="M21 16l-5-5L5 20" /></svg>
            {{ contest.allow_videos ? 'Photo / video' : 'Photo' }}
          </button>
          <button v-if="contest.allow_selfie" type="button" class="tool" :disabled="media.length >= 5" @click="cameraInput?.click()">
            <svg viewBox="0 0 24 24"><path d="M12 9a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM4 7h3l1.5-2h7L17 7h3v13H4z" /></svg>
            Selfie
          </button>
          <input ref="fileInput" type="file" class="sr" :accept="accept" multiple @change="pick">
          <input ref="cameraInput" type="file" class="sr" accept="image/*" capture="user" @change="pick">
        </template>
      </div>

      <div class="right">
        <span class="count" :class="{ over: left < 0 }">{{ left }}</span>
        <button type="button" class="send" :disabled="!canSubmit" @click="submit">
          {{ contests.submitting ? 'Submitting…' : uploading ? 'Uploading…' : 'Submit' }}
        </button>
      </div>
    </div>

    <p v-if="error" class="err">{{ error }}</p>
    <p v-else-if="submitted" class="ok">{{ submitted }}</p>
  </section>
</template>

<style scoped>
.composer { background: #fff; border-radius: 16px; padding: 16px 18px 18px; box-shadow: 0 1px 2px rgba(15,23,42,.05); margin-bottom: 18px; }
.composer h2 { margin: 0 0 10px; font-size: .95rem; font-weight: 800; color: #1e293b; }

textarea {
  width: 100%; border: 1px solid #e2e8f0; border-radius: 12px; padding: 11px 13px;
  font: inherit; font-size: .9rem; color: #1e293b; resize: vertical; min-height: 78px;
}
textarea:focus { outline: none; border-color: var(--brand-primary); }

.thumbs { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
.thumb { position: relative; width: 92px; height: 92px; border-radius: 10px; overflow: hidden; background: #f1f5f9; }
.thumb img, .thumb video { width: 100%; height: 100%; object-fit: cover; display: block; }
.x {
  position: absolute; top: 3px; right: 3px; width: 20px; height: 20px; border: none; border-radius: 50%;
  background: rgba(15,23,42,.7); color: #fff; font-size: .9rem; line-height: 1; cursor: pointer;
}

.hint { margin: 8px 0 0; font-size: .78rem; color: #94a3b8; }
.hint.req { color: #b45309; }

.foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 12px; flex-wrap: wrap; }
.tools { display: flex; gap: 8px; flex-wrap: wrap; }
.tool {
  display: inline-flex; align-items: center; gap: 6px; background: #f8fafc; border: 1px solid #e2e8f0;
  border-radius: 999px; padding: 7px 13px; font: inherit; font-size: .8rem; font-weight: 600;
  color: #475569; cursor: pointer;
}
.tool:hover:not(:disabled) { border-color: var(--brand-primary); color: var(--brand-primary); }
.tool:disabled { opacity: .5; cursor: default; }
.tool svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }

.right { display: flex; align-items: center; gap: 12px; margin-left: auto; }
.count { color: #94a3b8; font-size: .78rem; font-weight: 700; }
.count.over { color: #dc2626; }
.send {
  background: var(--brand-primary); color: #fff; border: none; border-radius: 999px;
  padding: 9px 24px; font: inherit; font-weight: 700; font-size: .85rem; cursor: pointer;
}
.send:disabled { opacity: .55; cursor: default; }

.sr { display: none; }
.err { margin: 10px 0 0; color: #dc2626; font-size: .82rem; }
.ok { margin: 10px 0 0; color: #15803d; font-size: .82rem; }
</style>
