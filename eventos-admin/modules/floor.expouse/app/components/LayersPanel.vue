<!-- components/LayersPanel.vue -->
<template>
  <div class="layers-panel">
    <!-- Header -->
    <div class="layers-header">
      <h3 class="text-sm font-semibold text-gray-800">Layers</h3>
      <div class="flex gap-1">
        <button
          @click="toggleAllVisibility"
          class="p-1 text-gray-600 hover:text-gray-800 transition-colors"
          title="Toggle All Visibility"
        >
          <NuxtIcon name="heroicons:eye" class="w-4 h-4" />
        </button>
        <button
          @click="toggleAllLocks"
          class="p-1 text-gray-600 hover:text-gray-800 transition-colors"
          title="Toggle All Locks"
        >
          <NuxtIcon name="heroicons:lock-closed" class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Search Bar -->
    <div class="search-bar mt-3">
      <div class="relative">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search layers..."
          class="w-full px-3 py-2 pl-9 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        />
        <NuxtIcon
          name="heroicons:magnifying-glass"
          class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2"
        />
        <button
          v-if="searchQuery"
          @click="searchQuery = ''"
          class="absolute right-2 top-1/2 transform -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600 transition-colors"
          title="Clear search"
        >
          <NuxtIcon name="heroicons:x-mark" class="w-4 h-4" />
        </button>
      </div>
      <div
        v-if="searchQuery && filteredLayers.length > 0"
        class="text-xs text-gray-500 mt-1"
      >
        Found {{ filteredLayers.length }} of {{ totalLayers }} layers
      </div>
      <div
        v-if="searchQuery && filteredLayers.length === 0"
        class="text-xs text-red-500 mt-1"
      >
        No layers match "{{ searchQuery }}"
      </div>
    </div>

    <!-- Layers List -->
    <div class="layers-list space-y-1 mt-3">
      <!-- Canvas Objects -->
      <div
        v-for="(layer, index) in filteredLayers"
        :key="layer.id"
        :class="[
          'layer-item group flex items-center gap-2 p-2 rounded border transition-all duration-200 cursor-pointer',
          layer.isSelected
            ? 'bg-blue-50 border-blue-200'
            : 'bg-white border-gray-200 hover:bg-gray-50',
          layer.isLocked ? 'opacity-75' : '',
          !layer.isVisible ? 'opacity-50' : '',
          dragOverLayer?.id === layer.id
            ? 'bg-blue-100 border-blue-300 border-2'
            : '',
        ]"
        @click="selectLayer(layer)"
        draggable="true"
        @dragstart="handleDragStart($event, layer)"
        @dragover="handleDragOver($event, layer)"
        @drop="handleDrop($event, layer, index)"
        @dragenter="handleDragEnter($event, layer)"
        @dragleave="handleDragLeave($event, layer)"
        @dragend="handleDragEnd"
      >
        <!-- Drag Handle -->
        <div
          class="drag-handle text-gray-400 hover:text-gray-600 cursor-grab"
          :class="layer.isLocked ? 'cursor-not-allowed opacity-50' : ''"
        >
          <NuxtIcon name="heroicons:bars-2" class="w-4 h-4" />
        </div>

        <!-- Layer Icon -->
        <div class="layer-icon shrink-0">
          <NuxtIcon :name="getLayerIcon(layer)" class="w-4 h-4 text-gray-600" />
        </div>

        <!-- Layer Name -->
        <div class="layer-name flex-1 min-w-0">
          <span
            class="text-sm text-gray-700 truncate block"
            v-html="highlightMatch(layer.name)"
          ></span>
          <div class="flex items-center gap-2 mt-1">
            <span
              v-if="layer.isLocked"
              class="text-xs text-yellow-600 flex items-center gap-1"
            >
              <NuxtIcon name="heroicons:lock-closed" class="w-3 h-3" />
              Locked
            </span>
            <span
              v-if="!layer.isVisible"
              class="text-xs text-red-600 flex items-center gap-1"
            >
              <NuxtIcon name="heroicons:eye-slash" class="w-3 h-3" />
              Hidden
            </span>
          </div>
        </div>

        <!-- Layer Actions -->
        <div
          class="layer-actions flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity"
        >
          <!-- Visibility Toggle -->
          <button
            @click.stop="toggleLayerVisibility(layer)"
            class="p-1 text-gray-500 hover:text-gray-700 transition-colors"
            :title="layer.isVisible ? 'Hide' : 'Show'"
            :class="layer.isVisible ? 'text-green-600' : 'text-red-600'"
          >
            <NuxtIcon
              :name="layer.isVisible ? 'heroicons:eye' : 'heroicons:eye-slash'"
              class="w-4 h-4"
            />
          </button>

          <!-- Lock Toggle -->
          <button
            @click.stop="toggleLayerLock(layer)"
            class="p-1 text-gray-500 hover:text-gray-700 transition-colors"
            :title="layer.isLocked ? 'Unlock' : 'Lock'"
            :class="layer.isLocked ? 'text-yellow-600' : 'text-gray-500'"
          >
            <NuxtIcon
              :name="
                layer.isLocked ? 'heroicons:lock-closed' : 'heroicons:lock-open'
              "
              class="w-4 h-4"
            />
          </button>

          <!-- Delete -->
          <button
            @click.stop="deleteLayer(layer)"
            class="p-1 text-red-500 hover:text-red-700 transition-colors"
            title="Delete"
            :disabled="layer.isLocked"
            :class="layer.isLocked ? 'opacity-50 cursor-not-allowed' : ''"
          >
            <NuxtIcon name="heroicons:trash" class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Empty State -->
      <div
        v-if="filteredLayers.length === 0 && !searchQuery"
        class="text-center py-8 text-gray-500"
      >
        <NuxtIcon
          name="heroicons:rectangle-stack"
          class="w-8 h-8 mx-auto mb-2 opacity-50"
        />
        <p class="text-sm">No layers yet</p>
        <p class="text-xs">Add elements to see them here</p>
      </div>
    </div>

    <!-- Layer Stats -->
    <div class="layer-stats mt-4 pt-3 border-t border-gray-200">
      <div class="flex justify-between text-xs text-gray-500">
        <span>Total: {{ totalLayers }}</span>
        <span>Visible: {{ visibleLayers }}</span>
        <span>Locked: {{ lockedLayers }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";

const canvasStore = useCanvasStore();

// Layer interface
interface Layer {
  id: string;
  type: "object" | "element" | "floor";
  name: string;
  zIndex: number;
  isSelected: boolean;
  isVisible: boolean;
  isLocked: boolean;
  originalObject?: any;
  originalElement?: any;
  objectIds?: string[]; // For floor type
}

// Reactive layers array
const layers = ref<Layer[]>([]);
const dragLayer = ref<Layer | null>(null);
const dragOverLayer = ref<Layer | null>(null);
const isDragging = ref(false);
const searchQuery = ref("");

// Computed properties
const sortedLayers = computed(() => {
  return [...layers.value].sort((a, b) => b.zIndex - a.zIndex);
});

const filteredLayers = computed(() => {
  if (!searchQuery.value.trim()) {
    return sortedLayers.value;
  }

  const query = searchQuery.value.toLowerCase().trim();
  return sortedLayers.value.filter((layer) =>
    layer.name.toLowerCase().includes(query)
  );
});

const totalLayers = computed(() => layers.value.length);
const visibleLayers = computed(
  () => layers.value.filter((layer) => layer.isVisible).length
);
const lockedLayers = computed(
  () => layers.value.filter((layer) => layer.isLocked).length
);

// Highlight matched text in search results
const highlightMatch = (text: string): string => {
  if (!searchQuery.value.trim()) {
    return text;
  }

  const query = searchQuery.value.trim();
  const regex = new RegExp(`(${query})`, "gi");
  return text.replace(
    regex,
    '<mark class="bg-yellow-200 px-1 rounded">$1</mark>'
  );
};

// Initialize layers from canvas store
const initializeLayers = () => {
  layers.value = [];

  const floorObjects: any[] = [];
  const otherObjects: any[] = [];

  // Separate floor objects (walls/doors part of the structure)
  canvasStore.objects.forEach((obj) => {
    const isFloorPart = (obj.type === 'wall' || obj.type === 'door-arc') && 
                       (obj.id && obj.id.includes('Floor-'));
    
    if (isFloorPart) {
      floorObjects.push(obj);
    } else {
      otherObjects.push(obj);
    }
  });

  // Add the combined "Main Wall" layer if floor objects exist
  if (floorObjects.length > 0) {
    const first = floorObjects[0];
    layers.value.push({
      id: 'main-floor-layer',
      type: 'floor',
      name: 'Main Wall',
      zIndex: first.zIndex || 0,
      isSelected: floorObjects.some(o => o.isSelected),
      isVisible: floorObjects.some(o => o.isVisible !== false),
      isLocked: floorObjects.some(o => o.isLocked),
      objectIds: floorObjects.map(o => o.id),
    });
  }

  // Add other canvas objects as layers
  otherObjects.forEach((obj) => {
    layers.value.push({
      id: obj.id,
      type: "object",
      name: getObjectName(obj),
      zIndex: obj.zIndex || 0,
      isSelected: obj.isSelected || false,
      isVisible: obj.isVisible !== false,
      isLocked: obj.isLocked || false,
      originalObject: obj,
    });
  });

  // Add DOM elements as layers
  canvasStore.domElements.forEach((element) => {
    layers.value.push({
      id: element.id,
      type: "element",
      name: getElementName(element),
      zIndex: element.zIndex || 10,
      isSelected: canvasStore.selectedElementId === element.id,
      isVisible: element.isVisible !== false,
      isLocked: element.isLocked || false,
      originalElement: element,
    });
  });
};

// Helper functions
const getObjectName = (obj: any): string => {
  if (obj.boothNumber) return `Booth ${obj.boothNumber}`;
  if (obj.type === "text" && obj.text)
    return `Text: ${obj.text.substring(0, 20)}${
      obj.text.length > 20 ? "..." : ""
    }`;
  if (obj.type === "image" && obj.src)
    return `Image: ${obj.src.split("/").pop()}`;
  return `${
    obj.type.charAt(0).toUpperCase() + obj.type.slice(1)
  } ${obj.id.slice(-4)}`;
};

const getElementName = (element: any): string => {
  if (element.type === "text" && element.content)
    return `Text: ${element.content.substring(0, 20)}${
      element.content.length > 20 ? "..." : ""
    }`;
  if (element.subtype) return element.subtype;
  return `${
    element.type.charAt(0).toUpperCase() + element.type.slice(1)
  } ${element.id.slice(-4)}`;
};

const getLayerName = (layer: Layer): string => {
  return layer.name;
};

const getLayerIcon = (layer: Layer): string => {
  if (layer.type === "floor") return "heroicons:home";
  if (layer.type === "element") {
    const element = layer.originalElement;
    if (element.type === "text") return "heroicons:document-text";
    if (element.type === "image") return "heroicons:photo";
    return "heroicons:rectangle-stack";
  } else {
    const obj = layer.originalObject;
    if (obj.type === "booth") return "heroicons:building-storefront";
    if (obj.type === "text") return "heroicons:document-text";
    if (obj.type === "image") return "heroicons:photo";
    if (obj.type === "wall") return "heroicons:cube";
    if (["rectangle", "ellipse"].includes(obj.type))
      return "heroicons:square-2-stack";
    return "heroicons:pencil-square";
  }
};

// Layer actions
const selectLayer = (layer: Layer) => {
  if (layer.isLocked) {
    console.log(`Layer ${layer.name} is locked and cannot be selected`);
    return;
  }

  // Clear all selections
  canvasStore.objects.forEach((obj) => (obj.isSelected = false));
  canvasStore.selectedElementId = null;
  canvasStore.selectedObjects = [];

  if (layer.type === "floor" && layer.objectIds) {
    // Select all floor objects
    const objs = canvasStore.objects.filter((o) => layer.objectIds!.includes(o.id));
    objs.forEach(o => o.isSelected = true);
    canvasStore.selectedObjects = [...objs];
  } else if (layer.type === "object") {
    // Select canvas object
    const obj = canvasStore.objects.find((o) => o.id === layer.id);
    if (obj) {
      obj.isSelected = true;
      canvasStore.selectedObjects = [obj];
    }
  } else {
    // Select DOM element
    canvasStore.selectedElementId = layer.id;
  }

  // Update layer selection state
  layers.value.forEach((l) => (l.isSelected = l.id === layer.id));
};

const toggleLayerVisibility = (layer: Layer) => {
  layer.isVisible = !layer.isVisible;

  if (layer.type === "floor" && layer.objectIds) {
    canvasStore.objects.forEach(o => {
      if (layer.objectIds!.includes(o.id)) {
        o.isVisible = layer.isVisible;
      }
    });
  } else if (layer.type === "object") {
    const obj = canvasStore.objects.find((o) => o.id === layer.id);
    if (obj) {
      obj.isVisible = layer.isVisible;
    }
  } else {
    const element = canvasStore.domElements.find((e) => e.id === layer.id);
    if (element) {
      element.isVisible = layer.isVisible;
    }
  }
};

const toggleLayerLock = (layer: Layer) => {
  layer.isLocked = !layer.isLocked;

  if (layer.type === "floor" && layer.objectIds) {
    canvasStore.objects.forEach(o => {
      if (layer.objectIds!.includes(o.id)) {
        o.isLocked = layer.isLocked;
      }
    });
  } else if (layer.type === "object") {
    const obj = canvasStore.objects.find((o) => o.id === layer.id);
    if (obj) {
      obj.isLocked = layer.isLocked;
    }
  } else {
    const element = canvasStore.domElements.find((e) => e.id === layer.id);
    if (element) {
      element.isLocked = layer.isLocked;
    }
  }
};

const deleteLayer = (layer: Layer) => {
  if (layer.isLocked) {
    console.log(`Cannot delete locked layer: ${layer.name}`);
    return;
  }

  if (layer.type === "floor" && layer.objectIds) {
    // Delete all floor objects
    canvasStore.objects = canvasStore.objects.filter(
      (o) => !layer.objectIds!.includes(o.id)
    );
    canvasStore.selectedObjects = canvasStore.selectedObjects.filter(
        (o) => !layer.objectIds!.includes(o.id)
    );
  } else if (layer.type === "object") {
    // Delete canvas object
    const index = canvasStore.objects.findIndex((o) => o.id === layer.id);
    if (index !== -1) {
      canvasStore.objects.splice(index, 1);
    }
    // Remove from selected objects if it was selected
    canvasStore.selectedObjects = canvasStore.selectedObjects.filter(
      (obj) => obj.id !== layer.id
    );
  } else {
    // Delete DOM element
    const index = canvasStore.domElements.findIndex((e) => e.id === layer.id);
    if (index !== -1) {
      canvasStore.domElements.splice(index, 1);
    }
    // Clear selection if this element was selected
    if (canvasStore.selectedElementId === layer.id) {
      canvasStore.selectedElementId = null;
    }
  }

  // Remove from layers
  const layerIndex = layers.value.findIndex((l) => l.id === layer.id);
  if (layerIndex !== -1) {
    layers.value.splice(layerIndex, 1);
  }
};

// Bulk actions
const toggleAllVisibility = () => {
  const allVisible = layers.value.every((layer) => layer.isVisible);
  layers.value.forEach((layer) => {
    layer.isVisible = !allVisible;
    toggleLayerVisibility(layer);
  });
};

const toggleAllLocks = () => {
  const allLocked = layers.value.every((layer) => layer.isLocked);
  layers.value.forEach((layer) => {
    layer.isLocked = !allLocked;
    toggleLayerLock(layer);
  });
};

// Simple Drag and Drop functionality
const handleDragStart = (event: DragEvent, layer: Layer) => {
  if (layer.isLocked) {
    event.preventDefault();
    console.log(`Cannot drag locked layer: ${layer.name}`);
    return;
  }

  if (!layer.isVisible) {
    event.preventDefault();
    console.log(`Cannot drag hidden layer: ${layer.name}`);
    return;
  }

  isDragging.value = true;
  dragLayer.value = layer;

  if (event.dataTransfer && event.target) {
    event.dataTransfer.setData("text/plain", layer.id);
    event.dataTransfer.effectAllowed = "move";
    const dragImage = event.target as HTMLElement;
    event.dataTransfer.setDragImage(dragImage, 20, 20);
  }
};

const handleDragEnter = (event: DragEvent, layer: Layer) => {
  if (!dragLayer.value || dragLayer.value.id === layer.id || layer.isLocked) {
    return;
  }

  event.preventDefault();
  dragOverLayer.value = layer;
};

const handleDragOver = (event: DragEvent, layer: Layer) => {
  if (!dragLayer.value || dragLayer.value.id === layer.id || layer.isLocked) {
    return;
  }

  event.preventDefault();
  event.dataTransfer!.dropEffect = "move";
};

const handleDragLeave = (event: DragEvent, layer: Layer) => {
  if (dragOverLayer.value?.id === layer.id) {
    dragOverLayer.value = null;
  }
};

const handleDrop = (
  event: DragEvent,
  targetLayer: Layer,
  targetIndex: number
) => {
  event.preventDefault();

  if (
    !dragLayer.value ||
    dragLayer.value.id === targetLayer.id ||
    targetLayer.isLocked
  ) {
    return;
  }

  // Get current sorted layers
  const currentSorted = sortedLayers.value;
  const dragIndex = currentSorted.findIndex(
    (l) => l.id === dragLayer.value!.id
  );

  if (dragIndex === -1 || targetIndex === -1) return;

  // Remove dragged layer from its current position
  const [draggedLayer] = currentSorted.splice(dragIndex, 1);

  // Insert at new position
  currentSorted.splice(targetIndex, 0, draggedLayer);

  // Update z-index based on new position (top = highest z-index)
  currentSorted.forEach((layer, index) => {
    const newZIndex = currentSorted.length - index; // Top layer gets highest z-index

    if (layer.type === "object") {
      const obj = canvasStore.objects.find((o) => o.id === layer.id);
      if (obj) {
        obj.zIndex = newZIndex;
      }
    } else {
      const element = canvasStore.domElements.find((e) => e.id === layer.id);
      if (element) {
        element.zIndex = newZIndex;
      }
    }
  });

  // canvasStore.save();
  dragOverLayer.value = null;
};

const handleDragEnd = () => {
  isDragging.value = false;
  dragLayer.value = null;
  dragOverLayer.value = null;
};

// Sync layer selection with canvas selection
const syncLayerSelection = () => {
  layers.value.forEach((layer) => {
    if (layer.type === "object") {
      const obj = canvasStore.objects.find((o) => o.id === layer.id);
      layer.isSelected = obj ? obj.isSelected : false;
    } else {
      layer.isSelected = canvasStore.selectedElementId === layer.id;
    }
  });
};

// Watch for changes in canvas store and update layers
watch(
  () => [
    canvasStore.objects,
    canvasStore.domElements,
    canvasStore.selectedObjects,
    canvasStore.selectedElementId,
  ],
  () => {
    initializeLayers();
    syncLayerSelection();
  },
  { deep: true }
);

// Initialize on mount
onMounted(() => {
  initializeLayers();
});

// Save when layers change
watch(
  layers,
  () => {
    // canvasStore.save();
  },
  { deep: true }
);
</script>

<style scoped>
@reference "tailwindcss";
.layers-panel {
  @apply h-full flex flex-col;
}

.layers-header {
  @apply flex items-center justify-between pb-2 border-b border-gray-200;
}

.search-bar {
  @apply shrink-0;
}

.layers-list {
  @apply flex-1 overflow-y-auto;
}

.layer-item {
  @apply transition-all duration-200;
}

.drag-handle {
  @apply cursor-grab active:cursor-grabbing;
}

.layer-actions {
  transition: opacity 0.2s ease;
}

/* Custom scrollbar for layers list */
.layers-list::-webkit-scrollbar {
  width: 4px;
}

.layers-list::-webkit-scrollbar-track {
  @apply bg-gray-100 rounded;
}

.layers-list::-webkit-scrollbar-thumb {
  @apply bg-gray-300 rounded;
}

.layers-list::-webkit-scrollbar-thumb:hover {
  @apply bg-gray-400;
}

/* Smooth transitions for drag operations */
.layer-item {
  transition: all 0.15s ease-in-out;
}
</style>
