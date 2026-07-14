<script setup lang="ts">
/**
 * A *person's* avatar: their photo, or — when they have none — initials on a
 * disc in the event's theme colour (see utils/avatar).
 *
 * Use this for people (speakers, delegates, chat partners, post authors) and
 * <AppImage> for everything else (logos, posters, covers): a person with no
 * photo should still be identifiable in a list, which a generic placeholder
 * graphic cannot do.
 *
 * Sizing and shape are the caller's job. The avatar fills its wrapper and
 * inherits its border-radius, so it drops into the existing `.avatar` / `.av`
 * wrappers without touching their CSS. The disc is an inline SVG with a viewBox
 * rather than styled text, so the initials scale with the box — a 28px chat
 * avatar and a 120px profile header both come out right without either one
 * having to tell us its size.
 *
 * A broken photo URL falls back to the disc too: photos are user uploads and
 * can 404 later, and a browser's broken-image icon where a face should be is
 * worse than initials.
 */
const props = defineProps<{
  src?: string | null
  name?: string | null
  /** Overrides the event's theme colour — for previews and the like. */
  primary?: string | null
}>()

const site = useSiteStore()

const failed = ref(false)
watch(() => props.src, () => { failed.value = false })

const showPhoto = computed(() => !!props.src && !failed.value)
const letters = computed(() => initials(props.name))
const background = computed(() =>
  avatarColor(props.name, props.primary ?? site.branding?.primary ?? null),
)
</script>

<template>
  <img
    v-if="showPhoto"
    class="ua"
    :src="src!"
    :alt="name || 'Avatar'"
    loading="lazy"
    @error="failed = true"
  >
  <svg
    v-else
    class="ua"
    viewBox="0 0 96 96"
    role="img"
    :aria-label="name || 'Avatar'"
  >
    <rect width="96" height="96" :fill="background" />
    <text
      x="48" y="48" dy=".35em"
      text-anchor="middle"
      fill="#fff"
      font-size="38"
      font-weight="700"
      font-family="inherit"
    >{{ letters }}</text>
  </svg>
</template>

<style scoped>
.ua {
  display: block;
  width: 100%;
  height: 100%;
  /* The wrapper decides whether this is a circle or a rounded square. */
  border-radius: inherit;
}
img.ua { object-fit: cover; }
svg.ua { user-select: none; }
</style>
