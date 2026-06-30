<template>
  <div
    v-if="store.currentTool === 'curve-arrow'"
    class="absolute top-4 left-20 bg-white p-3 rounded-lg shadow-lg"
  >
    <h3 class="text-sm font-medium mb-2">Curve Arrow Settings</h3>
    <div class="space-y-2">
      <div class="flex items-center space-x-2">
        <label class="text-xs">Curve Tension:</label>
        <input
          type="range"
          v-model="curveTension"
          min="0.1"
          max="2"
          step="0.1"
          class="w-20"
        />
      </div>
      <div class="text-xs text-gray-500">Click: Start → Control → End</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { useDrawingStore } from "@floorplan/stores/canvasStore";

const store = useDrawingStore();
const curveTension = ref(1.0);
</script>
