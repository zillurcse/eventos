<script setup lang="ts">
const props = withDefaults(defineProps<{
  modelValue: string | string[] | null
  multiple?: boolean
  max?: number
  aspect: number
  outputWidth?: number
  outputHeight?: number
  collection?: string
  galleryPath?: string
  hint?: string
  removable?: boolean
  cardWidth?: string
}>(), { removable: true })

const emit = defineEmits<{
  (e: 'update:modelValue', v: string | string[] | null): void
  (e: 'uploaded', v: { id: number, url: string }): void
}>()

const items = computed<string[]>(() =>
  props.multiple
    ? (Array.isArray(props.modelValue) ? props.modelValue : [])
    : (typeof props.modelValue === 'string' && props.modelValue ? [props.modelValue] : []),
)

const canAdd = computed(() =>
  props.multiple ? items.value.length < (props.max ?? Infinity) : items.value.length === 0,
)

// Wide formats (banners, headers) get a bigger card so the preview stays legible.
const cardW = computed(() => props.cardWidth ?? (props.aspect >= 1.6 ? '300px' : '160px'))

const chooserOpen = ref(false)
const cropSrc = ref('')
let targetIndex: number | null = null // null = append (multiple) / set (single)

function openChooser(index: number | null) {
  targetIndex = index
  chooserOpen.value = true
}

function openCropper(index: number) {
  targetIndex = index
  cropSrc.value = items.value[index]!
}

function setAt(index: number | null, url: string) {
  if (props.multiple) {
    const next = [...items.value]
    if (index === null) next.push(url)
    else next[index] = url
    emit('update:modelValue', next)
  } else {
    emit('update:modelValue', url)
  }
}

function onSelected(v: { id?: number, url: string }) {
  setAt(targetIndex, v.url)
  if (v.id != null) emit('uploaded', { id: v.id, url: v.url })
}

function remove(index: number) {
  if (props.multiple) {
    const next = [...items.value]
    next.splice(index, 1)
    emit('update:modelValue', next)
  } else {
    emit('update:modelValue', null)
  }
}

function view(url: string) {
  window.open(url, '_blank', 'noopener')
}
</script>

<template>
  <div>
    <div class="flex flex-wrap gap-3">
      <!-- Image cards -->
      <div
        v-for="(url, i) in items"
        :key="url + i"
        class="img-card"
        :style="{ width: cardW, aspectRatio: String(aspect) }"
      >
        <img :src="url" alt="">
        <div class="img-card-actions">
          <button class="img-action" title="Replace image" @click="openChooser(i)">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 3a2.85 2.85 0 114 4L7.5 20.5 2 22l1.5-5.5z"/>
            </svg>
          </button>
          <button class="img-action" title="Crop image" @click="openCropper(i)">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M6.13 1L6 16a2 2 0 002 2h15"/><path d="M1 6.13L16 6a2 2 0 012 2v15"/>
            </svg>
          </button>
          <button class="img-action" title="View full size" @click="view(url)">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
          <button v-if="removable" class="img-action danger" title="Remove image" @click="remove(i)">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>

      <!-- Add placeholder -->
      <!-- <button
        v-if="canAdd"
        type="button"
        class="img-add"
        :style="{ width: cardW, aspectRatio: String(aspect) }"
        @click="openChooser(null)"
      >
        <slot name="empty">
          <span class="flex flex-col items-center gap-1">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
              <path d="M12 5v14M5 12h14"/>
            </svg>
            Add image
          </span>
        </slot>
      </button> -->
      <!-- EMPTY CARD (Add new image) -->
        <div class="card-empty" :style="{ width: cardW, aspectRatio: String(aspect) }" @click="openChooser(null)"
            v-if="canAdd">
            <div class="plus-icon-box">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 13 12" fill="none">
                    <path d="M7.97344 0H5.57344V4.8H0.773438V7.2H5.57344V12H7.97344V7.2H12.7734V4.8H7.97344V0Z"
                        fill="#5B73E8" />
                </svg>
            </div>
            
            <img src="https://i.ibb.co.com/TDVr1Tyz/expouse-default-image-1.jpg" alt="" class="card-empty-image">
        </div>
    </div>

    <p v-if="hint" class="text-[.78rem] text-faint mt-2 mb-0">{{ hint }}</p>

    <ImageChooserModal
      v-if="chooserOpen"
      :aspect="aspect"
      :output-width="outputWidth"
      :output-height="outputHeight"
      :collection="collection"
      :gallery-path="galleryPath"
      @selected="onSelected"
      @close="chooserOpen = false"
    />

    <ImageCropperModal
      v-if="cropSrc"
      :src="cropSrc"
      :aspect="aspect"
      :output-width="outputWidth"
      :output-height="outputHeight"
      :collection="collection"
      @done="onSelected"
      @close="cropSrc = ''"
    />
  </div>
</template>

<style scoped>
.card-empty {
    position: relative;
    background-color: #D9D9D9;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    /* margin: 0 auto; */
    overflow: hidden;
}
.plus-icon-box {
    height: 35px;
    width: 35px;
    background-color: #ffffff;
    border-radius: 8px;
    border-bottom-left-radius: 2px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s ease, background-color 0.2s ease;
    z-index: 2
;
}

.card-empty:hover .plus-icon-box {
    transform: scale(1.1);
    background: #f7f9f9;
}
.card-empty-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1;
}
</style>