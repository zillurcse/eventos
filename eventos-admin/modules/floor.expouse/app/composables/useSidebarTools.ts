// useSidebarTools.ts - WITH FILTERED SHAPE AND ELEMENT TOOLS
import { useUiStore } from "@floorplan/stores/uiStore";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import { useAssetsStore } from "@floorplan/stores/assetsStore";

export function useSidebarTools() {
  const uiStore = useUiStore();
  const canvasStore = useCanvasStore();
  const assetsStore = useAssetsStore();

  // ────────────────────────────────
  // 🧰 TOOL DEFINITIONS
  // ────────────────────────────────
  const tools = [
    {
      id: "select",
      label: "Select Tool",
      icon: "move.svg",
      shortcut: "V",
      subItems: [
        // { id: "move", label: "Move", icon: "move.svg", shortcut: "V" }, // User commented this out
        { id: "select", label: "Select", icon: "select.svg", shortcut: "V" }, 
        { id: "hand", label: "Hand Tool", icon: "hand.svg", shortcut: "H" },
      ],
    },
    {
      id: "frame",
      label: "Frame Tool",
      icon: "frame.svg",
      shortcut: "F",
      subItems: [
        { id: "frame", label: "Frame", icon: "frame.svg", shortcut: "F" },
        { id: "section", label: "Section", icon: "section.svg", shortcut: "S" },
      ],
    },
    {
      id: "drawing",
      label: "Rectangle Tool",
      icon: "rectangle.svg",
      shortcut: "M",
      subItems: [
        { id: "rectangle", label: "Rectangle", icon: "rectangle.svg", shortcut: "M" },
        { id: "line", label: "Line", icon: "line.svg", shortcut: "L" },
        { id: "arrow", label: "Arrow", icon: "arrow.svg", shortcut: "⇧L" },
        { id: "two-headed-arrow", label: "Linear Dimension", icon: "linear-dimension.svg" },
        { id: "curve-arrow", label: "Draw", icon: "draw.svg" }, 
        { id: "ellipse", label: "Ellipse", icon: "ellipse.svg", shortcut: "O" },
        { id: "polygon", label: "Polygon", icon: "iconoir:triangle" },
        { id: "star", label: "Star", icon: "star.svg" },
        { id: "image-video", label: "Image/Video", icon: "image-video.svg", shortcut: "⇧K" },
       
      ],
    },
    {
      id: "booth",
      label: "Booth Tool",
      icon: "booth.svg",
    },
    {
      id: "pen", 
      label: "Pen Tool",
      icon: "pen.svg",
      shortcut: "P",
      subItems: [
        // { id: "pen", label: "Pen", icon: "pen.svg", shortcut: "P" },
        { id: "pencil", label: "Pencil", icon: "pencil.svg", shortcut: "P" },
      ]
    }, // Added subItems for Pen as user mentioned "fifth is pen tool... main tool has toggle item" except booth/text
    {
      id: "shape",
      label: "Shape",
      icon: "shape.svg",
      subItems: [
        { id: "single-door", label: "Single Door", icon: "single-door.svg" },
        { id: "double-door", label: "Double Door", icon: "double-door.svg" },
        { id: "diamond", label: "Diamond", icon: "mdi:diamond" },
        { id: "lounge", label: "Lounge", icon: "arcticons:lounge" },
        { id: "cafe", label: "Cafe", icon: "hugeicons:cafe" },
        { id: "restroom", label: "Restroom", icon: "fa7-solid:restroom" },
        { id: "malerestroom", label: "Male Restroom", icon: "grommet-icons:restroom-men" },
        { id: "womenrestroom", label: "Female Restroom", icon: "grommet-icons:restroom-women" },
        { id: "restaurant", label: "Restaurant", icon: "material-symbols:restaurant" },
        { id: "rectangle-table", label: "Rectangle table", icon: "material-symbols-light:table-large-rounded" },
        { id: "compass", label: "Compass", icon: "fontisto:compass-alt" },
        { id: "first-aid", label: "First Aid", icon: "bxs:first-aid" },
        { id: "emergency-exit", label: "Emergency Exit", icon: "guidance:emergency-exit" },
        { id: "elevator", label: "Elevator", icon: "material-symbols:elevator" },
        { id: "parking", label: "Parking", icon: "iconoir:parking" },
        { id: "field", label: "Ground Field", icon: "streamline-ultimate:soccer-field-bold" },
      ],
    },
    {
      id: "text",
      label: "Text Tool",
      icon: "text.svg",
      shortcut: "T",
    },
  ];

  const getEvent = (toolId: string) =>
    ["drawing", "shape", "elements"].includes(toolId) ? "mouseenter" : "click";

  const selectTool = (toolId: string) => {
    canvasStore.setTool(toolId);
  };

  const selectSubTool = (subToolId: string) => {
    // Only handle drawing tools here
    if (
      [
        "select", // Move
        "hand",
        "section",
        "frame",
        "pen",
        "line",
        "arrow",
        "curve-arrow",
        "two-headed-arrow",
        "pencil",
        "rectangle",
        "ellipse",
        "wall",
        "polygon",
        "star",
        "linear-dimension",
      ].includes(subToolId)
    ) {
      canvasStore.setTool(subToolId);
    }
  };

  const setupKeyboardShortcuts = () => {
    const handleKeydown = (event: KeyboardEvent) => {
      if (event.altKey && event.key === "s") {
        event.preventDefault();
        selectTool("hand");
      }
    };

    onMounted(() => {
      document.addEventListener("keydown", handleKeydown);
    });

    onUnmounted(() => {
      document.removeEventListener("keydown", handleKeydown);
    });
  };

  const selectToolWithImage = () => {
    const input = document.createElement("input");
    input.type = "file";
    input.accept = "image/*";
    input.onchange = (e) => {
      const file = (e.target as HTMLInputElement).files?.[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = (event) => {
        const assetId = assetsStore.addAsset({
          type: "image",
          data: event.target?.result,
        });

        const canvas = document.querySelector(
          ".whiteboard-container"
        ) as HTMLElement | null;
        if (!canvas) return console.error("Canvas element not found for image");

        console.log("Image loaded with asset ID:", assetId);
      };
      reader.readAsDataURL(file);
    };
    input.click();
  };

  return {
    uiStore,
    canvasStore,
    assetsStore,
    tools,
    getEvent,
    selectTool,
    selectSubTool,
    selectToolWithImage,
    setupKeyboardShortcuts,
  };
}
