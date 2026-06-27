// types/canvasAlignment.types.ts
// Centralized alignment type definitions

import type { CanvasObject } from "@floorplan/types/canvas";

/**
 * Local alignment guide - limited to nearby object bounds
 * Used by the new optimized dragging system
 */
export interface LocalAlignmentGuide {
  type: "vertical" | "horizontal";
  position: number;
  start: number; // Guide line start position (limited scope)
  end: number; // Guide line end position (limited scope)
  alignment: "left" | "right" | "top" | "bottom" | "centerX" | "centerY";
}

/**
 * Legacy alignment guide - full canvas scope
 * @deprecated Use LocalAlignmentGuide instead
 */
export interface AlignmentGuide {
  type: "vertical" | "horizontal";
  position: number;
  sourceObject: CanvasObject;
  targetObject: CanvasObject;
  alignment: "left" | "right" | "top" | "bottom" | "centerX" | "centerY";
}
