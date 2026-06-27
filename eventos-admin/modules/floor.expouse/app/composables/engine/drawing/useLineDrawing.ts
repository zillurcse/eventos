// useLineDrawing.ts
import type { Point, CanvasObject } from "@floorplan/types/canvas";

export function useLineDrawing(store: any, drawingState: any) {
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

  return {
    startLineDrawing,
    continueLineDrawing,
  };
}
