// stores/pageStore.ts
import { defineStore } from "pinia";

// Define the state interface
export interface PageState {
  pageWidth: number;
  pageHeight: number;
  showModal: boolean;
  badgeOrientation: string;
  badgeSize: string;
  badgeSizePreset: string;
  customWidth: number;
  customHeight: number;
  presetWidth: number;
  presetHeight: number;
}

// Define the store
export const usePageStore = defineStore("page", {
  state: (): PageState => ({
    pageWidth: 105, // default values
    pageHeight: 148,
    presetWidth: 105,
    presetHeight: 148,
    showModal: false,
    badgeOrientation: "portrait",
    badgeSize: "A6",
    badgeSizePreset: "preset",
    customWidth: 0,
    customHeight: 0,
  }),

  actions: {
    closeModal() {
      this.showModal = false;
      this.badgeSizePreset = "preset";
      this.badgeSize = "A6";
      this.customWidth = 105;
      this.customHeight = 148;
      this.badgeOrientation = "portrait";
    },

    saveBadgeConfig() {
      const route = useRoute();
      const router = useRouter();

      let widthMM = this.customWidth;
      let heightMM = this.customHeight;

      // If preset, assign standard mm sizes
      if (this.badgeSizePreset === "preset") {
        switch (this.badgeSize) {
          case "A4":
            widthMM = 210;
            heightMM = 297;
            break;
          case "A6":
            widthMM = 105;
            heightMM = 148;
            break;
          case "A7":
            widthMM = 74;
            heightMM = 105;
            break;
          default:
            widthMM = this.customWidth;
            heightMM = this.customHeight;
        }
      }

      // Swap for landscape
      this.presetWidth = widthMM;
      this.presetHeight = heightMM;

      if (this.badgeOrientation === "landscape") {
        [this.presetWidth, this.presetHeight] = [
          this.presetHeight,
          this.presetWidth,
        ];
      }

      // Convert mm → px (1mm ≈ 3.78px)
      const finalWidthPX = Math.round(this.presetWidth * 3.78);
      const finalHeightPX = Math.round(this.presetHeight * 3.78);

      // Update pageWidth and pageHeight
      this.pageWidth = finalWidthPX;
      this.pageHeight = finalHeightPX;
      console.log("route.path", route.path);

      // console.log("pageHeight", this.pageHeight);

      this.showModal = false;
      if (route.path == "/design-badge") {
        router.push("/design-badge/page-builder");
      }
    },

    updateCustomSize() {
      if (this.badgeSizePreset === "preset") {
        switch (this.badgeSize) {
          case "A4":
            this.customWidth = 210;
            this.customHeight = 297;
            break;
          case "A6":
            this.customWidth = 105;
            this.customHeight = 148;
            break;
          case "A7":
            this.customWidth = 74;
            this.customHeight = 105;
            break;
          default:
            this.customWidth = 0;
            this.customHeight = 0;
        }
      }
    },

    setBadgeSize(size: string) {
      this.badgeSize = size;
      this.updateCustomSize();
    },

    setBadgeSizePreset(preset: string) {
      this.badgeSizePreset = preset;
      this.updateCustomSize();
    },

    setBadgeOrientation(orientation: string) {
      this.badgeOrientation = orientation;
    },

    toggleModal() {
      this.showModal = !this.showModal;
    },

    getWidthLabel() {
      if (this.badgeSizePreset === "preset") {
        switch (this.badgeSize) {
          case "A7":
            return this.badgeOrientation === "landscape" ? "105.0mm" : "74.0mm";
          case "A6":
            return this.badgeOrientation === "landscape"
              ? "148.0mm"
              : "105.0mm";
          case "A4":
          default:
            return this.badgeOrientation === "landscape"
              ? "297.0mm"
              : "210.0mm";
        }
      }
      return "0.0mm"; // Default case for custom
    },

    getHeightLabel() {
      if (this.badgeSizePreset === "preset") {
        switch (this.badgeSize) {
          case "A7":
            return this.badgeOrientation === "landscape" ? "74.0mm" : "105.0mm";
          case "A6":
            return this.badgeOrientation === "landscape"
              ? "105.0mm"
              : "148.0mm";
          case "A4":
          default:
            return this.badgeOrientation === "landscape"
              ? "210.0mm"
              : "297.0mm";
        }
      }
      return "0.0mm"; // Default case for custom
    },
  },
});
