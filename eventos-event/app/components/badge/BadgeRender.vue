<script setup lang="ts">
/**
 * Draws a badge design exactly as the organizer's editor draws it, with this
 * attendee's details merged in.
 *
 * All the styling decisions live in `utils/badgeDesign.ts`, which is a faithful
 * port of the editor's own `PreviewCanvas.vue` — see that file for why every
 * default is copied rather than chosen. This component is just the markup, and
 * it mirrors PreviewCanvas element for element so the two cannot drift.
 *
 * The design is authored in a fixed pixel canvas (397 × 559 for A6) and scaled
 * down with a CSS transform, so a thumbnail, an on-screen badge and a 1:1 render
 * for PDF export all share one code path.
 */
const props = withDefaults(defineProps<{
  badgeJson: any
  /** Merge values keyed by box key; omit for a placeholder preview. */
  data?: Record<string, string> | null
  side?: 'front' | 'back'
  maxWidth?: number
  maxHeight?: number
}>(), {
  data: null,
  side: 'front',
  maxWidth: 320,
  maxHeight: 450,
})

const page = computed(() => badgePageSize(props.badgeJson))
const boxes = computed(() => badgeBoxes(props.badgeJson, props.side))
const background = computed(() => badgeBackground(props.badgeJson, props.side))
const punch = computed(() => badgePunch(props.badgeJson))

const scale = computed(() =>
  Math.min(props.maxWidth / page.value.width, props.maxHeight / page.value.height),
)
</script>

<template>
  <div
    class="badge"
    :style="{ width: `${page.width * scale}px`, height: `${page.height * scale}px` }"
  >
    <div
      class="canvas"
      :style="{
        width: `${page.width}px`,
        height: `${page.height}px`,
        background,
        transform: `scale(${scale})`,
      }"
    >
      <!-- Punch-hole guides, drawn under the elements as in the editor. -->
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
          class="qr"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.badge { position: relative; overflow: hidden; background: #fff; }
.canvas { position: absolute; top: 0; left: 0; transform-origin: top left; user-select: none; }
.qr { width: 100%; height: 100%; }

.punch { position: absolute; z-index: 10; background: transparent; border: 1px solid #e5e7eb; }
.punch.long { width: 64px; height: 16px; border-radius: 12px; }
.punch.circle { width: 20px; height: 20px; border-radius: 12px; }
.punch.centred { left: 50%; transform: translateX(-50%); }
</style>
