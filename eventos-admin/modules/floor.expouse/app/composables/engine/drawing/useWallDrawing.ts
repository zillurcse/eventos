// useWallDrawing.ts
import type { Point, CanvasObject } from "@floorplan/types/canvas";

export function useWallDrawing(store: any, drawingState: any) {
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
    // No floor area constraints for manual wall drawing
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

      // REMOVED: Auto-closing logic that adds fourth point
      // Let the user manually complete the wall shape
      // Only add the wall if we have at least 2 points (a valid segment)
      if (points.length >= 2) {
        drawingState.value.tempObject.points = [
          ...drawingState.value.wallState.points,
        ];
        store.addObject(drawingState.value.tempObject);
      }

      drawingState.value.tempObject = null;
    }

    // Reset wall drawing state
    drawingState.value.wallState.isDrawing = false;
    drawingState.value.wallState.points = [];
    drawingState.value.wallState.currentPreviewPoint = null;
  };

  return {
    startWallDrawing,
    addWallPoint,
    updateWallPreview,
    cancelWallDrawing,
    finishWallDrawing,
  };
}
