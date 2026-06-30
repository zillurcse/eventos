<!-- components/CommonAppearanceControls.vue -->
<template>
  <div class="common-appearance-controls space-y-4">
    <h4 class="text-sm font-semibold text-gray-800">Appearance</h4>

    <div v-if="showTextColor" class="space-y-2">
      <label class="block text-sm font-medium text-gray-700">Background Color</label>

      <div class="flex items-center gap-3">
        <!-- Group: Color Picker + Hex Input -->
        <div
          class="flex items-center border border-gray-300 rounded-md overflow-hidden shadow-sm bg-white"
        >
          <!-- Color Picker -->
          <div
            class="flex items-center justify-center bg-gray-50 px-2 border-r border-gray-300"
          >
            <input
              v-model="textColor"
              type="color"
              @input="updateProperties"
              class="cursor-pointer w-8 h-8 border-none outline-none rounded-sm bg-#000000"
              title="Pick body color"
            />
          </div>

          <!-- Text Input -->
          <input
            v-model="textColor"
            type="text"
            @input="updateProperties"
            class="px-3 py-1 text-sm font-mono text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-400"
            placeholder="#000000"
          />
        </div>

        <!-- Reset Icon Button -->
        <button
          @click="setTextColor('#000000')"
          class="p-1 text-gray-700 border border-gray-300 rounded-md bg-white hover:bg-gray-100 transition-all shadow-sm flex items-center justify-center"
          title="Reset to black"
        >
          <NuxtIcon name="streamline-plump:transparent-remix" class="w-5 h-5" />
        </button>
      </div>
    </div>

    <!-- Fill Color with #000000 Option -->
    <div v-if="!isWallObject && !showTextColor" class="space-y-2">
      <label class="block text-sm font-medium text-gray-700">Fill Color</label>

      <div class="flex items-center gap-3">
        <!-- Group: Color Picker + Hex Input -->
        <div
          class="flex items-center border border-gray-300 rounded-md overflow-hidden shadow-sm bg-white"
        >
          <!-- Color Picker -->
          <div
            class="flex items-center justify-center bg-gray-50 px-2 border-r border-gray-300"
          >
            <input
              v-model="fillColor"
              type="color"
              @input="updateProperties"
              class="cursor-pointer w-8 h-8 border-none outline-none rounded-sm bg-transparent"
              title="Pick fill color"
            />
          </div>

          <!-- Text Input -->
          <input
            v-model="fillColor"
            type="text"
            @input="updateProperties"
            class="px-3 py-1 text-sm font-mono text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-400"
            placeholder="#000000"
          />
        </div>

        <!-- #000000 Icon Button (inline) -->
        <button
          @click="setTransparentFill"
          class="p-1 border border-gray-200 rounded-md bg-gray-50 hover:bg-gray-100 transition-all shadow-sm flex items-center justify-center"
          title="Set transparent fill"
        >
          <NuxtIcon name="streamline-plump:transparent-remix" class="w-5 h-5" />
        </button>
      </div>
    </div>

    <!-- Stroke Properties -->
    <div v-if="showStroke && !showTextColor" class="space-y-2">
      <label class="block text-sm font-medium text-gray-700">Stroke</label>

      <div class="flex items-center gap-3">
        <!-- Group: Color Picker + Hex Input -->
        <div
          class="flex items-center border border-gray-300 rounded-md overflow-hidden shadow-sm bg-white"
        >
          <!-- Color Picker -->
          <div
            class="flex items-center justify-center bg-gray-50 px-2 border-r border-gray-300"
          >
            <input
              v-model="strokeColor"
              type="color"
              @input="updateProperties"
              class="cursor-pointer w-8 h-8 border-none outline-none rounded-sm bg-#000000"
              title="Pick stroke color"
            />
          </div>

          <!-- Text Input -->
          <input
            v-model="strokeColor"
            type="text"
            @input="updateProperties"
            class="px-3 py-1 text-sm font-mono text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-400"
            placeholder="#000000"
          />
        </div>

        <!-- Stroke Width Input -->
        <div
          class="flex items-center gap-1 border border-gray-300 rounded-md px-2 py-1 shadow-sm bg-white"
        >
          <input
            v-model.number="strokeWidth"
            type="number"
            min="0"
            @input="updateProperties"
            class="w-16 focus:outline-none font-mono text-gray-800"
          />
          <span class="text-xs text-gray-500">px</span>
        </div>
      </div>
    </div>

    <!-- Opacity -->
    <div class="space-y-2">
      <label class="block text-sm font-medium text-gray-700">
        Opacity: {{ (opacity * 100).toFixed(0) }}%
      </label>
      <input
        v-model.number="opacity"
        type="range"
        min="0"
        max="1"
        step="0.1"
        @input="updateProperties"
        class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer slider"
      />
    </div>
    
    <!-- Label Property (Editable Label) -->
    <div v-if="hasLabel" class="space-y-2">
      <label class="block text-sm font-medium text-gray-700">Label Text</label>
      <input
        v-model="label"
        type="text"
        @input="updateProperties"
        class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-400 bg-white text-gray-800 font-medium"
        placeholder="Enter label (e.g. Frame 1)..."
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";

const canvasStore = useCanvasStore();

// Props for external control
interface Props {
  fillColor?: string;
  strokeColor?: string;
  strokeWidth?: number;
  opacity?: number;
  textColor?: string;
}

const props = withDefaults(defineProps<Props>(), {
  fillColor: "transparent",
  strokeColor: "#000000",
  strokeWidth: 0,
  opacity: 1,
  textColor: "#000000",
});

// Emits for external control
const emit = defineEmits<{
  update: [
    updates: {
      fillColor?: string;
      strokeColor?: string;
      strokeWidth?: number;
      opacity?: number;
      textColor?: string;
    }
  ];
}>();

// Reactive properties
const fillColor = ref(props.fillColor);
const strokeColor = ref(props.strokeColor);
const strokeWidth = ref(props.strokeWidth);
const opacity = ref(props.opacity);
const textColor = ref(props.textColor);
const label = ref("");

// Computed properties to get current selection
const selectedElement = computed(() => {
  if (canvasStore.selectedElementId) {
    return canvasStore.domElements.find(
      (el) => el.id === canvasStore.selectedElementId
    );
  }
  return null;
});

const selectedObject = computed(() => {
  if (canvasStore.selectedObjects.length > 0) {
    return canvasStore.selectedObjects[0];
  }
  return null;
});

const hasSelection = computed(() => {
  return selectedElement.value || selectedObject.value;
});

// Check if current selection is a drawing element
const isDrawingElement = computed(() => {
  if (selectedObject.value) {
    const drawingTypes = ["drawing", "frame", "section", "rectangle", "ellipse", "line", "arrow", "curve-arrow"];
    return drawingTypes.includes(selectedObject.value.type);
  }
  return false;
});

const isWallObject = computed(() => {
  if (selectedObject.value) {
    return selectedObject.value.type === "wall";
  }
  return false;
});

// Show text color for ALL elements that can have text content or icons
const showTextColor = computed(() => {
  if (selectedElement.value) {
    // Show for all DOM elements since they can potentially have text
    return true;
  }
  if (selectedObject.value) {
    // Show for canvas objects that can have text
    return (
      selectedObject.value.type === "text" ||
      selectedObject.value.type === "drawing"
    );
  }
  return props.textColor !== undefined;
});

const hasLabel = computed(() => {
  if (selectedObject.value) {
    return ["frame", "section"].includes(selectedObject.value.type);
  }
  return false;
});

// Show stroke for elements that support strokes
const showStroke = computed(() => {
  if (selectedElement.value) {
    return selectedElement.value.type !== "text";
  }
  if (selectedObject.value) {
    // Drawing elements definitely support strokes
    return selectedObject.value.type !== "text";
  }
  return true;
});

// Helper function to check if color is #000000
const isTransparent = (color: string): boolean => {
  return (
    color === "#000000" ||
    color === "rgba(0, 0, 0, 0)" ||
    color === "#000000" ||
    color?.includes("rgba(0, 0, 0, 0)") ||
    color?.includes("#000000")
  );
};

// Helper function to set #000000 color
const setTransparentColor = (): string => {
  return "transparent";
};

// Sync properties from current selection - ENHANCED FOR DRAWING ELEMENTS
const syncPropertiesFromSelection = () => {
  if (selectedElement.value) {
    const style = selectedElement.value.styleProps || {};
    const element = selectedElement.value;

    console.log("🔄 Syncing from DOM element:", { element, style });

    // For ALL elements, sync text color from 'color' property
    textColor.value = style.color || "#000000";

    // Fill color from various possible properties
    fillColor.value = style.fill || style.backgroundColor || "#000000";

    // Stroke properties
    strokeColor.value = style.stroke || style.borderColor || "#000000";
    strokeWidth.value = style.strokeWidth || 0;

    // Opacity
    opacity.value = style.opacity !== undefined ? style.opacity : 1;
  } else if (selectedObject.value) {
    const obj = selectedObject.value;
    console.log("🔄 Syncing from canvas object:", obj);

    // For drawing elements, prioritize fill and stroke properties
    if (isDrawingElement.value) {
      // Fill color - check multiple possible properties
      fillColor.value =
        obj.fillColor || obj.fill || obj.backgroundColor || obj.color || "#000000";

      // Stroke color and width
      strokeColor.value =
        obj.strokeColor || obj.stroke || obj.borderColor || "#000000";
      strokeWidth.value = obj.strokeWidth || obj.borderWidth || 0;

      // Opacity
      opacity.value = obj.opacity !== undefined ? obj.opacity : 1;

      // Text color (for drawing elements that might have text)
      textColor.value = obj.textColor || obj.color || "#000000";
    }

    // Sync label
    if (hasLabel.value) {
      label.value = obj.label || "";
    }
  }
};

// Set #000000 fill
const setTransparentFill = () => {
  fillColor.value = setTransparentColor();
  updateProperties();
};

// Set specific fill color
const setFillColor = (color: string) => {
  fillColor.value = color;
  updateProperties();
};

// Set specific text color
const setTextColor = (color: string) => {
  textColor.value = color;
  updateProperties();
};

// Update properties - ENHANCED FOR DRAWING ELEMENTS
const updateProperties = () => {
  console.log("🔄 Updating appearance properties:", {
    fillColor: fillColor.value,
    strokeColor: strokeColor.value,
    strokeWidth: strokeWidth.value,
    opacity: opacity.value,
    textColor: textColor.value,
    selectedElement: selectedElement.value,
    selectedObject: selectedObject.value,
    isDrawingElement: isDrawingElement.value,
  });

  // For DOM elements
  if (selectedElement.value) {
    const updates: any = {
      styleProps: {
        ...selectedElement.value.styleProps,
        // Always apply these properties
        opacity: Number(opacity.value),
        color: textColor.value, // TEXT COLOR APPLIED TO ALL ELEMENTS
      },
    };

    // Apply fill color (as both fill and backgroundColor for compatibility)
    if (!isTransparent(fillColor.value)) {
      updates.styleProps.fill = fillColor.value;
      updates.styleProps.backgroundColor = fillColor.value;
    } else {
      updates.styleProps.fill = setTransparentColor();
      updates.styleProps.backgroundColor = setTransparentColor();
    }

    // Apply stroke properties for non-text elements
    if (showStroke.value) {
      updates.styleProps.stroke = strokeColor.value;
      updates.styleProps.strokeWidth = Number(strokeWidth.value);
      updates.styleProps.borderColor = strokeColor.value;
      updates.styleProps.borderWidth = `${strokeWidth.value}px`;
    } else {
      // Remove stroke properties for text elements
      delete updates.styleProps.stroke;
      delete updates.styleProps.strokeWidth;
      delete updates.styleProps.borderColor;
      delete updates.styleProps.borderWidth;
    }

    console.log("📝 Sending DOM element updates:", updates);
    canvasStore.updateElement(selectedElement.value.id, updates);
  }
  // For canvas objects (including drawing elements)
  else if (selectedObject.value) {
    const updates: any = {
      opacity: Number(opacity.value),
    };

    // For drawing elements, apply both fill and stroke properties
    if (isDrawingElement.value) {
      updates.fill = fillColor.value;
      updates.fillColor = fillColor.value;
      updates.stroke = strokeColor.value;
      updates.strokeColor = strokeColor.value;
      updates.strokeWidth = Number(strokeWidth.value);
      updates.textColor = textColor.value;

      updates.color = textColor.value;
    }

    if (hasLabel.value) {
      updates.label = label.value;
    }

    console.log("📝 Sending canvas object updates:", updates);
    canvasStore.updateObject(selectedObject.value.id, updates);
  }
  // For external control
  else if (!hasSelection.value) {
    const updates: any = {};

    if (fillColor.value !== props.fillColor)
      updates.fillColor = fillColor.value;
    if (strokeColor.value !== props.strokeColor)
      updates.strokeColor = strokeColor.value;
    if (strokeWidth.value !== props.strokeWidth)
      updates.strokeWidth = strokeWidth.value;
    if (opacity.value !== props.opacity) updates.opacity = opacity.value;
    if (textColor.value !== props.textColor)
      updates.textColor = textColor.value;

    if (Object.keys(updates).length > 0) {
      emit("update", updates);
    }
  }
};

// ========== WATCHERS ==========

// Watch for selection changes and sync properties
watch(
  [selectedElement, selectedObject],
  () => {
    console.log("👀 Selection changed, syncing properties");
    if (hasSelection.value) {
      syncPropertiesFromSelection();
    }
  },
  { immediate: true }
);

// Watch for prop changes (when controlled by parent)
watch(
  () => props.fillColor,
  (newColor) => {
    if (!hasSelection.value) fillColor.value = newColor;
  }
);

watch(
  () => props.strokeColor,
  (newColor) => {
    if (!hasSelection.value) strokeColor.value = newColor;
  }
);

watch(
  () => props.strokeWidth,
  (newWidth) => {
    if (!hasSelection.value) strokeWidth.value = newWidth;
  }
);

watch(
  () => props.opacity,
  (newOpacity) => {
    if (!hasSelection.value) opacity.value = newOpacity;
  }
);

watch(
  () => props.textColor,
  (newColor) => {
    if (!hasSelection.value) textColor.value = newColor;
  }
);
</script>

<style scoped>
.slider::-webkit-slider-thumb {
  appearance: none;
  height: 16px;
  width: 16px;
  border-radius: 50%;
  background: #3b82f6;
  cursor: pointer;
  border: 2px solid #ffffff;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
}

.slider::-moz-range-thumb {
  height: 16px;
  width: 16px;
  border-radius: 50%;
  background: #3b82f6;
  cursor: pointer;
  border: 2px solid #ffffff;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
}
</style>
