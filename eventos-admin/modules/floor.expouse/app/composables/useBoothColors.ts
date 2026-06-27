// composables/useBoothColors.ts
export const useBoothColors = () => {
  // Get custom colors from localStorage with proper error handling
  const getCustomColors = (): Record<string, string> => {
    if (process.client) {
      try {
        const saved = localStorage.getItem("booth-custom-colors");
        // Check if saved is not null, not undefined, and not empty string
        if (saved && saved !== "undefined" && saved.trim() !== "") {
          return JSON.parse(saved);
        }
      } catch (error) {
        console.warn(
          "Failed to parse booth custom colors from localStorage:",
          error
        );
        // Clear invalid data
        localStorage.removeItem("booth-custom-colors");
      }
    }
    return {};
  };

  // Get color for a specific status
  const getStatusColor = (status: string): string => {
    const customColors = getCustomColors();
    const defaultColors = {
      AVAILABLE: "#E7F9ED",
      BOOKED: "#FEE2E2",
      ON_HOLD: "#DBEAFE",
    };

    return customColors[status] || defaultColors[status] || "#E7F9ED";
  };

  // Update color for a status
  const updateStatusColor = (status: string, color: string) => {
    const customColors = getCustomColors();
    customColors[status] = color;

    if (process.client) {
      try {
        localStorage.setItem(
          "booth-custom-colors",
          JSON.stringify(customColors)
        );
      } catch (error) {
        console.error("Failed to save booth colors to localStorage:", error);
      }
    }

    // Trigger a custom event to notify all components about color change
    if (process.client) {
      window.dispatchEvent(
        new CustomEvent("booth-colors-updated", {
          detail: { status, color },
        })
      );
    }
  };

  // Reset color for a status
  const resetStatusColor = (status: string) => {
    const customColors = getCustomColors();
    delete customColors[status];

    if (process.client) {
      try {
        localStorage.setItem(
          "booth-custom-colors",
          JSON.stringify(customColors)
        );
      } catch (error) {
        console.error("Failed to save booth colors to localStorage:", error);
      }
    }

    // Trigger update event
    if (process.client) {
      window.dispatchEvent(
        new CustomEvent("booth-colors-updated", {
          detail: { status, color: null },
        })
      );
    }
  };

  // Initialize localStorage with default structure if empty
  const initializeColors = () => {
    if (process.client) {
      const current = getCustomColors();
      if (Object.keys(current).length === 0) {
        // Initialize with empty object to prevent "undefined" string
        localStorage.setItem("booth-custom-colors", JSON.stringify({}));
      }
    }
  };

  // Call initialization
  if (process.client) {
    initializeColors();
  }

  return {
    getCustomColors,
    getStatusColor,
    updateStatusColor,
    resetStatusColor,
  };
};
