<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

type CtaType = 'image' | 'video' | 'text'

interface VideoLink {
  platform: string
  url: string
  caption: string
}

interface Cta {
  id: string
  type: CtaType
  title: string | null
  description: string | null
  button_label: string | null
  button_link: string | null
  image_file_id: number | null
  image_url: string | null
  videos: VideoLink[]
  position: number
}

interface DraftShape {
  type: CtaType
  title: string
  description: string
  button_label: string
  button_link: string
  image_file_id: number | null
  image_url: string | null
  videos: VideoLink[]
}

const VIDEO_PLATFORMS = ['Youtube', 'Vimeo', 'Facebook', 'Other']

const ctas = ref<Cta[]>([])
const drawerOpen = ref(false)
const editingId = ref<string | null>(null)
const saving = ref(false)
const error = ref('')
const descRef = ref<HTMLElement | null>(null)

function freshDraft(): DraftShape {
  return {
    type: 'image',
    title: '',
    description: '',
    button_label: '',
    button_link: '',
    image_file_id: null,
    image_url: null,
    videos: [],
  }
}

const draft = reactive<DraftShape>(freshDraft())

function stripHtml(html: string): string {
  return html.replace(/<[^>]*>/g, '')
}

const canSave = computed(() => {
  if (saving.value) return false
  if (draft.type === 'image') return !!draft.image_file_id
  if (draft.type === 'video') return draft.videos.some((v: VideoLink) => v.url.trim())
  return !!draft.title.trim() || !!stripHtml(draft.description).trim()
})

async function load() {
  try {
    const res = await api<{ data: Cta[] }>(`/events/${id}/ctas`)
    ctas.value = res.data
  } catch { /* */ }
}

function openAdd() {
  Object.assign(draft, freshDraft())
  editingId.value = null
  error.value = ''
  drawerOpen.value = true
  syncDescEditor()
}

function openEdit(c: Cta) {
  Object.assign(draft, {
    type: c.type,
    title: c.title ?? '',
    description: c.description ?? '',
    button_label: c.button_label ?? '',
    button_link: c.button_link ?? '',
    image_file_id: c.image_file_id,
    image_url: c.image_url,
    videos: (c.videos ?? []).map((v: VideoLink) => ({ ...v })),
  })
  editingId.value = c.id
  error.value = ''
  drawerOpen.value = true
  syncDescEditor()
}

// Push the current description HTML into the contenteditable surface once it
// is mounted (drawer is v-if, so wait a tick).
function syncDescEditor() {
  nextTick(() => {
    if (descRef.value && descRef.value.innerHTML !== draft.description) {
      descRef.value.innerHTML = draft.description
    }
  })
}

watch(() => draft.type, (t: CtaType) => {
  if (t === 'text') syncDescEditor()
})

function fmtDesc(cmd: string) {
  document.execCommand(cmd, false)
  if (descRef.value) draft.description = descRef.value.innerHTML
}

function onDescInput() {
  if (descRef.value) draft.description = descRef.value.innerHTML
}

function addVideo() {
  draft.videos.push({ platform: 'Youtube', url: '', caption: '' })
}

function removeVideo(i: number) {
  draft.videos.splice(i, 1)
}

async function saveDraft() {
  if (!canSave.value) return
  error.value = ''
  saving.value = true
  try {
    const payload: Record<string, unknown> = { type: draft.type }
    if (draft.type === 'image') {
      payload.title = draft.title.trim() || null
      payload.button_label = draft.button_label.trim() || null
      payload.button_link = draft.button_link.trim() || null
      payload.image_file_id = draft.image_file_id
    } else if (draft.type === 'video') {
      payload.videos = draft.videos
        .filter((v: VideoLink) => v.url.trim())
        .map((v: VideoLink) => ({ platform: v.platform, url: v.url.trim(), caption: v.caption.trim() }))
    } else {
      payload.title = draft.title.trim() || null
      payload.description = draft.description || null
      payload.button_label = draft.button_label.trim() || null
      payload.button_link = draft.button_link.trim() || null
    }

    if (editingId.value) {
      const res = await api<{ data: Cta }>(`/events/${id}/ctas/${editingId.value}`, { method: 'PUT', body: payload })
      const i = ctas.value.findIndex((c: Cta) => c.id === editingId.value)
      if (i >= 0) ctas.value[i] = res.data
    } else {
      const res = await api<{ data: Cta }>(`/events/${id}/ctas`, { method: 'POST', body: payload })
      ctas.value.push(res.data)
    }
    drawerOpen.value = false
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save CTA.'
  } finally {
    saving.value = false
  }
}

async function removeCta(c: Cta) {
  if (!confirm('Remove this CTA?')) return
  try {
    await api(`/events/${id}/ctas/${c.id}`, { method: 'DELETE' })
    ctas.value = ctas.value.filter((x: Cta) => x.id !== c.id)
  } catch { /* */ }
}

function typeBadge(t: CtaType) {
  return { image: 'Image', video: 'Video', text: 'Text' }[t]
}

function summary(c: Cta): string {
  if (c.type === 'video') return `${c.videos?.length || 0} video link${(c.videos?.length || 0) === 1 ? '' : 's'}`
  if (c.type === 'text') return stripHtml(c.description || '').slice(0, 80) || '—'
  return c.button_label || '—'
}

onMounted(load)
</script>

<template>
  <div class="max-w-[860px]">
    <div class="mb-4">
      <h2 class="section-title m-0">CTA</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Sponsor calls-to-action shown across your event website and app.</p>
    </div>

    <div class="card">
      <div class="flex items-center justify-between gap-4 mb-5">
        <div>
          <div class="font-bold text-base">Sponsor CTAs</div>
          <div class="muted text-[.84rem]">Add image, video or text calls-to-action.</div>
        </div>
        <button class="btn" @click="openAdd">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
          ADD CTA
        </button>
      </div>

      <div v-if="ctas.length" class="flex flex-col gap-2.5">
        <div
          v-for="c in ctas" :key="c.id"
          class="flex items-center gap-3 border border-line rounded-xl px-4 py-3 bg-white"
        >
          <div class="w-16 h-12 rounded-lg overflow-hidden shrink-0 bg-[#f3f4f6] border border-line flex items-center justify-center text-muted">
            <img v-if="c.type === 'image' && c.image_url" :src="c.image_url" alt="" class="w-full h-full object-cover">
            <svg v-else-if="c.type === 'video'" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
            <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="14" y2="18"/></svg>
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="font-semibold text-ink truncate">{{ c.title || 'Untitled CTA' }}</span>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[.72rem] font-semibold bg-[#f3f0ff] text-[#6352e7]">{{ typeBadge(c.type) }}</span>
            </div>
            <div class="muted text-[.82rem] truncate">{{ summary(c) }}</div>
          </div>
          <button class="bg-transparent border-0 cursor-pointer text-base px-2 py-1 text-brand" title="Edit" @click="openEdit(c)">✎</button>
          <button class="bg-transparent border-0 cursor-pointer text-base px-2 py-1 text-[#dc2626]" title="Remove" @click="removeCta(c)">🗑</button>
        </div>
      </div>

      <p v-else class="muted text-[.86rem] py-10 text-center">
        No CTAs yet. Click <strong>+ ADD CTA</strong> to create one.
      </p>
    </div>

    <!-- Add / Edit drawer -->
    <Drawer v-if="drawerOpen" :title="editingId ? 'Edit CTA' : 'Add CTA'" @close="drawerOpen = false">
      <label>Select CTA Type</label>
      <select v-model="draft.type">
        <option value="image">Image</option>
        <option value="video">Video</option>
        <option value="text">Text</option>
      </select>

      <!-- IMAGE -->
      <template v-if="draft.type === 'image'">
        <label class="mt-4">Sponsor CTA</label>
        <input v-model="draft.title" placeholder="CTA Title">

        <label>CTA Button Label</label>
        <input v-model="draft.button_label" placeholder="CTA Button Label">

        <label>CTA Button Link</label>
        <input v-model="draft.button_link" placeholder="CTA Button Link">

        <label>
          CTA Banner
          <span class="text-[#dc2626] ml-0.5">*</span>
        </label>
        <UploadButton
          :preview="draft.image_url ?? undefined"
          collection="ctas"
          @uploaded="(v: any) => { draft.image_file_id = v.id; draft.image_url = v.url }"
        />
        <p class="muted text-[.82rem] -mt-2 mb-4">Recommended size 320×200 px.</p>
      </template>

      <!-- VIDEO -->
      <template v-else-if="draft.type === 'video'">
        <div class="flex items-center justify-between mt-4 mb-1.5">
          <label class="m-0">CTA Videos</label>
          <button type="button" class="text-[#6352e7] text-[.84rem] font-semibold bg-transparent border-0 cursor-pointer" @click="addVideo">
            ADD VIDEO LINK
          </button>
        </div>

        <div v-for="(v, i) in draft.videos" :key="i" class="mb-3">
          <div class="relative border border-line rounded-xl p-3">
            <button
              type="button"
              class="absolute -top-2 -right-2 w-5 h-5 rounded-full bg-[#dc2626] text-white text-xs leading-none flex items-center justify-center cursor-pointer border-0"
              title="Remove"
              @click="removeVideo(i)"
            >×</button>
            <div class="flex gap-2">
              <select v-model="v.platform" class="m-0 w-[130px] shrink-0">
                <option v-for="p in VIDEO_PLATFORMS" :key="p" :value="p">{{ p }}</option>
              </select>
              <input v-model="v.url" class="m-0 flex-1" placeholder="Enter URL">
            </div>
            <input v-model="v.caption" class="m-0 mt-2" placeholder="Enter Video Caption">
          </div>
        </div>

        <p v-if="!draft.videos.length" class="muted text-[.84rem] py-2">
          No videos yet. Click <strong>ADD VIDEO LINK</strong>.
        </p>
      </template>

      <!-- TEXT -->
      <template v-else>
        <label class="mt-4">Sponsor CTA</label>
        <input v-model="draft.title" placeholder="CTA Title">

        <label>Description</label>
        <div class="border border-line rounded-xl overflow-hidden my-1.5">
          <div class="flex items-center gap-0.5 px-3 py-2 bg-[#f7f8fa] border-b border-line">
            <button type="button" class="w-7 h-7 font-bold text-ink hover:bg-line rounded text-[.9rem]" @click="fmtDesc('bold')">B</button>
            <button type="button" class="w-7 h-7 italic text-ink hover:bg-line rounded text-[.9rem]" @click="fmtDesc('italic')">I</button>
            <button type="button" class="w-7 h-7 underline text-ink hover:bg-line rounded text-[.9rem]" @click="fmtDesc('underline')">U</button>
            <button type="button" class="w-7 h-7 line-through text-ink hover:bg-line rounded text-[.9rem]" @click="fmtDesc('strikeThrough')">S</button>
          </div>
          <div
            ref="descRef"
            contenteditable="true"
            class="min-h-40 p-3 text-[.93rem] text-ink outline-none"
            data-placeholder="Let's write an awesome story!"
            @input="onDescInput"
          />
        </div>

        <label>CTA Button Label</label>
        <input v-model="draft.button_label" placeholder="CTA Button Label">

        <label>CTA Button Link</label>
        <input v-model="draft.button_link" placeholder="CTA Button Link">
      </template>

      <p v-if="error" class="error mt-3">{{ error }}</p>

      <div class="modal-actions">
        <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
        <button class="btn" :disabled="!canSave" @click="saveDraft">
          {{ saving ? 'Saving…' : editingId ? 'UPDATE' : 'ADD' }}
        </button>
      </div>
    </Drawer>
  </div>
</template>

<style scoped>
[contenteditable][data-placeholder]:empty::before {
  content: attr(data-placeholder);
  color: #9ca3af;
  font-style: italic;
}
</style>
