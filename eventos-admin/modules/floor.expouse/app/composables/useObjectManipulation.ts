// useObjectManipulation.ts - WITH RESIZE ALIGNMENT GUIDES
import { ref, onMounted, onUnmounted } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useCanvasObjects } from "@floorplan/composables/useCanvasObjects";
import { useOptimizedDragging } from "@floorplan/composables/useOptimizedDragging";
import type { Point, CanvasObject, Command } from "@floorplan/types/canvas";

export function useObjectManipulation() {
  const store = useCanvasStore();
  const canvasObjects = useCanvasObjects();
  const dragging = useOptimizedDragging();

  const dragStartPositions = ref<
    Map<
      string,
      {
        points: Point[];
        boundingBox?: { x: number; y: number; width: number; height: number };
        position?: Point; // For DOM elements
      }
    >
  >(new Map());

  const interactionState = ref({
    isDragging: false,
    isResizing: false,
    isRotating: false,
    dragStartPoint: null as Point | null,
    draggingObject: null as CanvasObject | null,
    lastPos: { x: 0, y: 0 } as Point,
    resizeHandleIndex: null as number | null,
    currentCursor: "default",
    rotationCenter: null as Point | null,
    rotationStartAngle: 0,
    initialRotation: 0,
    originalObjects: [] as CanvasObject[],
    resizeOrigin: null as Point | null,
    initialBounds: null as {
      x: number;
      y: number;
      width: number;
      height: number;
    } | null,
    rotationStartPoint: null as Point | null,
    lastRotationAngle: 0,
    isRounding: false,
    roundingHandleIndex: null as number | null,
    initialCornerRadius: 0,
    hasDuplicated: false,
  });

  const isShiftPressed = ref(false);

  const canvasDimensions = ref<{ width: number; height: number }>({
    width: 0,
    height: 0,
  });

  const keyboardState = ref({
    directions: { x: 0, y: 0 },
    speed: 1,
    baseStep: 2,
    isActive: false,
    animationFrameId: null as number | null,
    startPositions: new Map<string, { points: Point[]; position?: Point }>(),
  });

  onMounted(() => {
    window.addEventListener("keydown", handleKeyDown);
    window.addEventListener("keyup", handleKeyUp);
  });

  onUnmounted(() => {
    window.removeEventListener("keydown", handleKeyDown);
    window.removeEventListener("keyup", handleKeyUp);
    stopKeyboardMovement();
  });

  const handleKeyDown = (e: KeyboardEvent) => {
    if (e.key === "Shift") {
      isShiftPressed.value = true;
      return;
    }

    if (!e.key.startsWith("Arrow")) return;

    let directionChanged = false;

    switch (e.key) {
      case "ArrowLeft":
        keyboardState.value.directions.x = -1;
        directionChanged = true;
        break;
      case "ArrowRight":
        keyboardState.value.directions.x = 1;
        directionChanged = true;
        break;
      case "ArrowUp":
        keyboardState.value.directions.y = -1;
        directionChanged = true;
        break;
      case "ArrowDown":
        keyboardState.value.directions.y = 1;
        directionChanged = true;
        break;
    }

    if (directionChanged && store.selectedObjects.length > 0) {
      e.preventDefault();
      if (!keyboardState.value.isActive) {
        startKeyboardMovement();
      }
    }
  };

  const handleKeyUp = (e: KeyboardEvent) => {
    if (e.key === "Shift") {
      isShiftPressed.value = false;
      return;
    }

    let directionChanged = false;

    switch (e.key) {
      case "ArrowLeft":
        keyboardState.value.directions.x = 0;
        directionChanged = true;
        break;
      case "ArrowRight":
        keyboardState.value.directions.x = 0;
        directionChanged = true;
        break;
      case "ArrowUp":
        keyboardState.value.directions.y = 0;
        directionChanged = true;
        break;
      case "ArrowDown":
        keyboardState.value.directions.y = 0;
        directionChanged = true;
        break;
    }

    if (
      directionChanged &&
      keyboardState.value.directions.x === 0 &&
      keyboardState.value.directions.y === 0
    ) {
      stopKeyboardMovement();
    }
  };

  const startKeyboardMovement = () => {
    keyboardState.value.isActive = true;
    keyboardState.value.speed = 1;

    // NEW: Add these lines to capture initial positions
    keyboardState.value.startPositions.clear();
    store.selectedObjects.forEach((obj) => {
      keyboardState.value.startPositions.set(obj.id, {
        points: obj.points.map((p) => ({ x: p.x, y: p.y })),
      });
    });

    store.selectedDomElements.forEach((el) => {
      keyboardState.value.startPositions.set(el.id, {
        points: [],
        position: { x: el.position.x, y: el.position.y },
      });
    });

    // Rest of existing code stays the same
    const animate = () => {
      if (!keyboardState.value.isActive) return;

      const effectiveSpeed =
        keyboardState.value.speed * keyboardState.value.baseStep;
      let deltaX = keyboardState.value.directions.x * effectiveSpeed;
      let deltaY = keyboardState.value.directions.y * effectiveSpeed;

      if (isShiftPressed.value) {
        deltaX *= 3;
        deltaY *= 3;
      }

      if (deltaX !== 0 || deltaY !== 0) {
        dragging.moveByKeyboard(deltaX, deltaY);
      }

      if (keyboardState.value.speed < 5) {
        keyboardState.value.speed += 0.05;
      }

      keyboardState.value.animationFrameId = requestAnimationFrame(animate);
    };

    keyboardState.value.animationFrameId = requestAnimationFrame(animate);
  };

  const stopKeyboardMovement = () => {
    keyboardState.value.isActive = false;
    keyboardState.value.speed = 1;

    if (keyboardState.value.animationFrameId) {
      cancelAnimationFrame(keyboardState.value.animationFrameId);
      keyboardState.value.animationFrameId = null;
    }

    // NEW: Create history entry for keyboard movement
    if (keyboardState.value.startPositions.size > 0) {
      const movedObjects: Array<{
        id: string;
        beforePoints: Point[];
        afterPoints: Point[];
        beforePosition?: Point;
        afterPosition?: Point;
        isElement: boolean;
      }> = [];

      const draggableObjects = [...store.selectedObjects];
      const draggableElements = [...store.selectedDomElements];
      const descendants = canvasObjects.getDescendants(draggableObjects);
      draggableObjects.push(...descendants.objects);
      draggableElements.push(...descendants.elements);

      // Check canvas objects
      draggableObjects.forEach((obj) => {
        const startPos = keyboardState.value.startPositions.get(obj.id);
        if (!startPos) return;

        const hasMoved = obj.points.some((p, i) => {
          const startP = startPos.points[i];
          return (
            !startP ||
            Math.abs(p.x - startP.x) > 0.1 ||
            Math.abs(p.y - startP.y) > 0.1
          );
        });

        if (hasMoved) {
          movedObjects.push({
            id: obj.id,
            beforePoints: startPos.points,
            afterPoints: obj.points.map((p) => ({ x: p.x, y: p.y })),
            isElement: false,
          });
        }
      });

      // Check DOM elements
      draggableElements.forEach((el) => {
        const startPos = keyboardState.value.startPositions.get(el.id);
        if (!startPos || !startPos.position) return;

        const hasMoved =
          Math.abs(el.position.x - startPos.position.x) > 0.1 ||
          Math.abs(el.position.y - startPos.position.y) > 0.1;

        if (hasMoved) {
          movedObjects.push({
            id: el.id,
            beforePoints: [],
            afterPoints: [],
            beforePosition: startPos.position,
            afterPosition: { x: el.position.x, y: el.position.y },
            isElement: true,
          });
        }
      });

      // Create history entry if objects moved
      if (movedObjects.length > 0) {
        const moveCommand = new (class implements Command {
          execute() {
            movedObjects.forEach((item) => {
              if (item.isElement) {
                const el = store.domElements.find((e) => e.id === item.id);
                if (el && item.afterPosition) {
                  el.position = { ...item.afterPosition };
                }
              } else {
                const obj = store.objects.find((o) => o.id === item.id);
                if (obj) {
                  obj.points = item.afterPoints.map((p) => ({
                    x: p.x,
                    y: p.y,
                  }));
                }
              }
            });
          }

          undo() {
            movedObjects.forEach((item) => {
              if (item.isElement) {
                const el = store.domElements.find((e) => e.id === item.id);
                if (el && item.beforePosition) {
                  el.position = { ...item.beforePosition };
                }
              } else {
                const obj = store.objects.find((o) => o.id === item.id);
                if (obj) {
                  obj.points = item.beforePoints.map((p) => ({
                    x: p.x,
                    y: p.y,
                  }));
                }
              }
            });
          }
        })();

        const objectType = movedObjects.every((o) => o.isElement)
          ? "dom"
          : movedObjects.every((o) => !o.isElement)
            ? "canvas"
            : "mixed";

        store.pushToHistory(
          moveCommand,
          "move",
          objectType,
          `Keyboard moved ${movedObjects.length} ${
            objectType === "dom" ? "elements" : "objects"
          }`,
        );

        console.log(
          `⌨️ Keyboard movement tracked: ${movedObjects.length} items`,
        );
      }

      keyboardState.value.startPositions.clear();
    }
  };

  const getBounds = (obj: CanvasObject) => {
    if (obj.boundingBox) return obj.boundingBox;
    if (obj.elementData) {
      return {
        x: obj.elementData.position.x,
        y: obj.elementData.position.y,
        width: obj.elementData.size.width,
        height: obj.elementData.size.height,
      };
    }
    return canvasObjects.getObjectBounding(obj);
  };

  const screenToWorld = (point: Point, zoom: number, offset: Point): Point => ({
    x: point.x / zoom + offset.x,
    y: point.y / zoom + offset.y,
  });

  const getHandleAtPoint = (worldPoint: Point) => {
    if (store.selectedObjects.length !== 1) return null;

    const obj = store.selectedObjects[0];
    const bounds = getBounds(obj);
    if (!bounds) return null;

    const center = canvasObjects.getCenter(obj);
    const handleSizeWorld = 10 / store.zoom;

    // ✅ RESIZE HANDLES (unchanged)
    const unrotatedHandles = [
      { x: bounds.x, y: bounds.y, index: 0 },
      { x: bounds.x + bounds.width / 2, y: bounds.y, index: 1 },
      { x: bounds.x + bounds.width, y: bounds.y, index: 2 },
      { x: bounds.x + bounds.width, y: bounds.y + bounds.height / 2, index: 3 },
      { x: bounds.x + bounds.width, y: bounds.y + bounds.height, index: 4 },
      { x: bounds.x + bounds.width / 2, y: bounds.y + bounds.height, index: 5 },
      { x: bounds.x, y: bounds.y + bounds.height, index: 6 },
      { x: bounds.x, y: bounds.y + bounds.height / 2, index: 7 },
    ];

    for (const h of unrotatedHandles) {
      const rotatedH = canvasObjects.rotatePoint(
        { x: h.x, y: h.y },
        center,
        obj.rotation,
      );
      const dist = Math.hypot(
        worldPoint.x - rotatedH.x,
        worldPoint.y - rotatedH.y,
      );
      if (dist < handleSizeWorld) {
        return { type: "resize", index: h.index };
      }
    }

    // NEW: Side edge detection for more precise resize enabling
    const rotatedCorners = [
      canvasObjects.rotatePoint(
        { x: unrotatedHandles[0].x, y: unrotatedHandles[0].y },
        center,
        obj.rotation,
      ), // top-left
      canvasObjects.rotatePoint(
        { x: unrotatedHandles[2].x, y: unrotatedHandles[2].y },
        center,
        obj.rotation,
      ), // top-right
      canvasObjects.rotatePoint(
        { x: unrotatedHandles[4].x, y: unrotatedHandles[4].y },
        center,
        obj.rotation,
      ), // bottom-right
      canvasObjects.rotatePoint(
        { x: unrotatedHandles[6].x, y: unrotatedHandles[6].y },
        center,
        obj.rotation,
      ), // bottom-left
    ];

    const sides = [
      { a: 0, b: 1, index: 1 }, // top
      { a: 1, b: 2, index: 3 }, // right
      { a: 2, b: 3, index: 5 }, // bottom
      { a: 3, b: 0, index: 7 }, // left
    ];

    for (const side of sides) {
      const dist = distanceToSegment(
        worldPoint,
        rotatedCorners[side.a],
        rotatedCorners[side.b],
      );
      if (dist < handleSizeWorld) {
        return { type: "resize", index: side.index };
      }
    }

    // 🎯 FIXED ROTATION HANDLE
    // Calculate position BEFORE rotation, then rotate it around center
    const rotationHandleDistance = 40 / store.zoom; // Increased distance from 30 to 40

    // Position at top-center in unrotated space
    const unrotatedRotationHandle = {
      x: bounds.x + bounds.width / 2,
      y: bounds.y - rotationHandleDistance, // Use consistent distance variable
    };

    // ✅ CRITICAL FIX: Rotate the handle position around the object center
    const rotatedRotationHandle = canvasObjects.rotatePoint(
      unrotatedRotationHandle,
      center,
      obj.rotation || 0, // Apply object's current rotation
    );

    // Check if click point is within handle radius
    const dist = Math.hypot(
      worldPoint.x - rotatedRotationHandle.x,
      worldPoint.y - rotatedRotationHandle.y,
    );

    if (dist < handleSizeWorld) {
      return { type: "rotation", index: -1 };
    }

    // ✅ RADIUS HANDLES (for Rectangular objects)
    const rectangularTypes = ["rectangle", "booth", "image", "element"];
    if (rectangularTypes.includes(obj.type) && !obj.isLocked) {
      const handleDistance = 15;
      const w = bounds.width;
      const h = bounds.height;
      const sw = w * store.zoom;
      const sh = h * store.zoom;

      // Matches useCanvasRectangle logic
      const safeDist = Math.min(handleDistance, sw / 3, sh / 3) / store.zoom;

      // Local positions (relative to center)
      const localHandles = [
        { x: -w / 2 + safeDist, y: -h / 2 + safeDist, index: 0 }, // Top-Left
        { x: w / 2 - safeDist, y: -h / 2 + safeDist, index: 1 }, // Top-Right
        { x: w / 2 - safeDist, y: h / 2 - safeDist, index: 2 }, // Bottom-Right
        { x: -w / 2 + safeDist, y: h / 2 - safeDist, index: 3 }, // Bottom-Left
      ];

      for (const h of localHandles) {
        const rotatedH = canvasObjects.rotatePoint(
          { x: center.x + h.x, y: center.y + h.y },
          center,
          obj.rotation || 0,
        );

        const dist = Math.hypot(
          worldPoint.x - rotatedH.x,
          worldPoint.y - rotatedH.y,
        );

        if (dist < handleSizeWorld) {
          return { type: "rounding", index: h.index };
        }
      }
    }

    return null;
  };

  const updateCanvasDimensions = (width: number, height: number) => {
    canvasDimensions.value = { width, height };
  };

  const startDragging = (
    worldPoint: Point,
    providedObjects?: CanvasObject[],
  ) => {
    interactionState.value.isDragging = true;
    interactionState.value.dragStartPoint = worldPoint;
    interactionState.value.lastPos = worldPoint;
    interactionState.value.currentCursor = "grabbing";

    // FIXED: Properly store initial positions with deep cloning
    dragStartPositions.value.clear();

    const draggableObjects = providedObjects
      ? [...providedObjects]
      : [...store.selectedObjects];
    const draggableElements = [...store.selectedDomElements];

    const descendants = canvasObjects.getDescendants(draggableObjects);
    draggableObjects.push(...descendants.objects);
    draggableElements.push(...descendants.elements);

    console.log(
      `🔍 Starting drag with ${draggableObjects.length} objects and ${draggableElements.length} DOM elements`,
    );
    if (draggableElements.length > 0) {
      console.log(
        `📝 DOM elements to drag:`,
        draggableElements.map((el) => ({
          id: el.id,
          type: el.type,
          pos: el.position,
        })),
      );
    }

    // Capture initial states for undo/redo for ALL identified participants
    draggableObjects.forEach((obj) => {
      dragStartPositions.value.set(obj.id, {
        points: obj.points.map((p) => ({ x: p.x, y: p.y })),
        boundingBox: obj.boundingBox ? { ...obj.boundingBox } : undefined,
      });
    });

    draggableElements.forEach((el) => {
      dragStartPositions.value.set(el.id, {
        points: [],
        position: { x: el.position.x, y: el.position.y },
      });
    });

    // Wrap DOM elements as pseudo-objects for optimized dragging system
    const domPseudoObjects = draggableElements.map((el) => ({
      id: el.id,
      type: "dom-element",
      points: [],
      isLocked: false,
      isVisible: true,
      elementData: el,
    }));

    dragging.startDrag(worldPoint, [...draggableObjects, ...domPseudoObjects]);
  };

  const doDragging = (
    point: Point,
    zoom: number,
    offset: Point,
    canvasWidth?: number,
    canvasHeight?: number,
    onUpdate?: () => void,
  ) => {
    const worldPoint = screenToWorld(point, zoom, offset);

    // CRITICAL: Only call updateDrag, NO store methods
    dragging.updateDrag(
      worldPoint,
      canvasWidth,
      canvasHeight,
      zoom,
      offset,
      onUpdate,
    );

    // DO NOT call any store update methods here!
  };

  const stopDragging = () => {
    if (!interactionState.value.isDragging) return;

    const movedObjects: Array<{
      id: string;
      beforePoints: Point[];
      afterPoints: Point[];
      beforeBoundingBox?: {
        x: number;
        y: number;
        width: number;
        height: number;
      };
      afterBoundingBox?: {
        x: number;
        y: number;
        width: number;
        height: number;
      };
      beforePosition?: Point;
      afterPosition?: Point;
      isElement: boolean;
    }> = [];

    // NEW: Iterate through dragStartPositions to capture movement for all participants (including children)
    dragStartPositions.value.forEach((startPos, id) => {
      // Check Canvas Objects
      const obj = store.objects.find((o) => o.id === id);
      if (obj) {
        const hasMoved = obj.points.some((p, i) => {
          const startP = startPos.points[i];
          return (
            !startP ||
            Math.abs(p.x - startP.x) > 0.1 ||
            Math.abs(p.y - startP.y) > 0.1
          );
        });

        if (hasMoved) {
          movedObjects.push({
            id: obj.id,
            beforePoints: startPos.points.map((p) => ({ x: p.x, y: p.y })),
            afterPoints: obj.points.map((p) => ({ x: p.x, y: p.y })),
            beforeBoundingBox: startPos.boundingBox
              ? { ...startPos.boundingBox }
              : undefined,
            afterBoundingBox: obj.boundingBox
              ? { ...obj.boundingBox }
              : undefined,
            isElement: false,
          });
        }
        return;
      }

      // Check DOM Elements
      const el = store.domElements.find((e) => e.id === id);
      if (el && startPos.position) {
        const hasMoved =
          Math.abs(el.position.x - startPos.position.x) > 0.1 ||
          Math.abs(el.position.y - startPos.position.y) > 0.1;

        if (hasMoved) {
          movedObjects.push({
            id: el.id,
            beforePoints: [],
            afterPoints: [],
            beforePosition: { ...startPos.position },
            afterPosition: { x: el.position.x, y: el.position.y },
            isElement: true,
          });
        }
      }
    });

    if (movedObjects.length > 0) {
      // ✅ Create deep copies with closure to prevent reference issues
      const timestamp = Date.now(); // Add timestamp for uniqueness

      const moveCommand = new (class implements Command {
        private timestamp = timestamp;
        private moves = movedObjects.map((item) => ({
          id: item.id,
          isElement: item.isElement,
          beforePoints: item.beforePoints.map((p) => ({ x: p.x, y: p.y })),
          afterPoints: item.afterPoints.map((p) => ({ x: p.x, y: p.y })),
          beforePosition: item.beforePosition
            ? { x: item.beforePosition.x, y: item.beforePosition.y }
            : undefined,
          afterPosition: item.afterPosition
            ? { x: item.afterPosition.x, y: item.afterPosition.y }
            : undefined,
          beforeBoundingBox: item.beforeBoundingBox
            ? {
                x: item.beforeBoundingBox.x,
                y: item.beforeBoundingBox.y,
                width: item.beforeBoundingBox.width,
                height: item.beforeBoundingBox.height,
              }
            : undefined,
          afterBoundingBox: item.afterBoundingBox
            ? {
                x: item.afterBoundingBox.x,
                y: item.afterBoundingBox.y,
                width: item.afterBoundingBox.width,
                height: item.afterBoundingBox.height,
              }
            : undefined,
        }));

        execute() {
          this.moves.forEach((item) => {
            if (item.isElement) {
              const el = store.domElements.find((e) => e.id === item.id);
              if (el && item.afterPosition) {
                el.position = {
                  x: item.afterPosition.x,
                  y: item.afterPosition.y,
                };
              }
            } else {
              const obj = store.objects.find((o) => o.id === item.id);
              if (obj) {
                obj.points = item.afterPoints.map((p) => ({ x: p.x, y: p.y }));
                if (item.afterBoundingBox && obj.type === "booth") {
                  obj.boundingBox = {
                    x: item.afterBoundingBox.x,
                    y: item.afterBoundingBox.y,
                    width: item.afterBoundingBox.width,
                    height: item.afterBoundingBox.height,
                  };
                }
              }
            }
          });
        }

        undo() {
          this.moves.forEach((item) => {
            if (item.isElement) {
              const el = store.domElements.find((e) => e.id === item.id);
              if (el && item.beforePosition) {
                el.position = {
                  x: item.beforePosition.x,
                  y: item.beforePosition.y,
                };
              }
            } else {
              const obj = store.objects.find((o) => o.id === item.id);
              if (obj) {
                obj.points = item.beforePoints.map((p) => ({ x: p.x, y: p.y }));
                if (item.beforeBoundingBox && obj.type === "booth") {
                  obj.boundingBox = {
                    x: item.beforeBoundingBox.x,
                    y: item.beforeBoundingBox.y,
                    width: item.beforeBoundingBox.width,
                    height: item.beforeBoundingBox.height,
                  };
                }
              }
            }
          });
        }
      })();

      const objectType = movedObjects.every((o) => o.isElement)
        ? "dom"
        : movedObjects.every((o) => !o.isElement)
          ? "canvas"
          : "mixed";

      store.pushToHistory(
        moveCommand,
        "move",
        objectType,
        `Moved ${movedObjects.length} ${
          objectType === "dom" ? "elements" : "objects"
        }`,
      );
    }

    dragStartPositions.value.clear();
    dragging.stopDrag();
    interactionState.value.isDragging = false;
    interactionState.value.currentCursor = "default";
    interactionState.value.hasDuplicated = false;
  };

  const switchToDuplicatedObjects = (newObjects: CanvasObject[]) => {
    interactionState.value.hasDuplicated = true;

    // Update internal dragStartPositions to match the new objects
    // Since we call this at the very beginning of the drag, current pos = start pos
    newObjects.forEach((obj) => {
      dragStartPositions.value.set(obj.id, {
        points: obj.points.map((p) => ({ x: p.x, y: p.y })),
        boundingBox: obj.boundingBox ? { ...obj.boundingBox } : undefined,
      });
    });

    // Update the optimized dragging system targets
    dragging.updateDraggedObjectsDuringDrag(newObjects);
  };

  const resetDuplicationState = () => {
    interactionState.value.hasDuplicated = false;
  };

  const startRotating = (worldPoint: Point) => {
    if (store.selectedObjects.length !== 1) return;

    const obj = store.selectedObjects[0];
    const center = canvasObjects.getCenter(obj);

    interactionState.value.isRotating = true;
    interactionState.value.rotationStartPoint = worldPoint;
    interactionState.value.dragStartPoint = worldPoint;
    interactionState.value.currentCursor = "grabbing";
    interactionState.value.rotationCenter = center;

    const dx = worldPoint.x - center.x;
    const dy = worldPoint.y - center.y;
    interactionState.value.rotationStartAngle = Math.atan2(dy, dx);
    interactionState.value.initialRotation = obj.rotation || 0;
    interactionState.value.lastRotationAngle = obj.rotation || 0;
  };

  const doRotating = (point: Point, zoom: number, offset: Point) => {
    if (
      !interactionState.value.isRotating ||
      store.selectedObjects.length !== 1
    )
      return;

    const worldPoint = screenToWorld(point, zoom, offset);
    const center = interactionState.value.rotationCenter;
    if (!center) return;

    const obj = store.selectedObjects[0];
    const dx = worldPoint.x - center.x;
    const dy = worldPoint.y - center.y;
    const currentAngle = Math.atan2(dy, dx);

    const deltaAngle = currentAngle - interactionState.value.rotationStartAngle;
    const deltaDegrees = deltaAngle * (180 / Math.PI);
    let newRotation = interactionState.value.initialRotation + deltaDegrees;

    newRotation = ((newRotation % 360) + 360) % 360;

    if (isShiftPressed.value) {
      const snapAngle = 15;
      newRotation = Math.round(newRotation / snapAngle) * snapAngle;
    }

    const rotationThreshold = 0.5;
    obj.rotation = newRotation;
    interactionState.value.lastRotationAngle = newRotation;
  };

  const stopRotating = () => {
    if (
      !interactionState.value.isRotating ||
      store.selectedObjects.length !== 1
    ) {
      interactionState.value.isRotating = false;
      return;
    }

    const obj = store.selectedObjects[0];
    const initialRotation = interactionState.value.initialRotation;
    const finalRotation = obj.rotation || 0;

    // Check if rotation actually changed
    if (Math.abs(finalRotation - initialRotation) > 0.5) {
      const rotateCommand = new (class implements Command {
        execute() {
          const target = store.objects.find((o) => o.id === obj.id);
          if (target) {
            target.rotation = finalRotation;
          }
        }

        undo() {
          const target = store.objects.find((o) => o.id === obj.id);
          if (target) {
            target.rotation = initialRotation;
          }
        }
      })();

      store.pushToHistory(
        rotateCommand,
        "modify",
        "canvas",
        `Rotated ${obj.type} from ${initialRotation.toFixed(
          1,
        )}° to ${finalRotation.toFixed(1)}°`,
      );

      console.log(
        `🔄 Rotation tracked: ${initialRotation.toFixed(
          1,
        )}° → ${finalRotation.toFixed(1)}°`,
      );
    }

    interactionState.value.isRotating = false;
    interactionState.value.rotationCenter = null;
    interactionState.value.rotationStartPoint = null;
    interactionState.value.rotationStartAngle = 0;
    interactionState.value.initialRotation = 0;
    interactionState.value.lastRotationAngle = 0;
    interactionState.value.currentCursor = "default";
  };

  const startResizing = (worldPoint: Point, handleIndex: number) => {
    if (store.selectedObjects.length !== 1) return;

    const obj = store.selectedObjects[0];
    const bounds = getBounds(obj);
    if (!bounds) return;

    interactionState.value.isResizing = true;
    interactionState.value.dragStartPoint = worldPoint;
    interactionState.value.resizeHandleIndex = handleIndex;

    // ✅ FIX: Calculate proper cursor direction considering rotation
    const rotation = obj.rotation || 0;
    const baseDirections = [
      "nw-resize",
      "n-resize",
      "ne-resize",
      "e-resize",
      "se-resize",
      "s-resize",
      "sw-resize",
      "w-resize",
    ];

    // Adjust cursor index based on rotation
    const rotationSteps = Math.round(rotation / 45) % 8;
    const adjustedIndex = (handleIndex + rotationSteps + 8) % 8;
    interactionState.value.currentCursor = baseDirections[adjustedIndex];

    // Store initial bounds in LOCAL coordinate system
    const center = canvasObjects.getCenter(obj);
    const localBounds = bounds;

    interactionState.value.initialBounds = { ...localBounds };

    // NEW: Deep clone for resize tracking
    interactionState.value.originalObjects = [
      {
        ...obj,
        points: obj.points ? obj.points.map((p) => ({ x: p.x, y: p.y })) : [],
        boundingBox: obj.boundingBox ? { ...obj.boundingBox } : undefined,
      },
    ];

    const resizeDirections = [
      { dx: "left", dy: "top" },
      { dx: "center", dy: "top" },
      { dx: "right", dy: "top" },
      { dx: "right", dy: "center" },
      { dx: "right", dy: "bottom" },
      { dx: "center", dy: "bottom" },
      { dx: "left", dy: "bottom" },
      { dx: "left", dy: "center" },
    ];

    const dir = resizeDirections[handleIndex];

    // ✅ Calculate origin in LOCAL coordinate system
    let originX = localBounds.x + localBounds.width / 2;
    let originY = localBounds.y + localBounds.height / 2;

    if (dir.dx === "left") originX = localBounds.x + localBounds.width;
    else if (dir.dx === "right") originX = localBounds.x;

    if (dir.dy === "top") originY = localBounds.y + localBounds.height;
    else if (dir.dy === "bottom") originY = localBounds.y;

    interactionState.value.resizeOrigin = { x: originX, y: originY };
  };

  const stopResizing = () => {
    if (
      !interactionState.value.isResizing ||
      store.selectedObjects.length !== 1
    ) {
      interactionState.value.isResizing = false;
      return;
    }

    const obj = store.selectedObjects[0];
    const original = interactionState.value.originalObjects[0];

    // Check if object actually changed
    const hasChanged = obj.points.some((p, i) => {
      const origP = original.points?.[i];
      return (
        !origP || Math.abs(p.x - origP.x) > 0.1 || Math.abs(p.y - origP.y) > 0.1
      );
    });

    if (hasChanged) {
      const beforeState = {
        points: original.points?.map((p) => ({ x: p.x, y: p.y })) || [],
        boundingBox: original.boundingBox
          ? { ...original.boundingBox }
          : undefined,
      };

      const afterState = {
        points: obj.points.map((p) => ({ x: p.x, y: p.y })),
        boundingBox: obj.boundingBox ? { ...obj.boundingBox } : undefined,
      };

      const resizeCommand = new (class implements Command {
        execute() {
          const target = store.objects.find((o) => o.id === obj.id);
          if (target) {
            target.points = afterState.points.map((p) => ({ x: p.x, y: p.y }));
            if (afterState.boundingBox) {
              target.boundingBox = { ...afterState.boundingBox };
            }
          }
        }

        undo() {
          const target = store.objects.find((o) => o.id === obj.id);
          if (target) {
            target.points = beforeState.points.map((p) => ({ x: p.x, y: p.y }));
            if (beforeState.boundingBox) {
              target.boundingBox = { ...beforeState.boundingBox };
            }
          }
        }
      })();

      store.pushToHistory(
        resizeCommand,
        "modify",
        "canvas",
        `Resized ${obj.type}`,
      );

      console.log(`📐 Resize tracked for object ${obj.id}`);
    }

    interactionState.value.isResizing = false;
    interactionState.value.resizeHandleIndex = null;
    interactionState.value.originalObjects = [];
    interactionState.value.initialBounds = null;
    interactionState.value.resizeOrigin = null;
  };

  // Updated doResizing function with fix for rotated resize stability
  // Updated doResizing function in useObjectManipulation.ts
  const doResizing = (
    point: Point,
    zoom: number,
    offset: Point,
    canvasWidth?: number,
    canvasHeight?: number,
  ) => {
    if (
      !interactionState.value.isResizing ||
      store.selectedObjects.length !== 1
    )
      return;

    const obj = store.selectedObjects[0];
    const rotation = obj.rotation || 0;

    // Get original object state
    const original = interactionState.value.originalObjects[0];
    const originalBounds = canvasObjects.getObjectBounding(original);
    if (!originalBounds) return;

    const originalCenter = {
      x: originalBounds.x + originalBounds.width / 2,
      y: originalBounds.y + originalBounds.height / 2,
    };

    const worldPoint = screenToWorld(point, zoom, offset);
    const dragStartPoint = interactionState.value.dragStartPoint!;
    const handleIndex = interactionState.value.resizeHandleIndex!;
    const initialBounds = interactionState.value.initialBounds!;
    const resizeOrigin = interactionState.value.resizeOrigin!;

    // ✅ STEP 1: Get handle position in WORLD space (after rotation)
    const rotationRad = (rotation * Math.PI) / 180;
    const bounds = initialBounds;

    // Define handle positions in LOCAL (unrotated) space
    const localHandlePositions = [
      { x: bounds.x, y: bounds.y }, // 0: top-left
      { x: bounds.x + bounds.width / 2, y: bounds.y }, // 1: top
      { x: bounds.x + bounds.width, y: bounds.y }, // 2: top-right
      { x: bounds.x + bounds.width, y: bounds.y + bounds.height / 2 }, // 3: right
      { x: bounds.x + bounds.width, y: bounds.y + bounds.height }, // 4: bottom-right
      { x: bounds.x + bounds.width / 2, y: bounds.y + bounds.height }, // 5: bottom
      { x: bounds.x, y: bounds.y + bounds.height }, // 6: bottom-left
      { x: bounds.x, y: bounds.y + bounds.height / 2 }, // 7: left
    ];

    const localHandle = localHandlePositions[handleIndex];

    // Rotate handle position to world space
    const cos = Math.cos(rotationRad);
    const sin = Math.sin(rotationRad);

    const worldHandleDx = localHandle.x - originalCenter.x;
    const worldHandleDy = localHandle.y - originalCenter.y;

    const worldHandlePos = {
      x: worldHandleDx * cos - worldHandleDy * sin + originalCenter.x,
      y: worldHandleDx * sin + worldHandleDy * cos + originalCenter.y,
    };

    // ✅ STEP 2: Calculate movement vector from handle's current position
    const worldDeltaX = worldPoint.x - dragStartPoint.x;
    const worldDeltaY = worldPoint.y - dragStartPoint.y;

    // ✅ STEP 3: Project world delta onto object's LOCAL axes
    // Local X-axis (width direction) in world space
    const localXAxisWorld = {
      x: cos,
      y: sin,
    };

    // Local Y-axis (height direction) in world space
    const localYAxisWorld = {
      x: -sin,
      y: cos,
    };

    // Project cursor movement onto local axes
    const localDeltaX =
      worldDeltaX * localXAxisWorld.x + worldDeltaY * localXAxisWorld.y;
    const localDeltaY =
      worldDeltaX * localYAxisWorld.x + worldDeltaY * localYAxisWorld.y;

    // ✅ STEP 4: Determine which sides are being resized (in LOCAL space)
    const handleConfig = [
      { xSide: "left", ySide: "top", isCorner: true }, // 0
      { xSide: "center", ySide: "top", isCorner: false }, // 1
      { xSide: "right", ySide: "top", isCorner: true }, // 2
      { xSide: "right", ySide: "center", isCorner: false }, // 3
      { xSide: "right", ySide: "bottom", isCorner: true }, // 4
      { xSide: "center", ySide: "bottom", isCorner: false }, // 5
      { xSide: "left", ySide: "bottom", isCorner: true }, // 6
      { xSide: "left", ySide: "center", isCorner: false }, // 7
    ][handleIndex];

    // ✅ STEP 5: Apply deltas based on handle type
    let effectiveDeltaX = 0;
    let effectiveDeltaY = 0;

    if (handleConfig.isCorner) {
      // Corner handles: resize in both directions
      effectiveDeltaX = localDeltaX;
      effectiveDeltaY = localDeltaY;
    } else {
      // Edge handles: resize only in the perpendicular direction
      if (handleConfig.xSide === "center") {
        // Top or Bottom edge: only vertical resize
        effectiveDeltaX = 0;
        effectiveDeltaY = localDeltaY;
      } else {
        // Left or Right edge: only horizontal resize
        effectiveDeltaX = localDeltaX;
        effectiveDeltaY = 0;
      }
    }

    // ✅ STEP 6: Calculate new bounds in LOCAL space
    let newMinX = initialBounds.x;
    let newMaxX = initialBounds.x + initialBounds.width;
    let newMinY = initialBounds.y;
    let newMaxY = initialBounds.y + initialBounds.height;

    // Apply deltas based on which side is being resized
    if (handleConfig.xSide === "left") {
      // Dragging left edge: changes minX
      newMinX = initialBounds.x + effectiveDeltaX;
      newMinX = Math.min(newMinX, newMaxX - 1);
    } else if (handleConfig.xSide === "right") {
      // Dragging right edge: changes maxX
      newMaxX = initialBounds.x + initialBounds.width + effectiveDeltaX;
      newMaxX = Math.max(newMaxX, newMinX + 1);
    }

    if (handleConfig.ySide === "top") {
      // Dragging top edge: changes minY
      newMinY = initialBounds.y + effectiveDeltaY;
      newMinY = Math.min(newMinY, newMaxY - 1);
    } else if (handleConfig.ySide === "bottom") {
      // Dragging bottom edge: changes maxY
      newMaxY = initialBounds.y + initialBounds.height + effectiveDeltaY;
      newMaxY = Math.max(newMaxY, newMinY + 1);
    }

    const newWidth = newMaxX - newMinX;
    const newHeight = newMaxY - newMinY;

    // ✅ STEP 7: Calculate scale factors
    const scaleX = initialBounds.width > 0 ? newWidth / initialBounds.width : 1;
    const scaleY =
      initialBounds.height > 0 ? newHeight / initialBounds.height : 1;

    // ✅ STEP 8: Apply scaling to points
    if (obj.points && Array.isArray(obj.points)) {
      // Transform original points to local (unrotated) space - but since points are already unrotated, use them directly
      const originalLocalPoints = original.points;

      // Scale points around resize origin in local space
      let scaledLocalPoints = originalLocalPoints.map((p) => ({
        x: resizeOrigin.x + (p.x - resizeOrigin.x) * scaleX,
        y: resizeOrigin.y + (p.y - resizeOrigin.y) * scaleY,
      }));

      // Calculate original world position of resize origin
      const originalResizeOriginWorld = canvasObjects.rotatePoint(
        resizeOrigin,
        originalCenter,
        rotation,
      );

      // Calculate new local center from scaled points
      const localXs = scaledLocalPoints.map((p) => p.x);
      const localYs = scaledLocalPoints.map((p) => p.y);
      const newLocalCenter = {
        x: (Math.min(...localXs) + Math.max(...localXs)) / 2,
        y: (Math.min(...localYs) + Math.max(...localYs)) / 2,
      };

      // Calculate projected new world position of resize origin
      const projectedResizeOriginWorld = canvasObjects.rotatePoint(
        resizeOrigin,
        newLocalCenter,
        rotation,
      );

      // Calculate translation to keep resize origin fixed in world space
      const tx = originalResizeOriginWorld.x - projectedResizeOriginWorld.x;
      const ty = originalResizeOriginWorld.y - projectedResizeOriginWorld.y;

      // Apply translation to all scaled local points
      scaledLocalPoints = scaledLocalPoints.map((p) => ({
        x: p.x + tx,
        y: p.y + ty,
      }));

      // Assign translated scaled points to object
      obj.points = scaledLocalPoints;

      // ✅ NEW: Update size and position properties dynamically
      if (obj.points.length >= 2) {
        const p1 = obj.points[0];
        const p2 = obj.points[1] || obj.points[obj.points.length - 1];

        // Calculate actual size from points
        const width = Math.abs(p2.x - p1.x);
        const height = Math.abs(p2.y - p1.y);
        const x = Math.min(p1.x, p2.x);
        const y = Math.min(p1.y, p2.y);

        // Update size property
        if (obj.size) {
          obj.size.width = width;
          obj.size.height = height;
        } else {
          obj.size = { width, height };
        }

        // Update position property
        if (obj.position) {
          obj.position.x = x;
          obj.position.y = y;
        } else {
          obj.position = { x, y };
        }

        // Update bounding box for ANY object that has one
        if (obj.boundingBox) {
          if (
            ["booth", "rectangle", "ellipse", "line", "arrow"].includes(
              obj.type,
            )
          ) {
            obj.boundingBox.x = x;
            obj.boundingBox.y = y;
            obj.boundingBox.width = width;
            obj.boundingBox.height = height;
          } else {
            // For complex path objects like pencil and wall, recalculate from all points
            let minX = Infinity,
              minY = Infinity,
              maxX = -Infinity,
              maxY = -Infinity;
            obj.points.forEach((p) => {
              minX = Math.min(minX, p.x);
              minY = Math.min(minY, p.y);
              maxX = Math.max(maxX, p.x);
              maxY = Math.max(maxY, p.y);
            });
            obj.boundingBox.x = minX;
            obj.boundingBox.y = minY;
            obj.boundingBox.width = maxX - minX;
            obj.boundingBox.height = maxY - minY;
          }
        }

        // For ellipse type, calculate from diameter
        if (obj.type === "ellipse") {
          if (obj.points.length >= 2) {
            const center = {
              x: (p1.x + p2.x) / 2,
              y: (p1.y + p2.y) / 2,
            };
            const radiusX = Math.abs(p2.x - p1.x) / 2;
            const radiusY = Math.abs(p2.y - p1.y) / 2;

            if (obj.size) {
              obj.size.width = radiusX * 2;
              obj.size.height = radiusY * 2;
            } else {
              obj.size = { width: radiusX * 2, height: radiusY * 2 };
            }

            if (obj.position) {
              obj.position.x = center.x - radiusX;
              obj.position.y = center.y - radiusY;
            } else {
              obj.position = { x: center.x - radiusX, y: center.y - radiusY };
            }
          }
        }
      }

      // Update bounding box for booth objects
      if (obj.type === "booth" && obj.points.length >= 2) {
        const p1 = obj.points[0];
        const p2 = obj.points[1];
        obj.boundingBox = {
          x: Math.min(p1.x, p2.x),
          y: Math.min(p1.y, p2.y),
          width: Math.abs(p2.x - p1.x),
          height: Math.abs(p2.y - p1.y),
        };
      }
    }

    // Calculate alignment guides during resize
    if (canvasWidth && canvasHeight) {
      dragging.calculateResizeGuides(
        obj,
        canvasWidth,
        canvasHeight,
        zoom,
        offset,
      );
    }
  };

  const stopObjectManipulation = () => {
    if (interactionState.value.isDragging) {
      stopDragging();
    }

    // Check rounding
    if (interactionState.value.isRounding) {
      stopRounding();
    }

    interactionState.value.isDragging = false;
    interactionState.value.isResizing = false;
    interactionState.value.isRotating = false;
    interactionState.value.isRounding = false;
    interactionState.value.dragStartPoint = null;
    interactionState.value.resizeHandleIndex = null;
    interactionState.value.roundingHandleIndex = null;
    interactionState.value.originalObjects = [];
    interactionState.value.resizeOrigin = null;
    interactionState.value.initialBounds = null;
    interactionState.value.rotationCenter = null;
    interactionState.value.rotationStartPoint = null;
    interactionState.value.rotationStartAngle = 0;
    interactionState.value.initialRotation = 0;
    interactionState.value.lastRotationAngle = 0;
    interactionState.value.initialCornerRadius = 0;
    interactionState.value.currentCursor = "default";

    dragging.clearGuides();
  };

  // Helper function to calculate distance from point to line segment
  const distanceToSegment = (p: Point, a: Point, b: Point): number => {
    const dx = b.x - a.x;
    const dy = b.y - a.y;
    if (dx === 0 && dy === 0) return Math.hypot(p.x - a.x, p.y - a.y);
    const t = ((p.x - a.x) * dx + (p.y - a.y) * dy) / (dx * dx + dy * dy);
    const proj = t < 0 ? a : t > 1 ? b : { x: a.x + t * dx, y: a.y + t * dy };
    return Math.hypot(p.x - proj.x, p.y - proj.y);
  };

  const isPointOnSelectedObject = (worldPoint: Point) => {
    for (const obj of store.selectedObjects) {
      const center = canvasObjects.getCenter(obj);
      const rotation = obj.rotation || 0;

      // Special handling for wall objects
      if (obj.type === "wall" && obj.points && obj.points.length >= 2) {
        const rotatedPoints = obj.points.map((p) =>
          canvasObjects.rotatePoint(p, center, rotation),
        );

        // Check distance to each wall segment
        let minDist = Infinity;
        for (let i = 0; i < rotatedPoints.length - 1; i++) {
          minDist = Math.min(
            minDist,
            distanceToSegment(
              worldPoint,
              rotatedPoints[i],
              rotatedPoints[i + 1],
            ),
          );
        }

        const hitThreshold = 10 / store.zoom; // Adjust threshold based on zoom
        if (minDist <= hitThreshold) {
          return true;
        }
      } else {
        // Existing logic for other object types
        const bounds = getBounds(obj);
        if (!bounds) continue;

        const localPoint = canvasObjects.rotatePoint(
          worldPoint,
          center,
          -rotation,
        );

        if (
          localPoint.x >= bounds.x &&
          localPoint.x <= bounds.x + bounds.width &&
          localPoint.y >= bounds.y &&
          localPoint.y <= bounds.y + bounds.height
        ) {
          return true;
        }
      }
    }
    return false;
  };

  const startRounding = (worldPoint: Point, handleIndex: number) => {
    if (store.selectedObjects.length !== 1) return;
    const obj = store.selectedObjects[0];

    interactionState.value.isRounding = true;
    interactionState.value.roundingHandleIndex = handleIndex;
    interactionState.value.dragStartPoint = worldPoint;
    interactionState.value.initialCornerRadius = obj.cornerRadius || 0;
    interactionState.value.currentCursor = "crosshair";
  };

  const doRounding = (point: Point, zoom: number, offset: Point) => {
    if (
      !interactionState.value.isRounding ||
      store.selectedObjects.length !== 1
    )
      return;

    const obj = store.selectedObjects[0];
    const worldPoint = screenToWorld(point, zoom, offset);
    const center = canvasObjects.getCenter(obj);
    const bounds = getBounds(obj);
    if (!bounds) return;

    // We want the distance from the ACTUAL corner of the bounding box to the mouse pointer.
    // 1. Get the relevant corner in local space
    const w = bounds.width;
    const h = bounds.height;

    const corners = [
      { x: -w / 2, y: -h / 2 }, // Top-Left
      { x: w / 2, y: -h / 2 }, // Top-Right
      { x: w / 2, y: h / 2 }, // Bottom-Right
      { x: -w / 2, y: h / 2 }, // Bottom-Left
    ];

    const handleIndex = interactionState.value.roundingHandleIndex || 0;
    const localCorner = corners[handleIndex];

    // 2. Rotate corner to world space
    const worldCorner = canvasObjects.rotatePoint(
      { x: center.x + localCorner.x, y: center.y + localCorner.y },
      center,
      obj.rotation || 0,
    );

    // 3. Calculate distance from corner to mouse cursor
    const dist = Math.hypot(
      worldPoint.x - worldCorner.x,
      worldPoint.y - worldCorner.y,
    );

    // 4. Update radius
    // Clamp to half of the smallest dimension
    const maxRadius = Math.min(w, h) / 2;
    obj.cornerRadius = Math.max(0, Math.min(maxRadius, dist));
  };

  const stopRounding = () => {
    if (
      !interactionState.value.isRounding ||
      store.selectedObjects.length !== 1
    ) {
      interactionState.value.isRounding = false;
      return;
    }

    const obj = store.selectedObjects[0];
    const fromRadius = interactionState.value.initialCornerRadius;
    const toRadius = obj.cornerRadius || 0;

    if (Math.abs(fromRadius - toRadius) > 0.5) {
      const radiusCommand = new (class implements Command {
        execute() {
          const target = store.objects.find((o) => o.id === obj.id);
          if (target) target.cornerRadius = toRadius;
        }
        undo() {
          const target = store.objects.find((o) => o.id === obj.id);
          if (target) target.cornerRadius = fromRadius;
        }
      })();

      store.pushToHistory(
        radiusCommand,
        "modify",
        "canvas",
        `Changed corner radius`,
      );
    }

    interactionState.value.isRounding = false;
    interactionState.value.roundingHandleIndex = null;
    interactionState.value.currentCursor = "default";
  };

  const updateCursor = (point: Point, zoom: number, offset: Point) => {
    const state = interactionState.value;

    // ✅ During active interactions, use interaction-specific cursors
    if (state.isDragging) {
      return "grabbing";
    }

    if (state.isRounding) {
      return "crosshair";
    }

    if (state.isResizing) {
      // Dynamically recalculate correct resize cursor based on CURRENT rotation and handle
      const handleIndex = state.resizeHandleIndex;
      if (handleIndex !== null && store.selectedObjects.length === 1) {
        const obj = store.selectedObjects[0];
        const rotation = obj.rotation || 0;
        const baseDirections = [
          "nw-resize",
          "n-resize",
          "ne-resize",
          "e-resize",
          "se-resize",
          "s-resize",
          "sw-resize",
          "w-resize",
        ];
        const rotationSteps = Math.round(rotation / 45) % 8;
        const adjustedIndex = (handleIndex + rotationSteps + 8) % 8;
        return baseDirections[adjustedIndex];
      }
      return "default";
    }

    if (state.isRotating) {
      return "grabbing";
    }

    // ✅ When not interacting, strictly follow tool selection
    if (store.currentTool === "hand") {
      return "grab";
    }

    const drawingTools = [
      "pencil",
      "line",
      "arrow",
      "curve-arrow",
      "rectangle",
      "ellipse",
      "wall",
      "booth",
      "two-headed-arrow",
      "frame",
      "section",
    ];
    if (drawingTools.includes(store.currentTool)) {
      return "crosshair";
    }

    if (store.currentTool === "select") {
      const worldPoint = screenToWorld(point, zoom, offset);
      const handleInfo = getHandleAtPoint(worldPoint);

      if (handleInfo) {
        if (handleInfo.type === "rotation") {
          return "grab";
        } else if (handleInfo.type === "rounding") {
          return "crosshair";
        } else {
          // Handle both hover detection AND active resizing
          const obj = store.selectedObjects[0];
          const rotation = obj?.rotation || 0;
          const baseDirections = [
            "nw-resize",
            "n-resize",
            "ne-resize",
            "e-resize",
            "se-resize",
            "s-resize",
            "sw-resize",
            "w-resize",
          ];
          const rotationSteps = Math.round(rotation / 45) % 8;

          let handleIndex: number;
          if (
            interactionState.value.isResizing &&
            interactionState.value.resizeHandleIndex !== null
          ) {
            // Use the active resize handle
            handleIndex = interactionState.value.resizeHandleIndex;
          } else {
            // Use the hovered handle
            handleIndex = handleInfo.index;
          }

          const adjustedIndex = (handleIndex + rotationSteps + 8) % 8;
          return baseDirections[adjustedIndex];
        }
      } else if (isPointOnSelectedObject(worldPoint)) {
        return "move";
      }

      return "default";
    }

    return "default";
  };

  return {
    interactionState,
    alignmentGuides: dragging.localGuides,
    alignedElements: dragging.alignedElements,
    isDraggingForRuler: dragging.isDragging,
    startDragging,
    doDragging,
    stopDragging,
    stopObjectManipulation,
    getHandleAtPoint,
    startRotating,
    doRotating,
    stopRotating,
    startResizing,
    doResizing,
    updateCursor,
    isPointOnSelectedObject,
    getObjectBounding: canvasObjects.getObjectBounding,
    boxesIntersect: canvasObjects.boxesIntersect,
    clearAlignmentGuides: dragging.clearGuides,
    updateCanvasDimensions,
    startRounding,
    doRounding,
    stopRounding,
    switchToDuplicatedObjects,
    resetDuplicationState,
  };
}
