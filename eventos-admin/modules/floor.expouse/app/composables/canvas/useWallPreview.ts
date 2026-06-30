// useWallPreview.ts
import type { Point, CanvasObject } from "@floorplan/types/canvas";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useWallSegmentMeasurement } from "@floorplan/composables/canvas/useWallSegmentMeasurement";

export function useWallPreview() {
  const store = useCanvasStore();
  const { renderWallSegmentMeasurement } = useWallSegmentMeasurement();

  const worldToScreen = (point: Point, zoom: number, offset: Point): Point => ({
    x: (point.x - offset.x) * zoom,
    y: (point.y - offset.y) * zoom,
  });

  const renderWallPreview = (
    ctx: CanvasRenderingContext2D,
    drawingState: any
  ) => {
    if (!ctx) return;

    // Wall drawing preview with measurements
    if (
      store.currentTool === "wall" &&
      drawingState.value.wallState.isDrawing &&
      drawingState.value.wallState.points.length > 0 &&
      drawingState.value.wallState.currentPreviewPoint
    ) {
      const points = drawingState.value.wallState.points;
      const previewPoint = drawingState.value.wallState.currentPreviewPoint;

      // Render measurements for all completed segments
      for (let i = 0; i < points.length - 1; i++) {
        const startPoint = points[i];
        const endPoint = points[i + 1];
        renderWallSegmentMeasurement(
          ctx,
          startPoint,
          endPoint,
          store.zoom,
          store.offset
        );
      }

      // Render measurement for the current preview segment
      if (points.length >= 1) {
        const lastPoint = points[points.length - 1];
        renderWallSegmentMeasurement(
          ctx,
          lastPoint,
          previewPoint,
          store.zoom,
          store.offset
        );
      }

      // Draw the wall preview as an open shape (don't auto-close)
      const screenPoints = points.map((p) =>
        worldToScreen(p, store.zoom, store.offset)
      );
      const screenPreview = worldToScreen(
        previewPoint,
        store.zoom,
        store.offset
      );

      ctx.strokeStyle = store.currentColor;
      ctx.lineWidth = 4;
      ctx.lineJoin = "miter";
      ctx.lineCap = "butt";

      ctx.beginPath();
      if (screenPoints.length > 0) {
        ctx.moveTo(screenPoints[0].x, screenPoints[0].y);

        // Draw completed segments
        for (let i = 1; i < screenPoints.length; i++) {
          ctx.lineTo(screenPoints[i].x, screenPoints[i].y);
        }

        // Draw preview segment (don't close the shape)
        ctx.lineTo(screenPreview.x, screenPreview.y);
      }
      ctx.stroke();
      ctx.lineWidth = 4; // Reset for other drawings
    }
  };

  return {
    renderWallPreview,
  };
}
