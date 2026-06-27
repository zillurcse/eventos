// useCanvasBooth.ts
import type { CanvasObject, Point } from "@floorplan/types/canvas";
import { useUiStore } from "@floorplan/stores/uiStore";
import { useCanvasLOD } from "@floorplan/composables/useCanvasLOD";

// Restore your original cache logic
let customColorsCache: Record<string, string> = {};
let cacheTimestamp = 0;
const CACHE_DURATION = 1000;

const getCustomColors = (): Record<string, string> => {
  const now = Date.now();
  if (now - cacheTimestamp < CACHE_DURATION) return customColorsCache;

  if (process.client) {
    try {
      const saved = localStorage.getItem("booth-custom-colors");
      customColorsCache = saved ? JSON.parse(saved) : {};
    } catch {
      customColorsCache = {};
    }
  }
  cacheTimestamp = now;
  return customColorsCache;
};

export function useCanvasBooth(
  uiStore: ReturnType<typeof useUiStore>,
  getCenter: (obj: CanvasObject) => Point,
  rotatePoint: (point: Point, center: Point, rotation: number) => Point,
) {
  const lod = useCanvasLOD();

  const renderBooth = (
    ctx: CanvasRenderingContext2D,
    obj: CanvasObject,
    zoom: number,
    offset: Point,
    worldToScreen: (point: Point, zoom: number, offset: Point) => Point,
    isHovered: boolean = false,
  ) => {
    if (obj.points.length < 2) return;

    const p1 = obj.points[0];
    const p2 = obj.points[1];
    const minX = Math.min(p1.x, p2.x);
    const minY = Math.min(p1.y, p2.y);
    const width = Math.abs(p1.x - p2.x);
    const height = Math.abs(p1.y - p2.y);
    const center = { x: minX + width / 2, y: minY + height / 2 };
    const screenCenter = worldToScreen(center, zoom, offset);
    const screenSize = Math.max(width, height) * zoom;

    // LOD: Tiny booth → dot
    if (screenSize < 30) {
      lod.renderAsDot(ctx, obj, zoom, offset, (p) =>
        worldToScreen(p, zoom, offset),
      );
      return;
    }

    const rotationRad = (obj.rotation || 0) * (Math.PI / 180);
    ctx.save();
    ctx.translate(screenCenter.x, screenCenter.y);
    ctx.rotate(rotationRad);
    ctx.translate(-screenCenter.x, -screenCenter.y);

    const screenMinX = (minX - offset.x) * zoom;
    const screenMinY = (minY - offset.y) * zoom;
    const screenWidth = width * zoom;
    const screenHeight = height * zoom;

    const status = obj.status || "AVAILABLE";
    const customColors = getCustomColors();
    const statusColors = {
      AVAILABLE: {
        fill: customColors.AVAILABLE || "#ecfdf5",
        border: "#22c55e",
      },
      BOOKED: { fill: customColors.BOOKED || "#fee2e2", border: "#ef4444" },
      ON_HOLD: { fill: customColors.ON_HOLD || "#dbeafe", border: "#3b82f6" },
    };
    const colors = statusColors[status] || statusColors.AVAILABLE;

    // Background + Border
    const radius = Math.max(0, (obj.cornerRadius || 0) * zoom);

    ctx.beginPath();
    if (radius > 0) {
      ctx.roundRect(screenMinX, screenMinY, screenWidth, screenHeight, radius);
    } else {
      ctx.rect(screenMinX, screenMinY, screenWidth, screenHeight);
    }

    ctx.fillStyle = colors.fill;
    ctx.fill();

    ctx.strokeStyle = colors.border;
    ctx.lineWidth = Math.max(2, zoom * 2);
    ctx.stroke();

    if (isHovered) {
      ctx.save();
      ctx.strokeStyle = "#3b82f6";
      ctx.lineWidth = Math.max(3, zoom * 3);
      ctx.beginPath();
      if (radius > 0) {
        ctx.roundRect(
          screenMinX,
          screenMinY,
          screenWidth,
          screenHeight,
          radius,
        );
      } else {
        ctx.rect(screenMinX, screenMinY, screenWidth, screenHeight);
      }
      ctx.stroke();
      ctx.restore();
    }

    // Booth Number (always show) - WITH CUSTOM FONT PROPERTIES
    if (screenSize > 40) {
      // Use custom font size or default to calculated size
      let fontSize;
      if (obj.boothNumberFontSize) {
        fontSize = Math.max(8, obj.boothNumberFontSize * zoom);
      } else {
        fontSize = Math.max(12, screenWidth * 0.14);
      }

      // Use custom color or default color
      ctx.fillStyle = obj.boothNumberColor || "#1f2937";
      ctx.font = `${fontSize}px Arial`;
      ctx.textAlign = "center";
      ctx.textBaseline = "middle";
      ctx.fillText(obj.boothNumber || "B", screenCenter.x, screenCenter.y);
    }

    // Booth Name (show when name exists) - WITH CUSTOM FONT PROPERTIES
    // REMOVED: "status === 'BOOKED' &&" restriction
    if (obj.booth_name && obj.booth_name.trim() !== "" && screenSize > 60) {
      // Use custom font size or default to calculated size
      let fontSize;
      if (obj.boothNameFontSize) {
        fontSize = Math.max(8, obj.boothNameFontSize * zoom);
      } else {
        fontSize = Math.max(10, screenWidth * 0.1);
      }

      // Use custom color or default color
      ctx.fillStyle = obj.boothNameColor || "#374151";
      ctx.font = `${fontSize}px Arial`;
      ctx.textAlign = "center";
      ctx.textBaseline = "middle";

      // Position name below booth number with proper spacing
      const nameY = screenCenter.y + Math.max(15, screenHeight * 0.15);

      // Truncate long names with ellipsis
      const maxWidth = screenWidth * 0.8;
      let displayName = obj.booth_name;
      const metrics = ctx.measureText(displayName);

      if (metrics.width > maxWidth) {
        // Truncate text to fit within booth
        while (
          displayName.length > 3 &&
          ctx.measureText(displayName + "...").width > maxWidth
        ) {
          displayName = displayName.slice(0, -1);
        }
        displayName = displayName + "...";
      }

      ctx.fillText(displayName, screenCenter.x, nameY);
    }

    // REMOVED: Internal measurements display

    // External measurements (show outside booth)
    const shouldShowMeasurements = lod.getLOD() !== "low" || screenSize > 100;

    const uiStore = useUiStore();

    if (shouldShowMeasurements) {
      const fontSize = Math.max(8, zoom * 10);
      ctx.font = `${fontSize}px Arial`;
      ctx.fillStyle = "#1f2937";

      ctx.textAlign = "center";
      ctx.textBaseline = "middle";

      // DYNAMIC UNIT CONVERSION USING uiStore
      const wText = uiStore.formatMeasurement(width);
      const hText = uiStore.formatMeasurement(height);
      const aText = uiStore.formatArea(width * height);

      // Width (bottom - outside booth)
      ctx.fillText(
        wText,
        screenCenter.x,
        screenMinY + screenHeight + fontSize * 1.5, // Position below booth
      );

      // Area (top - outside booth)
      ctx.fillText(
        aText,
        screenCenter.x,
        screenMinY - fontSize * 1.5, // Position above booth
      );

      // Height (left - outside booth)
      ctx.save();
      ctx.translate(screenMinX - fontSize * 1.5, screenCenter.y);
      ctx.rotate(-Math.PI / 2);
      ctx.textAlign = "center";
      ctx.fillText(hText, 0, -3);
      ctx.restore();
    }

    // ✅ UPDATED: Arrows show when hovered OR selected
    // শুধুমাত্র hovered বা selected booth-এর জন্য arrow দেখান
    if (isHovered && screenSize > 40 && !lod.shouldSkip.boothArrows()) {
      renderBoothArrows(
        ctx,
        screenMinX,
        screenMinY,
        screenWidth,
        screenHeight,
        screenCenter,
        zoom,
      );
    }

    // DRAW CORNER HANDLES IF HOVERED (consistent with rectangle logic)
    if (isHovered && !obj.isLocked) {
      const handleDistance = 15; // px from corner
      const handleRadius = 4;

      const sw = screenWidth;
      const sh = screenHeight;

      // Ensure handles don't overlap if booth is too small
      const safeDist = Math.min(handleDistance, sw / 3, sh / 3);

      // Since we are currently in a transformed space (rotated around center, but origin at screen (0,0))
      // It's easier to calculate local coordinates and then translate to screen center
      // but wait, line 66 moved origin back to (0,0) screen.
      // So screenMinX, screenMinY are the top-left of the booth in that space.

      const localHandles = [
        { x: screenMinX + safeDist, y: screenMinY + safeDist },
        { x: screenMinX + screenWidth - safeDist, y: screenMinY + safeDist },
        {
          x: screenMinX + screenWidth - safeDist,
          y: screenMinY + screenHeight - safeDist,
        },
        { x: screenMinX + safeDist, y: screenMinY + screenHeight - safeDist },
      ];

      ctx.fillStyle = "white";
      ctx.strokeStyle = "#4F46E5"; // Indigo-600
      ctx.lineWidth = 1;

      localHandles.forEach((h) => {
        // Outer circle
        ctx.beginPath();
        ctx.arc(h.x, h.y, handleRadius, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();

        // Inner dot
        ctx.beginPath();
        ctx.arc(h.x, h.y, 1, 0, Math.PI * 2);
        ctx.fillStyle = "#4F46E5";
        ctx.fill();
        ctx.fillStyle = "white"; // Reset for next handle
      });

      // Center dot
      ctx.beginPath();
      ctx.arc(screenCenter.x, screenCenter.y, handleRadius / 2, 0, Math.PI * 2);
      ctx.fillStyle = "#4F46E5";
      ctx.fill();
    }

    ctx.restore();
  };

  const renderBoothArrows = (
    ctx: CanvasRenderingContext2D,
    x: number,
    y: number,
    width: number,
    height: number,
    center: Point,
    zoom: number,
  ) => {
    const size = 20 * zoom;
    const offset = 20 * zoom;
    const color = "#6366f1";

    const draw = (
      px: number,
      py: number,
      dir: "up" | "right" | "down" | "left",
    ) => {
      // NEW DESIGN: Round shape with plus icon
      const radius = size / 2;

      // Draw background circle
      ctx.fillStyle = color;
      ctx.beginPath();
      ctx.arc(px, py, radius, 0, Math.PI * 2);
      ctx.fill();

      // Draw plus icon
      ctx.strokeStyle = "white";
      ctx.lineWidth = 2; // Line width for the plus icon
      const plusSize = radius * 1.2;

      ctx.beginPath();
      // Horizontal line
      ctx.moveTo(px - plusSize / 3, py);
      ctx.lineTo(px + plusSize / 3, py);
      // Vertical line
      ctx.moveTo(px, py - plusSize / 3);
      ctx.lineTo(px, py + plusSize / 3);
      ctx.stroke();

      /* EXISTING ARROW DESIGN (Commented out for future use)
      ctx.fillStyle = color;
      ctx.beginPath();
      if (dir === "up") {
        ctx.moveTo(px, py - size / 2);
        ctx.lineTo(px - size / 2, py + size / 2);
        ctx.lineTo(px + size / 2, py + size / 2);
      } else if (dir === "right") {
        ctx.moveTo(px + size / 2, py);
        ctx.lineTo(px - size / 2, py - size / 2);
        ctx.lineTo(px - size / 2, py + size / 2);
      } else if (dir === "down") {
        ctx.moveTo(px, py + size / 2);
        ctx.lineTo(px - size / 2, py - size / 2);
        ctx.lineTo(px + size / 2, py - size / 2);
      } else {
        ctx.moveTo(px - size / 2, py);
        ctx.lineTo(px + size / 2, py - size / 2);
        ctx.lineTo(px + size / 2, py + size / 2);
      }
      ctx.closePath();
      ctx.fill();
      */
    };

    draw(center.x, y - offset, "up");
    draw(x + width + offset, center.y, "right");
    draw(center.x, y + height + offset, "down");
    draw(x - offset, center.y, "left");
  };

  return { renderBooth, renderBoothArrows };
}
