// useCanvasCurveArrow.ts - UPDATED
import type { Point, CanvasObject } from "@floorplan/types/canvas";

export function useCanvasCurveArrow() {
  const renderCurveArrow = (
    ctx: CanvasRenderingContext2D,
    points: Point[],
    zoom: number,
    offset: Point,
    worldToScreen: (point: Point, zoom: number, offset: Point) => Point,
    obj?: CanvasObject // NEW: Accept object for properties
  ) => {
    if (points.length < 2) return;
    const screenPoints = points.map((p) => worldToScreen(p, zoom, offset));
    ctx.beginPath();
    ctx.moveTo(screenPoints[0].x, screenPoints[0].y);
    for (let i = 1; i < screenPoints.length - 1; i++) {
      const xc = (screenPoints[i].x + screenPoints[i + 1].x) / 2;
      const yc = (screenPoints[i].y + screenPoints[i + 1].y) / 2;
      ctx.quadraticCurveTo(screenPoints[i].x, screenPoints[i].y, xc, yc);
    }
    ctx.lineTo(
      screenPoints[screenPoints.length - 1].x,
      screenPoints[screenPoints.length - 1].y
    );
    ctx.stroke();

    // Draw arrow head
    const last = screenPoints[screenPoints.length - 1];
    const secondLast = screenPoints[screenPoints.length - 2];
    const arrowSize = 10;
    const angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
    ctx.beginPath();
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(
      last.x - arrowSize * Math.cos(angle - Math.PI / 6),
      last.y - arrowSize * Math.sin(angle - Math.PI / 6)
    );
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(
      last.x - arrowSize * Math.cos(angle + Math.PI / 6),
      last.y - arrowSize * Math.sin(angle + Math.PI / 6)
    );
    ctx.stroke();
  };

  return {
    renderCurveArrow,
  };
}
