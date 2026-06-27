<template>
  <div>
    <!-- Main Sidebar -->
    <div
      class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-white rounded-lg border border-gray-200 shadow-xl px-4 py-2 flex items-center gap-4 z-50 h-14"
    >
      <div
        v-for="(tool, index) in tools"
        :key="tool.id"
        class="relative flex items-center"
        :class="{ 'z-10': hoveredTool === tool.id }"
        @mouseleave="handleToolLeave(tool.id)"
      >
        <!-- BOOTH TOOL (Special Case) -->
        <button
          v-if="tool.id === 'booth'"
          class="w-16 h-16 bg-indigo-500 rounded-full flex items-center justify-center -mt-6 border-[6px] border-white shadow-lg transition-transform hover:scale-105"
          @click="handleToolInteraction(tool.id)"
          :aria-label="tool.label"
        >
          <img
            v-if="tool.icon.endsWith('.svg')"
            :src="`/img/icon/${tool.icon}`"
            class="w-8 h-8 object-contain"
          />
          <NuxtIcon v-else :name="tool.icon" class="text-3xl text-white" />
        </button>

        <!-- REGULAR TOOLS -->
        <div
          v-else
          class="flex items-center rounded-md transition-all duration-200"
          :class="{
             'bg-indigo-500 text-white': isToolActive(tool),
           }"
        >
          <!-- Main Tool Button -->
          <button
            class="flex items-center justify-center p-3 rounded-l-md transition-colors"
            :class="{
              'bg-indigo-500 text-white': isToolActive(tool),
              'hover:bg-gray-100': !isToolActive(tool),
              'rounded-r-md': !tool.subItems
            }"
            @click="handleToolInteraction(tool.id)"
            :aria-label="tool.label"
          >
            <!-- Show active sub-tool icon if applicable, otherwise default tool icon -->
            <img
               v-if="getActiveIcon(tool).endsWith('.svg')"
              :src="`/img/icon/${getActiveIcon(tool)}`"
              class=" object-cover"
               :class="{ 'brightness-0 invert': isToolActive(tool) }"
            />
            <NuxtIcon v-else :name="getActiveIcon(tool)" class="text-2xl" />
          </button>

          <!-- Toggle/Chevron Button (Only if sub items) -->
          <div
            v-if="tool.subItems"
            class="h-full flex items-center rounded-r-md cursor-pointer border-l border-white/20"
             :class="{
               'bg-indigo-500 text-white': isToolActive(tool),
               'bg-gray-100 text-gray-600 hover:bg-gray-200': !isToolActive(tool)
             }"
            @mouseenter="handleToolHover(tool.id)"
          >
            <button class=" h-full flex items-center justify-center ">
               <NuxtIcon name="heroicons:chevron-down" class="py-5 text-xl" />
            </button>
          </div>
        </div>

        <!-- Dropdown Menu -->
        <div
          v-if="tool.subItems"
          class="absolute bottom-full left-0 mb-3 bg-gray-50 rounded-xl shadow-2xl border border-gray-200 py-1 min-w-[320px] transform transition-all duration-200 origin-bottom-left flex flex-col overflow-hidden"
          :class="{
            'opacity-100 scale-100 pointer-events-auto': hoveredTool === tool.id,
            'opacity-0 scale-95 pointer-events-none': hoveredTool !== tool.id,
          }"
          @mouseenter="clearHoverTimeout"
          @mouseleave="handleToolLeave(tool.id)"
        >
          <!-- HEADER: Active Tool (Replica of design images) -->
          <div 
            class="px-2 pt-2 pb-1"
            v-if="!['select', 'frame', 'drawing'].includes(tool.id)"
          >
             <button
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm w-full bg-indigo-500 text-white"
               @click="handleSubToolClick(tool.id, getActiveSubItem(tool).id)"
             >
                  <!-- Checkmark -->
                  <span class="w-4 flex items-center justify-center">
                    <NuxtIcon name="heroicons:check" class="text-base" />
                  </span>

                  <!-- Icon -->
                  <div class="flex items-center justify-center w-6">
                    <img
                      v-if="getActiveSubItem(tool).icon.endsWith('.svg')"
                      :src="`/img/icon/${getActiveSubItem(tool).icon}`"
                      class="w-5 h-5 object-contain brightness-0 invert"
                    />
                    <NuxtIcon v-else :name="getActiveSubItem(tool).icon" class="text-lg" />
                  </div>

                  <!-- Label -->
                  <span class="grow text-left font-medium">{{ getActiveSubItem(tool).label }}</span>

                  <!-- Shortcut -->
                  <span class="text-xs font-mono text-indigo-200">
                      {{ getActiveSubItem(tool)?.shortcut || '' }}
                  </span>
             </button>
          </div>

          <!-- Divider -->
          <!-- <div class="h-px bg-gray-200 mx-2 my-1"></div> -->

          <!-- LIST: Other Items -->
          <div class="flex flex-col gap-1 px-2 pb-2">
            <button
              v-for="subItem in tool.subItems"
              :key="subItem.id"
              class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors group w-full"
              :class="{
                'bg-indigo-500 text-white': isSubToolSelected(tool.id, subItem.id) || (subItem.id === canvasStore.currentTool),
                'text-gray-700 hover:bg-gray-200': !(isSubToolSelected(tool.id, subItem.id) || (subItem.id === canvasStore.currentTool))
              }"
              @click="handleSubToolClick(tool.id, subItem.id)"
            >
              <!-- Checkmark Logic -->
              <span class="w-4 flex items-center justify-center">
                <NuxtIcon
                  v-if="isSubToolSelected(tool.id, subItem.id) || (subItem.id === canvasStore.currentTool)" 
                  name="heroicons:check"
                  class="text-base"
                  :class="{ 'text-white': isSubToolSelected(tool.id, subItem.id) || (subItem.id === canvasStore.currentTool) }"
                />
              </span>

              <!-- Icon -->
              <div class="flex items-center justify-center w-6">
                <img
                  v-if="subItem.icon.endsWith('.svg')"
                  :src="`/img/icon/${subItem.icon}`"
                  class="w-5 h-5 object-contain"
                  :class="{ 'brightness-0 invert': isSubToolSelected(tool.id, subItem.id) || (subItem.id === canvasStore.currentTool) }"
                />
                <NuxtIcon v-else :name="subItem.icon" class="text-lg" />
              </div>

              <!-- Label -->
              <span class="grow text-left font-medium">{{ subItem.label }}</span>

              <!-- Shortcut -->
              <span class="text-xs font-mono text-gray-400">
                  {{ subItem.shortcut || '' }}
              </span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Booth Modal -->
    <BoothModal
      v-if="showBoothModal"
      :show="showBoothModal"
      @close="handleBoothModalClose"
      @save="handleBoothSave"
    />

    <!-- Image Upload Modal -->
    <ImageUploadModal
      v-if="showImageModal"
      @close="showImageModal = false"
      @uploaded="handleImageUploaded"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { useSidebarTools } from "@floorplan/composables/useSidebarTools";

const { canvasStore, tools, selectTool, selectSubTool } = useSidebarTools();

const showBoothModal = ref(false);
const showImageModal = ref(false);
const hoveredTool = ref<string | null>(null);
const selectedSubTool = ref<{ mainToolId: string; subToolId: string } | null>(null);
let hoverTimeout: NodeJS.Timeout | null = null;

// Helper to get the correct icon to display on the main button
// If a subtool is active, show its icon. Otherwise show default tool icon.
// Helper to get the correct icon to display on the main button
// If a subtool is active, show its icon. Otherwise show default tool icon.
const getActiveIcon = (tool: any) => {
  // 1. Check if a sub-item is currently active in the engine (Real-time state)
  if (tool.subItems) {
      const activeRunningSub = tool.subItems.find((s: any) => s.id === canvasStore.currentTool);
      if (activeRunningSub) return activeRunningSub.icon;
  }

  // 2. Check if this tool was the last interaction (Visual state for stateless tools like Doors)
  if (selectedSubTool.value?.mainToolId === tool.id) {
       const lastSub = tool.subItems.find((s: any) => s.id === selectedSubTool.value?.subToolId);
       if (lastSub) return lastSub.icon;
  }

  // 3. Default fallback for Shape/Pen (Static base icon if nothing selected)
  if (tool.id === 'shape' || tool.id === 'pen') {
      return tool.icon;
  }

  // 4. Default fallback for others (First sub-item)
  if (tool.subItems && tool.subItems.length > 0) {
      return tool.subItems[0].icon;
  }
  return tool.icon;
};

const getActiveSubItem = (tool: any) => {
    // Special case for shape and pen tool: always return the tool itself (Static Header)
    if (tool.id === 'shape' || tool.id === 'pen') {
        return tool;
    }
    
    // FIXED HEADER: Always return the first sub-item (Default Tool)
    if (tool.subItems && tool.subItems.length > 0) {
        return tool.subItems[0];
    }
    return tool;
};


// Handle tool hover with delay
const handleToolHover = (toolId: string) => {
  if (hoverTimeout) {
    clearTimeout(hoverTimeout);
    hoverTimeout = null;
  }
  hoveredTool.value = toolId;
};

// Handle tool leave with delay
const handleToolLeave = (toolId: string) => {
  hoverTimeout = setTimeout(() => {
    if (hoveredTool.value === toolId) {
      hoveredTool.value = null;
    }
  }, 300);
};

// Clear the timeout when mouse enters dropdown
const clearHoverTimeout = () => {
  if (hoverTimeout) {
    clearTimeout(hoverTimeout);
    hoverTimeout = null;
  }
  clearHoverTimeout;
};

// Check if a subtool is selected
const isSubToolSelected = (mainToolId: string, subToolId: string): boolean => {
  // Explicit check for Hand tool activation via shortcut/spacebar
  if (mainToolId === 'select' && subToolId === 'hand' && canvasStore.currentTool === 'hand') {
      return true;
  }
  
  return (
    selectedSubTool.value?.mainToolId === mainToolId &&
    selectedSubTool.value?.subToolId === subToolId
  );
};

// Check if the main tool is active (either directly or via a sub-item)
const isToolActive = (tool: any) => {
    if (canvasStore.currentTool === tool.id) return true;
    if (tool.subItems) {
        return tool.subItems.some((s: any) => s.id === canvasStore.currentTool) || 
               // Special case mapping for Move -> Select because we renamed the ID
               (tool.id === 'select' && canvasStore.currentTool === 'move') ||
               // Special case for Hand tool belonging to Select group
               (tool.id === 'select' && canvasStore.currentTool === 'hand'); 
    }
    return false;
};

// Handle main tool interaction
const handleToolInteraction = (toolId: string) => {
  if (toolId === "image") {
    showImageModal.value = true;
    return;
  }

  // Common tools
  if (["select", "hand", "drawing", "shape", "elements", "booth", "text", "frame", "pen", "elements-merged"].includes(toolId)) {
    // If it has subitems, we might want to select the *last used* subitem or default?
    // For now, let's just select the ID directly, assuming it maps to a valid tool or default behavior
    if(toolId !== 'booth') {
        // If clicking the main button of a group, re-select the currently active subtool if valid
        // or default to the first one?
        // Actually, 'drawing' is not a tool ID itself in canvasStore usually.
        // Let's check if we have a selected subtool for this group
        if (selectedSubTool.value?.mainToolId === toolId) {
            selectTool(selectedSubTool.value.subToolId);
        } else {
             // Default to the first subitem if available
             const toolDef = tools.find(t => t.id === toolId);
             if (toolDef && toolDef.subItems && toolDef.subItems.length > 0) {
                 const dest = toolDef.subItems[0].id; // e.g. 'rectangle'
                 selectTool(dest);
                 selectedSubTool.value = { mainToolId: toolId, subToolId: dest };
             } else {
                 selectTool(toolId);
             }
        }
    } else {
        // BOOTH
         selectTool(toolId);
         showBoothModal.value = true;
    }
  }
};

// Handle sub tool click
const handleSubToolClick = (mainToolId: string, subToolId: string) => {
  console.log(`Creating element: ${mainToolId} - ${subToolId}`);

  // Set the selected subtool
  selectedSubTool.value = { mainToolId, subToolId };
  // Hide menu
  hoveredTool.value = null;

  // Special case for image
  if (subToolId === "image" || subToolId === "image-video") {
    showImageModal.value = true;
    return;
  }

   // Special case for Shapes in non-shape groups (if any)
  if (["star", "polygon", "diamond"].includes(subToolId)) {
     if (!canvasStore.addElement) return;
     canvasStore.addElement("shape", { subtype: subToolId });
     canvasStore.setTool("select"); // Reset to select after shape creation
     return;
  }

  // Standard Logic
  switch (mainToolId) {
    case "pen":
        // If user actively clicked the "Pen" header
        if (subToolId === 'pen') {
            subToolId = 'pencil';
        }
        selectSubTool(subToolId);
        break;
    case "shape":
        // If user actively clicked the "Shape" header (which now has ID 'shape'),
        // default to first item (single-door) or handle appropriately
        if(subToolId === 'shape') {
            subToolId = 'single-door';
        }

        // Special handling for Doors -> Add as Image
        if (['single-door', 'double-door'].includes(subToolId)) {
             const imgSrc = subToolId === 'single-door' ? 'single-door.svg' : 'double-door.svg';
             const label = subToolId === 'single-door' ? 'Single Door' : 'Double Door';
             
             canvasStore.addElement("image", { 
                 src: `/img/icon/${imgSrc}`, 
                 content: label 
             });
             canvasStore.setTool("select");
             break;
        }

        // Distinguish between pure shapes (diamond) and elements (lounge)
        // Hardcoded check for shape subtypes vs element subtypes
        if(subToolId === 'diamond') {
             canvasStore.addElement("shape", { subtype: subToolId });
             canvasStore.setTool("select");
        } else {
            // Assume element
             canvasStore.addElement("elements", { subtype: subToolId });
             canvasStore.setTool("select");
        }
      break;
    case "elements": // Legacy support
         canvasStore.addElement("elements", { subtype: subToolId });
         canvasStore.setTool("select");
      break;
    default:
      // Drawing tools, Frame tools, Pen tools
      selectSubTool(subToolId);
      break;
  }
};


// Handle image uploaded from modal
const handleImageUploaded = (data: { url: string; title: string }) => {
  if (!canvasStore.addElement) {
    console.error("addElement method not available in canvasStore");
    return;
  }

  const elementId = canvasStore.addElement("image", {
    src: data.url,
    content: data.title || "",
  });

  console.log(`Created image element: ${elementId}`);
  showImageModal.value = false;
  canvasStore.setTool("select");
};

// Handle booth modal close
const handleBoothModalClose = () => {
  showBoothModal.value = false;
  canvasStore.setTool("select");
};

// Handle booth data saving
const handleBoothSave = (boothData: any) => {
  canvasStore.addBooth(boothData);
  showBoothModal.value = false;
};

// Keyboard shortcut handler
const handleKeydown = (event: KeyboardEvent) => {
  if (event.altKey && event.key === "s") {
    event.preventDefault();
    selectTool("hand");
  }
  if (event.shiftKey && event.key.toUpperCase() === "T") {
    event.preventDefault();
    selectTool("text");
  }
};

// Add and remove event listeners
onMounted(() => {
  document.addEventListener("keydown", handleKeydown);
});

onUnmounted(() => {
  document.removeEventListener("keydown", handleKeydown);
  if (hoverTimeout) {
    clearTimeout(hoverTimeout);
  }
});
</script>

<style scoped>
/* Ensure the booth button pops out correctly */
button:focus {
    outline: none;
}
</style>
