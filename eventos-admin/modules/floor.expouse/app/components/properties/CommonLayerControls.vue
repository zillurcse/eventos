<!-- components/CommonLayerControls.vue -->
<template>
  <div class="common-layer-controls space-y-4">
    <h4 class="text-sm font-semibold text-gray-800">Layer</h4>

    <!-- Stack Order -->
    <div class="space-y-2">
      <label class="block text-sm font-medium text-gray-700">Stack Order</label>
      <div class="flex gap-2">
         <button
          @click="canvasStore.bringToFront()"
          class="flex-1 p-2 border rounded hover:bg-gray-50 flex items-center justify-center"
          title="Bring to Front"
        >
          <NuxtIcon name="mdi:arrange-bring-to-front" class="w-5 h-5 text-gray-600" />
        </button>
        <button
          @click="canvasStore.bringForward()"
          class="flex-1 p-2 border rounded hover:bg-gray-50 flex items-center justify-center"
          title="Bring Forward"
        >
          <NuxtIcon name="mdi:arrange-bring-forward" class="w-5 h-5 text-gray-600" />
        </button>
        <button
          @click="canvasStore.sendBackward()"
          class="flex-1 p-2 border rounded hover:bg-gray-50 flex items-center justify-center"
          title="Send Backward"
        >
          <NuxtIcon name="mdi:arrange-send-backward" class="w-5 h-5 text-gray-600" />
        </button>
        <button
          @click="canvasStore.sendToBack()"
          class="flex-1 p-2 border rounded hover:bg-gray-50 flex items-center justify-center"
          title="Send to Back"
        >
          <NuxtIcon name="mdi:arrange-send-to-back" class="w-5 h-5 text-gray-600" />
        </button>
      </div>
    </div>

    <!-- Z-Index (Advanced) -->
    <div class="space-y-2">
      <label class="block text-xs font-medium text-gray-500">Z-Index (Value)</label>
      <input
        v-model.number="zIndex"
        type="number"
        @change="updateProperties"
        @blur="updateProperties"
        class="w-full border rounded px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
        placeholder="0"
      />
    </div>

    <!-- Visibility & Lock Toggles -->
    <div class="grid grid-cols-2 gap-3">
      <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-700"
          >Visibility</label
        >
        <button
          @click="toggleVisibility"
          :class="[
            'w-full px-3 py-2 border rounded text-sm font-medium transition-colors flex items-center justify-center gap-2',
            isVisible
              ? 'bg-green-50 text-green-700 border-green-200 hover:bg-green-100'
              : 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100',
          ]"
        >
          <NuxtIcon
            :name="isVisible ? 'heroicons:eye' : 'heroicons:eye-slash'"
            class="w-4 h-4"
          />
          {{ isVisible ? "Visible" : "Hidden" }}
        </button>
      </div>

      <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-700">Lock</label>
        <button
          @click="toggleLock"
          :class="[
            'w-full px-3 py-2 border rounded text-sm font-medium transition-colors flex items-center justify-center gap-2',
            isLocked
              ? 'bg-yellow-50 text-yellow-700 border-yellow-200 hover:bg-yellow-100'
              : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100',
          ]"
        >
          <NuxtIcon
            :name="isLocked ? 'heroicons:lock-closed' : 'heroicons:lock-open'"
            class="w-4 h-4"
          />
          {{ isLocked ? "Unlock" : "Lock" }}
        </button>
      </div>
    </div>

    <!-- 🆕 BOOTH CREATION DISTANCE INFO (Read-only display with unit conversion) -->
    <!-- <div
      v-if="showBoothDistanceInfo"
      class="booth-distance-info space-y-2 border-t pt-4"
    >
      <h4 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
        <NuxtIcon name="heroicons:arrows-pointing-out" class="w-4 h-4" />
        Booth Creation Distance
      </h4>
      <div class="bg-blue-50 rounded-lg p-3">
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-700">Default Distance:</span>
          <span class="text-sm font-semibold text-blue-700">
            {{ displayedBoothDistance }} {{ uiStore.measurementUnit }}
          </span>
        </div>
        <p class="text-xs text-gray-500 mt-2">
          This distance will be used when creating new booths via arrow icons.
        </p>
      </div>
    </div> -->

    <!-- Export Section (Only for Rectangle/Wall containers) -->
    <div
      v-if="showExportSection"
      class="export-section space-y-3 border-t pt-4"
    >
      <h4 class="text-sm font-semibold text-gray-800 flex items-center gap-2">
        <NuxtIcon name="heroicons:arrow-down-tray" class="w-4 h-4" />
        Export Container
      </h4>
      <p class="text-xs text-gray-600">
        Export this {{ containerType }} and all items inside it
      </p>

      <!-- Format Selection Dropdown -->
      <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-700">
          Select Export Format
        </label>
        <select
          v-model="selectedFormat"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
        >
          <option value="">-- Choose Format --</option>
          <option value="png">PNG Image</option>
          <option value="pdf">PDF Document</option>
        </select>
      </div>

      <!-- Download Button (appears after format selection) -->
      <div v-if="selectedFormat" class="space-y-2">
        <button
          @click="handleDownload"
          :disabled="isExporting"
          :class="[
            'w-full px-4 py-3 rounded-lg text-sm font-medium transition-all flex items-center justify-center gap-2',
            isExporting
              ? 'bg-gray-400 cursor-not-allowed'
              : selectedFormat === 'png'
              ? 'bg-blue-600 hover:bg-blue-700 text-white shadow-sm hover:shadow-md'
              : 'bg-red-600 hover:bg-red-700 text-white shadow-sm hover:shadow-md',
          ]"
        >
          <NuxtIcon
            v-if="!isExporting"
            :name="
              selectedFormat === 'png'
                ? 'heroicons:photo'
                : 'heroicons:document-arrow-down'
            "
            class="w-5 h-5"
          />
          <NuxtIcon
            v-else
            name="heroicons:arrow-path"
            class="w-5 h-5 animate-spin"
          />
          <span v-if="!isExporting">
            Download {{ selectedFormat.toUpperCase() }}
          </span>
          <span v-else> Exporting... </span>
        </button>

        <!-- Format Info -->
        <div class="bg-gray-50 rounded-lg p-3 text-xs text-gray-600">
          <div v-if="selectedFormat === 'png'" class="flex items-start gap-2">
            <NuxtIcon
              name="heroicons:information-circle"
              class="w-4 h-4 mt-0.5 text-blue-600 shrink-0"
            />
            <div>
              <span class="font-medium text-gray-800">PNG Format:</span>
              High-quality raster image, supports transparency, best for digital
              use.
            </div>
          </div>
          <div v-if="selectedFormat === 'pdf'" class="flex items-start gap-2">
            <NuxtIcon
              name="heroicons:information-circle"
              class="w-4 h-4 mt-0.5 text-red-600 shrink-0"
            />
            <div>
              <span class="font-medium text-gray-800">PDF Format:</span>
              Vector-friendly document, ideal for printing and sharing.
            </div>
          </div>
        </div>
      </div>

      <!-- Contained Items Info -->
      <div
        v-if="containedItemsInfo"
        class="flex items-center gap-2 text-xs text-gray-500 pt-2 border-t"
      >
        <NuxtIcon name="heroicons:cube" class="w-4 h-4" />
        <span>
          Contains:
          <strong class="text-gray-700">{{
            containedItemsInfo.objects
          }}</strong>
          objects,
          <strong class="text-gray-700">{{
            containedItemsInfo.elements
          }}</strong>
          elements
        </span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, computed } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useUiStore } from "@floorplan/stores/uiStore";
import { useCanvasExport } from "@floorplan/composables/useCanvasExport";
import { useBoothCreationDistance } from "@floorplan/composables/useBoothCreationDistance";

// Props
interface Props {
  zIndex?: number;
  isVisible?: boolean;
  isLocked?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  zIndex: 0,
  isVisible: true,
  isLocked: false,
});

// Emits
const emit = defineEmits<{
  update: [
    updates: { zIndex?: number; isVisible?: boolean; isLocked?: boolean }
  ];
}>();

// Store and composables
const canvasStore = useCanvasStore();
const uiStore = useUiStore();
const canvasExport = useCanvasExport();

// 🆕 USE BOOTH DISTANCE COMPOSABLE FOR UNIT CONVERSION
const { convertDistance } = useBoothCreationDistance();

// Reactive properties
const zIndex = ref(props.zIndex);
const isVisible = ref(props.isVisible);
const isLocked = ref(props.isLocked);
const isExporting = ref(false);
const selectedFormat = ref<"png" | "pdf" | "">("");

// 🆕 COMPUTED: Check if selected object is a booth
const showBoothDistanceInfo = computed(() => {
  const selectedObj = canvasStore.selectedObjects[0];
  return selectedObj?.type === "booth";
});

// 🆕 COMPUTED: Display booth distance in current unit
const displayedBoothDistance = computed(() => {
  const selectedObj = canvasStore.selectedObjects[0];
  if (!selectedObj || selectedObj.type !== "booth") return 0;

  const distanceInCm = selectedObj.boothCreationDistance || 100;
  const currentUnit = uiStore.measurementUnit || "cm";

  // Convert from centimeters to current unit
  const convertedDistance = convertDistance(distanceInCm, "cm", currentUnit);

  // Round to 2 decimal places
  return Math.round(convertedDistance * 100) / 100;
});

// Export section visibility
const showExportSection = computed(() => canvasExport.canExport());

const containerType = computed(() => {
  const container = canvasExport.getSelectedContainer();
  if (!container) return "";
  if (container.type === "rectangle") return "Rectangle";
  if (container.type === "frame") return "Frame";
  if (container.type === "section") return "Section";
  return "Wall";
});

const containedItemsInfo = computed(() => {
  if (!showExportSection.value) return null;
  const container = canvasExport.getSelectedContainer();
  if (!container) return null;

  const { objects, elements } = canvasExport.getContainedItems(container);
  return {
    objects: objects.length,
    elements: elements.length,
  };
});

// Toggle functions
const toggleVisibility = () => {
  isVisible.value = !isVisible.value;
  updateProperties();
};

const toggleLock = () => {
  isLocked.value = !isLocked.value;
  updateProperties();
};

// Update properties
const updateProperties = () => {
  const updates: any = {};

  if (zIndex.value !== props.zIndex) {
    updates.zIndex = zIndex.value;
  }

  if (isVisible.value !== props.isVisible) {
    updates.isVisible = isVisible.value;
  }

  if (isLocked.value !== props.isLocked) {
    updates.isLocked = isLocked.value;
  }

  if (Object.keys(updates).length > 0) {
    console.log("📤 CommonLayerControls emitting updates:", updates);
    emit("update", updates);
  }
};

// Unified download handler
const handleDownload = async () => {
  if (!selectedFormat.value) {
    console.error("No format selected");
    return;
  }

  const container = canvasExport.getSelectedContainer();
  if (!container) {
    console.error("No valid container selected");
    return;
  }

  isExporting.value = true;

  try {
    if (selectedFormat.value === "png") {
      console.log("📸 Starting PNG export...");
      await canvasExport.exportAsPNG(container);
      console.log("✅ PNG export completed successfully");
    } else if (selectedFormat.value === "pdf") {
      console.log("📄 Starting PDF export...");
      await canvasExport.exportAsPDF(container);
      console.log("✅ PDF export completed successfully");
    }
  } catch (error) {
    console.error(
      `❌ ${selectedFormat.value.toUpperCase()} export failed:`,
      error
    );
    alert(
      `Failed to export ${selectedFormat.value.toUpperCase()}. Please try again.`
    );
  } finally {
    isExporting.value = false;
  }
};

// Watch for prop changes
watch(
  () => props.zIndex,
  (newZIndex) => {
    zIndex.value = newZIndex;
  },
  { immediate: true }
);

watch(
  () => props.isVisible,
  (newVisibility) => {
    isVisible.value = newVisibility;
  },
  { immediate: true }
);

watch(
  () => props.isLocked,
  (newLocked) => {
    isLocked.value = newLocked;
  },
  { immediate: true }
);

// Reset format selection when container changes
watch(showExportSection, (isVisible) => {
  if (!isVisible) {
    selectedFormat.value = "";
  }
});
</script>
