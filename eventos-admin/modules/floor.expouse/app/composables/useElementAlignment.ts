// composables/useElementAlignment.ts - COMPLETE WITH EQUIDISTANT SPACING
import { ref, computed } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import type { CanvasObject, Point, DomElement } from "@floorplan/types/canvas";

export interface ElementAlignmentGuide {
  type: "vertical" | "horizontal";
  position: number;
  start: number;
  end: number;
  sourceElement: DomElement;
  targetElement?: DomElement | CanvasObject;
  alignment: "left" | "right" | "top" | "bottom" | "centerX" | "centerY";
  isFullScreen?: boolean;
  isMultiAlign?: boolean;
  alignedCount?: number;
  distance?: number;
  isEquidistant?: boolean; // NEW
  alignedObjects?: Array<{ id: string; position: number }>;
  objectBounds?: Array<{
    id: string;
    start: number;
    end: number;
    isMoving?: boolean;
  }>;
}

export function useElementAlignment() {
  const store = useCanvasStore();
  const alignmentGuides = ref<ElementAlignmentGuide[]>([]);

  const ALIGNMENT_THRESHOLD = 10;
  const SNAP_THRESHOLD = 8;
  const EQUIDISTANT_TOLERANCE = 15; // NEW: For equal spacing detection
  const GUIDE_PADDING = 30;

  const getElementBounds = (element: DomElement) => {
    return {
      x: element.position.x,
      y: element.position.y,
      width: element.size.width,
      height: element.size.height,
    };
  };

  const getObjectBounds = (obj: CanvasObject) => {
    if (obj.elementData) {
      return {
        x: obj.elementData.position.x,
        y: obj.elementData.position.y,
        width: obj.elementData.size.width,
        height: obj.elementData.size.height,
      };
    }

    if (obj.points.length >= 2) {
      const xs = obj.points.map((p) => p.x);
      const ys = obj.points.map((p) => p.y);
      const minX = Math.min(...xs);
      const maxX = Math.max(...xs);
      const minY = Math.min(...ys);
      const maxY = Math.max(...ys);

      return {
        x: minX,
        y: minY,
        width: maxX - minX,
        height: maxY - minY,
      };
    }

    return null;
  };

  // ✅ NEW: Detect equidistant spacing for DOM elements
  const detectEquidistantSpacing = (
    movingElement: DomElement,
    movingBounds: any
  ): { snapX: number; snapY: number } => {
    let snapX = 0;
    let snapY = 0;

    const moving = {
      centerX: movingBounds.x + movingBounds.width / 2,
      centerY: movingBounds.y + movingBounds.height / 2,
    };

    // Collect all other elements (DOM + canvas objects)
    const allTargets: Array<{ id: string; centerX: number; centerY: number }> =
      [];

    // Add DOM elements
    store.domElements
      .filter(
        (el) =>
          el.id !== movingElement.id && !el.isLocked && el.isVisible !== false
      )
      .forEach((el) => {
        const bounds = getElementBounds(el);
        allTargets.push({
          id: el.id,
          centerX: bounds.x + bounds.width / 2,
          centerY: bounds.y + bounds.height / 2,
        });
      });

    // Add canvas objects
    store.objects
      .filter((obj) => !obj.isLocked && obj.isVisible !== false)
      .forEach((obj) => {
        const bounds = getObjectBounds(obj);
        if (bounds) {
          allTargets.push({
            id: obj.id,
            centerX: bounds.x + bounds.width / 2,
            centerY: bounds.y + bounds.height / 2,
          });
        }
      });

    // Check horizontal equidistant spacing
    const horizontallyAligned = allTargets.filter(
      (item) => Math.abs(item.centerY - moving.centerY) < 50
    );

    if (horizontallyAligned.length >= 2) {
      const allCenters = [
        ...horizontallyAligned.map((o) => o.centerX),
        moving.centerX,
      ].sort((a, b) => a - b);

      const gaps: number[] = [];
      for (let i = 0; i < allCenters.length - 1; i++) {
        gaps.push(allCenters[i + 1] - allCenters[i]);
      }

      if (gaps.length >= 2) {
        const avgGap = gaps.reduce((a, b) => a + b, 0) / gaps.length;
        const maxDeviation = Math.max(...gaps.map((g) => Math.abs(g - avgGap)));

        if (maxDeviation < EQUIDISTANT_TOLERANCE) {
          const movingIndex = allCenters.indexOf(moving.centerX);
          if (movingIndex > 0) {
            const expectedX = allCenters[0] + avgGap * movingIndex;
            snapX = expectedX - moving.centerX;
          }
        }
      }
    }

    // Check vertical equidistant spacing
    const verticallyAligned = allTargets.filter(
      (item) => Math.abs(item.centerX - moving.centerX) < 50
    );

    if (verticallyAligned.length >= 2) {
      const allCenters = [
        ...verticallyAligned.map((o) => o.centerY),
        moving.centerY,
      ].sort((a, b) => a - b);

      const gaps: number[] = [];
      for (let i = 0; i < allCenters.length - 1; i++) {
        gaps.push(allCenters[i + 1] - allCenters[i]);
      }

      if (gaps.length >= 2) {
        const avgGap = gaps.reduce((a, b) => a + b, 0) / gaps.length;
        const maxDeviation = Math.max(...gaps.map((g) => Math.abs(g - avgGap)));

        if (maxDeviation < EQUIDISTANT_TOLERANCE) {
          const movingIndex = allCenters.indexOf(moving.centerY);
          if (movingIndex > 0) {
            const expectedY = allCenters[0] + avgGap * movingIndex;
            snapY = expectedY - moving.centerY;
          }
        }
      }
    }

    return { snapX, snapY };
  };

  // ✅ NEW: Generate equidistant spacing guides
  const generateEquidistantGuides = (
    movingElement: DomElement,
    movingBounds: any
  ): ElementAlignmentGuide[] => {
    const guides: ElementAlignmentGuide[] = [];

    const moving = {
      centerX: movingBounds.x + movingBounds.width / 2,
      centerY: movingBounds.y + movingBounds.height / 2,
    };

    // Collect all targets with their bounds
    const allTargets: Array<{
      id: string;
      centerX: number;
      centerY: number;
      bounds: any;
    }> = [];

    store.domElements
      .filter(
        (el) =>
          el.id !== movingElement.id && !el.isLocked && el.isVisible !== false
      )
      .forEach((el) => {
        const bounds = getElementBounds(el);
        allTargets.push({
          id: el.id,
          centerX: bounds.x + bounds.width / 2,
          centerY: bounds.y + bounds.height / 2,
          bounds,
        });
      });

    store.objects
      .filter((obj) => !obj.isLocked && obj.isVisible !== false)
      .forEach((obj) => {
        const bounds = getObjectBounds(obj);
        if (bounds) {
          allTargets.push({
            id: obj.id,
            centerX: bounds.x + bounds.width / 2,
            centerY: bounds.y + bounds.height / 2,
            bounds,
          });
        }
      });

    // Horizontal equidistant guides
    const horizontallyAligned = allTargets.filter(
      (item) => Math.abs(item.centerY - moving.centerY) < 50
    );

    if (horizontallyAligned.length >= 2) {
      const allItems = [
        ...horizontallyAligned.map((o) => ({
          centerX: o.centerX,
          centerY: o.centerY,
          id: o.id,
        })),
        {
          centerX: moving.centerX,
          centerY: moving.centerY,
          id: movingElement.id,
        },
      ].sort((a, b) => a.centerX - b.centerX);

      const gaps: number[] = [];
      for (let i = 0; i < allItems.length - 1; i++) {
        gaps.push(allItems[i + 1].centerX - allItems[i].centerX);
      }

      if (gaps.length >= 2) {
        const avgGap = gaps.reduce((a, b) => a + b, 0) / gaps.length;
        const maxDeviation = Math.max(...gaps.map((g) => Math.abs(g - avgGap)));

        if (maxDeviation < EQUIDISTANT_TOLERANCE) {
          const minY = Math.min(...allItems.map((o) => o.centerY));
          const maxY = Math.max(...allItems.map((o) => o.centerY));

          for (let i = 0; i < allItems.length - 1; i++) {
            const item1 = allItems[i];
            const item2 = allItems[i + 1];
            const midX = (item1.centerX + item2.centerX) / 2;

            guides.push({
              type: "vertical",
              position: midX,
              start: minY - GUIDE_PADDING,
              end: maxY + GUIDE_PADDING,
              sourceElement: movingElement,
              alignment: "centerX",
              isMultiAlign: true,
              alignedCount: allItems.length,
              distance: Math.round(avgGap),
              isEquidistant: true,
            });
          }
        }
      }
    }

    // Vertical equidistant guides
    const verticallyAligned = allTargets.filter(
      (item) => Math.abs(item.centerX - moving.centerX) < 50
    );

    if (verticallyAligned.length >= 2) {
      const allItems = [
        ...verticallyAligned.map((o) => ({
          centerX: o.centerX,
          centerY: o.centerY,
          id: o.id,
        })),
        {
          centerX: moving.centerX,
          centerY: moving.centerY,
          id: movingElement.id,
        },
      ].sort((a, b) => a.centerY - b.centerY);

      const gaps: number[] = [];
      for (let i = 0; i < allItems.length - 1; i++) {
        gaps.push(allItems[i + 1].centerY - allItems[i].centerY);
      }

      if (gaps.length >= 2) {
        const avgGap = gaps.reduce((a, b) => a + b, 0) / gaps.length;
        const maxDeviation = Math.max(...gaps.map((g) => Math.abs(g - avgGap)));

        if (maxDeviation < EQUIDISTANT_TOLERANCE) {
          const minX = Math.min(...allItems.map((o) => o.centerX));
          const maxX = Math.max(...allItems.map((o) => o.centerX));

          for (let i = 0; i < allItems.length - 1; i++) {
            const item1 = allItems[i];
            const item2 = allItems[i + 1];
            const midY = (item1.centerY + item2.centerY) / 2;

            guides.push({
              type: "horizontal",
              position: midY,
              start: minX - GUIDE_PADDING,
              end: maxX + GUIDE_PADDING,
              sourceElement: movingElement,
              alignment: "centerY",
              isMultiAlign: true,
              alignedCount: allItems.length,
              distance: Math.round(avgGap),
              isEquidistant: true,
            });
          }
        }
      }
    }

    return guides;
  };

  const detectAlignmentGuides = (
    movingElement: DomElement
  ): ElementAlignmentGuide[] => {
    const guides: ElementAlignmentGuide[] = [];
    if (!movingElement) return guides;

    const movingBounds = getElementBounds(movingElement);
    if (!movingBounds) return guides;

    const moving = {
      left: movingBounds.x,
      right: movingBounds.x + movingBounds.width,
      top: movingBounds.y,
      bottom: movingBounds.y + movingBounds.height,
      centerX: movingBounds.x + movingBounds.width / 2,
      centerY: movingBounds.y + movingBounds.height / 2,
    };

    // ✅ NEW: Check for equidistant spacing guides FIRST
    const equidistantGuides = generateEquidistantGuides(
      movingElement,
      movingBounds
    );
    if (equidistantGuides.length > 0) {
      guides.push(...equidistantGuides);
    }

    const vertical = new Map<number, ElementAlignmentGuide>();
    const horizontal = new Map<number, ElementAlignmentGuide>();

    // Full-screen viewport center guides
    const canvasElement = document.querySelector("canvas");
    if (canvasElement) {
      const rect = canvasElement.getBoundingClientRect();
      const viewportCenter = {
        x: store.offset.x + rect.width / (2 * store.zoom),
        y: store.offset.y + rect.height / (2 * store.zoom),
      };

      if (Math.abs(moving.centerX - viewportCenter.x) < ALIGNMENT_THRESHOLD) {
        vertical.set(viewportCenter.x, {
          type: "vertical",
          position: viewportCenter.x,
          start: store.offset.y - 10000,
          end: store.offset.y + rect.height / store.zoom + 10000,
          sourceElement: movingElement,
          alignment: "centerX",
          isFullScreen: true,
        });
      }

      if (Math.abs(moving.centerY - viewportCenter.y) < ALIGNMENT_THRESHOLD) {
        horizontal.set(viewportCenter.y, {
          type: "horizontal",
          position: viewportCenter.y,
          start: store.offset.x - 10000,
          end: store.offset.x + rect.width / store.zoom + 10000,
          sourceElement: movingElement,
          alignment: "centerY",
          isFullScreen: true,
        });
      }
    }

    // Multi-alignment tracking
    const verticalAlignments = new Map<string, any>();
    const horizontalAlignments = new Map<string, any>();

    // Check against other DOM elements
    const otherElements = store.domElements.filter(
      (el) =>
        el.id !== movingElement.id && !el.isLocked && el.isVisible !== false
    );

    for (const target of otherElements) {
      const b = getElementBounds(target);
      if (!b) continue;

      const t = {
        left: b.x,
        right: b.x + b.width,
        top: b.y,
        bottom: b.y + b.height,
        centerX: b.x + b.width / 2,
        centerY: b.y + b.height / 2,
      };

      // Vertical alignments
      [
        { src: moving.left, tgt: t.left, align: "left" as const },
        { src: moving.right, tgt: t.right, align: "right" as const },
        { src: moving.centerX, tgt: t.centerX, align: "centerX" as const },
      ].forEach(({ src, tgt, align }) => {
        const distance = Math.abs(src - tgt);
        if (distance < ALIGNMENT_THRESHOLD) {
          const posKey = `${align}-${target.id}`;

          if (!verticalAlignments.has(posKey)) {
            verticalAlignments.set(posKey, {
              position: tgt,
              alignment: align,
              targets: [target],
              minY: Math.min(moving.top, t.top),
              maxY: Math.max(moving.bottom, t.bottom),
              distance: Math.round(distance),
            });
          }
        }
      });

      // Horizontal alignments
      [
        { src: moving.top, tgt: t.top, align: "top" as const },
        { src: moving.bottom, tgt: t.bottom, align: "bottom" as const },
        { src: moving.centerY, tgt: t.centerY, align: "centerY" as const },
      ].forEach(({ src, tgt, align }) => {
        const distance = Math.abs(src - tgt);
        if (distance < ALIGNMENT_THRESHOLD) {
          const posKey = `${align}-${target.id}`;

          if (!horizontalAlignments.has(posKey)) {
            horizontalAlignments.set(posKey, {
              position: tgt,
              alignment: align,
              targets: [target],
              minX: Math.min(moving.left, t.left),
              maxX: Math.max(moving.right, t.right),
              distance: Math.round(distance),
            });
          }
        }
      });
    }

    // Check against canvas objects
    const canvasObjects = store.objects.filter(
      (obj) => !obj.isLocked && obj.isVisible !== false
    );

    for (const obj of canvasObjects) {
      const b = getObjectBounds(obj);
      if (!b) continue;

      const t = {
        left: b.x,
        right: b.x + b.width,
        top: b.y,
        bottom: b.y + b.height,
        centerX: b.x + b.width / 2,
        centerY: b.y + b.height / 2,
      };

      // Vertical alignments
      [
        { src: moving.left, tgt: t.left, align: "left" as const },
        { src: moving.right, tgt: t.right, align: "right" as const },
        { src: moving.centerX, tgt: t.centerX, align: "centerX" as const },
      ].forEach(({ src, tgt, align }) => {
        const distance = Math.abs(src - tgt);
        if (distance < ALIGNMENT_THRESHOLD) {
          const posKey = `${align}-${obj.id}`;

          if (!verticalAlignments.has(posKey)) {
            verticalAlignments.set(posKey, {
              position: tgt,
              alignment: align,
              targets: [obj],
              minY: Math.min(moving.top, t.top),
              maxY: Math.max(moving.bottom, t.bottom),
              distance: Math.round(distance),
            });
          }
        }
      });

      // Horizontal alignments
      [
        { src: moving.top, tgt: t.top, align: "top" as const },
        { src: moving.bottom, tgt: t.bottom, align: "bottom" as const },
        { src: moving.centerY, tgt: t.centerY, align: "centerY" as const },
      ].forEach(({ src, tgt, align }) => {
        const distance = Math.abs(src - tgt);
        if (distance < ALIGNMENT_THRESHOLD) {
          const posKey = `${align}-${obj.id}`;

          if (!horizontalAlignments.has(posKey)) {
            horizontalAlignments.set(posKey, {
              position: tgt,
              alignment: align,
              targets: [obj],
              minX: Math.min(moving.left, t.left),
              maxX: Math.max(moving.right, t.right),
              distance: Math.round(distance),
            });
          }
        }
      });
    }

    // Build guides from alignments
    verticalAlignments.forEach((data) => {
      const isMultiAlign = data.targets.length > 1;
      guides.push({
        type: "vertical",
        position: data.position,
        start: data.minY - GUIDE_PADDING,
        end: data.maxY + GUIDE_PADDING,
        sourceElement: movingElement,
        targetElement: data.targets[0],
        alignment: data.alignment,
        isMultiAlign,
        alignedCount: isMultiAlign ? data.targets.length : undefined,
        distance: data.distance,
      });
    });

    horizontalAlignments.forEach((data) => {
      const isMultiAlign = data.targets.length > 1;
      guides.push({
        type: "horizontal",
        position: data.position,
        start: data.minX - GUIDE_PADDING,
        end: data.maxX + GUIDE_PADDING,
        sourceElement: movingElement,
        targetElement: data.targets[0],
        alignment: data.alignment,
        isMultiAlign,
        alignedCount: isMultiAlign ? data.targets.length : undefined,
        distance: data.distance,
      });
    });

    guides.push(...Array.from(vertical.values()));
    guides.push(...Array.from(horizontal.values()));

    return guides;
  };

  const applySnapping = (
    element: DomElement,
    guides: ElementAlignmentGuide[]
  ) => {
    if (guides.length === 0)
      return { x: element.position.x, y: element.position.y };

    const currentBounds = getElementBounds(element);
    let snapDeltaX = 0;
    let snapDeltaY = 0;

    // ✅ NEW: Priority 1 - Check equidistant spacing snap
    const equidistantSnap = detectEquidistantSpacing(element, currentBounds);
    if (Math.abs(equidistantSnap.snapX) > 0.1) {
      snapDeltaX = equidistantSnap.snapX;
    } else {
      // Priority 2 - Regular alignment snapping
      guides.forEach((guide) => {
        if (guide.type === "vertical") {
          const currentLeft = currentBounds.x;
          const currentRight = currentBounds.x + currentBounds.width;
          const currentCenterX = currentBounds.x + currentBounds.width / 2;

          let diff = 0;
          switch (guide.alignment) {
            case "left":
              diff = guide.position - currentLeft;
              break;
            case "right":
              diff = guide.position - currentRight;
              break;
            case "centerX":
              diff = guide.position - currentCenterX;
              break;
          }

          if (
            Math.abs(diff) < SNAP_THRESHOLD &&
            Math.abs(diff) < Math.abs(snapDeltaX || Infinity)
          ) {
            snapDeltaX = diff;
          }
        }
      });
    }

    if (Math.abs(equidistantSnap.snapY) > 0.1) {
      snapDeltaY = equidistantSnap.snapY;
    } else {
      guides.forEach((guide) => {
        if (guide.type === "horizontal") {
          const currentTop = currentBounds.y;
          const currentBottom = currentBounds.y + currentBounds.height;
          const currentCenterY = currentBounds.y + currentBounds.height / 2;

          let diff = 0;
          switch (guide.alignment) {
            case "top":
              diff = guide.position - currentTop;
              break;
            case "bottom":
              diff = guide.position - currentBottom;
              break;
            case "centerY":
              diff = guide.position - currentCenterY;
              break;
          }

          if (
            Math.abs(diff) < SNAP_THRESHOLD &&
            Math.abs(diff) < Math.abs(snapDeltaY || Infinity)
          ) {
            snapDeltaY = diff;
          }
        }
      });
    }

    return {
      x: element.position.x + snapDeltaX,
      y: element.position.y + snapDeltaY,
    };
  };

  const clearGuides = () => {
    alignmentGuides.value = [];
  };

  return {
    alignmentGuides: computed(() => alignmentGuides.value),
    detectAlignmentGuides,
    applySnapping,
    clearGuides,
    detectEquidistantSpacing,
    generateEquidistantGuides,
  };
}
