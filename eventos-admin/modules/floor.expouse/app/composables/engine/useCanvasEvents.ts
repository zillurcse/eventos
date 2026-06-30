// useCanvasEvents.ts
import { ref } from "vue";
import type { Point, CanvasObject } from "@floorplan/types/canvas";
import { useObjectManipulation } from "@floorplan/composables/useObjectManipulation";
import { useCanvasObjects } from "@floorplan/composables/useCanvasObjects";
import { useCanvasRendering } from "@floorplan/composables/useCanvasRendering";
import { debounce } from "@floorplan/composables/engine/useDebounce";

export function useCanvasEvents(
  canvasEl: Ref<HTMLCanvasElement | undefined>,
  store: any,
  uiStore: any,
  canvasDrawing: any,
  canvasSelection: any,
  canvasPanning: any,
  objectDetection: any,
  objectManipulation: any,
  boothArrows: any,
  canvasRendering: any,
  canvasObjects: any
) {
  const { getArrowAtPoint } = boothArrows;

  const worldToScreen = (point: Point, zoom: number, offset: Point): Point => ({
    x: (point.x - offset.x) * zoom,
    y: (point.y - offset.y) * zoom,
  });

  const screenToWorld = (point: Point, zoom: number, offset: Point): Point => ({
    x: point.x / zoom + offset.x,
    y: point.y / zoom + offset.y,
  });

  const debouncedSave = debounce(() => {
    console.log("🔄 Debounced save triggered");
    store.save();
  }, 1000);

  const handleDoubleClick = (event: MouseEvent) => {
    if (!canvasEl.value || !store.currentTool) return;

    const rect = canvasEl.value.getBoundingClientRect();
    const point = {
      x: event.clientX - rect.left,
      y: event.clientY - rect.top,
    };

    // Handle wall drawing double-click specifically
    if (
      store.currentTool === "wall" &&
      canvasDrawing.drawingState.value.wallState.isDrawing
    ) {
      const worldPoint = screenToWorld(point, store.zoom, store.offset);

      // Only add the point if we're not at the minimum required points
      if (canvasDrawing.drawingState.value.wallState.points.length < 2) {
        canvasDrawing.addWallPoint(worldPoint);
      }

      // Finish the wall drawing with the current points (no auto-closing)
      canvasDrawing.finishWallDrawing();
      canvasRendering.render();
      return;
    }

    canvasDrawing.startDrawing(point, store.zoom, store.offset, true);
    canvasRendering.render();
  };

  const handleMouseDown = (event: MouseEvent) => {
    if (!canvasEl.value || !store.currentTool) return;

    const rect = canvasEl.value.getBoundingClientRect();
    const point = {
      x: event.clientX - rect.left,
      y: event.clientY - rect.top,
    };

    if (store.currentTool === "wall") {
      const worldPoint = screenToWorld(point, store.zoom, store.offset);

      if (!canvasDrawing.drawingState.value.wallState.isDrawing) {
        canvasDrawing.startWallDrawing(worldPoint);
      } else {
        canvasDrawing.addWallPoint(worldPoint);
      }

      canvasDrawing.drawingState.value.wallState.currentPreviewPoint =
        worldPoint;
      canvasRendering.render();
      return;
    }

    if (store.currentTool === "select") {
      const selectedBooth = store.objects.find(
        (obj: CanvasObject) => obj.type === "booth" && obj.isSelected
      );
      if (selectedBooth) {
        const arrowDirection = getArrowAtPoint(
          point,
          selectedBooth,
          store.zoom,
          store.offset
        );
        if (arrowDirection) {
          return;
        }
      }

      const worldPoint = screenToWorld(point, store.zoom, store.offset);

      // Check for handle interactions first (rotation/resize)
      const handleInfo = objectManipulation.getHandleAtPoint(worldPoint);

      if (handleInfo) {
        // Check if the object is locked before allowing transformation
        const targetObject = store.selectedObjects[0];
        if (targetObject && targetObject.isLocked) {
          return;
        }

        if (handleInfo.type === "rotation") {
          objectManipulation.startRotating(worldPoint);
        } else {
          objectManipulation.startResizing(worldPoint, handleInfo.index);
        }
        canvasRendering.render();
        return;
      }

      // Check if clicking on any selectable canvas object
      const clickedObject =
        objectDetection.findSelectableObjectAtPoint(worldPoint);

      if (clickedObject) {
        // If the clicked object is not already selected, select it first
        if (!clickedObject.isSelected) {
          canvasSelection.selectObject(clickedObject);
        }

        // Then start dragging all selected objects (only if not locked)
        const canDrag = store.selectedObjects.every(
          (obj: CanvasObject) => !obj.isLocked
        );
        if (canDrag) {
          objectManipulation.startDragging(worldPoint);
        }
        canvasRendering.render();
        return;
      }

      // If no object was clicked, clear selection and start area selection
      canvasSelection.clearAllSelections();
      canvasSelection.startSelecting(point, store.zoom, store.offset);
    } else if (store.currentTool === "hand") {
      canvasPanning.startPanning(point);
    } else {
      // For drawing tools, allow free placement anywhere
      canvasDrawing.startDrawing(point, store.zoom, store.offset);
    }
    canvasRendering.render();
  };

  const handleMouseMove = (event: MouseEvent) => {
    if (!canvasEl.value || !store.currentTool) return;

    const rect = canvasEl.value.getBoundingClientRect();
    const point = {
      x: event.clientX - rect.left,
      y: event.clientY - rect.top,
    };

    const inside =
      point.x >= 0 &&
      point.x <= rect.width &&
      point.y >= 0 &&
      point.y <= rect.height;

    if (!inside) {
      // If mouse is outside and no active interaction, skip processing
      if (
        !canvasDrawing.drawingState.value.isDrawing &&
        !canvasDrawing.drawingState.value.isSelecting &&
        !canvasPanning.isPanning.value &&
        !objectManipulation.interactionState.value.isDragging &&
        !objectManipulation.interactionState.value.isResizing &&
        !objectManipulation.interactionState.value.isRotating &&
        !canvasDrawing.drawingState.value.wallState.isDrawing &&
        !canvasDrawing.drawingState.value.curveArrowState.isDrawing
      ) {
        if (canvasEl.value) {
          canvasEl.value.style.cursor = "default";
        }
        return;
      }
    }

    // Update cursor based on current interaction
    const cursor = objectManipulation.updateCursor(
      point,
      store.zoom,
      store.offset
    );
    if (canvasEl.value) {
      canvasEl.value.style.cursor = cursor;
    }

    const worldPoint = screenToWorld(point, store.zoom, store.offset);

    // Update hover state only if inside canvas (respect visibility)
    if (inside) {
      const hoveredObject = objectDetection.findObjectAtPoint(worldPoint);
      canvasRendering.updateHoverState(hoveredObject, point);
    }

    // Handle wall preview specifically - this is crucial
    if (
      store.currentTool === "wall" &&
      canvasDrawing.drawingState.value.wallState.isDrawing
    ) {
      canvasDrawing.draw(point, store.zoom, store.offset, event.shiftKey);
      return;
    }

    // Rest of existing handleMouseMove logic...
    if (
      store.currentTool === "select" &&
      canvasDrawing.drawingState.value.isSelecting
    ) {
      canvasSelection.continueSelecting(point, store.zoom, store.offset);
    } else if (store.currentTool === "hand" && canvasPanning.isPanning.value) {
      canvasPanning.doPanning(point);
    } else if (canvasDrawing.drawingState.value.isDrawing) {
      canvasDrawing.draw(point, store.zoom, store.offset, event.shiftKey);
    } else if (objectManipulation.interactionState.value.isDragging) {
      // Check if any selected object is locked before allowing drag
      const canDrag = store.selectedObjects.every(
        (obj: CanvasObject) => !obj.isLocked
      );
      if (canDrag) {
        objectManipulation.doDragging(point, store.zoom, store.offset);
      }
    } else if (objectManipulation.interactionState.value.isResizing) {
      // Check if the resized object is locked
      const targetObject = store.selectedObjects[0];
      if (targetObject && !targetObject.isLocked) {
        objectManipulation.doResizing(point, store.zoom, store.offset);
      }
    } else if (objectManipulation.interactionState.value.isRotating) {
      // Check if the rotated object is locked
      const targetObject = store.selectedObjects[0];
      if (targetObject && !targetObject.isLocked) {
        objectManipulation.doRotating(point, store.zoom, store.offset);
      }
    }

    canvasRendering.render();
  };

  const handleMouseUp = (event: MouseEvent) => {
    if (!canvasEl.value) return;

    const rect = canvasEl.value.getBoundingClientRect();
    const point = {
      x: event.clientX - rect.left,
      y: event.clientY - rect.top,
    };

    if (
      store.currentTool === "select" &&
      canvasDrawing.drawingState.value.isSelecting
    ) {
      canvasSelection.stopSelecting();
    } else if (store.currentTool === "hand") {
      canvasPanning.stopPanning();
    } else {
      canvasDrawing.stopDrawing(point, store.zoom, store.offset);
    }

    // Always stop rotation on mouse up
    if (objectManipulation.interactionState.value.isRotating) {
      objectManipulation.stopRotating();
    }

    // Stop other manipulations
    objectManipulation.stopObjectManipulation();

    canvasRendering.render();
  };

  const handleWheel = (event: WheelEvent) => {
    event.preventDefault();
    if (event.deltaY < 0) {
      store.zoomIn();
    } else {
      store.zoomOut();
    }
    canvasRendering.render();
  };

  const handleKeyDown = (event: KeyboardEvent) => {
    if (
      event.key === "Escape" &&
      store.currentTool === "curve-arrow" &&
      canvasDrawing.drawingState.value.curveArrowState.isDrawing
    ) {
      canvasDrawing.cancelCurveArrowDrawing();
      canvasRendering.render();
    } else if (
      event.key === "Escape" &&
      store.currentTool === "wall" &&
      canvasDrawing.drawingState.value.wallState.isDrawing
    ) {
      canvasDrawing.cancelWallDrawing();
      canvasRendering.render();
    }
  };

  const setupEventListeners = () => {
    window.addEventListener("keydown", handleKeyDown);
    window.addEventListener("resize", canvasRendering.resizeCanvas);
    window.addEventListener("mousemove", handleMouseMove);
    window.addEventListener("mouseup", handleMouseUp);
    if (canvasEl.value) {
      canvasEl.value.addEventListener("mousedown", handleMouseDown);
      canvasEl.value.addEventListener("dblclick", handleDoubleClick);
      canvasEl.value.addEventListener("wheel", handleWheel);
    }
  };

  const cleanupEventListeners = () => {
    window.removeEventListener("keydown", handleKeyDown);
    window.removeEventListener("resize", canvasRendering.resizeCanvas);
    window.removeEventListener("mousemove", handleMouseMove);
    window.removeEventListener("mouseup", handleMouseUp);
    if (canvasEl.value) {
      canvasEl.value.removeEventListener("mousedown", handleMouseDown);
      canvasEl.value.removeEventListener("dblclick", handleDoubleClick);
      canvasEl.value.removeEventListener("wheel", handleWheel);
    }
  };

  return {
    handleDoubleClick,
    handleMouseDown,
    handleMouseMove,
    handleMouseUp,
    handleWheel,
    handleKeyDown,
    setupEventListeners,
    cleanupEventListeners,
    interactionState: objectManipulation.interactionState,
  };
}
