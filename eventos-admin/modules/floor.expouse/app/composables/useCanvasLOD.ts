// composables/useCanvasLOD.ts
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import type { CanvasObject, Point } from "@floorplan/types/canvas";

export function useCanvasLOD() {
  const store = useCanvasStore();

  const getLOD = (): "high" | "medium" | "low" => {
    const zoom = store.zoom * 100;
    if (zoom > 70) return "high";
    if (zoom > 30) return "medium";
    return "low";
  };

  const shouldSkip = {
    measurements: () => getLOD() === "low",
    text: () => getLOD() === "low",
    arrows: () => getLOD() !== "high",
    boothArrows: () => getLOD() !== "high", // ← ADDED
    details: () => getLOD() === "low",
  };

  const renderAsDot = (
    ctx: CanvasRenderingContext2D,
    obj: CanvasObject,
    zoom: number,
    offset: Point,
    worldToScreen: (p: Point) => Point
  ) => {
    const center =
      obj.points.length >= 2
        ? {
            x: (obj.points[0].x + obj.points[1].x) / 2,
            y: (obj.points[0].y + obj.points[1].y) / 2,
          }
        : obj.points[0];

    const screen = worldToScreen(center);
    const size = Math.max(
      3,
      Math.min(10, Math.abs(obj.points[1]?.x - obj.points[0]?.x || 20) * zoom)
    );

    ctx.fillStyle = obj.color || "#3b82f6";
    ctx.fillRect(screen.x - size / 2, screen.y - size / 2, size, size);
  };

  return { getLOD, shouldSkip, renderAsDot };
}
