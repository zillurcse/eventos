import { defineStore } from "pinia";

export const useAssetsStore = defineStore("assets", {
  state: () => ({
    assets: new Map(), // Map of assetId to { type: string, data: any }
  }),
  actions: {
    addAsset(asset: { type: string; data: any }): string {
      const assetId = crypto.randomUUID();
      this.assets.set(assetId, { ...asset, id: assetId });
      return assetId;
    },
    getAsset(assetId: string) {
      return this.assets.get(assetId) || null;
    },
    removeAsset(assetId: string) {
      this.assets.delete(assetId);
    },
    clearAssets() {
      this.assets.clear();
    },
  },
  getters: {
    lastAddedAsset: (state) => {
      return [...state.assets.values()].pop()?.id || null;
    },
  },
});
