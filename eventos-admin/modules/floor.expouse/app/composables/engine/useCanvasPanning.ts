// useCanvasPanning.ts
import { ref } from "vue";
import type { Point } from "@floorplan/types/canvas";

export function useCanvasPanning(store: any) {
  const isPanning = ref(false);
  const lastMousePos = ref<Point>({ x: 0, y: 0 });

  const startPanning = (point: Point) => {
    if (store.currentTool !== "hand") return;
    isPanning.value = true;
    lastMousePos.value = point;
  };

  const doPanning = (point: Point) => {
    const dx = (point.x - lastMousePos.value.x) / store.zoom;
    const dy = (point.y - lastMousePos.value.y) / store.zoom;
    store.offset.x -= dx;
    store.offset.y -= dy;
    lastMousePos.value = point;
  };

  const stopPanning = () => {
    isPanning.value = false;
  };

  return {
    isPanning,
    lastMousePos,
    startPanning,
    doPanning,
    stopPanning,
  };
}
