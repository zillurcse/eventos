// useCanvasRectangle.ts - UPDATED
import type { CanvasObject, Point } from "@floorplan/types/canvas";
import { useCanvasProperties } from "@floorplan/composables/useCanvasProperties"; // NEW

export function useCanvasRectangle(
  getCenter: (obj: CanvasObject) => Point,
  rotatePoint: (point: Point, center: Point, rotation: number) => Point
) {
  const { drawRoundedRect } = useCanvasProperties(); // NEW

  const renderRectangle = (
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
    const minX = Math.min(p1.x, p2.x);
    const minY = Math.min(p1.y, p2.y);
    const maxX = Math.max(p1.x, p2.x);
    const maxY = Math.max(p1.y, p2.y);

    const corners: Point[] = [
      { x: minX, y: minY },
      { x: maxX, y: minY },
      { x: maxX, y: maxY },
      { x: minX, y: maxY },
    ];

    const center = getCenter(obj);
    const rotation = obj.rotation || 0;
    const rotatedCorners = corners.map((p) => rotatePoint(p, center, rotation));
    const screenCorners = rotatedCorners.map((p) =>
      worldToScreen(p, zoom, offset)
    );

    // Use corner radius if specified
    const cornerRadius = obj.cornerRadius || 0;

    ctx.save();
    if (cornerRadius > 0) {
      // For rounded rects, we need to handle rotation properly in drawRoundedRect or use a path
      // Currently drawRoundedRect might not support rotation directly if it uses ctx.rect
      // But we have screenCorners which are rotated...
      // Let's use a path-based approach for rotated rounded rectangles
      
      const width = maxX - minX;
      const height = maxY - minY;
      
      const screenWidth = width * zoom;
      const screenHeight = height * zoom;
      const screenRadius = cornerRadius * zoom;
      
      const screenCenter = worldToScreen(center, zoom, offset);
      
      ctx.translate(screenCenter.x, screenCenter.y);
      ctx.rotate((rotation * Math.PI) / 180);
      
      ctx.beginPath();
      ctx.roundRect(-screenWidth/2, -screenHeight/2, screenWidth, screenHeight, screenRadius);
    } else {
      ctx.beginPath();
      ctx.moveTo(screenCorners[0].x, screenCorners[0].y);
      for (let i = 1; i < screenCorners.length; i++) {
        ctx.lineTo(screenCorners[i].x, screenCorners[i].y);
      }
      ctx.closePath();
    }

    // Apply fill and stroke
    const hasFill = (obj.fillColor && obj.fillColor !== "transparent") || (obj.fill && obj.fill !== "transparent");
    if (hasFill) {
      ctx.fill();
    }
    
    const hasStroke = (obj.strokeColor && obj.strokeColor !== "transparent") || (obj.stroke && obj.stroke !== "transparent") || (obj.strokeWidth || 0) > 0;
    if (hasStroke) {
      ctx.stroke();
    } else if (["rectangle", "frame", "section"].includes(obj.type)) {
       // Default stroke if none provided to ensure visibility
       ctx.stroke();
    }

    // DRAW CORNER HANDLES IF HOVERED
    if (isHovered && !obj.isLocked) {
      const handleDistance = 15; // px from corner
      const handleRadius = 4;
      
      // Calculate handle positions in local space
      const width = maxX - minX;
      const height = maxY - minY;
      const sw = width * zoom;
      const sh = height * zoom;
      
      // Ensure handles don't overlap if rect is too small
      const safeDist = Math.min(handleDistance, sw/3, sh/3);
      
      const localHandles = [
        { x: -sw/2 + safeDist, y: -sh/2 + safeDist },
        { x: sw/2 - safeDist, y: -sh/2 + safeDist },
        { x: sw/2 - safeDist, y:  sh/2 - safeDist },
        { x: -sw/2 + safeDist, y:  sh/2 - safeDist }
      ];

      // If text or other context was moved, we are already at screenCenter rotated
      // If cornerRadius was 0, we were NOT in translated space. Let's fix.
      if (cornerRadius <= 0) {
          const screenCenter = worldToScreen(center, zoom, offset);
          ctx.translate(screenCenter.x, screenCenter.y);
          ctx.rotate((rotation * Math.PI) / 180);
      }

      ctx.fillStyle = "white";
      ctx.strokeStyle = "#4F46E5"; // Indigo-600 (approximate color from image)
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
      
      // Center dot (as seen in image)
      ctx.beginPath();
      ctx.arc(0, 0, handleRadius/2, 0, Math.PI * 2);
      ctx.fillStyle = "#4F46E5";
      ctx.fill();
    }

    ctx.restore();
  };

  return {
    renderRectangle,
  };
}
