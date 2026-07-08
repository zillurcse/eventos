<script setup lang="ts">
const props = withDefaults(defineProps<{
  aspect: number
  outputWidth?: number
  outputHeight?: number
  collection?: string
  /** Listing endpoint for previously uploaded images; the Gallery tab only renders when provided. */
  galleryPath?: string
  title?: string
}>(), { title: 'Select Images for Gallery' })

const emit = defineEmits<{
  (e: 'selected', v: { id?: number, url: string }): void
  (e: 'close'): void
}>()

const api = useApi()
const { upload } = useUpload()

const tab = ref<'upload' | 'gallery'>('upload')
const error = ref('')
const busy = ref(false)

// ── Upload tab: pick → crop in place → upload ─────────────
const pickedUrl = ref('')
const cropperEl = ref<{ crop: () => Promise<Blob> }>()
let objectUrl: string | null = null

function pick(e: Event) {
  const input = e.target as HTMLInputElement
  const f = input.files?.[0]
  input.value = ''
  if (!f) return
  const err = validateImageFile(f)
  if (err) { error.value = err; return }
  error.value = ''
  clearPick()
  objectUrl = URL.createObjectURL(f)
  pickedUrl.value = objectUrl
}

function clearPick() {
  if (objectUrl) URL.revokeObjectURL(objectUrl)
  objectUrl = null
  pickedUrl.value = ''
}

onBeforeUnmount(clearPick)

async function confirmUpload() {
  if (!cropperEl.value) return
  busy.value = true
  error.value = ''
  try {
    const blob = await cropperEl.value.crop()
    const file = new File([blob], 'image.jpg', { type: 'image/jpeg' })
    const r = await upload(file, { collection: props.collection })
    emit('selected', r)
    emit('close')
  } catch (e: any) {
    error.value = e?.data?.message || e?.message || 'Could not upload the image.'
  } finally {
    busy.value = false
  }
}

// ── Gallery tab ───────────────────────────────────────────
const gallery = ref<{ id?: number, url: string }[]>([])
const galleryLoading = ref(false)
const galleryLoaded = ref(false)

watch(tab, async (t) => {
  if (t !== 'gallery' || !props.galleryPath || galleryLoaded.value) return
  galleryLoading.value = true
  try {
    const res = await api<{ data: any[] }>(props.galleryPath)
    gallery.value = (res.data ?? []).map(i => ({ id: i.file_id ?? i.id, url: i.url ?? i.file_path })).filter(i => i.url)
    galleryLoaded.value = true
  } catch {
    error.value = 'Could not load the gallery.'
  } finally {
    galleryLoading.value = false
  }
})

const selectedGalleryUrl = ref('')

function toggleGallerySelection(item: { id?: number, url: string }) {
  selectedGalleryUrl.value = selectedGalleryUrl.value === item.url ? '' : item.url
}

function confirmGallerySelection() {
  const item = gallery.value.find(i => i.url === selectedGalleryUrl.value)
  if (!item) return
  emit('selected', item)
  emit('close')
}
</script>

<template>
  <Modal
    :title="title"
    :subtitle="galleryPath ? 'Upload a new image, or choose one you\'ve already uploaded to the gallery.' : 'Upload a new image.'"
    size="lg"
    @close="emit('close')"
  >
    <div v-if="galleryPath" class="inline-flex p-1 rounded-xl bg-[#f1f1f5] gap-1 mt-4 mb-5">
      <button
        type="button"
        class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-[.85rem] font-semibold border-0 cursor-pointer transition-colors"
        :class="tab === 'upload' ? 'bg-white text-ink shadow-sm' : 'bg-transparent text-muted hover:text-ink'"
        @click="tab = 'upload'"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/></svg>
        Upload
      </button>
      <button
        type="button"
        class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-[.85rem] font-semibold border-0 cursor-pointer transition-colors"
        :class="tab === 'gallery' ? 'bg-white text-ink shadow-sm' : 'bg-transparent text-muted hover:text-ink'"
        @click="tab = 'gallery'"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
        Gallery
      </button>
    </div>

    <!-- Upload tab -->
    <template v-if="tab === 'upload'">
      <label v-if="!pickedUrl" class="uploader h-[220px]! flex-col gap-2.5">
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="opacity-60">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/>
        </svg>
        <span>
          <span class="font-semibold text-ink">Click to upload</span> an image<br>
          <span class="text-[.78rem] text-faint">JPEG, PNG or WebP · max 10 MB</span>
        </span>
        <input type="file" accept="image/jpeg,image/png,image/webp" @change="pick">
      </label>
      <template v-else>
        <ImageCropper
          ref="cropperEl"
          :src="pickedUrl"
          :aspect="aspect"
          :output-width="outputWidth"
          :output-height="outputHeight"
        />
      </template>
      <p v-if="error" class="error mt-3">{{ error }}</p>
      <div class="modal-actions">
        <button v-if="pickedUrl" class="btn ghost" :disabled="busy" @click="clearPick">Back</button>
        <button v-else class="btn ghost" @click="emit('close')">Cancel</button>
        <button v-if="pickedUrl" class="btn" :disabled="busy" @click="confirmUpload">
          {{ busy ? 'Uploading…' : 'Crop & upload' }}
        </button>
      </div>
    </template>

    <!-- Gallery tab -->
    <template v-else>
      <!-- Loading skeleton -->
      <div v-if="galleryLoading" class="grid grid-cols-4 gap-2.5">
        <div v-for="n in 8" :key="n" class="aspect-square rounded-xl bg-[#f1f1f5] animate-pulse" />
      </div>

      <!-- Empty state -->
      <div v-else-if="!gallery.length" class="flex flex-col items-center justify-center gap-2.5 py-14 text-center">
        <div class="w-11 h-11 rounded-xl bg-[#f1f1f5] grid place-items-center text-muted">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
        </div>
        <p class="text-muted text-[.88rem] m-0">No images uploaded yet.</p>
        <button class="btn sm ghost" @click="tab = 'upload'">Upload one</button>
      </div>

      <!-- Grid -->
      <template v-else>
        <div class="flex items-center justify-between mb-3">
          <p class="text-muted text-[.8rem] m-0">{{ gallery.length }} image{{ gallery.length !== 1 ? 's' : '' }}</p>
        </div>
        <div class="grid grid-cols-4 gap-2.5 max-h-[50vh] overflow-auto p-0.5">
          <button
            v-for="(img, i) in gallery"
            :key="img.id ?? i"
            class="img-card aspect-square cursor-pointer p-0 transition-all"
            :class="selectedGalleryUrl === img.url ? 'ring-2 ring-brand ring-offset-1' : 'border-line hover:opacity-90'"
            @click="toggleGallerySelection(img)"
          >
            <img :src="img.url" alt="" loading="lazy">
            <div class="absolute inset-x-0 bottom-0 h-1/3 bg-gradient-to-t from-black/35 to-transparent pointer-events-none" />
            <div
              class="absolute top-1.5 right-1.5 w-5 h-5 rounded-full grid place-items-center transition-all"
              :class="selectedGalleryUrl === img.url ? 'bg-brand text-white' : 'bg-white/70 text-transparent'"
            >
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            </div>
          </button>
        </div>
      </template>

      <p v-if="error" class="error mt-3">{{ error }}</p>
      <div class="modal-actions">
        <button class="btn ghost" @click="emit('close')">Cancel</button>
        <button v-if="gallery.length" class="btn" :disabled="!selectedGalleryUrl" @click="confirmGallerySelection">Select</button>
      </div>
    </template>
  </Modal>
</template>
