// useSelectionManager.ts - NEW FILE
import { ref } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import type { CanvasObject, DomElement } from "@floorplan/types/canvas";

export function useSelectionManager() {
  const store = useCanvasStore();

  const isSelecting = ref(false);
  const selectionStart = ref<{ x: number; y: number } | null>(null);
  const selectionEnd = ref<{ x: number; y: number } | null>(null);

  // Clear all selections
  const clearAllSelections = () => {
    // Clear canvas objects
    store.objects.forEach((obj) => {
      obj.isSelected = false;
    });
    store.selectedObjects = [];

    // Clear DOM elements
    store.selectedElementId = null;
    store.selectedDomElements = [];

    console.log("All selections cleared");
  };

  // Select a single canvas object
  const selectCanvasObject = (
    objectId: string,
    isMultiSelect: boolean = false
  ) => {
    const obj = store.objects.find((o) => o.id === objectId);
    if (!obj || obj.isLocked || obj.isVisible === false) return;

    if (!isMultiSelect) {
      // Clear other selections for single select
      clearAllSelections();
    }

    // Toggle selection for this object
    if (isMultiSelect && obj.isSelected) {
      obj.isSelected = false;
      const index = store.selectedObjects.findIndex((o) => o.id === objectId);
      if (index > -1) {
        store.selectedObjects.splice(index, 1);
      }
    } else {
      obj.isSelected = true;
      if (!store.selectedObjects.some((o) => o.id === objectId)) {
        store.selectedObjects.push(obj);
      }
    }

    console.log(
      `Canvas object ${objectId} ${obj.isSelected ? "selected" : "deselected"}`
    );
  };

  // Select a DOM element
  const selectDomElement = (
    elementId: string,
    isMultiSelect: boolean = false
  ) => {
    const element = store.domElements.find((el) => el.id === elementId);
    if (!element || element.isLocked || element.isVisible === false) return;

    if (!isMultiSelect) {
      // Clear canvas selections when selecting DOM element
      store.objects.forEach((obj) => (obj.isSelected = false));
      store.selectedObjects = [];
    }

    // Handle DOM element selection
    if (isMultiSelect) {
      if (!store.selectedDomElements) store.selectedDomElements = [];
      const index = store.selectedDomElements.findIndex((el) => el.id === elementId);
      if (index > -1) {
        // Deselect
        store.selectedDomElements.splice(index, 1);
        if (store.selectedElementId === elementId) {
          store.selectedElementId =
            store.selectedDomElements.length > 0
              ? store.selectedDomElements[0].id
              : null;
        }
      } else {
        // Select
        store.selectedDomElements.push(element);
        store.selectedElementId = elementId;
      }
    } else {
      // Single select
      store.selectedElementId = elementId;
      store.selectedDomElements = [element];
    }

    console.log(`DOM element ${elementId} selected`);
  };

  // Check if anything is selected
  const hasSelection = () => {
    return (
      store.selectedObjects.length > 0 ||
      store.selectedElementId !== null ||
      (store.selectedDomElements && store.selectedDomElements.length > 0)
    );
  };

  // Get all selected items across both types
  const getAllSelected = () => {
    return {
      canvasObjects: store.selectedObjects,
      domElements: store.selectedDomElements || [],
      singleDomElement: store.selectedElementId,
    };
  };

  return {
    isSelecting,
    selectionStart,
    selectionEnd,
    clearAllSelections,
    selectCanvasObject,
    selectDomElement,
    hasSelection,
    getAllSelected,
  };
}
