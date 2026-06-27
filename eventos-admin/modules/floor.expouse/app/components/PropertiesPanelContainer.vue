<!-- components/PropertiesPanelContainer.vue -->
<template>
  <div class="properties-container">
    <!-- Floor Properties Panel -->
    <FloorPropertiesPanel
      v-if="showFloorProperties"
      class="transition-all duration-200"
    />

    <!-- Regular Properties Panel (for other elements) -->
    <PropertiesPanel
      v-else-if="showElementProperties"
      class="transition-all duration-200"
    />

    <!-- Empty State when nothing is selected -->
    <div v-else class="empty-state p-8 text-center text-gray-500">
      <NuxtIcon
        name="heroicons:cursor-click"
        class="w-12 h-12 mx-auto mb-3 opacity-30"
      />
      <p class="text-sm">No element selected</p>
      <p class="text-xs">Select a floor or element to edit properties</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import FloorPropertiesPanel from "./FloorPropertiesPanel.vue";
import PropertiesPanel from "./PropertiesPanel.vue"; // Your regular properties panel

const canvasStore = useCanvasStore();

// Show floor properties when floor is selected and no other elements
const showFloorProperties = computed(() => {
  return (
    canvasStore.currentFloorId &&
    canvasStore.selectedObjects.length === 0 &&
    !canvasStore.selectedElementId
  );
});

// Show element properties when any element is selected
const showElementProperties = computed(() => {
  return (
    canvasStore.selectedObjects.length > 0 || canvasStore.selectedElementId
  );
});
</script>

<style scoped>
@reference "tailwindcss";
.properties-container {
  @apply h-full overflow-y-auto;
}

.empty-state {
  @apply flex flex-col items-center justify-center h-full;
}
</style>
