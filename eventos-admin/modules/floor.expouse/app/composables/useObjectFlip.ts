// composables/useObjectFlip.ts - CORRECTED Flip functionality for canvas objects
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useCanvasObjects } from "@floorplan/composables/useCanvasObjects";
import type { Point, CanvasObject, Command } from "@floorplan/types/canvas";

export function useObjectFlip() {
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

  /**
   * Flip objects horizontally
   */
  const flipHorizontal = () => {
    const selectedCanvasObjects = store.selectedObjects.filter(
      (obj) => !obj.isLocked && obj.isVisible !== false
    );
    const selectedDomElements = store.selectedDomElements.filter(
      (el) => !el.isLocked
    );

    if (
      selectedCanvasObjects.length === 0 &&
      selectedDomElements.length === 0
    ) {
      console.warn("No objects selected for flip");
      return;
    }

    // Store initial states
    const initialStates = new Map<
      string,
      {
        points: Point[];
        boundingBox?: any;
        position?: Point;
        transform?: string;
      }
    >();

    selectedCanvasObjects.forEach((obj) => {
      initialStates.set(obj.id, {
        points: obj.points ? obj.points.map((p) => ({ x: p.x, y: p.y })) : [],
        boundingBox: obj.boundingBox ? { ...obj.boundingBox } : undefined,
        position: obj.position ? { ...obj.position } : undefined,
      });
    });

    selectedDomElements.forEach((el) => {
      initialStates.set(el.id, {
        points: [],
        position: el.position ? { ...el.position } : undefined,
        transform: el.styleProps?.transform || "",
      });
    });

    // Apply horizontal flip to canvas objects
    selectedCanvasObjects.forEach((obj) => {
      if (!obj.points || obj.points.length < 2) return;

      // ✅ SPECIAL HANDLING FOR BOOTH (2-point rectangle)
      if (obj.type === "booth" && obj.points.length === 2) {
        const p1 = obj.points[0];
        const p2 = obj.points[1];

        // Swap X coordinates to flip horizontally
        obj.points = [
          { x: p2.x, y: p1.y }, // Top-right becomes top-left
          { x: p1.x, y: p2.y }, // Bottom-left becomes bottom-right
        ];
      }
      // ✅ GENERAL HANDLING FOR OTHER OBJECTS (mirror points)
      else {
        const center = getCenter(obj);
        obj.points = obj.points.map((p) => ({
          x: center.x - (p.x - center.x), // Mirror around center X
          y: p.y, // Y stays the same
        }));
      }

      // ✅ CRITICAL: Update bounding box to match new points
      updateBoundingBox(obj);
    });

    // Apply horizontal flip to DOM elements (using transform)
    selectedDomElements.forEach((el) => {
      if (!el.styleProps) el.styleProps = {};
      const currentTransform = el.styleProps.transform || "";

      // Toggle horizontal flip
      if (currentTransform.includes("scaleX(-1)")) {
        el.styleProps.transform = currentTransform.replace(
          "scaleX(-1)",
          "scaleX(1)"
        );
      } else {
        el.styleProps.transform = currentTransform + " scaleX(-1)";
      }
    });

    // Store final states
    const finalStates = new Map<
      string,
      {
        points: Point[];
        boundingBox?: any;
        position?: Point;
        transform?: string;
      }
    >();

    selectedCanvasObjects.forEach((obj) => {
      finalStates.set(obj.id, {
        points: obj.points ? obj.points.map((p) => ({ x: p.x, y: p.y })) : [],
        boundingBox: obj.boundingBox ? { ...obj.boundingBox } : undefined,
        position: obj.position ? { ...obj.position } : undefined,
      });
    });

    selectedDomElements.forEach((el) => {
      finalStates.set(el.id, {
        points: [],
        position: el.position ? { ...el.position } : undefined,
        transform: el.styleProps?.transform || "",
      });
    });

    // Create undo/redo command
    const flipCmd = new (class implements Command {
      execute() {
        finalStates.forEach((state, id) => {
          const canvasObj = store.objects.find((o) => o.id === id);
          if (canvasObj && state.points.length > 0) {
            canvasObj.points = state.points.map((p) => ({ x: p.x, y: p.y }));
            // ✅ Restore bounding box
            if (state.boundingBox) {
              canvasObj.boundingBox = { ...state.boundingBox };
            }
            // ✅ Restore position
            if (state.position) {
              canvasObj.position = { ...state.position };
            }
          }

          const domEl = store.domElements.find((e) => e.id === id);
          if (domEl && state.transform !== undefined) {
            if (!domEl.styleProps) domEl.styleProps = {};
            domEl.styleProps.transform = state.transform;
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
            // ✅ Restore position
            if (state.position) {
              canvasObj.position = { ...state.position };
            }
          }

          const domEl = store.domElements.find((e) => e.id === id);
          if (domEl && state.transform !== undefined) {
            if (!domEl.styleProps) domEl.styleProps = {};
            domEl.styleProps.transform = state.transform;
          }
        });
      }
    })();

    flipCmd.execute();

    const objectType =
      selectedCanvasObjects.length > 0 && selectedDomElements.length > 0
        ? "mixed"
        : selectedCanvasObjects.length > 0
        ? "canvas"
        : "dom";

    store.pushToHistory(
      flipCmd,
      "modify",
      objectType,
      `Flipped ${
        selectedCanvasObjects.length + selectedDomElements.length
      } objects horizontally`
    );

    console.log(
      `✅ Flipped ${
        selectedCanvasObjects.length + selectedDomElements.length
      } objects horizontally`
    );
  };

  /**
   * Flip objects vertically
   */
  const flipVertical = () => {
    const selectedCanvasObjects = store.selectedObjects.filter(
      (obj) => !obj.isLocked && obj.isVisible !== false
    );
    const selectedDomElements = store.selectedDomElements.filter(
      (el) => !el.isLocked
    );

    if (
      selectedCanvasObjects.length === 0 &&
      selectedDomElements.length === 0
    ) {
      console.warn("No objects selected for flip");
      return;
    }

    // Store initial states
    const initialStates = new Map<
      string,
      {
        points: Point[];
        boundingBox?: any;
        position?: Point;
        transform?: string;
      }
    >();

    selectedCanvasObjects.forEach((obj) => {
      initialStates.set(obj.id, {
        points: obj.points ? obj.points.map((p) => ({ x: p.x, y: p.y })) : [],
        boundingBox: obj.boundingBox ? { ...obj.boundingBox } : undefined,
        position: obj.position ? { ...obj.position } : undefined,
      });
    });

    selectedDomElements.forEach((el) => {
      initialStates.set(el.id, {
        points: [],
        position: el.position ? { ...el.position } : undefined,
        transform: el.styleProps?.transform || "",
      });
    });

    // Apply vertical flip to canvas objects
    selectedCanvasObjects.forEach((obj) => {
      if (!obj.points || obj.points.length < 2) return;

      // ✅ SPECIAL HANDLING FOR BOOTH (2-point rectangle)
      if (obj.type === "booth" && obj.points.length === 2) {
        const p1 = obj.points[0];
        const p2 = obj.points[1];

        // Swap Y coordinates to flip vertically
        obj.points = [
          { x: p1.x, y: p2.y }, // Top-left becomes bottom-left
          { x: p2.x, y: p1.y }, // Bottom-right becomes top-right
        ];
      }
      // ✅ GENERAL HANDLING FOR OTHER OBJECTS (mirror points)
      else {
        const center = getCenter(obj);
        obj.points = obj.points.map((p) => ({
          x: p.x, // X stays the same
          y: center.y - (p.y - center.y), // Mirror around center Y
        }));
      }

      // ✅ CRITICAL: Update bounding box to match new points
      updateBoundingBox(obj);
    });

    // Apply vertical flip to DOM elements (using transform)
    selectedDomElements.forEach((el) => {
      if (!el.styleProps) el.styleProps = {};
      const currentTransform = el.styleProps.transform || "";

      // Toggle vertical flip
      if (currentTransform.includes("scaleY(-1)")) {
        el.styleProps.transform = currentTransform.replace(
          "scaleY(-1)",
          "scaleY(1)"
        );
      } else {
        el.styleProps.transform = currentTransform + " scaleY(-1)";
      }
    });

    // Store final states
    const finalStates = new Map<
      string,
      {
        points: Point[];
        boundingBox?: any;
        position?: Point;
        transform?: string;
      }
    >();

    selectedCanvasObjects.forEach((obj) => {
      finalStates.set(obj.id, {
        points: obj.points ? obj.points.map((p) => ({ x: p.x, y: p.y })) : [],
        boundingBox: obj.boundingBox ? { ...obj.boundingBox } : undefined,
        position: obj.position ? { ...obj.position } : undefined,
      });
    });

    selectedDomElements.forEach((el) => {
      finalStates.set(el.id, {
        points: [],
        position: el.position ? { ...el.position } : undefined,
        transform: el.styleProps?.transform || "",
      });
    });

    // Create undo/redo command
    const flipCmd = new (class implements Command {
      execute() {
        finalStates.forEach((state, id) => {
          const canvasObj = store.objects.find((o) => o.id === id);
          if (canvasObj && state.points.length > 0) {
            canvasObj.points = state.points.map((p) => ({ x: p.x, y: p.y }));
            // ✅ Restore bounding box
            if (state.boundingBox) {
              canvasObj.boundingBox = { ...state.boundingBox };
            }
            // ✅ Restore position
            if (state.position) {
              canvasObj.position = { ...state.position };
            }
          }

          const domEl = store.domElements.find((e) => e.id === id);
          if (domEl && state.transform !== undefined) {
            if (!domEl.styleProps) domEl.styleProps = {};
            domEl.styleProps.transform = state.transform;
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
            // ✅ Restore position
            if (state.position) {
              canvasObj.position = { ...state.position };
            }
          }

          const domEl = store.domElements.find((e) => e.id === id);
          if (domEl && state.transform !== undefined) {
            if (!domEl.styleProps) domEl.styleProps = {};
            domEl.styleProps.transform = state.transform;
          }
        });
      }
    })();

    flipCmd.execute();

    const objectType =
      selectedCanvasObjects.length > 0 && selectedDomElements.length > 0
        ? "mixed"
        : selectedCanvasObjects.length > 0
        ? "canvas"
        : "dom";

    store.pushToHistory(
      flipCmd,
      "modify",
      objectType,
      `Flipped ${
        selectedCanvasObjects.length + selectedDomElements.length
      } objects vertically`
    );

    console.log(
      `✅ Flipped ${
        selectedCanvasObjects.length + selectedDomElements.length
      } objects vertically`
    );
  };

  return {
    flipHorizontal,
    flipVertical,
  };
}
