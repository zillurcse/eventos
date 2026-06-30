<template>
  <div
    class="pointer-events-none absolute inset-0 z-[9999]"
    style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh"
  >
    <svg class="w-full h-full" style="overflow: visible">
      <!-- Render guides -->
      <g v-for="(guide, index) in visibleGuides" :key="index">
        <!-- Vertical guides -->
        <line
          v-if="guide.type === 'vertical'"
          :x1="screenX(guide.position)"
          :y1="screenY(guide.start)"
          :x2="screenX(guide.position)"
          :y2="screenY(guide.end)"
          :stroke="getGuideColor(guide)"
          :stroke-width="getGuideWidth(guide)"
          :stroke-dasharray="getGuideDashArray(guide)"
          stroke-linecap="round"
          :style="getGuideStyle(guide)"
        />

        <!-- Horizontal guides -->
        <line
          v-else
          :x1="screenX(guide.start)"
          :y1="screenY(guide.position)"
          :x2="screenX(guide.end)"
          :y2="screenY(guide.position)"
          :stroke="getGuideColor(guide)"
          :stroke-width="getGuideWidth(guide)"
          :stroke-dasharray="getGuideDashArray(guide)"
          stroke-linecap="round"
          :style="getGuideStyle(guide)"
        />

        <!-- Endpoint dots -->
        <circle
          v-if="guide.type === 'vertical'"
          :cx="screenX(guide.position)"
          :cy="screenY(guide.start)"
          :r="getGuideWidth(guide) * 1.5"
          :fill="getGuideColor(guide)"
        />
        <circle
          v-if="guide.type === 'vertical'"
          :cx="screenX(guide.position)"
          :cy="screenY(guide.end)"
          :r="getGuideWidth(guide) * 1.5"
          :fill="getGuideColor(guide)"
        />
        <circle
          v-if="guide.type === 'horizontal'"
          :cx="screenX(guide.start)"
          :cy="screenY(guide.position)"
          :r="getGuideWidth(guide) * 1.5"
          :fill="getGuideColor(guide)"
        />
        <circle
          v-if="guide.type === 'horizontal'"
          :cx="screenX(guide.end)"
          :cy="screenY(guide.position)"
          :r="getGuideWidth(guide) * 1.5"
          :fill="getGuideColor(guide)"
        />
      </g>

      <!-- "EQUAL" badges for equidistant guides -->
      <g v-for="(guide, index) in equidistantGuides" :key="`badge-${index}`">
        <g v-if="guide.type === 'vertical'">
          <defs>
            <filter :id="`shadow-v-${index}`">
              <feGaussianBlur in="SourceAlpha" stdDeviation="2" />
              <feOffset dx="0" dy="1" result="offsetblur" />
              <feComponentTransfer>
                <feFuncA type="linear" slope="0.3" />
              </feComponentTransfer>
              <feMerge>
                <feMergeNode />
                <feMergeNode in="SourceGraphic" />
              </feMerge>
            </filter>
          </defs>
          <rect
            :x="screenX(guide.position) - 30"
            :y="screenY((guide.start + guide.end) / 2) - 11"
            width="60"
            height="22"
            rx="11"
            :fill="getGuideColor(guide)"
            :filter="`url(#shadow-v-${index})`"
          />
          <rect
            :x="screenX(guide.position) - 30"
            :y="screenY((guide.start + guide.end) / 2) - 11"
            width="60"
            height="22"
            rx="11"
            fill="none"
            stroke="#FFFFFF"
            stroke-width="2"
          />
          <text
            :x="screenX(guide.position)"
            :y="screenY((guide.start + guide.end) / 2)"
            text-anchor="middle"
            dominant-baseline="middle"
            fill="#FFFFFF"
            font-size="11"
            font-weight="bold"
            font-family="-apple-system, BlinkMacSystemFont, sans-serif"
          >
            EQUAL
          </text>
        </g>
        <g v-else>
          <defs>
            <filter :id="`shadow-h-${index}`">
              <feGaussianBlur in="SourceAlpha" stdDeviation="2" />
              <feOffset dx="0" dy="1" result="offsetblur" />
              <feComponentTransfer>
                <feFuncA type="linear" slope="0.3" />
              </feComponentTransfer>
              <feMerge>
                <feMergeNode />
                <feMergeNode in="SourceGraphic" />
              </feMerge>
            </filter>
          </defs>
          <rect
            :x="screenX((guide.start + guide.end) / 2) - 30"
            :y="screenY(guide.position) - 11"
            width="60"
            height="22"
            rx="11"
            :fill="getGuideColor(guide)"
            :filter="`url(#shadow-h-${index})`"
          />
          <rect
            :x="screenX((guide.start + guide.end) / 2) - 30"
            :y="screenY(guide.position) - 11"
            width="60"
            height="22"
            rx="11"
            fill="none"
            stroke="#FFFFFF"
            stroke-width="2"
          />
          <text
            :x="screenX((guide.start + guide.end) / 2)"
            :y="screenY(guide.position)"
            text-anchor="middle"
            dominant-baseline="middle"
            fill="#FFFFFF"
            font-size="11"
            font-weight="bold"
            font-family="-apple-system, BlinkMacSystemFont, sans-serif"
          >
            EQUAL
          </text>
        </g>
      </g>

      <!-- Multi-align count markers -->
      <g v-for="(guide, index) in multiAlignGuides" :key="`multi-${index}`">
        <circle
          v-if="guide.type === 'vertical'"
          :cx="screenX(guide.position)"
          :cy="screenY((guide.start + guide.end) / 2)"
          r="12"
          :fill="getGuideColor(guide)"
          stroke="#FFFFFF"
          stroke-width="2"
        />
        <text
          v-if="guide.type === 'vertical'"
          :x="screenX(guide.position)"
          :y="screenY((guide.start + guide.end) / 2)"
          text-anchor="middle"
          dominant-baseline="middle"
          fill="#FFFFFF"
          font-size="10"
          font-weight="bold"
        >
          {{ guide.alignedCount }}
        </text>

        <circle
          v-if="guide.type === 'horizontal'"
          :cx="screenX((guide.start + guide.end) / 2)"
          :cy="screenY(guide.position)"
          r="12"
          :fill="getGuideColor(guide)"
          stroke="#FFFFFF"
          stroke-width="2"
        />
        <text
          v-if="guide.type === 'horizontal'"
          :x="screenX((guide.start + guide.end) / 2)"
          :y="screenY(guide.position)"
          text-anchor="middle"
          dominant-baseline="middle"
          fill="#FFFFFF"
          font-size="10"
          font-weight="bold"
        >
          {{ guide.alignedCount }}
        </text>
      </g>
    </svg>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import type { ElementAlignmentGuide } from "@floorplan/composables/useElementAlignment";

const props = withDefaults(
  defineProps<{
    guides?: ElementAlignmentGuide[];
  }>(),
  {
    guides: () => [],
  }
);

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
  if (!props.guides || !Array.isArray(props.guides)) {
    return [];
  }

  return props.guides.filter((guide) => {
    if (!guide) return false;

    if (guide.type === "vertical") {
      const x = screenX(guide.position);
      return x > -300 && x < canvasWidth.value + 300;
    } else {
      const y = screenY(guide.position);
      return y > -300 && y < canvasHeight.value + 300;
    }
  });
});

// Separate equidistant guides for badge rendering
const equidistantGuides = computed(() => {
  return visibleGuides.value.filter((g) => g.isEquidistant);
});

// Separate multi-align guides (excluding equidistant)
const multiAlignGuides = computed(() => {
  return visibleGuides.value.filter(
    (g) => g.isMultiAlign && !g.isEquidistant && g.alignedCount
  );
});

const screenX = (worldX: number) => {
  return (worldX - store.offset.x) * store.zoom;
};

const screenY = (worldY: number) => {
  return (worldY - store.offset.y) * store.zoom;
};

const getGuideColor = (guide: ElementAlignmentGuide) => {
  // ✅ Match canvas object color hierarchy
  if (guide.isEquidistant) {
    return "#6366f1"; // Emerald green for equal spacing
  }
  if (guide.isMultiAlign) {
    return "#F59E0B"; // Amber for multi-align
  }
  if (guide.alignment === "centerX" || guide.alignment === "centerY") {
    return "#8B5CF6"; // Purple for center
  }
  return "#6366F1"; // Indigo for edges
};

const getGuideWidth = (guide: ElementAlignmentGuide) => {
  // ✅ Match canvas object line widths
  if (guide.isEquidistant) return 3;
  if (guide.isMultiAlign) return 2.5;
  if (guide.alignment === "centerX" || guide.alignment === "centerY") return 2;
  return 1.5;
};

const getGuideDashArray = (guide: ElementAlignmentGuide) => {
  // ✅ Match canvas object dash patterns
  if (guide.isEquidistant) return "10,5"; // Dashed for equidistant
  if (guide.isMultiAlign) return ""; // Solid for multi-align
  return "5,5"; // Dashed for regular guides
};

const getGuideStyle = (guide: ElementAlignmentGuide) => {
  const color = getGuideColor(guide);
  const opacity = guide.isEquidistant ? 1 : 0.85;

  return {
    opacity,
    filter: `drop-shadow(0 0 ${guide.isEquidistant ? 8 : 6}px ${color})`,
  };
};
</script>
