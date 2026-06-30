import type { CanvasObject, Point } from "@floorplan/types/canvas";

export function useCanvasText() {
  const renderText = (
    ctx: CanvasRenderingContext2D,
    obj: CanvasObject,
    zoom: number,
    offset: Point,
    worldToScreen: (point: Point, zoom: number, offset: Point) => Point
  ) => {
    if (!obj.text || obj.points.length === 0) return;

    const position = worldToScreen(obj.points[0], zoom, offset);
    const fontSize = (obj.fontSize || 16) * zoom;
    const rotationRad = (obj.rotation || 0) * (Math.PI / 180);

    ctx.save();
    ctx.translate(position.x, position.y);
    ctx.rotate(rotationRad);
    ctx.font = `${fontSize}px sans-serif`;
    ctx.fillStyle = obj.color || "#000000";
    ctx.textAlign = "left";
    ctx.textBaseline = "top";
    ctx.fillText(obj.text, 0, 0);
    ctx.restore();
  };

  return {
    renderText,
  };
}
