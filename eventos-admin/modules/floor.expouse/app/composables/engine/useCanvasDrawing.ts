// useCanvasDrawing.ts
import { ref } from "vue";
import type { Point, CanvasObject } from "@floorplan/types/canvas";
import { usePencilDrawing } from "@floorplan/composables/engine/drawing/usePencilDrawing";
import { useLineDrawing } from "@floorplan/composables/engine/drawing/useLineDrawing";
import { useCurveArrowDrawing } from "@floorplan/composables/engine/drawing/useCurveArrowDrawing";
import { useWallDrawing } from "@floorplan/composables/engine/drawing/useWallDrawing";
import { useShapeDrawing } from "@floorplan/composables/engine/drawing/useShapeDrawing";
import { debounce } from "@floorplan/composables/engine/useDebounce";

export function useCanvasDrawing(store: any, canvasSelection?: any) {
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

  const pencilDrawing = usePencilDrawing(store, drawingState);
  const lineDrawing = useLineDrawing(store, drawingState);
  const curveArrowDrawing = useCurveArrowDrawing(store, drawingState);
  const wallDrawing = useWallDrawing(store, drawingState);
  const shapeDrawing = useShapeDrawing(store, drawingState);

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

  const startDrawing = (
    point: Point,
    zoom: number,
    offset: Point,
    isDoubleClick: boolean = false
  ) => {
    const worldPoint = screenToWorld(point, zoom, offset);

    // REMOVE floor constraints for all canvas drawing tools
    // Canvas objects (pencil, line, arrow, curve-arrow, rectangle, circle, ellipse, wall)
    // can be placed anywhere on the whiteboard

    if (
      isDoubleClick &&
      store.currentTool === "curve-arrow" &&
      drawingState.value.curveArrowState.isDrawing
    ) {
      curveArrowDrawing.finishCurveArrowDrawing();
      return;
    } else if (
      isDoubleClick &&
      store.currentTool === "wall" &&
      drawingState.value.wallState.isDrawing
    ) {
      wallDrawing.finishWallDrawing();
      return;
    }

    drawingState.value.isDrawing = true;
    drawingState.value.startPoint = worldPoint;
    drawingState.value.currentPoint = worldPoint;

    switch (store.currentTool) {
      case "pencil":
        pencilDrawing.startPencilDrawing(worldPoint);
        break;
      case "line":
      case "arrow":
        lineDrawing.startLineDrawing(worldPoint);
        break;
      case "curve-arrow":
        if (!drawingState.value.curveArrowState.isDrawing) {
          curveArrowDrawing.startCurveArrowDrawing(worldPoint);
        } else {
          curveArrowDrawing.addCurveArrowPoint(worldPoint);
        }
        break;
      case "wall":
        if (!drawingState.value.wallState.isDrawing) {
          wallDrawing.startWallDrawing(worldPoint);
        } else {
          wallDrawing.addWallPoint(worldPoint);
        }
        break;
      case "rectangle":
      case "ellipse":
        shapeDrawing.startShapeDrawing(worldPoint);
        break;
    }
  };

  const draw = (
    point: Point,
    zoom: number,
    offset: Point,
    shiftKey: boolean = false
  ) => {
    const worldPoint = screenToWorld(point, zoom, offset);
    drawingState.value.currentPoint = worldPoint;

    // Handle wall preview specifically
    if (
      store.currentTool === "wall" &&
      drawingState.value.wallState.isDrawing
    ) {
      drawingState.value.wallState.currentPreviewPoint = worldPoint;
      wallDrawing.updateWallPreview();
      return;
    }

    if (!drawingState.value.isDrawing) return;

    switch (store.currentTool) {
      case "pencil":
        pencilDrawing.continuePencilDrawing(worldPoint);
        break;
      case "line":
      case "arrow":
        lineDrawing.continueLineDrawing(worldPoint, shiftKey);
        break;
      case "curve-arrow":
        if (drawingState.value.curveArrowState.isDrawing) {
          drawingState.value.curveArrowState.currentPreviewPoint = worldPoint;
          curveArrowDrawing.updateCurveArrowPreview();
        }
        break;
      case "rectangle":
      case "ellipse":
        shapeDrawing.continueShapeDrawing(worldPoint, shiftKey);
        break;
    }
  };

  const stopDrawing = (point: Point, zoom: number, offset: Point) => {
    const worldPoint = screenToWorld(point, zoom, offset);
    drawingState.value.currentPoint = worldPoint;

    if (
      store.currentTool === "curve-arrow" &&
      drawingState.value.curveArrowState.isDrawing
    ) {
      return;
    } else if (
      store.currentTool === "wall" &&
      drawingState.value.wallState.isDrawing
    ) {
      return;
    }

    if (drawingState.value.isDrawing && drawingState.value.tempObject) {
      if (
        ["line", "arrow", "rectangle", "ellipse"].includes(
          store.currentTool
        )
      ) {
        drawingState.value.tempObject.points[1] = worldPoint;
      } else if (store.currentTool === "pencil") {
        drawingState.value.tempObject.points.push(worldPoint);
      }
      store.addObject(drawingState.value.tempObject);
      drawingState.value.tempObject = null;
    }

    drawingState.value.isDrawing = false;

    // Use debounced save instead of direct save
    debouncedSave();
  };

  // Expose wall drawing methods for direct access
  const {
    startWallDrawing,
    addWallPoint,
    updateWallPreview,
    cancelWallDrawing,
    finishWallDrawing,
  } = wallDrawing;

  // Expose curve arrow methods for direct access
  const { cancelCurveArrowDrawing } = curveArrowDrawing;

  return {
    drawingState,
    startDrawing,
    draw,
    stopDrawing,
    startWallDrawing,
    addWallPoint,
    updateWallPreview,
    cancelWallDrawing,
    finishWallDrawing,
    cancelCurveArrowDrawing,
  };
}
