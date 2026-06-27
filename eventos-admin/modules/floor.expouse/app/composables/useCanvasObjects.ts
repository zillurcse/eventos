// useCanvasObjects.ts
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import type { Point, CanvasObject } from "@floorplan/types/canvas";

export function useCanvasObjects() {
  const store = useCanvasStore();

  // Object creation utilities
  const createObject = (
    type: string,
    points: Point[],
    color: string
  ): CanvasObject => ({
    id: Date.now().toString(),
    type,
    points,
    color,
    isSelected: false,
    rotation: 0,
  });

  // Bounding box calculations
  const getObjectBounding = (obj: CanvasObject) => {
    // ✅ PERFORMANCE: If object has a cached bounding box, trust it.
    // This is critical for smooth dragging of complex paths (pencil, walls).
    if (obj.boundingBox && obj.boundingBox.width !== undefined) {
      return obj.boundingBox;
    }

    if (obj.points.length < 2) return obj.boundingBox || null;

    if (["rectangle", "ellipse", "frame", "section"].includes(obj.type)) {
      const p1 = obj.points[0];
      const p2 = obj.points[1];
      return {
        x: Math.min(p1.x, p2.x),
        y: Math.min(p1.y, p2.y),
        width: Math.abs(p1.x - p2.x),
        height: Math.abs(p1.y - p2.y),
      };
    } else {
      let minX = Infinity,
        minY = Infinity,
        maxX = -Infinity,
        maxY = -Infinity;

      obj.points.forEach((point) => {
        minX = Math.min(minX, point.x);
        minY = Math.min(minY, point.y);
        maxX = Math.max(maxX, point.x);
        maxY = Math.max(maxY, point.y);
      });

      if (minX === Infinity) return null;

      const bounds = {
        x: minX,
        y: minY,
        width: maxX - minX,
        height: maxY - minY,
      };

      // ✅ CACHE: Update the object's bounding box so next call is faster
      // This is safe even for reactive objects as it's just a non-structural metadata property
      if (obj.type === "pencil" || obj.type === "wall" || obj.type === "curve-arrow") {
        obj.boundingBox = bounds;
      }

      return bounds;
    }
  };

  const getRotatedBounding = (obj: CanvasObject) => {
    // ✅ FIRST: Check if object has a boundingBox property and use it
    if (obj.boundingBox && obj.type === "booth") {
      const bounds = obj.boundingBox;
      const center = {
        x: bounds.x + bounds.width / 2,
        y: bounds.y + bounds.height / 2,
      };
      const rotation = obj.rotation || 0;

      if (rotation === 0) {
        // No rotation, return the bounding box as-is
        return { ...bounds };
      }

      // For rotated booths, calculate rotated bounding box
      const corners: Point[] = [
        { x: bounds.x, y: bounds.y },
        { x: bounds.x + bounds.width, y: bounds.y },
        {
          x: bounds.x + bounds.width,
          y: bounds.y + bounds.height,
        },
        { x: bounds.x, y: bounds.y + bounds.height },
      ];

      // Rotate the four corners
      const rotatedCorners = corners.map((p) =>
        rotatePoint(p, center, rotation)
      );

      // Compute min/max over rotated corners
      let minX = Infinity,
        minY = Infinity,
        maxX = -Infinity,
        maxY = -Infinity;
      rotatedCorners.forEach((point) => {
        minX = Math.min(minX, point.x);
        minY = Math.min(minY, point.y);
        maxX = Math.max(maxX, point.x);
        maxY = Math.max(maxY, point.y);
      });

      if (minX === Infinity) return null;

      return {
        x: minX,
        y: minY,
        width: maxX - minX,
        height: maxY - minY,
      };
    }

    // ✅ FALLBACK: Use the existing logic for other object types
    if (obj.points.length < 2) return null;

    const center = getCenter(obj);
    const rotation = obj.rotation || 0;

    if (["rectangle", "ellipse", "frame", "section"].includes(obj.type)) {
      // Compute four corners of the unrotated bounding box
      const unrotatedBounds = getObjectBounding(obj);
      if (!unrotatedBounds) return null;

      const corners: Point[] = [
        { x: unrotatedBounds.x, y: unrotatedBounds.y },
        { x: unrotatedBounds.x + unrotatedBounds.width, y: unrotatedBounds.y },
        {
          x: unrotatedBounds.x + unrotatedBounds.width,
          y: unrotatedBounds.y + unrotatedBounds.height,
        },
        {
          x: unrotatedBounds.x,
          y: unrotatedBounds.y + unrotatedBounds.height,
        },
      ];

      // Rotate the four corners
      const rotatedCorners = corners.map((p) =>
        rotatePoint(p, center, rotation)
      );

      // Compute min/max over rotated corners
      let minX = Infinity,
        minY = Infinity,
        maxX = -Infinity,
        maxY = -Infinity;
      rotatedCorners.forEach((point) => {
        minX = Math.min(minX, point.x);
        minY = Math.min(minY, point.y);
        maxX = Math.max(maxX, point.x);
        maxY = Math.max(maxY, point.y);
      });

      if (minX === Infinity) return null;

      return {
        x: minX,
        y: minY,
        width: maxX - minX,
        height: maxY - minY,
      };
    } else {
      // ✅ OPTIMIZATION: If object is not rotated, use standard bounding box directly
      if (obj.rotation === 0 || !obj.rotation) {
        return getObjectBounding(obj);
      }

      // For other types (lines, pencil, walls, etc.), rotate all points and compute min/max
      const rotatedPoints = obj.points.map((p) =>
        rotatePoint(p, center, rotation)
      );

      let minX = Infinity,
        minY = Infinity,
        maxX = -Infinity,
        maxY = -Infinity;

      rotatedPoints.forEach((point) => {
        minX = Math.min(minX, point.x);
        minY = Math.min(minY, point.y);
        maxX = Math.max(maxX, point.x);
        maxY = Math.max(maxY, point.y);
      });

      if (minX === Infinity) return null;

      return {
        x: minX,
        y: minY,
        width: maxX - minX,
        height: maxY - minY,
      };
    }
  };

  const getSelectionBounds = (objects: CanvasObject[]) => {
    if (objects.length === 0) return null;

    let minX = Infinity,
      minY = Infinity,
      maxX = -Infinity,
      maxY = -Infinity;

    objects.forEach((obj) => {
      const bounds = getRotatedBounding(obj);
      if (bounds) {
        minX = Math.min(minX, bounds.x);
        minY = Math.min(minY, bounds.y);
        maxX = Math.max(maxX, bounds.x + bounds.width);
        maxY = Math.max(maxY, bounds.y + bounds.height);
      }
    });

    if (minX === Infinity) return null;

    return {
      x: minX,
      y: minY,
      width: maxX - minX,
      height: maxY - minY,
    };
  };

  // Geometry utilities
  const getCenter = (obj: CanvasObject): Point => {
    // First check for booth with boundingBox
    if (obj.boundingBox && obj.type === "booth") {
      return {
        x: obj.boundingBox.x + obj.boundingBox.width / 2,
        y: obj.boundingBox.y + obj.boundingBox.height / 2,
      };
    }

    // Fallback to existing logic
    const bounds = getObjectBounding(obj);
    return bounds
      ? {
          x: bounds.x + bounds.width / 2,
          y: bounds.y + bounds.height / 2,
        }
      : { x: 0, y: 0 };
  };

  const boxesIntersect = (a: any, b: any) => {
    return !(
      a.x + a.width < b.x ||
      b.x + b.width < a.x ||
      a.y + a.height < b.y ||
      b.y + b.height < a.y
    );
  };

  const isBoundsInside = (inner: any, outer: any) => {
    return (
      inner.x >= outer.x - 0.1 &&
      inner.y >= outer.y - 0.1 &&
      inner.x + inner.width <= outer.x + outer.width + 0.1 &&
      inner.y + inner.height <= outer.y + outer.height + 0.1
    );
  };

  // Object transformation utilities
  const rotatePoint = (point: Point, center: Point, angle: number): Point => {
    const rad = angle * (Math.PI / 180);
    const cos = Math.cos(rad);
    const sin = Math.sin(rad);

    const translatedX = point.x - center.x;
    const translatedY = point.y - center.y;

    return {
      x: translatedX * cos - translatedY * sin + center.x,
      y: translatedX * sin + translatedY * cos + center.y,
    };
  };

  const scaleObject = (
    obj: CanvasObject,
    scaleX: number,
    scaleY: number,
    origin: Point
  ) => {
    return obj.points.map((point) => ({
      x: origin.x + (point.x - origin.x) * scaleX,
      y: origin.y + (point.y - origin.y) * scaleY,
    }));
  };

  const getDescendants = (parents: CanvasObject[]) => {
    const allNestedObjects: CanvasObject[] = [];
    const allNestedElements: DomElement[] = [];
    
    const objectsToProcess = [...parents];
    const processedObjectIds = new Set(parents.map(o => o.id));
    const processedElementIds = new Set<string>();

    while (objectsToProcess.length > 0) {
      const parent = objectsToProcess.shift()!;
      if (parent.type === "frame" || parent.type === "section") {
        const parentBounds = getRotatedBounding(parent);
        if (!parentBounds) continue;

        // Find nested canvas objects
        store.objects.forEach(obj => {
          if (processedObjectIds.has(obj.id) || obj.isLocked || obj.isVisible === false) return;
          const objBounds = getRotatedBounding(obj);
          if (objBounds && isBoundsInside(objBounds, parentBounds)) {
            allNestedObjects.push(obj);
            objectsToProcess.push(obj); // Recurse
            processedObjectIds.add(obj.id);
          }
        });

        // Find nested DOM elements
        store.domElements.forEach(el => {
          if (processedElementIds.has(el.id) || el.isLocked || el.isVisible === false) return;
          const elBounds = { 
            x: el.position.x, 
            y: el.position.y, 
            width: el.size?.width ?? 0, 
            height: el.size?.height ?? 0 
          };
          if (isBoundsInside(elBounds, parentBounds)) {
            allNestedElements.push(el);
            processedElementIds.add(el.id);
          }
        });
      }
    }

    return {
      objects: allNestedObjects,
      elements: allNestedElements
    };
  };

  return {
    createObject,
    getObjectBounding,
    getRotatedBounding,
    getSelectionBounds,
    getCenter,
    boxesIntersect,
    rotatePoint,
    scaleObject,
    isBoundsInside,
    getDescendants,
  };
}
