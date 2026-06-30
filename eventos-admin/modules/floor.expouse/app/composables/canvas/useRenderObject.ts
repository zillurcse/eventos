// useRenderObject.ts
import type { Point, CanvasObject } from "@floorplan/types/canvas";
import { useCanvasObjects } from "@floorplan/composables/useCanvasObjects";

// Import all element composables
import { useCanvasPencil } from "@floorplan/composables/canvas/useCanvasPencil";
import { useCanvasLine } from "@floorplan/composables/canvas/useCanvasLine";
import { useCanvasArrow } from "@floorplan/composables/canvas/useCanvasArrow";
import { useCanvasCurveArrow } from "@floorplan/composables/canvas/useCanvasCurveArrow";
import { useCanvasWall } from "@floorplan/composables/canvas/useCanvasWall";
import { useCanvasRectangle } from "@floorplan/composables/canvas/useCanvasRectangle";
import { useCanvasBooth } from "@floorplan/composables/canvas/useCanvasBooth";

import { useCanvasEllipse } from "@floorplan/composables/canvas/useCanvasEllipse";
import { useCanvasText } from "@floorplan/composables/canvas/useCanvasText";
import { useCanvasShape } from "@floorplan/composables/canvas/useCanvasShape";
import { useCanvasElement } from "@floorplan/composables/canvas/useCanvasElement";
import { useCanvasImage } from "@floorplan/composables/canvas/useCanvasImage";

export function useRenderObject() {
  const canvasObjects = useCanvasObjects();

  // Initialize all composables with proper dependencies
  const pencilComposable = useCanvasPencil();
  const lineComposable = useCanvasLine();
  const arrowComposable = useCanvasArrow();
  const curveArrowComposable = useCanvasCurveArrow();
  const wallComposable = useCanvasWall();
  const rectangleComposable = useCanvasRectangle(
    canvasObjects.getCenter,
    canvasObjects.rotatePoint
  );
  const boothComposable = useCanvasBooth(
    canvasObjects.getCenter,
    canvasObjects.rotatePoint
  );

  const ellipseComposable = useCanvasEllipse();
  const textComposable = useCanvasText();
  const shapeComposable = useCanvasShape(
    canvasObjects.getCenter,
    canvasObjects.rotatePoint
  );
  const elementComposable = useCanvasElement();
  const imageComposable = useCanvasImage();

  const worldToScreen = (point: Point, zoom: number, offset: Point): Point => ({
    x: (point.x - offset.x) * zoom,
    y: (point.y - offset.y) * zoom,
  });

  const renderObject = (
    ctx: CanvasRenderingContext2D,
    obj: CanvasObject,
    zoom: number,
    offset: Point
  ) => {
    // Skip rendering if object is not visible
    if (obj.isVisible === false) {
      return;
    }

    ctx.strokeStyle = obj.color;
    ctx.lineWidth = 2;

    const rotation = obj.rotation || 0;
    let rotatedPoints = obj.points;
    if (
      rotation !== 0 &&
      ["pencil", "line", "arrow", "curve-arrow", "wall"].includes(obj.type)
    ) {
      const center = canvasObjects.getCenter(obj);
      rotatedPoints = obj.points.map((p) =>
        canvasObjects.rotatePoint(p, center, rotation)
      );
    }

    switch (obj.type) {
      case "pencil":
        pencilComposable.renderPencil(
          ctx,
          rotatedPoints,
          zoom,
          offset,
          worldToScreen
        );
        break;
      case "line":
        lineComposable.renderLine(
          ctx,
          rotatedPoints,
          zoom,
          offset,
          worldToScreen
        );
        break;
      case "arrow":
        arrowComposable.renderArrow(
          ctx,
          rotatedPoints,
          zoom,
          offset,
          worldToScreen
        );
        break;
      case "curve-arrow":
        curveArrowComposable.renderCurveArrow(
          ctx,
          rotatedPoints,
          zoom,
          offset,
          worldToScreen
        );
        break;
      case "wall":
        wallComposable.renderWall(
          ctx,
          rotatedPoints,
          zoom,
          offset,
          worldToScreen
        );
        break;
      case "rectangle":
        rectangleComposable.renderRectangle(
          ctx,
          obj,
          zoom,
          offset,
          worldToScreen
        );
        break;
      case "booth":
        boothComposable.renderBooth(ctx, obj, zoom, offset, worldToScreen);
        break;

      case "ellipse":
        ellipseComposable.renderEllipse(ctx, obj, zoom, offset, worldToScreen);
        break;
      case "text":
        textComposable.renderText(ctx, obj, zoom, offset, worldToScreen);
        break;
      case "element":
        elementComposable.renderElement(ctx, obj, zoom, offset, worldToScreen);
        break;
      case "image":
        imageComposable.renderImage(ctx, obj, zoom, offset, worldToScreen);
        break;
      default:
        if (
          ["diamond", "pentagon", "hexagon", "triangle", "star"].includes(
            obj.type
          )
        ) {
          shapeComposable.renderShape(ctx, obj, zoom, offset, worldToScreen);
        }
        break;
    }
  };

  return {
    renderObject,
    // Also export individual methods if needed
    renderPencil: pencilComposable.renderPencil,
    renderLine: lineComposable.renderLine,
    renderArrow: arrowComposable.renderArrow,
    renderCurveArrow: curveArrowComposable.renderCurveArrow,
    renderWall: wallComposable.renderWall,
    renderRectangle: rectangleComposable.renderRectangle,
    renderBooth: boothComposable.renderBooth,

    renderEllipse: ellipseComposable.renderEllipse,
    renderText: textComposable.renderText,
    renderShape: shapeComposable.renderShape,
    renderElement: elementComposable.renderElement,
    renderImage: imageComposable.renderImage,
  };
}
