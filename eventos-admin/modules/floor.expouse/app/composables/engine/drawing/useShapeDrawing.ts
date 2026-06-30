// useShapeDrawing.ts
import type { Point, CanvasObject } from "@floorplan/types/canvas";

export function useShapeDrawing(store: any, drawingState: any) {
  const startShapeDrawing = (point: Point) => {
    drawingState.value.tempObject = {
      id: Date.now().toString(),
      type: store.currentTool,
      points: [point, point],
      color: store.currentColor,
      isSelected: false,
      rotation: 0,
    };
  };

  const continueShapeDrawing = (point: Point, shiftKey: boolean) => {
    if (drawingState.value.tempObject) {
      let endPoint = point;
      if (shiftKey && store.currentTool === "rectangle") {
        const start = drawingState.value.tempObject.points[0];
        const dx = point.x - start.x;
        const dy = point.y - start.y;
        const size = Math.max(Math.abs(dx), Math.abs(dy));
        endPoint = {
          x: start.x + size * Math.sign(dx),
          y: start.y + size * Math.sign(dy),
        };
      } else if (shiftKey && store.currentTool === "ellipse") {
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

  return {
    startShapeDrawing,
    continueShapeDrawing,
  };
}
