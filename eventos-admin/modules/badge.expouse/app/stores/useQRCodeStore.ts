import { defineStore } from "pinia";

export interface QRCodeState {
  showModal: boolean;
}
export const useQRCodeStore = defineStore("QRCode", {
  state: (): QRCodeState => ({
    showModal: false,
  }),

  actions: {
    qrCodetoggleModal() {
      this.showModal = !this.showModal;
    },
  },
});
