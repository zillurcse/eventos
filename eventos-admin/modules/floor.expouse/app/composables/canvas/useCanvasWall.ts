import type { Point } from "@floorplan/types/canvas";
import type { UseUiStore } from "@floorplan/stores/uiStore";

export function useCanvasWall(uiStore: UseUiStore) {
  const renderWall = (
    ctx: CanvasRenderingContext2D,
    points: Point[],
    zoom: number,
    offset: Point,
    worldToScreen: (point: Point, zoom: number, offset: Point) => Point,
    obj?: CanvasObject
  ) => {
    if (points.length < 2) return;

    const screenPoints = points.map((p) => worldToScreen(p, zoom, offset));

    // Ensure perfect corners by using line joins
    ctx.lineJoin = "miter";
    ctx.lineCap = "butt";

    ctx.beginPath();
    //ctx.lineWidth = 4; // Thicker walls for better visibility
    if (obj?.strokeWidth > 4) {
      ctx.lineWidth = obj?.strokeWidth || 4;
    } else {
      ctx.lineWidth = 4;
    }

    ctx.moveTo(screenPoints[0].x, screenPoints[0].y);
    for (let i = 1; i < screenPoints.length; i++) {
      ctx.lineTo(screenPoints[i].x, screenPoints[i].y);
    }

    ctx.stroke();
    ctx.lineWidth = 2; // Reset for other drawings

    if (uiStore.showDimensions) {
      for (let i = 0; i < screenPoints.length - 1; i++) {
        const start = screenPoints[i];
        const end = screenPoints[i + 1];
        const dx = end.x - start.x;
        const dy = end.y - start.y;
        const length = Math.hypot(dx, dy).toFixed(0);

        const isHorizontal = Math.abs(dx) > Math.abs(dy);
        const isVertical = Math.abs(dy) > Math.abs(dx);
        let offset = 10;
        let indicatorStart, indicatorEnd, labelX, labelY;

        const midX = (start.x + end.x) / 2;
        const midY = (start.y + end.y) / 2;

        if (isHorizontal) {
          indicatorStart = { x: start.x, y: start.y - offset };
          indicatorEnd = { x: end.x, y: end.y - offset };
          labelX = midX;
          labelY = midY - offset - 5;
        } else if (isVertical) {
          indicatorStart = { x: start.x + offset, y: start.y };
          indicatorEnd = { x: end.x + offset, y: end.y };
          labelX = midX + offset + 5;
          labelY = midY;
        } else {
          const perpDx = -dy;
          const perpDy = dx;
          const perpLength = Math.hypot(perpDx, perpDy);
          const normPerpDx = (perpDx / perpLength) * offset;
          const normPerpDy = (perpDy / perpLength) * offset;
          indicatorStart = {
            x: start.x + normPerpDx,
            y: start.y + normPerpDy,
          };
          indicatorEnd = { x: end.x + normPerpDx, y: end.y + normPerpDy };
          labelX = (indicatorStart.x + indicatorEnd.x) / 2;
          labelY = (indicatorStart.y + indicatorEnd.y) / 2 - 5;
        }

        ctx.setLineDash([2, 2]);
        ctx.beginPath();
        ctx.moveTo(indicatorStart.x, indicatorStart.y);
        ctx.lineTo(indicatorEnd.x, indicatorEnd.y);
        ctx.stroke();
        ctx.setLineDash([]);

        const arrowLength = 8;
        const arrowAngle = Math.PI / 6;
        const angleRad = Math.atan2(
          indicatorEnd.y - indicatorStart.y,
          indicatorEnd.x - indicatorStart.x
        );

        ctx.beginPath();
        ctx.moveTo(indicatorStart.x, indicatorStart.y);
        ctx.lineTo(
          indicatorStart.x +
            arrowLength * Math.cos(angleRad + Math.PI - arrowAngle),
          indicatorStart.y +
            arrowLength * Math.sin(angleRad + Math.PI - arrowAngle)
        );
        ctx.moveTo(indicatorStart.x, indicatorStart.y);
        ctx.lineTo(
          indicatorStart.x +
            arrowLength * Math.cos(angleRad + Math.PI + arrowAngle),
          indicatorStart.y +
            arrowLength * Math.sin(angleRad + Math.PI + arrowAngle)
        );
        ctx.stroke();

        ctx.beginPath();
        ctx.moveTo(indicatorEnd.x, indicatorEnd.y);
        ctx.lineTo(
          indicatorEnd.x + arrowLength * Math.cos(angleRad - arrowAngle),
          indicatorEnd.y + arrowLength * Math.sin(angleRad - arrowAngle)
        );
        ctx.moveTo(indicatorEnd.x, indicatorEnd.y);
        ctx.lineTo(
          indicatorEnd.x + arrowLength * Math.cos(angleRad + arrowAngle),
          indicatorEnd.y + arrowLength * Math.sin(angleRad + arrowAngle)
        );
        ctx.stroke();

        ctx.fillStyle = "rgba(255, 255, 255, 0.8)";
        ctx.font = "12px Arial";
        const text = length;
        const textWidth = ctx.measureText(text).width;
        const padding = 5;
        ctx.fillRect(
          labelX - textWidth / 2 - padding,
          labelY - 6 - padding,
          textWidth + 2 * padding,
          12 + 2 * padding
        );
        ctx.fillStyle = "#000";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";

        ctx.fillText(text, labelX, labelY);
      }
    }
  };

  return {
    renderWall,
  };
}
