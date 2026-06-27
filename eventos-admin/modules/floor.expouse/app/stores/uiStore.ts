import { defineStore } from "pinia";

export const useUiStore = defineStore("ui", {
  state: () => ({
    showGuides: true,
    zoom: 100,
    measurementUnit: "centimeter", // Default unit
  }),

  actions: {
    scale() {
      this.zoom / 100;
    },

    toggleGuides() {
      this.showGuides = !this.showGuides;
    },

    setMeasurementUnit(unit: string) {
      this.measurementUnit = unit;
    },

    // Conversion methods
    convertToCurrentUnit(valueInCm: number): { value: number; unit: string } {
      switch (this.measurementUnit) {
        case "feet":
          return { value: valueInCm / 30.48, unit: "ft" };
        case "inches":
          return { value: valueInCm / 2.54, unit: "in" };
        case "meter":
          return { value: valueInCm / 100, unit: "m" };
        case "centimeter":
        default:
          return { value: valueInCm, unit: "cm" };
      }
    },

    formatMeasurement(valueInCm: number): string {
      const converted = this.convertToCurrentUnit(valueInCm);
      return `${converted.value.toFixed(2)} ${converted.unit}`;
    },

    formatArea(valueInSqCm: number): string {
      const converted = this.convertToCurrentUnit(Math.sqrt(valueInSqCm));
      const areaValue = Math.pow(converted.value, 2);
      return `${areaValue.toFixed(2)} ${converted.unit}²`;
    },
  },
});
