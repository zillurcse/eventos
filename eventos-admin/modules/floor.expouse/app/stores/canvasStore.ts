import { defineStore } from "pinia";
import localForage from "localforage";
import { useFloorsApi } from "@floorplan/composables/api/useFloorsApi";
import {
  formatNumberWithDecimals,
  parseFormattedNumber,
} from "@floorplan/utils/numberFormat";

import type {
  CanvasState,
  CanvasObject,
  ToolType,
  Point,
  Command,
  Floor,
  DomElement,
  HistoryCommand,
  BatchCommand,
} from "@floorplan/types/canvas";

const deepCloneForStorage = (obj: any, seen = new WeakMap()): any => {
  if (obj === null || obj === undefined) {
    return obj;
  }

  if (seen.has(obj)) {
    return seen.get(obj);
  }

  if (obj instanceof Date) {
    return obj.toISOString();
  }

  if (Array.isArray(obj)) {
    const arrCopy: any[] = [];
    seen.set(obj, arrCopy);
    obj.forEach((item, index) => {
      arrCopy[index] = deepCloneForStorage(item, seen);
    });
    return arrCopy;
  }

  if (typeof obj === "object") {
    if (
      obj instanceof Function ||
      obj instanceof HTMLElement ||
      obj instanceof Event
    ) {
      return undefined;
    }

    const objCopy: any = {};
    seen.set(obj, objCopy);

    for (const key in obj) {
      if (key.startsWith("_") || !obj.hasOwnProperty(key)) {
        continue;
      }

      try {
        const value = obj[key];

        if (
          typeof value === "function" ||
          typeof value === "symbol" ||
          value === undefined
        ) {
          continue;
        }

        objCopy[key] = deepCloneForStorage(value, seen);
      } catch (error) {
        console.warn(`Skipping non-serializable property: ${key}`, error);
        continue;
      }
    }
    return objCopy;
  }

  return obj;
};

const calculateFloorArea = (
  dimensions: { length: number; width: number },
  canvasWidth: number,
  canvasHeight: number
) => {
  const centerX = formatNumberWithDecimals(canvasWidth / 2 || 600);
  const centerY = formatNumberWithDecimals(canvasHeight / 2 || 400);
  const areaLength = formatNumberWithDecimals(dimensions.length * 0.9);
  const areaWidth = formatNumberWithDecimals(dimensions.width * 0.9);

  return {
    x: formatNumberWithDecimals(centerX - areaLength / 2),
    y: formatNumberWithDecimals(centerY - areaWidth / 2),
    width: formatNumberWithDecimals(areaLength),
    height: formatNumberWithDecimals(areaWidth),
  };
};

function clonePoint(p: Point): Point {
  return { x: p.x, y: p.y };
}

function cloneObject(obj: CanvasObject): CanvasObject {
  return {
    ...obj,
    points: obj.points.map(clonePoint),
    boundingBox: obj.boundingBox ? { ...obj.boundingBox } : undefined,
    zIndex: obj.zIndex ?? 0,
  };
}

function cloneDomElement(el: DomElement): DomElement {
  return {
    id: el.id,
    type: el.type,
    subtype: el.subtype,
    position: clonePoint(el.position),
    size: { ...el.size },
    rotation: el.rotation,
    content: el.content,
    src: el.src,
    styleProps: el.styleProps ? { ...el.styleProps } : undefined,
    zIndex: el.zIndex ?? 0,
    isLocked: el.isLocked ?? false,
  };
}

// Helper function to get default booth creation distance from localStorage
const getDefaultBoothDistance = (): number => {
  if (process.client) {
    try {
      const saved = localStorage.getItem("default-booth-distance");
      if (saved) {
        const parsed = parseInt(saved);
        if (!isNaN(parsed) && parsed > 0) {
          return parsed;
        }
      }
    } catch (error) {
      console.warn(
        "Failed to load default booth distance from localStorage:",
        error
      );
    }
  }
  return 100; // Default fallback
};

// Existing Command Classes (preserved)
class AddElementCommand implements Command {
  constructor(private elements: DomElement[], private element: DomElement) {
    this.element = cloneDomElement(element);
  }

  execute(): void {
    this.elements.push(this.element);
  }

  undo(): void {
    const index = this.elements.findIndex((e) => e.id === this.element.id);
    if (index !== -1) this.elements.splice(index, 1);
  }
}

class DeleteElementsCommand implements Command {
  private deletedElements: DomElement[];

  constructor(
    private elements: DomElement[],
    private selectedElementId: string | null
  ) {
    this.deletedElements = [];
    if (selectedElementId) {
      const element = elements.find((e) => e.id === selectedElementId);
      if (element) {
        this.deletedElements.push(cloneDomElement(element));
      }
    }
  }

  execute(): void {
    if (this.deletedElements.length > 0) {
      const index = this.elements.findIndex(
        (e) => e.id === this.deletedElements[0].id
      );
      if (index !== -1) this.elements.splice(index, 1);
    }
  }

  undo(): void {
    this.deletedElements.forEach((element) =>
      this.elements.push(cloneDomElement(element))
    );
  }
}

class UpdateElementCommand implements Command {
  private oldValues: Partial<DomElement> = {};
  private newValues: Partial<DomElement>;

  constructor(
    private elements: DomElement[],
    private id: string,
    updates: Partial<DomElement>
  ) {
    this.newValues = { ...updates };
    const element = elements.find((e) => e.id === id);
    if (element) {
      for (const key in updates) {
        this.oldValues[key] =
          key === "position"
            ? clonePoint(element.position)
            : key === "size"
            ? { ...element.size }
            : element[key];
      }
    }
  }

  execute(): void {
    const element = this.elements.find((e) => e.id === this.id);
    if (element) {
      for (const key in this.newValues) {
        element[key] =
          key === "position"
            ? clonePoint(this.newValues[key] as Point)
            : key === "size"
            ? { ...(this.newValues[key] as any) }
            : this.newValues[key];
      }
    }
  }

  undo(): void {
    const element = this.elements.find((e) => e.id === this.id);
    if (element) {
      for (const key in this.oldValues) {
        element[key] =
          key === "position"
            ? clonePoint(this.oldValues[key] as Point)
            : key === "size"
            ? { ...(this.oldValues[key] as any) }
            : this.oldValues[key];
      }
    }
  }
}

class AddObjectCommand implements Command {
  constructor(private objects: CanvasObject[], private object: CanvasObject) {
    this.object = cloneObject(object);
  }

  execute(): void {
    this.objects.push(this.object);
  }

  undo(): void {
    const index = this.objects.findIndex((o) => o.id === this.object.id);
    if (index !== -1) this.objects.splice(index, 1);
  }
}

class UpdateObjectCommand implements Command {
  private oldValues: Partial<CanvasObject> = {};
  private newValues: Partial<CanvasObject>;

  constructor(
    private objects: CanvasObject[],
    private id: string,
    updates: Partial<CanvasObject>
  ) {
    this.newValues = { ...updates };
    const obj = objects.find((o) => o.id === id);
    if (obj) {
      for (const key in updates) {
        this.oldValues[key] =
          key === "points"
            ? (obj[key] as Point[]).map(clonePoint)
            : key === "boundingBox"
            ? { ...(obj[key] as any) }
            : obj[key];
      }
    }
  }

  execute(): void {
    const obj = this.objects.find((o) => o.id === this.id);
    if (obj) {
      for (const key in this.newValues) {
        obj[key] =
          key === "points"
            ? (this.newValues[key] as Point[]).map(clonePoint)
            : key === "boundingBox"
            ? { ...(this.newValues[key] as any) }
            : this.newValues[key];
      }
    }
  }

  undo(): void {
    const obj = this.objects.find((o) => o.id === this.id);
    if (obj) {
      for (const key in this.oldValues) {
        obj[key] =
          key === "points"
            ? (this.oldValues[key] as Point[]).map(clonePoint)
            : key === "boundingBox"
            ? { ...(this.oldValues[key] as any) }
            : this.oldValues[key];
      }
    }
  }
}

class DeleteObjectsCommand implements Command {
  private deletedObjects: CanvasObject[];

  constructor(
    private objects: CanvasObject[],
    private selectedObjects: CanvasObject[]
  ) {
    this.deletedObjects = selectedObjects.map(cloneObject);
  }

  execute(): void {
    const remaining = this.objects.filter(
      (obj) => !this.selectedObjects.includes(obj)
    );
    this.objects.length = 0;
    this.objects.push(...remaining);
    this.selectedObjects.length = 0;
  }

  undo(): void {
    this.deletedObjects.forEach((obj) => this.objects.push(cloneObject(obj)));
  }
}

class ClearCanvasCommand implements Command {
  private previousObjects: CanvasObject[];
  private previousDomElements: DomElement[];

  constructor(
    private objects: CanvasObject[],
    private selectedObjects: CanvasObject[],
    private domElements: DomElement[]
  ) {
    this.previousObjects = objects.map(cloneObject);
    this.previousDomElements = domElements.map(cloneDomElement);
  }

  execute(): void {
    this.objects.length = 0;
    this.selectedObjects.length = 0;
    this.domElements.length = 0;
  }

  undo(): void {
    this.objects.length = 0;
    this.objects.push(...this.previousObjects.map(cloneObject));
    this.selectedObjects.length = 0;
    this.domElements.length = 0;
    this.domElements.push(...this.previousDomElements.map(cloneDomElement));
  }
}

// NEW: Enhanced command for better tracking
class EnhancedCommand implements HistoryCommand {
  timestamp: number;
  action:
    | "create"
    | "delete"
    | "move"
    | "duplicate"
    | "lock"
    | "unlock"
    | "modify"
    | "paste";
  objectType: "canvas" | "dom" | "mixed";
  description?: string;

  constructor(
    private baseCommand: Command,
    action: HistoryCommand["action"],
    objectType: HistoryCommand["objectType"],
    description?: string
  ) {
    this.timestamp = Date.now();
    this.action = action;
    this.objectType = objectType;
    this.description = description;
  }

  execute(): void {
    this.baseCommand.execute();
  }

  undo(): void {
    this.baseCommand.undo();
  }
}

export const useCanvasStore = defineStore("canvas", {
  state: (): CanvasState => ({
    canvasWidth: 0,
    canvasHeight: 0,
    floors: [],
    currentFloorId: null,
    objects: [],
    selectedObjects: [],
    currentTool: "select",
    currentColor: "#000000",
    currentStrokeWidth: 0,
    zoom: 1,
    offset: { x: 0, y: 0 },
    isDrawing: false,
    history: {
      past: [] as Command[],
      future: [] as Command[],
    },
    domElements: [],
    selectedElementId: null,
    selectedDomElements: [],
    isLoading: true,
    MIN_ZOOM: 0.11,
    MAX_ZOOM: 5,
    ZOOM_IN: 1.2,
    ZOOM_OUT: 0.9,
  }),

  actions: {
    // NEW: Enhanced history push with metadata
    pushToHistory(
      command: Command,
      action: HistoryCommand["action"] = "modify",
      objectType: HistoryCommand["objectType"] = "canvas",
      description?: string
    ) {
      const enhancedCmd = new EnhancedCommand(
        command,
        action,
        objectType,
        description
      );

      // ✅ CORRECT: Clear future AND push ENHANCED command
      this.history.future = [];
      this.history.past.push(enhancedCmd); // ← Use enhancedCmd, not command

      // Limit history size
      const MAX_HISTORY = 100;
      if (this.history.past.length > MAX_HISTORY) {
        this.history.past.shift();
      }

      console.log(
        `📝 History: ${action} - ${description || "unnamed operation"} (${
          this.history.past.length
        } actions)`
      );
    },

    getMaxZIndex(): number {
      const maxObjectZIndex = Math.max(
        0,
        ...this.objects.map((o) => o.zIndex ?? 0)
      );
      const maxDomElementZIndex = Math.max(
        0,
        ...this.domElements.map((e) => e.zIndex ?? 0)
      );
      return Math.max(maxObjectZIndex, maxDomElementZIndex);
    },

    cleanupProblematicData() {
      console.log("🧹 Cleaning up problematic data...");

      this.floors = this.floors.map((floor) => {
        const cleanFloor: any = {
          id: floor.id,
          name: floor.name,
          dimensions: floor.dimensions,
          shape_type: floor.shape_type,
          created_at: floor.created_at,
          updated_at: floor.updated_at,
          objects: [],
          domElements: [],
          history: { past: [], future: [] },
          zoom: floor.zoom || 1,
          offset: floor.offset || { x: 0, y: 0 },
        };

        cleanFloor.objects = floor.objects.map((obj) => {
          const cleanObj: any = {
            id: obj.id,
            type: obj.type,
            points: obj.points?.map((p) => ({ x: p.x, y: p.y })) || [],
            color: obj.color || "#000000",
            strokeWidth: obj.strokeWidth || 2,
            rotation: obj.rotation || 0,
            zIndex: obj.zIndex || 0,
            isLocked: obj.isLocked || false,
            isVisible: obj.isVisible !== false,
          };

          const allowedProps = [
            "boothNumber",
            "length",
            "breadth",
            "quantity",
            "status",
            "companyName",
            "displayOption",
            "booth_name",
          ];

          allowedProps.forEach((prop) => {
            if (obj[prop] !== undefined) {
              cleanObj[prop] = obj[prop];
            }
          });

          if (obj.boundingBox) {
            cleanObj.boundingBox = {
              x: obj.boundingBox.x,
              y: obj.boundingBox.y,
              width: obj.boundingBox.width,
              height: obj.boundingBox.height,
            };
          }

          return cleanObj;
        });

        cleanFloor.domElements = floor.domElements.map((el) => {
          const cleanEl: any = {
            id: el.id,
            type: el.type,
            subtype: el.subtype,
            position: { x: el.position.x, y: el.position.y },
            size: { width: el.size.width, height: el.size.height },
            rotation: el.rotation || 0,
            zIndex: el.zIndex || 0,
            isLocked: el.isLocked || false,
          };

          if (el.content !== undefined) cleanEl.content = el.content;
          if (el.src !== undefined) cleanEl.src = el.src;

          if (el.styleProps) {
            cleanEl.styleProps = {};
            for (const key in el.styleProps) {
              const value = el.styleProps[key];
              if (
                typeof value === "string" ||
                typeof value === "number" ||
                typeof value === "boolean"
              ) {
                cleanEl.styleProps[key] = value;
              }
            }
          }

          return cleanEl;
        });

        if (floor.floorArea) {
          cleanFloor.floorArea = {
            x: floor.floorArea.x,
            y: floor.floorArea.y,
            width: floor.floorArea.width,
            height: floor.floorArea.height,
          };
        }

        return cleanFloor;
      });

      console.log("✅ Data cleanup completed");
    },

    async init() {
      const floorsApi = useFloorsApi();
      try {
        console.log("📄 Initializing canvas store (READ-ONLY)...");

        let savedFloorId: string | null = null;
        try {
          savedFloorId = await localForage.getItem("currentFloorId");
          console.log(`📥 Loaded saved floor selection: ${savedFloorId}`);
        } catch (e) {
          console.warn("Could not load saved floor selection:", e);
        }

        try {
          console.log("📡 Attempting to fetch floors from API...");
          const apiFloors = await floorsApi.getFloors();
          this.isLoading = false;

          if (apiFloors && apiFloors.length > 0) {
            console.log(`📥 Loaded ${apiFloors.length} floors from API`);
            this.floors = apiFloors.map(this.normalizeFloorData);

            const validSavedFloor =
              savedFloorId && this.floors.find((f) => f.id === savedFloorId);
            this.currentFloorId = validSavedFloor
              ? savedFloorId
              : this.floors[0].id;

            this.loadFloorData(this.currentFloorId);
            await this.loadFloorOnPageReload();

            console.log(
              `✅ Canvas store initialized from API - selected floor: ${this.currentFloorId}`
            );
            return;
          } else {
            console.log(
              "🔭 No floors found in API, creating default floor in database..."
            );

            const defaultFloorData = {
              name: "Default Floor",
              dimensions: { length: 1200, width: 800 },
              shape_type: "rectangular",
              objects: [
                {
                  id: `Floor-Wall-${Date.now()}`,
                  type: "wall",
                  color: "#000000",
                  points: [
                    { x: 99, y: 100 },
                    { x: 101, y: 901 },
                    { x: 1781, y: 901 },
                    { x: 1780, y: 100 },
                    { x: 97, y: 100 },
                    { x: 97, y: 100 },
                  ],
                  zIndex: 1,
                  isLocked: false,
                  rotation: 0,
                  isVisible: true,
                  strokeWidth: 4,
                },
              ],
              dom_elements: [],
              zoom: 1,
              offset: { x: 0, y: 0 },
              wall_generated: false,
            };

            try {
              const newFloor = await floorsApi.createFloor(defaultFloorData);
              console.log("✅ Default floor created in database:", newFloor);

              this.floors = [this.normalizeFloorData(newFloor)];
              this.currentFloorId = newFloor.id.toString();

              this.loadFloorData(this.currentFloorId);

              console.log(
                `✅ Canvas store initialized with new database floor: ${this.currentFloorId}`
              );
              return;
            } catch (createError) {
              console.error(
                "❌ Failed to create default floor in database:",
                createError
              );
              throw createError;
            }
          }
        } catch (apiError) {
          console.warn(
            "API not available, falling back to localStorage:",
            apiError
          );
        }

        try {
          console.log("📡 Attempting to load from localStorage...");
          const saved = (await localForage.getItem("canvasState")) as any;
          if (saved?.floors && saved.floors.length > 0) {
            console.log(
              `📥 Loaded ${saved.floors.length} saved floors from localStorage`
            );
            this.floors = saved.floors.map(this.normalizeFloorData);

            this.currentFloorId =
              savedFloorId || saved.currentFloorId || this.floors[0]?.id;

            if (this.currentFloorId) {
              this.loadFloorData(this.currentFloorId);
            }

            console.log(
              `✅ Canvas store initialized from localStorage - selected floor: ${this.currentFloorId}`
            );
            return;
          } else {
            console.log("🔭 No floors found in localStorage");
          }
        } catch (localStorageError) {
          console.warn("LocalStorage load failed:", localStorageError);
        }

        if (!this.floors.length) {
          console.log(
            "🆕 Creating default floor locally (no existing data and database creation failed)"
          );
          await this.createDefaultFloor();
        }

        this.cleanupProblematicData();
        console.log(
          `✅ Canvas store initialized successfully - selected floor: ${this.currentFloorId}`
        );
      } catch (e) {
        console.error("❌ Failed to initialize canvas state:", e);
        if (!this.floors.length) {
          await this.createDefaultFloor();
        }
      }
    },

    async loadFloorOnPageReload() {
      console.log("🔄 Loading floor data on page reload...");

      if (!this.currentFloorId) {
        console.warn("⚠️ No current floor ID set, skipping page reload load");
        return;
      }

      const floorsApi = useFloorsApi();

      try {
        console.log(
          `📡 Fetching latest data for floor ${this.currentFloorId} from API...`
        );
        const floorData = await floorsApi.getFloor(this.currentFloorId);

        if (floorData) {
          const normalizedFloor = this.normalizeFloorData(floorData);
          const existingFloorIndex = this.floors.findIndex(
            (f) => f.id === this.currentFloorId
          );

          if (existingFloorIndex !== -1) {
            this.floors[existingFloorIndex] = normalizedFloor;
            console.log(
              `✅ Updated floor ${this.currentFloorId} with latest API data`
            );

            this.loadFloorData(this.currentFloorId);
          }
        }
      } catch (error) {
        console.warn(
          `⚠️ Could not fetch latest data for floor ${this.currentFloorId} on page reload, using cached data:`,
          error
        );
        this.loadFloorData(this.currentFloorId);
      }
    },

    loadFloorData(floorId: string) {
      const floor = this.floors.find((f) => f.id === floorId);
      if (!floor) return;

      this.objects.length = 0;
      this.selectedObjects.length = 0;
      this.domElements.length = 0;
      this.history.past.length = 0;
      this.history.future.length = 0;

      this.objects.push(...floor.objects.map(cloneObject));
      this.domElements.push(...floor.domElements.map(cloneDomElement));
      this.zoom = floor.zoom || 1;
      this.offset = clonePoint(floor.offset || { x: 0, y: 0 });

      console.log(`📊 Loaded floor data for: ${floorId} (no auto-save)`);
    },

    async switchFloor(floorId: string) {
      try {
        await localForage.setItem("currentFloorId", floorId);
        console.log(`💾 Saved floor selection: ${floorId}`);
      } catch (e) {
        console.warn("Could not save floor selection:", e);
      }

      console.log(`🔄 Switching to floor: ${floorId} (READ-ONLY OPERATION)`);

      const floorsApi = useFloorsApi();

      try {
        console.log(`📡 Fetching latest data for floor ${floorId} from API...`);
        const floorData = await floorsApi.getFloor(floorId);

        if (floorData) {
          const normalizedFloor = this.normalizeFloorData(floorData);
          const existingFloorIndex = this.floors.findIndex(
            (f) => f.id === floorId
          );

          if (existingFloorIndex !== -1) {
            this.floors[existingFloorIndex] = normalizedFloor;
            console.log(`✅ Updated floor ${floorId} with latest API data`);
          }
        }
      } catch (error) {
        console.warn(
          `⚠️ Could not fetch latest data for floor ${floorId}, using cached data:`,
          error
        );
      }

      if (this.currentFloorId) {
        const currentFloor = this.floors.find(
          (f) => f.id === this.currentFloorId
        );

        if (currentFloor) {
          currentFloor.objects = this.objects.map(cloneObject);
          currentFloor.domElements = this.domElements.map(cloneDomElement);
          currentFloor.history = { past: [], future: [] };
          currentFloor.zoom = this.zoom;
          currentFloor.offset = clonePoint(this.offset);
          currentFloor.updated_at = new Date().toISOString();
        }
      }

      const newFloor = this.floors.find((f) => f.id === floorId);

      this.objects.length = 0;
      this.selectedObjects.length = 0;
      this.domElements.length = 0;
      this.history.past.length = 0;
      this.history.future.length = 0;
      this.zoom = 1;
      this.offset = { x: 0, y: 0 };
      this.selectedElementId = null;

      if (newFloor) {
        this.objects.push(...newFloor.objects.map(cloneObject));
        this.domElements.push(...newFloor.domElements.map(cloneDomElement));

        const hasWalls = newFloor.objects.some((obj) => obj.type === "wall");
        const hasAnyObjects = newFloor.objects.length > 0;

        if (!hasAnyObjects && !newFloor.wallGenerated) {
          console.log(
            "🆕 Generating initial wall box for completely fresh floor"
          );
          this.generateWallBoxForFloor(newFloor.dimensions, newFloor.floorArea);

          const currentFloor = this.floors.find((f) => f.id === floorId);
          if (currentFloor) {
            currentFloor.wallGenerated = true;
          }
        }

        this.zoom = newFloor.zoom;
        this.offset = clonePoint(newFloor.offset);
      }

      this.currentFloorId = floorId;
      this.updateCurrentFloor();

      console.log(`✅ Switched to floor: ${floorId} (read-only, no auto-save)`);
    },

    async createDefaultFloor() {
      const now = new Date().toISOString();
      this.floors.push({
        id: "1",
        name: "Default Floor",
        dimensions: { length: 1200, width: 800 },
        shape_type: "rectangular",
        created_at: now,
        updated_at: now,
        objects: [],
        domElements: [],
        history: { past: [], future: [] },
        zoom: 1,
        offset: { x: 0, y: 0 },
        wallGenerated: false,
      });
      this.currentFloorId = "1";

      console.log("🆕 Created default floor locally (no auto-save)");
    },

    normalizeFloorData(floor: any): Floor {
      return {
        id: floor.id?.toString() || Math.random().toString(36).substring(2),
        name: floor.name || "Unnamed Floor",
        dimensions: floor.dimensions || { length: 1200, width: 800 },
        shape_type: floor.shape_type || "rectangular",
        objects:
          floor.objects?.map((o: any) => ({
            ...o,
            points: o.points?.map((p: any) => ({ x: p.x, y: p.y })) ?? [],
            color: o.color || "#000000",
            strokeWidth: o.strokeWidth || 2,
            isSelected: false,
            rotation: o.rotation || 0,
            zIndex: o.zIndex || 0,
            isLocked: o.isLocked || false,
            isVisible: o.isVisible !== false,
          })) ?? [],
        domElements:
          floor.dom_elements?.map((e: any) => ({
            id: e.id || Math.random().toString(36).substring(2),
            type: e.type || "unknown",
            subtype: e.subtype,
            position: e.position || { x: 0, y: 0 },
            size: e.size || { width: 100, height: 50 },
            rotation: e.rotation || 0,
            content: e.content,
            src: e.src,
            styleProps: e.styleProps || undefined,
            zIndex: e.zIndex || 0,
            isLocked: e.isLocked || false,
          })) ?? [],
        history: { past: [], future: [] },
        zoom: floor.zoom || 1,
        offset: floor.offset || { x: 0, y: 0 },
        floorArea: floor.floor_area || undefined,
        wallGenerated:
          floor.wall_generated ??
          ((floor.objects &&
            floor.objects.some((obj: any) => obj.type === "wall")) ||
            false),
        created_at: floor.created_at || new Date().toISOString(),
        updated_at: floor.updated_at || new Date().toISOString(),
      };
    },

    async saveToLocalStorage() {
      try {
        if (!this.currentFloorId) return;

        await localForage.setItem("currentFloorId", this.currentFloorId);

        const currentFloor = this.floors.find(
          (f) => f.id === this.currentFloorId
        );
        if (!currentFloor) return;

        this.updateCurrentFloor();
        const floorsApi = useFloorsApi();

        const floorData = {
          name: currentFloor.name,
          dimensions: currentFloor.dimensions,
          floor_area: currentFloor.floorArea,
          shape_type: currentFloor.shape_type,
          objects: currentFloor.objects,
          dom_elements: currentFloor.domElements,
          offset: currentFloor.offset,
          zoom: currentFloor.zoom,
          wall_generated: currentFloor.wallGenerated,
        };

        try {
          if (currentFloor.id && parseInt(currentFloor.id)) {
            await floorsApi.updateFloor(currentFloor.id, floorData);
            console.log("✅ Successfully saved floor to API");
          } else {
            const newFloor = await floorsApi.createFloor(floorData);
            currentFloor.id = newFloor.id.toString();
            this.currentFloorId = currentFloor.id;
            console.log("✅ Successfully created floor in API");
          }
        } catch (apiError) {
          console.warn(
            "API save failed, using localStorage fallback:",
            apiError
          );

          this.cleanupProblematicData();

          const serializableState = {
            floors: this.floors,
            currentFloorId: this.currentFloorId,
          };

          const finalState = deepCloneForStorage(serializableState);
          await localForage.setItem("canvasState", finalState);
          console.log("✅ Successfully saved to localStorage fallback");
        }
      } catch (error) {
        console.error("❌ Failed to save canvas state:", error);
        this.debugSerialization();
      }
    },

    debugSerialization() {
      console.group("🔍 Serialization Debug Info");

      try {
        this.floors.forEach((floor, index) => {
          try {
            const testFloor = JSON.parse(JSON.stringify(floor));
            console.log(`✅ Floor ${index} (${floor.name}): Serializable`);
          } catch (floorError) {
            console.error(
              `❌ Floor ${index} (${floor.name}): Not serializable`,
              floorError
            );

            Object.keys(floor).forEach((key) => {
              try {
                JSON.stringify(floor[key]);
              } catch (propError) {
                console.error(`   Problematic property: ${key}`, propError);
              }
            });
          }
        });

        const testState = {
          floors: this.floors,
          currentFloorId: this.currentFloorId,
        };

        JSON.stringify(testState);
        console.log("✅ Full state is serializable");
      } catch (error) {
        console.error("❌ State serialization failed:", error);
      }

      console.groupEnd();
    },

    async recoverFromCorruption() {
      console.warn("🔄 Attempting data recovery from corruption...");

      try {
        const savedFloorId = await localForage.getItem("currentFloorId");
        await localForage.removeItem("canvasState");

        this.floors = [
          {
            id: "1",
            name: "Recovered Floor",
            dimensions: { length: 1200, width: 800 },
            shape_type: "rectangular",
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
            objects: [],
            domElements: [],
            history: { past: [], future: [] },
            zoom: 1,
            offset: { x: 0, y: 0 },
          },
        ];

        this.currentFloorId = savedFloorId || "1";

        await this.saveToLocalStorage();
        console.log("✅ Data recovery completed");
      } catch (error) {
        console.error("❌ Data recovery failed:", error);
        throw error;
      }
    },

    trackSaveOperation(source: string) {
      console.log(`💾 Save operation triggered by: ${source}`);
      console.trace("Save call stack");
    },

    async manualSave() {
      this.trackSaveOperation("manual-save");
      console.log("💾 Manual save triggered by user action");
      try {
        this.updateCurrentFloor();
        await this.saveToLocalStorage();
      } catch (error) {
        console.error("❌ Save failed, attempting recovery:", error);
        if (error.name === "DataCloneError" || error.name === "TypeError") {
          await this.recoverFromCorruption();
        } else {
          throw error;
        }
      }
    },

    async save() {
      return this.manualSave();
    },

    setTool(tool: ToolType) {
      this.currentTool = tool;
    },

    getBoothById(id: string) {
      return this.objects.find((obj) => obj.type === "booth" && obj.id === id);
    },

    addBooth(boothData: any) {
      const boothNumber = boothData.boothNumber.trim();
      const existingBooth = this.objects.find(
        (obj) =>
          obj.type === "booth" &&
          obj.boothNumber?.toLowerCase() === boothNumber.toLowerCase()
      );

      if (existingBooth) {
        console.error(`Booth number ${boothNumber} already exists`);
        return;
      }

      const center = { x: 400, y: 300 };
      const halfLength = boothData.length / 2;
      const halfBreadth = boothData.breadth / 2;
      const quantity =
        boothData.type === "single" ? 1 : boothData.quantity || 1;
      const createdBooths: CanvasObject[] = [];
      let currentCenter = center;

      for (let i = 0; i < quantity; i++) {
        const points = [
          { x: currentCenter.x - halfLength, y: currentCenter.y - halfBreadth },
          { x: currentCenter.x + halfLength, y: currentCenter.y + halfBreadth },
        ];
        let boothNumber = boothData.boothNumber;

        if (i > 0) {
          const match = boothData.boothNumber.match(/^([A-Za-z]*)(\d+)$/);
          if (match) {
            boothNumber = `${match[1]}${parseInt(match[2]) + i}`;
          } else {
            boothNumber = `${boothData.boothNumber}-${i + 1}`;
          }

          let uniqueBoothNumber = boothNumber;
          let counter = 1;
          while (
            this.objects.find(
              (obj) =>
                obj.type === "booth" &&
                obj.boothNumber?.toLowerCase() ===
                  uniqueBoothNumber.toLowerCase()
            )
          ) {
            uniqueBoothNumber = `${boothNumber}-${counter}`;
            counter++;
          }
          boothNumber = uniqueBoothNumber;
        }

        const booth: CanvasObject = {
          id: `${Date.now()}-${i}`,
          type: "booth",
          points,
          color: "#00FF00",
          strokeWidth: 2,
          isSelected: false,
          rotation: 0,
          boothNumber,
          length: boothData.length,
          breadth: boothData.breadth,
          quantity,
          boundingBox: {
            x: points[0].x,
            y: points[0].y,
            width: boothData.length,
            height: boothData.breadth,
          },
          zIndex: this.getMaxZIndex() + 1,
          status: "AVAILABLE",
          booth_name: boothData.booth_name || "",
          displayOption: "booth_name",
          isLocked: false,
          isVisible: true,
          boothCreationDistance:
            boothData.boothCreationDistance || getDefaultBoothDistance(),
        };

        this.addObject(booth);
        createdBooths.push(booth);
        currentCenter.x += boothData.length;
      }

      this.selectedObjects = createdBooths;
      this.selectedElementId = null;
      this.setTool("select");
    },

    addObject(object: CanvasObject) {
      const cleanObject: CanvasObject = {
        id: object.id,
        type: object.type,
        points: object.points.map((p) => ({ x: p.x, y: p.y })),
        color: object.color,
        strokeWidth: object.strokeWidth || 2,
        isSelected: object.isSelected || false,
        rotation: object.rotation || 0,
        zIndex: object.zIndex || this.getMaxZIndex() + 1,
        isLocked: object.isLocked || false,
        isVisible: object.isVisible !== false,
      };

      if (object.boothNumber) cleanObject.boothNumber = object.boothNumber;
      if (object.length) cleanObject.length = object.length;
      if (object.breadth) cleanObject.breadth = object.breadth;
      if (object.quantity) cleanObject.quantity = object.quantity;
      if (object.status) cleanObject.status = object.status;
      if (object.companyName) cleanObject.companyName = object.companyName;
      if (object.displayOption)
        cleanObject.displayOption = object.displayOption;
      if (object.booth_name) cleanObject.booth_name = object.booth_name;
      if (object.boothCreationDistance !== undefined)
        cleanObject.boothCreationDistance = object.boothCreationDistance;
      
      // NEW: Preserve Label and Styling properties
      if (object.label !== undefined) cleanObject.label = object.label;
      if (object.labelVisible !== undefined) cleanObject.labelVisible = object.labelVisible;
      if (object.cornerRadius !== undefined) cleanObject.cornerRadius = object.cornerRadius;
      if (object.fillColor !== undefined) cleanObject.fillColor = object.fillColor;
      if (object.strokeColor !== undefined) cleanObject.strokeColor = object.strokeColor;
      if (object.dashStyle !== undefined) cleanObject.dashStyle = object.dashStyle;
      if (object.lineCap !== undefined) cleanObject.lineCap = object.lineCap;
      if (object.lineJoin !== undefined) cleanObject.lineJoin = object.lineJoin;
      if (object.opacity !== undefined) cleanObject.opacity = object.opacity;
      if (object.shadowOffsetX !== undefined) cleanObject.shadowOffsetX = object.shadowOffsetX;
      if (object.shadowOffsetY !== undefined) cleanObject.shadowOffsetY = object.shadowOffsetY;
      if (object.shadowColor !== undefined) cleanObject.shadowColor = object.shadowColor;
      if (object.shadowBlur !== undefined) cleanObject.shadowBlur = object.shadowBlur;
      if (object.blur !== undefined) cleanObject.blur = object.blur;

      if (object.boundingBox) {
        cleanObject.boundingBox = {
          x: object.boundingBox.x,
          y: object.boundingBox.y,
          width: object.boundingBox.width,
          height: object.boundingBox.height,
        };
      }

      const cmd = new AddObjectCommand(this.objects, cleanObject);
      cmd.execute();
      this.pushToHistory(cmd, "create", "canvas", `Created ${object.type}`);
    },

    updateObject(id: string, updates: Partial<CanvasObject>) {
      const cmd = new UpdateObjectCommand(this.objects, id, updates);
      cmd.execute();
      this.pushToHistory(cmd, "modify", "canvas", `Updated object ${id}`);
    },

    // In canvasStore.ts actions section
    updateFloorDimensions(
      floorId: string,
      dimensions: { length: number; width: number },
      shouldSave: boolean = true
    ) {
      const floor = this.floors.find((f) => f.id === floorId);
      if (!floor) return;

      // Calculate ratios for visual resizing
      const oldLength = floor.dimensions?.length || dimensions.length;
      const oldWidth = floor.dimensions?.width || dimensions.width;
      
      const lengthRatio = oldLength > 0 ? dimensions.length / oldLength : 1;
      const widthRatio = oldWidth > 0 ? dimensions.width / oldWidth : 1;

      // Update stored dimensions
      floor.dimensions = { ...dimensions };
      floor.updated_at = new Date().toISOString();

      // If this is the current floor, regenerate walls
      if (this.currentFloorId === floorId) {
        // Capture existing wall style before deletion
        const existingWall = this.objects.find((obj) => obj.type === "wall" && obj.id.includes("Floor-"));
        const styleOptions = existingWall ? {
            strokeColor: existingWall.strokeColor || existingWall.color,
            strokeWidth: existingWall.strokeWidth,
            opacity: existingWall.opacity,
            color: existingWall.color
        } : undefined;

        // Resize the visual floor area if it exists
        // Handle recovery from 0/invalid dimensions by recalculating fresh if needed
        if (floor.floorArea && oldLength > 1 && oldWidth > 1) {
          const oldArea = floor.floorArea;
          
          // Only resize if ratios are finite
          if (isFinite(lengthRatio) && isFinite(widthRatio)) {
              const newAreaWidth = oldArea.width * lengthRatio;
              const newAreaHeight = oldArea.height * widthRatio;
              
              const centerX = oldArea.x + oldArea.width / 2;
              const centerY = oldArea.y + oldArea.height / 2;
              
              floor.floorArea = {
                x: centerX - newAreaWidth / 2,
                y: centerY - newAreaHeight / 2,
                width: newAreaWidth,
                height: newAreaHeight
              };
               console.log("📏 Resized floor area visually:", floor.floorArea);
          }
        } else {
             // Fallback: If dimensions were 0 or missing, calculate fresh responsive area
             console.log("⚠️ Dimensions invalid or reset, recalculating responsive area");
             floor.floorArea = this.calculateResponsiveWallArea(dimensions);
        }

        // Remove existing walls
        this.objects = this.objects.filter(
          (obj) => obj.type !== "wall" && obj.type !== "door-arc"
        );

        // Regenerate walls with new dimensions, updated area, and PRESERVED STYLE
        this.generateWallBoxForFloor(dimensions, floor.floorArea, styleOptions);
      }

      if (shouldSave) {
        this.save();
      }
    },

    updateFloorAppearance(
      floorId: string, 
      updates: Partial<CanvasObject>,
      shouldSave: boolean = true
    ) {
       // Filter objects that are part of the floor structure (walls and doors)
       const structuralObjects = this.objects.filter(
         (obj) => (obj.type === 'wall' || obj.type === 'door-arc') && 
                  (obj.floorId === floorId || obj.id.includes('Floor-'))
       );

       if (structuralObjects.length > 0) {
           console.log(`🎨 Updating appearance for ${structuralObjects.length} floor objects`);
           structuralObjects.forEach(obj => {
               this.updateObject(obj.id, updates);
           });
           
           if (shouldSave) {
               this.save();
           }
       }
    },

    getMinZIndex(): number {
      const minObjectZIndex = Math.min(
        0,
        ...this.objects.map((o) => o.zIndex ?? 0)
      );
      const minDomElementZIndex = Math.min(
        0,
        ...this.domElements.map((e) => e.zIndex ?? 0)
      );
      return Math.min(minObjectZIndex, minDomElementZIndex);
    },

    bringToFront() {
        const maxZ = this.getMaxZIndex();
        this.updateSelectedZIndex(maxZ + 1);
    },

    bringForward() {
        const currentZ = this.getSelectedZIndex();
        this.updateSelectedZIndex(currentZ + 1);
    },

    sendBackward() {
        const currentZ = this.getSelectedZIndex();
        this.updateSelectedZIndex(currentZ - 1);
    },

    sendToBack() {
        const minZ = this.getMinZIndex();
        this.updateSelectedZIndex(minZ - 1);
    },

    getSelectedZIndex(): number {
         if (this.selectedElementId) {
             return this.domElements.find(e => e.id === this.selectedElementId)?.zIndex || 0;
         }
         if (this.selectedObjects.length > 0) {
             return this.selectedObjects[0].zIndex || 0;
         }
         return 0;
    },

    updateSelectedZIndex(newZ: number) {
        if (this.selectedElementId) {
            this.updateElement(this.selectedElementId, { zIndex: newZ });
        } else if (this.selectedObjects.length > 0) {
            this.selectedObjects.forEach(obj => {
                this.updateObject(obj.id, { zIndex: newZ });
            });
        }
    },

    updateElementData(id: string, updates: any) {
      const obj = this.objects.find((o) => o.id === id);
      if (obj?.elementData) {
        const updatedElementData = { ...obj.elementData, ...updates };
        this.updateObject(id, {
          elementData: updatedElementData,
          points:
            updates.position || updates.size
              ? [
                  {
                    x: updatedElementData.position.x,
                    y: updatedElementData.position.y,
                  },
                  {
                    x:
                      updatedElementData.position.x +
                      updatedElementData.size.width,
                    y:
                      updatedElementData.position.y +
                      updatedElementData.size.height,
                  },
                ]
              : obj.points,
          rotation:
            updates.rotation !== undefined ? updates.rotation : obj.rotation,
        });
      }
    },

    deleteSelected() {
      this.deleteElement();
    },

    clearCanvas() {
      const cmd = new ClearCanvasCommand(
        this.objects,
        this.selectedObjects,
        this.domElements
      );
      cmd.execute();
      this.pushToHistory(cmd, "delete", "mixed", "Cleared canvas");
    },

    undo() {
      if (this.history.past.length === 0) {
        console.log("Nothing to undo");
        return;
      }

      const enhancedCmd = this.history.past.pop()!; // This is now EnhancedCommand
      enhancedCmd.undo();

      this.history.future.push(enhancedCmd);
      console.log(
        `↶ Undo: ${enhancedCmd.description || "unknown"} | Past: ${
          this.history.past.length
        }`
      );
      this.updateCurrentFloor();
    },

    redo() {
      if (this.history.future.length === 0) {
        console.log("Nothing to redo");
        return;
      }

      const enhancedCmd = this.history.future.pop()!;
      enhancedCmd.execute();

      this.history.past.push(enhancedCmd);
      console.log(
        `▶️ Redo: ${enhancedCmd.description || "unknown"} | Future: ${
          this.history.future.length
        }`
      );
      this.updateCurrentFloor();
    },

    clearHistory() {
      this.history.past = [];
      this.history.future = [];
    },

    setZoom(zoom: number) {
      this.zoom = Math.max(this.MIN_ZOOM, Math.min(this.MAX_ZOOM, zoom));
    },

    zoomIn() {
      if (this.zoom >= this.MAX_ZOOM) return;
      this.setZoom(this.zoom * this.ZOOM_IN);
    },

    zoomOut() {
      if (this.zoom <= this.MIN_ZOOM) return;
      this.setZoom(this.zoom * this.ZOOM_OUT);
    },

    async createFloor(
      event_id: string,
      token: string,
      name: string,
      dimensions: { length: number; width: number },
      shapeType: string = "rectangular"
    ) {
      try {
        const floorData = {
          event_id,
          token,
          name,
          dimensions,
          shape_type: shapeType,
          objects: [],
          dom_elements: [],
          zoom: 1,
          offset: { x: 0, y: 0 },
          wall_generated: false,
        };

        const floorsApi = useFloorsApi();

        let newFloor: Floor;
        try {
          newFloor = await floorsApi.createFloor(floorData);
          console.log("✅ Floor created in API");
        } catch (apiError) {
          console.warn("API create failed, creating locally:", apiError);
          const newId = (
            Math.max(0, ...this.floors.map((f) => parseInt(f.id) || 0)) + 1
          ).toString();
          newFloor = {
            ...floorData,
            id: newId,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
            history: { past: [], future: [] },
            floorArea: this.calculateResponsiveWallArea(dimensions),
          } as Floor;
        }

        this.floors.push(this.normalizeFloorData(newFloor));
        this.switchFloor(newFloor.id.toString());

        console.log(`✅ Created new floor: ${name}`);
      } catch (error) {
        console.error("❌ Failed to create floor:", error);
        throw error;
      }
    },

    updateObjectPosition(id: string, newPoints: Point[]) {
      this.updateObject(id, { points: newPoints.map(clonePoint) });
    },

    calculateResponsiveWallArea(dimensions: { length: number; width: number }) {
      const screenWidthCm = window.innerWidth / this.zoom;
      const screenHeightCm = window.innerHeight / this.zoom;

      const wallLength = dimensions.length;
      const wallWidth = dimensions.width;

      const scaleX = screenWidthCm / wallLength;
      const scaleY = screenHeightCm / wallWidth;
      const scale = Math.min(scaleX, scaleY, 1);

      const scaledLength = wallLength * scale;
      const scaledWidth = wallWidth * scale;

      const areaX = (screenWidthCm - scaledLength) / 2;
      const areaY = (screenHeightCm - scaledWidth) / 2;

      return {
        x: areaX,
        y: areaY,
        width: scaledLength,
        height: scaledWidth,
      };
    },

    generateWallBoxForFloor(
      dimensions: { length: number; width: number },
      floorArea?: { x: number; y: number; width: number; height: number },
      styleOptions?: { strokeColor?: string; strokeWidth?: number; opacity?: number; color?: string }
    ) {
      const existingWall = this.objects.find((obj) => obj.type === "wall");
      if (existingWall) {
        console.log(
          "🚫 Wall box already exists in current objects, skipping generation"
        );
        return;
      }

      console.log("🗺️ Creating new wall rectangle with 4 doors for floor");

      let area = floorArea;
      if (!area) {
        area = this.calculateResponsiveWallArea(dimensions);
      }

      const wallThickness = 4;
      const doorWidth = 60;
      const doorDepth = 20;

      const topDoorX = area.x + area.width / 2;
      const bottomDoorX = area.x + area.width / 2;
      const leftDoorY = area.y + area.height / 2;
      const rightDoorY = area.y + area.height / 2;

      const wallSegments = [
        {
          id: `Floor-Wall-Top-Left-${Date.now()}`,
          points: [
            { x: area.x, y: area.y },
            { x: topDoorX - doorWidth / 2, y: area.y },
          ],
        },
        {
          id: `Floor-Wall-Top-Right-${Date.now()}`,
          points: [
            { x: topDoorX + doorWidth / 2, y: area.y },
            { x: area.x + area.width, y: area.y },
          ],
        },
        {
          id: `Floor-Wall-Right-Top-${Date.now()}`,
          points: [
            { x: area.x + area.width, y: area.y },
            { x: area.x + area.width, y: rightDoorY - doorWidth / 2 },
          ],
        },
        {
          id: `Floor-Wall-Right-Bottom-${Date.now()}`,
          points: [
            { x: area.x + area.width, y: rightDoorY + doorWidth / 2 },
            { x: area.x + area.width, y: area.y + area.height },
          ],
        },
        {
          id: `Floor-Wall-Bottom-Right-${Date.now()}`,
          points: [
            { x: area.x + area.width, y: area.y + area.height },
            { x: bottomDoorX + doorWidth / 2, y: area.y + area.height },
          ],
        },
        {
          id: `Floor-Wall-Bottom-Left-${Date.now()}`,
          points: [
            { x: bottomDoorX - doorWidth / 2, y: area.y + area.height },
            { x: area.x, y: area.y + area.height },
          ],
        },
        {
          id: `Floor-Wall-Left-Bottom-${Date.now()}`,
          points: [
            { x: area.x, y: area.y + area.height },
            { x: area.x, y: leftDoorY + doorWidth / 2 },
          ],
        },
        {
          id: `Floor-Wall-Left-Top-${Date.now()}`,
          points: [
            { x: area.x, y: leftDoorY - doorWidth / 2 },
            { x: area.x, y: area.y },
          ],
        },
      ];

      wallSegments.forEach((segment) => {
        this.addObject({
          id: segment.id,
          type: "wall",
          points: segment.points,
          color: styleOptions?.color || "#000000",
          strokeColor: styleOptions?.strokeColor || "#000000",
          strokeWidth: styleOptions?.strokeWidth || wallThickness,
          opacity: styleOptions?.opacity ?? 1,
          isSelected: false,
          rotation: 0,
          zIndex: 0,
          isLocked: false,
          isVisible: true,
          floorId: this.currentFloorId,
        });
      });

      const doors = [
        {
          id: `Floor-Door-Top-${Date.now()}`,
          points: [
            { x: topDoorX - doorWidth / 2, y: area.y },
            { x: topDoorX + doorWidth / 2, y: area.y },
            { x: topDoorX, y: area.y - doorDepth },
          ],
        },
        {
          id: `Floor-Door-Right-${Date.now()}`,
          points: [
            { x: area.x + area.width, y: rightDoorY - doorWidth / 2 },
            { x: area.x + area.width, y: rightDoorY + doorWidth / 2 },
            { x: area.x + area.width + doorDepth, y: rightDoorY },
          ],
        },
        {
          id: `Floor-Door-Bottom-${Date.now()}`,
          points: [
            { x: bottomDoorX + doorWidth / 2, y: area.y + area.height },
            { x: bottomDoorX - doorWidth / 2, y: area.y + area.height },
            { x: bottomDoorX, y: area.y + area.height + doorDepth },
          ],
        },
        {
          id: `Floor-Door-Left-${Date.now()}`,
          points: [
            { x: area.x, y: leftDoorY + doorWidth / 2 },
            { x: area.x, y: leftDoorY - doorWidth / 2 },
            { x: area.x - doorDepth, y: leftDoorY },
          ],
        },
      ];

      doors.forEach((door) => {
        this.addObject({
          id: door.id,
          type: "door-arc",
          points: door.points,
          color: styleOptions?.color || "#000000",
          strokeColor: styleOptions?.strokeColor || "#000000",
          strokeWidth: styleOptions?.strokeWidth || 2, // Default door width if not provided
          opacity: styleOptions?.opacity ?? 1,
          isSelected: false,
          rotation: 0,
          zIndex: 0,
          isLocked: false,
          isVisible: true,
          floorId: this.currentFloorId,
        });
      });

      console.log("✅ Wall rectangle with 4 doors created successfully");

      if (this.currentFloorId) {
        const floor = this.floors.find((f) => f.id === this.currentFloorId);
        if (floor) {
          floor.wallGenerated = true;
          if (!floor.floorArea) {
            floor.floorArea = area;
          }
        }
      }
    },

    constrainToFloorArea(
      position: Point,
      size: { width: number; height: number }
    ): Point {
      if (!this.currentFloorId) return position;

      const currentFloor = this.floors.find(
        (f) => f.id === this.currentFloorId
      );
      if (!currentFloor?.floorArea) return position;

      const area = currentFloor.floorArea;
      const constrainedX = Math.max(
        area.x,
        Math.min(area.x + area.width - size.width, position.x)
      );
      const constrainedY = Math.max(
        area.y,
        Math.min(area.y + area.height - size.height, position.y)
      );

      return { x: constrainedX, y: constrainedY };
    },

    updateCurrentFloor() {
      if (this.currentFloorId) {
        const currentFloor = this.floors.find(
          (f) => f.id === this.currentFloorId
        );
        if (currentFloor) {
          currentFloor.objects = this.objects.map((obj) => {
            const cleanObj: any = { ...obj };
            delete cleanObj.isSelected;
            delete cleanObj.elementData;
            return cleanObj;
          });

          currentFloor.domElements = this.domElements.map((el) => {
            const cleanEl: any = { ...el };
            return cleanEl;
          });

          currentFloor.zoom = this.zoom;
          currentFloor.offset = { ...this.offset };
          currentFloor.updated_at = new Date().toISOString();
        }
      }
    },

    addElement(type: string, options: any) {
      const id = Date.now().toString();
      const viewportWidth = window.innerWidth;
      const viewportHeight = window.innerHeight;

      let size = { width: 200 / this.zoom, height: 100 / this.zoom };
      
      // ✅ IMAGE & ICONS (shape, elements) should be square
      if (["image", "shape", "elements"].includes(type)) {
        size = { width: 100 / this.zoom, height: 100 / this.zoom };
      }

      const position = {
        x: this.offset.x + viewportWidth / 2 / this.zoom - size.width / 2,
        y: this.offset.y + viewportHeight / 2 / this.zoom - size.height / 2,
      };

      const constrainedPosition = this.constrainToFloorArea(position, size);

      const sanitizedStyleProps = options.styleProps
        ? Object.fromEntries(
            Object.entries(options.styleProps).filter(
              ([_, value]) =>
                typeof value === "string" || typeof value === "number"
            )
          )
        : undefined;

      const element: DomElement = {
        id,
        type,
        subtype: options.subtype,
        position: constrainedPosition,
        size,
        rotation: 0,
        content: type === "text" ? "" : options.content,
        src: options.src,
        styleProps: sanitizedStyleProps,
        zIndex: 1, // Default z-index 1 as requested
        isLocked: false,
        isVisible: true,
      };

      const cmd = new AddElementCommand(this.domElements, element);
      cmd.execute();
      this.pushToHistory(cmd, "create", "dom", `Created ${type} element`);

      this.selectedElementId = id;
      this.selectedObjects = [];

      this.updateCurrentFloor();

      return id;
    },

    updateElement(id: string, updates: Partial<DomElement>) {
      const element = this.domElements.find((e) => e.id === id);
      if (element && updates.position) {
        // Uncomment if constraint is needed:
        // updates.position = this.constrainToFloorArea(updates.position, element.size);
      }

      const cmd = new UpdateElementCommand(this.domElements, id, updates);
      cmd.execute();
      this.pushToHistory(cmd, "modify", "dom", `Updated element ${id}`);
      this.updateCurrentFloor();
    },

    deleteElement() {
      if (this.selectedElementId) {
        const cmd = new DeleteElementsCommand(
          this.domElements,
          this.selectedElementId
        );
        cmd.execute();
        this.pushToHistory(cmd, "delete", "dom", "Deleted element");
        this.selectedElementId = null;
        this.updateCurrentFloor();
        return;
      }

      if (this.selectedObjects.length > 0) {
        const cmd = new DeleteObjectsCommand(
          this.objects,
          this.selectedObjects
        );
        cmd.execute();
        this.pushToHistory(
          cmd,
          "delete",
          "canvas",
          `Deleted ${this.selectedObjects.length} objects`
        );
        this.selectedObjects = [];
        this.updateCurrentFloor();
        return;
      }

      console.log("Nothing selected – delete ignored");
    },
  },
});
