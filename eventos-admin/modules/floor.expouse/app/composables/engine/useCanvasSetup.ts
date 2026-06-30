// useCanvasSetup.ts
import { ref } from "vue";
import type { Point } from "@floorplan/types/canvas";

export function useCanvasSetup() {
  const canvasRef = ref<HTMLCanvasElement | null>(null);
  const ctx = ref<CanvasRenderingContext2D | null>(null);

  const setupCanvas = (canvas: HTMLCanvasElement) => {
    canvasRef.value = canvas;
    ctx.value = canvas.getContext("2d");
    resizeCanvas();
  };

  const resizeCanvas = () => {
    if (!canvasRef.value) return;
    const canvas = canvasRef.value;
    const container = canvas.parentElement;
    if (!container) return;
    const dpr = window.devicePixelRatio || 1;
    const rect = container.getBoundingClientRect();
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    canvas.style.width = `${rect.width}px`;
    canvas.style.height = `${rect.height}px`;
    if (ctx.value) {
      ctx.value.scale(dpr, dpr);
    }
  };

  const clearCanvas = () => {
    if (!canvasRef.value || !ctx.value) return;
    const canvas = canvasRef.value;
    ctx.value.clearRect(0, 0, canvas.width, canvas.height);
  };

  return {
    canvasRef,
    ctx,
    setupCanvas,
    resizeCanvas,
    clearCanvas,
  };
}
