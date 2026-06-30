// useObjectDetection.ts
import type { Point, CanvasObject } from "@floorplan/types/canvas";
import { useCanvasObjects } from "@floorplan/composables/useCanvasObjects";

export function useObjectDetection(store: any) {
  const canvasObjects = useCanvasObjects();

  const isPointInPolygon = (point: Point, polygon: Point[]): boolean => {
    if (polygon.length < 3) return false;
    let inside = false;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
      const xi = polygon[i].x,
        yi = polygon[i].y;
      const xj = polygon[j].x,
        yj = polygon[j].y;
      const intersect =
        yi > point.y !== yj > point.y &&
        point.x < ((xj - xi) * (point.y - yi)) / (yj - yi) + xi;
      if (intersect) inside = !inside;
    }
    return inside;
  };

  const findSelectableObjectAtPoint = (
    worldPoint: Point
  ): CanvasObject | null => {
    for (let i = store.objects.length - 1; i >= 0; i--) {
      const obj = store.objects[i];
      if (obj.isLocked || obj.isVisible === false) continue;
      if (isPointInObject(worldPoint, obj)) return obj;
    }
    return null;
  };

  const findObjectAtPoint = (worldPoint: Point): CanvasObject | null => {
    for (let i = store.objects.length - 1; i >= 0; i--) {
      const obj = store.objects[i];
      if (obj.isLocked || obj.isVisible === false) continue;
      if (isPointInObject(worldPoint, obj)) return obj;
    }
    return null;
  };

  const isPointInObject = (point: Point, obj: CanvasObject): boolean => {
    const bounds = canvasObjects.getRotatedBounding(obj);
    if (!bounds) return false;

    // Add some tolerance for better selection
    const tolerance = 5;
    return (
      point.x >= bounds.x - tolerance &&
      point.x <= bounds.x + bounds.width + tolerance &&
      point.y >= bounds.y - tolerance &&
      point.y <= bounds.y + bounds.height + tolerance
    );
  };

  const isPointInRectangle = (point: Point, obj: CanvasObject): boolean => {
    const bounds = canvasObjects.getRotatedBounding(obj);
    if (!bounds) return false;

    const center = canvasObjects.getCenter(obj);
    const rotation = -(obj.rotation || 0); // Reverse rotation for point check

    // Rotate the point to object's local coordinates
    const localPoint = canvasObjects.rotatePoint(point, center, rotation);

    // Check against unrotated bounds
    const unrotatedBounds = canvasObjects.getObjectBounding(obj);
    if (!unrotatedBounds) return false;

    return (
      localPoint.x >= unrotatedBounds.x &&
      localPoint.x <= unrotatedBounds.x + unrotatedBounds.width &&
      localPoint.y >= unrotatedBounds.y &&
      localPoint.y <= unrotatedBounds.y + unrotatedBounds.height
    );
  };

  const isPointInEllipse = (point: Point, obj: CanvasObject): boolean => {
    const bounds = canvasObjects.getRotatedBounding(obj);
    if (!bounds) return false;

    const centerX = bounds.x + bounds.width / 2;
    const centerY = bounds.y + bounds.height / 2;
    const radiusX = bounds.width / 2;
    const radiusY = bounds.height / 2;

    const normalizedX = (point.x - centerX) / radiusX;
    const normalizedY = (point.y - centerY) / radiusY;

    return normalizedX * normalizedX + normalizedY * normalizedY <= 1;
  };

  const isPointNearLine = (
    point: Point,
    obj: CanvasObject,
    tolerance: number = 5
  ): boolean => {
    if (obj.points.length < 2) return false;

    for (let i = 0; i < obj.points.length - 1; i++) {
      const p1 = obj.points[i];
      const p2 = obj.points[i + 1];

      // Calculate distance from point to line segment
      const A = point.x - p1.x;
      const B = point.y - p1.y;
      const C = p2.x - p1.x;
      const D = p2.y - p1.y;

      const dot = A * C + B * D;
      const lenSq = C * C + D * D;
      let param = -1;
      if (lenSq !== 0) param = dot / lenSq;

      let xx, yy;
      if (param < 0) {
        xx = p1.x;
        yy = p1.y;
      } else if (param > 1) {
        xx = p2.x;
        yy = p2.y;
      } else {
        xx = p1.x + param * C;
        yy = p1.y + param * D;
      }

      const dx = point.x - xx;
      const dy = point.y - yy;
      const distance = Math.sqrt(dx * dx + dy * dy);

      if (distance <= tolerance) return true;
    }
    return false;
  };

  const isPointNearPolyline = (
    point: Point,
    obj: CanvasObject,
    tolerance: number = 3
  ): boolean => {
    if (obj.points.length < 2) return false;

    for (let i = 0; i < obj.points.length - 1; i++) {
      const p1 = obj.points[i];
      const p2 = obj.points[i + 1];

      // Simple distance check for pencil drawings
      const dx = p2.x - p1.x;
      const dy = p2.y - p1.y;
      const length = Math.sqrt(dx * dx + dy * dy);

      if (length === 0) continue;

      const t = Math.max(
        0,
        Math.min(
          1,
          ((point.x - p1.x) * dx + (point.y - p1.y) * dy) / (length * length)
        )
      );
      const proj = {
        x: p1.x + t * dx,
        y: p1.y + t * dy,
      };

      const distance = Math.sqrt(
        Math.pow(point.x - proj.x, 2) + Math.pow(point.y - proj.y, 2)
      );

      if (distance <= tolerance) return true;
    }
    return false;
  };

  return {
    isPointInPolygon,
    findSelectableObjectAtPoint,
    findObjectAtPoint,
    isPointInObject,
    isPointInRectangle,
    isPointInEllipse,
    isPointNearLine,
    isPointNearPolyline,
  };
}
