// composables/useObjectAlignment.ts - Alignment for all object types
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useCanvasObjects } from "@floorplan/composables/useCanvasObjects";
import type { Point, CanvasObject, Command } from "@floorplan/types/canvas";

export function useObjectAlignment() {
  const store = useCanvasStore();
  const canvasObjects = useCanvasObjects();

  const getBounds = (obj: CanvasObject) => {
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

  const getCenter = (obj: CanvasObject) => {
    const bounds = getBounds(obj);
    if (!bounds) return { x: 0, y: 0 };
    return {
      x: bounds.x + bounds.width / 2,
      y: bounds.y + bounds.height / 2,
    };
  };

  // ✅ Helper function to update bounding box after point changes
  const updateBoundingBox = (obj: CanvasObject) => {
    if (!obj.points || obj.points.length < 2) return;

    const xs = obj.points.map((p) => p.x);
    const ys = obj.points.map((p) => p.y);

    obj.boundingBox = {
      x: Math.min(...xs),
      y: Math.min(...ys),
      width: Math.max(...xs) - Math.min(...xs),
      height: Math.max(...ys) - Math.min(...ys),
    };

    // Update position for booths
    if (obj.type === "booth") {
      obj.position = {
        x: obj.points[0].x,
        y: obj.points[0].y,
      };
    }
  };

  const alignObjects = (
    alignment: "left" | "right" | "top" | "bottom" | "center" | "middle"
  ) => {
    // Get selected objects (both canvas and DOM elements)
    const selectedCanvasObjects = store.selectedObjects.filter(
      (obj) => !obj.isLocked && obj.isVisible !== false
    );
    const selectedDomElements = store.selectedDomElements.filter(
      (el) => !el.isLocked
    );

    const totalSelected =
      selectedCanvasObjects.length + selectedDomElements.length;

    if (totalSelected === 0) {
      console.warn("No objects selected for alignment");
      return;
    }

    if (totalSelected === 1) {
      console.warn("Need at least 2 objects for alignment");
      return;
    }

    // Store initial states for undo
    const initialStates = new Map<
      string,
      { points: Point[]; position?: Point; boundingBox?: any }
    >();

    selectedCanvasObjects.forEach((obj) => {
      initialStates.set(obj.id, {
        points: obj.points.map((p) => ({ x: p.x, y: p.y })),
        boundingBox: obj.boundingBox ? { ...obj.boundingBox } : undefined,
      });
    });

    selectedDomElements.forEach((el) => {
      initialStates.set(el.id, {
        points: [],
        position: { x: el.position.x, y: el.position.y },
      });
    });

    // Calculate alignment reference position
    let referenceValue = 0;

    if (alignment === "left" || alignment === "right") {
      // For horizontal alignment, use the leftmost/rightmost object
      const allBounds = [
        ...selectedCanvasObjects.map((obj) => getBounds(obj)),
        ...selectedDomElements.map((el) => ({
          x: el.position.x,
          y: el.position.y,
          width: el.size.width,
          height: el.size.height,
        })),
      ].filter(Boolean);

      if (alignment === "left") {
        referenceValue = Math.min(...allBounds.map((b) => b!.x));
      } else {
        referenceValue = Math.max(...allBounds.map((b) => b!.x + b!.width));
      }
    } else if (alignment === "top" || alignment === "bottom") {
      // For vertical alignment, use the topmost/bottommost object
      const allBounds = [
        ...selectedCanvasObjects.map((obj) => getBounds(obj)),
        ...selectedDomElements.map((el) => ({
          x: el.position.x,
          y: el.position.y,
          width: el.size.width,
          height: el.size.height,
        })),
      ].filter(Boolean);

      if (alignment === "top") {
        referenceValue = Math.min(...allBounds.map((b) => b!.y));
      } else {
        referenceValue = Math.max(...allBounds.map((b) => b!.y + b!.height));
      }
    } else if (alignment === "center") {
      // Center horizontally
      const allCenters = [
        ...selectedCanvasObjects.map((obj) => getCenter(obj).x),
        ...selectedDomElements.map((el) => el.position.x + el.size.width / 2),
      ];
      referenceValue =
        allCenters.reduce((sum, x) => sum + x, 0) / allCenters.length;
    } else if (alignment === "middle") {
      // Center vertically
      const allCenters = [
        ...selectedCanvasObjects.map((obj) => getCenter(obj).y),
        ...selectedDomElements.map((el) => el.position.y + el.size.height / 2),
      ];
      referenceValue =
        allCenters.reduce((sum, y) => sum + y, 0) / allCenters.length;
    }

    // Apply alignment to canvas objects
    selectedCanvasObjects.forEach((obj) => {
      const bounds = getBounds(obj);
      if (!bounds) return;

      let deltaX = 0;
      let deltaY = 0;

      switch (alignment) {
        case "left":
          deltaX = referenceValue - bounds.x;
          break;
        case "right":
          deltaX = referenceValue - (bounds.x + bounds.width);
          break;
        case "top":
          deltaY = referenceValue - bounds.y;
          break;
        case "bottom":
          deltaY = referenceValue - (bounds.y + bounds.height);
          break;
        case "center":
          deltaX = referenceValue - (bounds.x + bounds.width / 2);
          break;
        case "middle":
          deltaY = referenceValue - (bounds.y + bounds.height / 2);
          break;
      }

      // Update object points
      if (obj.points && Array.isArray(obj.points)) {
        obj.points = obj.points.map((p) => ({
          x: p.x + deltaX,
          y: p.y + deltaY,
        }));
      }

      // ✅ CRITICAL: Update bounding box to match new points
      updateBoundingBox(obj);
    });

    // Apply alignment to DOM elements
    selectedDomElements.forEach((el) => {
      let newX = el.position.x;
      let newY = el.position.y;

      switch (alignment) {
        case "left":
          newX = referenceValue;
          break;
        case "right":
          newX = referenceValue - el.size.width;
          break;
        case "top":
          newY = referenceValue;
          break;
        case "bottom":
          newY = referenceValue - el.size.height;
          break;
        case "center":
          newX = referenceValue - el.size.width / 2;
          break;
        case "middle":
          newY = referenceValue - el.size.height / 2;
          break;
      }

      el.position = { x: newX, y: newY };
    });

    // Create undo/redo command
    const finalStates = new Map<
      string,
      { points: Point[]; position?: Point; boundingBox?: any }
    >();

    selectedCanvasObjects.forEach((obj) => {
      finalStates.set(obj.id, {
        points: obj.points.map((p) => ({ x: p.x, y: p.y })),
        boundingBox: obj.boundingBox ? { ...obj.boundingBox } : undefined,
      });
    });

    selectedDomElements.forEach((el) => {
      finalStates.set(el.id, {
        points: [],
        position: { x: el.position.x, y: el.position.y },
      });
    });

    const alignCommand = new (class implements Command {
      execute() {
        finalStates.forEach((state, id) => {
          const canvasObj = store.objects.find((o) => o.id === id);
          if (canvasObj && state.points.length > 0) {
            canvasObj.points = state.points.map((p) => ({ x: p.x, y: p.y }));
            // ✅ Restore bounding box
            if (state.boundingBox) {
              canvasObj.boundingBox = { ...state.boundingBox };
            }
            // ✅ Update position for booths
            if (canvasObj.type === "booth") {
              canvasObj.position = {
                x: canvasObj.points[0].x,
                y: canvasObj.points[0].y,
              };
            }
          }

          const domEl = store.domElements.find((e) => e.id === id);
          if (domEl && state.position) {
            domEl.position = { ...state.position };
          }
        });
      }

      undo() {
        initialStates.forEach((state, id) => {
          const canvasObj = store.objects.find((o) => o.id === id);
          if (canvasObj && state.points.length > 0) {
            canvasObj.points = state.points.map((p) => ({ x: p.x, y: p.y }));
            // ✅ Restore bounding box
            if (state.boundingBox) {
              canvasObj.boundingBox = { ...state.boundingBox };
            }
            // ✅ Update position for booths
            if (canvasObj.type === "booth") {
              canvasObj.position = {
                x: canvasObj.points[0].x,
                y: canvasObj.points[0].y,
              };
            }
          }

          const domEl = store.domElements.find((e) => e.id === id);
          if (domEl && state.position) {
            domEl.position = { ...state.position };
          }
        });
      }
    })();

    alignCommand.execute();

    const objectType =
      selectedCanvasObjects.length > 0 && selectedDomElements.length > 0
        ? "mixed"
        : selectedCanvasObjects.length > 0
        ? "canvas"
        : "dom";

    store.pushToHistory(
      alignCommand,
      "modify",
      objectType,
      `Aligned ${
        selectedCanvasObjects.length + selectedDomElements.length
      } objects to ${alignment}`
    );

    console.log(
      `✅ Aligned ${
        selectedCanvasObjects.length + selectedDomElements.length
      } objects to ${alignment}`
    );
  };

  return {
    alignObjects,
  };
}
