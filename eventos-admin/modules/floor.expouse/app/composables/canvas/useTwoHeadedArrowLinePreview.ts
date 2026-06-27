// useTwoHeadedArrowLinePreview.ts
import type { Point } from "@floorplan/types/canvas";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useTwoHeadedArrowLineSegmentMeasurement } from "@floorplan/composables/canvas/useTwoHeadedArrowLineSegmentMeasurement";
import { useTwoHeadedArrowLine } from "@floorplan/composables/canvas/useTwoHeadedArrowLine";

export function useTwoHeadedArrowLinePreview() {
  const store = useCanvasStore();
  const { renderTwoHeadedArrowLine } = useTwoHeadedArrowLine();
  const { renderTwoHeadedArrowLineSegmentMeasurement } =
    useTwoHeadedArrowLineSegmentMeasurement();

  const worldToScreen = (point: Point, zoom: number, offset: Point): Point => ({
    x: (point.x - offset.x) * zoom,
    y: (point.y - offset.y) * zoom,
  });

  const renderTwoHeadedArrowLinePreview = (
    ctx: CanvasRenderingContext2D,
    drawingState: any
  ) => {
    if (!ctx) return;

    // Use the correct twoHeadedArrowState instead of twoHeadedState
    if (
      store.currentTool === "two-headed-arrow" &&
      drawingState.value.twoHeadedArrowState?.isDrawing &&
      drawingState.value.twoHeadedArrowState?.points.length > 0 &&
      drawingState.value.twoHeadedArrowState?.currentPreviewPoint
    ) {
      const points = drawingState.value.twoHeadedArrowState.points;
      const previewPoint =
        drawingState.value.twoHeadedArrowState.currentPreviewPoint;

      // Render the preview line with arrowheads - ADD worldToScreen parameter
      renderTwoHeadedArrowLine(
        ctx,
        [points[0], previewPoint],
        store.zoom,
        store.offset,
        worldToScreen, // ADD THIS MISSING PARAMETER
        {
          color: store.currentColor,
          strokeWidth: 2,
        } as any
      );

      // Render the measurement in the middle of the line
      renderTwoHeadedArrowLineSegmentMeasurement(
        ctx,
        points[0],
        previewPoint,
        store.zoom,
        store.offset
      );
    }
  };

  return {
    renderTwoHeadedArrowLinePreview,
  };
}
