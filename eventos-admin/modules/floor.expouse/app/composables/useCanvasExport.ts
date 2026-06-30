// composables/useCanvasExport.ts
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useCanvasRendering } from "@floorplan/composables/useCanvasRendering";
import type { CanvasObject, Point } from "@floorplan/types/canvas";
import jsPDF from "jspdf";
import { loadIcon } from "@iconify/vue";

export function useCanvasExport() {
  const store = useCanvasStore();
  const canvasRendering = useCanvasRendering();

  // Add maps for icon names (copied from Element.vue)
  const shapeMap: Record<string, string> = {
    diamond: "mdi:diamond",
    pentagon: "mdi:pentagon",
    hexagon: "mdi:hexagon",
    triangle: "mdi:triangle",
    "shape-cube": "streamline-ultimate:shape-cube",
    cube: "fluent-mdl2:cube-shape",
    "free-shape-cube": "streamline-freehand-color:shape-cube",
    pyramid: "streamline-plump:pyramid-shape",
    square: "fluent-mdl2:square-shape",
    "square-filled": "fluent-mdl2:square-shape-solid",
    sphere: "streamline-sharp:sphere-shape",
    cone: "streamline:cone-shape",
    mountain: "mdi:mountain",
    "outline-cloud": "ic:outline-cloud",
    cloud: "ic:sharp-cloud",
    "flying-bird": "mdi:bird",
    bird: "lucide:bird",
    blackbird: "fluent-emoji-high-contrast:black-bird",
    "waves-birds": "lucide-lab:waves-birds",
    "nest-birds": "game-icons:nest-birds",
    camel: "hugeicons:camel",
    "fish-outline": "ion:fish-outline",
    fish: "ion:fish",
    desert: "uil:desert",
    "hill-fort": "game-icons:hill-fort",
    beach: "streamline:beach",
    shutter: "mdi:window-shutter-settings",
    garden: "guidance:garden",
    "tree-palm": "ph:tree-palm",
    "palm-tree": "fxemoji:palmtree",
    "tree-line": "mingcute:tree-line",
    evergreen: "openmoji:evergreen-tree",
    river: "game-icons:river",
    moon: "line-md:moon-loop",
    building: "mdi:building",
    gate: "guidance:tunnel",
    house: "tdesign:houses-2",
    field: "streamline-ultimate:soccer-field-bold",
    ship: "tabler:ship",
    kite: "hugeicons:kite",
    "kite-surfing": "material-symbols:kitesurfing-rounded",
    star: "heroicons:star",
  };

  const elementsMap: Record<string, string> = {
    registration: "medical-icon:i-registration",
    lounge: "arcticons:lounge",
    conference: "guidance:conference-room",
    meeting: "guidance:meeting-room",
    dining: "material-symbols:dinner-dining-outline",
    cafe: "hugeicons:cafe",
    bar: "carbon:bar",
    restroom: "fa7-solid:restroom",
    malerestroom: "grommet-icons:restroom-men",
    womenrestroom: "grommet-icons:restroom-women",
    water: "mage:water-glass-fill",
    restaurant: "material-symbols:restaurant",
    coatroom: "solar:hanger-bold",
    "round-table": "hugeicons:table-round",
    "rectangle-table": "material-symbols-light:table-large-rounded",
    sofa: "mdi:sofa",
    tree: "icon-park-outline:coconut-tree",
    seat: "mdi:seat",
    "single-door": "tabler:door",
    "double-door": "material-symbols:door-sliding-outline",
    compass: "fontisto:compass-alt",
    "entry-door": "game-icons:entry-door",
    "exit-door": "game-icons:exit-door",
    "sanitizer-station": "material-symbols:sanitizer",
    stairs: "guidance:stairs-up-person",
    handicap: "mage:handicapped",
    escalator: "mdi:escalator-up",
    "fire-extinguisher": "guidance:fire-extinguisher",
    "first-aid": "bxs:first-aid",
    "charging-point": "tabler:charging-pile",
    "emergency-exit": "guidance:emergency-exit",
    elevator: "material-symbols:elevator",
    "restricted-area": "guidance:no-entry-for-pedestrians",
    parking: "iconoir:parking",
    danger: "maki:danger",
  };

  /**
   * Check if object is a valid container (Rectangle or Wall)
   */
  const isValidContainer = (obj: CanvasObject): boolean => {
    return ["rectangle", "wall", "frame", "section"].includes(obj.type);
  };

  /**
   * Get bounding box of a container object
   */
  const getContainerBounds = (
    obj: CanvasObject
  ): { x: number; y: number; width: number; height: number } | null => {
    if (!obj.points || obj.points.length < 2) return null;

    if (["rectangle", "frame", "section"].includes(obj.type)) {
      const p1 = obj.points[0];
      const p2 = obj.points[1];
      return {
        x: Math.min(p1.x, p2.x),
        y: Math.min(p1.y, p2.y),
        width: Math.abs(p2.x - p1.x),
        height: Math.abs(p2.y - p1.y),
      };
    }

    if (obj.type === "wall") {
      const xCoords = obj.points.map((p) => p.x);
      const yCoords = obj.points.map((p) => p.y);
      const minX = Math.min(...xCoords);
      const maxX = Math.max(...xCoords);
      const minY = Math.min(...yCoords);
      const maxY = Math.max(...yCoords);

      return {
        x: minX,
        y: minY,
        width: maxX - minX,
        height: maxY - minY,
      };
    }

    return null;
  };

  /**
   * Check if a point is inside a polygon (for wall objects)
   */
  const isPointInPolygon = (point: Point, polygon: Point[]): boolean => {
    if (polygon.length < 3) return false;

    let inside = false;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
      const xi = polygon[i].x;
      const yi = polygon[i].y;
      const xj = polygon[j].x;
      const yj = polygon[j].y;

      const intersect =
        yi > point.y !== yj > point.y &&
        point.x < ((xj - xi) * (point.y - yi)) / (yj - yi) + xi;
      if (intersect) inside = !inside;
    }
    return inside;
  };

  /**
   * Check if an object is contained within the bounds
   */
  const isObjectContained = (
    obj: CanvasObject,
    containerObj: CanvasObject,
    bounds: { x: number; y: number; width: number; height: number }
  ): boolean => {
    if (!obj.points || obj.points.length === 0) return false;

    // For wall containers, use polygon containment
    if (containerObj.type === "wall" && containerObj.points.length >= 3) {
      // Check if all points of the object are inside the wall polygon
      return obj.points.every((point) =>
        isPointInPolygon(point, containerObj.points)
      );
    }

    // For rectangle containers, use bounding box containment
    const objBounds = getObjectBounds(obj);
    if (!objBounds) return false;

    // Object is contained if it's fully inside the bounds
    return (
      objBounds.x >= bounds.x &&
      objBounds.y >= bounds.y &&
      objBounds.x + objBounds.width <= bounds.x + bounds.width &&
      objBounds.y + objBounds.height <= bounds.y + bounds.height
    );
  };

  /**
   * Get bounding box of any canvas object
   */
  const getObjectBounds = (
    obj: CanvasObject
  ): { x: number; y: number; width: number; height: number } | null => {
    if (!obj.points || obj.points.length === 0) return null;

    const xCoords = obj.points.map((p) => p.x);
    const yCoords = obj.points.map((p) => p.y);

    return {
      x: Math.min(...xCoords),
      y: Math.min(...yCoords),
      width: Math.max(...xCoords) - Math.min(...xCoords),
      height: Math.max(...yCoords) - Math.min(...yCoords),
    };
  };

  /**
   * Check if a DOM element is contained within bounds
   */
  const isDomElementContained = (
    element: any,
    bounds: { x: number; y: number; width: number; height: number }
  ): boolean => {
    if (!element.position || !element.size) return false;

    const elX = element.position.x;
    const elY = element.position.y;
    const elWidth = element.size.width;
    const elHeight = element.size.height;

    return (
      elX >= bounds.x &&
      elY >= bounds.y &&
      elX + elWidth <= bounds.x + bounds.width &&
      elY + elHeight <= bounds.y + bounds.height
    );
  };

  /**
   * Get all objects and elements contained within a container
   */
  const getContainedItems = (containerObj: CanvasObject) => {
    const bounds = getContainerBounds(containerObj);
    if (!bounds) return { objects: [], elements: [] };

    const containedObjects = store.objects.filter(
      (obj) =>
        obj.id !== containerObj.id &&
        obj.isVisible !== false &&
        isObjectContained(obj, containerObj, bounds)
    );

    const containedElements = store.domElements.filter(
      (el) => el.isVisible !== false && isDomElementContained(el, bounds)
    );

    return {
      objects: containedObjects,
      elements: containedElements,
      bounds,
    };
  };

  /**
   * Create a temporary canvas for rendering
   */
  const createExportCanvas = (
    width: number,
    height: number,
    dpr: number = 2
  ): { canvas: HTMLCanvasElement; ctx: CanvasRenderingContext2D } => {
    const canvas = document.createElement("canvas");
    canvas.width = width * dpr;
    canvas.height = height * dpr;

    const ctx = canvas.getContext("2d")!;
    ctx.scale(dpr, dpr);

    // White background
    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, width, height);

    return { canvas, ctx };
  };

  /**
   * Render contained items to canvas
   */
  /**
   * Render contained items to canvas with correct z-index layering
   */
  const renderToCanvas = async (
    ctx: CanvasRenderingContext2D,
    containerObj: CanvasObject,
    containedObjects: CanvasObject[],
    containedElements: any[],
    bounds: { x: number; y: number; width: number; height: number }
  ) => {
    const zoom = 1;
    const offset = { x: bounds.x, y: bounds.y };

    // 1. Render container outline/background first (as the base)
    canvasRendering.renderObject(ctx, containerObj, zoom, offset);

    // 2. Merge CanvasObjects and DOM Elements into a single list
    const allItems = [
      ...containedObjects.map((o) => ({
        type: "object",
        data: o,
        zIndex: o.zIndex ?? 0,
      })),
      ...containedElements.map((e) => ({
        type: "element",
        data: e,
        zIndex: e.zIndex ?? 0,
      })),
    ];

    // 3. Sort by Z-Index to ensure correct layering
    allItems.sort((a, b) => a.zIndex - b.zIndex);

    // 4. Render loop
    for (const item of allItems) {
      if (item.type === "object") {
        canvasRendering.renderObject(ctx, item.data, zoom, offset);
      } else {
        const el = item.data;
        const x = (el.position.x - offset.x) * zoom;
        const y = (el.position.y - offset.y) * zoom;
        const width = el.size.width * zoom;
        const height = el.size.height * zoom;
        const style = el.styleProps || {};

        ctx.save();

        // Apply rotation
        if (el.rotation) {
          const centerX = x + width / 2;
          const centerY = y + height / 2;
          ctx.translate(centerX, centerY);
          ctx.rotate((el.rotation * Math.PI) / 180);
          ctx.translate(-centerX, -centerY);
        }

        // Apply global opacity
        ctx.globalAlpha = style.opacity !== undefined ? style.opacity : 1;

        // Apply generic box styles (Background & Border)
        // Note: Canvas doesn't support border-radius natively on fillRect/strokeRect easily
        // We'll use a path for rounded rectangles if needed, or keeping it simple for now.
        if (style.backgroundColor && style.backgroundColor !== "transparent") {
          ctx.fillStyle = style.backgroundColor;
          ctx.fillRect(x, y, width, height);
        }

        // Text Rendering
        if (el.type === "text") {
          ctx.fillStyle = style.color || "#000000";
          ctx.font = `${style.fontStyle || "normal"} ${style.fontWeight || "normal"} ${style.fontSize || 24}px ${style.fontFamily || "Verdana"}`.trim();
          
          // Text Align & Baseline (Aligning with Element.vue's flex-center behavior)
          const align = style.textAlign || "center"; // Default to center for single lines often looks best
          ctx.textAlign = align as CanvasTextAlign;
          ctx.textBaseline = "middle";

          let textX = x;
          if (align === "center") textX = x + width / 2;
          else if (align === "right") textX = x + width;

          // Text Shadow
          if (style.shadowOffsetX || style.shadowOffsetY || style.shadowBlur) {
            ctx.shadowColor = style.shadowColor || "#000000";
            ctx.shadowBlur = style.shadowBlur || 0;
            ctx.shadowOffsetX = style.shadowOffsetX || 0;
            ctx.shadowOffsetY = style.shadowOffsetY || 0;
          }

          // Handle multi-line text
          const content = el.content || "Double click to edit"; // Placeholder if empty
          const lines = content.split("\n");
          const lineHeightVal = (style.fontSize || 24) * (style.lineHeight || 1.2);
          const totalTextHeight = lines.length * lineHeightVal;
          let currentY = y + (height - totalTextHeight) / 2 + lineHeightVal / 2; // Vertical center

          lines.forEach((line: string) => {
            // Text Transform
            let textToDraw = line;
            if (style.textTransform === "uppercase") textToDraw = line.toUpperCase();
            else if (style.textTransform === "lowercase") textToDraw = line.toLowerCase();
            else if (style.textTransform === "capitalize") textToDraw = line.replace(/\b\w/g, c => c.toUpperCase());

            ctx.fillText(textToDraw, textX, currentY);
            currentY += lineHeightVal;
          });
          
          // Reset Shadow
          ctx.shadowColor = "transparent";
          ctx.shadowBlur = 0;
          ctx.shadowOffsetX = 0;
          ctx.shadowOffsetY = 0;
        
        } else if (el.type === "image" && el.src) {
          const img = new Image();
          img.crossOrigin = "anonymous";
          img.src = el.src;
          await new Promise((resolve) => {
            img.onload = resolve;
            img.onerror = resolve; // Continue on error
          });
          // Object-contain logic (mimic)
          // For simplicity in export, we stretch to fit or centered fit could be added
          ctx.drawImage(img, x, y, width, height);

        } else if (el.type === "shape" && el.subtype === "arrow" && el.path) {
          // Render Custom SVG Arrow Path
          const p = new Path2D(el.path);
          ctx.translate(x, y); // Coordinates in path are usually regular, might need adjustment if relative
          // Actually, 'path' in Element.vue seems to be drawn inside an SVG which fills the div.
          // SVG scales 'none' (preserveAspectRatio="none").
          // We need to scale the context to match the width/height vs the assumed coordinate space of the path?
          // The path logic in Element.vue (useArrow.ts) likely generates coordinates based on 0,0 to width,height.
          // Let's assume the path is relative to the element's box.
          
          ctx.strokeStyle = style.stroke || "#333333";
          ctx.lineWidth = style.strokeWidth || 2;
          ctx.lineCap = "round";
          ctx.lineJoin = "round";
          ctx.stroke(p);
          
          // Draw Arrowhead if needed (would need to parse polygon points)
          // Skipping complex arrowhead parsing for this iteration to avoid regressions
          
          ctx.translate(-x, -y);

        } else if (el.type === "booth") {
          ctx.fillStyle = "#d1d5db";
          ctx.fillRect(x, y, width, height);
          ctx.strokeStyle = "#000000";
          ctx.strokeRect(x, y, width, height);
          ctx.fillStyle = "#000000";
          ctx.font = "16px Arial";
          ctx.textAlign = "center";
          ctx.textBaseline = "middle";
          ctx.fillText("Booth", x + width / 2, y + height / 2);

        } else if ((el.type === "shape" || el.type === "elements") && el.subtype) {
          // Icon Rendering
          
          let svgString = "";
          let iconName: string | undefined;
          
          if (el.type === "shape") iconName = shapeMap[el.subtype];
          else if (el.type === "elements") iconName = elementsMap[el.subtype];

          // If not in map, assume subtype IS the icon name
          if (!iconName && el.subtype && el.subtype.includes(":")) {
             iconName = el.subtype;
          }

          if (iconName) {
              const [collection, icon] = iconName.split(":");
              let body = "", widthV = 24, heightV = 24, left = 0, top = 0;

              // 1. Try Local Bundle (Offline)
              const iconData = loadIcon(collection, icon);
              if (iconData) {
                 widthV = iconData.width;
                 heightV = iconData.height;
                 left = iconData.left;
                 top = iconData.top;
                 body = iconData.body;
              } 
              
              // 2. If no local data, fetch from API
              if (!body) {
                 try {
                    const response = await fetch(`https://api.iconify.design/${collection}/${icon}.svg`);
                    if (response.ok) {
                       svgString = await response.text();
                    }
                 } catch (e) {
                   console.warn(`Failed to fetch icon ${iconName} from API`, e);
                 }
              } else {
                 // Construct SVG from parts
                 svgString = `<svg xmlns="http://www.w3.org/2000/svg" width="${widthV}" height="${heightV}" viewBox="${left} ${top} ${widthV} ${heightV}" fill="${style.color || '#000000'}">${body}</svg>`;
              }
          }

          if (svgString) {
              // Ensure color is applied
              if (svgString.includes("currentColor")) {
                 svgString = svgString.replaceAll("currentColor", style.color || "#000000");
              }
              if (!svgString.includes("fill=") && !svgString.includes("stroke=")) {
                 svgString = svgString.replace("<svg", `<svg fill="${style.color || '#000000'}"`);
              }

              const blob = new Blob([svgString], { type: "image/svg+xml;charset=utf-8" });
              const url = URL.createObjectURL(blob);
              const img = new Image();
              
              await new Promise((resolve) => {
                  img.onload = resolve;
                  img.onerror = () => {
                     console.warn("Failed to load SVG Blob for export", iconName);
                     resolve(null);
                  };
                  img.src = url;
              });

              // ✅ FIX: Maintain Aspect Ratio & Center
              // Calculate aspect ratios
              const imgRatio = (img.naturalWidth || 24) / (img.naturalHeight || 24);
              const boxRatio = width / height;

              let renderWidth = width;
              let renderHeight = height;
              let renderX = x;
              let renderY = y;

              // If image is wider than box (relative to ratios), fit by width
              if (imgRatio > boxRatio) {
                 renderHeight = width / imgRatio;
                 renderY = y + (height - renderHeight) / 2;
              } else {
                 // If image is taller than box, fit by height
                 renderWidth = height * imgRatio;
                 renderX = x + (width - renderWidth) / 2;
              }
              
              ctx.drawImage(img, renderX, renderY, renderWidth, renderHeight);
              URL.revokeObjectURL(url);
          } else {
             // Placeholder
             // ...
          }
        }

        // Apply Border (Generic for all elements)
        if (style.borderWidth && style.borderWidth > 0 && style.borderColor) {
          ctx.strokeStyle = style.borderColor;
          ctx.lineWidth = style.borderWidth;
          // ctx.setLineDash(style.borderStyle === 'dashed' ? [5, 5] : []); // Optional
          ctx.strokeRect(x, y, width, height);
        }

        ctx.restore();
      }
    }
  };

  /**
   * Helper to trigger download of a Blob
   */
  const downloadBlob = (blob: Blob, filename: string) => {
    // Create a URL for the blob
    const url = window.URL.createObjectURL(blob);
    
    // Create temporary link
    const link = document.createElement("a");
    link.href = url;
    link.download = filename; // This is the standard way to set filename
    link.style.display = "none";
    
    // Append to body (required for Firefox/Chrome to trigger download reliably)
    document.body.appendChild(link);
    
    // Trigger download
    link.click();
    
    // Clean up
    setTimeout(() => {
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
    }, 1000);
  };

  /**
   * Export as PNG
   */
  const exportAsPNG = async (containerObj: CanvasObject) => {
    if (!isValidContainer(containerObj)) {
      console.error("Selected object is not a valid container");
      return;
    }

    const { objects, elements, bounds } = getContainedItems(containerObj);

    if (!bounds) {
      console.error("Could not determine container bounds");
      return;
    }

    console.log(
      `📸 Exporting PNG: ${objects.length} objects, ${elements.length} elements`
    );

    // Create export canvas
    const padding = 20;
    const exportWidth = bounds.width + padding * 2;
    const exportHeight = bounds.height + padding * 2;
    const { canvas, ctx } = createExportCanvas(exportWidth, exportHeight);

    // Adjust offset for padding
    const adjustedBounds = {
      x: bounds.x - padding,
      y: bounds.y - padding,
      width: bounds.width,
      height: bounds.height,
    };

    // Render content
    ctx.translate(padding, padding);
    await renderToCanvas(ctx, containerObj, objects, elements, bounds);

    // Wrap toBlob in Promise to ensure we await it
    return new Promise<void>((resolve, reject) => {
      canvas.toBlob((blob) => {
        if (!blob) {
          console.error("❌ Failed to create PNG blob");
          alert("Failed to generate PNG image.");
          reject(new Error("Blob creation failed"));
          return;
        }

        try {
          const filename = `export-${containerObj.type}-${Date.now()}.png`;
          downloadBlob(blob, filename);
          console.log(`✅ PNG export initiated: ${filename}`);
          resolve();
        } catch (err) {
          console.error("PNG Download Error:", err);
          reject(err);
        }
      }, "image/png");
    });
  };

  /**
   * Export as PDF
   */
  const exportAsPDF = async (containerObj: CanvasObject) => {
    if (!isValidContainer(containerObj)) {
      console.error("Selected object is not a valid container");
      return;
    }

    const { objects, elements, bounds } = getContainedItems(containerObj);

    if (!bounds) {
      console.error("Could not determine container bounds");
      return;
    }

    console.log(
      `📄 Exporting PDF: ${objects.length} objects, ${elements.length} elements`
    );

    // Create export canvas
    const padding = 20;
    const exportWidth = bounds.width + padding * 2;
    const exportHeight = bounds.height + padding * 2;
    const { canvas, ctx } = createExportCanvas(exportWidth, exportHeight, 3);

    // Render content with padding
    ctx.save();
    ctx.translate(padding, padding); // Account for DPR
    await renderToCanvas(ctx, containerObj, objects, elements, bounds);
    ctx.restore();

    // Create PDF
    try {
      const imgData = canvas.toDataURL("image/png");
      const pdf = new jsPDF({
        orientation: exportWidth > exportHeight ? "landscape" : "portrait",
        unit: "px",
        format: [exportWidth, exportHeight],
      });

      pdf.addImage(imgData, "PNG", 0, 0, exportWidth, exportHeight);
      
      const filename = `export-${containerObj.type}-${Date.now()}.pdf`;
      
      // Get raw blob from jsPDF
      const blob = pdf.output("blob");
      downloadBlob(blob, filename);

      console.log(`✅ PDF export initiated: ${filename}`);
    } catch (e) {
      console.error("❌ Failed to generate PDF data. Likely CORS issue.", e);
       alert("Export failed. If you used external images, they might be blocking secure export.");
    }
  };

  /**
   * Check if current selection is a valid container
   */
  const canExport = (): boolean => {
    if (store.selectedObjects.length !== 1) return false;
    return isValidContainer(store.selectedObjects[0]);
  };

  /**
   * Get selected container object
   */
  const getSelectedContainer = (): CanvasObject | null => {
    if (!canExport()) return null;
    return store.selectedObjects[0];
  };

  /**
   * Get ALL content from the canvas with dynamic bounds
   */
  const getAllContent = () => {
      // 1. Calculate Bounds of ALL objects
      let minX = Infinity;
      let minY = Infinity;
      let maxX = -Infinity;
      let maxY = -Infinity;
      let foundContent = false;

      // Check Objects
      store.objects.forEach(obj => {
          if (obj.isVisible === false) return;
          const b = getObjectBounds(obj);
          if (b) {
              foundContent = true;
              minX = Math.min(minX, b.x);
              minY = Math.min(minY, b.y);
              maxX = Math.max(maxX, b.x + b.width);
              maxY = Math.max(maxY, b.y + b.height);
          }
      });

      // Check DOM Elements
      store.domElements.forEach(el => {
          if (el.isVisible === false) return;
          foundContent = true;
          const x = el.position.x;
          const y = el.position.y;
          const w = el.size.width;
          const h = el.size.height;
          
          minX = Math.min(minX, x);
          minY = Math.min(minY, y);
          maxX = Math.max(maxX, x + w);
          maxY = Math.max(maxY, y + h);
      });

      // If no content, return default floor bounds or fallback
      if (!foundContent) {
         // Default to current floor dimensions if available, else standard
         const floor = store.floors.find(f => f.id === store.currentFloorId);
         return {
             objects: [],
             elements: [],
             bounds: { 
                 x: 0, 
                 y: 0, 
                 width: floor?.dimensions?.length || 1000, 
                 height: floor?.dimensions?.width || 800 
             }
         };
      }

      return {
          objects: store.objects.filter(o => o.isVisible !== false),
          elements: store.domElements.filter(e => e.isVisible !== false),
          bounds: {
              x: minX,
              y: minY,
              width: maxX - minX,
              height: maxY - minY
          }
      };
  };

  return {
    canExport,
    getSelectedContainer,
    getContainedItems,
    getAllContent,

    // NEW: Get Blob directly (for uploading)
    getCanvasBlob: async (containerObj?: CanvasObject | null): Promise<Blob | null> => {
       let objects, elements, bounds;
       let containerToRender: CanvasObject | null = containerObj || null;

       if (containerObj) {
           // Export specific container
           const data = getContainedItems(containerObj);
           objects = data.objects;
           elements = data.elements;
           bounds = data.bounds;
       } else {
           // Export FULL CANVAS
           const data = getAllContent();
           objects = data.objects;
           elements = data.elements;
           bounds = data.bounds;
           
           // Create a virtual container to represent the background/bounds
           containerToRender = {
               id: "virtual-root",
               type: "rectangle",
               points: [
                   {x: bounds.x, y: bounds.y}, 
                   {x: bounds.x + bounds.width, y: bounds.y}, 
                   {x: bounds.x + bounds.width, y: bounds.y + bounds.height}, 
                   {x: bounds.x, y: bounds.y + bounds.height}
               ],
               x: bounds.x,
               y: bounds.y,
               width: bounds.width,
               height: bounds.height,
               fill: "#ffffff", // White background
               stroke: "transparent", // NO BORDER
               strokeWidth: 0,
               zIndex: -9999,
               isVisible: true,
               isLocked: true,
               rotation: 0
           };
       }

       if (!bounds || !containerToRender) {
         console.error("Could not determine bounds for getCanvasBlob");
         return null;
       }

       const padding = 20;
       const exportWidth = bounds.width + padding * 2;
       const exportHeight = bounds.height + padding * 2;
       const { canvas, ctx } = createExportCanvas(exportWidth, exportHeight);

       ctx.translate(padding, padding);
       // We pass containerToRender as the first argument, and the content lists
       await renderToCanvas(ctx, containerToRender, objects, elements, bounds);
       
       return new Promise((resolve) => {
         canvas.toBlob((blob) => {
           resolve(blob);
           canvas.width = 0;
           canvas.height = 0;
         }, "image/png");
       });
    },

    exportAsPNG,
    exportAsPDF,
    // Assuming exportAsJSON is a function that would be defined elsewhere if needed
    // exportAsJSON, // re-export if needed
    isValidContainer,
  };
}
