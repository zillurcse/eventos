// useCanvasEngine.ts - With Proper Alignment Guide Integration
import { onMounted, onUnmounted, ref, computed, watchEffect } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useUiStore } from "@floorplan/stores/uiStore";
import type { CanvasObject, Point } from "@floorplan/types/canvas";
import { useObjectManipulation } from "@floorplan/composables/useObjectManipulation";
import { useCanvasObjects } from "@floorplan/composables/useCanvasObjects";
import { useCanvasRendering } from "@floorplan/composables/useCanvasRendering";
import { useBoothArrows } from "@floorplan/composables/useBoothArrows";
import { useDrawGrid } from "@floorplan/composables/engine/drawing/useDrawGrid";
import { useWallPreview } from "@floorplan/composables/canvas/useWallPreview";
import { useSelection } from "@floorplan/composables/canvas/useSelection";
import { useThrottledRendering } from "@floorplan/composables/useThrottledRendering";
import { useTwoHeadedArrowLinePreview } from "@floorplan/composables/canvas/useTwoHeadedArrowLinePreview";
import { useOptimizedGuideRenderer } from "@floorplan/composables/useOptimizedGuideRenderer";
import { useSelectionManager } from "@floorplan/composables/useSelectionManager";
import { useFrame } from "@floorplan/composables/canvas/useFrame";
import { useSection } from "@floorplan/composables/canvas/useSection";

const debounce = <T extends (...args: any[]) => any>(
  func: T,
  wait: number,
): ((...args: Parameters<T>) => void) => {
  let timeout: NodeJS.Timeout | null = null;

  return (...args: Parameters<T>) => {
    if (timeout) {
      clearTimeout(timeout);
    }

    timeout = setTimeout(() => {
      func(...args);
    }, wait);
  };
};

const isPointInPolygon = (point: Point, polygon: Point[]): boolean => {
  if (polygon.length < 3) return false;

  let inside = false;
  for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
    const xi = polygon[i].x,
      yi = polygon[i].y;
    const xj = polygon[j].x,
      yj = polygon[j].y;

    const intersect =
      yi > point.y !== yj > point.y &&
      point.x < ((xj - xi) * (point.y - yi)) / (yj - yi) + xi;
    if (intersect) inside = !inside;
  }
  return inside;
};

export function useCanvasEngine(canvasEl: Ref<HTMLCanvasElement | undefined>) {
  const keyDebounce = ref<Set<string>>(new Set());
  const store = useCanvasStore();
  const uiStore = useUiStore();
  const { getArrowAtPoint } = useBoothArrows();
  const objectManipulation = useObjectManipulation();
  const canvasObjects = useCanvasObjects();
  const canvasRendering = useCanvasRendering();
  const { drawGrid } = useDrawGrid();
  const { renderWallPreview } = useWallPreview();
  const { renderSelection } = useSelection();
  const { renderTwoHeadedArrowLinePreview } = useTwoHeadedArrowLinePreview();
  const { requestThrottledRender } = useThrottledRendering();

  // Initialize the optimized guide renderer
  const optimizedGuideRenderer = useOptimizedGuideRenderer();

  const selectionManager = useSelectionManager();
  const { getLabelBounds: getFrameLabelBounds } = useFrame();
  const { getLabelBounds: getSectionLabelBounds } = useSection();

  // Canvas references
  const canvasRef = ref<HTMLCanvasElement | null>(null);
  const ctx = ref<CanvasRenderingContext2D | null>(null);

  // NEW: Clipboard refs (local to engine since store modifications are restricted)
  const clipboardObjects = ref<CanvasObject[]>([]);
  const clipboardDomElements = ref<any[]>([]);

  // NEW: Spacebar handling
  const isSpacePressed = ref(false);
  const previousTool = ref<string>("");

  const isSingleCopyMode = ref(true);

  const continuousDrawingState = ref({
    wallStateActive: false,
    lineStateActive: false,
  });

  // Drawing state
  const drawingState = ref({
    isDrawing: false,
    isSelecting: false,
    startPoint: { x: 0, y: 0 } as Point,
    currentPoint: { x: 0, y: 0 } as Point,
    points: [] as Point[],
    tempObject: null as CanvasObject | null,
    // NEW: In-place label editing state
    labelEditing: {
      objId: null as string | null,
      text: "",
      screenPos: { x: 0, y: 0 } as Point,
      type: "" as string,
    },

    curveArrowState: {
      isDrawing: false,
      points: [] as Point[],
      currentPreviewPoint: null as Point | null,
    },
    twoHeadedArrowState: {
      isDrawing: false,
      points: [] as Point[],
      currentPreviewPoint: null as Point | null,
    },
    wallState: {
      isDrawing: false,
      points: [] as Point[],
      currentPreviewPoint: null as Point | null,
    },
    selectionStart: null as Point | null,
    selectionEnd: null as Point | null,
    activeObjectId: null as string | null,
    resizing: false,
    resizeDirection: null as string | null,
    lastPos: { x: 0, y: 0 } as Point,
    moving: false,
    rotating: false,
    startAngle: 0,
    rotateCenter: { x: 0, y: 0 } as Point,
    initialRotation: 0,
    isPerfectSquare: false,
    isPerfectCircle: false,
    // ✅ NEW: Track pending drawing state
    isDrawingPending: false,
  });

  // In useCanvasEngine.ts, add these refs after the drawingState
  const hoverState = ref({
    hoveredBoothId: null as string | null,
    mousePosition: { x: 0, y: 0 } as Point,
  });

  const isPanning = ref(false);
  const lastMousePos = ref<Point>({ x: 0, y: 0 });
  const isMac = navigator.userAgent.includes("Mac");

  const isStoreReady = computed(() => {
    return store && typeof store.currentTool !== "undefined";
  });

  // NEW: Deep copy helper
  const deepCopy = (obj: any) => JSON.parse(JSON.stringify(obj));

  const generateUniqueBoothNumber = (
    store: any,
    originalNumber: string,
    additionalExcludes: string[] = [],
  ): string => {
    console.log(`🔢 [Engine] Generating unique from: "${originalNumber}"`);

    const isTaken = (candidate: string) => {
      const lowerCandidate = candidate.toLowerCase();
      return (
        additionalExcludes.some((n) => n.toLowerCase() === lowerCandidate) ||
        store.objects.find(
          (obj: CanvasObject) =>
            obj.type === "booth" &&
            obj.boothNumber?.toLowerCase() === lowerCandidate,
        )
      );
    };

    // Pattern 1: Hyphenated with number at end "B-1" → "B-2", "B-1-1" → "B-1-2"
    const hyphenPattern = /^(.+)-(\d+)$/;
    const hyphenMatch = originalNumber.match(hyphenPattern);

    if (hyphenMatch) {
      const prefix = hyphenMatch[1];
      let baseNumber = parseInt(hyphenMatch[2]);
      let counter = baseNumber + 1;
      let candidate = `${prefix}-${counter}`;

      while (isTaken(candidate)) {
        counter++;
        candidate = `${prefix}-${counter}`;
      }

      console.log(
        `✅ [Engine] Generated: "${originalNumber}" → "${candidate}"`,
      );
      return candidate;
    }

    // Pattern 2: Letter followed by number "A101" → "A102", "Booth5" → "Booth6"
    const letterNumberPattern = /^([A-Za-z]*)(\d+)$/;
    const letterMatch = originalNumber.match(letterNumberPattern);

    if (letterMatch) {
      const prefix = letterMatch[1];
      let baseNumber = parseInt(letterMatch[2]);
      let counter = baseNumber + 1;
      let candidate = `${prefix}${counter}`;

      while (isTaken(candidate)) {
        counter++;
        candidate = `${prefix}${counter}`;
      }

      console.log(
        `✅ [Engine] Generated: "${originalNumber}" → "${candidate}"`,
      );
      return candidate;
    }

    // Pattern 3: Plain text without numbers "Booth" → "Booth-1", "MainBooth" → "MainBooth-1"
    let counter = 1;
    let candidate = `${originalNumber}-${counter}`;

    while (isTaken(candidate)) {
      counter++;
      candidate = `${originalNumber}-${counter}`;
    }

    console.log(`✅ [Engine] Generated: "${originalNumber}" → "${candidate}"`);
    return candidate;
  };

  // NEW: Copy selected
  const copySelected = () => {
    if (
      store.selectedObjects.length === 0 &&
      store.selectedDomElements.length === 0
    ) {
      console.log("Nothing selected to copy");
      return;
    }

    // 🔥 If single copy mode is ON, only copy the LAST selected item
    if (isSingleCopyMode.value) {
      if (store.selectedObjects.length > 0) {
        // Copy only the last selected canvas object
        const lastObject =
          store.selectedObjects[store.selectedObjects.length - 1];
        clipboardObjects.value = [deepCopy(lastObject)];
        clipboardDomElements.value = [];
      } else if (store.selectedDomElements.length > 0) {
        // Copy only the last selected DOM element
        const lastElement =
          store.selectedDomElements[store.selectedDomElements.length - 1];
        clipboardDomElements.value = [deepCopy(lastElement)];
        clipboardObjects.value = [];
      }
    } else {
      // Multi-copy mode (original behavior)
      clipboardObjects.value = store.selectedObjects.map(deepCopy);
      clipboardDomElements.value = store.selectedDomElements.map(deepCopy);
    }

    console.log(
      `📋 Copied ${clipboardObjects.value.length} objects and ${clipboardDomElements.value.length} elements`,
    );
  };

  // In useCanvasEngine.ts, update the pasteCopied function:

  // Replace the pasteCopied function in useCanvasEngine.ts with this fixed version:

  const pasteCopied = () => {
    // Prevent multiple executions
    if (keyDebounce.value.has("paste")) return;
    keyDebounce.value.add("paste");

    setTimeout(() => {
      keyDebounce.value.delete("paste");
    }, 100);

    if (
      clipboardObjects.value.length === 0 &&
      clipboardDomElements.value.length === 0
    ) {
      console.log("Clipboard is empty");
      return;
    }

    // 🔥 ALWAYS paste ONLY ONE item at a time
    let objectsToPaste: CanvasObject[] = [];
    let elementsToPaste: any[] = [];

    if (clipboardObjects.value.length > 0) {
      const lastObject =
        clipboardObjects.value[clipboardObjects.value.length - 1];
      objectsToPaste = [deepCopy(lastObject)];
    } else if (clipboardDomElements.value.length > 0) {
      const lastElement =
        clipboardDomElements.value[clipboardDomElements.value.length - 1];
      elementsToPaste = [deepCopy(lastElement)];
    }

    const offset = 10;
    const newObjects: CanvasObject[] = [];
    const newElements: any[] = [];

    // Process SINGLE canvas object
    objectsToPaste.forEach((obj: CanvasObject) => {
      const newObj = deepCopy(obj);
      newObj.id = `paste-${Date.now()}-${Math.random()
        .toString(36)
        .slice(2, 9)}`;
      // ✅ Don't set isSelected here - will be set in execute()

      if (newObj.points) {
        newObj.points = newObj.points.map((p: Point) => ({
          x: p.x + offset,
          y: p.y + offset,
        }));
      }

      if (newObj.boundingBox) {
        newObj.boundingBox.x += offset;
        newObj.boundingBox.y += offset;
      }

      if (newObj.type === "booth" && newObj.boothNumber) {
        newObj.boothNumber = generateUniqueBoothNumber(newObj.boothNumber);
      }

      newObjects.push(newObj);
    });

    // Process SINGLE DOM element
    elementsToPaste.forEach((el: any) => {
      const newEl = deepCopy(el);
      newEl.id = `paste-${Date.now()}-${Math.random()
        .toString(36)
        .slice(2, 9)}`;

      if (newEl.position) {
        newEl.position.x += offset;
        newEl.position.y += offset;
      }

      newElements.push(newEl);
    });

    // ✅ CRITICAL FIX: Clear selections BEFORE creating command
    clearAllSelections();

    // Create paste command
    const pasteCmd = new (class implements Command {
      private objId = newObjects.length > 0 ? newObjects[0].id : null;
      private objCopy = newObjects.length > 0 ? deepCopy(newObjects[0]) : null;
      private elId = newElements.length > 0 ? newElements[0].id : null;
      private elCopy = newElements.length > 0 ? deepCopy(newElements[0]) : null;

      execute() {
        console.log("🎯 Executing paste command...");

        // Add object if exists
        if (this.objCopy) {
          const exists = store.objects.find((o) => o.id === this.objId);
          if (!exists) {
            const newObjToAdd = deepCopy(this.objCopy);
            newObjToAdd.isSelected = false; // Will be set below
            store.objects.push(newObjToAdd);
            console.log(`✅ Added object ${this.objId} to store.objects`);
          }
        }

        // Add element if exists
        if (this.elCopy) {
          const exists = store.domElements.find((e) => e.id === this.elId);
          if (!exists) {
            store.domElements.push(deepCopy(this.elCopy));
            console.log(`✅ Added element ${this.elId} to store.domElements`);
          }
        }

        // ✅ CRITICAL: Clear all selections first
        store.objects.forEach((obj) => {
          obj.isSelected = false;
        });
        store.selectedObjects = [];
        store.selectedDomElements = [];
        store.selectedElementId = null;

        // ✅ NOW select the pasted item
        if (this.objId) {
          const obj = store.objects.find((o) => o.id === this.objId);
          if (obj) {
            obj.isSelected = true;
            store.selectedObjects = [obj];
            console.log(`✅ Selected pasted object ${this.objId}`);
          } else {
            console.error(`❌ Could not find pasted object ${this.objId}`);
          }
        }

        if (this.elId) {
          const el = store.domElements.find((e) => e.id === this.elId);
          if (el) {
            store.selectedDomElements = [el];
            store.selectedElementId = el.id;
            console.log(`✅ Selected pasted element ${this.elId}`);
          } else {
            console.error(`❌ Could not find pasted element ${this.elId}`);
          }
        }
      }

      undo() {
        console.log("↩️ Undoing paste command...");

        if (this.objId) {
          store.objects = store.objects.filter((o) => o.id !== this.objId);
        }
        if (this.elId) {
          store.domElements = store.domElements.filter(
            (e) => e.id !== this.elId,
          );
        }

        store.selectedObjects = [];
        store.selectedDomElements = [];
        store.selectedElementId = null;
      }
    })();

    // Execute and push to history
    pasteCmd.execute();
    store.pushToHistory(
      pasteCmd,
      "paste",
      newObjects.length > 0 ? "canvas" : "dom",
      `Pasted 1 item`,
    );

    console.log(`✅ Pasted 1 item and selected it`);

    // ✅ Force a render to show the selection
    requestAnimationFrame(() => {
      render();
    });
  };

  const duplicateSelected = (useOffset: boolean = true) => {
    console.log("🔄 [DEBUG] duplicateSelected called at:", Date.now());

    // Early return if nothing selected
    if (
      store.selectedObjects.length === 0 &&
      store.selectedDomElements.length === 0
    ) {
      console.log("⚠️ No items selected to duplicate");
      return;
    }

    // 1. DISCOVER ALL ITEMS TO DUPLICATE (Objects + Elements + Children)
    const initialSelectedObjects = [...store.selectedObjects].filter(
      (obj) => !obj.isLocked && obj.isVisible !== false,
    );

    // Add selected DOM elements (use full objects, not just IDs if possible, but store.selectedDomElements is IDs or Objects?)
    // Based on previous edits, store.selectedDomElements are now full objects.
    const initialSelectedElements = [...store.selectedDomElements].filter(
      (el) => !el.isLocked && el.isVisible !== false,
    );

    if (
      initialSelectedObjects.length === 0 &&
      initialSelectedElements.length === 0
    ) {
      console.log("⚠️ No unlocked, visible items to duplicate");
      return;
    }

    const objectsToDuplicateSet = new Set<CanvasObject>(initialSelectedObjects);
    const elementsToDuplicateSet = new Set<DomElement>(initialSelectedElements);

    const objectsToScan = [...initialSelectedObjects];

    // Recursive child discovery for Containers (Frames/Sections)
    while (objectsToScan.length > 0) {
      const parent = objectsToScan.shift()!;
      if (parent.type === "frame" || parent.type === "section") {
        const parentBounds = canvasObjects.getRotatedBounding(parent);
        if (parentBounds) {
          // Find nested Canvas Objects
          store.objects.forEach((obj) => {
            if (
              objectsToDuplicateSet.has(obj) ||
              obj.isLocked ||
              obj.isVisible === false
            )
              return;
            const objBounds = canvasObjects.getRotatedBounding(obj);
            if (
              objBounds &&
              canvasObjects.isBoundsInside(objBounds, parentBounds)
            ) {
              objectsToDuplicateSet.add(obj);
              objectsToScan.push(obj); // Recurse
            }
          });

          // Find nested DOM Elements
          store.domElements.forEach((el) => {
            if (
              elementsToDuplicateSet.has(el) ||
              el.isLocked ||
              el.isVisible === false
            )
              return;

            // Simple bounds check for element inside container
            const elBounds = {
              x: el.position.x,
              y: el.position.y,
              width: el.size.width,
              height: el.size.height,
            };

            if (canvasObjects.isBoundsInside(elBounds, parentBounds)) {
              elementsToDuplicateSet.add(el);
            }
          });
        }
      }
    }

    const objectsToDuplicate = Array.from(objectsToDuplicateSet);
    const elementsToDuplicate = Array.from(elementsToDuplicateSet);

    const offset = useOffset ? 10 : 0;
    const newObjects: CanvasObject[] = [];
    const newElements: DomElement[] = [];

    const usedBoothNumbers: string[] = [];

    // --- DUPLICATE CANVAS OBJECTS ---
    objectsToDuplicate.forEach((originalObj) => {
      const newObj = deepCopy(originalObj);
      newObj.id = `dup-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
      newObj.isSelected = false; // Will be set to true later

      // Handle different object types for offset
      switch (newObj.type) {
        case "wall":
        case "line":
        case "arrow":
        case "curve-arrow":
        case "two-headed-arrow":
        case "rectangle":
        case "ellipse":
        case "frame":
        case "section":
        case "pencil":
        case "shape":
        case "booth":
          if (newObj.points && newObj.points.length > 0) {
            newObj.points = newObj.points.map((p: Point) => ({
              x: p.x + offset,
              y: p.y + offset,
            }));
          }
          if (newObj.type === "booth" && newObj.position) {
            newObj.position.x += offset;
            newObj.position.y += offset;
          }
          if (newObj.type === "booth" && newObj.boothNumber) {
            newObj.boothNumber = generateUniqueBoothNumber(
              store,
              newObj.boothNumber,
              usedBoothNumbers,
            );
            usedBoothNumbers.push(newObj.boothNumber);
          }
          break;
      }

      // Update bounding box if it exists
      if (newObj.boundingBox) {
        newObj.boundingBox.x += offset;
        newObj.boundingBox.y += offset;
      }

      // Reset transform if it exists
      if (newObj.transform) {
        newObj.transform.position.x += offset;
        newObj.transform.position.y += offset;
      }

      newObjects.push(newObj);
    });

    // --- DUPLICATE DOM ELEMENTS ---
    elementsToDuplicate.forEach((originalEl) => {
      const newEl = deepCopy(originalEl);
      newEl.id = `dup-el-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
      // Offset position
      newEl.position.x += offset;
      newEl.position.y += offset;

      newElements.push(newEl);
    });

    // Create a single command for the operation
    const duplicateCmd = {
      execute: () => {
        console.log(
          `✅ Executing duplicate for ${newObjects.length} objects and ${newElements.length} elements`,
        );

        // 1. Clear current selections
        store.objects.forEach((obj) => (obj.isSelected = false));
        store.selectedObjects = [];
        store.selectedDomElements = []; // Clear element selection
        store.selectedElementId = null;

        // 2. Add new Canvas Objects
        newObjects.forEach((newObj) => {
          const copy = deepCopy(newObj);
          copy.isSelected = true; // Select new
          store.objects.push(copy);
          store.selectedObjects.push(copy);
        });

        // 3. Add new DOM Elements
        newElements.forEach((newEl) => {
          const copy = deepCopy(newEl);
          store.domElements.push(copy);
          // Select new elements
          store.selectedDomElements.push(copy);
          store.selectedElementId = copy.id;
        });
      },

      undo: () => {
        console.log(`↩️ Undoing duplicate`);
        const objIds = newObjects.map((o) => o.id);
        const elIds = newElements.map((e) => e.id);

        store.objects = store.objects.filter((o) => !objIds.includes(o.id));
        store.domElements = store.domElements.filter(
          (e) => !elIds.includes(e.id),
        );

        store.selectedObjects = store.selectedObjects.filter(
          (o) => !objIds.includes(o.id),
        );
        store.selectedDomElements = store.selectedDomElements.filter(
          (e) => !elIds.includes(e.id),
        );
      },
    };

    // Execute and push to history
    duplicateCmd.execute();
    store.pushToHistory(
      duplicateCmd,
      "duplicate",
      "mixed",
      `Duplicated ${newObjects.length + newElements.length} item(s)`,
    );

    console.log(`✅ Successfully duplicated items`);
    render();

    // Return all duplicates (objects + elements wrapped/normalized if needed by caller)
    // The caller (useObjectManipulation) expects CanvasObjects, so we might need to verify how it handles Elements
    // But for now, we return the canvas objects as primary return since the dragging logic handles them.
    // However, if we only duplicated elements, we should return something relevant.

    // CAUTION: The caller 'switchToDuplicatedObjects' expects CanvasObject[].
    // If we have elements, we should probably wrap them or ensure the caller handles them.
    // Based on `useObjectManipulation.ts`, it wraps DOM elements into pseudo-objects.

    // We will return the newObjects. The caller might need to be updated if it strictly relies on this return
    // for Elements dragging.
    // Let's verify `switchToDuplicatedObjects` in Next step if needed.
    // For now, returning proper list.
    return [...newObjects];
  };

  // In useCanvasEngine.ts, add this new function:

  const createBoothFromSelected = () => {
    const selectedBooth = store.selectedObjects.find(
      (obj: CanvasObject) => obj.type === "booth",
    );

    if (!selectedBooth) {
      console.log("No booth selected for Shift+B operation");
      return;
    }

    if (selectedBooth.isLocked) {
      console.warn("Cannot duplicate locked booth");
      return;
    }

    // Use the existing booth duplication logic from useBoothArrows
    const { duplicateBooth } = useBoothArrows();

    // Default direction is right (similar to clicking the right arrow)
    duplicateBooth(selectedBooth, "right");

    console.log("✅ Created new booth using Shift+B with unique number");
  };

  const pushToHistory = (description: string) => {
    // This ensures undo/redo operations are tracked properly
    store.updateCurrentFloor();
    console.log(`History action: ${description}`);
  };

  // NEW: Delete selected
  const deleteSelected = () => {
    if (
      store.selectedObjects.length === 0 &&
      store.selectedDomElements.length === 0
    ) {
      console.log("Nothing selected to delete");
      return;
    }

    // Store copies before deletion for undo
    const deletedObjects = store.selectedObjects.map(deepCopy);
    const deletedElements = store.selectedDomElements.map(deepCopy);

    const objectIdsToDelete = store.selectedObjects.map((obj) => obj.id);
    const elementIdsToDelete = store.selectedDomElements.map((el) => el.id);

    // Create undo/redo command
    const deleteCmd = new (class implements Command {
      execute() {
        store.objects = store.objects.filter(
          (obj: CanvasObject) => !objectIdsToDelete.includes(obj.id),
        );
        store.domElements = store.domElements.filter(
          (el: any) => !elementIdsToDelete.includes(el.id),
        );
        store.selectedObjects = [];
        store.selectedDomElements = [];
      }

      undo() {
        deletedObjects.forEach((obj) => store.objects.push(deepCopy(obj)));
        deletedElements.forEach((el) => store.domElements.push(deepCopy(el)));
      }
    })();

    deleteCmd.execute();
    store.pushToHistory(
      deleteCmd,
      "delete",
      deletedObjects.length > 0 && deletedElements.length > 0
        ? "mixed"
        : deletedObjects.length > 0
          ? "canvas"
          : "dom",
      `Deleted ${objectIdsToDelete.length} objects and ${elementIdsToDelete.length} elements`,
    );

    console.log(
      `Deleted ${objectIdsToDelete.length} objects and ${elementIdsToDelete.length} elements`,
    );
  };

  const lockSelected = () => {
    const objectsToLock = store.selectedObjects.filter((obj) => !obj.isLocked);
    const elementsToLock = store.selectedDomElements
      .map((id) => store.domElements.find((e) => e.id === id))
      .filter((el) => el && !el.isLocked);

    if (objectsToLock.length === 0 && elementsToLock.length === 0) {
      console.log("No unlocked items in selection");
      return;
    }

    const objIds = objectsToLock.map((o) => o.id);
    const elIds = elementsToLock.map((e) => e!.id);

    const lockCmd = new (class implements Command {
      execute() {
        objIds.forEach((id) => {
          const obj = store.objects.find((o) => o.id === id);
          if (obj) obj.isLocked = true;
        });
        elIds.forEach((id) => {
          const el = store.domElements.find((e) => e.id === id);
          if (el) el.isLocked = true;
        });
      }

      undo() {
        objIds.forEach((id) => {
          const obj = store.objects.find((o) => o.id === id);
          if (obj) obj.isLocked = false;
        });
        elIds.forEach((id) => {
          const el = store.domElements.find((e) => e.id === id);
          if (el) el.isLocked = false;
        });
      }
    })();

    lockCmd.execute();
    store.pushToHistory(
      lockCmd,
      "lock",
      objIds.length > 0 && elIds.length > 0
        ? "mixed"
        : objIds.length > 0
          ? "canvas"
          : "dom",
      `Locked ${objIds.length} objects and ${elIds.length} elements`,
    );

    // Clear selection after locking
    clearAllSelections();

    console.log(`Locked ${objIds.length} objects and ${elIds.length} elements`);
  };

  const unlockSelected = () => {
    const objectsToUnlock = store.selectedObjects.filter((obj) => obj.isLocked);
    const elementsToUnlock = store.selectedDomElements
      .map((id) => store.domElements.find((e) => e.id === id))
      .filter((el) => el && el.isLocked);

    if (objectsToUnlock.length === 0 && elementsToUnlock.length === 0) {
      console.log("No locked objects in selection");
      return;
    }

    const objIds = objectsToUnlock.map((o) => o.id);
    const elIds = elementsToUnlock.map((e) => e!.id);

    const unlockCmd = new (class implements Command {
      execute() {
        objIds.forEach((id) => {
          const obj = store.objects.find((o) => o.id === id);
          if (obj) obj.isLocked = false;
        });
        elIds.forEach((id) => {
          const el = store.domElements.find((e) => e.id === id);
          if (el) el.isLocked = false;
        });
      }

      undo() {
        objIds.forEach((id) => {
          const obj = store.objects.find((o) => o.id === id);
          if (obj) obj.isLocked = true;
        });
        elIds.forEach((id) => {
          const el = store.domElements.find((e) => e.id === id);
          if (el) el.isLocked = true;
        });
      }
    })();

    unlockCmd.execute();
    store.pushToHistory(
      unlockCmd,
      "unlock",
      objIds.length > 0 && elIds.length > 0
        ? "mixed"
        : objIds.length > 0
          ? "canvas"
          : "dom",
      `Unlocked ${objIds.length} objects and ${elIds.length} elements`,
    );

    console.log(
      `Unlocked ${objIds.length} objects and ${elIds.length} elements`,
    );

    // Selection is preserved
  };

  // Helper function to find selectable objects
  const findSelectableObjectAtPoint = (
    worldPoint: Point,
  ): CanvasObject | null => {
    for (let i = store.objects.length - 1; i >= 0; i--) {
      const obj = store.objects[i];

      if (obj.isLocked) {
        continue;
      }

      if (obj.isVisible === false) {
        continue;
      }

      if (isPointInObject(worldPoint, obj)) {
        return obj;
      }
    }
    return null;
  };

  const selectObject = (object: CanvasObject) => {
    if (object.isLocked) {
      return;
    }

    if (object.isVisible === false) {
      return;
    }

    clearAllSelections();

    object.isSelected = true;
    store.selectedObjects = [object];
    store.selectedElementId = null;

    console.log(`Selected object: ${object.type} (${object.id})`);
  };

  // Add this function in useCanvasEngine.ts if it doesn't exist:

  const clearAllSelections = () => {
    // Deselect all canvas objects
    store.objects.forEach((obj) => {
      obj.isSelected = false;
    });

    // Clear selected objects array
    store.selectedObjects = [];

    // Also clear DOM element selections
    store.selectedElementId = null;
    store.selectedDomElements = [];

    console.log("All selections cleared");
  };

  // Canvas setup
  // In useCanvasEngine.ts setupCanvas function:
  const setupCanvas = (canvas: HTMLCanvasElement) => {
    canvasRef.value = canvas;
    ctx.value = canvas.getContext("2d");
    resizeCanvas();

    // Make canvas focusable and set tabindex
    canvas.tabIndex = 0;
    canvas.style.outline = "none"; // Remove default outline
    canvas.focus();
  };

  // In useCanvasEngine.ts
  const canvasDimensions = ref({ width: 0, height: 0 });

  const resizeCanvas = () => {
    if (!canvasRef.value) return;
    const canvas = canvasRef.value;
    const container = canvas.parentElement;
    if (!container) return;
    const dpr = window.devicePixelRatio || 1;
    const rect = container.getBoundingClientRect();

    // Store dimensions
    canvasDimensions.value = { width: rect.width, height: rect.height };

    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    canvas.style.width = `${rect.width}px`;
    canvas.style.height = `${rect.height}px`;
    if (ctx.value) {
      ctx.value.scale(dpr, dpr);
    }

    objectManipulation.updateCanvasDimensions(rect.width, rect.height);
  };

  const clearCanvas = () => {
    if (!canvasRef.value || !ctx.value) return;
    const canvas = canvasRef.value;
    ctx.value.clearRect(0, 0, canvas.width, canvas.height);
  };

  const worldToScreen = (point: Point, zoom: number, offset: Point): Point => ({
    x: (point.x - offset.x) * zoom,
    y: (point.y - offset.y) * zoom,
  });

  const screenToWorld = (point: Point, zoom: number, offset: Point): Point => ({
    x: point.x / zoom + offset.x,
    y: point.y / zoom + offset.y,
  });

  // Event handlers
  const handleDoubleClick = (event: MouseEvent) => {
    if (!canvasEl.value || !isStoreReady.value) return;

    const rect = canvasEl.value.getBoundingClientRect();
    const point = {
      x: event.clientX - rect.left,
      y: event.clientY - rect.top,
    };

    const worldPoint = screenToWorld(point, store.zoom, store.offset);

    // ✅ NEW: Label Editing for Frames and Sections
    // Iterate from top to bottom (reverse order)
    for (let i = store.objects.length - 1; i >= 0; i--) {
      const obj = store.objects[i];
      if (
        (obj.type === "frame" || obj.type === "section") &&
        obj.label &&
        obj.labelVisible !== false
      ) {
        const labelBounds =
          obj.type === "frame"
            ? getFrameLabelBounds(obj, store.zoom, ctx.value!)
            : getSectionLabelBounds(obj, store.zoom, ctx.value!);

        const isLabelHit =
          labelBounds &&
          worldPoint.x >= labelBounds.x &&
          worldPoint.x <= labelBounds.x + labelBounds.width &&
          worldPoint.y >= labelBounds.y &&
          worldPoint.y <= labelBounds.y + labelBounds.height;

        // Allow double-clicking on the body too for better UX
        const isBodyHit = isPointInObjectPrecise(worldPoint, obj);

        if (isLabelHit || isBodyHit) {
          // Determine display position (top-left of the object in screen space)
          const bounds = canvasObjects.getRotatedBounding(obj);
          if (bounds) {
            const screenPos = worldToScreen(
              { x: bounds.x, y: bounds.y },
              store.zoom,
              store.offset,
            );

            drawingState.value.labelEditing = {
              objId: obj.id,
              text: obj.label || (obj.type === "frame" ? "Frame" : "Section"),
              screenPos: {
                x: screenPos.x,
                y: screenPos.y - 18 * store.zoom - 10, // Position exactly over the label
              },
              type: obj.type,
            };
          }
          render();
          return;
        }
      }
    }

    if (
      store.currentTool === "wall" &&
      drawingState.value.wallState.isDrawing
    ) {
      if (drawingState.value.wallState.points.length < 2) {
        addWallPoint(worldPoint);
      }

      finishWallDrawing();
      render();
      return;
    }

    startDrawing(point, store.zoom, store.offset, true);
    render();
  };

  const handleMouseDown = (event: MouseEvent) => {
    if (!canvasEl.value || !isStoreReady.value) return;

    const rect = canvasEl.value.getBoundingClientRect();
    const point = {
      x: event.clientX - rect.left,
      y: event.clientY - rect.top,
    };

    const cmdKey = event.ctrlKey || event.metaKey;

    // ✅ NEW: যদি hand tool-এ থাকি, তাহলে booth arrow ক্লিক কাজ করবে না
    if (store.currentTool === "hand") {
      // শুধুমাত্র panning করার অনুমতি দিন
      startPanning(point);
      render();
      return;
    }

    // TEXT TOOL - Click to place text element (existing code)
    if (store.currentTool === "text") {
      const worldPoint = screenToWorld(point, store.zoom, store.offset);

      const newElement = {
        id: `text-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
        type: "text",
        subtype: "p",
        position: {
          x: worldPoint.x - 100,
          y: worldPoint.y - 25,
        },
        size: {
          width: 200,
          height: 50,
        },
        styleProps: {
          fontSize: 24,
          fontFamily: "Verdana",
          color: "#000000",
          backgroundColor: "transparent",
        },
        content: "",
        rotation: 0,
        zIndex: store.domElements.length,
        isLocked: false,
        isVisible: true,
      };

      store.domElements.push(newElement);
      store.selectedElementId = newElement.id;
      store.selectedDomElements = [newElement.id];
      store.selectedObjects = [];

      store.setTool("select");

      nextTick(() => {
        setTimeout(() => {
          const textElement = document.querySelector(
            `[data-element="${newElement.id}"]`,
          );
          if (textElement) {
            const dblClickEvent = new MouseEvent("dblclick", {
              bubbles: true,
              cancelable: true,
              view: window,
            });
            textElement.dispatchEvent(dblClickEvent);
          }
        }, 100);
      });

      render();
      return;
    }

    if (store.currentTool === "two-headed-arrow") {
      let worldPoint = screenToWorld(point, store.zoom, store.offset);

      // Snapping support for two-headed-arrow
      if (
        event.shiftKey &&
        drawingState.value.twoHeadedArrowState.isDrawing &&
        drawingState.value.twoHeadedArrowState.points.length > 0
      ) {
        const lastPoint =
          drawingState.value.twoHeadedArrowState.points[
            drawingState.value.twoHeadedArrowState.points.length - 1
          ];
        const dx = Math.abs(worldPoint.x - lastPoint.x);
        const dy = Math.abs(worldPoint.y - lastPoint.y);
        if (dx > dy) worldPoint = { x: worldPoint.x, y: lastPoint.y };
        else worldPoint = { x: lastPoint.x, y: worldPoint.y };
      }

      if (!drawingState.value.twoHeadedArrowState.isDrawing) {
        startTwoHeadedArrowDrawing(worldPoint);
      } else {
        finishTwoHeadedArrowDrawing();
      }
      render();
      return;
    }

    if (store.currentTool === "wall") {
      let worldPoint = screenToWorld(point, store.zoom, store.offset);

      // Snapping support for wall
      if (
        event.shiftKey &&
        drawingState.value.wallState.isDrawing &&
        drawingState.value.wallState.points.length > 0
      ) {
        const lastPoint =
          drawingState.value.wallState.points[
            drawingState.value.wallState.points.length - 1
          ];
        const dx = Math.abs(worldPoint.x - lastPoint.x);
        const dy = Math.abs(worldPoint.y - lastPoint.y);
        if (dx > dy) worldPoint = { x: worldPoint.x, y: lastPoint.y };
        else worldPoint = { x: lastPoint.x, y: worldPoint.y };
      }

      if (!drawingState.value.wallState.isDrawing) {
        startWallDrawing(worldPoint);
      } else {
        addWallPoint(worldPoint);
      }
      drawingState.value.wallState.currentPreviewPoint = worldPoint;
      render();
      return;
    }

    if (store.currentTool === "curve-arrow") {
      const worldPoint = screenToWorld(point, store.zoom, store.offset);
      if (!drawingState.value.curveArrowState.isDrawing) {
        startCurveArrowDrawing(worldPoint);
      } else {
        addCurveArrowPoint(worldPoint);
        drawingState.value.curveArrowState.currentPreviewPoint = worldPoint;
      }
      render();
      return;
    }

    // ✅ NEW: Auto-switch to select tool when clicking on objects (NOT hand tool or pencil)
    if (!["select", "hand", "pencil"].includes(store.currentTool)) {
      const worldPoint = screenToWorld(point, store.zoom, store.offset);

      // Check if clicking on a canvas object
      const clickedObject = findTopmostSelectableObject(worldPoint, cmdKey);

      // Check if clicking on a DOM element
      const target = event.target as HTMLElement;
      const clickedDomElement = target.closest("[data-element]");

      // If clicked on any object or element, switch to select tool
      if (clickedObject || clickedDomElement) {
        console.log(
          "🎯 Clicked on object while using",
          store.currentTool,
          "- switching to select tool",
        );
        store.setTool("select");

        // Handle the click as if we were in select mode
        if (clickedObject) {
          if (cmdKey) {
            if (clickedObject.isSelected) {
              clickedObject.isSelected = false;
              store.selectedObjects = store.selectedObjects.filter(
                (obj) => obj.id !== clickedObject.id,
              );
            } else {
              clickedObject.isSelected = true;
              store.selectedObjects.push(clickedObject);
            }
          } else {
            if (!clickedObject.isSelected) {
              clearAllSelections();
              selectObject(clickedObject);
            }
          }

          const canDrag = store.selectedObjects.every((obj) => !obj.isLocked);
          if (canDrag && clickedObject.isSelected) {
            objectManipulation.startDragging(worldPoint);
          }
        }

        render();
        return;
      }
    }

    // ✅ UPDATED: Select tool logic - hover অবস্থায়ও arrow ক্লিক কাজ করবে
    if (store.currentTool === "select") {
      // 1. প্রথমে চেক করুন hovered booth-এ arrow-এ ক্লিক হয়েছে কিনা
      const hoveredBooth = store.objects.find(
        (obj) =>
          obj.type === "booth" && obj.id === hoverState.value.hoveredBoothId,
      );

      if (hoveredBooth) {
        const arrowDirection = getArrowAtPoint(
          point,
          hoveredBooth,
          store.zoom,
          store.offset,
        );
        if (arrowDirection) {
          // Handle arrow click (duplicate booth) - HOOVERED STATE
          const { duplicateBooth } = useBoothArrows();
          duplicateBooth(hoveredBooth, arrowDirection);

          // Clear any existing selection
          clearAllSelections();

          // Select the new booth
          const newBooth = store.objects.find(
            (obj) =>
              obj.boothNumber ===
              generateUniqueBoothNumber(hoveredBooth.boothNumber || "Booth"),
          );
          if (newBooth) {
            newBooth.isSelected = true;
            store.selectedObjects = [newBooth];
          }

          render();
          return;
        }
      }

      // 2. তারপর চেক করুন selected booth-এ arrow-এ ক্লিক হয়েছে কিনা
      const selectedBooth = store.objects.find(
        (obj) => obj.type === "booth" && obj.isSelected,
      );
      if (selectedBooth) {
        const arrowDirection = getArrowAtPoint(
          point,
          selectedBooth,
          store.zoom,
          store.offset,
        );
        if (arrowDirection) {
          // Handle arrow click (duplicate booth) - SELECTED STATE
          const { duplicateBooth } = useBoothArrows();
          duplicateBooth(selectedBooth, arrowDirection);

          // Clear selection and select the new booth
          clearAllSelections();

          const newBooth = store.objects.find(
            (obj) =>
              obj.boothNumber ===
              generateUniqueBoothNumber(selectedBooth.boothNumber || "Booth"),
          );
          if (newBooth) {
            newBooth.isSelected = true;
            store.selectedObjects = [newBooth];
          }

          render();
          return;
        }
      }

      // 3. Existing selection logic (handle, rotation, etc.)
      const worldPoint = screenToWorld(point, store.zoom, store.offset);
      const handleInfo = objectManipulation.getHandleAtPoint(worldPoint);

      if (handleInfo) {
        const targetObject = store.selectedObjects[0];
        if (targetObject && targetObject.isLocked) {
          return;
        }

        if (handleInfo.type === "rotation") {
          objectManipulation.startRotating(worldPoint);
        } else if (handleInfo.type === "rounding") {
          objectManipulation.startRounding(worldPoint, handleInfo.index);
        } else {
          objectManipulation.startResizing(worldPoint, handleInfo.index);
        }
        render();
        return;
      }

      const clickedObject = findTopmostSelectableObject(worldPoint, cmdKey);

      if (clickedObject) {
        if (cmdKey) {
          if (clickedObject.isSelected) {
            clickedObject.isSelected = false;
            store.selectedObjects = store.selectedObjects.filter(
              (obj) => obj.id !== clickedObject.id,
            );
          } else {
            clickedObject.isSelected = true;
            store.selectedObjects.push(clickedObject);
          }
        } else {
          if (!clickedObject.isSelected) {
            clearAllSelections();
            selectObject(clickedObject);
          }
        }

        const canDrag = store.selectedObjects.every((obj) => !obj.isLocked);
        if (canDrag && clickedObject.isSelected) {
          // ✅ NEW: Handle Alt+MouseDown duplication immediately (in-place for peel-off effect)
          if (
            event.altKey &&
            !objectManipulation.interactionState.value.hasDuplicated
          ) {
            console.log(
              "📂 Alt+MouseDown: Duplicating selected objects in-place",
            );
            duplicateSelected(true); // true = no offset, creates duplicate at exact same position
            objectManipulation.interactionState.value.hasDuplicated = true;
          }

          objectManipulation.startDragging(worldPoint);
        }
        render();
        return;
      }

      clearAllSelections();
      startSelecting(point, store.zoom, store.offset);
    } else if (store.currentTool === "hand") {
      startPanning(point);
    } else {
      // ✅ NEW: Set pending state instead of starting drawing
      drawingState.value.isDrawingPending = true;
      drawingState.value.startPoint = screenToWorld(
        point,
        store.zoom,
        store.offset,
      );
      drawingState.value.currentPoint = drawingState.value.startPoint;
    }
    render();
  };

  const isPointInObjectPrecise = (point: Point, obj: CanvasObject): boolean => {
    // Base tolerance - scales with zoom for consistent UX
    const tolerance = 8 / store.zoom;

    // CRITICAL: Use STRICT inner detection for filled objects to avoid overlap issues
    // This ensures clicking on nested objects selects the correct one
    const strictInnerDetection = [
      "rectangle",
      "ellipse",
      "booth",
      "frame",
      "section",
    ].includes(obj.type);

    const bounds = canvasObjects.getRotatedBounding(obj);
    if (!bounds) return false;

    const center = canvasObjects.getCenter(obj);
    const rotation = obj.rotation || 0;

    // ==========================================
    // RECTANGLES - Polygon containment for precise nested detection
    // ==========================================
    if (obj.type === "rectangle" && obj.points && obj.points.length >= 2) {
      const p1 = obj.points[0];
      const p2 = obj.points[1];

      const minX = Math.min(p1.x, p2.x);
      const maxX = Math.max(p1.x, p2.x);
      const minY = Math.min(p1.y, p2.y);
      const maxY = Math.max(p1.y, p2.y);

      // Apply inward tolerance for strict inner detection
      const innerTolerance = tolerance * 0.3;
      const adjustedMinX = minX + innerTolerance;
      const adjustedMaxX = maxX - innerTolerance;
      const adjustedMinY = minY + innerTolerance;
      const adjustedMaxY = maxY - innerTolerance;

      // Create corners in local space with adjusted bounds
      const corners = [
        { x: adjustedMinX, y: adjustedMinY },
        { x: adjustedMaxX, y: adjustedMinY },
        { x: adjustedMaxX, y: adjustedMaxY },
        { x: adjustedMinX, y: adjustedMaxY },
      ];

      // Handle rotation
      if (rotation !== 0) {
        const rotatedCorners = corners.map((corner) =>
          canvasObjects.rotatePoint(corner, center, rotation),
        );
        return isPointInPolygon(point, rotatedCorners);
      }

      // Non-rotated: strict inner bounds check
      return (
        point.x >= adjustedMinX &&
        point.x <= adjustedMaxX &&
        point.y >= adjustedMinY &&
        point.y <= adjustedMaxY
      );
    }

    // ==========================================
    // FRAME & SECTION - Same as rectangle
    // ==========================================
    if (
      ["frame", "section"].includes(obj.type) &&
      obj.points &&
      obj.points.length >= 2
    ) {
      const p1 = obj.points[0];
      const p2 = obj.points[1];
      const rotation = obj.rotation || 0;
      const center = canvasObjects.getCenter(obj);
      const minX = Math.min(p1.x, p2.x);
      const maxX = Math.max(p1.x, p2.x);
      const minY = Math.min(p1.y, p2.y);
      const maxY = Math.max(p1.y, p2.y);

      const corners = [
        { x: minX + tolerance * 0.3, y: minY + tolerance * 0.3 },
        { x: maxX - tolerance * 0.3, y: minY + tolerance * 0.3 },
        { x: maxX - tolerance * 0.3, y: maxY - tolerance * 0.3 },
        { x: minX + tolerance * 0.3, y: maxY - tolerance * 0.3 },
      ];

      if (rotation !== 0) {
        const rotatedCorners = corners.map((corner) =>
          canvasObjects.rotatePoint(corner, center, rotation),
        );
        return isPointInPolygon(point, rotatedCorners);
      }
      return (
        point.x >= minX + tolerance * 0.3 &&
        point.x <= maxX - tolerance * 0.3 &&
        point.y >= minY + tolerance * 0.3 &&
        point.y <= maxY - tolerance * 0.3
      );
    }

    // ==========================================
    // ELLIPSES - Ellipse equation with rotation support
    // ==========================================
    if (obj.type === "ellipse" && obj.points && obj.points.length >= 2) {
      const p1 = obj.points[0];
      const p2 = obj.points[1];

      // Calculate semi-major and semi-minor axes
      const rx = Math.abs(p2.x - p1.x) / 2;
      const ry = Math.abs(p2.y - p1.y) / 2;
      const cx = (p1.x + p2.x) / 2;
      const cy = (p1.y + p2.y) / 2;

      // Transform point to ellipse's local coordinate system
      const localPoint = canvasObjects.rotatePoint(
        point,
        { x: cx, y: cy },
        -rotation,
      );

      const dx = localPoint.x - cx;
      const dy = localPoint.y - cy;

      // Ellipse equation: (x²/a²) + (y²/b²) ≤ 1
      // Use strict inner check (subtract tolerance)
      const ellipseCheck = (dx * dx) / (rx * rx) + (dy * dy) / (ry * ry);
      const maxRadius = Math.max(rx, ry);

      return ellipseCheck <= 1.0 - tolerance / (maxRadius * 2); // Strict inner detection
    }

    // ==========================================
    // BOOTHS - Bounding box with rotation (most common nested type)
    // ==========================================
    if (obj.type === "booth") {
      // Use boundingBox if available
      const hoverTolerance = 15 / store.zoom;

      if (obj.boundingBox) {
        const bbox = obj.boundingBox;

        if (rotation !== 0) {
          const corners = [
            { x: bbox.x, y: bbox.y },
            { x: bbox.x + bbox.width, y: bbox.y },
            { x: bbox.x + bbox.width, y: bbox.y + bbox.height },
            { x: bbox.x, y: bbox.y + bbox.height },
          ];
          const rotatedCorners = corners.map((corner) =>
            canvasObjects.rotatePoint(corner, center, rotation),
          );
          if (isPointInPolygon(point, rotatedCorners)) return true;
        } else {
          if (
            point.x >= bbox.x - hoverTolerance &&
            point.x <= bbox.x + bbox.width + hoverTolerance &&
            point.y >= bbox.y - hoverTolerance &&
            point.y <= bbox.y + bbox.height + hoverTolerance
          )
            return true;
        }

        // Also check if point is on any of the duplication arrows
        const screenPoint = worldToScreen(point, store.zoom, store.offset);
        if (getArrowAtPoint(screenPoint, obj, store.zoom, store.offset)) {
          return true;
        }
        return false;
      }

      // Fallback to points if no boundingBox
      if (obj.points && obj.points.length >= 2) {
        const p1 = obj.points[0];
        const p2 = obj.points[1];
        const minX = Math.min(p1.x, p2.x);
        const maxX = Math.max(p1.x, p2.x);
        const minY = Math.min(p1.y, p2.y);
        const maxY = Math.max(p1.y, p2.y);

        // Apply inward tolerance
        const innerTolerance = tolerance * 0.3;
        const adjustedMinX = minX + innerTolerance;
        const adjustedMaxX = maxX - innerTolerance;
        const adjustedMinY = minY + innerTolerance;
        const adjustedMaxY = maxY - innerTolerance;

        const corners = [
          { x: adjustedMinX, y: adjustedMinY },
          { x: adjustedMaxX, y: adjustedMinY },
          { x: adjustedMaxX, y: adjustedMaxY },
          { x: adjustedMinX, y: adjustedMaxY },
        ];

        if (rotation !== 0) {
          const rotatedCorners = corners.map((corner) =>
            canvasObjects.rotatePoint(corner, center, rotation),
          );
          return isPointInPolygon(point, rotatedCorners);
        }

        return (
          point.x >= adjustedMinX &&
          point.x <= adjustedMaxX &&
          point.y >= adjustedMinY &&
          point.y <= adjustedMaxY
        );
      }
    }

    // ==========================================
    // WALLS - Multi-segment line detection
    // ==========================================
    if (obj.type === "wall" && obj.points && obj.points.length >= 2) {
      for (let i = 0; i < obj.points.length - 1; i++) {
        const distance = pointToLineDistance(
          point,
          obj.points[i],
          obj.points[i + 1],
        );
        if (distance <= tolerance) {
          return true;
        }
      }
      return false;
    }

    // ==========================================
    // LINES & ARROWS - Single segment detection
    // ==========================================
    if (
      (obj.type === "line" ||
        obj.type === "arrow" ||
        obj.type === "two-headed-arrow") &&
      obj.points &&
      obj.points.length >= 2
    ) {
      const distance = pointToLineDistance(point, obj.points[0], obj.points[1]);
      return distance <= tolerance;
    }

    // ==========================================
    // PENCIL & CURVE-ARROW - Multi-segment path detection
    // ==========================================
    if (
      (obj.type === "pencil" || obj.type === "curve-arrow") &&
      obj.points &&
      obj.points.length >= 2
    ) {
      for (let i = 0; i < obj.points.length - 1; i++) {
        const distance = pointToLineDistance(
          point,
          obj.points[i],
          obj.points[i + 1],
        );
        if (distance <= tolerance) {
          return true;
        }
      }
      return false;
    }

    // ==========================================
    // TEXT OBJECTS - Bounding box check (if you have text objects)
    // ==========================================
    if (obj.type === "text" && obj.boundingBox) {
      const bbox = obj.boundingBox;
      return (
        point.x >= bbox.x - tolerance &&
        point.x <= bbox.x + bbox.width + tolerance &&
        point.y >= bbox.y - tolerance &&
        point.y <= bbox.y + bbox.height + tolerance
      );
    }

    // ==========================================
    // GENERIC FALLBACK - Use bounding box for unknown types
    // ==========================================
    if (bounds) {
      // Apply inward tolerance for strict detection if it's a filled-type object
      const innerTolerance = strictInnerDetection ? tolerance * 0.3 : tolerance;

      // Check if object has rotation
      if (rotation !== 0) {
        // Create adjusted corners from bounds
        const adjustedBounds = {
          x: bounds.x + innerTolerance,
          y: bounds.y + innerTolerance,
          width: bounds.width - innerTolerance * 2,
          height: bounds.height - innerTolerance * 2,
        };

        const corners = [
          { x: adjustedBounds.x, y: adjustedBounds.y },
          { x: adjustedBounds.x + adjustedBounds.width, y: adjustedBounds.y },
          {
            x: adjustedBounds.x + adjustedBounds.width,
            y: adjustedBounds.y + adjustedBounds.height,
          },
          { x: adjustedBounds.x, y: adjustedBounds.y + adjustedBounds.height },
        ];

        // Rotate corners
        const rotatedCorners = corners.map((corner) =>
          canvasObjects.rotatePoint(corner, center, rotation),
        );

        return isPointInPolygon(point, rotatedCorners);
      }

      // Non-rotated fallback with strict detection
      if (strictInnerDetection) {
        return (
          point.x >= bounds.x + innerTolerance &&
          point.x <= bounds.x + bounds.width - innerTolerance &&
          point.y >= bounds.y + innerTolerance &&
          point.y <= bounds.y + bounds.height - innerTolerance
        );
      }

      return (
        point.x >= bounds.x - tolerance &&
        point.x <= bounds.x + bounds.width + tolerance &&
        point.y >= bounds.y - tolerance &&
        point.y <= bounds.y + bounds.height + tolerance
      );
    }

    return false;
  };

  const findTopmostSelectableObject = (
    worldPoint: Point,
    includeLocked: boolean = false,
  ): CanvasObject | null => {
    const candidates: Array<{
      obj: CanvasObject;
      index: number;
      area: number;
      isLine: boolean;
      distanceFromEdge: number;
    }> = [];

    for (let i = 0; i < store.objects.length; i++) {
      const obj = store.objects[i];

      if (!includeLocked && obj.isLocked) continue;
      if (obj.isVisible === false) continue;

      // Check if point is in object
      if (isPointInObject(worldPoint, obj)) {
        const bounds = canvasObjects.getRotatedBounding(obj);
        const area = bounds ? bounds.width * bounds.height : Infinity;
        const isLine = ["line", "arrow", "two-headed-arrow"].includes(obj.type);

        // Calculate distance from edge for lines
        let distanceFromEdge = 0;
        if (isLine) {
          if (obj.points && obj.points.length >= 2) {
            distanceFromEdge = pointToLineDistance(
              worldPoint,
              obj.points[0],
              obj.points[1],
            );
          }
        }

        candidates.push({ obj, index: i, area, isLine, distanceFromEdge });
      }
    }

    if (candidates.length === 0) return null;
    if (candidates.length === 1) return candidates[0].obj;

    // ===================================================================
    // INLINE SMART PRIORITIZATION (no helper function)
    // ===================================================================

    const filledObjects = candidates.filter((c) => !c.isLine);
    const lineObjects = candidates.filter((c) => c.isLine);

    // If we have both types, check for direct line hits
    if (filledObjects.length > 0 && lineObjects.length > 0) {
      const directLineHit = lineObjects.find((c) => c.distanceFromEdge < 5);

      if (directLineHit) {
        const smallestFilledArea = Math.min(
          ...filledObjects.map((c) => c.area),
        );
        if (directLineHit.area < smallestFilledArea * 0.5) {
          return directLineHit.obj;
        }
      }
    }

    // Select from filled objects if available, otherwise from lines
    const primaryCandidates =
      filledObjects.length > 0 ? filledObjects : lineObjects;

    if (primaryCandidates.length === 1) {
      return primaryCandidates[0].obj;
    }

    // INLINE: Select smallest with highest z-index
    const minArea = Math.min(...primaryCandidates.map((c) => c.area));
    const maxArea = Math.max(...primaryCandidates.map((c) => c.area));
    const areaRange = maxArea - minArea;

    const threshold = areaRange > minArea * 2 ? minArea * 1.2 : minArea * 1.5;
    const smallestCandidates = primaryCandidates.filter(
      (c) => c.area <= threshold,
    );

    // Return object with highest z-index among smallest
    return smallestCandidates.reduce((prev, current) => {
      return current.index > prev.index ? current : prev;
    }).obj;
  };

  const handleMouseMove = (event: MouseEvent) => {
    if (!canvasEl.value || !isStoreReady.value) return;

    const rect = canvasEl.value.getBoundingClientRect();
    const point = {
      x: event.clientX - rect.left,
      y: event.clientY - rect.top,
    };

    hoverState.value.mousePosition = point;

    // Update cursor
    const cursor = objectManipulation.updateCursor(
      point,
      store.zoom,
      store.offset,
    );
    if (canvasEl.value) {
      canvasEl.value.style.cursor = cursor;
    }

    const worldPoint = screenToWorld(point, store.zoom, store.offset);

    // ✅ UPDATED: শুধুমাত্র select tool-এ থাকলে hover state update করুন
    if (store.currentTool === "select") {
      // Include locked objects in hover detection (true parameter)
      const hoveredObject = findTopmostSelectableObject(worldPoint, true);

      // Clear previous hover states
      store.objects.forEach((obj) => {
        obj.isHovered = false;
      });

      // Set hover state on the hovered object
      if (hoveredObject) {
        hoveredObject.isHovered = true;
        hoverState.value.hoveredBoothId =
          hoveredObject.type === "booth" ? hoveredObject.id : null;
      } else {
        hoverState.value.hoveredBoothId = null;
      }

      canvasRendering.updateHoverState(hoveredObject, point);
    } else {
      // ✅ Hand tool বা অন্য কোনো tool-এ থাকলে hover state clear করুন
      store.objects.forEach((obj) => {
        obj.isHovered = false;
      });
      hoverState.value.hoveredBoothId = null;
      canvasRendering.updateHoverState(null, point);
    }

    // ✅ NEW: Check for pending drawing state and start drawing if mouse moved
    if (
      drawingState.value.isDrawingPending &&
      [
        "pencil",
        "line",
        "arrow",
        "rectangle",
        "ellipse",
        "frame",
        "section",
      ].includes(store.currentTool)
    ) {
      startDrawing(point, store.zoom, store.offset);
      drawingState.value.isDrawingPending = false;
    }

    if (
      store.currentTool === "two-headed-arrow" &&
      drawingState.value.twoHeadedArrowState.isDrawing
    ) {
      continueTwoHeadedArrowDrawing(worldPoint, event.shiftKey);
      render();
      return;
    }

    if (
      store.currentTool === "wall" &&
      drawingState.value.wallState.isDrawing
    ) {
      let previewPoint = worldPoint;
      if (event.shiftKey && drawingState.value.wallState.points.length > 0) {
        const lastPoint =
          drawingState.value.wallState.points[
            drawingState.value.wallState.points.length - 1
          ];
        const dx = Math.abs(worldPoint.x - lastPoint.x);
        const dy = Math.abs(worldPoint.y - lastPoint.y);
        if (dx > dy) previewPoint = { x: worldPoint.x, y: lastPoint.y };
        else previewPoint = { x: lastPoint.x, y: worldPoint.y };
      }
      drawingState.value.wallState.currentPreviewPoint = previewPoint;
      updateWallPreview();
      render();
      return;
    }

    if (
      store.currentTool === "curve-arrow" &&
      drawingState.value.curveArrowState.isDrawing
    ) {
      drawingState.value.curveArrowState.currentPreviewPoint = worldPoint;
      updateCurveArrowPreview();
      render();
      return;
    }

    if (store.currentTool === "select" && drawingState.value.isSelecting) {
      continueSelecting(point, store.zoom, store.offset);
    } else if (store.currentTool === "hand" && isPanning.value) {
      doPanning(point);
    } else if (drawingState.value.isDrawing) {
      draw(point, store.zoom, store.offset, event.shiftKey);
    } else if (objectManipulation.interactionState.value.isDragging) {
      const canDrag = store.selectedObjects.every((obj) => !obj.isLocked);
      if (canDrag) {
        objectManipulation.doDragging(
          point,
          store.zoom,
          store.offset,
          canvasDimensions.value.width,
          canvasDimensions.value.height,
          render,
        );
      }
    } else if (objectManipulation.interactionState.value.isResizing) {
      const targetObject = store.selectedObjects[0];
      if (targetObject && !targetObject.isLocked) {
        objectManipulation.doResizing(
          point,
          store.zoom,
          store.offset,
          canvasDimensions.value.width,
          canvasDimensions.value.height,
        );
      }
    } else if (objectManipulation.interactionState.value.isRotating) {
      const targetObject = store.selectedObjects[0];
      if (targetObject && !targetObject.isLocked) {
        objectManipulation.doRotating(point, store.zoom, store.offset);
      }
    } else if (objectManipulation.interactionState.value.isRounding) {
      const targetObject = store.selectedObjects[0];
      if (targetObject && !targetObject.isLocked) {
        objectManipulation.doRounding(point, store.zoom, store.offset);
      }
    }

    render();
  };

  const findObjectAtPoint = (worldPoint: Point): CanvasObject | null => {
    for (let i = store.objects.length - 1; i >= 0; i--) {
      const obj = store.objects[i];

      if (obj.isLocked) {
        continue;
      }
      if (obj.isVisible === false) {
        continue;
      }

      if (isPointInObject(worldPoint, obj)) {
        return obj;
      }
    }
    return null;
  };

  const isPointInObject = (point: Point, obj: CanvasObject): boolean => {
    const tolerance = 10 / store.zoom; // Generous tolerance for all objects

    const bounds = canvasObjects.getRotatedBounding(obj);
    if (!bounds) return false;

    const center = canvasObjects.getCenter(obj);
    const rotation = obj.rotation || 0;

    // RECTANGLES, FRAMES, SECTIONS
    if (
      ["rectangle", "frame", "section"].includes(obj.type) &&
      obj.points &&
      obj.points.length >= 2
    ) {
      const p1 = obj.points[0];
      const p2 = obj.points[1];

      const minX = Math.min(p1.x, p2.x);
      const maxX = Math.max(p1.x, p2.x);
      const minY = Math.min(p1.y, p2.y);
      const maxY = Math.max(p1.y, p2.y);

      if (rotation !== 0) {
        const corners = [
          { x: minX, y: minY },
          { x: maxX, y: minY },
          { x: maxX, y: maxY },
          { x: minX, y: maxY },
        ];
        const rotatedCorners = corners.map((corner) =>
          canvasObjects.rotatePoint(corner, center, rotation),
        );
        return isPointInPolygon(point, rotatedCorners);
      }

      return (
        point.x >= minX - tolerance &&
        point.x <= maxX + tolerance &&
        point.y >= minY - tolerance &&
        point.y <= maxY + tolerance
      );
    }

    // ELLIPSES
    if (obj.type === "ellipse" && obj.points && obj.points.length >= 2) {
      const p1 = obj.points[0];
      const p2 = obj.points[1];

      const rx = Math.abs(p2.x - p1.x) / 2;
      const ry = Math.abs(p2.y - p1.y) / 2;
      const cx = (p1.x + p2.x) / 2;
      const cy = (p1.y + p2.y) / 2;

      const localPoint = canvasObjects.rotatePoint(
        point,
        { x: cx, y: cy },
        -rotation,
      );

      const dx = localPoint.x - cx;
      const dy = localPoint.y - cy;

      const ellipseValue = (dx * dx) / (rx * rx) + (dy * dy) / (ry * ry);
      const maxRadius = Math.max(rx, ry);

      return ellipseValue <= 1.0 + tolerance / maxRadius;
    }

    // BOOTHS
    if (obj.type === "booth") {
      if (obj.boundingBox) {
        const bbox = obj.boundingBox;

        if (rotation !== 0) {
          const corners = [
            { x: bbox.x, y: bbox.y },
            { x: bbox.x + bbox.width, y: bbox.y },
            { x: bbox.x + bbox.width, y: bbox.y + bbox.height },
            { x: bbox.x, y: bbox.y + bbox.height },
          ];
          const rotatedCorners = corners.map((corner) =>
            canvasObjects.rotatePoint(corner, center, rotation),
          );
          if (isPointInPolygon(point, rotatedCorners)) return true;
        } else {
          if (
            point.x >= bbox.x - tolerance &&
            point.x <= bbox.x + bbox.width + tolerance &&
            point.y >= bbox.y - tolerance &&
            point.y <= bbox.y + bbox.height + tolerance
          )
            return true;
        }

        // Also check if point is on any of the duplication arrows
        const screenPoint = worldToScreen(point, store.zoom, store.offset);
        if (getArrowAtPoint(screenPoint, obj, store.zoom, store.offset)) {
          return true;
        }
        return false;
      }

      if (obj.points && obj.points.length >= 2) {
        const p1 = obj.points[0];
        const p2 = obj.points[1];
        const minX = Math.min(p1.x, p2.x);
        const maxX = Math.max(p1.x, p2.x);
        const minY = Math.min(p1.y, p2.y);
        const maxY = Math.max(p1.y, p2.y);

        if (isPointInObjectPrecise(point, obj)) return true;

        // Also check if point is on any of the duplication arrows
        const screenPoint = worldToScreen(point, store.zoom, store.offset);
        if (getArrowAtPoint(screenPoint, obj, store.zoom, store.offset)) {
          return true;
        }
        return false;
      }
    }

    // WALLS (Multi-segment) - Check if it forms a closed shape
    if (obj.type === "wall" && obj.points && obj.points.length >= 2) {
      // Check if it's a closed shape (first and last points are close)
      const firstPoint = obj.points[0];
      const lastPoint = obj.points[obj.points.length - 1];
      const distanceToClose = Math.hypot(
        lastPoint.x - firstPoint.x,
        lastPoint.y - firstPoint.y,
      );
      const isClosed = distanceToClose < 5; // If distance < 5 units, consider it closed

      if (isClosed && obj.points.length >= 3) {
        // Closed shape - check if point is inside the polygon
        const isInside = isPointInPolygon(point, obj.points);
        if (isInside) return true;
      }

      // Otherwise check distance to line segments (for open walls or edge clicks)
      for (let i = 0; i < obj.points.length - 1; i++) {
        const distance = pointToLineDistance(
          point,
          obj.points[i],
          obj.points[i + 1],
        );
        if (distance <= tolerance) return true;
      }
      return false;
    }

    // LINES & ARROWS (always line-based, never filled)
    if (
      (obj.type === "line" ||
        obj.type === "arrow" ||
        obj.type === "two-headed-arrow") &&
      obj.points &&
      obj.points.length >= 2
    ) {
      const distance = pointToLineDistance(point, obj.points[0], obj.points[1]);
      return distance <= tolerance;
    }

    // PENCIL, CURVE-ARROW, and other complex paths - Use bounding box for selection
    if (["pencil", "curve-arrow"].includes(obj.type)) {
      if (bounds) {
        if (rotation !== 0) {
          const corners = [
            { x: bounds.x, y: bounds.y },
            { x: bounds.x + bounds.width, y: bounds.y },
            { x: bounds.x + bounds.width, y: bounds.y + bounds.height },
            { x: bounds.x, y: bounds.y + bounds.height },
          ];
          const rotatedCorners = corners.map((corner) =>
            canvasObjects.rotatePoint(corner, center, rotation),
          );
          return isPointInPolygon(point, rotatedCorners);
        }

        return (
          point.x >= bounds.x - tolerance &&
          point.x <= bounds.x + bounds.width + tolerance &&
          point.y >= bounds.y - tolerance &&
          point.y <= bounds.y + bounds.height + tolerance
        );
      }
    }

    // GENERIC FALLBACK
    if (bounds) {
      if (rotation !== 0) {
        const corners = [
          { x: bounds.x, y: bounds.y },
          { x: bounds.x + bounds.width, y: bounds.y },
          { x: bounds.x + bounds.width, y: bounds.y + bounds.height },
          { x: bounds.x, y: bounds.y + bounds.height },
        ];
        const rotatedCorners = corners.map((corner) =>
          canvasObjects.rotatePoint(corner, center, rotation),
        );
        return isPointInPolygon(point, rotatedCorners);
      }

      return (
        point.x >= bounds.x - tolerance &&
        point.x <= bounds.x + bounds.width + tolerance &&
        point.y >= bounds.y - tolerance &&
        point.y <= bounds.y + bounds.height + tolerance
      );
    }

    return false;
  };

  // Add this helper function for calculating distance from point to line segment
  const pointToLineDistance = (
    point: Point,
    lineStart: Point,
    lineEnd: Point,
  ): number => {
    const A = point.x - lineStart.x;
    const B = point.y - lineStart.y;
    const C = lineEnd.x - lineStart.x;
    const D = lineEnd.y - lineStart.y;

    const dot = A * C + B * D;
    const lenSq = C * C + D * D;
    let param = -1;

    if (lenSq !== 0) {
      param = dot / lenSq;
    }

    let xx, yy;

    if (param < 0) {
      xx = lineStart.x;
      yy = lineStart.y;
    } else if (param > 1) {
      xx = lineEnd.x;
      yy = lineEnd.y;
    } else {
      xx = lineStart.x + param * C;
      yy = lineStart.y + param * D;
    }

    const dx = point.x - xx;
    const dy = point.y - yy;
    return Math.sqrt(dx * dx + dy * dy);
  };

  const finishSelectionRectangle = () => {
    if (!drawingState.value.selectionStart || !drawingState.value.selectionEnd)
      return;

    const start = drawingState.value.selectionStart;
    const end = drawingState.value.selectionEnd;

    // Always clear if not multi-select, even for small rects (fix for quick clicks)
    if (!event.ctrlKey && !event.metaKey) {
      clearAllSelections();
    }

    const selBox = {
      x: Math.min(start.x, end.x),
      y: Math.min(start.y, end.y),
      width: Math.abs(end.x - start.x),
      height: Math.abs(end.y - start.y),
    };

    // If small click (no drag), skip selection but keep clear
    if (selBox.width < 5 && selBox.height < 5) {
      drawingState.value.isSelecting = false;
      drawingState.value.selectionStart = null;
      drawingState.value.selectionEnd = null;
      return;
    }

    // Proceed with selection (unchanged)
    const selectedObjects = store.objects.filter((obj) => {
      if (obj.type === "wall" && obj.id.includes("Floor-Wall")) {
        return false;
      }

      if (obj.isLocked) {
        return false;
      }

      if (obj.isVisible === false) {
        return false;
      }

      const objBox = canvasObjects.getRotatedBounding(obj);
      return objBox && canvasObjects.boxesIntersect(selBox, objBox);
    });

    selectedObjects.forEach((obj) => {
      obj.isSelected = true;
      store.selectedObjects.push(obj);
    });

    drawingState.value.isSelecting = false;
    drawingState.value.selectionStart = null;
    drawingState.value.selectionEnd = null;
  };

  const handleMouseUp = (event: MouseEvent) => {
    if (!canvasEl.value) return;

    const rect = canvasEl.value.getBoundingClientRect();
    const point = {
      x: event.clientX - rect.left,
      y: event.clientY - rect.top,
    };

    // ✅ NEW: Cancel pending drawing state if no drawing started
    if (drawingState.value.isDrawingPending) {
      drawingState.value.isDrawingPending = false;
      render();
      return;
    }

    if (store.currentTool === "select" && drawingState.value.isSelecting) {
      stopSelecting();
    } else if (store.currentTool === "hand") {
      stopPanning();
    } else {
      if (
        store.currentTool === "wall" &&
        drawingState.value.wallState.isDrawing &&
        continuousDrawingState.value.wallStateActive &&
        drawingState.value.wallState.currentPreviewPoint
      ) {
        addWallPoint(drawingState.value.wallState.currentPreviewPoint);
        if (drawingState.value.tempObject) {
          drawingState.value.tempObject.points = [
            ...drawingState.value.wallState.points,
          ];
        }
        drawingState.value.wallState.currentPreviewPoint =
          drawingState.value.wallState.points[
            drawingState.value.wallState.points.length - 1
          ];
        render();
        return;
      }

      stopDrawing(point, store.zoom, store.offset);
    }

    if (objectManipulation.interactionState.value.isRotating) {
      objectManipulation.stopRotating();
    }

    if (objectManipulation.interactionState.value.isDragging) {
      objectManipulation.stopDragging();
    }

    objectManipulation.stopObjectManipulation();
    objectManipulation.clearAlignmentGuides();

    render();
  };

  let wheelTimeout: NodeJS.Timeout | null = null;

  const handleWheel = (event: WheelEvent) => {
    event.preventDefault();

    if (!event.ctrlKey) return;

    if (wheelTimeout) return;
    wheelTimeout = setTimeout(() => {
      if (event.deltaY < 0) {
        store.zoomIn();
      } else {
        store.zoomOut();
      }
      render();
      wheelTimeout = null;
    }, 16);
  };

  // UPDATED: Key down handler with all keyboard shortcuts
  const handleKeyDown = (event: KeyboardEvent) => {
    if (!isStoreReady.value) return;

    const target = event.target as HTMLElement;
    const isTyping =
      target.tagName === "INPUT" ||
      target.tagName === "TEXTAREA" ||
      target.isContentEditable ||
      target.closest('[contenteditable="true"]') !== null;

    const { key, ctrlKey, metaKey, shiftKey, altKey } = event;
    const cmdKey = ctrlKey || metaKey;
    const lowerKey = key.toLowerCase();

    // Spacebar to select 'Hand' tool (Temporary Toggle)
    if (key === " " && !isTyping) {
      if (!isSpacePressed.value) {
        isSpacePressed.value = true;
        store.currentTool = "hand";
      }
      event.preventDefault();
      return;
    }

    // ✅ UPDATED: Tool selection shortcuts
    if (!cmdKey && !altKey && !isTyping) {
      let tool = "";
      let specialAction = false;

      switch (lowerKey) {
        case "h":
          if (shiftKey) {
            tool = "two-headed-arrow";
            console.log("🎯 Activated: Two-headed arrow draw state");
          } else {
            tool = "hand";
          }
          break;

        case "v":
          tool = "select";
          break;

        case "t":
          tool = "text";
          break;

        case "p":
          tool = "pencil";
          break;
        case "m":
          if (shiftKey) {
            tool = "rectangle";
            drawingState.value.isPerfectSquare = true; // ← KEEP THIS
            console.log("Activated: Perfect square rectangle mode (Shift+M)");
          } else {
            tool = "rectangle";
            drawingState.value.isPerfectSquare = false;
          }
          break;

        case "f":
          if (shiftKey) {
            tool = "frame";
            drawingState.value.isPerfectSquare = true;
            console.log("🎯 Activated: Square Frame mode (Shift+F)");
          } else {
            tool = "frame";
            drawingState.value.isPerfectSquare = false;
          }
          break;

        case "s":
          if (shiftKey) {
            tool = "section";
            drawingState.value.isPerfectSquare = true;
            console.log("🎯 Activated: Square Section mode (Shift+S)");
          } else {
            tool = "section";
            drawingState.value.isPerfectSquare = false;
          }
          break;

        case "o":
          if (shiftKey) {
            tool = "ellipse";
            drawingState.value.isPerfectCircle = true;
            console.log("Activated: Perfect circle mode (Shift+O)");
          } else {
            tool = "ellipse";
            drawingState.value.isPerfectCircle = false;
          }
          break;
        // ❌ REMOVED: Original circle tool case commented out
        // case "c":
        //   tool = "circle";
        //   break;

        case "w":
          if (shiftKey) {
            tool = "wall";
            continuousDrawingState.value.wallStateActive = true;
            console.log("🎯 Activated: State wall draw (continuous mode)");
          } else {
            tool = "wall";
            continuousDrawingState.value.wallStateActive = false;
            console.log("🎯 Activated: Wall draw mode");
          }
          break;

        case "l":
          if (cmdKey && shiftKey) {
            tool = "arrow";
            console.log("🎯 Activated: Arrow draw state");
          } else if (cmdKey) {
            tool = "line";
            continuousDrawingState.value.lineStateActive = true;
            console.log("🎯 Activated: State line draw (continuous mode)");
          } else if (shiftKey) {
            tool = "arrow";
          } else {
            tool = "line";
            continuousDrawingState.value.lineStateActive = false;
          }
          break;

        case "c":
          // ✅ CHANGED: C now only activates curve-arrow (no longer circle)
          tool = "curve-arrow";
          console.log("🎯 Activated: Curve arrow draw state");
          break;

        case "b":
        case "B":
          if (shiftKey) {
            const debounceKey = "shift+b";
            if (!keyDebounce.value.has(debounceKey)) {
              keyDebounce.value.add(debounceKey);
              createBoothFromSelected();
              event.preventDefault();
              setTimeout(() => keyDebounce.value.delete(debounceKey), 200);
            }
            return;
          }
          break;
      }

      if (tool) {
        store.currentTool = tool;
        event.preventDefault();
        return;
      }

      if (specialAction) {
        return;
      }
    }

    // Zoom shortcuts - Skip if typing
    // Zoom shortcuts - Skip if typing
    if (
      !isTyping &&
      (event.key === "+" || // Numpad + OR main keyboard Shift + = (both give "+")
        event.key === "Add" || // Legacy fallback for very old browsers (numpad +)
        event.key === "-" || // Main keyboard -
        event.key === "Subtract") // Numpad -
    ) {
      const isZoomIn = event.key === "+" || event.key === "Add";

      const factor = isZoomIn ? 1.1 : 0.9;
      const newZoom = store.zoom * factor;

      if (canvasEl.value) {
        const rect = canvasEl.value.getBoundingClientRect();
        const centerScreen = { x: rect.width / 2, y: rect.height / 2 };
        const worldCenter = screenToWorld(
          centerScreen,
          store.zoom,
          store.offset,
        );
        store.zoom = newZoom;
        const newScreenCenter = worldToScreen(
          worldCenter,
          store.zoom,
          store.offset,
        );
        store.offset.x += (newScreenCenter.x - centerScreen.x) / newZoom;
        store.offset.y += (newScreenCenter.y - centerScreen.y) / newZoom;
      }
      event.preventDefault();
      render();
      return;
    }

    // Clipboard, undo/redo with DEBOUNCE
    if (cmdKey) {
      switch (lowerKey) {
        case "z":
          if (!keyDebounce.value.has("z")) {
            keyDebounce.value.add("z");
            if (shiftKey) {
              store.redo();
            } else {
              store.undo();
            }
            event.preventDefault();
          }
          break;
        case "y":
          if (!keyDebounce.value.has("y")) {
            keyDebounce.value.add("y");
            store.redo();
            event.preventDefault();
          }
          break;
        case "c":
          if (!keyDebounce.value.has("c")) {
            keyDebounce.value.add("c");
            copySelected();
            event.preventDefault();
          }
          break;
        case "v":
          if (!keyDebounce.value.has("v")) {
            keyDebounce.value.add("v");
            pasteCopied();
            event.preventDefault();
          }
          break;
        case "x":
          if (!keyDebounce.value.has("x")) {
            keyDebounce.value.add("x");
            copySelected();
            deleteSelected();
            event.preventDefault();
          }
          break;
        case "l":
          if (!keyDebounce.value.has("l")) {
            keyDebounce.value.add("l");
            if (cmdKey && shiftKey && altKey) {
              unlockSelected();
            } else if (cmdKey && !shiftKey && !altKey) {
              lockSelected();
            }
            event.preventDefault();
          }
          break;
      }
      render();
      return;
    }

    // Delete selected objects/elements
    if ((key === "Delete" || (isMac && key === "Backspace")) && !isTyping) {
      deleteSelected();
      event.preventDefault();
      render();
      return;
    }

    // ✅ UPDATED: Escape key handling with state clearing
    if (
      event.key === "Escape" &&
      store.currentTool === "curve-arrow" &&
      drawingState.value.curveArrowState.isDrawing
    ) {
      cancelCurveArrowDrawing();
      render();
    } else if (
      event.key === "Escape" &&
      store.currentTool === "wall" &&
      drawingState.value.wallState.isDrawing
    ) {
      cancelWallDrawing();
      render();
    } else if (
      event.key === "Escape" &&
      store.currentTool === "two-headed-arrow" &&
      drawingState.value.twoHeadedArrowState.isDrawing
    ) {
      cancelTwoHeadedArrowDrawing();
      render();
    } else if (event.key === "Escape") {
      // ✅ NEW: Clear any active drawing state
      continuousDrawingState.value.wallStateActive = false;
      continuousDrawingState.value.lineStateActive = false;
      drawingState.value.isPerfectSquare = false;
    }
  };

  // NEW: Key up handler
  const handleKeyUp = (event: KeyboardEvent) => {
    // Spacebar handling: Switch back to 'Select' on release
    if (event.key === " ") {
      isSpacePressed.value = false;
      store.currentTool = "select";
      isPanning.value = false;
    }

    // // ✅ NEW: Reset special drawing states when Shift is released
    // if (event.key === "Shift") {
    //   // Perfect square mode off
    //   drawingState.value.isPerfectSquare = false;

    //   // Continuous wall/line modes off
    //   continuousDrawingState.value.wallStateActive = false;
    //   continuousDrawingState.value.lineStateActive = false;

    //   console.log("🔄 Shift released → special drawing modes reset");
    // }

    if (event.key === "Shift") {
      drawingState.value.isPerfectSquare = false;
      drawingState.value.isPerfectCircle = false; // Reset ellipse perfect mode
      continuousDrawingState.value.wallStateActive = false;
      continuousDrawingState.value.lineStateActive = false;
      console.log("🔄 Shift released → special drawing modes reset");
    }

    // Clear key debounce (existing)
    const lowerKey = event.key.toLowerCase();
    if (keyDebounce.value.has(lowerKey)) {
      keyDebounce.value.delete(lowerKey);
    }
  };

  // Panning logic
  const startPanning = (point: Point) => {
    if (store.currentTool !== "hand") return;
    isPanning.value = true;
    lastMousePos.value = point;
  };

  const doPanning = (point: Point) => {
    const dx = (point.x - lastMousePos.value.x) / store.zoom;
    const dy = (point.y - lastMousePos.value.y) / store.zoom;
    store.offset.x -= dx;
    store.offset.y -= dy;
    lastMousePos.value = point;
  };

  const stopPanning = () => {
    isPanning.value = false;
  };

  // Drawing functions (keeping existing implementations)
  const startDrawing = (
    point: Point,
    zoom: number,
    offset: Point,
    isDoubleClick: boolean = false,
  ) => {
    // If we have a pending start point (from handleMouseDown), use it
    // otherwise convert current screen point to world.
    const worldPoint =
      drawingState.value.isDrawingPending && drawingState.value.startPoint
        ? drawingState.value.startPoint
        : screenToWorld(point, zoom, offset);

    if (
      isDoubleClick &&
      store.currentTool === "curve-arrow" &&
      drawingState.value.curveArrowState.isDrawing
    ) {
      finishCurveArrowDrawing();
      return;
    } else if (
      isDoubleClick &&
      store.currentTool === "wall" &&
      drawingState.value.wallState.isDrawing
    ) {
      finishWallDrawing();
      return;
    }

    drawingState.value.isDrawing = true;
    drawingState.value.startPoint = worldPoint;
    drawingState.value.currentPoint = worldPoint;

    switch (store.currentTool) {
      case "pencil":
        startPencilDrawing(worldPoint);
        break;
      case "line":
      case "arrow":
        startLineDrawing(worldPoint);
        break;
      case "rectangle":
      case "ellipse":
      case "frame":
      case "section":
        startShapeDrawing(worldPoint);
        break;
    }
  };

  const finishLabelEdit = (newLabel: string) => {
    const { objId } = drawingState.value.labelEditing;
    if (objId) {
      store.updateObject(objId, { label: newLabel });
    }
    drawingState.value.labelEditing.objId = null;
    render();
  };

  const draw = (
    point: Point,
    zoom: number,
    offset: Point,
    shiftKey: boolean = false,
  ) => {
    const worldPoint = screenToWorld(point, zoom, offset);
    drawingState.value.currentPoint = worldPoint;

    if (
      store.currentTool === "wall" &&
      drawingState.value.wallState.isDrawing
    ) {
      let previewPoint = worldPoint;
      if (shiftKey && drawingState.value.wallState.points.length > 0) {
        const lastPoint =
          drawingState.value.wallState.points[
            drawingState.value.wallState.points.length - 1
          ];
        const dx = Math.abs(worldPoint.x - lastPoint.x);
        const dy = Math.abs(worldPoint.y - lastPoint.y);
        if (dx > dy) previewPoint = { x: worldPoint.x, y: lastPoint.y };
        else previewPoint = { x: lastPoint.x, y: worldPoint.y };
      }
      drawingState.value.wallState.currentPreviewPoint = previewPoint;
      updateWallPreview();
      return;
    }

    if (
      store.currentTool === "two-headed-arrow" &&
      drawingState.value.twoHeadedArrowState.isDrawing
    ) {
      let previewPoint = worldPoint;
      if (
        shiftKey &&
        drawingState.value.twoHeadedArrowState.points.length > 0
      ) {
        const lastPoint =
          drawingState.value.twoHeadedArrowState.points[
            drawingState.value.twoHeadedArrowState.points.length - 1
          ];
        const dx = Math.abs(worldPoint.x - lastPoint.x);
        const dy = Math.abs(worldPoint.y - lastPoint.y);
        if (dx > dy) previewPoint = { x: worldPoint.x, y: lastPoint.y };
        else previewPoint = { x: lastPoint.x, y: worldPoint.y };
      }
      drawingState.value.twoHeadedArrowState.currentPreviewPoint = previewPoint;
      return;
    }

    if (!drawingState.value.isDrawing) return;

    switch (store.currentTool) {
      case "pencil":
        continuePencilDrawing(worldPoint);
        break;
      case "line":
      case "arrow":
        continueLineDrawing(worldPoint, shiftKey);
        break;
      case "curve-arrow":
        if (drawingState.value.curveArrowState.isDrawing) {
          drawingState.value.curveArrowState.currentPreviewPoint = worldPoint;
          updateCurveArrowPreview();
        }
        break;
      case "rectangle":
      case "ellipse":
      case "frame":
      case "section":
        continueShapeDrawing(worldPoint, shiftKey);
        break;
    }
  };

  const stopDrawing = (point: Point, zoom: number, offset: Point) => {
    const worldPoint = screenToWorld(point, zoom, offset);
    drawingState.value.currentPoint = worldPoint;

    // ✅ EXISTING: Wall এবং two-headed-arrow-এর জন্য early return আছে
    // কিন্তু এখানে আমরা চাই যে continuous mode-এ থাকলে finalize করতে হবে
    if (
      store.currentTool === "wall" &&
      drawingState.value.wallState.isDrawing
    ) {
      // ✅ NEW: Continuous mode-এ থাকলে mouse up-এ point add করো এবং preview finalize করো
      if (continuousDrawingState.value.wallStateActive) {
        if (drawingState.value.wallState.currentPreviewPoint) {
          // শেষ preview point-কে actual point হিসেবে যোগ করো
          addWallPoint(drawingState.value.wallState.currentPreviewPoint);
          // tempObject update করো
          if (drawingState.value.tempObject) {
            drawingState.value.tempObject.points = [
              ...drawingState.value.wallState.points,
            ];
          }
        }
      }
      // existing return রাখো, যাতে নিচের general logic না চলে
      return;
    }

    if (
      store.currentTool === "two-headed-arrow" &&
      drawingState.value.twoHeadedArrowState.isDrawing
    ) {
      return;
    }

    if (drawingState.value.isDrawing && drawingState.value.tempObject) {
      // General case (line, arrow, pencil etc.)
      if (
        ["line", "arrow", "rectangle", "ellipse", "frame", "section"].includes(
          store.currentTool,
        )
      ) {
        drawingState.value.tempObject.points[1] =
          drawingState.value.tempObject.points[1];
      } else if (store.currentTool === "pencil") {
        const points = drawingState.value.tempObject.points;
        const lastPoint = points[points.length - 1];

        // Only add final point if it's not identical to the last one
        if (
          !lastPoint ||
          Math.hypot(worldPoint.x - lastPoint.x, worldPoint.y - lastPoint.y) >
            0.1
        ) {
          points.push(worldPoint);
        }

        // ✅ CRITICAL FIX: Bake the bounding box immediately upon finishing the drawing.
        // This ensures the selection box is instantly attached and valid.
        if (points.length > 0) {
          let minX = Infinity,
            minY = Infinity,
            maxX = -Infinity,
            maxY = -Infinity;
          for (const p of points) {
            if (p.x < minX) minX = p.x;
            if (p.y < minY) minY = p.y;
            if (p.x > maxX) maxX = p.x;
            if (p.y > maxY) maxY = p.y;
          }
          drawingState.value.tempObject.boundingBox = {
            x: minX,
            y: minY,
            width: maxX - minX,
            height: maxY - minY,
          };
        }
      }

      store.addObject(drawingState.value.tempObject);
      drawingState.value.tempObject = null;
    }

    drawingState.value.isDrawing = false;
  };

  // Pencil functions
  const startPencilDrawing = (point: Point) => {
    drawingState.value.tempObject = {
      id: Date.now().toString(),
      type: "pencil",
      points: [point],
      color: store.currentColor,
      isSelected: false,
      rotation: 0,
    };
  };

  const continuePencilDrawing = (point: Point) => {
    if (drawingState.value.tempObject) {
      const points = drawingState.value.tempObject.points;
      const lastPoint = points[points.length - 1];

      if (!lastPoint) {
        points.push(point);
        return;
      }

      // ✅ SMOOTHNESS: Only add point if it's far enough from the last point (input smoothing)
      // This reduces jitter and makes the splines look much better
      const dist = Math.hypot(point.x - lastPoint.x, point.y - lastPoint.y);
      const minDistance = 2 / store.zoom; // Adjust threshold based on zoom

      if (dist > minDistance) {
        // Weighted average for real-time smoothing (Low-pass filter)
        // Adjust these weights (e.g., 0.2 and 0.8) to tune the "flow" feel
        const smoothedPoint = {
          x: lastPoint.x * 0.2 + point.x * 0.8,
          y: lastPoint.y * 0.2 + point.y * 0.8,
        };
        points.push(smoothedPoint);
      }
    }
  };

  // Line functions
  const startLineDrawing = (point: Point) => {
    drawingState.value.tempObject = {
      id: Date.now().toString(),
      type: store.currentTool,
      points: [point, point],
      color: store.currentColor,
      isSelected: false,
      rotation: 0,
    };
  };

  const continueLineDrawing = (point: Point, shiftKey: boolean) => {
    if (drawingState.value.tempObject) {
      let endPoint = point;
      if (shiftKey) {
        const start = drawingState.value.tempObject.points[0];
        const dx = point.x - start.x;
        const dy = point.y - start.y;
        const angle = Math.atan2(dy, dx);
        const snappedAngle = Math.round(angle / (Math.PI / 4)) * (Math.PI / 4);
        const length = Math.hypot(dx, dy);
        endPoint = {
          x: start.x + length * Math.cos(snappedAngle),
          y: start.y + length * Math.sin(snappedAngle),
        };
      }
      drawingState.value.tempObject.points[1] = endPoint;
    }
  };

  // Curve Arrow functions
  const startCurveArrowDrawing = (point: Point) => {
    drawingState.value.curveArrowState.isDrawing = true;
    drawingState.value.curveArrowState.points = [point];
    drawingState.value.tempObject = {
      id: Date.now().toString(),
      type: "curve-arrow",
      points: [point],
      color: store.currentColor,
      isSelected: false,
      rotation: 0,
    };
  };

  const addCurveArrowPoint = (point: Point) => {
    drawingState.value.curveArrowState.points.push(point);
    if (drawingState.value.tempObject) {
      drawingState.value.tempObject.points = [
        ...drawingState.value.curveArrowState.points,
      ];
    }
  };

  const updateCurveArrowPreview = () => {
    if (
      drawingState.value.tempObject &&
      drawingState.value.curveArrowState.points.length >= 1 &&
      drawingState.value.curveArrowState.currentPreviewPoint
    ) {
      drawingState.value.tempObject.points = [
        ...drawingState.value.curveArrowState.points,
        drawingState.value.curveArrowState.currentPreviewPoint,
      ];
    }
  };

  const cancelCurveArrowDrawing = () => {
    drawingState.value.curveArrowState.isDrawing = false;
    drawingState.value.curveArrowState.points = [];
    drawingState.value.curveArrowState.currentPreviewPoint = null;
    drawingState.value.tempObject = null;
  };

  const finishCurveArrowDrawing = () => {
    if (
      drawingState.value.tempObject &&
      drawingState.value.curveArrowState.isDrawing
    ) {
      drawingState.value.tempObject.points = [
        ...drawingState.value.curveArrowState.points,
      ];
      store.addObject(drawingState.value.tempObject);
      drawingState.value.tempObject = null;
    }
    drawingState.value.curveArrowState.isDrawing = false;
    drawingState.value.curveArrowState.points = [];
    drawingState.value.curveArrowState.currentPreviewPoint = null;
  };

  // Wall functions
  const startWallDrawing = (point: Point) => {
    drawingState.value.wallState.isDrawing = true;
    drawingState.value.wallState.points = [point];
    drawingState.value.tempObject = {
      id: Date.now().toString(),
      type: "wall",
      points: [point],
      color: store.currentColor,
      isSelected: false,
      rotation: 0,
    };
  };

  const addWallPoint = (point: Point) => {
    drawingState.value.wallState.points.push(point);
    if (drawingState.value.tempObject) {
      drawingState.value.tempObject.points = [
        ...drawingState.value.wallState.points,
      ];
    }
  };

  const updateWallPreview = () => {
    if (
      drawingState.value.tempObject &&
      drawingState.value.wallState.points.length >= 1 &&
      drawingState.value.wallState.currentPreviewPoint
    ) {
      drawingState.value.tempObject.points = [
        ...drawingState.value.wallState.points,
        drawingState.value.wallState.currentPreviewPoint,
      ];
    }
  };

  const cancelWallDrawing = () => {
    drawingState.value.wallState.isDrawing = false;
    drawingState.value.wallState.points = [];
    drawingState.value.wallState.currentPreviewPoint = null;
    drawingState.value.tempObject = null;
  };

  const finishWallDrawing = () => {
    if (
      drawingState.value.tempObject &&
      drawingState.value.wallState.isDrawing
    ) {
      const points = drawingState.value.wallState.points;

      if (points.length >= 2) {
        drawingState.value.tempObject.points = [
          ...drawingState.value.wallState.points,
        ];
        store.addObject(drawingState.value.tempObject);
      }

      drawingState.value.tempObject = null;
    }

    drawingState.value.wallState.isDrawing = false;
    drawingState.value.wallState.points = [];
    drawingState.value.wallState.currentPreviewPoint = null;
  };

  // Snapping helper for drawing
  const snapToObjects = (point: Point): Point => {
    const SNAP_THRESHOLD = 30 / store.zoom; // Increased threshold for easier snapping

    let closestPoint = { ...point };
    let minDistance = SNAP_THRESHOLD;

    const checkPoint = (target: Point) => {
      const dist = Math.hypot(target.x - point.x, target.y - point.y);
      if (dist < minDistance) {
        minDistance = dist;
        closestPoint = { ...target };
      }
    };

    // Check Canvas Objects
    for (const obj of store.objects) {
      if (obj.isLocked || obj.isVisible === false) continue;

      // 1. Check explicit points (vertices)
      if (obj.points) {
        for (const p of obj.points) {
          checkPoint(p);
        }
      }

      // 2. Check bounding box corners AND midpoints for shapes
      if (["rectangle", "image", "booth", "text", "wall"].includes(obj.type)) {
        const bounds = canvasObjects.getRotatedBounding(obj);
        if (bounds) {
          const vertices = [
            { x: bounds.x, y: bounds.y }, // Top-Left
            { x: bounds.x + bounds.width, y: bounds.y }, // Top-Right
            { x: bounds.x + bounds.width, y: bounds.y + bounds.height }, // Bottom-Right
            { x: bounds.x, y: bounds.y + bounds.height }, // Bottom-Left
            // Midpoints
            { x: bounds.x + bounds.width / 2, y: bounds.y }, // Top-Mid
            { x: bounds.x + bounds.width, y: bounds.y + bounds.height / 2 }, // Right-Mid
            { x: bounds.x + bounds.width / 2, y: bounds.y + bounds.height }, // Bottom-Mid
            { x: bounds.x, y: bounds.y + bounds.height / 2 }, // Left-Mid
          ];
          for (const p of vertices) checkPoint(p);
        }
      }
    }

    // Check DOM Elements
    for (const el of store.domElements) {
      if (el.isLocked || el.isVisible === false) continue;
      const vertices = [
        { x: el.position.x, y: el.position.y },
        { x: el.position.x + el.size.width, y: el.position.y },
        { x: el.position.x + el.size.width, y: el.position.y + el.size.height },
        { x: el.position.x, y: el.position.y + el.size.height },
        // Midpoints
        { x: el.position.x + el.size.width / 2, y: el.position.y },
        {
          x: el.position.x + el.size.width,
          y: el.position.y + el.size.height / 2,
        },
        {
          x: el.position.x + el.size.width / 2,
          y: el.position.y + el.size.height,
        },
        { x: el.position.x, y: el.position.y + el.size.height / 2 },
      ];
      for (const p of vertices) checkPoint(p);
    }

    return closestPoint;
  };

  const startTwoHeadedArrowDrawing = (point: Point) => {
    // User requested absolute start position with no snapping
    drawingState.value.twoHeadedArrowState.isDrawing = true;
    drawingState.value.twoHeadedArrowState.points = [point];
    drawingState.value.twoHeadedArrowState.currentPreviewPoint = point;
  };

  const continueTwoHeadedArrowDrawing = (
    point: Point,
    shiftKey: boolean = false,
  ) => {
    if (drawingState.value.twoHeadedArrowState.isDrawing) {
      if (
        shiftKey &&
        drawingState.value.twoHeadedArrowState.points.length > 0
      ) {
        // Shift logic: Snap to object corner first, then constrain orthogonally
        const rawSnap = snapToObjects(point);
        const start = drawingState.value.twoHeadedArrowState.points[0];
        const dx = Math.abs(point.x - start.x);
        const dy = Math.abs(point.y - start.y);

        if (dx > dy) {
          // Horizontal: Keep Snap X, Force Start Y
          drawingState.value.twoHeadedArrowState.currentPreviewPoint = {
            x: rawSnap.x,
            y: start.y,
          };
        } else {
          // Vertical: Force Start X, Keep Snap Y
          drawingState.value.twoHeadedArrowState.currentPreviewPoint = {
            x: start.x,
            y: rawSnap.y,
          };
        }
      } else {
        // Normal behavior
        const snappedPoint = snapToObjects(point);
        drawingState.value.twoHeadedArrowState.currentPreviewPoint =
          snappedPoint;
      }
    }
  };

  const finishTwoHeadedArrowDrawing = () => {
    if (
      drawingState.value.twoHeadedArrowState.isDrawing &&
      drawingState.value.twoHeadedArrowState.points.length === 1 &&
      drawingState.value.twoHeadedArrowState.currentPreviewPoint
    ) {
      const [start] = drawingState.value.twoHeadedArrowState.points;
      const end = drawingState.value.twoHeadedArrowState.currentPreviewPoint;

      const obj: CanvasObject = {
        id: Date.now().toString(),
        type: "two-headed-arrow",
        points: [start, end],
        color: store.currentColor,
        strokeWidth: 2,
        isSelected: false,
        rotation: 0,
      };

      store.addObject(obj);
    }

    cancelTwoHeadedArrowDrawing();
  };

  const cancelTwoHeadedArrowDrawing = () => {
    drawingState.value.twoHeadedArrowState = {
      isDrawing: false,
      points: [],
      currentPreviewPoint: null,
    };
  };

  // Shape functions
  const startShapeDrawing = (point: Point) => {
    const type = store.currentTool;
    let label = "";
    if (type === "frame") label = "Frame";
    else if (type === "section") label = "Hall";

    const isContainer = type === "frame" || type === "section";

    drawingState.value.tempObject = {
      id: Date.now().toString(),
      type: type,
      points: [point, point],
      color: store.currentColor,
      strokeWidth: 2,
      isSelected: false,
      rotation: 0,
      label: label,
      labelVisible: true,
      // Default container styles
      fillColor: "transparent",
      strokeColor: store.currentColor,
      cornerRadius: 0,
      opacity: 1,
    };
  };

  const continueShapeDrawing = (point: Point, shiftKey: boolean) => {
    if (drawingState.value.tempObject) {
      let endPoint = point;

      // ✅ EXACT LOGIC: Check if Shift is held OR state mode is active (same as Shift+M/Shift+O)
      // For rectangle: Check isPerfectSquare state OR current shiftKey press
      if (
        (shiftKey || drawingState.value.isPerfectSquare) &&
        ["rectangle", "frame", "section"].includes(store.currentTool)
      ) {
        const start = drawingState.value.tempObject.points[0];
        const dx = point.x - start.x;
        const dy = point.y - start.y;
        const size = Math.max(Math.abs(dx), Math.abs(dy));
        endPoint = {
          x: start.x + size * Math.sign(dx),
          y: start.y + size * Math.sign(dy),
        };
      }
      // ✅ EXACT LOGIC: For ellipse/circle: Check isPerfectCircle state OR current shiftKey press
      else if (
        (shiftKey || drawingState.value.isPerfectCircle) &&
        store.currentTool === "ellipse"
      ) {
        const start = drawingState.value.tempObject.points[0];
        const dx = point.x - start.x;
        const dy = point.y - start.y;
        const radius = Math.max(Math.abs(dx), Math.abs(dy));
        endPoint = {
          x: start.x + radius * Math.sign(dx),
          y: start.y + radius * Math.sign(dy),
        };
      }

      drawingState.value.tempObject.points[1] = endPoint;
    }
  };

  // Selection functions
  const startSelecting = (point: Point, zoom: number, offset: Point) => {
    const worldPoint = screenToWorld(point, zoom, offset);
    drawingState.value.isSelecting = true;
    drawingState.value.selectionStart = worldPoint;
    drawingState.value.selectionEnd = worldPoint;
  };

  const continueSelecting = (point: Point, zoom: number, offset: Point) => {
    if (!drawingState.value.isSelecting) return;
    drawingState.value.selectionEnd = screenToWorld(point, zoom, offset);
  };

  const stopSelecting = () => {
    if (!drawingState.value.selectionStart || !drawingState.value.selectionEnd)
      return;

    const start = drawingState.value.selectionStart;
    const end = drawingState.value.selectionEnd;
    const selBox = {
      x: Math.min(start.x, end.x),
      y: Math.min(start.y, end.y),
      width: Math.abs(end.x - start.x),
      height: Math.abs(end.y - start.y),
    };

    clearAllSelections();

    const selectedObjects = store.objects.filter((obj) => {
      if (obj.type === "wall" && obj.id.includes("Floor-Wall")) {
        return false;
      }

      if (obj.isLocked) {
        return false;
      }

      if (obj.isVisible === false) {
        return false;
      }

      const objBox = canvasObjects.getRotatedBounding(obj);
      return objBox && canvasObjects.boxesIntersect(selBox, objBox);
    });

    selectedObjects.forEach((obj) => {
      obj.isSelected = true;
      store.selectedObjects.push(obj);
    });

    drawingState.value.isSelecting = false;
    drawingState.value.selectionStart = null;
    drawingState.value.selectionEnd = null;
  };

  // Updated render function in useCanvasEngine.ts
  const render = () => {
    if (!ctx.value || !canvasEl.value) return;

    clearCanvas();

    // 1. Draw grid first (if enabled)
    if (uiStore.showGuides) {
      drawGrid(ctx.value, canvasEl.value, store.zoom, store.offset);
    }

    ctx.value.strokeStyle = store.currentColor;
    ctx.value.lineWidth = 2;

    // 2. Render all objects
    store.objects.forEach((obj) => {
      // Use the isHovered property set during mouse move
      const isHovered = obj.isHovered || false;

      canvasRendering.renderObject(
        ctx.value!,
        obj,
        store.zoom,
        store.offset,
        isHovered,
      );
    });

    // 3. Render temporary drawing object
    if (drawingState.value.tempObject) {
      canvasRendering.renderObject(
        ctx.value!,
        drawingState.value.tempObject,
        store.zoom,
        store.offset,
      );
    }

    // 4. Render previews
    if (ctx.value) {
      renderTwoHeadedArrowLinePreview(ctx.value, drawingState);
      renderWallPreview(ctx.value, drawingState);
    }

    // 5. Render Figma-style smart alignment guides
    if (
      objectManipulation.isDraggingForRuler.value ||
      objectManipulation.alignmentGuides.value.length > 0
    ) {
      // A. Render the alignment guide lines and grouped markers
      optimizedGuideRenderer.renderLocalGuides(
        ctx.value!,
        objectManipulation.alignmentGuides.value,
        store.zoom,
        store.offset,
      );

      // B. CRUCIAL ADDITION: Render the individual 'x' marks on the aligned element edges
      optimizedGuideRenderer.renderAlignedElementMarks(
        ctx.value!,
        objectManipulation.alignedElements.value,
        store.objects,
        store.zoom,
        store.offset,
      );
    }

    // 6. Selection rectangle
    if (
      drawingState.value.isSelecting &&
      drawingState.value.selectionStart &&
      drawingState.value.selectionEnd
    ) {
      const start = worldToScreen(
        drawingState.value.selectionStart,
        store.zoom,
        store.offset,
      );
      const end = worldToScreen(
        drawingState.value.selectionEnd,
        store.zoom,
        store.offset,
      );
      ctx.value.strokeStyle = store.currentColor;
      ctx.value.lineWidth = 1;
      ctx.value.setLineDash([5, 5]);
      ctx.value.strokeRect(
        Math.min(start.x, end.x),
        Math.min(start.y, end.y),
        Math.abs(end.x - start.x),
        Math.abs(end.y - start.y),
      );
      ctx.value.setLineDash([]);
      ctx.value.strokeStyle = store.currentColor;
    }

    // 7. Render selection handles
    store.selectedObjects.forEach((obj) => renderSelection(ctx.value, obj));

    // 8. Render hover measurements
    canvasRendering.renderHoverMeasurements(
      ctx.value,
      store.zoom,
      store.offset,
    );
  };

  watchEffect(() => {
    requestThrottledRender(render, 16);
  });

  onMounted(() => {
    if (canvasEl.value) {
      setupCanvas(canvasEl.value);
      canvasEl.value.focus();
      render();
    }
    window.addEventListener("keydown", handleKeyDown);
    window.addEventListener("keyup", handleKeyUp); // NEW
    window.addEventListener("resize", resizeCanvas);
  });

  onUnmounted(() => {
    window.removeEventListener("keydown", handleKeyDown);
    window.removeEventListener("keyup", handleKeyUp); // NEW
    window.removeEventListener("resize", resizeCanvas);
  });

  return {
    handleDoubleClick: (event: MouseEvent) => {
      if (isStoreReady.value) handleDoubleClick(event);
    },
    handleMouseDown: (event: MouseEvent) => {
      if (isStoreReady.value) handleMouseDown(event);
    },
    handleMouseMove: (event: MouseEvent) => {
      if (isStoreReady.value) handleMouseMove(event);
    },
    handleMouseUp,
    handleWheel,
    handleKeyDown,
    drawingState,
    setupCanvas,
    resizeCanvas,
    interactionState: objectManipulation.interactionState,
    isPanning,
    isPointInPolygon,
    // ✅ NEW: Export the booth number generator
    generateUniqueBoothNumber: (originalNumber: string) =>
      generateUniqueBoothNumber(store, originalNumber),
    finishLabelEdit, // NEW
    render,
  };
}
