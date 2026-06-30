// src/composables/canvas/useTwoHeadedArrowLine.ts
import type { Point, CanvasObject } from "@floorplan/types/canvas";
import { useTwoHeadedArrowLineSegmentMeasurement } from "@floorplan/composables/canvas/useTwoHeadedArrowLineSegmentMeasurement";

export function useTwoHeadedArrowLine() {
  const { renderTwoHeadedArrowLineSegmentMeasurement } =
    useTwoHeadedArrowLineSegmentMeasurement();

  const worldToScreen = (point: Point, zoom: number, offset: Point): Point => ({
    x: (point.x - offset.x) * zoom,
    y: (point.y - offset.y) * zoom,
  });

  const renderTwoHeadedArrowLine = (
    ctx: CanvasRenderingContext2D,
    points: Point[],
    zoom: number,
    offset: Point,
    worldToScreenFn: (point: Point, zoom: number, offset: Point) => Point,
    obj?: CanvasObject
  ) => {
    if (points.length < 2) return;

    const screenPoints = points.map((p) => worldToScreenFn(p, zoom, offset));
    const start = screenPoints[0];
    const end = screenPoints[screenPoints.length - 1];

    const dx = end.x - start.x;
    const dy = end.y - start.y;
    const angle = Math.atan2(dy, dx);

    // Apply styles
    ctx.strokeStyle = obj?.color || "#000000";
    ctx.lineWidth = obj?.strokeWidth || 2;
    ctx.lineCap = "round";
    ctx.lineJoin = "round";

    // Main line
    ctx.beginPath();
    ctx.moveTo(start.x, start.y);
    ctx.lineTo(end.x, end.y);
    ctx.stroke();

    // Arrowheads (both ends)
    const arrowLen = 6;
    const arrowAngle = Math.PI / 6;

    const drawArrowHead = (
      x: number,
      y: number,
      a: number,
      flip: boolean = false
    ) => {
      const direction = flip ? -1 : 1;

      ctx.beginPath();
      ctx.moveTo(x, y);
      ctx.lineTo(
        x + direction * arrowLen * Math.cos(a - arrowAngle),
        y + direction * arrowLen * Math.sin(a - arrowAngle)
      );
      ctx.moveTo(x, y);
      ctx.lineTo(
        x + direction * arrowLen * Math.cos(a + arrowAngle),
        y + direction * arrowLen * Math.sin(a + arrowAngle)
      );
      ctx.stroke();
    };

    // Start arrowhead (pointing toward end)
    drawArrowHead(start.x, start.y, angle, false);
    // End arrowhead (pointing toward start)
    drawArrowHead(end.x, end.y, angle + Math.PI, false);

    if (obj && points.length >= 2) {
      // Convert screen points back to world points for measurement
      const worldStart = {
        x: points[0].x,
        y: points[0].y,
      };
      const worldEnd = {
        x: points[1].x,
        y: points[1].y,
      };

      renderTwoHeadedArrowLineSegmentMeasurement(
        ctx,
        worldStart,
        worldEnd,
        zoom,
        offset
      );
    }
  };

  return { renderTwoHeadedArrowLine };
}
