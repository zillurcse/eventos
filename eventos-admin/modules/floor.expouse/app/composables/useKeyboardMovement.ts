// composables/useKeyboardMovement.ts
import { ref, onMounted, onUnmounted } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";

export function useKeyboardMovement() {
  const store = useCanvasStore();

  const moveStep = ref(5); // pixels per key press
  const isMoving = ref(false);

  const moveSelectedObjects = (dx: number, dy: number) => {
    const selectedObjects = store.selectedObjects.filter(
      (obj) => !obj.isLocked
    );

    if (selectedObjects.length === 0) return;

    selectedObjects.forEach((obj) => {
      if (obj.points && obj.points.length >= 2) {
        const newPoints = obj.points.map((point) => ({
          x: point.x + dx,
          y: point.y + dy,
        }));

        // Update object points
        store.updateObject(obj.id, { points: newPoints });

        // Update bounding box if it exists
        if (obj.boundingBox) {
          store.updateObject(obj.id, {
            boundingBox: {
              x: obj.boundingBox.x + dx,
              y: obj.boundingBox.y + dy,
              width: obj.boundingBox.width,
              height: obj.boundingBox.height,
            },
          });
        }
      }
    });
  };

  const handleKeyDown = (event: KeyboardEvent) => {
    // Only handle arrow keys when no input is focused
    if (document.activeElement?.tagName === "INPUT") return;

    if (event.key.startsWith("Arrow")) {
      event.preventDefault();

      let dx = 0,
        dy = 0;

      switch (event.key) {
        case "ArrowUp":
          dy = -moveStep.value;
          break;
        case "ArrowDown":
          dy = moveStep.value;
          break;
        case "ArrowLeft":
          dx = -moveStep.value;
          break;
        case "ArrowRight":
          dx = moveStep.value;
          break;
      }

      if (dx !== 0 || dy !== 0) {
        if (event.shiftKey) {
          // Larger steps with Shift
          moveSelectedObjects(dx * 5, dy * 5);
        } else if (event.ctrlKey || event.metaKey) {
          // Smaller steps with Ctrl/Cmd
          moveSelectedObjects(dx * 0.5, dy * 0.5);
        } else {
          // Normal steps
          moveSelectedObjects(dx, dy);
        }

        isMoving.value = true;
      }
    }
  };

  const handleKeyUp = (event: KeyboardEvent) => {
    if (event.key.startsWith("Arrow")) {
      isMoving.value = false;
    }
  };

  onMounted(() => {
    document.addEventListener("keydown", handleKeyDown);
    document.addEventListener("keyup", handleKeyUp);
  });

  onUnmounted(() => {
    document.removeEventListener("keydown", handleKeyDown);
    document.removeEventListener("keyup", handleKeyUp);
  });

  return {
    moveStep,
    isMoving,
  };
}
