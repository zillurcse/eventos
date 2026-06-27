// useCanvasRendering.ts → WITH CIRCLE FUNCTIONALITY REMOVED
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useUiStore } from "@floorplan/stores/uiStore";
import type { CanvasObject, Point } from "@floorplan/types/canvas";
import { useCanvasObjects } from "@floorplan/composables/useCanvasObjects";
import { useCanvasProperties } from "@floorplan/composables/useCanvasProperties";
import { useCanvasLOD } from "@floorplan/composables/useCanvasLOD";
import { useObjectManipulation } from "@floorplan/composables/useObjectManipulation";

// All composables
import { useCanvasHover } from "@floorplan/composables/canvas/useCanvasHover";
import { useCanvasPencil } from "@floorplan/composables/canvas/useCanvasPencil";
import { useCanvasLine } from "@floorplan/composables/canvas/useCanvasLine";
import { useCanvasArrow } from "@floorplan/composables/canvas/useCanvasArrow";
import { useCanvasCurveArrow } from "@floorplan/composables/canvas/useCanvasCurveArrow";
import { useCanvasWall } from "@floorplan/composables/canvas/useCanvasWall";
import { useCanvasRectangle } from "@floorplan/composables/canvas/useCanvasRectangle";
import { useCanvasBooth } from "@floorplan/composables/canvas/useCanvasBooth";
// ❌ REMOVED: Circle composable import
// import { useCanvasCircle } from "@floorplan/composables/canvas/useCanvasCircle";
import { useCanvasEllipse } from "@floorplan/composables/canvas/useCanvasEllipse";
import { useCanvasText } from "@floorplan/composables/canvas/useCanvasText";
import { useCanvasShape } from "@floorplan/composables/canvas/useCanvasShape";
import { useCanvasElement } from "@floorplan/composables/canvas/useCanvasElement";
import { useCanvasImage } from "@floorplan/composables/canvas/useCanvasImage";
import { useTwoHeadedArrowLine } from "@floorplan/composables/canvas/useTwoHeadedArrowLine";
import { useCanvasDoorArc } from "@floorplan/composables/canvas/useCanvasDoorArc";
import { useFrame } from "@floorplan/composables/canvas/useFrame";
import { useSection } from "@floorplan/composables/canvas/useSection";

export function useCanvasRendering() {
  const store = useCanvasStore();
  const uiStore = useUiStore();
  const lod = useCanvasLOD();
  const objectManipulation = useObjectManipulation();

  const { getCenter, rotatePoint } = useCanvasObjects();
  const { applyDrawingProperties, applyAppearanceProperties } =
    useCanvasProperties();

  // Composables
  const hoverComposable = useCanvasHover(uiStore);
  const pencilComposable = useCanvasPencil();
  const lineComposable = useCanvasLine();
  const arrowComposable = useCanvasArrow();
  const curveArrowComposable = useCanvasCurveArrow();
  const wallComposable = useCanvasWall(uiStore);
  const rectangleComposable = useCanvasRectangle(getCenter, rotatePoint);
  const boothComposable = useCanvasBooth(uiStore, getCenter, rotatePoint);
  // ❌ REMOVED: Circle composable initialization
  // const circleComposable = useCanvasCircle();
  const ellipseComposable = useCanvasEllipse();
  const textComposable = useCanvasText();
  const shapeComposable = useCanvasShape(getCenter, rotatePoint);
  const elementComposable = useCanvasElement();
  const imageComposable = useCanvasImage();
  const twoHeadedArrowLine = useTwoHeadedArrowLine();
  const doorArcComposable = useCanvasDoorArc();
  const frameComposable = useFrame();
  const sectionComposable = useSection();

  const worldToScreen = (point: Point, zoom: number, offset: Point): Point => ({
    x: (point.x - offset.x) * zoom,
    y: (point.y - offset.y) * zoom,
  });

  const screenToWorld = (point: Point, zoom: number, offset: Point): Point => ({
    x: point.x / zoom + offset.x,
    y: point.y / zoom + offset.y,
  });

  const renderSmartAlignmentAxis = (
    ctx: CanvasRenderingContext2D,
    zoom: number,
    offset: Point,
    alignmentGuides: AlignmentGuide[]
  ) => {
    if (!alignmentGuides || alignmentGuides.length === 0) return;

    const canvasWidth = ctx.canvas.width / (window.devicePixelRatio || 1);
    const canvasHeight = ctx.canvas.height / (window.devicePixelRatio || 1);

    ctx.save();
    ctx.setLineDash([3, 3]);
    ctx.lineCap = "round";

    alignmentGuides.forEach((guide) => {
      const isCenter =
        guide.alignment === "centerX" || guide.alignment === "centerY";
      const color = isCenter ? "#aa313b" : "#aa333b";
      const width = isCenter ? 1.5 : 1.5;

      ctx.lineWidth = width;
      ctx.strokeStyle = color;
      ctx.shadowColor = color;
      ctx.shadowBlur = 10;

      if (guide.type === "vertical") {
        const x = (guide.position - offset.x) * zoom;
        if (x > -300 && x < canvasWidth + 300) {
          ctx.beginPath();
          ctx.moveTo(x, 0);
          ctx.lineTo(x, canvasHeight);
          ctx.stroke();
          ctx.shadowBlur = 0;
          ctx.stroke();
        }
      } else {
        const y = (guide.position - offset.y) * zoom;
        if (y > -300 && y < canvasHeight + 300) {
          ctx.beginPath();
          ctx.moveTo(0, y);
          ctx.lineTo(canvasWidth, y);
          ctx.stroke();
          ctx.shadowBlur = 0;
          ctx.stroke();
        }
      }
    });

    ctx.restore();
  };

  const renderAlignmentGuides = (
    ctx: CanvasRenderingContext2D,
    zoom: number,
    offset: Point
  ) => {
    const guides = objectManipulation.alignmentGuides.value;

    if (!guides || guides.length === 0) {
      return;
    }

    renderSmartAlignmentAxis(ctx, zoom, offset, guides);
  };

  const renderObject = (
    ctx: CanvasRenderingContext2D,
    obj: CanvasObject,
    zoom: number,
    offset: Point,
    isHovered: boolean = false
  ) => {
    if (obj.isVisible === false) return;

    const w2s = (p: Point) => worldToScreen(p, zoom, offset);

    applyAppearanceProperties(ctx, obj);

    // LOD skip for tiny objects (except booth/wall)
    if (obj.type !== "booth" && obj.type !== "wall") {
      const size =
        obj.points?.length >= 2
          ? Math.max(
              Math.abs(obj.points[1].x - obj.points[0].x),
              Math.abs(obj.points[1].y - obj.points[0].y)
            ) * zoom
          : 30;

      if (size < 8 && lod.getLOD() === "low") {
        lod.renderAsDot(ctx, obj, zoom, offset, w2s);
        return;
      }
    }

    ctx.save();
    applyDrawingProperties(ctx, obj, zoom);

    const rotatedPoints = obj.rotation
      ? obj.points.map((p) => rotatePoint(p, getCenter(obj), obj.rotation))
      : obj.points;

    switch (obj.type) {
      case "booth":
        boothComposable.renderBooth(ctx, obj, zoom, offset, w2s, isHovered);
        break;
      case "pencil":
        pencilComposable.renderPencil(ctx, rotatedPoints, zoom, offset, w2s);
        break;
      case "line":
        lineComposable.renderLine(ctx, rotatedPoints, zoom, offset, w2s, obj);
        break;
      case "arrow":
        arrowComposable.renderArrow(ctx, rotatedPoints, zoom, offset, w2s, obj);
        break;
      case "curve-arrow":
        curveArrowComposable.renderCurveArrow(
          ctx,
          rotatedPoints,
          zoom,
          offset,
          w2s,
          obj
        );
        break;
      case "two-headed-arrow":
        twoHeadedArrowLine.renderTwoHeadedArrowLine(
          ctx,
          rotatedPoints,
          zoom,
          offset,
          w2s,
          obj
        );
        break;
      case "wall":
        wallComposable.renderWall(ctx, rotatedPoints, zoom, offset, w2s, obj);
        break;
      case "rectangle":
        rectangleComposable.renderRectangle(ctx, obj, zoom, offset, w2s, isHovered);
        break;
      case "frame":
        frameComposable.renderFrame(ctx, obj, zoom, offset, w2s, isHovered);
        break;
      case "section":
        sectionComposable.renderSection(ctx, obj, zoom, offset, w2s, isHovered);
        break;
      case "ellipse":
        ellipseComposable.renderEllipse(ctx, obj, zoom, offset, w2s, isHovered);
        break;
      case "text":
        textComposable.renderText(ctx, obj, zoom, offset, w2s);
        break;
      case "element":
        elementComposable.renderElement(ctx, obj, zoom, offset, w2s, isHovered);
        break;
      case "image":
        imageComposable.renderImage(ctx, obj, zoom, offset, w2s, isHovered);
        break;
      case "door-arc":
        doorArcComposable.renderDoorArc(
          ctx,
          rotatedPoints,
          zoom,
          offset,
          w2s,
          obj
        );
        break;
      default:
        if (
          ["diamond", "pentagon", "hexagon", "triangle", "star"].includes(
            obj.type
          )
        ) {
          shapeComposable.renderShape(ctx, obj, zoom, offset, w2s, isHovered);
        }
        break;
    }

    ctx.restore();

    // Show lock icon for locked objects only on hover
    if (obj.isLocked && isHovered) {
      renderLockIcon(ctx, obj, zoom, offset);
    }

    // Show LOCKED text watermark only on hover
    if (obj.isLocked) {
      renderLockedLabel(ctx, obj, zoom, offset);
    }
  };

  // Load lock icon SVG once
  const lockIconImage = new Image();
  lockIconImage.src = '/img/icon/lock.svg';

  const renderLockIcon = (
    ctx: CanvasRenderingContext2D,
    obj: CanvasObject,
    zoom: number,
    offset: Point
  ) => {
    const bounds = useCanvasObjects().getRotatedBounding(obj);
    if (!bounds) return;

    const screenPos = worldToScreen({ x: bounds.x, y: bounds.y }, zoom, offset);
    
    ctx.save();
    
    // Position icon at the top-left corner
    const iconSize = 18;
    const iconX = screenPos.x - iconSize - 2;
    const iconY = screenPos.y - iconSize - 2;
    
    // Draw the SVG lock icon
    if (lockIconImage.complete) {
      ctx.drawImage(lockIconImage, iconX, iconY, iconSize, iconSize);
    }
    
    ctx.restore();
  };

  const renderLockedLabel = (
    ctx: CanvasRenderingContext2D,
    obj: CanvasObject,
    zoom: number,
    offset: Point
  ) => {
    const bounds = useCanvasObjects().getRotatedBounding(obj);
    if (!bounds) return;

    const screenPos = worldToScreen({ x: bounds.x, y: bounds.y }, zoom, offset);
    const screenWidth = bounds.width * zoom;
    const screenHeight = bounds.height * zoom;

    ctx.save();
    
    // Watermark styling: semi-transparent, centered, rotated if needed
    ctx.font = `bold ${Math.min(10, screenWidth / 3)}px Inter, sans-serif`;
    const text = "L O C K E D"; // Spaces for letter-spacing
    const textMetrics = ctx.measureText(text);
    
    // Position at the top, just under the top edge
    const centerX = screenPos.x + screenWidth / 2;
    const paddingX = 6;
    const paddingY = 4;
    const fontSize = Math.min(10, screenWidth / 3);
    const labelHeight = Math.min(fontSize + paddingY * 2, screenHeight / 3);
    const centerY = screenPos.y + labelHeight / 2 + 5;

    ctx.translate(centerX, centerY);
    if (obj.rotation) {
      ctx.rotate((obj.rotation * Math.PI) / 180);
    }

    // Border and background
    const labelWidth = textMetrics.width + paddingX * 2;
    ctx.beginPath();
    ctx.roundRect(-labelWidth / 2, -labelHeight / 2, labelWidth, labelHeight, 2); // Rounded a little bit
    
    // Slight transparent background
    ctx.globalAlpha = 0.05;
    ctx.fillStyle = "#000000";
    ctx.fill();

    // Clean border around
    ctx.globalAlpha = 0.2;
    ctx.strokeStyle = "#a31111ff";
    ctx.lineWidth = 0.8;
    ctx.stroke();

    // Text watermark with gray color and spacing
    ctx.globalAlpha = 0.5;
    ctx.fillStyle = "#374151"; // Darker gray for better readability with spacing
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillText(text, 0, 0.5); // Nudged down slightly for visual balance

    ctx.restore();
  };

  return {
    worldToScreen,
    screenToWorld,
    renderObject,
    renderAlignmentGuides,
    renderSmartAlignmentAxis,
    renderPencil: pencilComposable.renderPencil,
    renderLine: lineComposable.renderLine,
    renderArrow: arrowComposable.renderArrow,
    renderCurveArrow: curveArrowComposable.renderCurveArrow,
    renderTwoHeadedArrowLine: twoHeadedArrowLine.renderTwoHeadedArrowLine,
    renderWall: wallComposable.renderWall,
    renderRectangle: rectangleComposable.renderRectangle,
    renderBooth: boothComposable.renderBooth,
    renderEllipse: ellipseComposable.renderEllipse,
    renderText: textComposable.renderText,
    renderShape: shapeComposable.renderShape,
    renderElement: elementComposable.renderElement,
    renderImage: imageComposable.renderImage,
    updateHoverState: hoverComposable.updateHoverState,
    getObjectMeasurements: hoverComposable.getObjectMearements,
    renderHoverMeasurements: hoverComposable.renderHoverMeasurements,
    hoverState: hoverComposable.hoverState,
    renderDoorArc: doorArcComposable.renderDoorArc,
  };
}
