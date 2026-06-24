<script setup lang="ts">
const props = defineProps<{ preview?: string | null, collection?: string, path?: string }>()
const emit = defineEmits<{ (e: 'uploaded', v: { id: number, url: string }): void }>()

const { upload } = useUpload()
const localPreview = ref<string | null>(props.preview ?? null)
const busy = ref(false)

watch(() => props.preview, v => { if (v !== undefined) localPreview.value = v })

async function pick(e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0]
  if (!f) return
  busy.value = true
  try {
    const r = await upload(f, { collection: props.collection, path: props.path })
    localPreview.value = r.url
    emit('uploaded', r)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <label class="uploader">
    <img v-if="localPreview" :src="localPreview" alt="">
    <span v-else>{{ busy ? 'Uploading…' : '+ Click to upload an image' }}</span>
    <input type="file" accept="image/*" @change="pick">
  </label>
</template>
