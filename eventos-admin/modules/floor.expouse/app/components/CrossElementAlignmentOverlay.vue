<!-- components/CrossElementAlignmentOverlay.vue -->
<template>
  <div
    class="pointer-events-none absolute inset-0 z-[9999]"
    style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh"
  >
    <svg class="w-full h-full" style="overflow: visible">
      <g v-for="(guide, index) in visibleGuides" :key="`cross-${index}`">
        <line
          v-if="guide.type === 'vertical'"
          :x1="screenX(guide.position)"
          :y1="0"
          :x2="screenX(guide.position)"
          :y2="canvasHeight"
          :stroke="getGuideColor(guide.alignment)"
          :stroke-width="getGuideWidth(guide.alignment)"
          stroke-dasharray="10,6"
          stroke-linecap="round"
          :style="getGuideStyle(guide.alignment)"
        />
        <line
          v-else
          :x1="0"
          :y1="screenY(guide.position)"
          :x2="canvasWidth"
          :y2="screenY(guide.position)"
          :stroke="getGuideColor(guide.alignment)"
          :stroke-width="getGuideWidth(guide.alignment)"
          stroke-dasharray="10,6"
          stroke-linecap="round"
          :style="getGuideStyle(guide.alignment)"
        />
      </g>
    </svg>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import type { CrossElementGuide } from "@floorplan/composables/useCrossElementAlignment";

const props = defineProps<{
  guides: CrossElementGuide[];
}>();

const store = useCanvasStore();
const canvasWidth = ref(window.innerWidth);
const canvasHeight = ref(window.innerHeight);

const updateDimensions = () => {
  canvasWidth.value = window.innerWidth;
  canvasHeight.value = window.innerHeight;
};

onMounted(() => {
  window.addEventListener("resize", updateDimensions);
});

onUnmounted(() => {
  window.removeEventListener("resize", updateDimensions);
});

const visibleGuides = computed(() => {
  return props.guides.filter((guide) => {
    if (guide.type === "vertical") {
      const x = screenX(guide.position);
      return x > -300 && x < canvasWidth.value + 300;
    } else {
      const y = screenY(guide.position);
      return y > -300 && y < canvasHeight.value + 300;
    }
  });
});

const screenX = (worldX: number) => {
  return (worldX - store.offset.x) * store.zoom;
};

const screenY = (worldY: number) => {
  return (worldY - store.offset.y) * store.zoom;
};

const getGuideColor = (alignment: string) => {
  // Use identical colors to your existing alignment guide system
  return alignment === "centerX" || alignment === "centerY"
    ? "#c084fc" // Softer purple for center
    : "#d946ef"; // Vibrant magenta for edges
};

const getGuideWidth = (alignment: string) => {
  // Match existing guide widths
  return alignment === "centerX" || alignment === "centerY" ? 2.8 : 2.5;
};

const getGuideStyle = (alignment: string) => {
  const color = getGuideColor(alignment);
  return {
    filter: `drop-shadow(0 0 10px ${color})`,
  };
};
</script>
