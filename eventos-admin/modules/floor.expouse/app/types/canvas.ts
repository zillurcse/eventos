export interface Point {
  x: number;
  y: number;
}

export interface CanvasObject {
  id: string;
  type: string;
  points: Point[];
  color: string;
  strokeWidth: number;
  isSelected: boolean;
  rotation?: number;
  boothNumber?: string;
  exhibitorCategory?: string;
  length?: number;
  breadth?: number;
  quantity?: number;
  boundingBox?: { x: number; y: number; width: number; height: number };
  zIndex?: number;
  elementData?: any;
  isLocked: boolean;
  isVisible: boolean;
  booth_name?: string;
  status?: string;
  displayOption?: string;
  boothCreationDistance?: number;

  boothNumberFontSize?: number;
  boothNumberColor?: string;
  boothNameFontSize?: number;
  boothNameColor?: string;

  // Additional properties for styling and rendering
  fillColor?: string;
  strokeColor?: string;
  opacity?: number;
  dashStyle?: string;
  lineCap?: string;
  lineJoin?: string;
  cornerRadius?: number;
  shadowOffsetX?: number;
  shadowOffsetY?: number;
  shadowColor?: string;
  shadowBlur?: number;
  lineWidth?: number;
  borderWidth?: number;
  fill?: string;
  stroke?: string;
  companyName?: string;
  isHovered?: boolean;
  label?: string;
  labelVisible?: boolean;
}

export interface DomElement {
  id: string;
  type: string;
  subtype?: string;
  position: Point;
  size: { width: number; height: number };
  rotation: number;
  content?: string;
  src?: string;
  styleProps?: {
    stroke?: string;
    strokeWidth?: number;
    fill?: string;
    fontSize?: string;
    fontWeight?: string;
    textAlign?: string;
    [key: string]: string | number | undefined;
  };
  zIndex?: number;
  isLocked?: boolean;
}

export interface Floor {
  id: string;
  name: string;
  dimensions: { length: number; width: number };
  shape_type: string;
  created_at: string;
  updated_at: string;
  objects: CanvasObject[];
  domElements: DomElement[];
  history: { past: Command[]; future: Command[] };
  zoom: number;
  offset: Point;
  wallGenerated?: boolean;
  floorArea?: {
    x: number;
    y: number;
    width: number;
    height: number;
  };
  backgroundColor?: string;
  backgroundPattern?: string;
  isVisible?: boolean;
  isLocked?: boolean;
}

export interface CanvasState {
  canvasWidth: number;
  canvasHeight: number;
  floors: Floor[];
  currentFloorId: string | null;
  objects: CanvasObject[];
  selectedObjects: CanvasObject[];
  currentTool: ToolType;
  currentColor: string;
  currentStrokeWidth: number;
  zoom: number;
  offset: Point;
  isDrawing: boolean;
  history: { past: Command[]; future: Command[] };
  domElements: DomElement[];
  selectedElementId: string | null;
  selectedDomElements: DomElement[];
  isLoading: boolean;
  MIN_ZOOM: 0.11;
  MAX_ZOOM: 5;
  ZOOM_IN: 1.2;
  ZOOM_OUT: 0.9;
}

export type ToolType =
  | "select"
  | "hand"
  | "pencil"
  | "line"
  | "arrow"
  | "curve-arrow"
  | "two-headed-arrow"
  | "rectangle"
  | "ellipse"
  | "wall"
  | "booth"
  | "text"
  | "shape"
  | "elements"
  | "frame"
  | "section";

export interface Command {
  execute(): void;
  undo(): void;
}

// Enhanced History Command for better undo/redo tracking
export interface HistoryCommand extends Command {
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
}

// Batch command for grouping multiple operations
export class BatchCommand implements HistoryCommand {
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

  private commands: Command[] = [];

  constructor(
    action: HistoryCommand["action"],
    objectType: HistoryCommand["objectType"],
    description?: string
  ) {
    this.timestamp = Date.now();
    this.action = action;
    this.objectType = objectType;
    this.description = description;
  }

  addCommand(command: Command): void {
    this.commands.push(command);
  }

  execute(): void {
    this.commands.forEach((cmd) => cmd.execute());
  }

  undo(): void {
    // Undo in reverse order
    for (let i = this.commands.length - 1; i >= 0; i--) {
      this.commands[i].undo();
    }
  }
}
