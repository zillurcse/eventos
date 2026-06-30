// useCanvasState.ts
import { ref } from "vue";
import type { Point, CanvasObject } from "@floorplan/types/canvas";

export function useCanvasState() {
  const drawingState = ref({
    isDrawing: false,
    isSelecting: false,
    startPoint: { x: 0, y: 0 } as Point,
    currentPoint: { x: 0, y: 0 } as Point,
    points: [] as Point[],
    tempObject: null as CanvasObject | null,
    curveArrowState: {
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
  });

  return {
    drawingState,
  };
}
