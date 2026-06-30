// composables/useBoothCreationDistance.ts
/**
 * Composable for managing the default booth creation distance with unit conversion
 *
 * - Stores distance internally in CENTIMETERS (base unit)
 * - Automatically converts when measurement unit changes
 * - Persists to localStorage in centimeters
 * - Returns converted value based on current unit
 */

import { ref, watch, computed } from "vue";
import { useUiStore } from "@floorplan/stores/uiStore";

// Singleton pattern - shared across all components
let defaultDistanceInCm = ref<number>(100); // Always stored in centimeters
let isInitialized = false;

const STORAGE_KEY = "default-booth-distance-cm";
const DEFAULT_DISTANCE_CM = 100;

// Conversion factors (to centimeters)
const CONVERSION_TO_CM = {
  cm: 1,
  m: 100,
  ft: 30.48,
  in: 2.54,
};

// Conversion factors (from centimeters)
const CONVERSION_FROM_CM = {
  cm: 1,
  m: 0.01,
  ft: 0.0328084,
  in: 0.393701,
};

export function useBoothCreationDistance() {
  const uiStore = useUiStore();

  // Initialize from localStorage only once
  if (!isInitialized && process.client) {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
      const parsed = parseFloat(saved);
      if (!isNaN(parsed) && parsed > 0) {
        defaultDistanceInCm.value = parsed;
        console.log(
          "📏 Loaded default booth distance from localStorage:",
          parsed,
          "cm"
        );
      }
    }
    isInitialized = true;

    // Watch for changes and persist to localStorage (always in cm)
    watch(defaultDistanceInCm, (newValue) => {
      if (newValue > 0) {
        localStorage.setItem(STORAGE_KEY, newValue.toString());
        console.log(
          "💾 Saved default booth distance to localStorage:",
          newValue,
          "cm"
        );
      }
    });
  }

  /**
   * Get the default distance converted to the current measurement unit
   */
  const getDefaultDistance = (): number => {
    const currentUnit = uiStore.measurementUnit || "cm";
    const conversionFactor = CONVERSION_FROM_CM[currentUnit] || 1;
    const convertedValue = defaultDistanceInCm.value * conversionFactor;

    // Round to 2 decimal places for cleaner display
    return Math.round(convertedValue * 100) / 100;
  };

  /**
   * Get the default distance in centimeters (base unit)
   * Use this when creating booths to ensure consistency
   */
  const getDefaultDistanceInCm = (): number => {
    return defaultDistanceInCm.value;
  };

  /**
   * Set a new default distance
   * @param distance - Distance in the CURRENT measurement unit
   */
  const setDefaultDistance = (distance: number): void => {
    if (distance <= 0) return;

    const currentUnit = uiStore.measurementUnit || "cm";
    const conversionFactor = CONVERSION_TO_CM[currentUnit] || 1;

    // Convert to centimeters before storing
    defaultDistanceInCm.value = distance * conversionFactor;

    console.log(
      `🎯 Updated default booth distance: ${distance} ${currentUnit} = ${defaultDistanceInCm.value} cm`
    );
  };

  /**
   * Convert a distance from one unit to another
   */
  const convertDistance = (
    value: number,
    fromUnit: string,
    toUnit: string
  ): number => {
    // Convert to cm first
    const inCm = value * (CONVERSION_TO_CM[fromUnit] || 1);
    // Then convert to target unit
    const result = inCm * (CONVERSION_FROM_CM[toUnit] || 1);
    // Round to 2 decimal places
    return Math.round(result * 100) / 100;
  };

  /**
   * Reset to the original default distance
   */
  const resetToDefault = (): void => {
    defaultDistanceInCm.value = DEFAULT_DISTANCE_CM;
    if (process.client) {
      localStorage.removeItem(STORAGE_KEY);
    }
    console.log(
      "🔄 Reset booth distance to default:",
      DEFAULT_DISTANCE_CM,
      "cm"
    );
  };

  /**
   * Computed property that returns distance in current unit
   * Automatically updates when measurement unit changes
   */
  const defaultDistance = computed(() => {
    return getDefaultDistance();
  });

  return {
    defaultDistance,
    defaultDistanceInCm, // For direct access to base unit value
    getDefaultDistance,
    getDefaultDistanceInCm,
    setDefaultDistance,
    convertDistance,
    resetToDefault,
  };
}
