// useCanvasSelection.ts
import type { Point, CanvasObject } from "@floorplan/types/canvas";
import { useCanvasObjects } from "@floorplan/composables/useCanvasObjects";
import { debounce } from "@floorplan/composables/useDebounce";

export function useCanvasSelection(store: any) {
  const canvasObjects = useCanvasObjects();

  const debouncedSave = debounce(() => {
    console.log("🔄 Debounced save triggered");
    store.save();
  }, 1000);

  const selectObject = (object: CanvasObject) => {
    if (object.isLocked || object.isVisible === false) {
      return;
    }

    clearAllSelections();
    object.isSelected = true;
    store.selectedObjects = [object];
    store.selectedElementId = null;

    console.log(`Selected object: ${object.type} (${object.id})`);
  };

  const selectFloorWall = (floorWall: CanvasObject) => {
    clearAllSelections();
    floorWall.isSelected = true;
    store.selectedObjects = [floorWall];
    store.selectedElementId = null;

    console.log(`Selected floor wall: ${floorWall.id}`);
  };

  const clearAllSelections = () => {
    store.objects.forEach((obj: CanvasObject) => {
      obj.isSelected = false;
    });
    store.selectedObjects = [];
    store.selectedElementId = null;
  };

  const startSelecting = (point: Point, zoom: number, offset: Point) => {
    const worldPoint = {
      x: point.x / zoom + offset.x,
      y: point.y / zoom + offset.y,
    };
    // This would be set in drawingState which is managed by useCanvasDrawing
  };

  const continueSelecting = (point: Point, zoom: number, offset: Point) => {
    // This would update drawingState which is managed by useCanvasDrawing
  };

  const stopSelecting = () => {
    // This implementation would use drawingState from useCanvasDrawing
    // For now, it's kept as a placeholder since the actual implementation
    // depends on the drawingState managed by useCanvasDrawing
  };

  return {
    selectObject,
    selectFloorWall,
    clearAllSelections,
    startSelecting,
    continueSelecting,
    stopSelecting,
  };
}
