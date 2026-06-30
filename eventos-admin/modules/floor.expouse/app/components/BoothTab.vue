<!-- components/BoothTab.vue -->
<template>
  <div class="p-2 overflow-y-auto h-full mb-10" ref="boothListRef">
    <div class="booth-tab space-y-6">
      <!-- Booth State Colors Section -->
      <div class="space-y-4">
        <h4 class="text-sm font-semibold text-gray-800">Booth State Colors</h4>
        <div class="space-y-3">
          <div
            v-for="status in boothStatuses"
            :key="status"
            class="flex items-center justify-between p-3 border rounded-lg"
          >
            <span class="text-sm font-medium text-gray-700">
              {{ BOOTH_STATUS_LABELS[status] }}
            </span>
            <div class="flex items-center gap-3">
              <input
                type="color"
                :value="customColors[status] || BOOTH_STATUS_COLORS[status]"
                @input="handleUpdateStatusColor(status, $event.target.value)"
                class="w-8 h-8 rounded border border-gray-300 cursor-pointer"
                title="Click to change color"
              />
              <span class="text-sm font-mono text-gray-600">
                {{
                  (
                    customColors[status] || BOOTH_STATUS_COLORS[status]
                  ).toUpperCase()
                }}
              </span>
              <button
                v-if="customColors[status]"
                @click="handleResetStatusColor(status)"
                class="p-1 text-gray-400 hover:text-gray-600 transition-colors"
                title="Reset to default color"
              >
                <NuxtIcon name="heroicons:arrow-path" class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Booth List Section -->
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <h4 class="text-sm font-semibold text-gray-800">
            Booths ({{ filteredBooths.length
            }}{{ hasSearch ? ` of ${booths.length}` : "" }})
          </h4>
          <button
            @click="showCreateModal = true"
            class="px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700 transition-colors"
          >
            Add Booth
          </button>
        </div>

        <!-- Search Input -->
        <div class="relative">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search booths by number or company..."
            class="w-full px-3 py-2 pl-9 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          <NuxtIcon
            name="heroicons:magnifying-glass"
            class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2"
          />
          <button
            v-if="searchQuery"
            @click="searchQuery = ''"
            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
          >
            <NuxtIcon name="heroicons:x-mark" class="w-4 h-4" />
          </button>
        </div>

        <!-- Compact Booths List -->
        <div v-if="filteredBooths.length > 0" class="space-y-2">
          <BoothItem
            v-for="booth in filteredBooths"
            :key="booth.id"
            :booth="booth"
            :display-option="displayOption"
            :is-selected="isBoothSelected(booth.id)"
            @update:status="updateBoothStatus"
            @edit="editBooth"
            @delete="deleteBooth"
            @click="selectBooth(booth)"
          />
        </div>

        <!-- Empty State -->
        <div
          v-else
          class="text-center py-8 border-2 border-dashed border-gray-300 rounded-lg"
        >
          <NuxtIcon
            v-if="hasSearch"
            name="heroicons:magnifying-glass"
            class="w-12 h-12 text-gray-400 mx-auto mb-3"
          />
          <NuxtIcon
            v-else
            name="heroicons:rectangle-stack"
            class="w-12 h-12 text-gray-400 mx-auto mb-3"
          />
          <p class="text-gray-500 text-sm">
            {{
              hasSearch
                ? "No booths found matching your search"
                : "No booths created yet"
            }}
          </p>
          <button
            v-if="!hasSearch"
            @click="showCreateModal = true"
            class="mt-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700 transition-colors"
          >
            Create First Booth
          </button>
          <button
            v-else
            @click="searchQuery = ''"
            class="mt-2 px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded hover:bg-gray-700 transition-colors"
          >
            Clear Search
          </button>
        </div>
      </div>

      <!-- Create/Edit Booth Modal -->
      <teleport to="body">
        <BoothModal
          v-if="showCreateModal"
          :show="showCreateModal"
          :editing-booth="editingBooth"
          @close="handleModalClose"
          @save="handleBoothSave"
        />
      </teleport>
    </div>
  </div>
</template>

<script setup lang="ts">
import {
  BOOTH_STATUS_COLORS,
  BOOTH_STATUS_LABELS,
  type BoothStatus,
  type BoothDisplayOption,
} from "@floorplan/constants/boothConstants";

import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useBoothColors } from "@floorplan/composables/useBoothColors";

const canvasStore = useCanvasStore();
const { getStatusColor, updateStatusColor, resetStatusColor, getCustomColors } =
  useBoothColors();

// Reactive state
const customColors = ref<Record<BoothStatus, string>>({});
const displayOption = ref<BoothDisplayOption>("companyName");
const showCreateModal = ref(false);
const editingBooth = ref<any>(null);
const boothListRef = ref<HTMLElement>();
const searchQuery = ref("");

// Watch for canvas selection changes
watch(
  () => canvasStore.selectedObjects,
  (newSelection) => {
    // If a booth is selected on canvas, ensure it's highlighted in the list
    const selectedBooth = newSelection.find((obj) => obj.type === "booth");
    if (selectedBooth) {
      // The BoothItem component will handle the scrolling via its own watcher
      console.log("Booth selected on canvas:", selectedBooth.id);
    }
  },
  { deep: true }
);

// Watch for color changes and update all booths
onMounted(() => {
  customColors.value = getCustomColors();

  if (process.client) {
    window.addEventListener("booth-colors-updated", () => {
      customColors.value = getCustomColors();
      // Update all booths to reflect color changes
      updateAllBoothsDisplay();
    });
  }
});

// Watch display option changes and update booth rendering
watch(displayOption, (newOption) => {
  // Update all booths to reflect display option changes
  updateAllBoothsDisplay();
});

// Computed properties
const booths = computed(() => {
  return canvasStore.objects
    .filter((obj) => obj.type === "booth")
    .map((booth) => ({
      id: booth.id,
      boothNumber: booth.boothNumber || "Unknown",
      status: (booth.status || "AVAILABLE") as BoothStatus,
      booth_name: booth.booth_name,
      // ✅ Updated: Use booth.size if available, otherwise use length/breadth
      length: booth.size?.width || booth.length || 10,
      breadth: booth.size?.height || booth.breadth || 10,
      isSelected: booth.isSelected || false,
    }));
});

const filteredBooths = computed(() => {
  if (!searchQuery.value.trim()) {
    return booths.value;
  }

  const query = searchQuery.value.toLowerCase().trim();
  return booths.value.filter(
    (booth) =>
      booth.boothNumber.toLowerCase().includes(query) ||
      (booth.booth_name && booth.booth_name.toLowerCase().includes(query)) // CHANGED: companyName → booth_name
  );
});

const hasSearch = computed(() => {
  return searchQuery.value.trim().length > 0;
});

const boothStatuses = computed(() => {
  return Object.keys(BOOTH_STATUS_LABELS) as BoothStatus[];
});

// Methods
const isBoothSelected = (boothId: string): boolean => {
  return canvasStore.selectedObjects.some(
    (obj) => obj.id === boothId && obj.type === "booth"
  );
};

const selectBooth = (booth: any) => {
  // Find the actual canvas object
  const canvasBooth = canvasStore.objects.find(
    (obj) => obj.id === booth.id && obj.type === "booth"
  );

  if (canvasBooth) {
    // Clear current selection and select only this booth
    canvasStore.selectedObjects = [canvasBooth];

    // Ensure the booth is also selected in the canvas store
    canvasStore.objects.forEach((obj) => {
      if (obj.type === "booth") {
        obj.isSelected = obj.id === booth.id;
      }
    });
  }
};

const updateAllBoothsDisplay = () => {
  // This triggers re-rendering of all booths in Whiteboard.vue
  canvasStore.objects
    .filter((obj) => obj.type === "booth")
    .forEach((booth) => {
      canvasStore.updateObject(booth.id, {
        lastDisplayUpdate: Date.now(),
        displayOption: displayOption.value,
      });
    });
};

const updateBoothStatus = async ({
  id,
  status,
}: {
  id: string;
  status: BoothStatus;
}) => {
  try {
    canvasStore.updateObject(id, { status });
  } catch (error) {
    console.error("Failed to update booth status:", error);
  }
};

const editBooth = (booth: any) => {
  editingBooth.value = booth;
  showCreateModal.value = true;
};

const deleteBooth = async (id: string) => {
  if (confirm("Are you sure you want to delete this booth?")) {
    try {
      canvasStore.selectedObjects = canvasStore.selectedObjects.filter(
        (obj) => obj.id !== id
      );

      const index = canvasStore.objects.findIndex((obj) => obj.id === id);
      if (index !== -1) {
        canvasStore.objects.splice(index, 1);
        await canvasStore.save();
      }
    } catch (error) {
      console.error("Failed to delete booth:", error);
    }
  }
};

const handleModalClose = () => {
  showCreateModal.value = false;
  editingBooth.value = null;
};

// In BoothTab.vue - update handleBoothSave function
const handleBoothSave = (boothData: any) => {
  if (editingBooth.value) {
    const actualBooth = canvasStore.getBoothById(editingBooth.value.id);

    if (!actualBooth) {
      console.error("Booth not found for editing");
      handleModalClose();
      return;
    }

    // Validate booth number uniqueness when editing
    const boothNumber = boothData.boothNumber.trim();
    const existingBooth = canvasStore.objects.find(
      (obj) =>
        obj.type === "booth" &&
        obj.id !== editingBooth.value.id && // Exclude current booth
        obj.boothNumber?.toLowerCase() === boothNumber.toLowerCase()
    );

    if (existingBooth) {
      alert(
        `Booth number ${boothNumber} already exists. Please choose a different number.`
      );
      return;
    }

    const updates: Partial<CanvasObject> = {
      boothNumber: boothData.boothNumber,
      length: boothData.length,
      breadth: boothData.breadth,
      status: actualBooth.status || "AVAILABLE",
      booth_name: boothData.booth_name || "", // FIX: Use the new booth_name from form
      displayOption: displayOption.value,
    };

    if (actualBooth.points && actualBooth.points.length >= 2) {
      updates.points = [
        { x: actualBooth.points[0].x, y: actualBooth.points[0].y },
        {
          x: actualBooth.points[0].x + boothData.length,
          y: actualBooth.points[0].y + boothData.breadth,
        },
      ];
    }

    canvasStore.updateObject(editingBooth.value.id, updates);
  } else {
    // For new booths, validation is handled in the modal
    const boothWithName = {
      ...boothData,
      booth_name: boothData.booth_name || "", // Ensure booth_name is included
    };
    canvasStore.addBooth(boothWithName);
  }

  handleModalClose();
};

// Color customization methods
const handleUpdateStatusColor = (status: BoothStatus, color: string) => {
  updateStatusColor(status, color);
  customColors.value[status] = color;
  updateAllBoothsDisplay();
};

const handleResetStatusColor = (status: BoothStatus) => {
  resetStatusColor(status);
  delete customColors.value[status];
  updateAllBoothsDisplay();
};
</script>
