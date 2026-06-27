// useCanvasDoorArc.ts - New composable for rendering door arcs
import type { Point, CanvasObject } from "@floorplan/types/canvas";

export function useCanvasDoorArc() {
  const renderDoorArc = (
    ctx: CanvasRenderingContext2D,
    points: Point[],
    zoom: number,
    offset: Point,
    worldToScreen: (point: Point, zoom: number, offset: Point) => Point,
    obj?: CanvasObject
  ) => {
    if (points.length < 3) return;

    const [start, end, control] = points.map((p) =>
      worldToScreen(p, zoom, offset)
    );

    ctx.save();

    // Draw the arc using quadratic curve
    ctx.beginPath();
    ctx.moveTo(start.x, start.y);

    // Calculate the arc using quadratic bezier curve
    ctx.quadraticCurveTo(control.x, control.y, end.x, end.y);

    // Apply stroke
    ctx.strokeStyle = obj?.color || "#000000";
    ctx.lineWidth = (obj?.strokeWidth || 2) * zoom;
    ctx.stroke();

    // Optional: Draw straight lines from start/end to control point (door swing visualization)
    ctx.beginPath();
    ctx.setLineDash([3, 3]);
    ctx.moveTo(start.x, start.y);
    ctx.lineTo(control.x, control.y);
    ctx.lineTo(end.x, end.y);
    ctx.strokeStyle = obj?.color || "#000000";
    ctx.lineWidth = 1;
    ctx.stroke();
    ctx.setLineDash([]);

    ctx.restore();
  };

  return {
    renderDoorArc,
  };
}
