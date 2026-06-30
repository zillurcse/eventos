import type { CanvasObject, Point } from "@floorplan/types/canvas";
import type { useUiStore } from "@floorplan/stores/uiStore";
import { ref } from "vue";
import { useCanvasObjects } from "@floorplan/composables/useCanvasObjects";

export function useCanvasHover(uiStore: useUiStore) {
  const { getObjectBounding } = useCanvasObjects();

  const hoverState = ref({
    isHovering: false,
    hoveredObject: null as CanvasObject | null,
    mousePosition: { x: 0, y: 0 } as Point,
  });

  const updateHoverState = (object: CanvasObject | null, mousePos: Point) => {
    hoverState.value.isHovering = !!object;
    hoverState.value.hoveredObject = object;
    hoverState.value.mousePosition = mousePos;
  };

  const getObjectMeasurements = (obj: CanvasObject) => {
    const bounds = getObjectBounding(obj);
    if (!bounds) return null;

    const width = bounds.width;
    const height = bounds.height;
    const area = width * height;

    return {
      width: uiStore.formatMeasurement(width),
      height: uiStore.formatMeasurement(height),
      area: uiStore.formatArea(area),
    };
  };

  const renderHoverMeasurements = (
    ctx: CanvasRenderingContext2D,
    zoom: number,
    offset: Point
  ) => {
    if (!hoverState.value.isHovering || !hoverState.value.hoveredObject) return;

    const obj = hoverState.value.hoveredObject;
    const measurements = getObjectMeasurements(obj);
    if (!measurements) return;

    const mousePos = hoverState.value.mousePosition;

    // Create measurement text
    const lines = [
      `W: ${measurements.width}`,
      `H: ${measurements.height}`,
      `Area: ${measurements.area}`,
    ];

    // Calculate tooltip dimensions
    ctx.font = "12px Arial";
    const lineHeight = 14;
    const padding = 8;

    const textWidth = Math.max(
      ...lines.map((line) => ctx.measureText(line).width)
    );
    const tooltipWidth = textWidth + padding * 2;
    const tooltipHeight = lines.length * lineHeight + padding * 2;

    // Position tooltip (avoid going off-screen)
    let tooltipX = mousePos.x + 15;
    let tooltipY = mousePos.y + 15;

    if (
      tooltipX + tooltipWidth >
      ctx.canvas.width / (window.devicePixelRatio || 1)
    ) {
      tooltipX = mousePos.x - tooltipWidth - 5;
    }
    if (
      tooltipY + tooltipHeight >
      ctx.canvas.height / (window.devicePixelRatio || 1)
    ) {
      tooltipY = mousePos.y - tooltipHeight - 5;
    }

    // Draw tooltip background
    ctx.fillStyle = "rgba(255, 255, 255, 0.95)";
    ctx.strokeStyle = "#3b82f6";
    ctx.lineWidth = 1;
    ctx.fillRect(tooltipX, tooltipY, tooltipWidth, tooltipHeight);
    ctx.strokeRect(tooltipX, tooltipY, tooltipWidth, tooltipHeight);

    // Draw text
    ctx.fillStyle = "#000000";
    ctx.textAlign = "left";
    ctx.textBaseline = "top";

    lines.forEach((line, index) => {
      ctx.fillText(
        line,
        tooltipX + padding,
        tooltipY + padding + index * lineHeight
      );
    });
  };

  return {
    hoverState,
    updateHoverState,
    getObjectMeasurements,
    renderHoverMeasurements,
  };
}
