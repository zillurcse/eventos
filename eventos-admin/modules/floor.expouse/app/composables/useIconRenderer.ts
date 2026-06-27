// composables/useIconRenderer.ts
export function useIconRenderer() {
  const iconCache = new Map();

  const fetchIcon = async (iconName: string): Promise<string> => {
    if (iconCache.has(iconName)) {
      return iconCache.get(iconName);
    }

    try {
      // Iconify API endpoint
      const response = await fetch(
        `https://api.iconify.design/${iconName}.svg`
      );
      if (response.ok) {
        const svg = await response.text();
        iconCache.set(iconName, svg);
        return svg;
      }
    } catch (error) {
      console.warn(`Failed to fetch icon: ${iconName}`, error);
    }

    return "";
  };

  const getShapeIconName = (subtype: string): string => {
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
      blackbird: "fluent-emoji-high-contrast:blackbird",
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
    return shapeMap[subtype] || "";
  };

  const getElementIconName = (subtype: string): string => {
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
    return elementsMap[subtype] || "";
  };

  return {
    fetchIcon,
    getShapeIconName,
    getElementIconName,
  };
}
