<script setup lang="ts">
/**
 * Any image that might not be there: a person's photo, an exhibitor logo, a
 * session cover, a lounge table shot. When `src` is missing — or turns out to be
 * a dead link — the event's placeholder graphic is shown instead.
 *
 * Sizing and shape are the caller's job. The image fills its wrapper and
 * inherits its border-radius, so this drops into the existing `.avatar` / `.av`
 * / `.logo` wrappers without touching their CSS. The placeholder is a wide 2:1
 * graphic, so `object-fit: cover` crops it to whatever box it lands in — a round
 * 40px avatar and a 16:9 banner both come out looking deliberate.
 *
 * A broken URL falls back too: images here are user uploads and can 404 later,
 * and a browser's broken-image icon where a face should be is worse than a
 * placeholder.
 */
const props = defineProps<{
  src?: string | null
  alt?: string | null
}>()

const PLACEHOLDER = '/img/placeholder.svg'

const failed = ref(false)
watch(() => props.src, () => { failed.value = false })

const resolved = computed(() => (!props.src || failed.value) ? PLACEHOLDER : props.src)
const isPlaceholder = computed(() => resolved.value === PLACEHOLDER)
</script>

<template>
  <img
    class="app-img"
    :src="resolved"
    :alt="alt || ''"
    :class="{ placeholder: isPlaceholder }"
    loading="lazy"
    @error="failed = true"
  >
</template>

<style scoped>
.app-img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  /* The wrapper decides whether this is a circle, a rounded card or a square. */
  border-radius: inherit;
}
/* The placeholder is decorative, never the subject — don't let it be dragged or
   read out as if it were the person's photo. */
.app-img.placeholder {
  -webkit-user-drag: none;
  user-select: none;
}
</style>
