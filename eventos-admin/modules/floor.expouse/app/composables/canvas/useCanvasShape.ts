import type { CanvasObject, Point } from "@floorplan/types/canvas";

export function useCanvasShape(
  getCenter: (obj: CanvasObject) => Point,
  rotatePoint: (point: Point, center: Point, rotation: number) => Point
) {
  const renderShape = (
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
    const center = getCenter(obj);
    const rotation = obj.rotation || 0;

    let points: Point[] = [];

    switch (obj.type) {
      case "diamond":
        points = createDiamondPoints(p1, p2);
        break;
      case "pentagon":
        points = createPentagonPoints(p1, p2);
        break;
      case "hexagon":
        points = createHexagonPoints(p1, p2);
        break;
      case "triangle":
        points = createTrianglePoints(p1, p2);
        break;
      case "star":
        points = createStarPoints(p1, p2);
        break;
      default:
        return;
    }

    const rotatedPoints = points.map((p) => rotatePoint(p, center, rotation));
    const screenPoints = rotatedPoints.map((p) =>
      worldToScreen(p, zoom, offset)
    );

    ctx.save();
    ctx.beginPath();
    ctx.moveTo(screenPoints[0].x, screenPoints[0].y);
    for (let i = 1; i < screenPoints.length; i++) {
      ctx.lineTo(screenPoints[i].x, screenPoints[i].y);
    }
    ctx.closePath();
    ctx.stroke();

    // DRAW CORNER HANDLES IF HOVERED
    if (isHovered && !obj.isLocked) {
      const handleDistance = 15; // px from corner
      const handleRadius = 4;
      
      const width = Math.abs(p2.x - p1.x) * zoom;
      const height = Math.abs(p2.y - p1.y) * zoom;
      
      // Ensure handles don't overlap if shape is too small
      const safeDist = Math.min(handleDistance, width/3, height/3);
      
      const screenCenter = worldToScreen(center, zoom, offset);
      const rotationRad = (rotation * Math.PI) / 180;

      ctx.translate(screenCenter.x, screenCenter.y);
      ctx.rotate(rotationRad);

      const localHandles = [
        { x: -width/2 + safeDist, y: -height/2 + safeDist },
        { x:  width/2 - safeDist, y: -height/2 + safeDist },
        { x:  width/2 - safeDist, y:  height/2 - safeDist },
        { x: -width/2 + safeDist, y:  height/2 - safeDist }
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

  const createDiamondPoints = (p1: Point, p2: Point): Point[] => {
    const centerX = (p1.x + p2.x) / 2;
    const centerY = (p1.y + p2.y) / 2;
    const width = Math.abs(p2.x - p1.x);
    const height = Math.abs(p2.y - p1.y);

    return [
      { x: centerX, y: p1.y },
      { x: p2.x, y: centerY },
      { x: centerX, y: p2.y },
      { x: p1.x, y: centerY },
    ];
  };

  const createPentagonPoints = (p1: Point, p2: Point): Point[] => {
    const centerX = (p1.x + p2.x) / 2;
    const centerY = (p1.y + p2.y) / 2;
    const width = Math.abs(p2.x - p1.x);
    const height = Math.abs(p2.y - p1.y);
    const radius = Math.min(width, height) / 2;

    const points: Point[] = [];
    for (let i = 0; i < 5; i++) {
      const angle = (i * 2 * Math.PI) / 5 - Math.PI / 2;
      points.push({
        x: centerX + radius * Math.cos(angle),
        y: centerY + radius * Math.sin(angle),
      });
    }
    return points;
  };

  const createHexagonPoints = (p1: Point, p2: Point): Point[] => {
    const centerX = (p1.x + p2.x) / 2;
    const centerY = (p1.y + p2.y) / 2;
    const width = Math.abs(p2.x - p1.x);
    const height = Math.abs(p2.y - p1.y);
    const radius = Math.min(width, height) / 2;

    const points: Point[] = [];
    for (let i = 0; i < 6; i++) {
      const angle = (i * 2 * Math.PI) / 6 - Math.PI / 6;
      points.push({
        x: centerX + radius * Math.cos(angle),
        y: centerY + radius * Math.sin(angle),
      });
    }
    return points;
  };

  const createTrianglePoints = (p1: Point, p2: Point): Point[] => {
    const minX = Math.min(p1.x, p2.x);
    const minY = Math.min(p1.y, p2.y);
    const width = Math.abs(p2.x - p1.x);
    const height = Math.abs(p2.y - p1.y);

    return [
      { x: minX + width / 2, y: minY },
      { x: minX + width, y: minY + height },
      { x: minX, y: minY + height },
    ];
  };

  const createStarPoints = (p1: Point, p2: Point): Point[] => {
    const centerX = (p1.x + p2.x) / 2;
    const centerY = (p1.y + p2.y) / 2;
    const width = Math.abs(p2.x - p1.x);
    const height = Math.abs(p2.y - p1.y);
    const outerRadius = Math.min(width, height) / 2;
    const innerRadius = outerRadius * 0.4;

    const points: Point[] = [];
    for (let i = 0; i < 10; i++) {
      const angle = (i * 2 * Math.PI) / 10 - Math.PI / 2;
      const radius = i % 2 === 0 ? outerRadius : innerRadius;
      points.push({
        x: centerX + radius * Math.cos(angle),
        y: centerY + radius * Math.sin(angle),
      });
    }
    return points;
  };

  return {
    renderShape,
  };
}
