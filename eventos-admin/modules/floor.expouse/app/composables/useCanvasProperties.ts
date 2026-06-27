// composables/useCanvasProperties.ts → FINAL FIXED VERSION
import type { CanvasObject } from "@floorplan/types/canvas";

export function useCanvasProperties() {
  const dashCache = new Map<string, number[]>();
  const shadowCache = new Map<string, any>();

  const DASH_PATTERNS = {
    Dashed: [10, 5],
    Dotted: [2, 3],
    "Dot Dash": [10, 3, 2, 3],
    "Long Dash": [20, 5],
    "Double Dash": [10, 5, 2, 5],
    Solid: [],
  } as const;

  // ────── APPLY DRAWING PROPERTIES (lineWidth, dash, cap, etc.) ──────
  const applyDrawingProperties = (
    ctx: CanvasRenderingContext2D,
    obj: CanvasObject,
    zoom: number = 1 // ← NOW REQUIRED
  ) => {
    if (!ctx || !obj) return;

    // Line Width — SCALED WITH ZOOM
    const baseWidth = obj.strokeWidth !== undefined ? obj.strokeWidth : (obj.lineWidth || 2);
    const lineWidth = baseWidth * zoom;
    if (ctx.lineWidth !== lineWidth) {
      ctx.lineWidth = lineWidth;
    }

    // Line Dash — use cached or predefined
    const dashStyle = obj.dashStyle || "Solid";
    let pattern = dashCache.get(dashStyle);
    if (!pattern) {
      pattern = DASH_PATTERNS[dashStyle as keyof typeof DASH_PATTERNS] || [];
      dashCache.set(dashStyle, pattern);
    }

    // Scale dash pattern with zoom
    const scaledDash = pattern.length > 0 ? pattern.map((d) => d * zoom) : [];
    if (JSON.stringify(ctx.getLineDash()) !== JSON.stringify(scaledDash)) {
      ctx.setLineDash(scaledDash);
    }

    // Line Cap & Join
    ctx.lineCap = (obj.lineCap as CanvasLineCap) || "round";
    ctx.lineJoin = (obj.lineJoin as CanvasLineJoin) || "round";
    ctx.miterLimit = 10;
  };

  // ────── APPLY APPEARANCE (color, opacity, shadow) ──────
  const applyAppearanceProperties = (
    ctx: CanvasRenderingContext2D,
    obj: CanvasObject
  ) => {
    const fill = obj.fillColor || obj.fill || obj.color || "#3b82f6";
    const stroke = obj.strokeColor || obj.stroke || (obj.type === 'frame' || obj.type === 'section' ? '#1f2937' : obj.color) || "#000000";
    const opacity = obj.opacity !== undefined ? obj.opacity : 1;

    if (ctx.fillStyle !== fill) ctx.fillStyle = fill;
    if (ctx.strokeStyle !== stroke) ctx.strokeStyle = stroke;
    if (ctx.globalAlpha !== opacity) ctx.globalAlpha = opacity;

    // ────── SHADOWS (cached + fast path) ──────
    const shadowBlur = obj.shadowBlur !== undefined ? obj.shadowBlur : obj.blur;
    const hasShadow = shadowBlur && shadowBlur > 0 && obj.shadowColor;
    const shadowKey = `${obj.shadowColor}-${shadowBlur}-${
      obj.shadowOffsetX || 0
    }-${obj.shadowOffsetY || 0}`;

    if (!hasShadow) {
      if (ctx.shadowBlur !== 0) {
        ctx.shadowColor = "transparent";
        ctx.shadowBlur = 0;
        ctx.shadowOffsetX = 0;
        ctx.shadowOffsetY = 0;
      }
      return;
    }

    let cached = shadowCache.get(shadowKey);
    if (!cached) {
      cached = {
        shadowColor: obj.shadowColor || "rgba(0,0,0,0.3)",
        shadowBlur: shadowBlur || 10,
        shadowOffsetX: obj.shadowOffsetX || 2,
        shadowOffsetY: obj.shadowOffsetY || 2,
      };
      shadowCache.set(shadowKey, cached);
    }

    ctx.shadowColor = cached.shadowColor;
    ctx.shadowBlur = cached.shadowBlur;
    ctx.shadowOffsetX = cached.shadowOffsetX;
    ctx.shadowOffsetY = cached.shadowOffsetY;
  };

  // ────── ROUNDED RECT (optimized) ──────
  const drawRoundedRect = (
    ctx: CanvasRenderingContext2D,
    x: number,
    y: number,
    w: number,
    h: number,
    r: number
  ) => {
    if (r <= 0) {
      ctx.rect(x, y, w, h);
      return;
    }
    const radius = Math.min(r, Math.min(w, h) / 2);
    ctx.beginPath();
    ctx.moveTo(x + radius, y);
    ctx.arcTo(x + w, y, x + w, y + h, radius);
    ctx.arcTo(x + w, y + h, x, y + h, radius);
    ctx.arcTo(x, y + h, x, y, radius);
    ctx.arcTo(x, y, x + w, y, radius);
    ctx.closePath();
  };

  return {
    applyDrawingProperties,
    applyAppearanceProperties,
    drawRoundedRect,
  };
}
