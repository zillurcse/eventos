<!-- components/CommonShapeElementProperties.vue -->
<template>
  <div class="common-shape-element-properties space-y-6">
    <!-- Geometry Section -->
    <div class="space-y-4">
      <!-- Rotation & Corner Radius -->
      <div class="flex flex-row gap-3">
        <div class="border rounded flex items-center flex-1">
          <span class="p-1 bg-gray-200 text-gray-700 w-8 text-center">
            <NuxtIcon name="iconoir:radius" class="text-base" />
          </span>
          <input
            v-model="cornerRadius"
            type="number"
            class="w-full px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-300 rounded-r"
            placeholder="0"
            @change="updateProperties"
          />
        </div>
      </div>
    </div>

    <!-- Stroke Properties Section -->
    <div class="space-y-4">
      <h4 class="text-sm font-semibold text-gray-800">Stroke Properties</h4>

      <!-- Dash Style & Line Cap -->
      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700">Dash</label>
          <select
            v-model="dashStyle"
            @change="updateProperties"
            class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300"
          >
            <option value="solid">Solid</option>
            <option value="dashed">Dashed</option>
            <option value="dotted">Dotted</option>
            <option value="dash-dot">Dash Dot</option>
          </select>
        </div>
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700"
            >Line Cap</label
          >
          <select
            v-model="lineCap"
            @change="updateProperties"
            class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300"
          >
            <option value="butt">Butt</option>
            <option value="round">Round</option>
            <option value="square">Square</option>
          </select>
        </div>
      </div>

      <!-- Line Join -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-700">Line Join</label>
        <select
          v-model="lineJoin"
          @change="updateProperties"
          class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300"
        >
          <option value="miter">Miter</option>
          <option value="round">Round</option>
          <option value="bevel">Bevel</option>
        </select>
      </div>

      <!-- Stroke Color & Width -->

      <div class="flex items-center justify-center gap-3">
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700"
            >Stroke Color</label
          >
          <div class="flex items-center justify-center">
            <input
              v-model="strokeColor"
              type="color"
              @change="updateProperties"
              class="w-12 h-8 border border-r-0 border-gray-300 cursor-pointer"
            />

            <input
              v-model="strokeColor"
              type="text"
              @change="updateProperties"
              class="w-full border border-l-0 border-gray-300 px-3 py-1 focus:outline-none focus:ring-2 focus:ring-blue-300 font-mono text-sm"
            />
          </div>
        </div>
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700">Width</label>
          <input
            type="number"
            v-model="strokeWidth"
            @change="updateProperties"
            class="w-full border border-gray-300 rounded px-3 py-1 focus:outline-none"
            min="0"
            step="0.1"
          />
        </div>
      </div>
    </div>

    <!-- Fill Properties Section -->
    <div class="space-y-4">
      <h4 class="text-sm font-semibold text-gray-800">Fill Properties</h4>

      <!-- Fill Color -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-700"
          >Fill Color</label
        >
        <div class="flex items-center">
          <input
            v-model="fillColor"
            type="color"
            @change="updateProperties"
            class="w-8 h-8 border rounded cursor-pointer"
          />
          <input
            v-model="fillColor"
            type="text"
            @change="updateProperties"
            class="flex-1 border rounded px-3 py-1 focus:outline-none focus:ring-2 focus:ring-blue-300 font-mono text-sm"
            placeholder="rgba(0,0,0,0.3)"
          />
        </div>
      </div>
    </div>

    <!-- Shadow Effects Section -->
    <div class="space-y-4">
      <h4 class="text-sm font-semibold text-gray-800">Shadow Effects</h4>

      <!-- Shadow Offset -->
      <div class="grid grid-cols-2 gap-3">
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700"
            >Shadow Offset X</label
          >
          <input
            v-model="shadowOffsetX"
            type="number"
            @change="updateProperties"
            class="w-full border rounded px-3 py-1 focus:outline-none focus:ring-2 focus:ring-blue-300"
            placeholder="0"
          />
        </div>
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700"
            >Shadow Offset Y</label
          >
          <input
            v-model="shadowOffsetY"
            type="number"
            @change="updateProperties"
            class="w-full border rounded px-3 py-1 focus:outline-none focus:ring-2 focus:ring-blue-300"
            placeholder="0"
          />
        </div>
      </div>

      <!-- Shadow Color & Blur -->
      <div class="flex items-center justify-center gap-3 pb-10">
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700"
            >Shadow Color</label
          >
          <div class="flex items-center justify-center">
            <input
              v-model="shadowColor"
              type="color"
              @change="updateProperties"
              class="w-12 h-8 border border-r-0 border-gray-300 cursor-pointer"
            />

            <input
              v-model="shadowColor"
              type="text"
              @change="updateProperties"
              class="w-full border border-l-0 border-gray-300 px-3 py-1 focus:outline-none focus:ring-2 focus:ring-blue-300 font-mono text-sm"
            />
          </div>
        </div>
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700">Blur</label>
          <input
            type="number"
            v-model="blur"
            @change="updateProperties"
            class="w-full border border-gray-300 rounded px-3 py-1 focus:outline-none"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";

const canvasStore = useCanvasStore();

// Props to accept the selected element/object
const props = defineProps<{
  selectedElement?: any;
  selectedObject?: any;
}>();

// Reactive properties
const xPosition = ref(0);
const yPosition = ref(0);
const length = ref(0);
const breadth = ref(0);
const rotation = ref(0);
const cornerRadius = ref(0);
const dashStyle = ref("solid");
const lineCap = ref("butt");
const lineJoin = ref("miter");
const fillColor = ref("rgba(0,0,0,0.3)");
const strokeColor = ref("#242525");
const strokeWidth = ref(1);
const shadowOffsetX = ref(0);
const shadowOffsetY = ref(0);
const shadowColor = ref("#000000");
const blur = ref(0);

// Computed properties to determine which element is active
const activeElement = computed(() => {
  return props.selectedElement || props.selectedObject;
});

// Sync properties from selected element/object
// Updated syncProperties function in CommonShapeElementProperties.vue
const syncProperties = () => {
  const element = activeElement.value;
  if (!element) return;

  // ✅ IMPROVED: Position synchronization
  if (element.position) {
    xPosition.value = Math.round(element.position.x);
    yPosition.value = Math.round(element.position.y);
  } else if (element.points && element.points.length >= 2) {
    const bounds = getObjectBounds(element);
    if (bounds) {
      xPosition.value = Math.round(bounds.x);
      yPosition.value = Math.round(bounds.y);
    }
  } else if (element.x !== undefined && element.y !== undefined) {
    xPosition.value = Math.round(element.x);
    yPosition.value = Math.round(element.y);
  }

  // ✅ IMPROVED: Dimensions with dynamic resizing support
  if (element.size) {
    // Use size property if available (updated during resize)
    length.value = Math.round(element.size.width);
    breadth.value = Math.round(element.size.height);

    // Update position from size for consistency
    if (element.position) {
      xPosition.value = Math.round(element.position.x);
      yPosition.value = Math.round(element.position.y);
    }
  } else if (element.points && element.points.length >= 2) {
    const bounds = getObjectBounds(element);
    if (bounds) {
      length.value = Math.round(bounds.width);
      breadth.value = Math.round(bounds.height);

      // ✅ Update size and position properties for consistency
      if (element) {
        if (!element.size) {
          element.size = { width: bounds.width, height: bounds.height };
        } else {
          element.size.width = bounds.width;
          element.size.height = bounds.height;
        }

        if (!element.position) {
          element.position = { x: bounds.x, y: bounds.y };
        } else {
          element.position.x = bounds.x;
          element.position.y = bounds.y;
        }
      }
    }
  } else if (element.length && element.breadth) {
    length.value = element.length;
    breadth.value = element.breadth;
  } else if (element.width && element.height) {
    length.value = element.width;
    breadth.value = element.height;
  }

  // ✅ IMPROVED: Rotation
  rotation.value = element.rotation || 0;

  // ✅ IMPROVED: Fill and stroke properties with fallbacks
  fillColor.value = element.fillColor || element.color || "rgba(0,0,0,0.3)";
  strokeColor.value = element.strokeColor || element.borderColor || "#242525";
  strokeWidth.value = element.strokeWidth || element.borderWidth || 1;

  // ✅ IMPROVED: Corner radius (for shapes)
  cornerRadius.value = element.cornerRadius || 0;

  // ✅ IMPROVED: Line properties with fallbacks
  dashStyle.value = element.dashStyle || "solid";
  lineCap.value = element.lineCap || "butt";
  lineJoin.value = element.lineJoin || "miter";

  // ✅ IMPROVED: Shadow properties
  shadowOffsetX.value = element.shadowOffsetX || 0;
  shadowOffsetY.value = element.shadowOffsetY || 0;
  shadowColor.value = element.shadowColor || "#000000";
  blur.value = element.blur || element.shadowBlur || 0;

  // ✅ NEW: Trigger property update to ensure UI reflects changes
  if (props.selectedElement || props.selectedObject) {
    setTimeout(() => {
      updateProperties();
    }, 0);
  }
};

// Helper function to get object bounds from points
const getObjectBounds = (obj: any) => {
  if (obj.points && obj.points.length >= 2) {
    const p1 = obj.points[0];
    const p2 = obj.points[1];
    return {
      x: Math.min(p1.x, p2.x),
      y: Math.min(p1.y, p2.y),
      width: Math.abs(p1.x - p2.x),
      height: Math.abs(p1.y - p2.y),
    };
  }
  return null;
};

// Update properties in store
const updateProperties = () => {
  const updates: any = {
    // Position and size
    rotation: rotation.value,

    // Fill and stroke
    fillColor: fillColor.value,
    strokeColor: strokeColor.value,
    strokeWidth: strokeWidth.value,
    borderWidth: strokeWidth.value, // For compatibility

    // Shape-specific properties
    cornerRadius: cornerRadius.value,
    dashStyle: dashStyle.value,
    lineCap: lineCap.value,
    lineJoin: lineJoin.value,

    // Shadow properties
    shadowOffsetX: shadowOffsetX.value,
    shadowOffsetY: shadowOffsetY.value,
    shadowColor: shadowColor.value,
    blur: blur.value,
  };

  // Handle position updates based on element type
  if (props.selectedElement) {
    updates.position = { x: xPosition.value, y: yPosition.value };
    updates.size = { width: length.value, height: breadth.value };
    canvasStore.updateElement(props.selectedElement.id, updates);
  } else if (props.selectedObject) {
    // For canvas objects, update points array for position/size
    if (
      props.selectedObject.points &&
      props.selectedObject.points.length >= 2
    ) {
      const newPoints = [
        { x: xPosition.value, y: yPosition.value },
        {
          x: xPosition.value + length.value,
          y: yPosition.value + breadth.value,
        },
      ];
      updates.points = newPoints;
    }

    // Also update direct properties for booth objects
    if (props.selectedObject.type === "booth") {
      updates.length = length.value;
      updates.breadth = breadth.value;
    }

    canvasStore.updateObject(props.selectedObject.id, updates);
  }
};

// Watch for changes in the active element
watch(
  () => activeElement.value,
  () => {
    syncProperties();
  },
  { immediate: true }
);
</script>
