// useTwoHeadedArrowLineSegmentMeasurement.ts
import type { Point } from "@floorplan/types/canvas";
import { useUiStore } from "@floorplan/stores/uiStore";

export function useTwoHeadedArrowLineSegmentMeasurement() {
  const uiStore = useUiStore();

  const worldToScreen = (point: Point, zoom: number, offset: Point): Point => ({
    x: (point.x - offset.x) * zoom,
    y: (point.y - offset.y) * zoom,
  });

  const convertDistance = (
    distanceCm: number
  ): { value: number; unit: string } => {
    const unit = uiStore.measurementUnit || "centimeter";

    switch (unit) {
      case "feet":
        return { value: distanceCm / 30.48, unit: "ft" };
      case "inches":
        return { value: distanceCm / 2.54, unit: "in" };
      case "meter":
        return { value: distanceCm / 100, unit: "m" };
      case "centimeter":
      default:
        return { value: distanceCm, unit: "cm" };
    }
  };

  const formatMeasurementText = (distanceCm: number): string => {
    const converted = convertDistance(distanceCm);

    switch (converted.unit) {
      case "ft":
      case "m":
        return `${converted.value.toFixed(2)} ${converted.unit}`;
      case "in":
        return `${converted.value.toFixed(1)} ${converted.unit}`;
      case "cm":
      default:
        return `${converted.value.toFixed(1)} ${converted.unit}`;
    }
  };

  const renderTwoHeadedArrowLineSegmentMeasurement = (
    ctx: CanvasRenderingContext2D,
    startPoint: Point,
    endPoint: Point,
    zoom: number,
    offset: Point
  ) => {
    const dist = Math.hypot(
      endPoint.x - startPoint.x,
      endPoint.y - startPoint.y
    );

    if (dist < 0.1) return;

    const screenStart = worldToScreen(startPoint, zoom, offset);
    const screenEnd = worldToScreen(endPoint, zoom, offset);

    const dx = screenEnd.x - screenStart.x;
    const dy = screenEnd.y - screenStart.y;
    const length_px = Math.hypot(dx, dy);

    if (length_px < 10) return;

    const midX = (screenStart.x + screenEnd.x) / 2;
    const midY = (screenStart.y + screenEnd.y) / 2;
    const angle = Math.atan2(dy, dx);

    // Calculate perpendicular vector for text positioning
    const perpX = -Math.sin(angle);
    const perpY = Math.cos(angle);

    // Determine which side is "above" the line
    // For horizontal lines (angle near 0), we want text above (negative Y)
    // For vertical lines (angle near π/2), we want text to the left (negative X)
    let textOffsetX = perpX;
    let textOffsetY = perpY;

    // Adjust offset direction to always place text in a consistent position
    // For most cases, we want text above the line
    if (Math.abs(angle) < Math.PI / 2) {
      // Right-pointing lines: text should be above
      if (perpY > 0) {
        textOffsetX = -perpX;
        textOffsetY = -perpY;
      }
    } else {
      // Left-pointing lines: text should be above
      if (perpY < 0) {
        textOffsetX = -perpX;
        textOffsetY = -perpY;
      }
    }

    // Position text with offset
    const textOffset = 15;
    const textX = midX + textOffsetX * textOffset;
    const textY = midY + textOffsetY * textOffset;

    const text = formatMeasurementText(dist);

    // Save context state for rotation
    ctx.save();

    // Move to text position and rotate
    ctx.translate(textX, textY);

    // Rotate text to be readable (perpendicular to line)
    // But limit rotation to keep text mostly horizontal for readability
    let textAngle = angle;
    // Normalize angle to keep text upright
    if (textAngle > Math.PI / 2 && textAngle <= Math.PI) {
      textAngle -= Math.PI;
    } else if (textAngle < -Math.PI / 2 && textAngle >= -Math.PI) {
      textAngle += Math.PI;
    }
    ctx.rotate(textAngle);

    // Draw text background
    const textMetrics = ctx.measureText(text);
    const textWidth = textMetrics.width;
    const textHeight = 14;
    const padding = 4;

    ctx.fillStyle = "rgba(255, 255, 255, 0.9)";
    ctx.fillRect(
      -textWidth / 2 - padding,
      -textHeight / 2 - padding,
      textWidth + 2 * padding,
      textHeight + 2 * padding
    );

    // Draw text
    ctx.fillStyle = "#000000";
    ctx.font = "12px Arial";
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillText(text, 0, 0);

    // Restore context
    ctx.restore();
  };

  return {
    renderTwoHeadedArrowLineSegmentMeasurement,
    convertDistance,
    formatMeasurementText,
  };
}
