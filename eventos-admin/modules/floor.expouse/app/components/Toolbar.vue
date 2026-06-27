<template>
  <div>
    <nav
      class="flex flex-col sm:flex-row justify-between items-start sm:items-center bg-white px-3 py-1 sm:px-4 text-blue-800 shadow relative"
    >
      <!-- Mobile Menu Toggle -->
      <button
        @click="isMobileMenuOpen = !isMobileMenuOpen"
        class="sm:hidden p-1 mb-2 hover:bg-blue-100 rounded self-start"
        aria-label="Toggle Menu"
      >
        <NuxtIcon
          :name="isMobileMenuOpen ? 'mdi:close' : 'mdi:menu'"
          class="h-6 w-6"
        />
      </button>

      <!-- Left: Floor dropdown + New -->
      <div
        class="flex items-center space-x-2 mb-2 sm:mb-0 w-full sm:w-auto"
        :class="{ 'hidden sm:flex': !isMobileMenuOpen && isMobile }"
      >
        <div class="flex items-center space-x-2 flex-1 sm:flex-initial">
          <select
            v-if="store.floors && store.floors.length"
            v-model="selectedFloorId"
            @change="handleFloorChange"
            class="border border-blue-300 rounded px-2 py-1 bg-white text-sm w-full sm:w-auto font-medium"
            aria-label="Select Floor"
          >
            <option
              v-for="floor in store.floors"
              :key="floor.id"
              :value="floor.id"
            >
              🏠 {{ floor.name }} ({{ floor.dimensions.length }}x{{
                floor.dimensions.width
              }}cm)
            </option>
          </select>
          <span v-else class="text-gray-600 text-sm">No floors</span>

          <!-- THIS FLOOR ADDING PLUS BUTTON WILL BE REOPEN FOR PREMIUM CUSTOMER -->
          <button
            @click="openModal = true"
            class="px-2 hover:bg-blue-100 rounded flex items-center justify-center"
            aria-label="Create New Floor"
          >
            <NuxtIcon name="mdi:plus" class="text-lg" />
          </button>
        </div>
      </div>

      <!-- Center: Toolbar (Undo, Redo, Guides, Zoom, Color) -->
      <div
        class="flex flex-wrap items-center gapx-2 mb-2 sm:mb-0 w-full sm:w-auto justify-center"
        :class="{ 'hidden sm:flex': !isMobileMenuOpen && isMobile }"
      >
        <!-- Measurement Unit Dropdown -->
        <div class="flex items-center space-x-1 mr-2">
          <label
            for="unitSelector"
            class="text-xs text-blue-800 hidden sm:block"
            >Units:</label
          >
          <select
            id="unitSelector"
            v-model="selectedUnit"
            @change="updateMeasurementUnit"
            class="border border-blue-300 rounded px-2 py-1 bg-white text-sm"
            aria-label="Select Measurement Unit"
          >
            <option value="centimeter">Centimeter</option>
            <option value="meter">Meter</option>
            <option value="feet">Feet</option>
            <option value="inches">Inches</option>
          </select>
        </div>

        <!-- Color Picker -->
        <div class="flex items-center space-x-1">
          <label for="colorPicker" class="text-xs text-blue-800 hidden sm:block"
            >Color:</label
          >
          <input
            id="colorPicker"
            type="color"
            v-model="store.currentColor"
            class="w-7 h-7 p-0 border-none cursor-pointer rounded"
            title="Choose drawing color"
          />
        </div>

        <!-- Action Buttons Group -->
        <div
          class="flex justify-center items-center space-x-1 bg-gray-50 rounded-lg px-2 ml-2"
        >
          <!-- Clear -->
          <button
            @click="store.clearCanvas"
            class="flex items-center justify-center p-2 hover:bg-blue-100 rounded"
            aria-label="Clear Canvas"
            title="Clear Canvas"
          >
            <NuxtIcon name="oui:eraser" class="text-lg" />
          </button>

          <!-- Undo -->
          <button
            @click="store.undo"
            :disabled="store.history.past.length === 0"
            class="flex items-center justify-center p-2 hover:bg-blue-100 rounded"
            aria-label="Undo"
            title="Undo (Ctrl+Z)"
          >
            <NuxtIcon
              name="jam:undo"
              class="text-lg"
              :class="{ 'text-gray-400': store.history.past.length === 0 }"
            />
          </button>

          <!-- Redo -->
          <button
            @click="store.redo"
            :disabled="store.history.future.length === 0"
            class="flex items-center justify-center p-2 hover:bg-blue-100 rounded"
            aria-label="Redo"
            title="Redo (Ctrl+Y)"
          >
            <NuxtIcon
              name="jam:redo"
              class="text-lg"
              :class="{ 'text-gray-400': store.history.future.length === 0 }"
            />
          </button>

          <!-- Toggle Guides -->
          <button
            @click="uiStore.toggleGuides"
            class="flex items-center justify-center p-2 hover:bg-blue-100 rounded"
            aria-label="Toggle Guidelines"
            :class="{ 'bg-blue-200': uiStore.showGuides }"
            title="Toggle Grid"
          >
            <NuxtIcon name="ic:outline-grid-4x4" class="text-lg" />
          </button>
        </div>

        <!-- Zoom Controls -->
        <div
          class="flex justify-center items-center space-x-1 bg-gray-50 rounded-lg px-2 mx-2"
        >
          <!-- Zoom Out -->
          <button
            @click="store.zoomOut"
            class="flex items-center justify-center p-2 hover:bg-blue-100 rounded"
            aria-label="Zoom Out"
            title="Zoom Out"
          >
            <NuxtIcon name="ph:magnifying-glass-minus" class="text-lg" />
          </button>

          <!-- Zoom Display -->
          <button
            class="flex items-center justify-center px-3 py-1 text-xs min-w-[60px] text-center rounded"
            @click="resetZoom"
            title="Reset Zoom"
          >
            {{ Math.round(store.zoom * 100) }}%
          </button>

          <!-- Zoom In -->
          <button
            @click="store.zoomIn"
            class="flex items-center justify-center p-2 hover:bg-blue-100 rounded"
            aria-label="Zoom In"
            title="Zoom In"
          >
            <NuxtIcon name="ph:magnifying-glass-plus" class="text-lg" />
          </button>
        </div>
      </div>

      <!-- Right: Share & Save -->
      <div
        class="flex items-center gap-2 w-full sm:w-auto"
        :class="{ 'hidden sm:flex': !isMobileMenuOpen && isMobile }"
      >
        <!-- Share Button Group -->
        <div class="relative flex flex-col items-center">
            <!-- Main Share Button -->
            <button
                @click="handleShare"
                :disabled="shareLoading || isSaving"
                class="flex items-center justify-center gap-2 bg-indigo-500 text-white px-4 py-1 rounded-lg hover:bg-indigo-600 disabled:opacity-70 disabled:cursor-not-allowed transition-all duration-200 font-medium shadow-sm min-w-[100px]"
                title="Share Design Link"
            >
                <svg
                    v-if="shareLoading"
                    class="animate-spin h-5 w-5 text-white"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <template v-else>
                    <NuxtIcon name="ph:share-network-fill" class="text-lg" />
                    <span class="hidden sm:inline">Share</span>
                </template>
            </button>

            <!-- Share Input Popover (Below Button) -->
            <div 
              v-if="showShareInput && generatedShareUrl" 
              class="absolute top-full mt-2 right-0 z-50 bg-white border border-indigo-200 rounded-lg shadow-xl p-2 w-[300px] animate-in fade-in slide-in-from-top-2"
            >
                <!-- Close Button -->
                <button 
                    @click="showShareInput = false"
                    class="absolute -top-2 -right-2 bg-white text-gray-500 hover:text-red-500 rounded-full p-0.5 border border-gray-200 shadow-sm transition-colors z-10"
                    title="Close"
                >
                    <NuxtIcon name="mdi:close" class="text-sm" />
                </button>

                <div class="flex items-center bg-gray-50 border border-gray-200 rounded overflow-hidden">
                    <input 
                      type="text" 
                      readonly 
                      :value="generatedShareUrl" 
                      class="px-3 py-2 text-sm text-gray-700 w-full outline-none bg-transparent"
                      @click="($event.target as HTMLInputElement).select()"
                    />
                    <button 
                      @click="copyShareLink" 
                      title="Copy Link"
                      class="px-3 py-2 text-indigo-600 hover:bg-indigo-50 border-l border-gray-200 transition-colors"
                    >
                       <NuxtIcon name="ph:copy-simple" class="text-lg" />
                    </button>
                </div>
                <div class="relative h-5 mt-1 text-center overflow-hidden">
                    <Transition name="msg-slide">
                        <div 
                           v-if="showCopyMessage" 
                           class="absolute inset-0 flex items-center justify-center text-xs font-bold text-emerald-600"
                        >
                            <NuxtIcon name="mdi:check" class="mr-1"/> Link Copied!
                        </div>
                        <div 
                           v-else 
                           class="absolute inset-0 flex items-center justify-center text-xs text-gray-400"
                        >
                           Link reflects design snapshot
                        </div>
                    </Transition>
                </div>
            </div>
        </div>

        <!-- Save Button with Spinner -->
        <button
          @click="handleSave"
          :disabled="isSaving"
          class="flex items-center justify-center gap-2 bg-emerald-500 text-white px-5 py-1 rounded-lg hover:bg-emerald-600 disabled:opacity-70 disabled:cursor-not-allowed transition-all duration-200 font-medium shadow-sm"
        >
          <svg
            v-if="isSaving"
            class="animate-spin h-4 w-4 text-white"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle
              class="opacity-25"
              cx="12"
              cy="12"
              r="10"
              stroke="currentColor"
              stroke-width="4"
            ></circle>
            <path
              class="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            ></path>
          </svg>
          <NuxtIcon v-else name="mingcute:save-line" class="text-lg" />
          {{ isSaving ? "Saving..." : "Save" }}
        </button>
      </div>

      <!-- Floor creation modal -->
      <div
        v-if="openModal"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-[9999] p-4"
        @click="openModal = false"
      >
        <div
          class="bg-white p-6 rounded shadow-lg w-full max-w-md max-h-[90vh] overflow-y-auto"
          @click.stop
        >
          <h2 class="text-lg font-bold mb-4">Create New Floor</h2>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium mb-1">
                Floor Name:
              </label>
              <input
                v-model="newFloorName"
                class="border border-gray-300 p-3 w-full rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 text-sm"
                placeholder="Enter floor name"
              />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium mb-1">
                  Length (cm):
                </label>
                <input
                  type="number"
                  v-model.number="newFloorLength"
                  class="border border-gray-300 p-3 w-full rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 text-sm"
                  min="1"
                  placeholder="Length"
                />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">
                  Width (cm):
                </label>
                <input
                  type="number"
                  v-model.number="newFloorWidth"
                  class="border border-gray-300 p-3 w-full rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 text-sm"
                  min="1"
                  placeholder="Width"
                />
              </div>
            </div>
          </div>

          <div
            class="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-2 mt-6"
          >
            <button
              @click="createNewFloor"
              :disabled="
                isCreating ||
                !newFloorName ||
                newFloorLength <= 0 ||
                newFloorWidth <= 0
              "
              class="relative px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 disabled:opacity-70 disabled:cursor-not-allowed transition-all duration-200 font-medium flex-1 sm:flex-initial flex items-center justify-center gap-2"
            >
              <!-- Spinner -->
              <svg
                v-if="isCreating"
                class="animate-spin h-4 w-4 text-white"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
              >
                <circle
                  class="opacity-25"
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="currentColor"
                  stroke-width="4"
                />
                <path
                  class="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                />
              </svg>

              <!-- Icon + Text -->
              <NuxtIcon v-else name="ph:plus-fill" class="text-lg" />
              <span>{{ isCreating ? "Creating..." : "Create Floor" }}</span>
            </button>
            <button
              @click="openModal = false"
              class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors text-sm font-medium flex-1 sm:flex-initial"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </nav>
    
    <!-- Loading Overlay -->
    <Teleport to="body">
      <div
        v-if="isLoading"
        class="fixed inset-0 z-[99999] flex items-center justify-center bg-white/90 backdrop-blur-sm"
      >
        <div class="text-center">
          <div class="flex justify-center gap-2 mb-4">
            <div
              v-for="i in 8"
              :key="i"
              class="w-3 h-3 bg-emerald-500 rounded-full animate-pulse"
              :style="{ animationDelay: `${i * 0.1}s` }"
            />
          </div>
          <p class="text-sm font-medium text-gray-700">{{ loadingMessage }}</p>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, computed } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useUiStore } from "@floorplan/stores/uiStore";

const uiStore = useUiStore();
const store = useCanvasStore();
const route = useRoute();

/* ────── LOADING STATE ────── */
let isLoading = store.isLoading; // ← start **true**
const loadingMessage = ref("Loading…");

const startLoading = (msg: string) => {
  loadingMessage.value = msg;
  isLoading = true; // ← **THIS** makes the overlay appear
};
const stopLoading = () => (isLoading = false);

/* ────── UI STATE ────── */
const isMobileMenuOpen = ref(false);
const isMobile = ref(false);
const openModal = ref(false);

// 🆕 Load saved unit from localStorage or use default from uiStore
const selectedUnit = ref(
  process.client
    ? localStorage.getItem("measurementUnit") || uiStore.measurementUnit
    : "centimeter"
);

/* ────── FORM STATE ────── */
const newFloorName = ref("");
const newFloorLength = ref<number>(1680);
const newFloorWidth = ref<number>(800);
const newFloorShape = ref("rectangular");
const isCreating = ref(false);
const isSaving = ref(false);

/* ────── FLOOR SELECTION ────── */
const selectedFloorId = ref(store.currentFloorId);

/* ────── URL PARAMS ────── */
const queryEventId = ref(route.query.event as string);
const queryToken = ref(route.query.user as string);
const event_id = computed(() => atob(queryEventId.value));
const token = computed(() => atob(queryToken.value));

/* ────── HELPERS ────── */
// 🆕 Update measurement unit and persist to localStorage
const updateMeasurementUnit = () => {
  console.log("📏 Updating measurement unit to:", selectedUnit.value);

  // Update the store
  uiStore.setMeasurementUnit(selectedUnit.value);

  // 🎯 Save to localStorage for persistence
  if (process.client) {
    localStorage.setItem("measurementUnit", selectedUnit.value);
    console.log("✅ Saved to localStorage:", selectedUnit.value);
  }
};

const resetZoom = () => store.setZoom(1);

const shareLoading = ref(false);
const showShareInput = ref(false);
const generatedShareUrl = ref("");
const isDirty = ref(false); // Track if design has changed since last share
const showCopyMessage = ref(false);

const copyShareLink = async () => {
    if (!generatedShareUrl.value) return;
    try {
        await navigator.clipboard.writeText(generatedShareUrl.value);
        showCopyMessage.value = true;
        setTimeout(() => {
            showCopyMessage.value = false;
        }, 2000);
    } catch (e) {
        console.warn("Copy failed", e);
    }
};

const handleShare = async () => {
  if (isSaving.value || shareLoading.value) return;

  // If we have a link and the design hasn't changed (isDirty is false), just show the existing link
  if (!isDirty.value && generatedShareUrl.value) {
      showShareInput.value = true;
      return;
  }

  // 1. Save state first
  await handleSave();
  
  shareLoading.value = true;
  showShareInput.value = false;
  
  try {
    const { getCanvasBlob } = useCanvasExport();
    
    // Pass NULL to export full canvas
    const blob = await getCanvasBlob(null);
    if (!blob) throw new Error("Failed to generate image blob");

    const formData = new FormData();
    formData.append("image", blob, `preview-${Date.now()}.png`);
    
    const response: any = await $fetch('/api/upload-preview', {
        method: 'POST',
        body: formData
    });
    
    if (response && response.success && response.imageUrl) {
        generatedShareUrl.value = `${window.location.origin}/preview?image=${encodeURIComponent(response.imageUrl)}`;
        showShareInput.value = true;
        isDirty.value = false; // logic: link is now fresh
        
        await copyShareLink(); // Auto-copy
    } else {
        throw new Error("Upload failed");
    }

  } catch (err) {
    console.error("Share Error:", err);
    alert("Failed to generate share link. Please try again.");
  } finally {
    shareLoading.value = false;
  }
};

/* ────── SAVE ────── */
const handleSave = async () => {
  if (isSaving.value) return;
  isSaving.value = true;
  
  // Design has definitively changed/saved, so mark as dirty
  isDirty.value = true;
  showShareInput.value = false; // Hide old link since it's now stale
  
  try {
    await store.save();
  } finally {
    setTimeout(() => (isSaving.value = false), 800);
  }
};

/* ────── SWITCH FLOOR ────── */
const handleFloorChange = async () => {
  if (!selectedFloorId.value || selectedFloorId.value === store.currentFloorId)
    return;

  startLoading("Switching floor…");
  try {
    await store.loadFloorData(selectedFloorId.value);
    store.switchFloor(selectedFloorId.value);
  } catch (e) {
    console.error(e);
    loadingMessage.value = "Failed to switch floor";
  } finally {
    stopLoading();
    if (isMobile.value) isMobileMenuOpen.value = false;
  }
};

/* ────── CREATE FLOOR ────── */
const createNewFloor = async () => {
  if (
    !newFloorName.value ||
    newFloorLength.value <= 0 ||
    newFloorWidth.value <= 0
  )
    return;

  // startLoading("Creating floor…");
  isCreating.value = true;
  try {
    await store.createFloor(
      event_id.value,
      token.value,
      newFloorName.value,
      { length: newFloorLength.value, width: newFloorWidth.value },
      newFloorShape.value
    );
    await store.loadFloorData(store.currentFloorId);
    openModal.value = false;
    newFloorName.value = "";
    newFloorLength.value = 1680;
    newFloorWidth.value = 800;
  } catch (e) {
    console.error(e);
    loadingMessage.value = "Failed to create floor";
  } finally {
    isCreating.value = false;
    stopLoading();
  }
};

/* ────── MOBILE MENU ────── */
const handleClickOutside = (e: MouseEvent) => {
  const nav = document.querySelector("nav");
  if (nav && !nav.contains(e.target as Node)) isMobileMenuOpen.value = false;
};
const checkScreenSize = () => {
  isMobile.value = window.innerWidth < 640;
  if (!isMobile.value) isMobileMenuOpen.value = false;
};

/* ────── WATCHERS ────── */
watch(
  () => route.query,
  (q) => {
    queryEventId.value = q.event as string;
    queryToken.value = q.user as string;
  },
  { immediate: true }
);

watch(
  () => store.currentFloorId,
  (id) => (selectedFloorId.value = id),
  { immediate: true }
);

/* ────── INITIAL LOAD ────── */
onMounted(async () => {
  checkScreenSize();
  window.addEventListener("resize", checkScreenSize);
  document.addEventListener("click", handleClickOutside);

  // 🆕 Initialize the store with saved unit on mount
  if (process.client) {
    const savedUnit = localStorage.getItem("measurementUnit");
    if (savedUnit) {
      selectedUnit.value = savedUnit;
      uiStore.setMeasurementUnit(savedUnit);
      console.log("🔄 Restored measurement unit from localStorage:", savedUnit);
    }
  }

  const ev = route.query.event as string;
  const tk = route.query.user as string;
  if (!ev || !tk) {
    loadingMessage.value = "Invalid link";
    stopLoading();
    return;
  }

  try {
    startLoading("Loading your floor plan…");
    const decodedEvent = atob(ev);
    const decodedToken = atob(tk);
    store.token = decodedToken;

    // ← YOUR EXISTING METHOD
    await store.loadFloorData(store.currentFloorId);
  } catch (e) {
    console.error(e);
    loadingMessage.value = "Failed to load floor";
  } finally {
    stopLoading(); // ← ALWAYS hide spinner
  }
});

onUnmounted(() => {
  window.removeEventListener("resize", checkScreenSize);
  document.removeEventListener("click", handleClickOutside);
});
</script>

<style scoped>
/* Ensure color picker is styled consistently */
input[type="color"] {
  -webkit-appearance: none;
  appearance: none;
  background: transparent;
  border-radius: 6px;
  overflow: hidden;
  border: 1px solid #cbd5e0;
}
input[type="color"]::-webkit-color-swatch {
  border: none;
  border-radius: 4px;
}
input[type="color"]::-moz-color-swatch {
  border: none;
  border-radius: 4px;
}

/* Smooth transitions */
nav {
  transition: all 0.3s ease;
}

/* Better touch targets for mobile */
@media (max-width: 640px) {
  button {
    min-height: 44px;
    min-width: 44px;
  }

  select,
  input {
    min-height: 44px;
  }
}

/* Custom scrollbar for modal */
.modal-content {
  scrollbar-width: thin;
  scrollbar-color: #cbd5e0 #f7fafc;
}

.modal-content::-webkit-scrollbar {
  width: 6px;
}

.modal-content::-webkit-scrollbar-track {
  background: #f7fafc;
  border-radius: 3px;
}

.modal-content::-webkit-scrollbar-thumb {
  background: #cbd5e0;
  border-radius: 3px;
}

.modal-content::-webkit-scrollbar-thumb:hover {
  background: #a0aec0;
}

@keyframes pulse {
  0%,
  100% {
    opacity: 0.4;
    transform: scale(0.8);
  }
  50% {
    opacity: 1;
    transform: scale(1.2);
  }
}
.animate-pulse {
  animation: pulse 1.2s ease-in-out infinite;
}

/* Transition for Share Message */
.msg-slide-enter-active,
.msg-slide-leave-active {
  transition: all 0.3s ease;
}
.msg-slide-enter-from {
  opacity: 0;
  transform: translateY(10px);
}
.msg-slide-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
