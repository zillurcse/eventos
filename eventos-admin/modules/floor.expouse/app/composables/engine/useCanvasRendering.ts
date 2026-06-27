// useCanvasRendering.ts
import { ref } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useUiStore } from "@floorplan/stores/uiStore";
import type { Point, CanvasObject } from "@floorplan/types/canvas";
import { useCanvasSetup } from "@floorplan/composables/useCanvasSetup";
import { useObjectManipulation } from "@floorplan/composables/useObjectManipulation";
import { useCanvasObjects } from "@floorplan/composables/useCanvasObjects";

export function useCanvasRendering() {
  const store = useCanvasStore();
  const uiStore = useUiStore();
  const canvasSetup = useCanvasSetup();
  const objectManipulation = useObjectManipulation();
  const canvasObjects = useCanvasObjects();

  let renderScheduled = false;

  const worldToScreen = (point: Point, zoom: number, offset: Point): Point => ({
    x: (point.x - offset.x) * zoom,
    y: (point.y - offset.y) * zoom,
  });

  const screenToWorld = (point: Point, zoom: number, offset: Point): Point => ({
    x: point.x / zoom + offset.x,
    y: point.y / zoom + offset.y,
  });

  const drawGrid = (zoom: number, offset: Point) => {
    if (!canvasSetup.ctx.value || !canvasSetup.canvasRef.value) return;
    const ctx = canvasSetup.ctx.value;
    const canvas = canvasSetup.canvasRef.value;
    const dpr = window.devicePixelRatio || 1;
    const width = canvas.width / dpr;
    const height = canvas.height / dpr;
    const baseGridSize = 20;
    const baseHighlightSpacing = 100;
    const effectiveGridSize = baseGridSize * zoom;
    const effectiveHighlightSpacing = baseHighlightSpacing * zoom;
    const startX =
      Math.floor(offset.x / effectiveGridSize) * effectiveGridSize - offset.x;
    const startY =
      Math.floor(offset.y / effectiveGridSize) * effectiveGridSize - offset.y;
    const highlightStartX =
      Math.floor(offset.x / effectiveHighlightSpacing) *
        effectiveHighlightSpacing -
      offset.x;
    const highlightStartY =
      Math.floor(offset.y / effectiveHighlightSpacing) *
        effectiveHighlightSpacing -
      offset.y;

    ctx.strokeStyle = "#e5e7eb";
    ctx.lineWidth = 1;
    for (let x = startX; x < width; x += effectiveGridSize) {
      ctx.beginPath();
      ctx.moveTo(x, 0);
      ctx.lineTo(x, height);
      ctx.stroke();
    }
    for (let y = startY; y < height; y += effectiveGridSize) {
      ctx.beginPath();
      ctx.moveTo(0, y);
      ctx.lineTo(width, y);
      ctx.stroke();
    }

    ctx.strokeStyle = "#d1d5db";
    ctx.lineWidth = 1;
    for (let x = highlightStartX; x < width; x += effectiveHighlightSpacing) {
      ctx.beginPath();
      ctx.moveTo(x, 0);
      ctx.lineTo(x, height);
      ctx.stroke();
    }
    for (let y = highlightStartY; y < height; y += effectiveHighlightSpacing) {
      ctx.beginPath();
      ctx.moveTo(0, y);
      ctx.lineTo(width, y);
      ctx.stroke();
    }
  };

  const renderWallPreview = (drawingState: any) => {
    if (!canvasSetup.ctx.value) return;

    // Wall drawing preview with measurements
    if (
      store.currentTool === "wall" &&
      drawingState.wallState.isDrawing &&
      drawingState.wallState.points.length > 0 &&
      drawingState.wallState.currentPreviewPoint
    ) {
      const points = drawingState.wallState.points;
      const previewPoint = drawingState.wallState.currentPreviewPoint;

      // Render measurements for all completed segments
      for (let i = 0; i < points.length - 1; i++) {
        const startPoint = points[i];
        const endPoint = points[i + 1];
        renderWallSegmentMeasurement(
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
          lastPoint,
          previewPoint,
          store.zoom,
          store.offset
        );
      }

      // Draw the wall preview as an open shape (don't auto-close)
      const screenPoints = points.map((p: Point) =>
        worldToScreen(p, store.zoom, store.offset)
      );
      const screenPreview = worldToScreen(
        previewPoint,
        store.zoom,
        store.offset
      );

      canvasSetup.ctx.value.strokeStyle = store.currentColor;
      canvasSetup.ctx.value.lineWidth = 4;
      canvasSetup.ctx.value.lineJoin = "miter";
      canvasSetup.ctx.value.lineCap = "butt";

      canvasSetup.ctx.value.beginPath();
      if (screenPoints.length > 0) {
        canvasSetup.ctx.value.moveTo(screenPoints[0].x, screenPoints[0].y);

        // Draw completed segments
        for (let i = 1; i < screenPoints.length; i++) {
          canvasSetup.ctx.value.lineTo(screenPoints[i].x, screenPoints[i].y);
        }

        // Draw preview segment (don't close the shape)
        canvasSetup.ctx.value.lineTo(screenPreview.x, screenPreview.y);
      }
      canvasSetup.ctx.value.stroke();
      canvasSetup.ctx.value.lineWidth = 2; // Reset for other drawings
    }
  };

  const renderWallSegmentMeasurement = (
    startPoint: Point,
    endPoint: Point,
    zoom: number,
    offset: Point
  ) => {
    if (!canvasSetup.ctx.value) return;
    const ctx = canvasSetup.ctx.value;

    const dist = Math.hypot(
      endPoint.x - startPoint.x,
      endPoint.y - startPoint.y
    );

    if (dist > 0) {
      const screenStart = worldToScreen(startPoint, zoom, offset);
      const screenEnd = worldToScreen(endPoint, zoom, offset);

      const dx = screenEnd.x - screenStart.x;
      const dy = screenEnd.y - screenStart.y;
      const length_px = Math.hypot(dx, dy);

      if (length_px >= 5) {
        // Reduced threshold for better responsiveness
        const angle = Math.atan2(dy, dx) * (180 / Math.PI);

        // Calculate perpendicular offset for measurement line
        let perp_x = -dy;
        let perp_y = dx;
        let norm = Math.hypot(perp_x, perp_y);

        if (norm > 0) {
          perp_x /= norm;
          perp_y /= norm;

          // Determine offset direction based on angle
          let sign = 1;
          if (angle > -45 && angle <= 45) {
            sign = -1; // Right side for horizontal lines
          } else if (angle > 45 && angle <= 135) {
            sign = -1; // Bottom side for vertical lines
          } else if (angle > 135 || angle <= -135) {
            sign = -1; // Left side for horizontal lines
          } else {
            sign = 1; // Top side for vertical lines
          }

          const offset_amount = 30; // Increased offset for better visibility
          const offset_x = perp_x * sign * offset_amount;
          const offset_y = perp_y * sign * offset_amount;

          const m_start = {
            x: screenStart.x + offset_x,
            y: screenStart.y + offset_y,
          };
          const m_end = {
            x: screenEnd.x + offset_x,
            y: screenEnd.y + offset_y,
          };

          // Draw dashed measurement line (PRESERVING EXISTING STYLE)
          ctx.strokeStyle = "#000000";
          ctx.lineWidth = 2;
          ctx.setLineDash([5, 5]);
          ctx.beginPath();
          ctx.moveTo(m_start.x, m_start.y);
          ctx.lineTo(m_end.x, m_end.y);
          ctx.stroke();
          ctx.setLineDash([]);

          // Draw arrowheads
          const arrow_size = 7;
          const norm_dx = dx / length_px;
          const norm_dy = dy / length_px;

          // Arrow at start
          const startArrowAngle1 = Math.atan2(-norm_dy, -norm_dx) - 360 / 9;
          const startArrowAngle2 = Math.atan2(-norm_dy, -norm_dx) + 360 / 9;

          ctx.beginPath();
          ctx.moveTo(m_start.x, m_start.y);
          ctx.lineTo(
            m_start.x + arrow_size * Math.cos(startArrowAngle1),
            m_start.y + arrow_size * Math.sin(startArrowAngle1)
          );
          ctx.moveTo(m_start.x, m_start.y);
          ctx.lineTo(
            m_start.x + arrow_size * Math.cos(startArrowAngle2),
            m_start.y + arrow_size * Math.sin(startArrowAngle2)
          );
          ctx.stroke();

          // Arrow at end
          const endArrowAngle1 = Math.atan2(norm_dy, norm_dx) - 360 / 9;
          const endArrowAngle2 = Math.atan2(norm_dy, norm_dx) + 360 / 9;

          ctx.beginPath();
          ctx.moveTo(m_end.x, m_end.y);
          ctx.lineTo(
            m_end.x + arrow_size * Math.cos(endArrowAngle1),
            m_end.y + arrow_size * Math.sin(endArrowAngle1)
          );
          ctx.moveTo(m_end.x, m_end.y);
          ctx.lineTo(
            m_end.x + arrow_size * Math.cos(endArrowAngle2),
            m_end.y + arrow_size * Math.sin(endArrowAngle2)
          );
          ctx.stroke();

          // Draw measurement label
          const text = dist.toFixed(1) + " cm";
          ctx.font = "12px Arial";
          const textMetrics = ctx.measureText(text);
          const textWidth = textMetrics.width;
          const textHeight = 14;
          const labelX = (m_start.x + m_end.x) / 2;
          const labelY = (m_start.y + m_end.y) / 2;
          const padding = 4;

          // Label background
          ctx.fillStyle = "rgba(255, 255, 255, 0.9)";
          ctx.fillRect(
            labelX - textWidth / 2 - padding,
            labelY - textHeight / 2 - padding,
            textWidth + 2 * padding,
            textHeight + 2 * padding
          );

          // Label text
          ctx.fillStyle = "#000000";
          ctx.textAlign = "center";
          ctx.textBaseline = "middle";
          ctx.fillText(text, labelX, labelY);
        }
      }
    }
  };

  const renderSelection = (obj: CanvasObject) => {
    if (!canvasSetup.ctx.value) return;

    // Don't render selection handles for locked objects
    if (obj.isLocked) {
      return;
    }

    // Don't render selection for invisible objects
    if (obj.isVisible === false) {
      return;
    }

    const bound = objectManipulation.getObjectBounding(obj);
    if (!bound) return;

    const rotation = obj.rotation || 0;

    // Convert bounds corners to screen coordinates
    const corners = [
      { x: bound.x, y: bound.y }, // top-left
      { x: bound.x + bound.width, y: bound.y }, // top-right
      { x: bound.x + bound.width, y: bound.y + bound.height }, // bottom-right
      { x: bound.x, y: bound.y + bound.height }, // bottom-left
    ];

    const screenCorners = corners.map((corner) =>
      worldToScreen(corner, store.zoom, store.offset)
    );

    // Calculate rotated bounds in screen coordinates
    const centerX =
      (screenCorners[0].x +
        screenCorners[1].x +
        screenCorners[2].x +
        screenCorners[3].x) /
      4;
    const centerY =
      (screenCorners[0].y +
        screenCorners[1].y +
        screenCorners[2].y +
        screenCorners[3].y) /
      4;

    // Save context state
    canvasSetup.ctx.value.save();

    // Apply rotation transformation
    canvasSetup.ctx.value.translate(centerX, centerY);
    canvasSetup.ctx.value.rotate((rotation * Math.PI) / 180);
    canvasSetup.ctx.value.translate(-centerX, -centerY);

    // Calculate rotated bounding box dimensions
    const minX = Math.min(...screenCorners.map((p) => p.x));
    const maxX = Math.max(...screenCorners.map((p) => p.x));
    const minY = Math.min(...screenCorners.map((p) => p.y));
    const maxY = Math.max(...screenCorners.map((p) => p.y));

    const width = maxX - minX;
    const height = maxY - minY;
    const rotatedCenterX = minX + width / 2;
    const rotatedCenterY = minY + height / 2;

    // Draw dashed border around the rotated bounds
    canvasSetup.ctx.value.strokeStyle = "#3b82f6";
    canvasSetup.ctx.value.lineWidth = 2;
    canvasSetup.ctx.value.setLineDash([5, 5]);
    canvasSetup.ctx.value.strokeRect(minX, minY, width, height);
    canvasSetup.ctx.value.setLineDash([]);

    // Resize handles (only show for single selection)
    if (store.selectedObjects.length === 1) {
      const handleRadius = 5;
      const handlePositions = [
        { x: minX, y: minY }, // top-left
        { x: rotatedCenterX, y: minY }, // top
        { x: maxX, y: minY }, // top-right
        { x: maxX, y: rotatedCenterY }, // right
        { x: maxX, y: maxY }, // bottom-right
        { x: rotatedCenterX, y: maxY }, // bottom
        { x: minX, y: maxY }, // bottom-left
        { x: minX, y: rotatedCenterY }, // left
      ];

      handlePositions.forEach((pos) => {
        canvasSetup.ctx.value.beginPath();
        canvasSetup.ctx.value.arc(pos.x, pos.y, handleRadius, 0, 2 * Math.PI);
        canvasSetup.ctx.value.fillStyle = "white";
        canvasSetup.ctx.value.fill();
        canvasSetup.ctx.value.strokeStyle = "#3b82f6";
        canvasSetup.ctx.value.lineWidth = 2;
        canvasSetup.ctx.value.stroke();
      });

      // Rotation handle line
      const rotationHandleDistance = 30;
      const rotationHandleY = minY - rotationHandleDistance;

      canvasSetup.ctx.value.strokeStyle = "#3b82f6";
      canvasSetup.ctx.value.lineWidth = 2;
      canvasSetup.ctx.value.setLineDash([4, 4]);
      canvasSetup.ctx.value.beginPath();
      canvasSetup.ctx.value.moveTo(rotatedCenterX, minY - 3);
      canvasSetup.ctx.value.lineTo(rotatedCenterX, rotationHandleY + 5);
      canvasSetup.ctx.value.stroke();
      canvasSetup.ctx.value.setLineDash([]);
    }

    // Restore context state
    canvasSetup.ctx.value.restore();
  };

  const updateHoverState = (
    hoveredObject: CanvasObject | null,
    point: Point
  ) => {
    // Implementation for hover state updates
  };

  const renderHoverMeasurements = (
    ctx: CanvasRenderingContext2D,
    zoom: number,
    offset: Point
  ) => {
    // Implementation for hover measurements
  };

  const renderObject = (
    ctx: CanvasRenderingContext2D,
    obj: CanvasObject,
    zoom: number,
    offset: Point
  ) => {
    // Implementation for rendering individual objects
  };

  const render = () => {
    if (!canvasSetup.ctx.value || !canvasSetup.canvasRef.value) return;

    canvasSetup.clearCanvas();

    if (uiStore.showGuides) {
      drawGrid(store.zoom, store.offset);
    }

    canvasSetup.ctx.value.strokeStyle = store.currentColor;
    canvasSetup.ctx.value.lineWidth = 2;

    // Note: The actual rendering of objects would be handled here
    // This is a simplified version - the full implementation would
    // include rendering all objects, temporary objects, etc.

    // Use optimized rendering
    if (renderScheduled) return;

    renderScheduled = true;
    requestAnimationFrame(() => {
      // Actual rendering logic would go here
      renderScheduled = false;
    });
  };

  const resizeCanvas = () => {
    canvasSetup.resizeCanvas();
  };

  return {
    worldToScreen,
    screenToWorld,
    drawGrid,
    renderWallPreview,
    renderWallSegmentMeasurement,
    renderSelection,
    updateHoverState,
    renderHoverMeasurements,
    renderObject,
    render,
    resizeCanvas,
  };
}
