<template>
  <div
    v-if="store.currentTool === 'arrow'"
    class="absolute top-4 left-20 bg-white p-3 rounded-lg shadow-lg"
  >
    <h3 class="text-sm font-medium mb-2">Arrow Settings</h3>
    <div class="space-y-2">
      <div class="flex items-center space-x-2">
        <label class="text-xs">Head Size:</label>
        <input type="range" v-model="headSize" min="5" max="30" class="w-20" />
      </div>
      <div class="flex items-center space-x-2">
        <label class="text-xs">Head Angle:</label>
        <input
          type="range"
          v-model="headAngle"
          min="15"
          max="45"
          class="w-20"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from "vue";
import { useDrawingStore } from "@floorplan/stores/canvasStore";

const store = useDrawingStore();
const headSize = ref(15);
const headAngle = ref(30);

// Emit events or update store when settings change
watch([headSize, headAngle], () => {
  // Update arrow drawing logic
});
</script>
