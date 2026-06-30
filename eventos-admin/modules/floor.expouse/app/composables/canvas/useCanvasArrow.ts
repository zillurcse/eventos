// useCanvasArrow.ts - UPDATED
import type { Point, CanvasObject } from "@floorplan/types/canvas";

export function useCanvasArrow() {
  const renderArrow = (
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

    // Draw the main arrow line
    ctx.beginPath();
    ctx.moveTo(start.x, start.y);
    ctx.lineTo(end.x, end.y);
    ctx.stroke();

    // Draw arrow head
    const arrowSize = obj?.strokeWidth ? Math.max(10, obj.strokeWidth * 2) : 10; // Scale arrow head with stroke width
    const angle = Math.atan2(end.y - start.y, end.x - start.x);

    ctx.beginPath();
    ctx.moveTo(end.x, end.y);
    ctx.lineTo(
      end.x - arrowSize * Math.cos(angle - Math.PI / 6),
      end.y - arrowSize * Math.sin(angle - Math.PI / 6)
    );
    ctx.moveTo(end.x, end.y);
    ctx.lineTo(
      end.x - arrowSize * Math.cos(angle + Math.PI / 6),
      end.y - arrowSize * Math.sin(angle + Math.PI / 6)
    );
    ctx.stroke();

    // Apply fill to arrow head if specified
    if (obj?.fill && obj.fill !== "transparent") {
      // Create filled arrow head
      ctx.beginPath();
      ctx.moveTo(end.x, end.y);
      ctx.lineTo(
        end.x - arrowSize * Math.cos(angle - Math.PI / 6),
        end.y - arrowSize * Math.sin(angle - Math.PI / 6)
      );
      ctx.lineTo(
        end.x - arrowSize * Math.cos(angle + Math.PI / 6),
        end.y - arrowSize * Math.sin(angle + Math.PI / 6)
      );
      ctx.closePath();
      ctx.fill();
    }
  };

  return {
    renderArrow,
  };
}
