// useWallSegmentMeasurement.ts
import type { Point } from "@floorplan/types/canvas";
import { useUiStore } from "@floorplan/stores/uiStore"; // Add this import

export function useWallSegmentMeasurement() {
  const uiStore = useUiStore(); // Add this

  const worldToScreen = (point: Point, zoom: number, offset: Point): Point => ({
    x: (point.x - offset.x) * zoom,
    y: (point.y - offset.y) * zoom,
  });

  // Add this function to convert distance based on selected unit
  const convertDistance = (
    distanceCm: number
  ): { value: number; unit: string } => {
    const unit = uiStore.measurementUnit || "centimeter";

    switch (unit) {
      case "feet":
        return { value: distanceCm / 30.48, unit: "ft" }; // 1 foot = 30.48 cm
      case "inches":
        return { value: distanceCm / 2.54, unit: "in" }; // 1 inch = 2.54 cm
      case "meter":
        return { value: distanceCm / 100, unit: "m" }; // 1 meter = 100 cm
      case "centimeter":
      default:
        return { value: distanceCm, unit: "cm" };
    }
  };

  // Add this function to format the measurement text
  const formatMeasurementText = (distanceCm: number): string => {
    const converted = convertDistance(distanceCm);

    // Format based on the unit for appropriate decimal places
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

  const renderWallSegmentMeasurement = (
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

    if (dist > 0) {
      const screenStart = worldToScreen(startPoint, zoom, offset);
      const screenEnd = worldToScreen(endPoint, zoom, offset);

      const dx = screenEnd.x - screenStart.x;
      const dy = screenEnd.y - screenStart.y;
      const length_px = Math.hypot(dx, dy);

      if (length_px >= 5) {
        const angle = Math.atan2(dy, dx) * (180 / Math.PI);

        // Calculate perpendicular offset for measurement line
        let perp_x = -dy;
        let perp_y = dx;
        let norm = Math.hypot(perp_x, perp_y);

        if (norm > 0) {
          perp_x /= norm;
          perp_y /= norm;

          // Determine offset direction based on angle
          let sign = 1;
          if (angle > -45 && angle <= 45) {
            sign = -1;
          } else if (angle > 45 && angle <= 135) {
            sign = -1;
          } else if (angle > 135 || angle <= -135) {
            sign = -1;
          } else {
            sign = 1;
          }

          const offset_amount = 30;
          const offset_x = perp_x * sign * offset_amount;
          const offset_y = perp_y * sign * offset_amount;

          const m_start = {
            x: screenStart.x + offset_x,
            y: screenStart.y + offset_y,
          };
          const m_end = {
            x: screenEnd.x + offset_x,
            y: screenEnd.y + offset_y,
          };

          // Draw dashed measurement line
          ctx.strokeStyle = "#000000";
          ctx.lineWidth = 2;
          ctx.setLineDash([5, 5]);
          ctx.beginPath();
          ctx.moveTo(m_start.x, m_start.y);
          ctx.lineTo(m_end.x, m_end.y);
          ctx.stroke();
          ctx.setLineDash([]);

          // Draw arrowheads
          const arrow_size = 7;
          const norm_dx = dx / length_px;
          const norm_dy = dy / length_px;

          // Arrow at start
          const startArrowAngle1 = Math.atan2(-norm_dy, -norm_dx) - 360 / 9;
          const startArrowAngle2 = Math.atan2(-norm_dy, -norm_dx) + 360 / 9;

          ctx.beginPath();
          ctx.moveTo(m_start.x, m_start.y);
          ctx.lineTo(
            m_start.x + arrow_size * Math.cos(startArrowAngle1),
            m_start.y + arrow_size * Math.sin(startArrowAngle1)
          );
          ctx.moveTo(m_start.x, m_start.y);
          ctx.lineTo(
            m_start.x + arrow_size * Math.cos(startArrowAngle2),
            m_start.y + arrow_size * Math.sin(startArrowAngle2)
          );
          ctx.stroke();

          // Arrow at end
          const endArrowAngle1 = Math.atan2(norm_dy, norm_dx) - 360 / 9;
          const endArrowAngle2 = Math.atan2(norm_dy, norm_dx) + 360 / 9;

          ctx.beginPath();
          ctx.moveTo(m_end.x, m_end.y);
          ctx.lineTo(
            m_end.x + arrow_size * Math.cos(endArrowAngle1),
            m_end.y + arrow_size * Math.sin(endArrowAngle1)
          );
          ctx.moveTo(m_end.x, m_end.y);
          ctx.lineTo(
            m_end.x + arrow_size * Math.cos(endArrowAngle2),
            m_end.y + arrow_size * Math.sin(endArrowAngle2)
          );
          ctx.stroke();

          // Draw measurement label - UPDATED to use dynamic unit
          const text = formatMeasurementText(dist); // Use the new function
          ctx.font = "12px Arial";
          const textMetrics = ctx.measureText(text);
          const textWidth = textMetrics.width;
          const textHeight = 14;
          const labelX = (m_start.x + m_end.x) / 2;
          const labelY = (m_start.y + m_end.y) / 2;
          const padding = 4;

          // Label background
          ctx.fillStyle = "rgba(255, 255, 255, 0.9)";
          ctx.fillRect(
            labelX - textWidth / 2 - padding,
            labelY - textHeight / 2 - padding,
            textWidth + 2 * padding,
            textHeight + 2 * padding
          );

          // Label text
          ctx.fillStyle = "#000000";
          ctx.textAlign = "center";
          ctx.textBaseline = "middle";
          ctx.fillText(text, labelX, labelY);
        }
      }
    }
  };

  return {
    renderWallSegmentMeasurement,
    convertDistance, // Export if needed elsewhere
    formatMeasurementText, // Export if needed elsewhere
  };
}
