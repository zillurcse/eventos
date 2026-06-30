<template>
  <div
    v-if="store.currentTool === 'pencil'"
    class="absolute top-4 left-20 bg-white p-3 rounded-lg shadow-lg"
  >
    <h3 class="text-sm font-medium mb-2">Pencil Settings</h3>
    <div class="space-y-2">
      <div class="flex items-center space-x-2">
        <label class="text-xs">Smoothing:</label>
        <input type="range" v-model="smoothing" min="0" max="10" class="w-20" />
      </div>
      <div class="flex items-center space-x-2">
        <label class="text-xs">
          <input type="checkbox" v-model="pressureSensitive" class="mr-1" />
          Pressure
        </label>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { useDrawingStore } from "@floorplan/stores/canvasStore";

const store = useDrawingStore();
const smoothing = ref(5);
const pressureSensitive = ref(false);
</script>
