import type { Point, CanvasObject } from "@floorplan/types/canvas";
import { useCanvasObjects } from "@floorplan/composables/useCanvasObjects";

export function useHoverMeasurements() {
  const canvasObjects = useCanvasObjects();

  const getObjectMeasurements = (obj: CanvasObject) => {
    const bounds = canvasObjects.getRotatedBounding(obj);
    if (!bounds) return null;

    return {
      width: bounds.width,
      height: bounds.height,
      area: bounds.width * bounds.height,
    };
  };

  const renderHoverMeasurements = (
    ctx: CanvasRenderingContext2D,
    obj: CanvasObject,
    zoom: number,
    offset: Point
  ) => {
    if (!obj) return;

    const measurements = getObjectMeasurements(obj);
    if (!measurements) return;

    const bounds = canvasObjects.getRotatedBounding(obj);
    if (!bounds) return;

    // Convert to screen coordinates
    const screenBounds = {
      x: (bounds.x - offset.x) * zoom,
      y: (bounds.y - offset.y) * zoom,
      width: bounds.width * zoom,
      height: bounds.height * zoom,
    };

    // Format measurements
    const widthText = `${measurements.width.toFixed(2)} m`;
    const heightText = `${measurements.height.toFixed(2)} m`;
    const areaText = `${measurements.area.toFixed(2)} m²`;

    ctx.save();

    // Style
    ctx.font = "12px Inter, system-ui, sans-serif";
    ctx.fillStyle = "#1e40af";
    ctx.strokeStyle = "#ffffff";
    ctx.lineWidth = 3;
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";

    // Area at top
    const areaX = screenBounds.x + screenBounds.width / 2;
    const areaY = screenBounds.y - 15;
    ctx.strokeText(areaText, areaX, areaY);
    ctx.fillText(areaText, areaX, areaY);

    // Width at bottom
    const widthX = screenBounds.x + screenBounds.width / 2;
    const widthY = screenBounds.y + screenBounds.height + 15;
    ctx.strokeText(widthText, widthX, widthY);
    ctx.fillText(widthText, widthX, widthY);

    // Height on right (rotated)
    ctx.save();
    ctx.translate(
      screenBounds.x + screenBounds.width + 15,
      screenBounds.y + screenBounds.height / 2
    );
    ctx.rotate(-Math.PI / 2);
    ctx.strokeText(heightText, 0, 0);
    ctx.fillText(heightText, 0, 0);
    ctx.restore();

    ctx.restore();
  };

  return {
    getObjectMeasurements,
    renderHoverMeasurements,
  };
}
