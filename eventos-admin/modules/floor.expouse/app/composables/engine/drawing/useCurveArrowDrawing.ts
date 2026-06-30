// useCurveArrowDrawing.ts
import type { Point, CanvasObject } from "@floorplan/types/canvas";

export function useCurveArrowDrawing(store: any, drawingState: any) {
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

  return {
    startCurveArrowDrawing,
    addCurveArrowPoint,
    updateCurveArrowPreview,
    cancelCurveArrowDrawing,
    finishCurveArrowDrawing,
  };
}
