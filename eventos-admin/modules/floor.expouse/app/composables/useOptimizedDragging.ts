// composables/useOptimizedDragging.ts - COMPLETE WITH EQUIDISTANT SPACING
import { ref, computed, toRaw } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useCanvasObjects } from "@floorplan/composables/useCanvasObjects";
import { useBoothArrows } from "@floorplan/composables/useBoothArrows";
import type { Point, CanvasObject } from "@floorplan/types/canvas";

interface LocalAlignmentGuide {
  type: "vertical" | "horizontal";
  position: number;
  start: number;
  end: number;
  alignment: "left" | "right" | "top" | "bottom" | "centerX" | "centerY";
  isMultiAlign?: boolean;
  alignedCount?: number;
  isFullScreen?: boolean;
  distance?: number;
  targetObjectId?: string;
  alignedObjects?: Array<{
    id: string;
    position: number;
  }>;
  objectBounds?: Array<{
    id: string;
    start: number;
    end: number;
    isMoving?: boolean;
  }>;
  isEquidistant?: boolean; // NEW
}

interface DragState {
  isActive: boolean;
  startPoint: Point | null;
  draggedObjects: CanvasObject[];
  initialPositions: Map<string, Point[]>;
  initialBoundingBoxes: Map<string, { x: number; y: number; width: number; height: number }>;
}

interface AlignedElementData {
  id: string;
  edges: Array<"left" | "right" | "top" | "bottom" | "centerX" | "centerY">;
}

export function useOptimizedDragging() {
  const store = useCanvasStore();
  const canvasObjects = useCanvasObjects();
  const { syncBoothDrag } = useBoothArrows();

  const SNAP_THRESHOLD = 8;
  const GUIDE_PADDING = 30;
  const MIN_DISTANCE_TO_SHOW = 50;
  const EQUIDISTANT_TOLERANCE = 15; // NEW: Tolerance for equal spacing detection

  const isUpdatePending = ref(false);
  const latestArgs = ref<{ worldPoint: Point; canvasWidth?: number; canvasHeight?: number; zoom?: number; offset?: Point; callback?: () => void } | null>(null);

  const dragState = ref<DragState>({
    isActive: false,
    startPoint: null,
    draggedObjects: [],
    initialPositions: new Map(),
    initialBoundingBoxes: new Map(),
  });

  const localGuides = ref<LocalAlignmentGuide[]>([]);
  const alignedElements = ref<AlignedElementData[]>([]);

  const boundsCache = new Map<string, any>();
  let cacheTimestamp = 0;
  const CACHE_DURATION = 50;

  const getRotatedBounds = (obj: CanvasObject) => {
    if (obj.elementData) {
      return {
        x: obj.elementData.position.x,
        y: obj.elementData.position.y,
        width: obj.elementData.size.width,
        height: obj.elementData.size.height,
      };
    }
    return canvasObjects.getRotatedBounding(obj);
  };

  const getBounds = (obj: CanvasObject) => {
    const now = Date.now();
    if (now - cacheTimestamp > CACHE_DURATION) {
      boundsCache.clear();
      cacheTimestamp = now;
    }

    if (boundsCache.has(obj.id)) {
      return boundsCache.get(obj.id);
    }

    const bounds = getRotatedBounds(obj);
    boundsCache.set(obj.id, bounds);
    return bounds;
  };

  // ✅ UPDATED: Include DOM elements as alignment targets
  const findNearbyObjects = (
    movingObj: CanvasObject
  ): Array<CanvasObject | any> => {
    if (!store.objects || !Array.isArray(store.objects)) {
      return [];
    }

    const nearbyCanvasObjects = store.objects.filter((obj) => {
      if (obj.id === movingObj.id || obj.isLocked || obj.isVisible === false) {
        return false;
      }
      return true;
    });

    // ✅ NEW: Add DOM elements as pseudo-objects for alignment
    const domElementsAsPseudoObjects = (store.domElements || [])
      .filter((el) => !el.isLocked && el.isVisible !== false)
      .map((el) => ({
        id: el.id,
        type: "dom-element",
        points: [
          { x: el.position.x, y: el.position.y },
          {
            x: el.position.x + el.size.width,
            y: el.position.y + el.size.height,
          },
        ],
        isLocked: false,
        isVisible: true,
        elementData: {
          position: el.position,
          size: el.size,
        },
      }));

    return [...nearbyCanvasObjects, ...domElementsAsPseudoObjects];
  };

  // ✅ NEW: Detect near-equal spacing and return snap adjustments
  // ✅ UPDATED: Detect near-equal spacing and return snap adjustments (gap-based)
  const detectEquidistantSpacing = (
    movingObj: CanvasObject,
    movingBounds: any
  ): { snapX: number; snapY: number } => {
    let snapX = 0;
    let snapY = 0;

    const moving = {
      left: movingBounds.x,
      right: movingBounds.x + movingBounds.width,
      top: movingBounds.y,
      bottom: movingBounds.y + movingBounds.height,
      centerX: movingBounds.x + movingBounds.width / 2,
      centerY: movingBounds.y + movingBounds.height / 2,
    };

    const allObjects = store.objects.filter(
      (obj) =>
        obj.id !== movingObj.id && !obj.isLocked && obj.isVisible !== false
    );

    // Horizontal equidistant spacing (gaps between objects)
    const horizontallyAligned = allObjects
      .map((obj) => {
        const bounds = getBounds(obj);
        if (!bounds) return null;
        return {
          id: obj.id,
          left: bounds.x,
          right: bounds.x + bounds.width,
          centerY: bounds.y + bounds.height / 2,
        };
      })
      .filter((item) => item && Math.abs(item.centerY - moving.centerY) < 50);

    if (horizontallyAligned.length >= 2) {
      const sortedByX = [...horizontallyAligned].sort((a, b) => a.left - b.left);
      
      // Option 1: Moving object is part of a 3+ object sequence
      const allItems = [
        ...sortedByX.map(o => ({ left: o.left, right: o.right, id: o.id })),
        { left: moving.left, right: moving.right, id: movingObj.id }
      ].sort((a, b) => a.left - b.left);

      const movingIdx = allItems.findIndex(i => i.id === movingObj.id);
      
      if (movingIdx > 0 && movingIdx < allItems.length - 1) {
        // Between two objects: Gap(prev, moving) should equal Gap(moving, next)
        const prev = allItems[movingIdx - 1];
        const next = allItems[movingIdx + 1];
        // Gap1 = moving.left - prev.right
        // Gap2 = next.left - moving.right
        // Target: moving.left - prev.right = next.left - (moving.left + width)
        // 2 * moving.left = next.left + prev.right - width
        const targetLeft = (next.left + prev.right - movingBounds.width) / 2;
        if (Math.abs(moving.left - targetLeft) < EQUIDISTANT_TOLERANCE) {
          snapX = targetLeft - moving.left;
        }
      } else if (movingIdx > 1) {
        // After two objects: Gap(last-1, last) should equal Gap(last, moving)
        const last = allItems[movingIdx - 1];
        const prev = allItems[movingIdx - 2];
        const prevGap = last.left - prev.right;
        const targetLeft = last.right + prevGap;
        if (Math.abs(moving.left - targetLeft) < EQUIDISTANT_TOLERANCE) {
          snapX = targetLeft - moving.left;
        }
      } else if (movingIdx < allItems.length - 2) {
        // Before two objects: Gap(moving, next) should equal Gap(next, next+1)
        const next = allItems[movingIdx + 1];
        const next2 = allItems[movingIdx + 2];
        const nextGap = next2.left - next.right;
        const targetRight = next.left - nextGap;
        if (Math.abs(moving.right - targetRight) < EQUIDISTANT_TOLERANCE) {
          snapX = targetRight - moving.right;
        }
      }
    }

    // Vertical equidistant spacing (gaps between objects)
    const verticallyAligned = allObjects
      .map((obj) => {
        const bounds = getBounds(obj);
        if (!bounds) return null;
        return {
          id: obj.id,
          top: bounds.y,
          bottom: bounds.y + bounds.height,
          centerX: bounds.x + bounds.width / 2,
        };
      })
      .filter((item) => item && Math.abs(item.centerX - moving.centerX) < 50);

    if (verticallyAligned.length >= 2) {
      const sortedByY = [...verticallyAligned].sort((a, b) => a.top - b.top);
      const allItems = [
        ...sortedByY.map(o => ({ top: o.top, bottom: o.bottom, id: o.id })),
        { top: moving.top, bottom: moving.bottom, id: movingObj.id }
      ].sort((a, b) => a.top - b.top);

      const movingIdx = allItems.findIndex(i => i.id === movingObj.id);

      if (movingIdx > 0 && movingIdx < allItems.length - 1) {
        const prev = allItems[movingIdx - 1];
        const next = allItems[movingIdx + 1];
        const targetTop = (next.top + prev.bottom - movingBounds.height) / 2;
        if (Math.abs(moving.top - targetTop) < EQUIDISTANT_TOLERANCE) {
          snapY = targetTop - moving.top;
        }
      } else if (movingIdx > 1) {
        const last = allItems[movingIdx - 1];
        const prev = allItems[movingIdx - 2];
        const prevGap = last.top - prev.bottom;
        const targetTop = last.bottom + prevGap;
        if (Math.abs(moving.top - targetTop) < EQUIDISTANT_TOLERANCE) {
          snapY = targetTop - moving.top;
        }
      } else if (movingIdx < allItems.length - 2) {
        const next = allItems[movingIdx + 1];
        const next2 = allItems[movingIdx + 2];
        const nextGap = next2.top - next.bottom;
        const targetBottom = next.top - nextGap;
        if (Math.abs(moving.bottom - targetBottom) < EQUIDISTANT_TOLERANCE) {
          snapY = targetBottom - moving.bottom;
        }
      }
    }

    return { snapX, snapY };
  };

  // ✅ UPDATED: Generate visual guides for equidistant spacing (gap-based with distances)
  const generateEquidistantGuides = (
    movingObj: CanvasObject,
    movingBounds: any
  ): LocalAlignmentGuide[] => {
    const guides: LocalAlignmentGuide[] = [];
    const moving = {
      left: movingBounds.x,
      right: movingBounds.x + movingBounds.width,
      top: movingBounds.y,
      bottom: movingBounds.y + movingBounds.height,
      centerX: movingBounds.x + movingBounds.width / 2,
      centerY: movingBounds.y + movingBounds.height / 2,
    };

    const allObjects = store.objects.filter(
      (obj) =>
        obj.id !== movingObj.id && !obj.isLocked && obj.isVisible !== false
    );

    // Horizontal gaps
    const horizontallyAligned = allObjects
      .map((obj) => {
        const b = getBounds(obj);
        if (!b) return null;
        return { obj, b, centerY: b.y + b.height / 2 };
      })
      .filter((item) => item && Math.abs(item.centerY - moving.centerY) < 50);

    if (horizontallyAligned.length >= 2) {
      const allSorted = [
        ...horizontallyAligned.map(o => ({ id: o.obj.id, left: o.b.x, right: o.b.x + o.b.width, top: o.b.y, bottom: o.b.y + o.b.height })),
        { id: movingObj.id, left: moving.left, right: moving.right, top: moving.top, bottom: moving.bottom }
      ].sort((a, b) => a.left - b.left);

      const gaps: number[] = [];
      for (let i = 0; i < allSorted.length - 1; i++) {
        gaps.push(allSorted[i + 1].left - allSorted[i].right);
      }

      if (gaps.length >= 2) {
        const avgGap = gaps.reduce((a, b) => a + b, 0) / gaps.length;
        const maxDev = Math.max(...gaps.map(g => Math.abs(g - avgGap)));

        if (maxDev < 2) { // Snap strength
          const minY = Math.min(...allSorted.map(o => o.top));
          const maxY = Math.max(...allSorted.map(o => o.bottom));
          const midY = (minY + maxY) / 2;

          guides.push({
            type: "horizontal",
            position: midY,
            start: allSorted[0].left,
            end: allSorted[allSorted.length - 1].right,
            alignment: "centerY",
            isEquidistant: true,
            distance: Math.round(avgGap),
            objectBounds: allSorted.map(o => ({ id: o.id, start: o.left, end: o.right, isMoving: o.id === movingObj.id }))
          });
        }
      }
    }

    // Vertical gaps
    const verticallyAligned = allObjects
      .map((obj) => {
        const b = getBounds(obj);
        if (!b) return null;
        return { obj, b, centerX: b.x + b.width / 2 };
      })
      .filter((item) => item && Math.abs(item.centerX - moving.centerX) < 50);

    if (verticallyAligned.length >= 2) {
      const allSorted = [
        ...verticallyAligned.map(o => ({ id: o.obj.id, top: o.b.y, bottom: o.b.y + o.b.height, left: o.b.x, right: o.b.x + o.b.width })),
        { id: movingObj.id, top: moving.top, bottom: moving.bottom, left: moving.left, right: moving.right }
      ].sort((a, b) => a.top - b.top);

      const gaps: number[] = [];
      for (let i = 0; i < allSorted.length - 1; i++) {
        gaps.push(allSorted[i + 1].top - allSorted[i].bottom);
      }

      if (gaps.length >= 2) {
        const avgGap = gaps.reduce((a, b) => a + b, 0) / gaps.length;
        const maxDev = Math.max(...gaps.map(g => Math.abs(g - avgGap)));

        if (maxDev < 2) {
          const minX = Math.min(...allSorted.map(o => o.left));
          const maxX = Math.max(...allSorted.map(o => o.right));
          const midX = (minX + maxX) / 2;

          guides.push({
            type: "vertical",
            position: midX,
            start: allSorted[0].top,
            end: allSorted[allSorted.length - 1].bottom,
            alignment: "centerX",
            isEquidistant: true,
            distance: Math.round(avgGap),
            objectBounds: allSorted.map(o => ({ id: o.id, start: o.top, end: o.bottom, isMoving: o.id === movingObj.id }))
          });
        }
      }
    }

    return guides;
  };

  const filterBestGuides = (
    guides: LocalAlignmentGuide[]
  ): LocalAlignmentGuide[] => {
    const verticalGuides = guides.filter((g) => g.type === "vertical");
    const horizontalGuides = guides.filter((g) => g.type === "horizontal");

    const filterByType = (guidesArray: LocalAlignmentGuide[]) => {
      const grouped = new Map<string, LocalAlignmentGuide[]>();
      guidesArray.forEach((guide) => {
        const key = guide.alignment;
        if (!grouped.has(key)) {
          grouped.set(key, []);
        }
        grouped.get(key)!.push(guide);
      });

      const filtered: LocalAlignmentGuide[] = [];
      grouped.forEach((guidesOfType) => {
        if (guidesOfType.length === 0) return;

        const sorted = guidesOfType.sort((a, b) => {
          const distA = a.distance ?? 0;
          const distB = b.distance ?? 0;
          return distA - distB;
        });

        filtered.push(sorted[0]);
      });

      return filtered;
    };

    const allFiltered = [
      ...filterByType(verticalGuides),
      ...filterByType(horizontalGuides),
    ];

    const finalVertical = allFiltered
      .filter((g) => g.type === "vertical")
      .slice(0, 2);
    const finalHorizontal = allFiltered
      .filter((g) => g.type === "horizontal")
      .slice(0, 2);

    return [...finalVertical, ...finalHorizontal];
  };

  const calculateLocalGuides = (
    movingObj: CanvasObject,
    canvasWidth: number,
    canvasHeight: number,
    providedMovingBounds?: any // NEW
  ): LocalAlignmentGuide[] => {
    const guides: LocalAlignmentGuide[] = [];

    if (!movingObj) {
      alignedElements.value = [];
      return guides;
    }

    const movingBounds = providedMovingBounds || getBounds(movingObj);
    if (!movingBounds) {
      alignedElements.value = [];
      return guides;
    }

    const alignedEdgesMap = new Map<string, Set<string>>();

    const trackAlignment = (objId: string, alignment: string) => {
      if (!alignedEdgesMap.has(objId)) {
        alignedEdgesMap.set(objId, new Set());
      }
      alignedEdgesMap.get(objId)!.add(alignment);
    };

    const moving = {
      left: movingBounds.x,
      right: movingBounds.x + movingBounds.width,
      top: movingBounds.y,
      bottom: movingBounds.y + movingBounds.height,
      centerX: movingBounds.x + movingBounds.width / 2,
      centerY: movingBounds.y + movingBounds.height / 2,
    };

    const nearbyObjects = findNearbyObjects(movingObj);

    // ✅ NEW: Check for equidistant spacing guides FIRST
    const equidistantGuides = generateEquidistantGuides(
      movingObj,
      movingBounds
    );
    if (equidistantGuides.length > 0) {
      guides.push(...equidistantGuides);
    }

    const verticalAlignments = new Map<string, any>();
    const horizontalAlignments = new Map<string, any>();

    for (const targetObj of nearbyObjects) {
      const targetBounds = getBounds(targetObj);
      if (!targetBounds) continue;

      const target = {
        left: targetBounds.x,
        right: targetBounds.x + targetBounds.width,
        top: targetBounds.y,
        bottom: targetBounds.y + targetBounds.height,
        centerX: targetBounds.x + targetBounds.width / 2,
        centerY: targetBounds.y + targetBounds.height / 2,
      };

      const verticalChecks = [
        { movingPos: moving.left, targetPos: target.left, align: "left" },
        { movingPos: moving.right, targetPos: target.right, align: "right" },
        {
          movingPos: moving.centerX,
          targetPos: target.centerX,
          align: "centerX",
        },
      ];

      for (const { movingPos, targetPos, align } of verticalChecks) {
        const distance = Math.abs(movingPos - targetPos);

        if (distance > SNAP_THRESHOLD && distance < MIN_DISTANCE_TO_SHOW) {
          continue;
        }

        const posKey = `${align}-${targetObj.id}`;

        if (distance <= SNAP_THRESHOLD) {
          trackAlignment(targetObj.id, align);
          trackAlignment(movingObj.id, align);
        }

        if (!verticalAlignments.has(posKey)) {
          verticalAlignments.set(posKey, {
            position: targetPos,
            alignment: align,
            objects: [targetObj],
            minY: Math.min(moving.top, target.top),
            maxY: Math.max(moving.bottom, target.bottom),
            distance: Math.round(distance),
            targetObjectId: targetObj.id,
          });
        }
      }

      const horizontalChecks = [
        { movingPos: moving.top, targetPos: target.top, align: "top" },
        { movingPos: moving.bottom, targetPos: target.bottom, align: "bottom" },
        {
          movingPos: moving.centerY,
          targetPos: target.centerY,
          align: "centerY",
        },
      ];

      for (const { movingPos, targetPos, align } of horizontalChecks) {
        const distance = Math.abs(movingPos - targetPos);

        if (distance > SNAP_THRESHOLD && distance < MIN_DISTANCE_TO_SHOW) {
          continue;
        }

        const posKey = `${align}-${targetObj.id}`;

        if (distance <= SNAP_THRESHOLD) {
          trackAlignment(targetObj.id, align);
          trackAlignment(movingObj.id, align);
        }

        if (!horizontalAlignments.has(posKey)) {
          horizontalAlignments.set(posKey, {
            position: targetPos,
            alignment: align,
            objects: [targetObj],
            minX: Math.min(moving.left, target.left),
            maxX: Math.max(moving.right, target.right),
            distance: Math.round(distance),
            targetObjectId: targetObj.id,
          });
        }
      }
    }

    const allGuides: LocalAlignmentGuide[] = [];

    verticalAlignments.forEach((data) => {
      const alignedObjects: Array<{ id: string; position: number }> = [];
      const objectBounds: Array<{
        id: string;
        start: number;
        end: number;
        isMoving?: boolean;
      }> = [];

      store.objects.forEach((obj) => {
        if (obj.id === movingObj.id || obj.isLocked || obj.isVisible === false)
          return;
        const bounds = getBounds(obj);
        if (!bounds) return;

        if (
          data.alignment === "left" &&
          Math.abs(bounds.x - data.position) <= SNAP_THRESHOLD
        ) {
          alignedObjects.push({ id: obj.id, position: bounds.x });
          objectBounds.push({
            id: obj.id,
            start: bounds.y,
            end: bounds.y + bounds.height,
            isMoving: false,
          });
        } else if (
          data.alignment === "right" &&
          Math.abs(bounds.x + bounds.width - data.position) <= SNAP_THRESHOLD
        ) {
          alignedObjects.push({
            id: obj.id,
            position: bounds.x + bounds.width,
          });
          objectBounds.push({
            id: obj.id,
            start: bounds.y,
            end: bounds.y + bounds.height,
            isMoving: false,
          });
        } else if (
          data.alignment === "centerX" &&
          Math.abs(bounds.x + bounds.width / 2 - data.position) <=
            SNAP_THRESHOLD
        ) {
          alignedObjects.push({
            id: obj.id,
            position: bounds.x + bounds.width / 2,
          });
          objectBounds.push({
            id: obj.id,
            start: bounds.y,
            end: bounds.y + bounds.height,
            isMoving: false,
          });
        }
      });

      const isMultiAlign = alignedObjects.length > 1;
      const alignedCount = alignedObjects.length;

      const movingBounds = getBounds(movingObj);
      if (movingBounds && objectBounds.length > 0) {
        objectBounds.push({
          id: movingObj.id,
          start: movingBounds.y,
          end: movingBounds.y + movingBounds.height,
          isMoving: true,
        });
      }

      allGuides.push({
        type: "vertical" as const,
        position: data.position,
        start: data.minY - GUIDE_PADDING,
        end: data.maxY + GUIDE_PADDING,
        alignment: data.alignment as any,
        isMultiAlign,
        alignedCount: isMultiAlign ? alignedCount : undefined,
        distance: data.distance,
        targetObjectId: data.targetObjectId,
        alignedObjects: alignedObjects.length > 0 ? alignedObjects : undefined,
        objectBounds: objectBounds.length > 1 ? objectBounds : undefined,
      });
    });

    horizontalAlignments.forEach((data) => {
      const alignedObjects: Array<{ id: string; position: number }> = [];
      const objectBounds: Array<{
        id: string;
        start: number;
        end: number;
        isMoving?: boolean;
      }> = [];

      store.objects.forEach((obj) => {
        if (obj.id === movingObj.id || obj.isLocked || obj.isVisible === false)
          return;
        const bounds = getBounds(obj);
        if (!bounds) return;

        if (
          data.alignment === "top" &&
          Math.abs(bounds.y - data.position) <= SNAP_THRESHOLD
        ) {
          alignedObjects.push({ id: obj.id, position: bounds.y });
          objectBounds.push({
            id: obj.id,
            start: bounds.x,
            end: bounds.x + bounds.width,
            isMoving: false,
          });
        } else if (
          data.alignment === "bottom" &&
          Math.abs(bounds.y + bounds.height - data.position) <= SNAP_THRESHOLD
        ) {
          alignedObjects.push({
            id: obj.id,
            position: bounds.y + bounds.height,
          });
          objectBounds.push({
            id: obj.id,
            start: bounds.x,
            end: bounds.x + bounds.width,
            isMoving: false,
          });
        } else if (
          data.alignment === "centerY" &&
          Math.abs(bounds.y + bounds.height / 2 - data.position) <=
            SNAP_THRESHOLD
        ) {
          alignedObjects.push({
            id: obj.id,
            position: bounds.y + bounds.height / 2,
          });
          objectBounds.push({
            id: obj.id,
            start: bounds.x,
            end: bounds.x + bounds.width,
            isMoving: false,
          });
        }
      });

      const isMultiAlign = alignedObjects.length > 1;
      const alignedCount = alignedObjects.length;

      const movingBounds = getBounds(movingObj);
      if (movingBounds && objectBounds.length > 0) {
        objectBounds.push({
          id: movingObj.id,
          start: movingBounds.x,
          end: movingBounds.x + movingBounds.width,
          isMoving: true,
        });
      }

      allGuides.push({
        type: "horizontal" as const,
        position: data.position,
        start: data.minX - GUIDE_PADDING,
        end: data.maxX + GUIDE_PADDING,
        alignment: data.alignment as any,
        isMultiAlign,
        alignedCount: isMultiAlign ? alignedCount : undefined,
        distance: data.distance,
        targetObjectId: data.targetObjectId,
        alignedObjects: alignedObjects.length > 0 ? alignedObjects : undefined,
        objectBounds: objectBounds.length > 1 ? objectBounds : undefined,
      });
    });

    const filteredGuides = filterBestGuides(allGuides);

    alignedElements.value = Array.from(alignedEdgesMap.entries()).map(
      ([id, edges]) => ({
        id,
        edges: Array.from(edges) as AlignedElementData["edges"],
      })
    );

    return [...guides, ...filteredGuides];
  };

  const calculateFullScreenGuides = (
    movingObj: CanvasObject,
    canvasWidth: number,
    canvasHeight: number,
    zoom: number,
    offset: Point,
    providedMovingBounds?: any // NEW
  ): LocalAlignmentGuide[] => {
    const guides: LocalAlignmentGuide[] = [];
    const movingBounds = providedMovingBounds || getBounds(movingObj);
    if (!movingBounds) return guides;

    const viewportWorldBounds = {
      centerX: offset.x + canvasWidth / (2 * zoom),
      centerY: offset.y + canvasHeight / (2 * zoom),
      top: offset.y,
      bottom: offset.y + canvasHeight / zoom,
      left: offset.x,
      right: offset.x + canvasWidth / zoom,
    };

    const moving = {
      centerX: movingBounds.x + movingBounds.width / 2,
      centerY: movingBounds.y + movingBounds.height / 2,
    };

    if (
      Math.abs(moving.centerX - viewportWorldBounds.centerX) < SNAP_THRESHOLD
    ) {
      guides.push({
        type: "vertical",
        position: viewportWorldBounds.centerX,
        start: viewportWorldBounds.top - 10000,
        end: viewportWorldBounds.bottom + 10000,
        alignment: "centerX",
        isMultiAlign: false,
        isFullScreen: true,
      });
    }

    if (
      Math.abs(moving.centerY - viewportWorldBounds.centerY) < SNAP_THRESHOLD
    ) {
      guides.push({
        type: "horizontal",
        position: viewportWorldBounds.centerY,
        start: viewportWorldBounds.left - 10000,
        end: viewportWorldBounds.right + 10000,
        alignment: "centerY",
        isMultiAlign: false,
        isFullScreen: true,
      });
    }

    return guides;
  };

  const calculateSnapAdjustments = (
    obj: CanvasObject,
    currentWorldPos: Point
  ): Point => {
    if (!obj || !dragState.value.startPoint) {
      return { x: 0, y: 0 };
    }

    const initialPos = dragState.value.initialPositions.get(obj.id);
    if (!initialPos) return { x: 0, y: 0 };

    const bounds = getBounds(obj);
    if (!bounds) return { x: 0, y: 0 };

    const deltaFromStart = {
      x: currentWorldPos.x - dragState.value.startPoint!.x,
      y: currentWorldPos.y - dragState.value.startPoint!.y,
    };

    const moving = {
      left: initialPos[0].x + deltaFromStart.x,
      right: initialPos[0].x + bounds.width + deltaFromStart.x,
      top: initialPos[0].y + deltaFromStart.y,
      bottom: initialPos[0].y + bounds.height + deltaFromStart.y,
      centerX: initialPos[0].x + bounds.width / 2 + deltaFromStart.x,
      centerY: initialPos[0].y + bounds.height / 2 + deltaFromStart.y,
    };

    let snapX = 0;
    let snapY = 0;
    let minDistX = SNAP_THRESHOLD;
    let minDistY = SNAP_THRESHOLD;

    // ✅ NEW: Priority 1 - Check equidistant spacing snap FIRST
    const movingBounds = {
      x: initialPos[0].x + deltaFromStart.x,
      y: initialPos[0].y + deltaFromStart.y,
      width: bounds.width,
      height: bounds.height,
    };

    const equidistantSnap = detectEquidistantSpacing(obj, movingBounds);
    if (Math.abs(equidistantSnap.snapX) > 0.1) {
      snapX = equidistantSnap.snapX;
      minDistX = 0; // Override edge snapping
    }
    if (Math.abs(equidistantSnap.snapY) > 0.1) {
      snapY = equidistantSnap.snapY;
      minDistY = 0; // Override edge snapping
    }

    // Priority 2 - Regular edge/center snapping (only if equidistant didn't snap)
    if (minDistX > 0 || minDistY > 0) {
      const nearbyObjects = findNearbyObjects(obj);

      for (const targetObj of nearbyObjects) {
        const targetBounds = getBounds(targetObj);
        if (!targetBounds) continue;

        const target = {
          left: targetBounds.x,
          right: targetBounds.x + targetBounds.width,
          top: targetBounds.y,
          bottom: targetBounds.y + targetBounds.height,
          centerX: targetBounds.x + targetBounds.width / 2,
          centerY: targetBounds.y + targetBounds.height / 2,
        };

        if (minDistX > 0) {
          const xChecks = [
            { movingPos: moving.left, targetPos: target.left },
            { movingPos: moving.right, targetPos: target.right },
            { movingPos: moving.centerX, targetPos: target.centerX },
          ];

          // ✅ NEW: Adjacency snapping for rectangles/booths (Left-Right, Right-Left)
          if (
            ["rectangle", "booth"].includes(obj.type) &&
            ["rectangle", "booth"].includes(targetObj.type)
          ) {
            xChecks.push(
              { movingPos: moving.left, targetPos: target.right },
              { movingPos: moving.right, targetPos: target.left }
            );
          }

          for (const { movingPos, targetPos } of xChecks) {
            const dist = Math.abs(movingPos - targetPos);
            if (dist < minDistX) {
              snapX = targetPos - movingPos;
              minDistX = dist;
            }
          }
        }

        if (minDistY > 0) {
          const yChecks = [
            { movingPos: moving.top, targetPos: target.top },
            { movingPos: moving.bottom, targetPos: target.bottom },
            { movingPos: moving.centerY, targetPos: target.centerY },
          ];

          // ✅ NEW: Adjacency snapping for rectangles/booths (Top-Bottom, Bottom-Top)
          if (
            ["rectangle", "booth"].includes(obj.type) &&
            ["rectangle", "booth"].includes(targetObj.type)
          ) {
            yChecks.push(
              { movingPos: moving.top, targetPos: target.bottom },
              { movingPos: moving.bottom, targetPos: target.top }
            );
          }

          for (const { movingPos, targetPos } of yChecks) {
            const dist = Math.abs(movingPos - targetPos);
            if (dist < minDistY) {
              snapY = targetPos - movingPos;
              minDistY = dist;
            }
          }
        }
      }
    }

    return { x: snapX, y: snapY };
  };

  const startDrag = (worldPoint: Point, objects: CanvasObject[]) => {
    if (!objects || !Array.isArray(objects)) {
      return;
    }

    const draggable = objects.filter(
      (obj) => obj && !obj.isLocked && obj.isVisible !== false
    );
    if (draggable.length === 0) return;

    const initialPositions = new Map<string, Point[]>();
    const initialBoundingBoxes = new Map<string, { x: number; y: number; width: number; height: number }>();
    draggable.forEach((obj) => {
      if (!obj) return;

      if (obj.elementData) {
        initialPositions.set(obj.id, [{ ...obj.elementData.position }]);
      } else if (obj.points && Array.isArray(obj.points)) {
        initialPositions.set(
          obj.id,
          obj.points.map((p) => ({ ...p }))
        );
      }

      if (obj.boundingBox) {
        initialBoundingBoxes.set(obj.id, { ...obj.boundingBox });
      }
    });

    dragState.value = {
      isActive: true,
      startPoint: { ...worldPoint },
      draggedObjects: draggable,
      initialPositions,
      initialBoundingBoxes,
    };

    localGuides.value = [];
    alignedElements.value = [];
    boundsCache.clear();
  };

  const updateDrag = (
    worldPoint: Point,
    canvasWidth?: number,
    canvasHeight?: number,
    zoom?: number,
    offset?: Point,
    callback?: () => void
  ) => {
    if (!dragState.value.isActive || !dragState.value.startPoint) return;

    latestArgs.value = { worldPoint, canvasWidth, canvasHeight, zoom, offset, callback };
    if (isUpdatePending.value) return;

    isUpdatePending.value = true;
    requestAnimationFrame(() => {
      const args = latestArgs.value;
      if (!args || !dragState.value.isActive) {
        isUpdatePending.value = false;
        return;
      }

      const { worldPoint: wp, canvasWidth: cw, canvasHeight: ch, zoom: z, offset: off } = args;
      const totalDeltaX = wp.x - dragState.value.startPoint!.x;
      const totalDeltaY = wp.y - dragState.value.startPoint!.y;

      dragState.value.draggedObjects.forEach((obj) => {
        if (!obj || obj.isLocked) return;

        const bounds = getBounds(obj);
        if (!bounds) return;

        const initialPos = dragState.value.initialPositions.get(obj.id);
        if (!initialPos) return;

        let finalDeltaX = totalDeltaX;
        let finalDeltaY = totalDeltaY;

        if (dragState.value.draggedObjects.length === 1) {
          const snap = calculateSnapAdjustments(obj, wp);
          finalDeltaX += snap.x;
          finalDeltaY += snap.y;

          if (cw && ch) {
            // ✅ IMPROVED: Pass snapped bounds for precise guide rendering
            const snappedBounds = {
              x: initialPos[0].x + finalDeltaX,
              y: initialPos[0].y + finalDeltaY,
              width: bounds.width,
              height: bounds.height,
            };

            const localGuidesArray = calculateLocalGuides(
              obj,
              cw,
              ch,
              snappedBounds
            );
            const fullScreenGuidesArray =
              cw && ch && z && off
                ? calculateFullScreenGuides(obj, cw, ch, z, off, snappedBounds)
                : [];
            localGuides.value = [...localGuidesArray, ...fullScreenGuidesArray];
          }
        }

        if (obj.elementData) {
          const oldX = obj.elementData.position.x;
          const oldY = obj.elementData.position.y;
          obj.elementData.position.x = initialPos[0].x + finalDeltaX;
          obj.elementData.position.y = initialPos[0].y + finalDeltaY;
          console.log(`📝 Updated DOM element ${obj.id}: (${oldX.toFixed(1)}, ${oldY.toFixed(1)}) → (${obj.elementData.position.x.toFixed(1)}, ${obj.elementData.position.y.toFixed(1)})`);
        } else if (obj.points && Array.isArray(obj.points)) {
          // ✅ OPTIMIZATION: Use raw data for the tight point-update loop
          // to bypass Vue's deep reactivity overhead, which is huge for objects with many points (pencils).
          const rawPoints = toRaw(obj.points);
          const initialPoints = initialPos;
          
          for (let i = 0; i < initialPoints.length; i++) {
            if (rawPoints[i]) {
              rawPoints[i].x = initialPoints[i].x + finalDeltaX;
              rawPoints[i].y = initialPoints[i].y + finalDeltaY;
            }
          }

          if (obj.boundingBox) {
            const initialBox = dragState.value.initialBoundingBoxes.get(obj.id);
            if (initialBox) {
              obj.boundingBox.x = initialBox.x + finalDeltaX;
              obj.boundingBox.y = initialBox.y + finalDeltaY;
            }
          }

          if (obj.type === "booth") {
            syncBoothDrag(obj.id, obj.points);
          }
        }
      });
      
      if (args.callback) {
        args.callback();
      }
      
      isUpdatePending.value = false;
    });
  };

  const stopDrag = () => {
    dragState.value.isActive = false;
    dragState.value.startPoint = null;
    dragState.value.draggedObjects = [];
    dragState.value.initialPositions.clear();

    setTimeout(() => {
      localGuides.value = [];
      alignedElements.value = [];
      boundsCache.clear();
    }, 150);
  };

  const updateDraggedObjectsDuringDrag = (newObjects: CanvasObject[]) => {
    if (!dragState.value.isActive) return;

    const initialPositions = new Map<string, Point[]>();
    const initialBoundingBoxes = new Map<string, { x: number; y: number; width: number; height: number }>();

    newObjects.forEach((obj) => {
      if (!obj) return;

      if (obj.elementData) {
        initialPositions.set(obj.id, [{ ...obj.elementData.position }]);
      } else if (obj.points && Array.isArray(obj.points)) {
        initialPositions.set(
          obj.id,
          obj.points.map((p) => ({ ...p }))
        );
      }

      if (obj.boundingBox) {
        initialBoundingBoxes.set(obj.id, { ...obj.boundingBox });
      }
    });

    dragState.value.draggedObjects = newObjects;
    dragState.value.initialPositions = initialPositions;
    dragState.value.initialBoundingBoxes = initialBoundingBoxes;
  };

  const moveByKeyboard = (dx: number, dy: number) => {
    if (!store.selectedObjects || !Array.isArray(store.selectedObjects)) {
      return;
    }

    const objects = store.selectedObjects.filter((obj) => obj && !obj.isLocked);
    if (objects.length === 0) return;

    objects.forEach((obj) => {
      if (!obj) return;

      if (obj.elementData) {
        const newPosition = {
          x: obj.elementData.position.x + dx,
          y: obj.elementData.position.y + dy,
        };
        obj.elementData.position = newPosition;
      } else if (obj.points && Array.isArray(obj.points)) {
        const newPoints = obj.points.map((p) => ({
          x: p.x + dx,
          y: p.y + dy,
        }));
        obj.points = newPoints;

        if (obj.boundingBox) {
          obj.boundingBox.x += dx;
          obj.boundingBox.y += dy;
        }

        if (obj.type === "booth") {
          syncBoothDrag(obj.id, newPoints);
        }
      }
    });

    setTimeout(() => {
      localGuides.value = [];
      alignedElements.value = [];
    }, 150);
  };

  const calculateResizeGuides = (
    resizingObj: CanvasObject,
    canvasWidth?: number,
    canvasHeight?: number,
    zoom?: number,
    offset?: Point
  ) => {
    if (!canvasWidth || !canvasHeight) {
      localGuides.value = [];
      alignedElements.value = [];
      return;
    }

    const localGuidesArray = calculateLocalGuides(
      resizingObj,
      canvasWidth,
      canvasHeight
    );
    const fullScreenGuidesArray =
      zoom && offset
        ? calculateFullScreenGuides(
            resizingObj,
            canvasWidth,
            canvasHeight,
            zoom,
            offset
          )
        : [];

    localGuides.value = [...localGuidesArray, ...fullScreenGuidesArray];
  };

  return {
    isDragging: computed(() => dragState.value.isActive),
    localGuides: computed(() => localGuides.value),
    alignedElements: computed(() => alignedElements.value),
    startDrag,
    updateDrag,
    stopDrag,
    moveByKeyboard,
    calculateResizeGuides,
    clearGuides: () => {
      localGuides.value = [];
      alignedElements.value = [];
      boundsCache.clear();
    },
  };
}
