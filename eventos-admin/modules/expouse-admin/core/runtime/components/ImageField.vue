<script setup lang="ts">
const props = withDefaults(defineProps<{
  modelValue: string | string[] | null
  multiple?: boolean
  max?: number
  /** Omit for a free-form crop box (no fixed ratio). */
  aspect?: number
  outputWidth?: number
  outputHeight?: number
  collection?: string
  galleryPath?: string
  /** Upload endpoint; exhibitor-side callers post to /exhibitor/uploads instead. */
  uploadPath?: string
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
const cardW = computed(() => props.cardWidth ?? ((props.aspect ?? 1) >= 1.6 ? '300px' : '160px'))
const cardAspect = computed(() => String(props.aspect ?? 1))

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
        :style="{ width: cardW, aspectRatio: cardAspect }"
      >
        <img :src="url" alt="">
        <div class="img-card-actions">
          <button class="img-action" title="Replace image" @click="openChooser(i)">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M3 16.0058H7.24C7.37161 16.0066 7.50207 15.9813 7.62391 15.9316C7.74574 15.8818 7.85656 15.8085 7.95 15.7158L14.87 8.7858L17.71 6.0058C17.8037 5.91284 17.8781 5.80223 17.9289 5.68038C17.9797 5.55852 18.0058 5.42781 18.0058 5.2958C18.0058 5.16379 17.9797 5.03308 17.9289 4.91122C17.8781 4.78936 17.8037 4.67876 17.71 4.5858L13.47 0.295798C13.377 0.20207 13.2664 0.127676 13.1446 0.0769069C13.0227 0.0261382 12.892 0 12.76 0C12.628 0 12.4973 0.0261382 12.3754 0.0769069C12.2536 0.127676 12.143 0.20207 12.05 0.295798L9.23 3.1258L2.29 10.0558C2.19732 10.1492 2.12399 10.2601 2.07423 10.3819C2.02446 10.5037 1.99924 10.6342 2 10.7658V15.0058C2 15.271 2.10536 15.5254 2.29289 15.7129C2.48043 15.9004 2.73478 16.0058 3 16.0058ZM12.76 2.4158L15.59 5.2458L14.17 6.6658L11.34 3.8358L12.76 2.4158ZM4 11.1758L9.93 5.2458L12.76 8.0758L6.83 14.0058H4V11.1758ZM19 18.0058H1C0.734784 18.0058 0.48043 18.1112 0.292893 18.2987C0.105357 18.4862 0 18.7406 0 19.0058C0 19.271 0.105357 19.5254 0.292893 19.7129C0.48043 19.9004 0.734784 20.0058 1 20.0058H19C19.2652 20.0058 19.5196 19.9004 19.7071 19.7129C19.8946 19.5254 20 19.271 20 19.0058C20 18.7406 19.8946 18.4862 19.7071 18.2987C19.5196 18.1112 19.2652 18.0058 19 18.0058Z" fill="#6452E7"/>
            </svg>
          </button>
          <button class="img-action" title="Crop image" @click="openCropper(i)">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path d="M15 12H14V3C14 2.73478 13.8946 2.48043 13.7071 2.29289C13.5196 2.10536 13.2652 2 13 2H4V1C4 0.734784 3.89464 0.48043 3.70711 0.292893C3.51957 0.105357 3.26522 0 3 0C2.73478 0 2.48043 0.105357 2.29289 0.292893C2.10536 0.48043 2 0.734784 2 1V2H1C0.734784 2 0.48043 2.10536 0.292893 2.29289C0.105357 2.48043 0 2.73478 0 3C0 3.26522 0.105357 3.51957 0.292893 3.70711C0.48043 3.89464 0.734784 4 1 4H2V13C2 13.2652 2.10536 13.5196 2.29289 13.7071C2.48043 13.8946 2.73478 14 3 14H12V15C12 15.2652 12.1054 15.5196 12.2929 15.7071C12.4804 15.8946 12.7348 16 13 16C13.2652 16 13.5196 15.8946 13.7071 15.7071C13.8946 15.5196 14 15.2652 14 15V14H15C15.2652 14 15.5196 13.8946 15.7071 13.7071C15.8946 13.5196 16 13.2652 16 13C16 12.7348 15.8946 12.4804 15.7071 12.2929C15.5196 12.1054 15.2652 12 15 12ZM12 12H4V4H12V12Z" fill="#6452E7"/>
            </svg>
          </button>
          <button class="img-action" title="View full size" @click="view(url)">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="16" viewBox="0 0 20 16" fill="none">
              <path d="M19.9235 7.6C17.9035 2.91 14.1035 0 10.0035 0C5.90348 0 2.10348 2.91 0.0834848 7.6C0.0284215 7.72617 0 7.86234 0 8C0 8.13766 0.0284215 8.27383 0.0834848 8.4C2.10348 13.09 5.90348 16 10.0035 16C14.1035 16 17.9035 13.09 19.9235 8.4C19.9785 8.27383 20.007 8.13766 20.007 8C20.007 7.86234 19.9785 7.72617 19.9235 7.6ZM10.0035 14C6.83348 14 3.83348 11.71 2.10348 8C3.83348 4.29 6.83348 2 10.0035 2C13.1735 2 16.1735 4.29 17.9035 8C16.1735 11.71 13.1735 14 10.0035 14ZM10.0035 4C9.21236 4 8.439 4.2346 7.7812 4.67412C7.12341 5.11365 6.61072 5.73836 6.30797 6.46927C6.00522 7.20017 5.926 8.00444 6.08034 8.78036C6.23468 9.55628 6.61565 10.269 7.17506 10.8284C7.73447 11.3878 8.4472 11.7688 9.22312 11.9231C9.99905 12.0775 10.8033 11.9983 11.5342 11.6955C12.2651 11.3928 12.8898 10.8801 13.3294 10.2223C13.7689 9.56448 14.0035 8.79113 14.0035 8C14.0035 6.93913 13.5821 5.92172 12.8319 5.17157C12.0818 4.42143 11.0644 4 10.0035 4ZM10.0035 10C9.60792 10 9.22124 9.8827 8.89234 9.66294C8.56345 9.44318 8.3071 9.13082 8.15573 8.76537C8.00435 8.39991 7.96474 7.99778 8.04191 7.60982C8.11908 7.22186 8.30957 6.86549 8.58927 6.58579C8.86898 6.30608 9.22534 6.1156 9.6133 6.03843C10.0013 5.96126 10.4034 6.00087 10.7689 6.15224C11.1343 6.30362 11.4467 6.55996 11.6664 6.88886C11.8862 7.21776 12.0035 7.60444 12.0035 8C12.0035 8.53043 11.7928 9.03914 11.4177 9.41421C11.0426 9.78929 10.5339 10 10.0035 10Z" fill="#6452E7"/>
            </svg>
          </button>
          <button v-if="removable" class="img-action danger" title="Remove image" @click="remove(i)">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="20" viewBox="0 0 18 20" fill="none">
              <path d="M7 16C7.26522 16 7.51957 15.8946 7.70711 15.7071C7.89464 15.5196 8 15.2652 8 15V9C8 8.73478 7.89464 8.48043 7.70711 8.29289C7.51957 8.10536 7.26522 8 7 8C6.73478 8 6.48043 8.10536 6.29289 8.29289C6.10536 8.48043 6 8.73478 6 9V15C6 15.2652 6.10536 15.5196 6.29289 15.7071C6.48043 15.8946 6.73478 16 7 16ZM17 4H13V3C13 2.20435 12.6839 1.44129 12.1213 0.87868C11.5587 0.316071 10.7956 0 10 0H8C7.20435 0 6.44129 0.316071 5.87868 0.87868C5.31607 1.44129 5 2.20435 5 3V4H1C0.734784 4 0.48043 4.10536 0.292893 4.29289C0.105357 4.48043 0 4.73478 0 5C0 5.26522 0.105357 5.51957 0.292893 5.70711C0.48043 5.89464 0.734784 6 1 6H2V17C2 17.7956 2.31607 18.5587 2.87868 19.1213C3.44129 19.6839 4.20435 20 5 20H13C13.7956 20 14.5587 19.6839 15.1213 19.1213C15.6839 18.5587 16 17.7956 16 17V6H17C17.2652 6 17.5196 5.89464 17.7071 5.70711C17.8946 5.51957 18 5.26522 18 5C18 4.73478 17.8946 4.48043 17.7071 4.29289C17.5196 4.10536 17.2652 4 17 4ZM7 3C7 2.73478 7.10536 2.48043 7.29289 2.29289C7.48043 2.10536 7.73478 2 8 2H10C10.2652 2 10.5196 2.10536 10.7071 2.29289C10.8946 2.48043 11 2.73478 11 3V4H7V3ZM14 17C14 17.2652 13.8946 17.5196 13.7071 17.7071C13.5196 17.8946 13.2652 18 13 18H5C4.73478 18 4.48043 17.8946 4.29289 17.7071C4.10536 17.5196 4 17.2652 4 17V6H14V17ZM11 16C11.2652 16 11.5196 15.8946 11.7071 15.7071C11.8946 15.5196 12 15.2652 12 15V9C12 8.73478 11.8946 8.48043 11.7071 8.29289C11.5196 8.10536 11.2652 8 11 8C10.7348 8 10.4804 8.10536 10.2929 8.29289C10.1054 8.48043 10 8.73478 10 9V15C10 15.2652 10.1054 15.5196 10.2929 15.7071C10.4804 15.8946 10.7348 16 11 16Z" fill="#6452E7"/>
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
        <div class="card-empty" :style="{ width: cardW, aspectRatio: cardAspect }" @click="openChooser(null)"
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
      :upload-path="uploadPath"
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
      :upload-path="uploadPath"
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