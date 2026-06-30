// usePencilDrawing.ts
import type { Point, CanvasObject } from "@floorplan/types/canvas";

export function usePencilDrawing(store: any, drawingState: any) {
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
      drawingState.value.tempObject.points.push(point);
    }
  };

  return {
    startPencilDrawing,
    continuePencilDrawing,
  };
}
