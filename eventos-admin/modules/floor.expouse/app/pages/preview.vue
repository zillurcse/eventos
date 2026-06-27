<!-- pages/preview.vue -->
<template>
  <div class="w-full h-screen bg-white relative overflow-hidden">
    <!-- Image Preview Mode -->
    <img 
      v-if="$route.query.image" 
      :src="decodeURIComponent($route.query.image as string)" 
      class="w-full h-full object-contain pointer-events-none" 
      alt="Design Preview" 
    />

    <!-- Interactive Canvas Mode -->
    <canvas 
      v-else 
      ref="canvasEl" 
      class="absolute inset-0" 
      :class="canvasClasses" 
    />

    <!-- DOM Elements overlay (same as Whiteboard.vue) -->
    <div class="absolute inset-0" :class="pointerEventsNone">
      <Element
        v-for="element in domElements"
        :key="element.id"
        :id="element.id"
        :type="element.type"
        :subtype="element.subtype"
        :position="element.position"
        :size="element.size"
        :style-props="element.styleProps"
        :content="element.content"
        :src="element.src"
        :rotation="element.rotation"
        :z-index="element.zIndex"
        :class="pointerEventsNone"
      />
    </div>

    <!-- Rotation Handles (same as Whiteboard.vue) -->
    <div
      v-for="(handle, index) in rotationHandles"
      :key="`rotation-${index}`"
      class="absolute w-6 h-6 flex items-center justify-center z-10 pointer-events-none"
      :style="{ left: `${handle.x - 12}px`, top: `${handle.y - 25}px` }"
    >
      <NuxtIcon name="heroicons:arrow-path" class="w-4 h-4 text-blue-500" />
    </div>

    <!-- Booth Arrows (same as Whiteboard.vue) -->
    <div
      v-for="(arrow, index) in boothArrows"
      :key="`arrow-${index}`"
      class="absolute flex items-center justify-center z-50 pointer-events-none"
      :style="{
        left: `${arrow.x}px`,
        top: `${arrow.y}px`,
        width: `${arrow.width}px`,
        height: `${arrow.height}px`,
      }"
    />

    <!-- Preview Controls Overlay -->
    <!-- <div class="absolute top-4 right-4 flex gap-2 z-50">
      <button
        @click="downloadAsPNG"
        class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors"
      >
        📥 PNG
      </button>
      <button
        @click="downloadAsPDF"
        class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors"
      >
        📥 PDF
      </button>
      <button
        @click="exportAsJSON"
        class="bg-purple-500 text-white px-4 py-2 rounded-lg hover:bg-purple-600 transition-colors"
      >
        💾 JSON
      </button>
      <button
        @click="closePreview"
        class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors"
      >
        ✕ Close
      </button>
    </div> -->
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick, watch } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useCanvasObjects } from "@floorplan/composables/useCanvasObjects";
import { useBoothArrows } from "@floorplan/composables/useBoothArrows";
import { useCanvasEngine } from "@floorplan/composables/useCanvasEngine";
import { useCanvasExport } from "@floorplan/composables/useCanvasExport";

const store = useCanvasStore();
const canvasObjects = useCanvasObjects();
const { getBoothArrowRegions } = useBoothArrows();
const route = useRoute();
const canvasExport = useCanvasExport(); // Use the robust export logic

const canvasEl = ref<HTMLCanvasElement>();
const isLoading = ref(true);
const loadingMessage = ref("Loading floor plan...");

// Store readiness check
const isStoreReady = computed(() => {
  return (
    store &&
    typeof store.currentTool !== "undefined" &&
    typeof store.zoom !== "undefined" &&
    typeof store.offset !== "undefined" &&
    Array.isArray(store.objects) &&
    Array.isArray(store.domElements)
  );
});

const canvasClasses = computed(() => "cursor-default");

// Only show dimension arrows if they are relevant for the view. 
// Assuming users want to see dimensions.
const boothArrows = computed(() => {
  if (!isStoreReady.value) return [];

  const arrows: any[] = [];
  store.objects.forEach((obj) => {
    if (obj.type === "booth" && obj.isVisible !== false) {
      // Show arrows for all visible booths in preview mode
      const arrowRegions = getBoothArrowRegions(obj, store.zoom, store.offset);
      arrowRegions.forEach((region) => {
        arrows.push({
          ...region,
          objId: obj.id,
          x: region.x,
          y: region.y,
          width: region.width,
          height: region.height,
        });
      });
    }
  });
  return arrows;
});

const domElements = computed(() => {
  if (!isStoreReady.value) return [];
  return [...store.domElements].sort(
    (a, b) => (a.zIndex ?? 0) - (b.zIndex ?? 0)
  );
});

const pointerEventsNone = "pointer-events-none";

// Canvas setup and rendering
const { render, setupCanvas } = useCanvasEngine(canvasEl);

// Export functions using the ROBUST useCanvasExport
const downloadAsPNG = async () => {
    // Find the main container (or we can pass the containerObj if we have a wrapper)
    // In preview mode, we usually want to export the whole 'floor'.
    // Since useCanvasExport expects a "containerObj" usually (like a floor object),
    // we might need to verify how export works.
    // The previous export logic often exported 'selected object' or 'floor'.
    // Let's shim a "Virtual Floor Object" if needed or use the export function correctly.

    // Actually, useCanvasExport has methods like exportAsPNG(containerObj).
    // If the floor itself isn't an object, we need to wrap it.
    // But wait, the standard export flow in CommonLayerControls usually exports the 'floor' wrapper
    // or the 'selected container'.
    
    // For "Download Design", we likely want to export the *entire content* within the floor bounds.
    // Let's create a virtual container object representing the floor bounds.
    const floorWidth = 2000; // Default or fetch from store if available
    const floorHeight = 1000; // Default
    
    // We can try to use store.floors to get dimensions if available
    const currentFloor = store.floors?.find(f => f.id === store.currentFloorId);
    
    const virtualContainer = {
      id: "preview-export",
      type: "rectangle", // Mimic a container
      points: [],
      x: 0,
      y: 0,
      width: currentFloor?.dimensions?.length || 2000,
      height: currentFloor?.dimensions?.width || 1000,
      isVisible: true,
      isLocked: false,
      rotation: 0,
      opacity: 1,
      zIndex: 0,
      fill: "#ffffff",
      stroke: "transparent"
    };

    // We must ensure the export logic considers this a "container"
    // The export logic calls 'getContainedItems'.
    // If we pass an object that strictly covers the whole area, getContainedItems *should* find everything inside it.
    await canvasExport.exportAsPNG(virtualContainer as any);
};

const downloadAsPDF = async () => {
   const currentFloor = store.floors?.find(f => f.id === store.currentFloorId);
    const virtualContainer = {
      id: "preview-export",
      type: "rectangle",
      x: 0,
      y: 0,
      width: currentFloor?.dimensions?.length || 2000,
      height: currentFloor?.dimensions?.width || 1000,
      isVisible: true,
      isLocked: false,
      rotation: 0,
    };
    await canvasExport.exportAsPDF(virtualContainer as any);
};

// Data Loading Logic
onMounted(async () => {
  // Check for image mode first
  const imagePath = route.query.image as string;
  
  if (imagePath) {
      console.log("📸 Image Preview Mode:", imagePath);
      // We are in Image Preview Mode
      // Need to clear existing canvas setup or just hide it?
      // Best to add a mode flag
      
      // Let's dynamically create an image element and append it, OR update template
      // Since template is static, we should have updated it.
      // But we can't update template easily without re-writing the whole file. 
      // ACTUALLY, I SHOULD HAVE UPDATED THE TEMPLATE TOO.
      // But I can hack it here by checking if I am in image mode.
  }

  const ev = route.query.event as string;
  const tk = route.query.user as string;
  const fl = route.query.floor as string;

  console.log("🚀 PREVIEW MOUNT: Starting load", { ev, tk, fl, imagePath });

  if (imagePath) {
      // Just stop loading, the template will handle it (once I update template below)
      loadingMessage.value = "Loading preview image...";
      
      // Preload image to ensure it works
      const img = new Image();
      img.onload = () => {
          isLoading.value = false;
      };
      img.onerror = () => {
          loadingMessage.value = "Failed to load preview image.";
      };
      img.src = decodeURIComponent(imagePath);
      
      return;
  }

  if (ev && tk && fl) {
     try {
       // Decode token
       const userToken = atob(tk);
       store.token = userToken; 
       
       console.log("🔑 Token set, loading floor data for:", fl);

       // 1. Force API fetch for fresh data
       const floorsApi = useFloorsApi();
       const floorData = await floorsApi.getFloor(fl);
       
       if (floorData) {
         console.log("✅ Floor data fetched:", floorData);
         
         // 2. Hydrate the store manually to be safe
         const normalizedFloor = store.normalizeFloorData(floorData);
         const existingIndex = store.floors.findIndex(f => f.id === fl);
         if (existingIndex !== -1) {
            store.floors[existingIndex] = normalizedFloor;
         } else {
            store.floors.push(normalizedFloor);
         }
         
         store.currentFloorId = fl;

         // 3. Load data into active state
         store.loadFloorData(fl);
         console.log("🎨 Objects in store:", store.objects.length);
         console.log("🖼️ DOM Elements in store:", store.domElements.length);
       } else {
         console.error("❌ Failed to fetch floor data from API");
         loadingMessage.value = "Failed to load floor data.";
       }

     } catch (e) {
       console.error("❌ Preview Load Error:", e);
       loadingMessage.value = "Failed to load floor plan.";
     }
  } else {
     console.warn("⚠️ Missing URL parameters for preview");
     // If fallback to local data is allowed, check store
     if (!store.objects.length) {
        loadingMessage.value = "Invalid preview link.";
     }
  }
  
  // Always finish loading state
  isLoading.value = false;

  await nextTick();

  // Initialize Canvas
  if (canvasEl.value && !imagePath) {
    console.log("🎨 Setting up canvas context...");
    setupCanvas(canvasEl.value);
    
    // Force a center view if objects exist
    if (store.objects.length > 0 || store.domElements.length > 0) {
       // Optional: Auto-fit logic could go here
       // For now, ensure zoom is reasonable
       if (store.zoom < 0.2) store.zoom = 1;
    }

    // Start Render Loop
    console.log("🔄 Starting render loop...");
    const renderLoop = () => {
      render();
      requestAnimationFrame(renderLoop);
    };
    renderLoop();
  } else if (!imagePath) {
    console.error("❌ Canvas element not found!");
  }
});

// Set page meta for layout
definePageMeta({
  layout: "floorplan-blank",
});

// Set page title
useHead({
  title: "Shared Design Preview",
});
</script>

<style scoped>
/* Same styles as Whiteboard.vue */
</style>
