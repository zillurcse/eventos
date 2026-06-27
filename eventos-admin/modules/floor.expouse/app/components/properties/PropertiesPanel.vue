<!-- components/PropertiesPanel.vue -->
<template>
  <div class="properties-panel space-y-6 pb-16 px-3">
    <!-- Floor Properties (Exclusive View) -->
    <PropertiesFloorProperties v-if="isFloorElement" />

    <template v-else>
      <!-- Common Position & Size Controls -->
      <PropertiesCommonPositionSizeControls
        v-if="hasSelection && !isLocked"
        :position="elementPosition"
        :size="elementSize"
        :rotation="rotation"
        @update="handlePositionSizeUpdate"
      />

      <!-- Booth Name Input -->
      <div
        v-if="isBoothElement && !isLocked"
        class="booth-name-control space-y-2 border-t pt-4"
      >
        <label class="block text-sm font-medium text-gray-700">Booth Name</label>
        <input
          :value="boothName"
          @input="handleBoothNameUpdate"
          type="text"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          placeholder="Enter booth name"
        />
      </div>

      <!-- 🆕 BOOTH TEXT STYLING CONTROLS (Only for Booths) -->
      <div
        v-if="isBoothElement && !isLocked"
        class="booth-text-styling space-y-4 border-t pt-4"
      >
        <h4 class="text-sm font-semibold text-gray-800">Booth Text Styling</h4>

        <!-- Booth Number Styling -->
        <div class="space-y-3">
          <h5 class="text-xs font-medium text-gray-700">Booth Number</h5>
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="block text-xs font-medium text-gray-600"
                >Font Size</label
              >
              <div class="flex items-center gap-2">
                <input
                  :value="boothNumberFontSize"
                  @input="handleBoothNumberFontSizeInput"
                  type="number"
                  min="8"
                  max="72"
                  class="w-full border border-gray-300 rounded-lg px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                />
                <span class="text-xs text-gray-500">px</span>
              </div>
            </div>
            <div class="space-y-1">
              <label class="block text-xs font-medium text-gray-600">Color</label>
              <input
                :value="boothNumberColor"
                @input="handleBoothNumberColorInput"
                type="color"
                class="w-full h-8 border border-gray-300 rounded-lg cursor-pointer"
              />
            </div>
          </div>
        </div>

        <!-- Booth Name Styling -->
        <div class="space-y-3">
          <h5 class="text-xs font-medium text-gray-700">Booth Name</h5>
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="block text-xs font-medium text-gray-600"
                >Font Size</label
              >
              <div class="flex items-center gap-2">
                <input
                  :value="boothNameFontSize"
                  @input="handleBoothNameFontSizeInput"
                  type="number"
                  min="8"
                  max="72"
                  class="w-full border border-gray-300 rounded-lg px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                />
                <span class="text-xs text-gray-500">px</span>
              </div>
            </div>
            <div class="space-y-1">
              <label class="block text-xs font-medium text-gray-600">Color</label>
              <input
                :value="boothNameColor"
                @input="handleBoothNameColorInput"
                type="color"
                class="w-full h-8 border border-gray-300 rounded-lg cursor-pointer"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- 🆕 BOOTH DISTANCE INPUT (Only for Booths) -->
      <div
        v-if="isBoothElement && !isLocked"
        class="booth-distance-control space-y-3 border-t pt-4"
      >
        <h4 class="text-sm font-semibold text-gray-800">
          Booth Creation Distance
        </h4>
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700">
            Distance for New Booth ({{ currentUnitLabel }})
          </label>
          <div class="flex items-center gap-2">
            <input
              :value="boothDistance"
              @input="handleBoothDistanceInput"
              type="number"
              min="0.1"
              step="0.1"
              class="flex-1 border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Enter distance"
            />
            <span class="text-sm text-gray-600">{{ currentUnitLabel }}</span>
          </div>
          <p class="text-xs text-gray-500">
            This distance will be used when creating new booths via arrow icons.
          </p>
          <div class="flex items-center gap-2 text-xs">
            <div class="flex items-center gap-1 text-blue-600">
              <NuxtIcon name="heroicons:information-circle" class="w-4 h-4" />
              <span
                >Current default: {{ defaultDistance.toFixed(2) }}
                {{ currentUnitLabel }}</span
              >
            </div>
          </div>
        </div>
      </div>

      <!-- Type-Specific Properties -->
      <div class="type-specific-properties">
      <!-- Text Properties -->
      <div v-if="isTextElement && !isLocked">
        <PropertiesTextPropertiesPanel />
        <PropertiesCommonLayerControls
          :z-index="zIndex"
          :is-visible="isVisible"
          :is-locked="isLocked"
          @update="handleLayerUpdate"
        />
      </div>

      <!-- Image Properties -->
      <div v-else-if="isImageElement" class="image-properties space-y-4">
        <h4 v-if="!isLocked" class="text-sm font-semibold text-gray-800">Image Properties</h4>
        <PropertiesCommonAppearanceControls
          v-if="!isLocked"
          :fill-color="fillColor"
          :stroke-color="strokeColor"
          :stroke-width="strokeWidth"
          :opacity="opacity"
          @update="handleAppearanceUpdate"
        />
        <PropertiesCommonLayerControls
          :z-index="zIndex"
          :is-visible="isVisible"
          :is-locked="isLocked"
          @update="handleLayerUpdate"
        />
      </div>

      <!-- Booth Properties (Layer Controls) -->
      <div v-else-if="isBoothElement">
        <PropertiesCommonLayerControls
          :z-index="zIndex"
          :is-visible="isVisible"
          :is-locked="isLocked"
          @update="handleLayerUpdate"
        />
      </div>

      <!-- Default Properties for other elements -->
      <div
        v-else-if="hasSelection"
        class="default-properties space-y-6"
      >
        <PropertiesCommonAppearanceControls
          v-if="!isLocked"
          :fill-color="fillColor"
          :stroke-color="strokeColor"
          :stroke-width="strokeWidth"
          :opacity="opacity"
          @update="handleAppearanceUpdate"
        />

        <!-- Drawing Properties -->
        <PropertiesDrawingProperties
          v-if="isDrawingElement && !isLocked"
          :dash-style="drawingProps.dashStyle"
          :line-cap="drawingProps.lineCap"
          :line-join="drawingProps.lineJoin"
          :corner-radius="drawingProps.cornerRadius"
          :shadow-offset-x="drawingProps.shadowOffsetX"
          :shadow-offset-y="drawingProps.shadowOffsetY"
          :shadow-color="drawingProps.shadowColor"
          :shadow-blur="drawingProps.shadowBlur"
          :element-type="selectedObject?.type"
          @update="handleDrawingPropsUpdate"
        />

        <PropertiesCommonLayerControls
          :z-index="zIndex"
          :is-visible="isVisible"
          :is-locked="isLocked"
          @update="handleLayerUpdate"
        />
      </div>

      <!-- Empty State -->
      <div
        v-else
        class="empty-state text-center py-8 text-gray-500 border border-dashed border-gray-300 rounded-lg"
      >
        <NuxtIcon
          name="heroicons:cursor-arrow-rays"
          class="w-12 h-12 mx-auto mb-3 opacity-50"
        />
        <p class="text-sm">No element selected</p>
        <p class="text-xs">Select an element to edit its properties</p>
      </div>
    </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useUiStore } from "@floorplan/stores/uiStore";
import { useBoothCreationDistance } from "@floorplan/composables/useBoothCreationDistance";
import { useCanvasObjects } from "@floorplan/composables/useCanvasObjects";

const canvasStore = ref(null);
const canvasObjects = ref(null);
const uiStore = useUiStore();
let isClient = false;

if (process.client) {
  canvasStore.value = useCanvasStore();
  canvasObjects.value = useCanvasObjects();
  isClient = true;
}

// 🆕 USE THE ENHANCED BOOTH DISTANCE COMPOSABLE WITH UNIT CONVERSION
const {
  defaultDistance,
  getDefaultDistance,
  getDefaultDistanceInCm,
  setDefaultDistance,
  convertDistance,
} = useBoothCreationDistance();

// Reactive properties
const elementPosition = ref({ x: 0, y: 0 });
const elementSize = ref({ width: 0, height: 0 });
const rotation = ref(0);
const fillColor = ref("#000000");
const strokeColor = ref("#000000");
const strokeWidth = ref(1);
const opacity = ref(1);
const zIndex = ref(0);
const isVisible = ref(true);
const isLocked = ref(false);

const boothName = ref("");

// Add to reactive properties section
const boothNumberFontSize = ref(14);
const boothNumberColor = ref("#1f2937");
const boothNameFontSize = ref(12);
const boothNameColor = ref("#374151");

// 🆕 Booth distance - reactive value that updates with unit changes
const boothDistance = ref(getDefaultDistance());

// 🆕 Get current unit label
const currentUnitLabel = computed(() => {
  const unitMap: { [key: string]: string } = {
    centimeter: "cm",
    meter: "m",
    feet: "ft",
    inches: "in",
  };
  return unitMap[uiStore.measurementUnit] || "cm";
});

// 🆕 Watch for measurement unit changes and convert the distance
watch(
  () => uiStore.measurementUnit,
  (newUnit, oldUnit) => {
    if (oldUnit && newUnit !== oldUnit && boothDistance.value > 0) {
      const unitMap: { [key: string]: string } = {
        centimeter: "cm",
        meter: "m",
        feet: "ft",
        inches: "in",
      };

      const oldUnitShort = unitMap[oldUnit] || "cm";
      const newUnitShort = unitMap[newUnit] || "cm";

      const currentValueInOldUnit = boothDistance.value;

      const toCm: { [key: string]: number } = {
        cm: 1,
        m: 100,
        ft: 30.48,
        in: 2.54,
      };

      const valueInCm = currentValueInOldUnit * (toCm[oldUnitShort] || 1);
      const convertedValue = valueInCm / (toCm[newUnitShort] || 1);
      const newValue = Math.round(convertedValue * 100) / 100;

      boothDistance.value = newValue;
      setDefaultDistance(newValue);
    }
  },
  { immediate: false }
);

// 🆕 Watch for default distance changes
watch(defaultDistance, (newDefaultDistance) => {
  if (!isBoothElement.value) {
    boothDistance.value = newDefaultDistance;
  }
});

const drawingProps = ref({
  dashStyle: "Dot Dash",
  lineCap: "Square",
  lineJoin: "Bevel",
  cornerRadius: 0,
  shadowOffsetX: 0,
  shadowOffsetY: 0,
  shadowColor: "#000000",
  shadowBlur: 0,
});

const selectedElement = computed(() => {
  if (!isClient || !canvasStore.value?.selectedElementId) return null;
  return canvasStore.value.domElements.find(
    (el) => el.id === canvasStore.value.selectedElementId
  );
});

const selectedObject = computed(() => {
  if (!isClient || !canvasStore.value) return undefined;
  return canvasStore.value.selectedObjects[0];
});

const hasSelection = computed(() => {
  return !!(selectedElement.value || selectedObject.value);
});

const DRAWING_TYPES = [
  "drawing",
  "wall",
  "pencil",
  "line",
  "arrow",
  "curve-arrow",
  "rectangle",
  "ellipse",
  "frame",
  "section",
];

const isFloorElement = computed(() => {
  const obj = selectedObject.value;
  if (!obj) return false;

  // STRICTLY WALL - Clicking door-arc should NOT select floor properties
  const isFloorType = obj.type === "wall"; 
  const currentFloorId = canvasStore.value?.currentFloorId;

  const belongsToFloor =
    (currentFloorId && obj.floorId === currentFloorId) ||
    (obj.id && obj.id.includes("Floor-"));

  return isFloorType && belongsToFloor;
});

const isDrawingElement = computed(() => {
  return (
    selectedObject.value?.type &&
    DRAWING_TYPES.includes(selectedObject.value.type) &&
    !isFloorElement.value // Exclude floor walls from generic drawing settings
  );
});

const isTextElement = computed(() => {
  return (
    selectedElement.value?.type === "text" ||
    selectedObject.value?.type === "text"
  );
});

const isBoothElement = computed(() => {
  return selectedObject.value?.type === "booth";
});

const isShapeElement = computed(() => {
  return (
    selectedElement.value?.type === "shape" ||
    selectedObject.value?.type === "shape" ||
    selectedElement.value?.type === "elements" ||
    selectedObject.value?.type === "rectangle" ||
    selectedObject.value?.type === "ellipse" ||
    selectedObject.value?.type === "frame" ||
    selectedObject.value?.type === "section"
  );
});

const isImageElement = computed(() => {
  return (
    selectedElement.value?.type === "image" ||
    selectedObject.value?.type === "image"
  );
});

const getObjectBounds = (obj: any) => {
  if (!obj) return null;
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

const syncProperties = () => {
  if (!isClient || !canvasStore.value) {
    elementPosition.value = { x: 0, y: 0 };
    elementSize.value = { width: 0, height: 0 };
    rotation.value = 0;
    return;
  }

  const currentSelectedElement = selectedElement.value;
  const currentSelectedObject = selectedObject.value;

  if (currentSelectedElement) {
    elementPosition.value = {
      x: currentSelectedElement.position?.x || 0,
      y: currentSelectedElement.position?.y || 0,
    };
    elementSize.value = {
      width: currentSelectedElement.size?.width || 0,
      height: currentSelectedElement.size?.height || 0,
    };
    rotation.value = currentSelectedElement.rotation || 0;
    fillColor.value =
      currentSelectedElement.fillColor ||
      currentSelectedElement.styleProps?.backgroundColor ||
      "#000000";
    strokeColor.value =
      currentSelectedElement.strokeColor ||
      currentSelectedElement.styleProps?.borderColor ||
      "#000000";
    strokeWidth.value =
      currentSelectedElement.strokeWidth ||
      currentSelectedElement.styleProps?.borderWidth ||
      1;
    opacity.value =
      currentSelectedElement.opacity !== undefined
        ? currentSelectedElement.opacity
        : 1;
    zIndex.value = currentSelectedElement.zIndex || 0;
    isVisible.value = currentSelectedElement.isVisible !== false;
    isLocked.value = currentSelectedElement.isLocked || false;
  } else if (currentSelectedObject) {
    const obj = currentSelectedObject;
    const bounds = getObjectBounds(obj);

    if (bounds) {
      elementPosition.value = { x: bounds.x, y: bounds.y };
      elementSize.value = { width: bounds.width, height: bounds.height };
    } else if (obj.position && obj.size) {
      elementPosition.value = { ...obj.position };
      elementSize.value = { ...obj.size };
    } else {
      elementPosition.value = { x: obj.x || 0, y: obj.y || 0 };
      elementSize.value = {
        width: obj.width || 100,
        height: obj.height || 100,
      };
    }

    rotation.value = obj.rotation || 0;
    fillColor.value = obj.fillColor || obj.fill || obj.color || "#000000";
    strokeColor.value = obj.strokeColor || obj.stroke || "#000000";
    strokeWidth.value = obj.strokeWidth || obj.borderWidth || 1;
    opacity.value = obj.opacity !== undefined ? obj.opacity : 1;
    zIndex.value = obj.zIndex || 0;
    isVisible.value = obj.isVisible !== false;
    isLocked.value = obj.isLocked || false;

    // 🆕 SYNC BOOTH CUSTOMIZATION PROPERTIES
    if (obj.type === "booth") {
      boothName.value = obj.booth_name || "";
      boothNumberFontSize.value = obj.boothNumberFontSize || 14;
      boothNumberColor.value = obj.boothNumberColor || "#1f2937";
      boothNameFontSize.value = obj.boothNameFontSize || 12;
      boothNameColor.value = obj.boothNameColor || "#374151";

      // SYNC BOOTH DISTANCE WITH UNIT CONVERSION
      if (obj.boothCreationDistance !== undefined) {
        const distanceInCm = obj.boothCreationDistance;
        const currentUnit = uiStore.measurementUnit || "centimeter";
        const unitMap: { [key: string]: string } = {
          centimeter: "cm",
          meter: "m",
          feet: "ft",
          inches: "in",
        };
        const currentUnitShort = unitMap[currentUnit] || "cm";
        const toCm: { [key: string]: number } = {
          cm: 1,
          m: 100,
          ft: 30.48,
          in: 2.54,
        };
        boothDistance.value =
          Math.round((distanceInCm / (toCm[currentUnitShort] || 1)) * 100) /
          100;
      } else {
        boothDistance.value = getDefaultDistance();
      }
    }

    if (isDrawingElement.value) {
      syncDrawingProps();
    }
  } else {
    elementPosition.value = { x: 0, y: 0 };
    elementSize.value = { width: 0, height: 0 };
    rotation.value = 0;
    fillColor.value = "#000000";
    strokeColor.value = "#000000";
    strokeWidth.value = 1;
    opacity.value = 1;
    zIndex.value = 0;
    isVisible.value = true;
    isLocked.value = false;
    boothDistance.value = getDefaultDistance();
  }
};

const syncDrawingProps = () => {
  if (selectedObject.value && isDrawingElement.value) {
    const obj = selectedObject.value;
    drawingProps.value = {
      dashStyle: obj.dashStyle || "Dot Dash",
      lineCap: obj.lineCap || "Square",
      lineJoin: obj.lineJoin || "Bevel",
      cornerRadius: obj.cornerRadius || 0,
      shadowOffsetX: obj.shadowOffsetX || 0,
      shadowOffsetY: obj.shadowOffsetY || 0,
      shadowColor: obj.shadowColor || "#000000",
      shadowBlur: obj.shadowBlur || 0,
    };
  }
};

const handleBoothNameUpdate = (event: Event) => {
  const input = event.target as HTMLInputElement;
  boothName.value = input.value;

  if (!isClient || !canvasStore.value || !selectedObject.value) return;

  canvasStore.value.updateObject(selectedObject.value.id, {
    booth_name: boothName.value,
  });
  updateBoothTextStyling();
};

// 🆕 BOOTH CUSTOMIZATION INPUT HANDLERS

const handleBoothNameFontSizeInput = (event: Event) => {
  const input = event.target as HTMLInputElement;
  const newValue = parseInt(input.value);

  if (isNaN(newValue) || newValue < 8 || newValue > 72) return;

  boothNameFontSize.value = newValue;
  updateBoothTextStyling();
};

const handleBoothNumberFontSizeInput = (event: Event) => {
  const input = event.target as HTMLInputElement;
  const newValue = parseInt(input.value);

  if (isNaN(newValue) || newValue < 8 || newValue > 72) return;

  boothNumberFontSize.value = newValue;
  updateBoothTextStyling();
};

const handleBoothNumberColorInput = (event: Event) => {
  const input = event.target as HTMLInputElement;
  boothNumberColor.value = input.value;
  updateBoothTextStyling();
};

const handleBoothNameColorInput = (event: Event) => {
  const input = event.target as HTMLInputElement;
  boothNameColor.value = input.value;
  updateBoothTextStyling();
};

const updateBoothTextStyling = () => {
  if (!isClient || !canvasStore.value || !selectedObject.value) return;

  console.log("🎨 Updating booth text styling:", {
    boothNumberFontSize: boothNumberFontSize.value,
    boothNumberColor: boothNumberColor.value,
    boothNameFontSize: boothNameFontSize.value,
    boothNameColor: boothNameColor.value,
  });

  canvasStore.value.updateObject(selectedObject.value.id, {
    boothNumberFontSize: boothNumberFontSize.value,
    boothNumberColor: boothNumberColor.value,
    boothNameFontSize: boothNameFontSize.value,
    boothNameColor: boothNameColor.value,
    boothName: boothName.value,
  });
};

// 🆕 HANDLE BOOTH DISTANCE INPUT WITH UNIT CONVERSION
const handleBoothDistanceInput = (event: Event) => {
  const input = event.target as HTMLInputElement;
  const newValue = parseFloat(input.value);

  if (isNaN(newValue) || newValue <= 0) return;

  boothDistance.value = Math.round(newValue * 100) / 100;
  handleBoothDistanceUpdate();
};

// 🆕 HANDLE BOOTH DISTANCE UPDATE WITH UNIT CONVERSION
const handleBoothDistanceUpdate = () => {
  if (!isClient || !canvasStore.value) return;

  const currentUnit = uiStore.measurementUnit || "centimeter";
  const unitMap: { [key: string]: string } = {
    centimeter: "cm",
    meter: "m",
    feet: "ft",
    inches: "in",
  };
  const currentUnitShort = unitMap[currentUnit] || "cm";
  const toCm: { [key: string]: number } = {
    cm: 1,
    m: 100,
    ft: 30.48,
    in: 2.54,
  };
  const distanceInCm = boothDistance.value * (toCm[currentUnitShort] || 1);

  if (selectedObject.value && selectedObject.value.type === "booth") {
    canvasStore.value.updateObject(selectedObject.value.id, {
      boothCreationDistance: distanceInCm,
    });
  }

  setDefaultDistance(boothDistance.value);
};

const handlePositionSizeUpdate = (updates: any) => {
  if (!isClient || !canvasStore.value) return;

  const currentSelectedElement = selectedElement.value;
  const currentSelectedObject = selectedObject.value;

  if (currentSelectedElement) {
    const elementUpdates: any = {};
    if (updates.position) elementUpdates.position = updates.position;
    if (updates.size) elementUpdates.size = updates.size;
    if (updates.rotation !== undefined)
      elementUpdates.rotation = updates.rotation;

    canvasStore.value.updateElement(currentSelectedElement.id, elementUpdates);
  } else if (currentSelectedObject) {
    const objectUpdates: any = {};
    if (updates.rotation !== undefined)
      objectUpdates.rotation = updates.rotation;

    if (updates.position && updates.size && currentSelectedObject.points) {
      const newPoints = [
        { x: updates.position.x, y: updates.position.y },
        {
          x: updates.position.x + updates.size.width,
          y: updates.position.y + updates.size.height,
        },
      ];
      objectUpdates.points = newPoints;

      if (currentSelectedObject.boundingBox) {
        objectUpdates.boundingBox = {
          x: updates.position.x,
          y: updates.position.y,
          width: updates.size.width,
          height: updates.size.height,
        };
      }
    } else if (updates.position && currentSelectedObject.points) {
      const deltaX = updates.position.x - elementPosition.value.x;
      const deltaY = updates.position.y - elementPosition.value.y;
      const newPoints = currentSelectedObject.points.map((point: any) => ({
        x: point.x + deltaX,
        y: point.y + deltaY,
      }));
      objectUpdates.points = newPoints;

      if (currentSelectedObject.boundingBox) {
        objectUpdates.boundingBox = {
          ...currentSelectedObject.boundingBox,
          x: updates.position.x,
          y: updates.position.y,
        };
      }

      // 🆕 MOVE DESCENDANTS (Children) for Containers
      if (
        (currentSelectedObject.type === "frame" ||
          currentSelectedObject.type === "section") &&
        canvasObjects.value
      ) {
        const descendants = canvasObjects.value.getDescendants([
          currentSelectedObject,
        ]);

        // Move canvas objects (Shapes, Booths, etc.)
        descendants.objects.forEach((obj: any) => {
          const newPoints = obj.points.map((p: any) => ({
            x: p.x + deltaX,
            y: p.y + deltaY,
          }));

          const childUpdates: any = { points: newPoints };
          if (obj.boundingBox) {
            childUpdates.boundingBox = {
              ...obj.boundingBox,
              x: obj.boundingBox.x + deltaX,
              y: obj.boundingBox.y + deltaY,
            };
          }
          canvasStore.value.updateObject(obj.id, childUpdates);
        });

        // Move DOM elements (Text, Icons, etc.)
        descendants.elements.forEach((el: any) => {
          canvasStore.value.updateElement(el.id, {
            position: { x: el.position.x + deltaX, y: el.position.y + deltaY },
          });
        });
      }
    } else if (updates.size && currentSelectedObject.points) {
      const scaleX = updates.size.width / elementSize.value.width;
      const scaleY = updates.size.height / elementSize.value.height;
      const newPoints = currentSelectedObject.points.map((point: any) => ({
        x:
          elementPosition.value.x +
          (point.x - elementPosition.value.x) * scaleX,
        y:
          elementPosition.value.y +
          (point.y - elementPosition.value.y) * scaleY,
      }));
      objectUpdates.points = newPoints;

      if (currentSelectedObject.boundingBox) {
        objectUpdates.boundingBox = {
          ...currentSelectedObject.boundingBox,
          width: updates.size.width,
          height: updates.size.height,
        };
      }
    }

    canvasStore.value.updateObject(currentSelectedObject.id, objectUpdates);
  }

  if (updates.position) elementPosition.value = updates.position;
  if (updates.size) elementSize.value = updates.size;
  if (updates.rotation !== undefined) rotation.value = updates.rotation;
};

const handleAppearanceUpdate = (updates: any) => {
  if (!isClient || !canvasStore.value) return;

  if (updates.fillColor) fillColor.value = updates.fillColor;
  if (updates.strokeColor) strokeColor.value = updates.strokeColor;
  if (updates.strokeWidth) strokeWidth.value = updates.strokeWidth;
  if (updates.opacity) opacity.value = updates.opacity;

  const currentSelectedElement = selectedElement.value;
  const currentSelectedObject = selectedObject.value;

  if (currentSelectedElement) {
    canvasStore.value.updateElement(currentSelectedElement.id, updates);
  } else if (currentSelectedObject) {
    const objectUpdates = { ...updates };
    if (updates.fillColor && currentSelectedObject.type === "booth") {
      objectUpdates.color = updates.fillColor;
    }
    canvasStore.value.updateObject(currentSelectedObject.id, objectUpdates);
  }
};

const handleLayerUpdate = (updates: any) => {
  if (!isClient || !canvasStore.value) return;

  if (updates.zIndex !== undefined) zIndex.value = updates.zIndex;
  if (updates.isVisible !== undefined) isVisible.value = updates.isVisible;
  if (updates.isLocked !== undefined) isLocked.value = updates.isLocked;

  const currentSelectedElement = selectedElement.value;
  const currentSelectedObject = selectedObject.value;

  if (currentSelectedElement) {
    canvasStore.value.updateElement(currentSelectedElement.id, updates);
  } else if (currentSelectedObject) {
    canvasStore.value.updateObject(currentSelectedObject.id, updates);
  }
};

const handleDrawingPropsUpdate = (updates: any) => {
  if (!isClient || !canvasStore.value || !selectedObject.value) return;

  canvasStore.value.updateObject(selectedObject.value.id, updates);
  drawingProps.value = { ...drawingProps.value, ...updates };
};

if (isClient) {
  watch(
    [selectedElement, selectedObject],
    () => {
      syncProperties();
    },
    { immediate: true, deep: true }
  );

  watch(
    () => [canvasStore.value.domElements, canvasStore.value.objects],
    () => {
      syncProperties();
    },
    { deep: true }
  );
}

if (isClient) {
  syncProperties();
}
</script>
