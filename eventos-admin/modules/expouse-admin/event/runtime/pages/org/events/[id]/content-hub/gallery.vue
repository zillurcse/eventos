<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const { upload } = useUpload()
const id = route.params.id as string

interface GalleryImage {
  id: string
  file_id: number | null
  url: string
  caption: string | null
  album: string
  sort_order: number
  is_featured: boolean
  created_at: string | null
}

const images = ref<GalleryImage[]>([])
const activeAlbum = ref<string>('All')
const saved = ref(false)
const uploading = ref(false)
const uploadProgress = reactive({ done: 0, total: 0 })
const isDragOver = ref(false)

// edit-caption drawer
const drawerOpen = ref(false)
const editing = reactive<{ id: string, caption: string, album: string, is_featured: boolean }>({ id: '', caption: '', album: '', is_featured: false })

// lightbox
const lightboxIndex = ref<number | null>(null)

// drag reorder
const dragIndex = ref<number | null>(null)

const albums = computed(() => {
  const set = new Set<string>()
  images.value.forEach((i: GalleryImage) => set.add(i.album || 'General'))
  return ['All', ...Array.from(set).sort()]
})

const filtered = computed(() => {
  if (activeAlbum.value === 'All') return images.value
  return images.value.filter((i: GalleryImage) => (i.album || 'General') === activeAlbum.value)
})

const lightboxImage = computed(() =>
  lightboxIndex.value !== null ? filtered.value[lightboxIndex.value] : null,
)

function flash() {
  saved.value = true; setTimeout(() => (saved.value = false), 1500)
}

async function load() {
  try {
    const res = await api<{ data: GalleryImage[] }>(`/events/${id}/gallery`)
    images.value = res.data
  } catch { /* */ }
}

// ── Upload (multi-file, drag & drop) ──────────────────────────────────────────

async function handleFiles(files: FileList | File[]) {
  const list = Array.from(files).filter(f => f.type.startsWith('image/'))
  if (!list.length) return

  uploading.value = true
  uploadProgress.done = 0
  uploadProgress.total = list.length
  const payload: { file_id: number, url: string, album?: string }[] = []
  const targetAlbum = activeAlbum.value !== 'All' ? activeAlbum.value : undefined

  for (const f of list) {
    try {
      const r = await upload(f, { collection: 'cover' })
      payload.push({ file_id: r.id, url: r.url, ...(targetAlbum ? { album: targetAlbum } : {}) })
    } catch { /* skip failed file */ }
    uploadProgress.done++
  }

  if (payload.length) {
    try {
      const res = await api<{ data: GalleryImage[] }>(`/events/${id}/gallery`, { method: 'POST', body: { images: payload } })
      images.value.push(...res.data)
      flash()
    } catch { /* */ }
  }
  uploading.value = false
}

function onFileInput(e: Event) {
  const files = (e.target as HTMLInputElement).files
  if (files) handleFiles(files)
  ;(e.target as HTMLInputElement).value = ''
}

function onDrop(e: DragEvent) {
  isDragOver.value = false
  if (e.dataTransfer?.files?.length) handleFiles(e.dataTransfer.files)
}

// ── Edit / feature / delete ───────────────────────────────────────────────────

function openEdit(img: GalleryImage) {
  Object.assign(editing, { id: img.id, caption: img.caption ?? '', album: img.album === 'General' ? '' : img.album, is_featured: img.is_featured })
  drawerOpen.value = true
}

async function saveEdit() {
  try {
    const res = await api<{ data: GalleryImage }>(`/events/${id}/gallery/${editing.id}`, {
      method: 'PATCH',
      body: { caption: editing.caption.trim() || null, album: editing.album.trim() || null, is_featured: editing.is_featured },
    })
    const i = images.value.findIndex((x: GalleryImage) => x.id === editing.id)
    if (i >= 0) images.value[i] = res.data
    drawerOpen.value = false
    flash()
  } catch { /* */ }
}

async function toggleFeature(img: GalleryImage) {
  try {
    const res = await api<{ data: GalleryImage }>(`/events/${id}/gallery/${img.id}`, { method: 'PATCH', body: { is_featured: !img.is_featured } })
    const i = images.value.findIndex((x: GalleryImage) => x.id === img.id)
    if (i >= 0) images.value[i] = res.data
  } catch { /* */ }
}

async function removeImage(img: GalleryImage) {
  if (!confirm('Remove this image from the gallery?')) return
  try {
    await api(`/events/${id}/gallery/${img.id}`, { method: 'DELETE' })
    images.value = images.value.filter((x: GalleryImage) => x.id !== img.id)
  } catch { /* */ }
}

// ── Drag reorder (only meaningful when viewing "All") ─────────────────────────

function onCardDragStart(globalIndex: number) { dragIndex.value = globalIndex }

function onCardDragOver(globalIndex: number, e: DragEvent) {
  e.preventDefault()
  if (dragIndex.value === null || dragIndex.value === globalIndex) return
  const arr = [...images.value]
  const [moved] = arr.splice(dragIndex.value, 1)
  arr.splice(globalIndex, 0, moved)
  images.value = arr
  dragIndex.value = globalIndex
}

async function onCardDragEnd() {
  dragIndex.value = null
  try {
    await api(`/events/${id}/gallery/reorder`, { method: 'POST', body: { order: images.value.map((i: GalleryImage) => i.id) } })
    flash()
  } catch { /* */ }
}

// global index helper (drag reorder operates on the full list)
function globalIndexOf(img: GalleryImage): number {
  return images.value.findIndex((x: GalleryImage) => x.id === img.id)
}

// ── Lightbox ──────────────────────────────────────────────────────────────────

function openLightbox(i: number) { lightboxIndex.value = i }
function closeLightbox() { lightboxIndex.value = null }
function prevLightbox() {
  if (lightboxIndex.value === null) return
  lightboxIndex.value = (lightboxIndex.value - 1 + filtered.value.length) % filtered.value.length
}
function nextLightbox() {
  if (lightboxIndex.value === null) return
  lightboxIndex.value = (lightboxIndex.value + 1) % filtered.value.length
}

function onKey(e: KeyboardEvent) {
  if (lightboxIndex.value === null) return
  if (e.key === 'Escape') closeLightbox()
  if (e.key === 'ArrowLeft') prevLightbox()
  if (e.key === 'ArrowRight') nextLightbox()
}

onMounted(() => { load(); window.addEventListener('keydown', onKey) })
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div>
    <div class="mb-4 flex items-end justify-between gap-4 flex-wrap">
      <div>
        <h2 class="section-title m-0">
          Image Gallery
          <span v-if="saved" class="badge active ml-2">saved ✓</span>
        </h2>
        <p class="muted text-[.86rem] mt-0.5 mb-0">Upload event photos, organize them into albums and reorder by dragging.</p>
      </div>
      <label class="btn cursor-pointer">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
        UPLOAD IMAGES
        <input type="file" accept="image/*" multiple class="hidden" @change="onFileInput">
      </label>
    </div>

    <!-- Album filter tabs -->
    <div v-if="images.length" class="flex flex-wrap gap-2 mb-4">
      <button
        v-for="a in albums" :key="a"
        class="px-3.5 py-1.5 rounded-full border text-sm font-medium transition-all duration-150"
        :class="activeAlbum === a
          ? 'border-[#6352e7] bg-[#f3f0ff] text-[#6352e7]'
          : 'border-line bg-white text-muted hover:border-[#6352e7] hover:text-[#6352e7]'"
        @click="activeAlbum = a"
      >
        {{ a }}
        <span class="ml-1 text-[.78rem] opacity-70">
          {{ a === 'All' ? images.length : images.filter((i: GalleryImage) => (i.album || 'General') === a).length }}
        </span>
      </button>
    </div>

    <!-- Drop zone -->
    <div
      class="rounded-2xl border-2 border-dashed transition-colors duration-150 mb-5 p-6 text-center"
      :class="isDragOver ? 'border-[#6352e7] bg-[#f3f0ff]' : 'border-line bg-[#fafafb]'"
      @dragover.prevent="isDragOver = true"
      @dragleave.prevent="isDragOver = false"
      @drop.prevent="onDrop"
    >
      <div v-if="uploading" class="flex flex-col items-center gap-2">
        <div class="font-semibold text-[#6352e7]">Uploading {{ uploadProgress.done }} / {{ uploadProgress.total }}…</div>
        <div class="w-[220px] h-1.5 rounded-full bg-[#e7e5f3] overflow-hidden">
          <div class="h-full bg-[#6352e7] transition-all duration-200" :style="{ width: `${(uploadProgress.done / Math.max(uploadProgress.total, 1)) * 100}%` }" />
        </div>
      </div>
      <div v-else class="text-muted text-[.9rem]">
        <strong class="text-[#6352e7]">Drag &amp; drop</strong> images here, or use the Upload button.
        <span v-if="activeAlbum !== 'All'" class="block text-[.82rem] mt-1">New images will be added to album “{{ activeAlbum }}”.</span>
      </div>
    </div>

    <!-- Grid -->
    <div v-if="filtered.length" class="grid grid-cols-[repeat(auto-fill,minmax(180px,1fr))] gap-3">
      <div
        v-for="(img, i) in filtered" :key="img.id"
        class="group relative aspect-square rounded-xl overflow-hidden bg-[#f3f4f6] border border-line cursor-pointer"
        :class="{ 'ring-2 ring-[#6352e7] opacity-60': dragIndex === globalIndexOf(img) }"
        draggable="true"
        @dragstart="onCardDragStart(globalIndexOf(img))"
        @dragover="onCardDragOver(globalIndexOf(img), $event)"
        @dragend="onCardDragEnd"
        @click="openLightbox(i)"
      >
        <img :src="img.url" :alt="img.caption || ''" class="w-full h-full object-cover" loading="lazy">

        <!-- featured star -->
        <div v-if="img.is_featured" class="absolute top-2 left-2 w-6 h-6 rounded-full bg-[rgba(0,0,0,.45)] grid place-items-center text-[#f59e0b] text-sm">★</div>

        <!-- caption strip -->
        <div v-if="img.caption" class="absolute bottom-0 left-0 right-0 px-2.5 py-1.5 bg-[rgba(0,0,0,.5)] text-white text-xs truncate">
          {{ img.caption }}
        </div>

        <!-- hover overlay -->
        <div class="absolute inset-0 bg-[rgba(0,0,0,.45)] opacity-0 group-hover:opacity-100 transition-opacity duration-150 flex items-center justify-center gap-1.5" @click.stop>
          <button class="overlay-btn" :title="img.is_featured ? 'Unfeature' : 'Feature'" @click="toggleFeature(img)">
            <span :class="img.is_featured ? 'text-[#f59e0b]' : 'text-white'">★</span>
          </button>
          <button class="overlay-btn" title="Edit" @click="openEdit(img)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
          </button>
          <button class="overlay-btn danger" title="Remove" @click="removeImage(img)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
          </button>
        </div>
      </div>
    </div>

    <p v-else-if="!uploading" class="muted text-[.86rem] py-10 text-center">
      {{ images.length ? 'No images in this album.' : 'No images yet. Drag photos above or click UPLOAD IMAGES.' }}
    </p>

    <!-- Edit drawer -->
    <Drawer v-if="drawerOpen" title="Edit Image" @close="drawerOpen = false">
      <label>Caption</label>
      <input v-model="editing.caption" placeholder="Optional caption">

      <label>Album</label>
      <input v-model="editing.album" placeholder="e.g. Day 1, Keynotes (blank = General)" list="album-list">
      <datalist id="album-list">
        <option v-for="a in albums.filter((a: string) => a !== 'All')" :key="a" :value="a" />
      </datalist>
      <p class="muted text-[.82rem] -mt-2 mb-4">Group related photos under the same album name.</p>

      <label class="flex items-center gap-3 cursor-pointer select-none mb-2">
        <input v-model="editing.is_featured" type="checkbox" class="w-4.5 h-4.5 m-0 accent-brand">
        <span class="text-[.93rem] font-medium text-ink">Featured image</span>
      </label>

      <div class="modal-actions">
        <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
        <button class="btn" @click="saveEdit">SAVE</button>
      </div>
    </Drawer>

    <!-- Lightbox -->
    <div
      v-if="lightboxImage"
      class="fixed inset-0 z-[300] bg-[rgba(10,10,20,.92)] flex items-center justify-center"
      @click.self="closeLightbox"
    >
      <button class="lb-nav left-4" title="Previous" @click.stop="prevLightbox">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
      </button>

      <div class="max-w-[88vw] max-h-[88vh] flex flex-col items-center gap-3">
        <img :src="lightboxImage.url" :alt="lightboxImage.caption || ''" class="max-w-full max-h-[80vh] object-contain rounded-lg shadow-2xl">
        <div class="text-white/90 text-sm text-center">
          <span v-if="lightboxImage.caption">{{ lightboxImage.caption }} · </span>
          <span class="text-white/50">{{ (lightboxIndex ?? 0) + 1 }} / {{ filtered.length }}</span>
        </div>
      </div>

      <button class="lb-nav right-4" title="Next" @click.stop="nextLightbox">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
      </button>

      <button class="lb-nav top-4 right-4 !w-10 !h-10" title="Close" @click.stop="closeLightbox">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
  </div>
</template>

<style scoped>
.hidden { display: none; }

.overlay-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 9px;
  background: rgba(255, 255, 255, .15);
  color: #fff;
  border: none;
  cursor: pointer;
  backdrop-filter: blur(2px);
  transition: background .15s;
}
.overlay-btn:hover { background: rgba(255, 255, 255, .3); }
.overlay-btn.danger:hover { background: #dc2626; }

.lb-nav {
  position: absolute;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 46px;
  height: 46px;
  border-radius: 50%;
  background: rgba(255, 255, 255, .12);
  color: #fff;
  border: none;
  cursor: pointer;
  transition: background .15s;
}
.lb-nav:hover { background: rgba(255, 255, 255, .25); }
</style>
