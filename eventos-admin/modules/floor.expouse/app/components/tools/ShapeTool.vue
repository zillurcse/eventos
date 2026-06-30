<template>
  <div
    v-if="isShapeToolActive"
    class="absolute top-4 left-20 bg-white p-3 rounded-lg shadow-lg"
  >
    <h3 class="text-sm font-medium mb-2">Shape Settings</h3>

    <!-- Shape Type Selector -->
    <div class="grid grid-cols-3 gap-2 mb-3">
      <button
        v-for="shape in shapeTypes"
        :key="shape"
        @click="setShapeType(shape)"
        :class="[
          'p-2 rounded border text-xs transition-colors',
          selectedShape === shape
            ? 'bg-blue-100 border-blue-500 text-blue-700'
            : 'border-gray-300 text-gray-600 hover:bg-gray-50',
        ]"
      >
        {{ shapeLabels[shape] }}
      </button>
    </div>

    <!-- Fill Options -->
    <div class="space-y-2">
      <div class="flex items-center space-x-2">
        <label class="text-xs">Fill:</label>
        <input
          type="color"
          v-model="fillColor"
          class="w-6 h-6 rounded border border-gray-300 cursor-pointer"
        />
        <button
          @click="fillColor = 'transparent'"
          class="text-xs px-2 py-1 border border-gray-300 rounded hover:bg-gray-50"
        >
          None
        </button>
      </div>

      <!-- Polygon specific settings -->
      <div
        v-if="selectedShape === 'polygon'"
        class="flex items-center space-x-2"
      >
        <label class="text-xs">Sides:</label>
        <input
          type="number"
          v-model.number="polygonSides"
          min="3"
          max="12"
          class="w-16 px-2 py-1 border border-gray-300 rounded text-xs"
        />
      </div>

      <!-- Snap to grid -->
      <div class="flex items-center space-x-2">
        <label class="text-xs">
          <input type="checkbox" v-model="snapToGrid" class="mr-1" />
          Snap to Grid
        </label>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { useDrawingStore } from "@floorplan/stores/canvasStore";
import type { CanvasObject } from "@floorplan/types/canvas";

const store = useDrawingStore();

const shapeTypes = ["rectangle", "ellipse", "polygon"] as const;
type ShapeType = (typeof shapeTypes)[number];

const shapeLabels = {
  rectangle: "Rect",
  ellipse: "Ellipse",
  polygon: "Polygon",
};

const selectedShape = ref<ShapeType>("rectangle");
const fillColor = ref("#ffffff33");
const polygonSides = ref(5);
const snapToGrid = ref(true);

const isShapeToolActive = computed(() =>
  shapeTypes.includes(store.currentTool as ShapeType)
);

const setShapeType = (shape: ShapeType) => {
  store.setTool(shape);
  selectedShape.value = shape;
};

// Update store when settings change
watch([fillColor, polygonSides, snapToGrid], () => {
  // These settings can be applied when creating new shapes
});
</script>
