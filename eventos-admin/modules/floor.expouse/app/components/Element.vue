<template>
  <div
    :data-element="id"
    class="absolute cursor-move select-none"
    :class="{
      'cursor-not-allowed': isLocked || isHandToolActive,
      'pointer-events-none': isLocked || isHandToolActive,
    }"
    :style="{
      left: `${screenPosition.x}px`,
      top: `${screenPosition.y}px`,
      width: `${screenSize.width}px`,
      height: `${screenSize.height}px`,
      transform: `rotate(${rotation}deg)`,
      transformOrigin: 'center',
      zIndex: zIndex ?? 0,
      display: isVisible ? 'block' : 'none',
      ...styleProps,
    }"
    @dblclick="startEditing"
    @pointerdown="handlePointerDown"
    @pointermove="handlePointerMove"
    @pointerup="handlePointerUp"
    @keydown="handleKeyDown"
    @mouseenter="isHovered = true"
    @mouseleave="isHovered = false"
  >
    <div
      v-if="type === 'text'"
      class="w-full h-full p-2"
      :style="{
        fontSize: `${styleProps?.fontSize || 24}px`,
        fontFamily: styleProps?.fontFamily || 'Verdana',
        color: styleProps?.color || '#000000',
        lineHeight: styleProps?.lineHeight || 1.2,
        fontWeight: styleProps?.fontWeight || 'normal',
        fontStyle: styleProps?.fontStyle || 'normal',
        textDecoration: styleProps?.textDecoration || 'none',
        textAlign: styleProps?.textAlign || 'left',
        textTransform: styleProps?.textTransform || 'none',
        backgroundColor: styleProps?.backgroundColor || 'transparent',
        textShadow: getTextShadow(),
        letterSpacing: styleProps?.letterSpacing || 'normal',
        wordSpacing: styleProps?.wordSpacing || 'normal',
        opacity: styleProps?.opacity !== undefined ? styleProps.opacity : 1,
      }"
    >
      <div
        v-if="isEditing"
        ref="textRef"
        contenteditable
        class="w-full h-full outline-none overflow-auto flex items-center"
        :class="[
          textClasses,
          isEditing && !hasUserContent ? 'justify-start' : 'justify-center',
        ]"
        @blur="saveEdit"
        @keydown.enter.prevent="saveEdit"
        @input="handleInput"
      ></div>
      <div
        v-else-if="hasUserContent"
        class="w-full h-full flex items-center justify-center"
        :class="textClasses"
      >
        {{ content }}
      </div>
      <div
        v-else
        class="w-full h-full flex items-center justify-center text-gray-500 italic"
        :class="textClasses"
      >
        Double click to edit
      </div>
    </div>
    <NuxtIcon
      v-else-if="type === 'shape' && subtypeIcon"
      :name="subtypeIcon"
      class="w-full h-full"
    />
    <NuxtIcon
      v-else-if="type === 'elements' && subtypeIcon"
      :name="subtypeIcon"
      class="w-full h-full"
    />
    <div
      v-else-if="type === 'booth'"
      class="w-full h-full bg-gray-300 border border-black flex items-center justify-center"
    >
      Booth
    </div>
    <img
      v-else-if="type === 'image'"
      :src="src"
      class="w-full h-full object-contain"
      alt="Dynamic element"
    />
    <svg
      v-else-if="type === 'shape' && subtype === 'arrow' && path"
      class="w-full h-full"
      preserveAspectRatio="none"
    >
      <g>
        <path
          :d="path"
          fill="none"
          :stroke="styleProps.stroke || '#333'"
          :stroke-width="styleProps.strokeWidth || 2"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
        <polygon
          v-if="controlPoints?.length >= 2"
          :points="arrowheadPoints"
          :fill="styleProps.stroke || '#333'"
        />
      </g>
    </svg>
    <div
      v-if="isSelected && !isEditing && isVisible"
      class="absolute inset-0 border-2 border-dashed border-blue-500 pointer-events-none"
    ></div>
    
    <!-- Locked Watermark Badge -->
    <div
      v-if="isLocked && isVisible && isHovered"
      class="absolute -top-6 left-0 pointer-events-none select-none"
    >
      <div 
        class="bg-gray-100/10 text-gray-500/40 text-[clamp(8px,12px,14px)] font-bold uppercase tracking-widest border border-gray-500/20 px-1.5 py-1 rounded flex items-center gap-1.5"
      >
        <img src="/img/icon/lock.svg" class="w-3.5 h-3.5 opacity-40 shrink-0" alt="Lock icon" />
        Locked
      </div>
    </div>

    <template
      v-if="
        isSelected &&
        !isEditing &&
        isVisible &&
        !isLocked &&
        !isHandToolActive &&
        !isMultiSelected
      "
    >
      <div
        v-for="dir in resizeDirections"
        :key="dir"
        class="handle"
        :class="dir"
        @pointerdown.prevent="startResize($event, dir)"
      ></div>
      <div
        class="absolute top-[-25px] left-[50%] w-[1px] h-[20px] border-l border-dashed border-blue-500"
        style="transform: translateX(-0.5px)"
      ></div>
      <div class="rotate-icon" @pointerdown.prevent="startRotate">
        <NuxtIcon name="heroicons:arrow-path" class="w-5 h-5 text-blue-500" />
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import {
  ref,
  computed,
  onMounted,
  onBeforeUnmount,
  watch,
  nextTick,
} from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useOptimizedDragging } from "@floorplan/composables/useOptimizedDragging";
import type { Point, DomElement, Command } from "@floorplan/types/canvas";

const props = defineProps<{
  id: string;
  type: string;
  subtype?: string;
  position: Point;
  size: { width: number; height: number };
  styleProps?: Record<string, any>;
  content?: string;
  src?: string;
  rotation: number;
  path?: string;
  controlPoints?: Point[];
  zIndex?: number;
}>();

const emit = defineEmits<{
  select: [id: string, isMultiSelect: boolean];
  updateGuides: [guides: any[]];
}>();

const canvasStore = useCanvasStore();
const dragging = useOptimizedDragging();

const isEditing = ref(false);
const textRef = ref<HTMLElement | null>(null);
const lastPos = ref<Point>({ x: 0, y: 0 });
const resizeDirection = ref<string | null>(null);
const rotateCenter = ref<Point>({ x: 0, y: 0 });
const startAngle = ref(0);
const initialRotation = ref(0);
const cursorPosition = ref<{ offset: number; relativeX: number } | null>(null);
const isDragging = ref(false);

// Local state for history tracking
const dragStartPositions = new Map<string, Point>();


const arrowKeyMoveStep = 5;

const elementData = computed(() => {
  return canvasStore.domElements.find((el) => el.id === props.id);
});

const isLocked = computed(() => {
  return elementData.value?.isLocked || false;
});

const isVisible = computed(() => {
  return elementData.value?.isVisible !== false;
});

const isHandToolActive = computed(() => {
  return canvasStore.activeTool === "hand";
});

const isSelected = computed(() => {
  const hasDomElementSelection =
    canvasStore.selectedDomElements &&
    Array.isArray(canvasStore.selectedDomElements) &&
    canvasStore.selectedDomElements.includes(props.id);

  return canvasStore.selectedElementId === props.id || hasDomElementSelection;
});

// NEW: Check if this element is part of a multi-selection
const isMultiSelected = computed(() => {
  return (
    canvasStore.selectedDomElements &&
    Array.isArray(canvasStore.selectedDomElements) &&
    canvasStore.selectedDomElements.length > 1 &&
    canvasStore.selectedDomElements.includes(props.id)
  );
});

const isHovered = ref(false);

const screenPosition = computed(() => ({
  x: (props.position.x - canvasStore.offset.x) * canvasStore.zoom,
  y: (props.position.y - canvasStore.offset.y) * canvasStore.zoom,
}));

const screenSize = computed(() => ({
  width: props.size.width * canvasStore.zoom,
  height: props.size.height * canvasStore.zoom,
}));

const textClasses = computed(() => ({
  "text-4xl font-bold": props.subtype === "h1",
  "text-3xl font-bold": props.subtype === "h2",
  "text-2xl font-bold": props.subtype === "h3",
  "text-xl font-bold": props.subtype === "h4",
  "text-lg font-bold": props.subtype === "h5",
  "text-sm font-bold": props.subtype === "h6",
}));

const subtypeIcon = computed(() => {
  const shapeMap: Record<string, string> = {
    diamond: "mdi:diamond",
    pentagon: "mdi:pentagon",
    polygon: "iconoir:triangle",
    hexagon: "mdi:hexagon",
    triangle: "mdi:triangle",
    "shape-cube": "streamline-ultimate:shape-cube",
    cube: "fluent-mdl2:cube-shape",
    "free-shape-cube": "streamline-freehand-color:shape-cube",
    pyramid: "streamline-plump:pyramid-shape",
    square: "fluent-mdl2:square-shape",
    "square-filled": "fluent-mdl2:square-shape-solid",
    sphere: "streamline-sharp:sphere-shape",
    cone: "streamline:cone-shape",
    mountain: "mdi:mountain",
    "outline-cloud": "ic:outline-cloud",
    cloud: "ic:sharp-cloud",
    "flying-bird": "mdi:bird",
    bird: "lucide:bird",
    blackbird: "fluent-emoji-high-contrast:black-bird",
    "waves-birds": "lucide-lab:waves-birds",
    "nest-birds": "game-icons:nest-birds",
    camel: "hugeicons:camel",
    "fish-outline": "ion:fish-outline",
    fish: "ion:fish",
    desert: "uil:desert",
    "hill-fort": "game-icons:hill-fort",
    beach: "streamline:beach",
    shutter: "mdi:window-shutter-settings",
    garden: "guidance:garden",
    "tree-palm": "ph:tree-palm",
    "palm-tree": "fxemoji:palmtree",
    "tree-line": "mingcute:tree-line",
    evergreen: "openmoji:evergreen-tree",
    river: "game-icons:river",
    moon: "line-md:moon-loop",
    building: "mdi:building",
    gate: "guidance:tunnel",
    house: "tdesign:houses-2",
    field: "streamline-ultimate:soccer-field-bold",
    ship: "tabler:ship",
    kite: "hugeicons:kite",
    "kite-surfing": "material-symbols:kitesurfing-rounded",
    star: "heroicons:star",
  };

  const elementsMap: Record<string, string> = {
    registration: "medical-icon:i-registration",
    lounge: "arcticons:lounge",
    conference: "guidance:conference-room",
    meeting: "guidance:meeting-room",
    dining: "material-symbols:dinner-dining-outline",
    cafe: "hugeicons:cafe",
    bar: "carbon:bar",
    restroom: "fa7-solid:restroom",
    malerestroom: "grommet-icons:restroom-men",
    womenrestroom: "grommet-icons:restroom-women",
    water: "mage:water-glass-fill",
    restaurant: "material-symbols:restaurant",
    coatroom: "solar:hanger-bold",
    "round-table": "hugeicons:table-round",
    "rectangle-table": "material-symbols-light:table-large-rounded",
    sofa: "mdi:sofa",
    tree: "icon-park-outline:coconut-tree",
    seat: "mdi:seat",
    "single-door": "tabler:door",
    "double-door": "material-symbols:door-sliding-outline",
    compass: "fontisto:compass-alt",
    "entry-door": "game-icons:entry-door",
    "exit-door": "game-icons:exit-door",
    "sanitizer-station": "material-symbols:sanitizer",
    stairs: "guidance:stairs-up-person",
    handicap: "mage:handicapped",
    escalator: "mdi:escalator-up",
    "fire-extinguisher": "guidance:fire-extinguisher",
    "first-aid": "bxs:first-aid",
    "charging-point": "tabler:charging-pile",
    "emergency-exit": "guidance:emergency-exit",
    elevator: "material-symbols:elevator",
    "restricted-area": "guidance:no-entry-for-pedestrians",
    parking: "iconoir:parking",
    danger: "maki:danger",
  };

  return (
    shapeMap[props.subtype || ""] || elementsMap[props.subtype || ""] || ""
  );
});

const arrowheadPoints = computed(() => {
  if (
    !props.controlPoints ||
    !Array.isArray(props.controlPoints) ||
    props.controlPoints.length < 2
  )
    return "";
  const [secondLast, last] = props.controlPoints.slice(-2);
  const angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
  const arrowLength = 10;
  const p1 = { x: last.x, y: last.y };
  const p2 = {
    x: last.x - arrowLength * Math.cos(angle - Math.PI / 6),
    y: last.y - arrowLength * Math.sin(angle - Math.PI / 6),
  };
  const p3 = {
    x: last.x - arrowLength * Math.cos(angle + Math.PI / 6),
    y: last.y - arrowLength * Math.sin(angle + Math.PI / 6),
  };
  return `${p1.x},${p1.y} ${p2.x},${p2.y} ${p3.x},${p3.y}`;
});

const resizeDirections = [
  "top-left",
  "top",
  "top-right",
  "right",
  "bottom-right",
  "bottom",
  "bottom-left",
  "left",
];

const getTextShadow = () => {
  if (!props.styleProps) return "none";
  const {
    shadowOffsetX = 0,
    shadowOffsetY = 0,
    shadowBlur = 0,
    shadowColor = "#000000",
  } = props.styleProps;
  if (shadowOffsetX === 0 && shadowOffsetY === 0 && shadowBlur === 0)
    return "none";
  return `${shadowOffsetX}px ${shadowOffsetY}px ${shadowBlur}px ${shadowColor}`;
};

const getCanvasRect = () =>
  document.querySelector("canvas")?.getBoundingClientRect() || {
    left: 0,
    top: 0,
  };

const getRelativePosition = (e: PointerEvent) => {
  const rect = getCanvasRect();
  return { x: e.clientX - rect.left, y: e.clientY - rect.top };
};

const screenToWorld = (point: Point) => ({
  x: point.x / canvasStore.zoom + canvasStore.offset.x,
  y: point.y / canvasStore.zoom + canvasStore.offset.y,
});

const hasUserContent = computed(() => {
  return !!props.content;
});

// Arrow key movement with optimized dragging
const handleArrowKeyMove = (key: string) => {
  if (isLocked.value || !isSelected.value || isHandToolActive.value) return;

  let deltaX = 0;
  let deltaY = 0;

  switch (key) {
    case "ArrowLeft":
      deltaX = -arrowKeyMoveStep;
      break;
    case "ArrowUp":
      deltaY = -arrowKeyMoveStep;
      break;
    case "ArrowRight":
      deltaX = arrowKeyMoveStep;
      break;
    case "ArrowDown":
      deltaY = arrowKeyMoveStep;
      break;
  }

  // Use the optimized dragging mechanism (handles multi-select internally)
  if (canvasStore.selectedDomElements.length > 0) {
    // We need to ensure the dragging composable knows about the selected objects
    // Note: moveByKeyboard normally relies on store.selectedObjects or store.selectedDomElements
    // We might need to ensure the composable supports DOM element movement via keyboard
    // The current implementation of useOptimizedDragging.moveByKeyboard focuses on store.selectedObjects
    // We'll manually adapt it if needed, or rely on the store update if the composable supports it.
    
    // Check if useOptimizedDragging supports DOM elements in moveByKeyboard. 
    // Looking at the code: it filters store.selectedObjects. 
    // We need to extend it or handle it here if it doesn't support DOM elements yet.
    // Based on the reviewed code, moveByKeyboard primarily iterated store.selectedObjects.
    // However, let's use the mechanism from useObjectManipulation which handles both.
    
    // Actually, useObjectManipulation implements its own keyboard handler that calls dragging.moveByKeyboard
    // BUT dragging.moveByKeyboard in useOptimizedDragging ONLY iterates selectedObjects. 
    // Changing that file is out of scope unless necessary. 
    // Let's implement the DOM element movement here using the same history/batch logic as useObjectManipulation would.
    
    const elementsToMove = isMultiSelected.value 
      ? canvasStore.selectedDomElements 
      : [props.id];
      
    elementsToMove.forEach((elemId: string) => {
       const elem = canvasStore.domElements.find((e) => e.id === elemId);
       if (elem && !elem.isLocked) {
         elem.position = {
           x: elem.position.x + deltaX,
           y: elem.position.y + deltaY
         };
       }
    });
  }
};

const handleKeyDown = (e: KeyboardEvent) => {
  if (!isSelected.value || isLocked.value || isHandToolActive.value) return;

  // ✅ FIX: Don't handle arrow keys if user is typing
  const target = e.target as HTMLElement;
  const isTyping =
    target.tagName === "INPUT" ||
    target.tagName === "TEXTAREA" ||
    target.isContentEditable ||
    target.closest('[contenteditable="true"]') !== null;

  if (isTyping) return;

  if (["ArrowLeft", "ArrowUp", "ArrowRight", "ArrowDown"].includes(e.key)) {
    e.preventDefault();
    e.stopPropagation();
    handleArrowKeyMove(e.key);
  }
};

const startEditing = () => {
  if (props.type !== "text" || isLocked.value || isHandToolActive.value) return;
  isEditing.value = true;
  nextTick(() => {
    if (textRef.value) {
      textRef.value.textContent = props.content || "";
      textRef.value.focus();
      const range = document.createRange();
      const selection = window.getSelection();
      if (!props.content) {
        range.setStart(textRef.value, 0);
        range.collapse(true);
      } else {
        range.selectNodeContents(textRef.value);
        range.collapse(false);
      }
      selection?.removeAllRanges();
      selection?.addRange(range);
    }
  });
};

const saveEdit = () => {
  if (textRef.value) {
    const newContent = textRef.value.textContent?.trim() || "";
    canvasStore.updateElement(props.id, {
      content: newContent === "Double click to edit" ? "" : newContent,
    });
  }
  isEditing.value = false;
};

const handleInput = () => {
  // Optional: Auto-save on input if desired
};

watch(
  () => canvasStore.selectedElementId,
  (newId) => {
    if (newId !== props.id && isEditing.value) {
      saveEdit();
      isEditing.value = false;
    }
  }
);

const handlePointerDown = (e: PointerEvent) => {
  if (isLocked.value && !(e.ctrlKey || e.metaKey)) return;
  if (canvasStore.currentTool === "hand") {
    e.stopPropagation();
    return;
  }

  if (isLocked.value && !(e.ctrlKey || e.metaKey)) {
    e.preventDefault();
    e.stopPropagation();
    return false;
  }

  if (!isVisible.value) return;

  e.stopPropagation();

  // NEW: Check for Ctrl/Cmd key for multi-select
  const isMultiSelect = e.ctrlKey || e.metaKey;
  emit("select", props.id, isMultiSelect);

  if (isEditing.value) return;

  isDragging.value = true;
  lastPos.value = getRelativePosition(e);
  
  // Initialize drag with useOptimizedDragging
  const worldPoint = screenToWorld(lastPos.value);
  
  // Capture start positions for history
  dragStartPositions.clear();
  const selectedIds = canvasStore.selectedDomElements.length > 0 
    ? canvasStore.selectedDomElements 
    : [props.id];
    
  // Prepare proxy objects for useOptimizedDragging
  const dragObjects = selectedIds.map(id => {
    const el = canvasStore.domElements.find(e => e.id === id);
    if (el) {
      dragStartPositions.set(id, { ...el.position });
      return {
        id: el.id,
        type: 'dom-element', // Identifier for optimized dragging
        isLocked: el.isLocked || false,
        isVisible: el.isVisible !== false,
        elementData: el, // Pass the reactive object directly
        // Add minimal required CanvasObject properties
        points: [],
        rotation: el.rotation || 0
      };
    }
    return null;
  }).filter(Boolean) as any[];

  dragging.startDrag(worldPoint, dragObjects);

  document.addEventListener("pointermove", doMove);
  document.addEventListener("pointerup", stopMove, { once: true });
};

const doMove = (e: PointerEvent) => {
  if (isLocked.value || isHandToolActive.value) {
    stopMove();
    return;
  }

  const relPoint = getRelativePosition(e);
  // We don't calculate delta manually anymore, dragging.updateDrag handles it 
  // based on world coordinates
  lastPos.value = relPoint;
  
  const worldPoint = screenToWorld(relPoint);
  const canvasRect = getCanvasRect();
  
  // Delegate to optimized dragging
  dragging.updateDrag(
    worldPoint,
    canvasRect.width / canvasStore.zoom,
    canvasRect.height / canvasStore.zoom,
    canvasStore.zoom,
    canvasStore.offset,
    () => {
      // Emit the calculated guides
      emit("updateGuides", dragging.localGuides.value);
    }
  );
};

const stopMove = () => {
  document.removeEventListener("pointermove", doMove);
  isDragging.value = false;

  // History tracking logic
  const movedElements: Array<{
    id: string;
    beforePosition: Point;
    afterPosition: Point;
  }> = [];

  dragStartPositions.forEach((startPos, id) => {
    const el = canvasStore.domElements.find(e => e.id === id);
    if (el) {
      const hasMoved = 
        Math.abs(el.position.x - startPos.x) > 0.1 || 
        Math.abs(el.position.y - startPos.y) > 0.1;

      if (hasMoved) {
        movedElements.push({
          id,
          beforePosition: startPos,
          afterPosition: { ...el.position }
        });
      }
    }
  });

  if (movedElements.length > 0) {
    const moveCommand = new (class implements Command {
        private moves = movedElements.map((item) => ({
          id: item.id,
          beforePosition: { ...item.beforePosition },
          afterPosition: { ...item.afterPosition },
        }));

        execute() {
          this.moves.forEach((item) => {
            const el = canvasStore.domElements.find((e) => e.id === item.id);
            if (el) {
              el.position = { ...item.afterPosition };
            }
          });
        }

        undo() {
          this.moves.forEach((item) => {
            const el = canvasStore.domElements.find((e) => e.id === item.id);
            if (el) {
              el.position = { ...item.beforePosition };
            }
          });
        }
      })();

    canvasStore.pushToHistory(
      moveCommand,
      "move",
      "dom",
      `Moved ${movedElements.length} elements`
    );
  }

  dragStartPositions.clear();
  dragging.stopDrag();

  setTimeout(() => {
    emit("updateGuides", []);
  }, 300);
};

const startResize = (e: PointerEvent, direction: string) => {
  if (isLocked.value || isHandToolActive.value) {
    e.preventDefault();
    e.stopPropagation();
    return false;
  }

  e.stopPropagation();
  resizeDirection.value = direction;
  lastPos.value = { x: e.clientX, y: e.clientY };

  if (isEditing.value && textRef.value && props.type === "text") {
    const selection = window.getSelection();
    if (selection?.rangeCount) {
      const range = selection.getRangeAt(0);
      const cursorOffset = range.startOffset;
      const rect = textRef.value.getBoundingClientRect();
      const relativeX = !props.content
        ? 0
        : (e.clientX - rect.left) / rect.width;
      cursorPosition.value = { offset: cursorOffset, relativeX };
    }
  }

  document.addEventListener("pointermove", doResize);
  document.addEventListener("pointerup", stopResize, { once: true });
};

const doResize = (e: PointerEvent) => {
  if (isLocked.value || isHandToolActive.value) {
    stopResize();
    return;
  }

  const deltaX = (e.clientX - lastPos.value.x) / canvasStore.zoom;
  const deltaY = (e.clientY - lastPos.value.y) / canvasStore.zoom;
  lastPos.value = { x: e.clientX, y: e.clientY };

  let newX = props.position.x;
  let newY = props.position.y;
  let newWidth = props.size.width;
  let newHeight = props.size.height;

  switch (resizeDirection.value) {
    case "top-left":
      newX += deltaX;
      newY += deltaY;
      newWidth -= deltaX;
      newHeight -= deltaY;
      break;
    case "top":
      newY += deltaY;
      newHeight -= deltaY;
      break;
    case "top-right":
      newY += deltaY;
      newHeight -= deltaY;
      newWidth += deltaX;
      break;
    case "right":
      newWidth += deltaX;
      break;
    case "bottom-right":
      newWidth += deltaX;
      newHeight += deltaY;
      break;
    case "bottom":
      newHeight += deltaY;
      break;
    case "bottom-left":
      newX += deltaX;
      newWidth -= deltaX;
      newHeight += deltaY;
      break;
    case "left":
      newX += deltaX;
      newWidth -= deltaX;
      break;
  }

  if (newWidth > 10 && newHeight > 10) {
    canvasStore.updateElement(props.id, {
      position: { x: newX, y: newY },
      size: { width: newWidth, height: newHeight },
    });
    
    // NEW: Calculate and show resize alignment guides
    if (elementData.value) {
      // Create a proxy object with the NEW dimensions for guide calculation
      const proxyObj = {
        id: props.id,
        type: 'dom-element',
        isLocked: false,
        isVisible: true,
        // We must pass the updated elementData or equivalent structure
        elementData: {
          position: { x: newX, y: newY },
          size: { width: newWidth, height: newHeight }
        },
        points: []
      } as any;
      
      const canvasRect = getCanvasRect();
      dragging.calculateResizeGuides(
        proxyObj,
        canvasRect.width / canvasStore.zoom,
        canvasRect.height / canvasStore.zoom,
        canvasStore.zoom,
        canvasStore.offset
      );
      
      emit("updateGuides", dragging.localGuides.value);
    }

    if (
      isEditing.value &&
      textRef.value &&
      props.type === "text" &&
      cursorPosition.value
    ) {
      nextTick(() => {
        const selection = window.getSelection();
        const range = document.createRange();
        if (!props.content) {
          range.setStart(textRef.value, 0);
          range.collapse(true);
        } else {
          range.selectNodeContents(textRef.value);
          range.collapse(false);
        }
        textRef.value.focus();
        selection?.removeAllRanges();
        selection?.addRange(range);
      });
    }
  }
};

const stopResize = () => {
  document.removeEventListener("pointermove", doResize);
  resizeDirection.value = null;
  cursorPosition.value = null;
  
  dragging.clearGuides();
  emit("updateGuides", []);
};

const startRotate = (e: PointerEvent) => {
  if (isLocked.value || isHandToolActive.value) {
    e.preventDefault();
    e.stopPropagation();
    return false;
  }

  e.stopPropagation();
  const centerX = props.position.x + props.size.width / 2;
  const centerY = props.position.y + props.size.height / 2;
  rotateCenter.value = { x: centerX, y: centerY };
  const relPoint = getRelativePosition(e);
  const worldPoint = screenToWorld(relPoint);
  startAngle.value =
    Math.atan2(worldPoint.y - centerY, worldPoint.x - centerX) *
    (180 / Math.PI);
  initialRotation.value = props.rotation;
  document.addEventListener("pointermove", doRotate);
  document.addEventListener("pointerup", stopRotate, { once: true });
};

const doRotate = (e: PointerEvent) => {
  if (isLocked.value || isHandToolActive.value) {
    stopRotate();
    return;
  }

  const relPoint = getRelativePosition(e);
  const worldPoint = screenToWorld(relPoint);
  const angle =
    Math.atan2(
      worldPoint.y - rotateCenter.value.y,
      worldPoint.x - rotateCenter.value.x
    ) *
    (180 / Math.PI);
  canvasStore.updateElement(props.id, {
    rotation: (initialRotation.value + (angle - startAngle.value) + 360) % 360,
  });
};

const stopRotate = () => document.removeEventListener("pointermove", doRotate);

onMounted(() => {
  if (!canvasStore.selectedDomElements) {
    console.warn("selectedDomElements not initialized in canvasStore");
  }
  window.addEventListener("keydown", handleKeyDown);
});

onBeforeUnmount(() => {
  window.removeEventListener("keydown", handleKeyDown);
});
</script>

<style scoped>
@reference "tailwindcss";
.handle {
  @apply absolute w-2.5 h-2.5 bg-white border-2 border-blue-500 rounded-full z-10;
}
.top-left {
  top: -5px;
  left: -5px;
  @apply cursor-nwse-resize;
}
.top {
  top: -5px;
  @apply left-1/2 -translate-x-1/2 cursor-ns-resize;
}
.top-right {
  top: -5px;
  right: -5px;
  @apply cursor-nesw-resize;
}
.right {
  @apply top-1/2 -translate-y-1/2 cursor-ew-resize;
  right: -5px;
}
.bottom-right {
  bottom: -5px;
  right: -5px;
  @apply cursor-nwse-resize;
}
.bottom {
  bottom: -5px;
  @apply left-1/2 -translate-x-1/2 cursor-ns-resize;
}
.bottom-left {
  bottom: -5px;
  left: -5px;
  @apply cursor-nesw-resize;
}
.left {
  @apply top-1/2 -translate-y-1/2 cursor-ew-resize;
  left: -5px;
}
.rotate-icon {
  top: -30px;
  @apply absolute left-1/2 -translate-x-1/2 cursor-grab z-10;
}
</style>
