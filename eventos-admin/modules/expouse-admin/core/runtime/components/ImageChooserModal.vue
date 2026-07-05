<script setup lang="ts">
const props = withDefaults(defineProps<{
  aspect: number
  outputWidth?: number
  outputHeight?: number
  collection?: string
  /** Listing endpoint for previously uploaded images; the Gallery tab only renders when provided. */
  galleryPath?: string
  title?: string
}>(), { title: 'Select image' })

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
    gallery.value = (res.data ?? []).map(i => ({ id: i.id, url: i.url ?? i.file_path })).filter(i => i.url)
    galleryLoaded.value = true
  } catch {
    error.value = 'Could not load the gallery.'
  } finally {
    galleryLoading.value = false
  }
})

function selectFromGallery(item: { id?: number, url: string }) {
  emit('selected', item)
  emit('close')
}
</script>

<template>
  <Modal :title="title" size="lg" @close="emit('close')">
    <div v-if="galleryPath" class="tabs">
      <button class="tab" :class="{ active: tab === 'upload' }" @click="tab = 'upload'">Upload</button>
      <button class="tab" :class="{ active: tab === 'gallery' }" @click="tab = 'gallery'">Gallery</button>
    </div>

    <!-- Upload tab -->
    <template v-if="tab === 'upload'">
      <label v-if="!pickedUrl" class="uploader h-[220px]!">
        <span>
          + Click to upload an image<br>
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
      <p v-if="galleryLoading" class="text-muted text-[.88rem] py-8 text-center">Loading gallery…</p>
      <p v-else-if="!gallery.length" class="text-muted text-[.88rem] py-8 text-center">No images uploaded yet.</p>
      <div v-else class="grid grid-cols-4 gap-2.5 max-h-[50vh] overflow-auto">
        <button
          v-for="(img, i) in gallery"
          :key="img.id ?? i"
          class="img-card aspect-square cursor-pointer p-0 border-line"
          @click="selectFromGallery(img)"
        >
          <img :src="img.url" alt="">
        </button>
      </div>
      <p v-if="error" class="error mt-3">{{ error }}</p>
      <div class="modal-actions">
        <button class="btn ghost" @click="emit('close')">Cancel</button>
      </div>
    </template>
  </Modal>
</template>
