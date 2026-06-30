<template>
  <div class="flex justify-center items-center h-screen bg-gray-100">
    <!-- A6 Page -->
    <div
      ref="a6Page"
      @click="getClickPosition"
      class="bg-white border border-gray-400 shadow-md relative"
      :style="pageStyle"
    >
      <!-- Show coordinates -->
      <div
        v-if="coords"
        class="absolute bg-black text-white text-xs px-2 py-1 rounded"
        :style="{ left: coords.x + 'px', top: coords.y + 'px' }"
      >
        X: {{ coords.x }}, Y: {{ coords.y }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from "vue";

// A6 size in mm: 105 × 148
// We'll scale it in pixels for the screen (1mm ≈ 3.78px for 96dpi)
const mmToPx = (mm) => mm * 3.78;

const pageWidth = mmToPx(105); // A6 width in pixels
const pageHeight = mmToPx(148); // A6 height in pixels

const a6Page = ref(null);
const coords = reactive({ x: null, y: null });

const pageStyle = computed(() => ({
  width: pageWidth + "px",
  height: pageHeight + "px",
}));

const getClickPosition = (event) => {
  const rect = a6Page.value.getBoundingClientRect();
  coords.x = Math.round(event.clientX - rect.left);
  coords.y = Math.round(event.clientY - rect.top);
};
</script>

<style>
body {
  margin: 0;
}
</style>
