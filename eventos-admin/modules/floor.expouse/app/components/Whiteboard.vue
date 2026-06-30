<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useCanvasEngine } from "@floorplan/composables/useCanvasEngine";
import { useCanvasObjects } from "@floorplan/composables/useCanvasObjects";
import { useBoothArrows } from "@floorplan/composables/useBoothArrows";
import { useKeyboardMovement } from "@floorplan/composables/useKeyboardMovement";
import type { Point } from "@floorplan/types/canvas";
import type { ElementAlignmentGuide } from "@floorplan/composables/useElementAlignment";

const store = useCanvasStore();
const canvasObjects = useCanvasObjects();
const { getBoothArrowRegions } = useBoothArrows();
const { moveStep, isMoving } = useKeyboardMovement();
const canvasEl = ref<HTMLCanvasElement>();

const elementAlignmentGuides = ref<ElementAlignmentGuide[]>([]);

// Track if we just clicked on a canvas object
const justClickedCanvasObject = ref(false);

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

const canvasClasses = computed(() => {
  if (!isStoreReady.value) return "cursor-default";

  return {
    "cursor-crosshair": [
      "pencil",
      "line",
      "arrow",
      "curve-arrow",
      "rectangle",
      "ellipse",
      "wall",
      "booth",
    ].includes(store.currentTool),
    "cursor-grab": store.currentTool === "hand" && !isPanning.value,
    "cursor-grabbing": store.currentTool === "hand" && isPanning.value,
    "cursor-default": store.currentTool === "select",
  };
});

const boothArrows = computed(() => {
  if (!isStoreReady.value) return [];

  const arrows: any[] = [];
  store.objects.forEach((obj) => {
    if (obj.type === "booth" && obj.isHovered) {
      if (obj.isLocked || obj.isVisible === false) return;

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

const rotationHandles = computed(() => {
  if (!isStoreReady.value) return [];

  if (store.selectedObjects.length !== 1) return [];

  return store.selectedObjects
    .filter((obj) =>
      [
        "wall",
        "line",
        "arrow",
        "curve-arrow",
        "rectangle",
        "ellipse",
        "booth",
      ].includes(obj.type)
    )
    .filter((obj) => !obj.isLocked && obj.isVisible !== false)
    .map((obj) => {
      const bounds = canvasObjects.getObjectBounding(obj);
      if (!bounds) return null;

      const center = canvasObjects.getCenter(obj);
      const rotation = obj.rotation || 0;

      const rotationHandleDistance = 40;

      const unrotatedHandle = {
        x: bounds.x + bounds.width / 2,
        y: bounds.y - rotationHandleDistance,
      };

      const rotatedHandle = canvasObjects.rotatePoint(
        unrotatedHandle,
        center,
        rotation
      );

      const screenX = (rotatedHandle.x - store.offset.x) * store.zoom;
      const screenY = (rotatedHandle.y - store.offset.y) * store.zoom;

      return {
        x: screenX,
        y: screenY,
        objId: obj.id,
        isLocked: obj.isLocked,
        isVisible: obj.isVisible !== false,
      };
    })
    .filter((h) => h !== null);
});

const zoomDisplay = computed(() => {
  if (!isStoreReady.value) return "100%";
  return `${Math.round(store.zoom * 100)}%`;
});

const handleMouseDown = (event: MouseEvent) => {
  if (!canvasEl.value || !isStoreReady.value) return;

  // ✅ NEW: Check if we're clicking on a canvas object
  const rect = canvasEl.value.getBoundingClientRect();
  const point = {
    x: event.clientX - rect.left,
    y: event.clientY - rect.top,
  };

  const worldPoint = {
    x: point.x / store.zoom + store.offset.x,
    y: point.y / store.zoom + store.offset.y,
  };

  // Check if clicking on a canvas object
  const clickedObject = store.objects
    .slice()
    .reverse()
    .find((obj) => {
      if (obj.isLocked || obj.isVisible === false) return false;
      return isPointInObject(worldPoint, obj);
    });

  if (clickedObject && store.currentTool === "select") {
    justClickedCanvasObject.value = true;
    // If there's a DOM element selected, clear it immediately
    if (store.selectedElementId) {
      store.selectedElementId = null;
      store.selectedDomElements = [];
    }
  } else {
    justClickedCanvasObject.value = false;
  }

  engineMouseDown(event);
};

const handleMouseMove = (event: MouseEvent) => {
  if (!canvasEl.value || !isStoreReady.value) return;
  engineMouseMove(event);
};

const handleDoubleClick = (event: MouseEvent) => {
  if (!canvasEl.value || !isStoreReady.value) return;
  engineDoubleClick(event);
};

const {
  handleDoubleClick: engineDoubleClick,
  handleMouseDown: engineMouseDown,
  handleMouseMove: engineMouseMove,
  handleMouseUp,
  handleWheel,
  handleKeyDown,
  drawingState,
  isPanning,
  setupCanvas,
  resizeCanvas,
  interactionState,
  isPointInPolygon,
  finishLabelEdit,
  render,
} = useCanvasEngine(canvasEl);

const labelInputRef = ref<HTMLInputElement | null>(null);

watch(
  () => drawingState.value.labelEditing.objId,
  (newId) => {
    if (newId) {
      nextTick(() => {
        if (labelInputRef.value) {
          labelInputRef.value.focus();
          labelInputRef.value.select();
        }
      });
    }
  }
);

// ✅ Helper function to check if point is in object
const isPointInObject = (point: Point, obj: any): boolean => {
  const tolerance = 10 / store.zoom;

  if (obj.points && obj.points.length >= 2) {
    const xs = obj.points.map((p: Point) => p.x);
    const ys = obj.points.map((p: Point) => p.y);
    const minX = Math.min(...xs);
    const maxX = Math.max(...xs);
    const minY = Math.min(...ys);
    const maxY = Math.max(...ys);

    return (
      point.x >= minX - tolerance &&
      point.x <= maxX + tolerance &&
      point.y >= minY - tolerance &&
      point.y <= maxY + tolerance
    );
  }

  if (obj.boundingBox) {
    const bbox = obj.boundingBox;
    return (
      point.x >= bbox.x - tolerance &&
      point.x <= bbox.x + bbox.width + tolerance &&
      point.y >= bbox.y - tolerance &&
      point.y <= bbox.y + bbox.height + tolerance
    );
  }

  return false;
};

const handleGuidesUpdate = (guides: ElementAlignmentGuide[]) => {
  elementAlignmentGuides.value = guides;
};

// ✅ UPDATED: Better element selection with multi-select support
const handleElementSelect = (id: string, isMultiSelect: boolean = false) => {
  if (!isStoreReady.value) return;

  // Clear canvas object selections
  store.selectedObjects.forEach((obj) => {
    obj.isSelected = false;
  });
  store.selectedObjects = [];

  if (isMultiSelect) {
    // Multi-select mode
    if (!store.selectedDomElements) {
      store.selectedDomElements = [];
    }

    const index = store.selectedDomElements.indexOf(id);
    if (index > -1) {
      // Deselect if already selected
      store.selectedDomElements.splice(index, 1);
      if (store.selectedDomElements.length === 0) {
        store.selectedElementId = null;
      } else {
        store.selectedElementId =
          store.selectedDomElements[store.selectedDomElements.length - 1];
      }
    } else {
      // Add to selection
      store.selectedDomElements.push(id);
      store.selectedElementId = id;
    }
  } else {
    // Single select mode
    store.selectedElementId = id;
    store.selectedDomElements = [id];
  }
};

const handleWindowResize = () => {
  if (!isStoreReady.value || !store.currentFloorId) return;
  render();
};

const handleArrowClick = (
  event: MouseEvent,
  objId: string,
  direction: "top" | "right" | "bottom" | "left"
) => {
  event.stopPropagation();

  if (!isStoreReady.value || store.currentTool === "hand") return;

  const booth = store.objects.find((o) => o.id === objId && o.type === "booth");
  if (!booth) {
    console.warn(`Booth with ID ${objId} not found`);
    return;
  }

  if (booth.isLocked) {
    console.warn(`Cannot duplicate locked booth: ${objId}`);
    return;
  }

  const { duplicateBooth } = useBoothArrows();
  duplicateBooth(booth, direction);
  store.save();
};

const startRotationFromHTML = (event: MouseEvent, objId: string) => {
  event.preventDefault();
  event.stopPropagation();

  if (!isStoreReady.value || store.currentTool === "hand") return;

  const obj = store.selectedObjects.find((o) => o.id === objId);
  if (!obj || !canvasEl.value) return;

  const rect = canvasEl.value.getBoundingClientRect();
  const point = {
    x: (event.clientX - rect.left) / store.zoom + store.offset.x,
    y: (event.clientY - rect.top) / store.zoom + store.offset.y,
  };

  const bounds = canvasObjects.getObjectBounding(obj);
  if (bounds && store.selectedObjects.length === 1) {
    const centerX = bounds.x + bounds.width / 2;
    const centerY = bounds.y + bounds.height / 2;
    const dx = point.x - centerX;
    const dy = point.y - centerY;
    const startAngle = Math.atan2(dy, dx);

    interactionState.value.isRotating = true;
    interactionState.value.dragStartPoint = point;
    interactionState.value.currentCursor = "grabbing";
    interactionState.value.rotationCenter = { x: centerX, y: centerY };
    interactionState.value.rotationStartAngle = startAngle;
    interactionState.value.initialRotation = obj.rotation || 0;
    interactionState.value.originalObjects = store.selectedObjects.map((o) => ({
      ...o,
      points: o.points.map((p) => ({ ...p })),
    }));
  }
};

const handleGlobalMouseMove = (event: MouseEvent) => {
  if (interactionState.value.isRotating) engineMouseMove(event);
};

const handleGlobalMouseUp = () => {
  if (interactionState.value.isRotating)
    interactionState.value.isRotating = false;
};

const toolInfo = computed(() => {
  if (!isStoreReady.value) return null;

  if (store.currentTool === "curve-arrow") {
    return {
      isDrawing: drawingState?.curveArrowState?.isDrawing ?? false,
      points: drawingState?.curveArrowState?.points?.length ?? 0,
      instruction: drawingState?.curveArrowState?.isDrawing
        ? `Points: ${drawingState.curveArrowState.points.length} • Click to add • Double-click to finish • ESC to cancel`
        : "Click to start curve arrow",
    };
  } else if (store.currentTool === "wall") {
    return {
      isDrawing: drawingState?.wallState?.isDrawing ?? false,
      points: drawingState?.wallState?.points?.length ?? 0,
      instruction: drawingState?.wallState?.isDrawing
        ? `Points: ${drawingState.wallState.points.length} • Click to add • Double-click to finish • ESC to cancel`
        : "Click to start wall",
    };
  } else if (store.currentTool === "booth") {
    return { instruction: "Click booth tool to add booths" };
  }
  return null;
});

// ✅ UPDATED: Improved canvas click handler with Floor Selection
const handleCanvasClick = (event: MouseEvent) => {
  if (!isStoreReady.value) return;

  const target = event.target as HTMLElement;
  const isCanvas = target.closest("canvas") !== null;
  const isDomElement = target.closest("[data-element]") !== null;

  // ✅ FIX: Don't do anything if we just clicked a canvas object (handled in mousedown)
  if (justClickedCanvasObject.value) {
    justClickedCanvasObject.value = false;
    return;
  }

  // Handle clicks on empty canvas area
  if (isCanvas && !isDomElement && store.currentTool === 'select') {
    const rect = (event.target as HTMLCanvasElement).getBoundingClientRect();
    const point = {
      x: event.clientX - rect.left,
      y: event.clientY - rect.top,
    };

    const worldPoint = {
      x: point.x / store.zoom + store.offset.x,
      y: point.y / store.zoom + store.offset.y,
    };

    // 2. Check if we clicked on the Floor Area
    if (store.currentFloorId) {
        const floor = store.floors.find(f => f.id === store.currentFloorId);
        if (floor && floor.floorArea) {
            const { x, y, width, height } = floor.floorArea;
            // Simple AABB check for floor area
            const isInsideFloor = 
                worldPoint.x >= x && 
                worldPoint.x <= x + width && 
                worldPoint.y >= y && 
                worldPoint.y <= y + height;
                
            if (isInsideFloor) {
                // Find ALL floor wall objects
                const floorWalls = store.objects.filter(
                    obj => (obj.type === 'wall') && 
                           (obj.floorId === floor.id || obj.id.includes('Floor-'))
                );
                
                if (floorWalls.length > 0) {
                    // Check if floor is effectively locked (check first wall)
                    const isLocked = floorWalls.some(w => w.isLocked);
                    if (isLocked) {
                        return; 
                    }

                    console.log(`📍 Selected Floor via blank area click (${floorWalls.length} walls)`);
                    
                    // Clear other selections
                    store.selectedDomElements = [];
                    store.selectedElementId = null;
                    store.selectedObjects.forEach(o => o.isSelected = false);
                    
                    // Select ALL Floor Walls
                    floorWalls.forEach(w => w.isSelected = true);
                    store.selectedObjects = [...floorWalls];
                    return;
                }
            }
        }
    }

    // 3. Keep existing behavior: Clear DOM element selection if nothing hit
    const domTypes = ["elements", "text", "shape"];
    const selectedDom = store.domElements.find(
      (el) => store.selectedElementId === el.id && domTypes.includes(el.type)
    );

    if (selectedDom) {
        store.selectedElementId = null;
        store.selectedDomElements = [];
    }
  }
};

onMounted(() => {
  if (canvasEl.value) {
    setupCanvas(canvasEl.value);
    canvasEl.value.focus();
    render();
  }
  window.addEventListener("resize", resizeCanvas);
  window.addEventListener("resize", handleWindowResize);
  window.addEventListener("mousemove", handleGlobalMouseMove);
  window.addEventListener("mouseup", handleGlobalMouseUp);

  const renderLoop = () => {
    render();
  };
  renderLoop();
});

onUnmounted(() => {
  window.removeEventListener("resize", resizeCanvas);
  window.removeEventListener("resize", handleWindowResize);
  window.removeEventListener("mousemove", handleGlobalMouseMove);
  window.removeEventListener("mouseup", handleGlobalMouseUp);
});
</script>

<template>
  <div
    class="w-full h-full bg-white relative overflow-hidden"
    @click="handleCanvasClick"
  >
    <canvas
      ref="canvasEl"
      class="absolute inset-0"
      :class="canvasClasses"
      @mousedown="handleMouseDown"
      @mousemove="handleMouseMove"
      @mouseup="handleMouseUp"
      @dblclick="handleDoubleClick"
      @wheel="handleWheel"
      tabindex="0"
    />

    <!-- DOM Elements Layer -->
    <div
      class="absolute inset-0"
      :class="
        isStoreReady && store.currentTool === 'hand'
          ? 'pointer-events-none'
          : 'pointer-events-none'
      "
    >
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
        @select="handleElementSelect"
        @update-guides="handleGuidesUpdate"
        :class="
          isStoreReady && store.currentTool === 'hand'
            ? 'pointer-events-none'
            : 'pointer-events-auto'
        "
      />
    </div>

    <!-- Alignment Guides Overlay for DOM Elements -->
    <AlignmentGuidesOverlay :guides="elementAlignmentGuides" />

    <!-- Rotation Handles -->
    <div
      v-for="(handle, index) in rotationHandles"
      :key="`rotation-${index}`"
      class="absolute w-6 h-6 flex items-center justify-center z-10"
      :class="{
        'cursor-grab hover:scale-110 hover:shadow-lg':
          isStoreReady && store.currentTool !== 'hand',
        'pointer-events-none cursor-grab':
          isStoreReady && store.currentTool === 'hand',
        'cursor-not-allowed opacity-50': handle.isLocked,
        hidden: !handle.isVisible,
      }"
      :style="{ left: `${handle.x - 12}px`, top: `${handle.y - 25}px` }"
      @mousedown="startRotationFromHTML($event, handle.objId)"
    ></div>

    <!-- Booth Arrows -->
    <div
      v-for="(arrow, index) in boothArrows"
      :key="`arrow-${index}`"
      class="absolute flex items-center justify-center z-50"
      :class="
        isStoreReady && store.currentTool === 'hand'
          ? 'pointer-events-none'
          : 'cursor-pointer hover:scale-110'
      "
      :style="{
        left: `${arrow.x}px`,
        top: `${arrow.y}px`,
        width: `${arrow.width}px`,
        height: `${arrow.height}px`,
      }"
      @mousedown="handleArrowClick($event, arrow.objId, arrow.direction)"
    />

    <!-- Label Editor Overlay (In-place editing for Frames/Sections) -->
    <div
      v-if="drawingState.labelEditing.objId"
      class="absolute z-[100] transition-all duration-75"
      :style="{
        left: `${drawingState.labelEditing.screenPos.x}px`,
        top: `${drawingState.labelEditing.screenPos.y}px`,
      }"
    >
      <input
        ref="labelInputRef"
        v-model="drawingState.labelEditing.text"
        class="px-2 py-1 bg-white border-2 border-blue-600 rounded-md shadow-xl outline-none font-bold text-gray-900 min-w-[80px]"
        :style="{
          fontSize: `${Math.max(12, 14 * store.zoom)}px`,
          transform: 'translateY(-50%)'
        }"
        @blur="finishLabelEdit(drawingState.labelEditing.text)"
        @keydown.enter.stop="finishLabelEdit(drawingState.labelEditing.text)"
        @keydown.esc.stop="drawingState.labelEditing.objId = null"
        @mousedown.stop
      />
    </div>
  </div>
</template>
