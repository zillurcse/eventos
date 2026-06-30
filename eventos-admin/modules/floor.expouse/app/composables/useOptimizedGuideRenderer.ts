// composables/useOptimizedGuideRenderer.ts - COMPLETE WITH EQUIDISTANT RENDERING
import type { Point, CanvasObject } from "@floorplan/types/canvas";
import { useCanvasObjects } from "@floorplan/composables/useCanvasObjects";
import { useUiStore } from "@floorplan/stores/uiStore";

interface LocalAlignmentGuide {
  type: "vertical" | "horizontal";
  position: number;
  start: number;
  end: number;
  alignment: "left" | "right" | "top" | "bottom" | "centerX" | "centerY";
  isMultiAlign?: boolean;
  alignedCount?: number;
  isFullScreen?: boolean;
  distance?: number;
  targetObjectId?: string;
  alignedObjects?: Array<{
    id: string;
    position: number;
  }>;
  objectBounds?: Array<{
    id: string;
    start: number;
    end: number;
    isMoving?: boolean;
  }>;
  isEquidistant?: boolean;
}

interface AlignedElementData {
  id: string;
  edges: Array<"left" | "right" | "top" | "bottom" | "centerX" | "centerY">;
}

export function useOptimizedGuideRenderer() {
  const worldToScreen = (point: Point, zoom: number, offset: Point): Point => ({
    x: (point.x - offset.x) * zoom,
    y: (point.y - offset.y) * zoom,
  });

  const canvasObjects = useCanvasObjects();
  const uiStore = useUiStore();

  const getBounds = (obj: CanvasObject) => {
    if (obj.elementData) {
      return {
        x: obj.elementData.position.x,
        y: obj.elementData.position.y,
        width: obj.elementData.size.width,
        height: obj.elementData.size.height,
      };
    } else {
      return canvasObjects.getRotatedBounding(obj);
    }
  };

  const drawGroupedAlignmentMarker = (
    ctx: CanvasRenderingContext2D,
    x: number,
    y: number,
    color: string,
    count: number
  ) => {
    ctx.save();

    ctx.fillStyle = color;
    ctx.beginPath();
    ctx.arc(x, y, 12, 0, Math.PI * 2);
    ctx.fill();

    ctx.strokeStyle = "#FFFFFF";
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(x, y, 12, 0, Math.PI * 2);
    ctx.stroke();

    ctx.fillStyle = "#FFFFFF";
    ctx.font = "bold 10px sans-serif";
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillText(count.toString(), x, y);

    ctx.restore();
  };

  const drawDistanceLabel = (
    ctx: CanvasRenderingContext2D,
    x: number,
    y: number,
    distanceInCm: number,
    isVertical: boolean,
    canvasWidth: number,
    canvasHeight: number
  ) => {
    if (!distanceInCm || distanceInCm < 5) return;

    ctx.save();

    const converted = uiStore.convertToCurrentUnit(distanceInCm);
    const text = `${converted.value.toFixed(1)} ${converted.unit}`;

    ctx.font =
      "bold 12px -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
    const metrics = ctx.measureText(text);
    const padding = 8;
    const boxWidth = metrics.width + padding * 2;
    const boxHeight = 20;

    let labelX = x;
    let labelY = y;

    if (isVertical) {
      labelX = x + 35;
      if (labelX + boxWidth / 2 > canvasWidth - 20) {
        labelX = x - 35;
      }
      if (labelY < boxHeight / 2 + 20) {
        labelY = boxHeight / 2 + 20;
      } else if (labelY > canvasHeight - boxHeight / 2 - 20) {
        labelY = canvasHeight - boxHeight / 2 - 20;
      }
    } else {
      labelY = y - 35;
      if (labelY - boxHeight / 2 < 20) {
        labelY = y + 35;
      }
      if (labelX < boxWidth / 2 + 20) {
        labelX = boxWidth / 2 + 20;
      } else if (labelX > canvasWidth - boxWidth / 2 - 20) {
        labelX = canvasWidth - boxWidth / 2 - 20;
      }
    }

    ctx.shadowColor = "rgba(0, 0, 0, 0.3)";
    ctx.shadowBlur = 6;
    ctx.shadowOffsetX = 0;
    ctx.shadowOffsetY = 2;

    const gradient = ctx.createLinearGradient(
      labelX - boxWidth / 2,
      labelY - boxHeight / 2,
      labelX - boxWidth / 2,
      labelY + boxHeight / 2
    );
    gradient.addColorStop(0, "#10B981");
    gradient.addColorStop(1, "#059669");

    ctx.fillStyle = gradient;
    ctx.beginPath();
    ctx.roundRect(
      labelX - boxWidth / 2,
      labelY - boxHeight / 2,
      boxWidth,
      boxHeight,
      6
    );
    ctx.fill();

    ctx.shadowColor = "transparent";
    ctx.shadowBlur = 0;
    ctx.shadowOffsetX = 0;
    ctx.shadowOffsetY = 0;

    ctx.strokeStyle = "rgba(255, 255, 255, 0.4)";
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.roundRect(
      labelX - boxWidth / 2,
      labelY - boxHeight / 2,
      boxWidth,
      boxHeight,
      6
    );
    ctx.stroke();

    ctx.fillStyle = "#FFFFFF";
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillText(text, labelX, labelY);

    ctx.restore();
  };

  const renderAlignedElementMarks = (
    ctx: CanvasRenderingContext2D,
    alignedElements: AlignedElementData[],
    allCanvasObjects: CanvasObject[],
    zoom: number,
    offset: Point
  ) => {
    if (!alignedElements || alignedElements.length === 0) return;

    ctx.save();
    ctx.strokeStyle = "#EF4444";
    ctx.lineWidth = 2;
    ctx.globalAlpha = 1;
    const MARKER_SIZE = 4;

    const objectsMap = new Map(allCanvasObjects.map((obj) => [obj.id, obj]));

    for (const alignedData of alignedElements) {
      const obj = objectsMap.get(alignedData.id);
      if (!obj) continue;

      const bounds = getBounds(obj);
      if (!bounds) continue;

      const screen = {
        left: (bounds.x - offset.x) * zoom,
        right: (bounds.x + bounds.width - offset.x) * zoom,
        top: (bounds.y - offset.y) * zoom,
        bottom: (bounds.y + bounds.height - offset.y) * zoom,
        centerX: (bounds.x + bounds.width / 2 - offset.x) * zoom,
        centerY: (bounds.y + bounds.height / 2 - offset.y) * zoom,
      };

      for (const edge of alignedData.edges) {
        let x, y;

        switch (edge) {
          case "top":
            x = screen.centerX;
            y = screen.top;
            break;
          case "bottom":
            x = screen.centerX;
            y = screen.bottom;
            break;
          case "left":
            x = screen.left;
            y = screen.centerY;
            break;
          case "right":
            x = screen.right;
            y = screen.centerY;
            break;
          case "centerX":
            x = screen.centerX;
            y = screen.centerY;
            break;
          case "centerY":
            x = screen.centerX;
            y = screen.centerY;
            break;
          default:
            continue;
        }

        ctx.fillStyle = "#EF4444";
        ctx.beginPath();
        ctx.arc(x, y, MARKER_SIZE, 0, Math.PI * 2);
        ctx.fill();

        ctx.strokeStyle = "#FFFFFF";
        ctx.lineWidth = 1.5;
        ctx.beginPath();
        ctx.arc(x, y, MARKER_SIZE, 0, Math.PI * 2);
        ctx.stroke();
      }
    }
    ctx.restore();
  };

  const renderLocalGuides = (
    ctx: CanvasRenderingContext2D,
    guides: LocalAlignmentGuide[],
    zoom: number,
    offset: Point
  ) => {
    if (!guides || guides.length === 0) return;

    ctx.save();

    const canvasWidth = ctx.canvas.width / (window.devicePixelRatio || 1);
    const canvasHeight = ctx.canvas.height / (window.devicePixelRatio || 1);

    const fullScreenGuides = guides.filter((g) => g.isFullScreen);
    const localGuides = guides.filter((g) => !g.isFullScreen);

    // Render FULL-SCREEN guides
    if (fullScreenGuides.length > 0) {
      fullScreenGuides.forEach((guide) => {
        const color = "#6366f1";
        const x = (guide.position - offset.x) * zoom;
        const y = (guide.position - offset.y) * zoom;

        ctx.strokeStyle = color;
        ctx.lineWidth = 2;
        ctx.setLineDash([]);
        ctx.globalAlpha = 0.9;

        ctx.beginPath();
        if (guide.type === "vertical") {
          ctx.moveTo(x, 0);
          ctx.lineTo(x, canvasHeight);
        } else {
          ctx.moveTo(0, y);
          ctx.lineTo(canvasWidth, y);
        }
        ctx.stroke();
      });
    }

    const verticalLocalGuides = localGuides.filter(
      (g) => g.type === "vertical"
    );
    const horizontalLocalGuides = localGuides.filter(
      (g) => g.type === "horizontal"
    );

    const usedLabelPositions: Array<{
      x: number;
      y: number;
      width: number;
      height: number;
    }> = [];

    const LABEL_BUFFER = 20;

    const checkLabelOverlap = (
      x: number,
      y: number,
      width: number,
      height: number
    ): boolean => {
      return usedLabelPositions.some((pos) => {
        const buffer = LABEL_BUFFER;
        return !(
          x + width + buffer < pos.x ||
          x > pos.x + pos.width + buffer ||
          y + height + buffer < pos.y ||
          y > pos.y + pos.height + buffer
        );
      });
    };

    // Render vertical LOCAL guides
    if (verticalLocalGuides.length > 0) {
      verticalLocalGuides.forEach((guide) => {
        const isCenter = guide.alignment === "centerX";
        const isMulti = guide.isMultiAlign || false;
        const isEquidistant = guide.isEquidistant || false;

        // ✅ Color hierarchy: Equidistant > Multi > Center > Edge
        const color = isEquidistant
          ? "#10B981" // Emerald green for equal spacing
          : isMulti
          ? "#F59E0B" // Amber for multi-align
          : isCenter
          ? "#8B5CF6" // Purple for center
          : "#6366F1"; // Indigo for edges

        const x = (guide.position - offset.x) * zoom;
        const startY = (guide.start - offset.y) * zoom;
        const endY = (guide.end - offset.y) * zoom;

        ctx.strokeStyle = color;
        ctx.lineWidth = isEquidistant ? 3 : isMulti ? 2.5 : isCenter ? 2 : 1.5;
        ctx.setLineDash(isEquidistant ? [10, 5] : isMulti ? [] : [5, 5]);
        ctx.globalAlpha = isEquidistant ? 1 : 0.85;

        ctx.beginPath();
        ctx.moveTo(x, startY);
        ctx.lineTo(x, endY);
        ctx.stroke();

        ctx.globalAlpha = 1;
        ctx.fillStyle = color;

        // Draw endpoint dots
        const dotSize = isEquidistant ? 4 : 3;
        ctx.beginPath();
        ctx.arc(x, startY, dotSize, 0, Math.PI * 2);
        ctx.fill();
        ctx.beginPath();
        ctx.arc(x, endY, dotSize, 0, Math.PI * 2);
        ctx.fill();

        // ✅ Show "EQUAL" badge for equidistant spacing
        if (isEquidistant && guide.distance) {
          const midY = (startY + endY) / 2;
          const badgeColor = "#6366F1"; // Indigo for the badge as in image

          ctx.save();

          const badgeWidth = 64;
          const badgeHeight = 24;

          ctx.shadowColor = "rgba(0, 0, 0, 0.25)";
          ctx.shadowBlur = 8;
          ctx.shadowOffsetY = 3;

          ctx.fillStyle = badgeColor;
          ctx.beginPath();
          ctx.roundRect(
            x - badgeWidth / 2,
            midY - badgeHeight / 2,
            badgeWidth,
            badgeHeight,
            12
          );
          ctx.fill();

          ctx.shadowColor = "transparent";

          ctx.strokeStyle = "#FFFFFF";
          ctx.lineWidth = 2.5;
          ctx.stroke();

          ctx.fillStyle = "#FFFFFF";
          ctx.font = "bold 11px system-ui, -apple-system, sans-serif";
          ctx.textAlign = "center";
          ctx.textBaseline = "middle";
          ctx.fillText("EQUAL", x, midY);

          ctx.restore();
        }

        // Show distance label for SINGLE alignment
        if (
          !isMulti &&
          !isEquidistant &&
          guide.distance &&
          guide.distance >= 50
        ) {
          const midY = (startY + endY) / 2;
          const labelWidth = 80;
          const labelHeight = 40;

          if (
            !checkLabelOverlap(
              x - labelWidth / 2,
              midY - labelHeight / 2,
              labelWidth,
              labelHeight
            )
          ) {
            drawDistanceLabel(
              ctx,
              x,
              midY,
              guide.distance,
              true,
              canvasWidth,
              canvasHeight
            );
            usedLabelPositions.push({
              x: x - labelWidth / 2,
              y: midY - labelHeight / 2,
              width: labelWidth,
              height: labelHeight,
            });
          }
        }

        // Draw distance labels between aligned objects
        if (guide.objectBounds && guide.objectBounds.length >= 2) {
          const sortedBounds = [...guide.objectBounds].sort(
            (a, b) => a.start - b.start
          );

          for (let i = 0; i < sortedBounds.length - 1; i++) {
            const obj1 = sortedBounds[i];
            const obj2 = sortedBounds[i + 1];

            const gapDistance = obj2.start - obj1.end;

            if (gapDistance < 1) continue;

            const gapMidpoint = (obj1.end + obj2.start) / 2;
            const screenX = (guide.position - offset.x) * zoom;
            const screenY = (gapMidpoint - offset.y) * zoom;

            const labelWidth = 80;
            const labelHeight = 40;

            if (
              !checkLabelOverlap(
                screenX - labelWidth / 2,
                screenY - labelHeight / 2,
                labelWidth,
                labelHeight
              )
            ) {
              drawDistanceLabel(
                ctx,
                screenX,
                screenY,
                gapDistance,
                true,
                canvasWidth,
                canvasHeight
              );
              usedLabelPositions.push({
                x: screenX - labelWidth / 2,
                y: screenY - labelHeight / 2,
                width: labelWidth,
                height: labelHeight,
              });
            }
          }
        } else if (guide.alignedObjects && guide.alignedObjects.length >= 2) {
          const sortedObjects = [...guide.alignedObjects].sort(
            (a, b) => a.position - b.position
          );

          for (let i = 0; i < sortedObjects.length - 1; i++) {
            const obj1 = sortedObjects[i];
            const obj2 = sortedObjects[i + 1];

            const distanceInCm = Math.abs(obj2.position - obj1.position);

            const y1 = (obj1.position - offset.y) * zoom;
            const y2 = (obj2.position - offset.y) * zoom;
            const midY = (y1 + y2) / 2;

            const labelWidth = 80;
            const labelHeight = 40;

            if (
              !checkLabelOverlap(
                x - labelWidth / 2,
                midY - labelHeight / 2,
                labelWidth,
                labelHeight
              )
            ) {
              drawDistanceLabel(
                ctx,
                x,
                midY,
                distanceInCm,
                true,
                canvasWidth,
                canvasHeight
              );
              usedLabelPositions.push({
                x: x - labelWidth / 2,
                y: midY - labelHeight / 2,
                width: labelWidth,
                height: labelHeight,
              });
            }
          }
        }

        if (isMulti && guide.alignedCount && !isEquidistant) {
          const midY = (startY + endY) / 2;
          drawGroupedAlignmentMarker(ctx, x, midY, color, guide.alignedCount);
        }
      });
    }

    // Render horizontal LOCAL guides
    if (horizontalLocalGuides.length > 0) {
      horizontalLocalGuides.forEach((guide) => {
        const isCenter = guide.alignment === "centerY";
        const isMulti = guide.isMultiAlign || false;
        const isEquidistant = guide.isEquidistant || false;

        const color = isEquidistant
          ? "#10B981"
          : isMulti
          ? "#F59E0B"
          : isCenter
          ? "#8B5CF6"
          : "#6366F1";

        const y = (guide.position - offset.y) * zoom;
        const startX = (guide.start - offset.x) * zoom;
        const endX = (guide.end - offset.x) * zoom;

        ctx.strokeStyle = color;
        ctx.lineWidth = isEquidistant ? 3 : isMulti ? 2.5 : isCenter ? 2 : 1.5;
        ctx.setLineDash(isEquidistant ? [10, 5] : isMulti ? [] : [5, 5]);
        ctx.globalAlpha = isEquidistant ? 1 : 0.85;

        ctx.beginPath();
        ctx.moveTo(startX, y);
        ctx.lineTo(endX, y);
        ctx.stroke();

        ctx.globalAlpha = 1;
        ctx.fillStyle = color;

        // Draw endpoint dots
        const dotSize = isEquidistant ? 4 : 3;
        ctx.beginPath();
        ctx.arc(startX, y, dotSize, 0, Math.PI * 2);
        ctx.fill();
        ctx.beginPath();
        ctx.arc(endX, y, dotSize, 0, Math.PI * 2);
        ctx.fill();

        // ✅ Show "EQUAL" badge for equidistant spacing
        if (isEquidistant && guide.distance) {
          const midX = (startX + endX) / 2;
          const badgeColor = "#6366F1";

          ctx.save();

          const badgeWidth = 64;
          const badgeHeight = 24;

          ctx.shadowColor = "rgba(0, 0, 0, 0.25)";
          ctx.shadowBlur = 8;
          ctx.shadowOffsetY = 3;

          ctx.fillStyle = badgeColor;
          ctx.beginPath();
          ctx.roundRect(
            midX - badgeWidth / 2,
            y - badgeHeight / 2,
            badgeWidth,
            badgeHeight,
            12
          );
          ctx.fill();

          ctx.shadowColor = "transparent";

          ctx.strokeStyle = "#FFFFFF";
          ctx.lineWidth = 2.5;
          ctx.stroke();

          ctx.fillStyle = "#FFFFFF";
          ctx.font = "bold 11px system-ui, -apple-system, sans-serif";
          ctx.textAlign = "center";
          ctx.textBaseline = "middle";
          ctx.fillText("EQUAL", midX, y);

          ctx.restore();
        }

        // Show distance label for SINGLE alignment
        if (
          !isMulti &&
          !isEquidistant &&
          guide.distance &&
          guide.distance >= 50
        ) {
          const midX = (startX + endX) / 2;
          const labelWidth = 80;
          const labelHeight = 40;

          if (
            !checkLabelOverlap(
              midX - labelWidth / 2,
              y - labelHeight / 2,
              labelWidth,
              labelHeight
            )
          ) {
            drawDistanceLabel(
              ctx,
              midX,
              y,
              guide.distance,
              false,
              canvasWidth,
              canvasHeight
            );
            usedLabelPositions.push({
              x: midX - labelWidth / 2,
              y: y - labelHeight / 2,
              width: labelWidth,
              height: labelHeight,
            });
          }
        }

        // Draw distance labels between aligned objects
        if (guide.objectBounds && guide.objectBounds.length >= 2) {
          const sortedBounds = [...guide.objectBounds].sort(
            (a, b) => a.start - b.start
          );

          for (let i = 0; i < sortedBounds.length - 1; i++) {
            const obj1 = sortedBounds[i];
            const obj2 = sortedBounds[i + 1];

            const gapDistance = obj2.start - obj1.end;

            if (gapDistance < 1) continue;

            const gapMidpoint = (obj1.end + obj2.start) / 2;
            const screenX = (gapMidpoint - offset.x) * zoom;
            const screenY = (guide.position - offset.y) * zoom;

            const labelWidth = 80;
            const labelHeight = 40;

            if (
              !checkLabelOverlap(
                screenX - labelWidth / 2,
                screenY - labelHeight / 2,
                labelWidth,
                labelHeight
              )
            ) {
              drawDistanceLabel(
                ctx,
                screenX,
                screenY,
                gapDistance,
                false,
                canvasWidth,
                canvasHeight
              );
              usedLabelPositions.push({
                x: screenX - labelWidth / 2,
                y: screenY - labelHeight / 2,
                width: labelWidth,
                height: labelHeight,
              });
            }
          }
        } else if (guide.alignedObjects && guide.alignedObjects.length >= 2) {
          const sortedObjects = [...guide.alignedObjects].sort(
            (a, b) => a.position - b.position
          );

          for (let i = 0; i < sortedObjects.length - 1; i++) {
            const obj1 = sortedObjects[i];
            const obj2 = sortedObjects[i + 1];

            const distanceInCm = Math.abs(obj2.position - obj1.position);

            const x1 = (obj1.position - offset.x) * zoom;
            const x2 = (obj2.position - offset.x) * zoom;
            const midX = (x1 + x2) / 2;

            const labelWidth = 80;
            const labelHeight = 40;

            if (
              !checkLabelOverlap(
                midX - labelWidth / 2,
                y - labelHeight / 2,
                labelWidth,
                labelHeight
              )
            ) {
              drawDistanceLabel(
                ctx,
                midX,
                y,
                distanceInCm,
                false,
                canvasWidth,
                canvasHeight
              );
              usedLabelPositions.push({
                x: midX - labelWidth / 2,
                y: y - labelHeight / 2,
                width: labelWidth,
                height: labelHeight,
              });
            }
          }
        }

        if (isMulti && guide.alignedCount && !isEquidistant) {
          const midX = (startX + endX) / 2;
          drawGroupedAlignmentMarker(ctx, midX, y, color, guide.alignedCount);
        }
      });
    }

    ctx.restore();
  };

  return {
    renderLocalGuides,
    renderAlignedElementMarks,
    getBounds,
  };
}
