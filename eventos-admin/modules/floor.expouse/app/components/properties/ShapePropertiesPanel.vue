<!-- components/ShapePropertiesPanel.vue -->
<template>
  <div class="shape-properties">
    <PropertiesCommonShapeElementProperties
      :selected-element="selectedElement"
      :selected-object="selectedObject"
    />

    <!-- Additional shape-specific properties can be added here -->
    <div class="space-y-4 mt-6" v-if="hasAdditionalShapeProperties">
      <h4 class="text-sm font-semibold text-gray-800">Shape Specific</h4>

      <!-- Size Preset (moved from old implementation) -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-700"
          >Size Preset</label
        >
        <select
          v-model="sizePreset"
          @change="onSizePresetChange"
          class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300"
        >
          <option value="small">Small</option>
          <option value="medium">Medium</option>
          <option value="large">Large</option>
          <option value="custom">Custom</option>
        </select>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";

const canvasStore = useCanvasStore();

// Size preset (kept for backward compatibility)
const sizePreset = ref("small");

// Computed properties
const selectedElement = computed(() => {
  if (canvasStore.selectedElementId) {
    return canvasStore.domElements.find(
      (el) => el.id === canvasStore.selectedElementId
    );
  }
  return null;
});

const selectedObject = computed(() => {
  return canvasStore.selectedObjects[0];
});

const hasAdditionalShapeProperties = computed(() => {
  return (
    selectedElement.value?.subtype === "shape" ||
    selectedObject.value?.subtype === "shape"
  );
});

// Apply size preset (kept for backward compatibility)
const onSizePresetChange = () => {
  const element = selectedElement.value || selectedObject.value;
  if (!element) return;

  let newWidth = 100;
  let newHeight = 100;

  switch (sizePreset.value) {
    case "small":
      newWidth = 50;
      newHeight = 50;
      break;
    case "medium":
      newWidth = 150;
      newHeight = 150;
      break;
    case "large":
      newWidth = 300;
      newHeight = 300;
      break;
    case "custom":
      // Keep current size
      return;
  }

  // Update element size
  if (selectedElement.value) {
    canvasStore.updateElement(selectedElement.value.id, {
      size: { width: newWidth, height: newHeight },
    });
  } else if (selectedObject.value) {
    canvasStore.updateObject(selectedObject.value.id, {
      width: newWidth,
      height: newHeight,
    });
  }
};
</script>
