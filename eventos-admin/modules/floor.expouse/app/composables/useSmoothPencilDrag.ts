// composables/useSmoothPencilDrag.ts - Ultra-smooth pencil dragging
import { ref } from "vue";
import type { Point, CanvasObject } from "@floorplan/types/canvas";

/**
 * This composable provides optimized dragging for pencil objects
 * by using requestAnimationFrame and reducing unnecessary re-renders
 */
export function useSmoothPencilDrag() {
  const isDragging = ref(false);
  const pendingUpdate = ref(false);
  const lastUpdateTime = ref(0);
  const UPDATE_THROTTLE = 16; // ~60fps

  /**
   * Optimized point update that batches changes
   */
  const updatePencilPoints = (
    obj: CanvasObject,
    deltaX: number,
    deltaY: number,
    initialPoints: Point[]
  ) => {
    // Skip redundant updates
    const now = Date.now();
    if (pendingUpdate.value && now - lastUpdateTime.value < UPDATE_THROTTLE) {
      return;
    }

    pendingUpdate.value = true;
    lastUpdateTime.value = now;

    requestAnimationFrame(() => {
      // Direct mutation for smooth performance
      if (obj.points && Array.isArray(obj.points)) {
        initialPoints.forEach((p, index) => {
          if (obj.points[index]) {
            obj.points[index].x = p.x + deltaX;
            obj.points[index].y = p.y + deltaY;
          }
        });
      }
      pendingUpdate.value = false;
    });
  };

  /**
   * Pre-calculate screen transformations for better performance
   */
  const optimizePointTransform = (
    points: Point[],
    zoom: number,
    offset: Point
  ) => {
    // Cache transformed points for rendering
    return points.map((p) => ({
      x: (p.x - offset.x) * zoom,
      y: (p.y - offset.y) * zoom,
    }));
  };

  return {
    isDragging,
    updatePencilPoints,
    optimizePointTransform,
  };
}
