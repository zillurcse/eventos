<!-- components/properties/FloorProperties.vue -->
<template>
  <div class="floor-properties space-y-4">
    <!-- Floor Name -->
    <div v-if="!isWallLocked" class="space-y-2">
      <label class="block text-sm font-semibold text-gray-800">Floor Name</label>
      <input
        v-model="floorName"
        type="text"
        @change="updateFloorName"
        class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300"
        placeholder="Enter floor name"
      />
    </div>

    <!-- Floor Information -->
    <div v-if="selectedFloor && !isWallLocked" class="space-y-3 pt-4 border-t border-gray-200">
      <h4 class="text-sm font-semibold text-gray-800">Floor Information</h4>

      <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
          <span class="text-gray-600">Created:</span>
          <p class="font-medium">{{ formatDate(selectedFloor.created_at) }}</p>
        </div>
        <div>
          <span class="text-gray-600">Updated:</span>
          <p class="font-medium">{{ formatDate(selectedFloor.updated_at) }}</p>
        </div>
        <div class="col-span-2">
            <span class="text-gray-600">Dimensions:</span>
            <p class="font-medium">
              {{ formattedDimensions.width }} × {{ formattedDimensions.height }}
            </p>
        </div>
        <div class="col-span-2">
          <div class="flex items-center gap-2">
            <span class="text-gray-600 shrink-0">Area:</span>
            <div class="flex items-baseline gap-1 font-medium text-blue-600">
              <span class="text-base">{{ formattedArea.value }}</span>
              <span class="text-xs text-gray-500">{{ formattedArea.unit }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Dimensions (Custom Controls) -->
    <div v-if="!isWallLocked" class="space-y-4 pt-4 border-t border-gray-200">
        <h4 class="text-sm font-semibold text-gray-800">Dimensions</h4>
        
        <div class="grid grid-cols-2 gap-3">
             <!-- Width -->
             <div class="flex flex-col gap-1">
                 <label class="text-xs text-gray-500">Width</label>
                 <div class="flex items-center border rounded-md px-2 bg-white">
                      <input
                        type="number"
                        v-model.number="displayDimensions.width"
                        @input="handleWidthInput"
                        class="w-full py-1 text-sm outline-none"
                      />
                      <span class="text-xs text-gray-400 pl-1">{{ currentUnit }}</span>
                 </div>
             </div>

             <!-- Height -->
             <div class="flex flex-col gap-1">
                 <label class="text-xs text-gray-500">Height</label>
                 <div class="flex items-center border rounded-md px-2 bg-white">
                      <input
                        type="number"
                        v-model.number="displayDimensions.height"
                        @input="handleHeightInput"
                        class="w-full py-1 text-sm outline-none"
                      />
                      <span class="text-xs text-gray-400 pl-1">{{ currentUnit }}</span>
                 </div>
             </div>
        </div>
    </div>


    <!-- Wall Appearance (Individual Controls) -->
    <div v-if="!isWallLocked" class="space-y-4 pt-4 border-t border-gray-200">
      <h4 class="text-sm font-semibold text-gray-800">Appearance</h4>

      <!-- Stroke Properties -->
      <div class="space-y-2">
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
                v-model="wallStrokeColor"
                type="color"
                @input="updateWallAppearance"
                class="cursor-pointer w-8 h-8 border-none outline-none rounded-sm"
                title="Pick stroke color"
              />
            </div>

            <!-- Text Input -->
            <input
              v-model="wallStrokeColor"
              type="text"
              @input="updateWallAppearance"
              class="px-3 py-1 text-sm font-mono text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-400"
              placeholder="#000000"
            />
          </div>

          <!-- Stroke Width Input -->
          <div
            class="flex items-center gap-1 border border-gray-300 rounded-md px-2 py-1 shadow-sm bg-white"
          >
            <input
              v-model.number="wallStrokeWidth"
              type="number"
              min="0"
              @input="updateWallAppearance"
              class="w-16 focus:outline-none font-mono text-gray-800"
            />
            <span class="text-xs text-gray-500">px</span>
          </div>
        </div>
      </div>

      <!-- Opacity -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-700">
          Opacity: {{ (wallOpacity * 100).toFixed(0) }}%
        </label>
        <input
          v-model.number="wallOpacity"
          type="range"
          min="0"
          max="1"
          step="0.1"
          @input="updateWallAppearance"
          class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer slider"
        />
      </div>
    </div>

    <!-- Layer Controls -->
    <div class="space-y-4 pt-4 border-t border-gray-200" v-if="selectedWallObject">
        <h4 class="text-sm font-semibold text-gray-800">Layer</h4>
        
        <div class="space-y-3">
             <!-- Z-Index -->
             <div class="flex justify-between items-center">
                  <label class="text-sm text-gray-600">Stack Order</label>
                  <div class="flex items-center bg-gray-100 rounded-lg p-1">
                      <button @click="updateZIndex(-1)" class="p-1 hover:bg-white rounded shadow-sm" title="Send Backward">
                          <NuxtIcon name="mdi:layers-minus" class="w-4 h-4" />
                      </button>
                      <span class="mx-2 text-xs w-8 text-center">{{ wallZIndex }}</span>
                      <button @click="updateZIndex(1)" class="p-1 hover:bg-white rounded shadow-sm" title="Bring Forward">
                           <NuxtIcon name="mdi:layers-plus" class="w-4 h-4" />
                      </button>
                  </div>
             </div>

             <!-- Visibility & Lock -->
             <div class="flex justify-between gap-2">
                 <button 
                    @click="toggleVisibility"
                    class="flex-1 flex items-center justify-center gap-2 py-2 border rounded-md text-sm transition-colors"
                    :class="isWallVisible ? 'bg-white text-gray-700 hover:bg-gray-50' : 'bg-gray-100 text-gray-400'"
                 >
                    <NuxtIcon :name="isWallVisible ? 'heroicons:eye' : 'heroicons:eye-slash'" class="w-4 h-4" />
                    {{ isWallVisible ? 'Visible' : 'Hidden' }}
                 </button>

                 <button 
                    @click="toggleLock"
                    class="flex-1 flex items-center justify-center gap-2 py-2 border rounded-md text-sm transition-colors"
                    :class="isWallLocked ? 'bg-red-50 text-red-600 border-red-200' : 'bg-white text-gray-700 hover:bg-gray-50'"
                 >
                    <NuxtIcon :name="isWallLocked ? 'heroicons:lock-closed' : 'heroicons:lock-open'" class="w-4 h-4" />
                    {{ isWallLocked ? 'Locked' : 'Unlocked' }}
                 </button>
             </div>
        </div>
    </div>
    <!-- Danger Zone (Delete Floor) -->
    <div v-if="selectedFloor && !isWallLocked" class="space-y-4 pt-4 border-t border-gray-200">
      <h4 class="text-sm font-semibold text-red-700">Danger Zone</h4>
      <p class="text-xs text-gray-600">
        Deleting this floor will permanently remove all objects, elements, and
        associated data. This action cannot be undone.
      </p>
      <button
        @click="showDeleteDialog = true"
        class="flex items-center justify-center gap-2 w-full px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all duration-200 font-medium shadow-md"
      >
        <NuxtIcon name="heroicons:trash" class="w-5 h-5" />
        <span>Delete This Floor</span>
      </button>
    </div>

    <!-- Delete Confirmation Dialog -->
    <teleport to="body">
      <div
        v-if="showDeleteDialog"
        class="fixed inset-0 bg-black/60 flex items-center justify-center z-[9999] p-4"
        @click.self="showDeleteDialog = false"
      >
        <div
          class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden animate-in fade-in zoom-in duration-300"
        >
          <div class="bg-gradient-to-r from-red-500 to-rose-600 text-white p-6">
            <div class="flex items-center gap-3">
              <div class="p-3 bg-white/20 rounded-full">
                <NuxtIcon name="heroicons:exclamation-triangle" class="w-8 h-8" />
              </div>
              <div>
                <h3 class="text-xl font-bold">Delete Floor Permanently?</h3>
                <p class="text-sm opacity-90 mt-1">
                  {{ floorName }}
                </p>
              </div>
            </div>
          </div>

          <div class="p-6 space-y-4">
            <p class="text-gray-700 leading-relaxed">
              This will <strong>permanently delete</strong> the floor and
              <strong>all booths, walls, and drawings</strong> inside it.
            </p>
            <p class="text-sm text-red-600 font-medium">
              This action <strong>cannot be undone</strong>.
            </p>
          </div>

          <div class="flex border-t border-gray-200">
            <button
              @click="showDeleteDialog = false"
              class="flex-1 px-6 py-4 text-gray-700 hover:bg-gray-100 font-medium transition-colors"
            >
              Cancel
            </button>
            <button
              @click="confirmDeleteFloor"
              :disabled="isDeleting"
              class="flex-1 px-6 py-4 bg-red-600 text-white hover:bg-red-700 font-medium transition-colors flex items-center justify-center gap-2"
            >
              <svg
                v-if="isDeleting"
                class="animate-spin h-4 w-4"
                viewBox="0 0 24 24"
              >
                <circle
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="currentColor"
                  stroke-width="4"
                  fill="none"
                  opacity="0.3"
                />
                <path fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
              </svg>
              {{ isDeleting ? "Deleting..." : "Yes, Delete Forever" }}
            </button>
          </div>
        </div>
      </div>
    </teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useUiStore } from "@floorplan/stores/uiStore";
import { useFloorsApi } from "@floorplan/composables/api/useFloorsApi"; // Import API

const canvasStore = useCanvasStore();
const uiStore = useUiStore();
const { deleteFloor } = useFloorsApi(); // Use API

// Reactive properties
const floorName = ref("");
// ... (rest of refs)
const showDeleteDialog = ref(false);
const isDeleting = ref(false);

// ... (rest of computed and methods)

const confirmDeleteFloor = async () => {
    if (isDeleting.value || !selectedFloor.value) return;
    isDeleting.value = true;
    
    try {
        const success = await deleteFloor(selectedFloor.value.id);
        if (success) {
            showDeleteDialog.value = false;
            canvasStore.floors = canvasStore.floors.filter(
                (f) => f.id !== selectedFloor.value!.id
            );
            if (canvasStore.floors.length > 0) {
                canvasStore.switchFloor(canvasStore.floors[0].id);
            } else {
                canvasStore.createFloor("New Floor", { length: 1680, width: 800 });
            }
        }
    } finally {
        setTimeout(() => (isDeleting.value = false), 1000);
    }
};

const displayDimensions = ref({ width: 0, height: 0 });

const wallStrokeColor = ref("#4B5563");
const wallStrokeWidth = ref(8);
const wallOpacity = ref(1);

const wallZIndex = ref(0);
const isWallVisible = ref(true);
const isWallLocked = ref(false);

const currentUnit = computed(() => {
    switch(uiStore.measurementUnit) {
        case 'feet': return 'ft';
        case 'inches': return 'in';
        case 'meter': return 'm';
        case 'centimeter': return 'cm';
        default: return 'cm';
    }
});

// Computed properties
const selectedFloor = computed(() => {
  if (!canvasStore.currentFloorId) return null;
  return canvasStore.floors.find((f) => f.id === canvasStore.currentFloorId);
});

const actualDimensions = computed(() => {
  if (selectedFloor.value) {
    return {
      width: selectedFloor.value.dimensions.length,
      height: selectedFloor.value.dimensions.width,
    };
  }
  return { width: 0, height: 0 };
});

const actualArea = computed(() => {
  const dims = actualDimensions.value;
  return dims.width * dims.height;
});

const formattedDimensions = computed(() => {
  const dims = actualDimensions.value;
  const widthConverted = uiStore.convertToCurrentUnit(dims.width);
  const heightConverted = uiStore.convertToCurrentUnit(dims.height);

  return {
    width: `${widthConverted.value.toFixed(1)} ${widthConverted.unit}`,
    height: `${heightConverted.value.toFixed(1)} ${heightConverted.unit}`,
  };
});

const formattedArea = computed(() => {
  const area = actualArea.value;

  // Convert area (square units)
  switch (uiStore.measurementUnit) {
    case "feet": {
      const sqFt = area / (30.48 * 30.48);
      return { value: sqFt.toFixed(2), unit: "ft²" };
    }
    case "inches": {
      const sqIn = area / (2.54 * 2.54);
      return { value: sqIn.toFixed(2), unit: "in²" };
    }
    case "meter": {
      const sqM = area / (100 * 100);
      return { value: sqM.toFixed(2), unit: "m²" };
    }
    case "centimeter":
    default: {
      return { value: area.toFixed(2), unit: "cm²" };
    }
  }
});

const selectedWallObject = computed(() => {
  if (!canvasStore.currentFloorId) return null;
  const floorId = canvasStore.currentFloorId;
  
  let wallObject = canvasStore.objects.find(
    (obj) => obj.type === "wall" && obj.floorId === floorId
  );
  if (!wallObject) {
    wallObject = canvasStore.objects.find(
      (obj) => obj.type === "wall" && obj.id.includes(floorId)
    );
  }
  if (!wallObject) {
    wallObject = canvasStore.objects.find((obj) => obj.type === "wall" && obj.id.includes("Floor-"));
  }
  return wallObject;
});

// Sync properties
const syncProperties = () => {
  if (selectedFloor.value) {
    floorName.value = selectedFloor.value.name;

    // Convert stored dims (cm) to current unit for display input
    const widthConverted = uiStore.convertToCurrentUnit(selectedFloor.value.dimensions.length);
    const heightConverted = uiStore.convertToCurrentUnit(selectedFloor.value.dimensions.width);
    
    displayDimensions.value = {
        width: parseFloat(widthConverted.value.toFixed(2)),
        height: parseFloat(heightConverted.value.toFixed(2))
    };

    if (selectedWallObject.value) {
      const wall = selectedWallObject.value;
      wallStrokeColor.value = wall.strokeColor || wall.color || "#4B5563";
      wallStrokeWidth.value = wall.strokeWidth || 8;
      wallOpacity.value = wall.opacity ?? 1;
      
      wallZIndex.value = wall.zIndex || 0;
      isWallVisible.value = wall.isVisible !== false;
      isWallLocked.value = wall.isLocked || false;
    }
  }
};

// Handlers
const updateFloorName = () => {
    if (selectedFloor.value && floorName.value.trim()) {
        const floor = canvasStore.floors.find(f => f.id === selectedFloor.value!.id);
        if (floor) {
            floor.name = floorName.value.trim();
            floor.updated_at = new Date().toISOString();
        }
    }
};

const handleWidthInput = () => {
    updateFloorDims();
};

const handleHeightInput = () => {
    updateFloorDims();
};

const updateFloorDims = () => {
    if (!selectedFloor.value) return;
    
    // Convert display values (in current unit) back to cm
    const rawWidth = displayDimensions.value.width;
    const rawHeight = displayDimensions.value.height;
    
    // Simple conversion back to cm based on unit
    let widthInCm = rawWidth;
    let heightInCm = rawHeight;
    
    // Helper to inverse convert
    if (uiStore.measurementUnit === 'feet') {
        widthInCm = rawWidth * 30.48;
        heightInCm = rawHeight * 30.48;
    } else if (uiStore.measurementUnit === 'inches') {
        widthInCm = rawWidth * 2.54;
        heightInCm = rawHeight * 2.54;
    } else if (uiStore.measurementUnit === 'meter') {
        widthInCm = rawWidth * 100;
        heightInCm = rawHeight * 100;
    }
    
    canvasStore.updateFloorDimensions(
        selectedFloor.value.id,
        { length: widthInCm, width: heightInCm },
        false
    );
};

const updateWallAppearance = () => {
  if (selectedFloor.value) {
    const wallUpdates = {
      strokeColor: wallStrokeColor.value,
      strokeWidth: wallStrokeWidth.value,
      opacity: wallOpacity.value,
      color: wallStrokeColor.value,
    };
    canvasStore.updateFloorAppearance(selectedFloor.value.id, wallUpdates, false);
  }
};

// Layer methods
const updateZIndex = (delta: number) => {
    if (selectedWallObject.value) {
         const newIndex = (selectedWallObject.value.zIndex || 0) + delta;
         wallZIndex.value = newIndex;
         canvasStore.updateFloorAppearance(selectedFloor.value!.id, { zIndex: newIndex }, false);
    }
};

const toggleVisibility = () => {
    if (selectedFloor.value) {
        isWallVisible.value = !isWallVisible.value;
        canvasStore.updateFloorAppearance(selectedFloor.value.id, { isVisible: isWallVisible.value }, false);
    }
};

const toggleLock = () => {
    if (selectedFloor.value) {
         isWallLocked.value = !isWallLocked.value;
         canvasStore.updateFloorAppearance(selectedFloor.value.id, { isLocked: isWallLocked.value }, false);
    }
};


// Formatting
const formatDate = (dateString: string) => new Date(dateString).toLocaleDateString();

// Watchers
watch(() => selectedFloor.value, syncProperties, { immediate: true });
watch(() => selectedFloor.value?.dimensions, syncProperties, { deep: true }); // Watch specifically for dimension changes
watch(() => selectedWallObject.value, syncProperties, { deep: true });
watch(() => uiStore.measurementUnit, syncProperties); // Re-sync (convert) when unit changes

</script>

<style scoped>
input[type="color"]::-webkit-color-swatch {
  border: 1px solid #d1d5db;
  border-radius: 4px;
}

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
