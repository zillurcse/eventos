// useCanvasPencil.ts - MAXIMUM SMOOTHNESS with optimized Catmull-Rom
import type { Point, CanvasObject } from "@floorplan/types/canvas";

export function useCanvasPencil() {
  const renderPencil = (
    ctx: CanvasRenderingContext2D,
    points: Point[],
    zoom: number,
    offset: Point,
    worldToScreen: (point: Point, zoom: number, offset: Point) => Point,
    obj?: CanvasObject
  ) => {
    if (points.length < 2) return;

    ctx.save();

    // Apply stroke properties with maximum smoothing
    // ctx.strokeStyle = obj?.stroke || obj?.color || "#000000";
    ctx.lineWidth = (obj?.strokeWidth || 2) * zoom;
    ctx.lineCap = "round";
    ctx.lineJoin = "round";
    ctx.globalAlpha = obj?.opacity !== undefined ? obj.opacity : 1;

    // ✅ CRITICAL: Enable maximum quality anti-aliasing
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = "high";

    // ✅ PERFORMANCE: Convert points to screen space once
    const screenPoints = points.map((p) => worldToScreen(p, zoom, offset));

    // ✅ SMOOTHNESS: Use different algorithms based on point count
    ctx.beginPath();
    ctx.moveTo(screenPoints[0].x, screenPoints[0].y);

    if (screenPoints.length === 2) {
      // Just 2 points: straight line
      ctx.lineTo(screenPoints[1].x, screenPoints[1].y);
    } else if (screenPoints.length === 3) {
      // 3 points: simple quadratic curve
      ctx.quadraticCurveTo(
        screenPoints[1].x,
        screenPoints[1].y,
        screenPoints[2].x,
        screenPoints[2].y
      );
    } else {
      // ✅ 4+ points: MAXIMUM SMOOTHNESS with Catmull-Rom spline
      // This creates ultra-smooth curves that pass through all points
      const tension = 0.5; // Controls curve tightness (0.5 = balanced)

      for (let i = 0; i < screenPoints.length - 1; i++) {
        // Get surrounding points for curve calculation
        const p0 = i > 0 ? screenPoints[i - 1] : screenPoints[i];
        const p1 = screenPoints[i];
        const p2 = screenPoints[i + 1];
        const p3 =
          i < screenPoints.length - 2
            ? screenPoints[i + 2]
            : screenPoints[i + 1];

        // Calculate control points using Catmull-Rom algorithm
        const cp1x = p1.x + ((p2.x - p0.x) / 6) * tension;
        const cp1y = p1.y + ((p2.y - p0.y) / 6) * tension;
        const cp2x = p2.x - ((p3.x - p1.x) / 6) * tension;
        const cp2y = p2.y - ((p3.y - p1.y) / 6) * tension;

        // Draw smooth bezier curve
        ctx.bezierCurveTo(cp1x, cp1y, cp2x, cp2y, p2.x, p2.y);
      }
    }

    // ✅ PERFORMANCE: Single stroke call
    ctx.stroke();

    // Apply fill if specified
    if (obj?.fill && obj.fill !== "transparent") {
      ctx.fillStyle = obj.fill;
      ctx.fill();
    }

    ctx.restore();
  };

  return {
    renderPencil,
  };
}
