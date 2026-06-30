// useHoverState.ts
import { ref } from "vue";
import type { Point, CanvasObject } from "@floorplan/types/canvas";

export function useHoverState() {
  const hoverState = ref({
    hoveredObject: null as CanvasObject | null,
    hoverPoint: null as Point | null,
    showMeasurements: false,
  });

  const updateHoverState = (
    hoveredObject: CanvasObject | null,
    point: Point
  ) => {
    hoverState.value.hoveredObject = hoveredObject;
    hoverState.value.hoverPoint = point;
    hoverState.value.showMeasurements = !!hoveredObject;
  };

  return {
    hoverState,
    updateHoverState,
  };
}
