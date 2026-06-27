// useCanvasLine.ts - UPDATED
import type { Point, CanvasObject } from "@floorplan/types/canvas";

export function useCanvasLine() {
  const renderLine = (
    ctx: CanvasRenderingContext2D,
    points: Point[],
    zoom: number,
    offset: Point,
    worldToScreen: (point: Point, zoom: number, offset: Point) => Point,
    obj?: CanvasObject // NEW: Accept object for properties
  ) => {
    if (points.length < 2) return;
    const start = worldToScreen(points[0], zoom, offset);
    const end = worldToScreen(points[1], zoom, offset);

    ctx.beginPath();
    ctx.moveTo(start.x, start.y);
    ctx.lineTo(end.x, end.y);
    ctx.stroke();
  };

  return {
    renderLine,
  };
}
