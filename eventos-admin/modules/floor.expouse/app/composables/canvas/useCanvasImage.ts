import type { CanvasObject, Point } from "@floorplan/types/canvas";

export function useCanvasImage() {
  const renderImage = (
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
    const width = Math.abs(p2.x - p1.x);
    const height = Math.abs(p2.y - p1.y);

    const screenMinX = (minX - offset.x) * zoom;
    const screenMinY = (minY - offset.y) * zoom;
    const screenWidth = width * zoom;
    const screenHeight = height * zoom;

    const rotationRad = (obj.rotation || 0) * (Math.PI / 180);
    const centerX = (p1.x + p2.x) / 2;
    const centerY = (p1.y + p2.y) / 2;
    const screenCenter = worldToScreen({ x: centerX, y: centerY }, zoom, offset);

    ctx.save();
    
    // Handle rotation if present
    if (obj.rotation) {
      ctx.translate(screenCenter.x, screenCenter.y);
      ctx.rotate(rotationRad);
      ctx.translate(-screenCenter.x, -screenCenter.y);
    }

    // Draw image placeholder
    const radius = Math.max(0, (obj.cornerRadius || 0) * zoom);
    
    ctx.beginPath();
    if (radius > 0) {
      ctx.roundRect(screenMinX, screenMinY, screenWidth, screenHeight, radius);
    } else {
      ctx.rect(screenMinX, screenMinY, screenWidth, screenHeight);
    }
    
    ctx.strokeStyle = obj.color;
    ctx.stroke();

    // Draw diagonal lines (optionally clipped by rounded rect)
    ctx.save();
    if (radius > 0) {
      ctx.clip(); // Clip diagonals within the rounded corners
    }
    ctx.beginPath();
    ctx.moveTo(screenMinX, screenMinY);
    ctx.lineTo(screenMinX + screenWidth, screenMinY + screenHeight);
    ctx.moveTo(screenMinX + screenWidth, screenMinY);
    ctx.lineTo(screenMinX, screenMinY + screenHeight);
    ctx.stroke();
    ctx.restore();

    // Draw image indicator text
    ctx.fillStyle = obj.color;
    ctx.font = "12px Arial";
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillText(
      "Image",
      screenMinX + screenWidth / 2,
      screenMinY + screenHeight / 2
    );

    // DRAW CORNER HANDLES IF HOVERED
    if (isHovered && !obj.isLocked) {
      const handleDistance = 15; // px from corner
      const handleRadius = 4;
      
      const sw = screenWidth;
      const sh = screenHeight;
      
      const safeDist = Math.min(handleDistance, sw/3, sh/3);
      
      const localHandles = [
        { x: screenMinX + safeDist, y: screenMinY + safeDist },
        { x: screenMinX + screenWidth - safeDist, y: screenMinY + safeDist },
        { x: screenMinX + screenWidth - safeDist, y: screenMinY + screenHeight - safeDist },
        { x: screenMinX + safeDist, y: screenMinY + screenHeight - safeDist }
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
      ctx.arc(screenMinX + screenWidth / 2, screenMinY + screenHeight / 2, handleRadius/2, 0, Math.PI * 2);
      ctx.fillStyle = "#4F46E5";
      ctx.fill();
    }

    ctx.restore();
  };

  return {
    renderImage,
  };
}
