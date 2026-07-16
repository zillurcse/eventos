<script setup lang="ts">
const props = withDefaults(defineProps<{
  /** Existing image URL to re-crop… */
  src?: string | null
  /** …or a freshly picked file to crop before its first upload. */
  file?: File | null
  aspect?: number
  outputWidth?: number
  outputHeight?: number
  collection?: string
  title?: string
}>(), { src: null, file: null, title: 'Crop image' })

const emit = defineEmits<{
  (e: 'done', v: { id: number, url: string }): void
  (e: 'close'): void
}>()

const { upload } = useUpload()

const stageSrc = ref('')
const busy = ref(false)
const error = ref('')
const cropperEl = ref<{ crop: () => Promise<Blob> }>()

let objectUrl: string | null = null

onMounted(async () => {
  if (props.file) {
    objectUrl = URL.createObjectURL(props.file)
    stageSrc.value = objectUrl
  } else if (props.src) {
    // Pull the remote image into a local blob when possible so the canvas
    // export isn't blocked by cross-origin tainting.
    try {
      const blob = await (await fetch(props.src)).blob()
      objectUrl = URL.createObjectURL(blob)
      stageSrc.value = objectUrl
    } catch {
      stageSrc.value = props.src
    }
  }
})

onBeforeUnmount(() => { if (objectUrl) URL.revokeObjectURL(objectUrl) })

async function confirm() {
  if (!cropperEl.value) return
  busy.value = true
  error.value = ''
  try {
    const blob = await cropperEl.value.crop()
    const file = new File([blob], 'image.jpg', { type: 'image/jpeg' })
    const r = await upload(file, { collection: props.collection })
    emit('done', r)
    emit('close')
  } catch (e: any) {
    error.value = e?.data?.message || e?.message || 'Could not crop the image.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <Modal :title="title" size="lg" @close="emit('close')">
    <ImageCropper
      v-if="stageSrc"
      ref="cropperEl"
      :src="stageSrc"
      :aspect="aspect"
      :output-width="outputWidth"
      :output-height="outputHeight"
    />
    <p v-if="error" class="error mt-3">{{ error }}</p>
    <div class="modal-actions">
      <button class="btn ghost" :disabled="busy" @click="emit('close')">Cancel</button>
      <button class="btn" :disabled="busy || !stageSrc" @click="confirm">
        {{ busy ? 'Saving…' : 'Crop & save' }}
      </button>
    </div>
  </Modal>
</template>
