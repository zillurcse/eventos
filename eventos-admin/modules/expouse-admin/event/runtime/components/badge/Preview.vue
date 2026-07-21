<script setup lang="ts">
import { computed } from 'vue'

/**
 * Read-only render of a badge design, optionally merged with one person's data.
 *
 * All styling lives in `runtime/utils/badgeDesign.ts`, a faithful port of the
 * badge editor's own `PreviewCanvas.vue`, so the templates list, the guest
 * wizard and the attendee's phone all draw a design the same way. The event app
 * has a twin of this component (`components/badge/BadgeRender.vue`) over an
 * identical copy of that util — the two apps share no code, and a badge that
 * looks one way in the organizer's preview and another on paper is worse than a
 * duplicated file.
 *
 * With `data` omitted this is a plain design preview (what the templates list
 * shows); with it, every box whose `key` is a merge token draws that person's
 * value instead of the authored placeholder.
 */
const props = withDefaults(defineProps<{
  badgeJson: any
  data?: Record<string, string> | null
  side?: 'front' | 'back'
  /** Box the drawing is scaled to fit, in px. */
  maxWidth?: number
  maxHeight?: number
}>(), {
  data: null,
  side: 'front',
  maxWidth: 220,
  maxHeight: 150,
})

const page = computed(() => badgePageSize(props.badgeJson))
const boxes = computed(() => badgeBoxes(props.badgeJson, props.side))
const background = computed(() => badgeBackground(props.badgeJson, props.side))
const punch = computed(() => badgePunch(props.badgeJson))

const hasContent = computed(() => boxes.value.length > 0)

// Never upscale a design past 1:1 in the admin — these are thumbnails and modal
// previews, not print output.
const scale = computed(() =>
  Math.min(props.maxWidth / page.value.width, props.maxHeight / page.value.height, 1),
)
</script>

<template>
  <div
    class="relative overflow-hidden bg-white"
    :style="{ width: `${page.width * scale}px`, height: `${page.height * scale}px` }"
  >
    <div
      class="absolute top-0 left-0 origin-top-left select-none"
      :style="{
        width: `${page.width}px`,
        height: `${page.height}px`,
        background,
        transform: `scale(${scale})`,
      }"
    >
      <template v-if="punch.long === 'long-left-right'">
        <div class="punch long" style="top: 20px; left: 20px" />
        <div class="punch long" style="top: 20px; right: 20px" />
      </template>
      <div v-else-if="punch.long === 'long-center'" class="punch long centred" style="top: 20px" />

      <template v-if="punch.circle === 'circle-left-right'">
        <div class="punch circle" style="top: 20px; left: 20px" />
        <div class="punch circle" style="top: 20px; right: 20px" />
      </template>
      <div v-else-if="punch.circle === 'circle-center'" class="punch circle centred" style="top: 20px" />

      <div v-for="(box, i) in boxes" :key="box.id ?? i" :style="badgeBoxStyle(box)">
        <component
          :is="box.type"
          v-if="BADGE_TEXT_TYPES.includes(box.type)"
          :style="badgeTextStyle(box)"
        >{{ badgeText(box, data) }}</component>

        <img
          v-else-if="(box.type === 'img' || box.type === 'background') && badgeImage(box, data)"
          :src="badgeImage(box, data)"
          :style="badgeImageStyle(box)"
          alt=""
        >

        <div
          v-else-if="box.type === 'avatar' && badgeImage(box, data)"
          :style="badgeAvatarStyle(box).container"
        >
          <img :src="badgeImage(box, data)" :style="badgeAvatarStyle(box).image" alt="">
        </div>

        <Qrcode
          v-else-if="box.type === 'qrcode'"
          :value="badgeQr(box, data).value"
          :variant="badgeQr(box, data).variant"
          :radius="badgeQr(box, data).radius"
          :black-color="badgeQr(box, data).blackColor"
          :white-color="badgeQr(box, data).whiteColor"
          class="w-full h-full"
        />
      </div>
    </div>

    <div v-if="!hasContent" class="absolute inset-0 grid place-items-center text-faint">
      <AppIcon name="box" class="w-7 h-7 opacity-60" />
    </div>
  </div>
</template>

<style scoped>
.punch { position: absolute; z-index: 10; background: transparent; border: 1px solid #e5e7eb; }
.punch.long { width: 64px; height: 16px; border-radius: 12px; }
.punch.circle { width: 20px; height: 20px; border-radius: 12px; }
.punch.centred { left: 50%; transform: translateX(-50%); }
</style>
