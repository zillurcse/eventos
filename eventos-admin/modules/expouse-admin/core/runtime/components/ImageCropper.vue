<script setup lang="ts">
import { Cropper } from 'vue-advanced-cropper'
import 'vue-advanced-cropper/dist/style.css'

const props = defineProps<{
  src: string
  aspect: number
  outputWidth?: number
  outputHeight?: number
}>()

const cropperRef = ref<InstanceType<typeof Cropper>>()

const canvasOpts = computed(() =>
  props.outputWidth && props.outputHeight
    ? { width: props.outputWidth, height: props.outputHeight }
    : true,
)

async function crop(): Promise<Blob> {
  const canvas = cropperRef.value?.getResult().canvas
  if (!canvas) throw new Error('Cropper is not ready yet.')
  const blob = await new Promise<Blob>((resolve, reject) => {
    canvas.toBlob(b => (b ? resolve(b) : reject(new Error('Could not export the cropped image.'))), 'image/jpeg', 0.9)
  })
  return maybeCompress(blob)
}

defineExpose({ crop })
</script>

<template>
  <Cropper
    ref="cropperRef"
    class="crop-stage"
    :src="src"
    :stencil-props="{ aspectRatio: aspect }"
    :canvas="canvasOpts"
    :cross-origin="'anonymous'"
    image-restriction="fit-area"
  />
</template>

<style scoped>
.crop-stage {
  width: 100%;
  max-height: 60vh;
  min-height: 260px;
  background: #f1f2f6;
  border-radius: 12px;
  overflow: hidden;
}
</style>
