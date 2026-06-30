// useCanvasEllipse.ts - UPDATED
import type { CanvasObject, Point } from "@floorplan/types/canvas";

export function useCanvasEllipse() {
  const renderEllipse = (
    ctx: CanvasRenderingContext2D,
    obj: CanvasObject,
    zoom: number,
    offset: Point,
    worldToScreen: (point: Point, zoom: number, offset: Point) => Point,
    isHovered: boolean = false
  ) => {
    if (obj.points.length < 2) return;

    const p1 = obj.points[0];
    const p2 = obj.points[1];
    const centerX = (p1.x + p2.x) / 2;
    const centerY = (p1.y + p2.y) / 2;
    const radiusX = Math.abs(p2.x - p1.x) / 2;
    const radiusY = Math.abs(p2.y - p1.y) / 2;

    const screenCenter = worldToScreen(
      { x: centerX, y: centerY },
      zoom,
      offset
    );
    const screenRadiusX = radiusX * zoom;
    const screenRadiusY = radiusY * zoom;
    const rotationRad = (obj.rotation || 0) * (Math.PI / 180);

    ctx.save();
    ctx.translate(screenCenter.x, screenCenter.y);
    ctx.rotate(rotationRad);
    ctx.beginPath();
    ctx.ellipse(0, 0, screenRadiusX, screenRadiusY, 0, 0, 2 * Math.PI);

    // Apply fill and stroke
    if (obj.fill && obj.fill !== "transparent") {
      ctx.fill();
    }
    ctx.stroke();

    // DRAW CORNER HANDLES IF HOVERED
    if (isHovered && !obj.isLocked) {
      const handleDistance = 15; // px from corner
      const handleRadius = 4;
      
      const sw = screenRadiusX * 2;
      const sh = screenRadiusY * 2;
      
      // Ensure handles don't overlap if ellipse is too small
      const safeDist = Math.min(handleDistance, sw/3, sh/3);
      
      const localHandles = [
        { x: -screenRadiusX + safeDist, y: -screenRadiusY + safeDist },
        { x:  screenRadiusX - safeDist, y: -screenRadiusY + safeDist },
        { x:  screenRadiusX - safeDist, y:  screenRadiusY - safeDist },
        { x: -screenRadiusX + safeDist, y:  screenRadiusY - safeDist }
      ];

      ctx.fillStyle = "white";
      ctx.strokeStyle = "#4F46E5"; // Indigo-600
      ctx.lineWidth = 1;

      localHandles.forEach(h => {
        // Outer circle
        ctx.beginPath();
        ctx.arc(h.x, h.y, handleRadius, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
        
        // Inner dot
        ctx.beginPath();
        ctx.arc(h.x, h.y, 1, 0, Math.PI * 2);
        ctx.fillStyle = "#4F46E5";
        ctx.fill();
        ctx.fillStyle = "white"; // Reset for next handle
      });
      
      // Center dot
      ctx.beginPath();
      ctx.arc(0, 0, handleRadius/2, 0, Math.PI * 2);
      ctx.fillStyle = "#4F46E5";
      ctx.fill();
    }

    ctx.restore();
  };

  return {
    renderEllipse,
  };
}
