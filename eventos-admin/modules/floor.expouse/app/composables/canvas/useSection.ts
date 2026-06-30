import type { CanvasObject, Point } from "@floorplan/types/canvas";
import { useCanvasObjects } from "@floorplan/composables/useCanvasObjects";
import { useCanvasRectangle } from "@floorplan/composables/canvas/useCanvasRectangle";

export function useSection() {
  const { getCenter, rotatePoint, getRotatedBounding } = useCanvasObjects();
  const rectangleComposable = useCanvasRectangle(getCenter, rotatePoint);

  const renderSection = (
    ctx: CanvasRenderingContext2D,
    obj: CanvasObject,
    zoom: number,
    offset: Point,
    w2s: (p: Point) => Point,
    isHovered: boolean = false
  ) => {
    // 1. Render the rectangle body
    rectangleComposable.renderRectangle(ctx, obj, zoom, offset, w2s, isHovered);

    // 2. Render the label if visible
    if (obj.label && obj.labelVisible !== false) {
      renderLabel(ctx, obj, zoom, offset, w2s);
    }
  };

  const renderLabel = (
    ctx: CanvasRenderingContext2D,
    obj: CanvasObject,
    zoom: number,
    offset: Point,
    w2s: (p: Point) => Point
  ) => {
    const bounds = getRotatedBounding(obj);
    if (!bounds) return;

    const screenPos = w2s({ x: bounds.x, y: bounds.y });
    
    ctx.save();
    const fontSize = Math.max(12, 14 * zoom);
    ctx.font = `bold ${fontSize}px Inter, sans-serif`;
    
    const textWidth = ctx.measureText(obj.label!).width;
    const padding = 4;
    const labelHeight = fontSize + padding;
    
    // Position: Top-Left Outer
    const labelX = screenPos.x;
    const labelY = screenPos.y - 4;

    // Background box
    ctx.globalAlpha = 0.85;
    ctx.fillStyle = "#ffffff";
    ctx.beginPath();
    ctx.roundRect(labelX, labelY - labelHeight, textWidth + padding * 2, labelHeight, 4);
    ctx.fill();
    
    // Label Text
    ctx.globalAlpha = 1.0;
    ctx.fillStyle = obj.color || "#1f2937";
    ctx.textAlign = "left";
    ctx.textBaseline = "middle";
    ctx.fillText(obj.label!, labelX + padding, labelY - labelHeight / 2);
    
    ctx.restore();
  };

  const getLabelBounds = (
    obj: CanvasObject,
    zoom: number,
    ctx: CanvasRenderingContext2D
  ) => {
    if (!obj.label) return null;
    const bounds = getRotatedBounding(obj);
    if (!bounds) return null;

    ctx.save();
    const fontSize = Math.max(12, 14 * zoom);
    ctx.font = `bold ${fontSize}px Inter, sans-serif`;
    const textWidth = ctx.measureText(obj.label).width;
    ctx.restore();

    const padding = 4;
    const labelHeight = (fontSize + padding) / zoom;
    const labelWidth = (textWidth + padding * 2) / zoom;
    const gap = 4 / zoom;

    return {
      x: bounds.x,
      y: bounds.y - gap - labelHeight,
      width: labelWidth,
      height: labelHeight,
    };
  };

  return {
    renderSection,
    getLabelBounds,
  };
}
