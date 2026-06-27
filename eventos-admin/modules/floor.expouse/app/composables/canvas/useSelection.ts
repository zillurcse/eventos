// 🔧 FIX for useSelection.ts - Rotation Handle Visual Rendering
// This ensures the visual position matches the click detection

import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useCanvasObjects } from "@floorplan/composables/useCanvasObjects";
import type { CanvasObject, Point } from "@floorplan/types/canvas";

export function useSelection() {
  const store = useCanvasStore();
  const canvasObjects = useCanvasObjects();

  const renderSelection = (
    ctx: CanvasRenderingContext2D | null,
    obj: CanvasObject
  ) => {
    if (!ctx || !obj.isSelected) return;

    const zoom = store.zoom;
    const offset = store.offset;

    // Get bounds and center
    const bounds = canvasObjects.getObjectBounding(obj);
    if (!bounds) return;

    const center = canvasObjects.getCenter(obj);
    const rotation = obj.rotation || 0;

    // World to screen conversion
    const worldToScreen = (point: Point): Point => ({
      x: (point.x - offset.x) * zoom,
      y: (point.y - offset.y) * zoom,
    });

    ctx.save();

    // ✅ RESIZE HANDLES
    const handleSize = 8;
    const unrotatedHandles = [
      { x: bounds.x, y: bounds.y }, // top-left
      { x: bounds.x + bounds.width / 2, y: bounds.y }, // top-center
      { x: bounds.x + bounds.width, y: bounds.y }, // top-right
      { x: bounds.x + bounds.width, y: bounds.y + bounds.height / 2 }, // right
      { x: bounds.x + bounds.width, y: bounds.y + bounds.height }, // bottom-right
      { x: bounds.x + bounds.width / 2, y: bounds.y + bounds.height }, // bottom
      { x: bounds.x, y: bounds.y + bounds.height }, // bottom-left
      { x: bounds.x, y: bounds.y + bounds.height / 2 }, // left
    ];

    // Draw resize handles (rotated) - SQUARE BOXES
    unrotatedHandles.forEach((handle) => {
      const rotatedHandle = canvasObjects.rotatePoint(handle, center, rotation);
      const screenHandle = worldToScreen(rotatedHandle);

      const squareSize = handleSize; // Make square slightly larger (16px)

      // Draw white square outline (unfilled)
      ctx.strokeStyle = "#6366f1";
      ctx.lineWidth = 1.5;
      ctx.fillStyle = "transparent"; // No fill

      ctx.beginPath();
      ctx.rect(
        screenHandle.x - squareSize / 2,
        screenHandle.y - squareSize / 2,
        squareSize,
        squareSize
      );
      ctx.stroke(); // Only stroke, no fill
    });

    // 🎯 FIXED ROTATION HANDLE
    const rotationHandleDistance = 40 / zoom; // Must match getHandleAtPoint distance

    // Calculate unrotated position
    const unrotatedRotationHandle = {
      x: bounds.x + bounds.width / 2,
      y: bounds.y - rotationHandleDistance,
    };

    // ✅ CRITICAL FIX: Apply the same rotation as in click detection
    const rotatedRotationHandle = canvasObjects.rotatePoint(
      unrotatedRotationHandle,
      center,
      rotation // Apply object's rotation
    );

    const screenRotationHandle = worldToScreen(rotatedRotationHandle);

    // Draw rotation handle icon
    ctx.fillStyle = "#6366f1";
    ctx.strokeStyle = "#ffffff";
    ctx.lineWidth = 2;

    // Draw circle
    ctx.beginPath();
    ctx.arc(screenRotationHandle.x, screenRotationHandle.y, 10, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();

    // Draw rotation icon (curved arrow)
    ctx.strokeStyle = "#ffffff";
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.arc(
      screenRotationHandle.x,
      screenRotationHandle.y,
      5,
      -Math.PI / 4,
      Math.PI
    );
    ctx.stroke();

    // Arrow tip
    ctx.beginPath();
    ctx.moveTo(screenRotationHandle.x - 4, screenRotationHandle.y + 3);
    ctx.lineTo(screenRotationHandle.x - 2, screenRotationHandle.y + 5);
    ctx.lineTo(screenRotationHandle.x - 4, screenRotationHandle.y + 5);
    ctx.closePath();
    ctx.fill();

    // ✅ Draw connecting line from top-center handle to rotation handle
    const topCenterHandle = canvasObjects.rotatePoint(
      { x: bounds.x + bounds.width / 2, y: bounds.y },
      center,
      rotation
    );
    const screenTopCenter = worldToScreen(topCenterHandle);

    ctx.strokeStyle = "#6366f1";
    ctx.lineWidth = 3;
    ctx.setLineDash([4, 4]);
    ctx.beginPath();
    ctx.moveTo(screenTopCenter.x, screenTopCenter.y);
    ctx.lineTo(screenRotationHandle.x, screenRotationHandle.y);
    ctx.stroke();
    ctx.setLineDash([]);

    ctx.restore();
  };

  return {
    renderSelection,
  };
}
