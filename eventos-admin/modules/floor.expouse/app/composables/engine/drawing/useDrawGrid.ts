// useDrawGrid.ts
import type { Point } from "@floorplan/types/canvas";

export function useDrawGrid() {
  const drawGrid = (
    ctx: CanvasRenderingContext2D,
    canvas: HTMLCanvasElement,
    zoom: number,
    offset: Point
  ) => {
    const dpr = window.devicePixelRatio || 1;
    const width = canvas.width / dpr;
    const height = canvas.height / dpr;
    const baseGridSize = 20;
    const baseHighlightSpacing = 100;
    const effectiveGridSize = baseGridSize * zoom;
    const effectiveHighlightSpacing = baseHighlightSpacing * zoom;
    const startX =
      Math.floor(offset.x / effectiveGridSize) * effectiveGridSize - offset.x;
    const startY =
      Math.floor(offset.y / effectiveGridSize) * effectiveGridSize - offset.y;
    const highlightStartX =
      Math.floor(offset.x / effectiveHighlightSpacing) *
        effectiveHighlightSpacing -
      offset.x;
    const highlightStartY =
      Math.floor(offset.y / effectiveHighlightSpacing) *
        effectiveHighlightSpacing -
      offset.y;

    ctx.strokeStyle = "#e5e7eb";
    ctx.lineWidth = 1;
    for (let x = startX; x < width; x += effectiveGridSize) {
      ctx.beginPath();
      ctx.moveTo(x, 0);
      ctx.lineTo(x, height);
      ctx.stroke();
    }
    for (let y = startY; y < height; y += effectiveGridSize) {
      ctx.beginPath();
      ctx.moveTo(0, y);
      ctx.lineTo(width, y);
      ctx.stroke();
    }

    ctx.strokeStyle = "#d1d5db";
    ctx.lineWidth = 1;
    for (let x = highlightStartX; x < width; x += effectiveHighlightSpacing) {
      ctx.beginPath();
      ctx.moveTo(x, 0);
      ctx.lineTo(x, height);
      ctx.stroke();
    }
    for (let y = highlightStartY; y < height; y += effectiveHighlightSpacing) {
      ctx.beginPath();
      ctx.moveTo(0, y);
      ctx.lineTo(width, y);
      ctx.stroke();
    }
  };

  return {
    drawGrid,
  };
}
