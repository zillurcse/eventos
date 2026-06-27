// useBoothArrows.ts - ENHANCED with Improved Booth Numbering Logic
import { ref } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import type { Point, CanvasObject } from "@floorplan/types/canvas";

export function useBoothArrows() {
  const store = useCanvasStore();

  // Helper function to rotate a point around a center
  const rotatePoint = (
    point: Point,
    center: Point,
    angleDegrees: number
  ): Point => {
    const angleRad = (angleDegrees * Math.PI) / 180;
    const cos = Math.cos(angleRad);
    const sin = Math.sin(angleRad);

    const dx = point.x - center.x;
    const dy = point.y - center.y;

    return {
      x: center.x + (dx * cos - dy * sin),
      y: center.y + (dx * sin + dy * cos),
    };
  };

  const getBoothArrowRegions = (
    obj: CanvasObject,
    zoom: number,
    offset: Point
  ) => {
    if (obj.isLocked || obj.isVisible === false) {
      return [];
    }

    // ✅ UPDATED: isSelected চেক বাদ দিন যাতে hover অবস্থাতেও arrow render হয়
    if (obj.points.length < 2) return [];

    const p1 = obj.points[0];
    const p2 = obj.points[1];
    const minX = Math.min(p1.x, p2.x);
    const minY = Math.min(p1.y, p2.y);
    const width = Math.abs(p1.x - p2.x);
    const height = Math.abs(p1.y - p2.y);

    // World coordinates (before zoom/offset)
    const worldCenterX = minX + width / 2;
    const worldCenterY = minY + height / 2;
    const worldCenter = { x: worldCenterX, y: worldCenterY };

    const arrowSize = 20; // Base size in world coordinates
    const arrowOffset = 16; // Base offset in world coordinates

    const rotation = obj.rotation || 0;

    // Calculate arrow positions in world coordinates (unrotated)
    const worldArrowPositions = [
      {
        direction: "top" as const,
        worldX: worldCenterX,
        worldY: minY - arrowOffset,
      },
      {
        direction: "right" as const,
        worldX: minX + width + arrowOffset,
        worldY: worldCenterY,
      },
      {
        direction: "bottom" as const,
        worldX: worldCenterX,
        worldY: minY + height + arrowOffset,
      },
      {
        direction: "left" as const,
        worldX: minX - arrowOffset,
        worldY: worldCenterY,
      },
    ];

    // Apply rotation and convert to screen coordinates
    return worldArrowPositions.map((arrow) => {
      // Rotate arrow position around booth center
      const rotatedWorld = rotatePoint(
        { x: arrow.worldX, y: arrow.worldY },
        worldCenter,
        rotation
      );

      // Convert to screen coordinates
      const screenX = (rotatedWorld.x - offset.x) * zoom;
      const screenY = (rotatedWorld.y - offset.y) * zoom;
      const screenArrowSize = arrowSize * zoom;

      return {
        direction: arrow.direction,
        x: screenX - screenArrowSize / 2,
        y: screenY - screenArrowSize / 2,
        width: screenArrowSize,
        height: screenArrowSize,
        // Store center for easier debugging
        centerX: screenX,
        centerY: screenY,
      };
    });
  };

  const getArrowAtPoint = (
    point: Point,
    obj: CanvasObject,
    zoom: number,
    offset: Point
  ) => {
    // ✅ UPDATED: isLocked বা isVisible চেক করুন, কিন্তু isSelected চেক বাদ দিন
    // কারণ আমরা hover অবস্থাতেও arrow detect করতে চাই
    if (obj.isLocked || obj.isVisible === false) {
      return null;
    }

    const arrowRegions = getBoothArrowRegions(obj, zoom, offset);

    for (const region of arrowRegions) {
      if (
        point.x >= region.x &&
        point.x <= region.x + region.width &&
        point.y >= region.y &&
        point.y <= region.y + region.height
      ) {
        return region.direction;
      }
    }

    return null;
  };

  // ✅ ENHANCED: Improved booth number generation - Handles all patterns correctly
  const generateUniqueBoothNumber = (originalNumber: string): string => {
    console.log(`🔢 [Arrow] Generating unique from: "${originalNumber}"`);

    // Pattern 1: Hyphenated with number at end "B-1" → "B-2", "B-1-1" → "B-1-2"
    const hyphenPattern = /^(.+)-(\d+)$/;
    const hyphenMatch = originalNumber.match(hyphenPattern);

    if (hyphenMatch) {
      const prefix = hyphenMatch[1];
      let baseNumber = parseInt(hyphenMatch[2]);
      let counter = baseNumber + 1;
      let candidate = `${prefix}-${counter}`;

      while (
        store.objects.find(
          (obj) =>
            obj.type === "booth" &&
            obj.boothNumber?.toLowerCase() === candidate.toLowerCase()
        )
      ) {
        counter++;
        candidate = `${prefix}-${counter}`;
      }

      console.log(`✅ [Arrow] Generated: "${originalNumber}" → "${candidate}"`);
      return candidate;
    }

    // Pattern 2: Letter followed by number "A101" → "A102", "Booth5" → "Booth6"
    const letterNumberPattern = /^([A-Za-z]*)(\d+)$/;
    const letterMatch = originalNumber.match(letterNumberPattern);

    if (letterMatch) {
      const prefix = letterMatch[1];
      let baseNumber = parseInt(letterMatch[2]);
      let counter = baseNumber + 1;
      let candidate = `${prefix}${counter}`;

      while (
        store.objects.find(
          (obj) =>
            obj.type === "booth" &&
            obj.boothNumber?.toLowerCase() === candidate.toLowerCase()
        )
      ) {
        counter++;
        candidate = `${prefix}${counter}`;
      }

      console.log(`✅ [Arrow] Generated: "${originalNumber}" → "${candidate}"`);
      return candidate;
    }

    // Pattern 3: Plain text without numbers "Booth" → "Booth-1", "MainBooth" → "MainBooth-1"
    let counter = 1;
    let candidate = `${originalNumber}-${counter}`;

    while (
      store.objects.find(
        (obj) =>
          obj.type === "booth" &&
          obj.boothNumber?.toLowerCase() === candidate.toLowerCase()
      )
    ) {
      counter++;
      candidate = `${originalNumber}-${counter}`;
    }

    console.log(`✅ [Arrow] Generated: "${originalNumber}" → "${candidate}"`);
    return candidate;
  };

  // ✅ ENHANCED: Duplicate booth with proper boundingBox sync
  const duplicateBooth = (
    originalBooth: CanvasObject,
    direction: "top" | "right" | "bottom" | "left"
  ) => {
    if (originalBooth.isLocked) {
      console.warn("Cannot duplicate locked booth:", originalBooth.id);
      return;
    }

    const customDistance = originalBooth.boothCreationDistance;
    const offsetDistance =
      customDistance !== undefined && customDistance > 0
        ? customDistance
        : Math.max(originalBooth.length || 100, originalBooth.breadth || 100);

    console.log("📏 Using distance for booth creation:", {
      customDistance,
      offsetDistance,
      direction,
      rotation: originalBooth.rotation || 0,
    });

    // ✅ FIX: Calculate offset considering rotation
    const rotation = originalBooth.rotation || 0;
    const rotationRad = (rotation * Math.PI) / 180;

    let offsetX = 0;
    let offsetY = 0;

    // Base offset in local coordinates (before rotation)
    let localOffsetX = 0;
    let localOffsetY = 0;

    switch (direction) {
      case "top":
        localOffsetY = -offsetDistance;
        break;
      case "right":
        localOffsetX = offsetDistance;
        break;
      case "bottom":
        localOffsetY = offsetDistance;
        break;
      case "left":
        localOffsetX = -offsetDistance;
        break;
    }

    // ✅ Apply rotation transformation to the offset
    offsetX =
      localOffsetX * Math.cos(rotationRad) -
      localOffsetY * Math.sin(rotationRad);
    offsetY =
      localOffsetX * Math.sin(rotationRad) +
      localOffsetY * Math.cos(rotationRad);

    console.log("📐 Calculated offset with rotation:", {
      offsetX,
      offsetY,
      rotation,
    });

    // ✅ Generate unique booth number using improved logic
    const newBoothNumber = generateUniqueBoothNumber(
      originalBooth.boothNumber || "Booth"
    );

    // Create new booth with offset points
    const newPoints = originalBooth.points.map((p) => ({
      x: p.x + offsetX,
      y: p.y + offsetY,
    }));

    // ✅ Recalculate boundingBox from new points
    const p1 = newPoints[0];
    const p2 = newPoints[1];
    const newBoundingBox = {
      x: Math.min(p1.x, p2.x),
      y: Math.min(p1.y, p2.y),
      width: Math.abs(p2.x - p1.x),
      height: Math.abs(p2.y - p1.y),
    };

    const newBooth: CanvasObject = {
      ...originalBooth,
      id: `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
      points: newPoints,
      boothNumber: newBoothNumber,
      isSelected: true,
      isLocked: false,
      isVisible: true,
      boothCreationDistance: originalBooth.boothCreationDistance,
      boundingBox: newBoundingBox,
      rotation: originalBooth.rotation, // ✅ Preserve rotation
    };

    console.log(`📦 [Arrow] New booth created at:`, newPoints);

    // Clear all selections
    store.objects.forEach((obj) => {
      obj.isSelected = false;
    });
    store.selectedObjects = [];

    // Add the new booth
    store.addObject(newBooth);

    // Ensure the new booth is selected and boundingBox is synced
    const addedBooth = store.objects.find((obj) => obj.id === newBooth.id);
    if (addedBooth) {
      addedBooth.isSelected = true;

      if (addedBooth.points && addedBooth.points.length >= 2) {
        const ap1 = addedBooth.points[0];
        const ap2 = addedBooth.points[1];
        addedBooth.boundingBox = {
          x: Math.min(ap1.x, ap2.x),
          y: Math.min(ap1.y, ap2.y),
          width: Math.abs(ap2.x - ap1.x),
          height: Math.abs(ap2.y - ap1.y),
        };
      }

      store.selectedObjects = [addedBooth];
      console.log(
        `✅ [Arrow] Booth ${newBoothNumber} created in ${direction} direction with rotation ${rotation}°`
      );
    }
  };

  const syncBoothDrag = (boothId: string, newPoints: Point[]) => {
    const booth = store.objects.find(
      (obj) => obj.id === boothId && obj.type === "booth"
    );
    if (booth) {
      if (booth.isLocked) {
        console.warn("Cannot drag locked booth:", boothId);
        return;
      }

      store.updateObject(boothId, {
        points: newPoints,
        boundingBox: booth.boundingBox
          ? {
              x: newPoints[0].x,
              y: newPoints[0].y,
              width: Math.abs(newPoints[1].x - newPoints[0].x),
              height: Math.abs(newPoints[1].y - newPoints[0].y),
            }
          : undefined,
      });
    }
  };

  return {
    getBoothArrowRegions,
    getArrowAtPoint,
    duplicateBooth,
    syncBoothDrag,
    generateUniqueBoothNumber,
  };
}
