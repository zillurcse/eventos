<!-- components/RightSidebar.vue - WITH CONDITIONAL CONTROL STATES -->
<template>
  <div>
    <div
      class="fixed right-0 top-11 h-[calc(100%-2.5rem)] z-10 mb-5"
      :class="isCollapsed ? 'pointer-events-none' : ''"
    >
      <!-- Sidebar Content -->
      <div
        class="h-full flex flex-col bg-white border-l border-gray-200 transition-all duration-300 ease-in-out pb-10"
        :class="[
          'w-80',
          isCollapsed
            ? 'translate-x-full opacity-0'
            : 'translate-x-0 opacity-100',
        ]"
      >
        <!-- Tab Header -->
        <div class="flex border-b border-gray-200">
          <button
            v-for="tab in availableTabs"
            :key="tab"
            @click="currentTab = tab"
            :class="{
              'bg-gray-200 text-gray-800': currentTab === tab,
              'text-gray-600': currentTab !== tab,
            }"
            class="py-2 px-4 text-sm font-medium transition-colors hover:bg-gray-100 whitespace-nowrap flex-1"
          >
            {{ tab }}
          </button>
        </div>

        <!-- Tab Content -->
        <div class="flex-1 p-4 overflow-auto">
          <!-- Properties Tab -->
          <div v-if="currentTab === 'Properties'" class="space-y-6">
            <!-- Common Element Controls -->
            <div
              v-if="hasSelection && !isFloorWallSelected"
              class="space-y-4 pb-4 border-b border-gray-200"
            >
              <!-- Alignment Controls -->
              <div class="space-y-3 border-b border-gray-200 pb-4">
                <div class="flex flex-row items-center justify-center gap-5">
                  <NuxtIcon
                    name="solar:align-left-bold-duotone"
                    class="text-xl border border-gray-200 rounded p-1 transition-all"
                    :class="
                      hasSelection
                        ? 'bg-gray-500 text-white hover:bg-blue-500 cursor-pointer'
                        : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                    "
                    title="Align Left"
                    @click="hasSelection && align('left')"
                  />
                  <NuxtIcon
                    name="solar:align-vertical-center-bold"
                    class="text-xl border border-gray-200 rounded p-1 transition-all"
                    :class="
                      hasSelection
                        ? 'bg-gray-500 text-white hover:bg-blue-500 cursor-pointer'
                        : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                    "
                    title="Align Center"
                    @click="hasSelection && align('center')"
                  />
                  <NuxtIcon
                    name="solar:align-right-bold-duotone"
                    class="text-xl border border-gray-200 rounded p-1 transition-all"
                    :class="
                      hasSelection
                        ? 'bg-gray-500 text-white hover:bg-blue-500 cursor-pointer'
                        : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                    "
                    title="Align Right"
                    @click="hasSelection && align('right')"
                  />
                  <NuxtIcon
                    name="solar:align-top-bold-duotone"
                    class="text-xl border border-gray-200 rounded p-1 transition-all"
                    :class="
                      hasSelection
                        ? 'bg-gray-500 text-white hover:bg-blue-500 cursor-pointer'
                        : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                    "
                    title="Align Top"
                    @click="hasSelection && align('top')"
                  />
                  <NuxtIcon
                    name="bi:align-middle"
                    class="text-xl border border-gray-200 rounded p-1 transition-all"
                    :class="
                      hasSelection
                        ? 'bg-gray-500 text-white hover:bg-blue-500 cursor-pointer'
                        : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                    "
                    title="Align Middle"
                    @click="hasSelection && align('middle')"
                  />
                  <NuxtIcon
                    name="solar:align-bottom-bold-duotone"
                    class="text-xl border border-gray-200 rounded p-1 transition-all"
                    :class="
                      hasSelection
                        ? 'bg-gray-500 text-white hover:bg-blue-500 cursor-pointer'
                        : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                    "
                    title="Align Bottom"
                    @click="hasSelection && align('bottom')"
                  />
                </div>
              </div>

              <!-- Transformation Controls -->
              <div class="space-y-3">
                <div class="flex flex-row items-center justify-center gap-5">
                  <!-- <NuxtIcon
                    name="fluent:flip-vertical-24-filled"
                    class="text-xl border border-gray-200 rounded p-1 transition-all"
                    :class="
                      hasSelection
                        ? 'bg-gray-500 text-white hover:bg-blue-500 cursor-pointer'
                        : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                    "
                    title="Flip Vertical"
                    @click="hasSelection && flipVertical()"
                  />
                  <NuxtIcon
                    name="fluent:flip-horizontal-24-filled"
                    class="text-xl border border-gray-200 rounded p-1 transition-all"
                    :class="
                      hasSelection
                        ? 'bg-gray-500 text-white hover:bg-blue-500 cursor-pointer'
                        : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                    "
                    title="Flip Horizontal"
                    @click="hasSelection && flipHorizontal()"
                  /> -->

                  <NuxtIcon
                    name="mage:copy"
                    class="text-xl border border-gray-200 rounded p-1 transition-all"
                    :class="
                      hasSelection
                        ? 'bg-gray-500 text-white hover:bg-blue-500 cursor-pointer'
                        : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                    "
                    title="Copy"
                    @click="hasSelection && copyElement()"
                  />
                  <NuxtIcon
                    name="mingcute:paste-line"
                    class="text-xl border border-gray-200 rounded p-1 transition-all"
                    :class="
                      clipboard
                        ? 'bg-gray-500 text-white hover:bg-blue-500 cursor-pointer'
                        : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                    "
                    title="Paste"
                    @click="clipboard && pasteElement()"
                  />
                  <NuxtIcon
                    name="bx:duplicate"
                    class="text-xl border border-gray-200 rounded p-1 transition-all"
                    :class="
                      hasSelection
                        ? 'bg-gray-500 text-white hover:bg-blue-500 cursor-pointer'
                        : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                    "
                    title="Duplicate"
                    @click="hasSelection && duplicateElement()"
                  />
                  <button
                    @click="deleteElement"
                    :disabled="!hasSelection"
                    class="p-1 flex items-center rounded transition-all"
                    :class="
                      hasSelection
                        ? 'bg-red-500 hover:bg-red-600 cursor-pointer'
                        : 'bg-gray-200 cursor-not-allowed'
                    "
                    title="Delete selected"
                  >
                    <NuxtIcon
                      name="heroicons:trash"
                      :class="hasSelection ? 'text-white' : 'text-gray-400'"
                    />
                  </button>
                </div>
              </div>
            </div>

            <!-- ✅ NEW: Show message when nothing is selected -->
            <div
              v-if="!hasSelection"
              class="flex flex-col items-center justify-center py-12 text-center"
            >
              <NuxtIcon
                name="heroicons:cursor-arrow-rays"
                class="w-16 h-16 text-gray-300 mb-4"
              />
              <p class="text-sm text-gray-500 font-medium">
                No object selected
              </p>
              <p class="text-xs text-gray-400 mt-2 max-w-xs">
                Select an object on the canvas to view and edit its properties
              </p>
            </div>

            <!-- Type-Specific Properties -->
            <PropertiesPanel v-if="hasSelection" />
          </div>

          <!-- Booths Tab -->
          <div v-if="currentTab === 'Booths'">
            <BoothTab />
          </div>

          <!-- Layers Tab -->
          <div v-if="currentTab === 'Layers'" class="h-full">
            <LayersPanel />
          </div>
        </div>
      </div>

      <!-- Collapse Toggle Button -->
      <button
        @click="toggleSidebar"
        class="absolute top-4 z-50 w-8 h-8 bg-white border border-gray-200 rounded-l-md flex items-center justify-center hover:bg-gray-50 transition-all duration-300 ease-in-out shadow-sm pointer-events-auto"
        :class="isCollapsed ? 'right-0' : 'right-80'"
      >
        <NuxtIcon
          :name="
            isCollapsed ? 'heroicons:chevron-left' : 'heroicons:chevron-right'
          "
          class="w-4 h-4 text-gray-600 transition-transform duration-300"
        />
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useObjectAlignment } from "@floorplan/composables/useObjectAlignment";
import { useObjectFlip } from "@floorplan/composables/useObjectFlip";

const canvasStore = useCanvasStore();
const { alignObjects } = useObjectAlignment();
const { flipHorizontal: flipHorizontalFn, flipVertical: flipVerticalFn } =
  useObjectFlip();

const tabs = ["Booths", "Properties", "Layers"];
const currentTab = ref("Properties");
const clipboard = ref<any>(null);
const isCollapsed = ref(false);

const toggleSidebar = () => {
  isCollapsed.value = !isCollapsed.value;
};

// ✅ ENHANCED: More robust selection detection
const hasSelection = computed(() => {
  const hasCanvasObjects = canvasStore.selectedObjects.length > 0;
  const hasDomElements = canvasStore.selectedElementId !== null;

  return hasCanvasObjects || hasDomElements;
});

const isFloorWallSelected = computed(() => {
  return canvasStore.selectedObjects.some(
    (obj) => obj.type === "wall" && obj.id.includes("Floor-Wall")
  );
});

const availableTabs = computed(() => {
  return ["Booths", "Properties", "Layers"];
});

// Watch for floor selection to auto-switch to Properties tab
watch(isFloorWallSelected, (newVal) => {
  if (newVal) {
    currentTab.value = "Properties";
  }
});

const selectedElement = computed(() => {
  if (canvasStore.selectedElementId) {
    return canvasStore.domElements.find(
      (e) => e.id === canvasStore.selectedElementId
    );
  }
  return null;
});

const selectedObject = computed(() => {
  return canvasStore.selectedObjects[0];
});

// Alignment function
const align = (
  direction: "left" | "right" | "top" | "bottom" | "center" | "middle"
) => {
  if (!hasSelection.value) {
    console.warn("⚠️ Cannot align: No objects selected");
    return;
  }
  alignObjects(direction);
};

// Flip functions
const flipHorizontal = () => {
  if (!hasSelection.value) {
    console.warn("⚠️ Cannot flip: No objects selected");
    return;
  }
  flipHorizontalFn();
};

const flipVertical = () => {
  if (!hasSelection.value) {
    console.warn("⚠️ Cannot flip: No objects selected");
    return;
  }
  flipVerticalFn();
};

const copyElement = () => {
  if (!hasSelection.value) {
    console.warn("⚠️ Cannot copy: No objects selected");
    return;
  }

  if (canvasStore.selectedElementId) {
    const element = canvasStore.domElements.find(
      (e) => e.id === canvasStore.selectedElementId
    );
    clipboard.value = {
      type: "element",
      data: JSON.parse(JSON.stringify(element)),
    };
    console.log("📋 Copied DOM element to clipboard");
  } else if (canvasStore.selectedObjects.length > 0) {
    clipboard.value = {
      type: "objects",
      data: canvasStore.selectedObjects.map((obj) =>
        JSON.parse(JSON.stringify(obj))
      ),
    };
    console.log(
      `📋 Copied ${canvasStore.selectedObjects.length} objects to clipboard`
    );
  }
};

const pasteElement = () => {
  if (!clipboard.value) {
    console.warn("⚠️ Cannot paste: Clipboard is empty");
    return;
  }

  if (clipboard.value.type === "element") {
    const newElement = {
      ...clipboard.value.data,
      id: Date.now().toString(),
      position: {
        x: clipboard.value.data.position.x + 20,
        y: clipboard.value.data.position.y + 20,
      },
      groupId: `group-${Date.now()}`,
    };
    canvasStore.domElements.push(newElement);

    canvasStore.objects.forEach((obj) => (obj.isSelected = false));
    canvasStore.selectedObjects = [];
    canvasStore.selectedElementId = newElement.id;
    console.log("✅ Pasted DOM element");
  } else if (clipboard.value.type === "objects") {
    const newObjects = clipboard.value.data.map((obj: any) => ({
      ...obj,
      id: `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
      points: obj.points.map((p: any) => ({ x: p.x + 20, y: p.y + 20 })),
      isSelected: false,
    }));

    canvasStore.objects.forEach((obj) => (obj.isSelected = false));
    canvasStore.selectedObjects = [];
    canvasStore.selectedElementId = null;

    newObjects.forEach((obj: any) => canvasStore.addObject(obj));

    newObjects.forEach((obj: any) => {
      const addedObj = canvasStore.objects.find((o) => o.id === obj.id);
      if (addedObj) {
        addedObj.isSelected = true;
        canvasStore.selectedObjects.push(addedObj);
      }
    });

    console.log(`✅ Pasted ${newObjects.length} objects and selected them`);
  }
};

const duplicateElement = () => {
  if (!hasSelection.value) {
    console.warn("⚠️ Cannot duplicate: No objects selected");
    return;
  }
  copyElement();
  setTimeout(pasteElement, 10);
};

const deleteElement = () => {
  if (!hasSelection.value) {
    console.warn("⚠️ Cannot delete: No objects selected");
    return;
  }

  canvasStore.deleteElement();

  if (!hasSelection.value) {
    currentTab.value = "Properties";
  }
};

const handleKeyDown = (event: KeyboardEvent) => {
  // Prevent actions if typing in an input field
  const target = event.target as HTMLElement;
  const isTyping =
    target.tagName === "INPUT" ||
    target.tagName === "TEXTAREA" ||
    target.isContentEditable;

  if (isTyping) return;

  if (event.key === "Delete" && hasSelection.value) {
    event.preventDefault();
    deleteElement();
  }

  if (event.ctrlKey && event.key === "c" && hasSelection.value) {
    event.preventDefault();
    copyElement();
  }
  if (event.ctrlKey && event.key === "v" && clipboard.value) {
    event.preventDefault();
    pasteElement();
  }
  if (event.ctrlKey && event.key === "d" && hasSelection.value) {
    event.preventDefault();
    duplicateElement();
  }
};

onMounted(() => {
  document.addEventListener("keydown", handleKeyDown);
});

onUnmounted(() => {
  document.removeEventListener("keydown", handleKeyDown);
});
</script>

<style scoped>
/* Smooth transitions for disabled states */
.transition-all {
  transition: all 0.2s ease-in-out;
}

/* Ensure pointer events are properly handled */
.cursor-not-allowed {
  pointer-events: none;
}

.cursor-pointer {
  cursor: pointer;
}
</style>
