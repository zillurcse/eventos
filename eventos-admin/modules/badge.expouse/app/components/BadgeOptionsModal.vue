<template>
  <transition name="flip">
    <div
      v-if="pageStore.showModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
    >
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-4xl">
        <!-- Modal Header -->
        <div class="flex justify-between items-center border-b pb-2">
          <h3 class="text-lg font-semibold text-gray-900">Badge Options</h3>
          <button
            @click="pageStore.toggleModal"
            class="text-gray-500 hover:text-gray-700"
          >
            <span class="text-xl">×</span>
          </button>
        </div>

        <!-- Modal Body -->
        <div class="grid grid-cols-[1.5fr_1fr] gap-6 mt-4">
          <!-- Options Panel -->
          <div>
            <!-- Badge Size -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700"
                >Badge Size</label
              >
              <div class="mt-2 space-y-2">
                <!-- Preset/Custom Radio Inline -->
                <div class="flex items-center gap-6">
                  <label class="flex items-center">
                    <input
                      v-model="pageStore.badgeSizePreset"
                      type="radio"
                      value="preset"
                      class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-600"
                    />
                    <span class="ml-2 text-sm text-gray-700">Preset</span>
                  </label>
                  <label class="flex items-center">
                    <input
                      v-model="pageStore.badgeSizePreset"
                      type="radio"
                      value="custom"
                      class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-600"
                    />
                    <span class="ml-2 text-sm text-gray-700">Custom</span>
                  </label>
                </div>

                <!-- Preset Options -->
                <select
                  v-if="pageStore.badgeSizePreset === 'preset'"
                  v-model="pageStore.badgeSize"
                  class="mt-2 block w-full border border-gray-300 rounded-md p-2 focus:ring-indigo-600 focus:border-indigo-600"
                >
                  <option value="A4">A4 (210×297mm)</option>
                  <option value="A6">A6 (105×148mm)</option>
                  <option value="A7">A7 (74×105mm)</option>
                </select>

                <!-- Custom Inputs -->
                <div
                  v-if="pageStore.badgeSizePreset === 'custom'"
                  class="mt-2 space-y-2"
                >
                  <div>
                    <label class="block text-sm font-medium text-gray-700"
                      >Width (mm)</label
                    >
                    <input
                      v-model.number="pageStore.customWidth"
                      type="number"
                      min="50"
                      max="300"
                      class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-indigo-600 focus:border-indigo-600"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700"
                      >Height (mm)</label
                    >
                    <input
                      v-model.number="pageStore.customHeight"
                      type="number"
                      min="50"
                      max="300"
                      class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-indigo-600 focus:border-indigo-600"
                    />
                  </div>
                </div>
              </div>
            </div>

            <!-- Orientation Inline -->
            <div v-if="pageStore.badgeSizePreset === 'preset'" class="mb-4">
              <label class="block text-sm font-medium text-gray-700"
                >Orientation</label
              >
              <div class="flex items-center gap-6 mt-2">
                <label class="flex items-center">
                  <input
                    v-model="pageStore.badgeOrientation"
                    type="radio"
                    value="portrait"
                    class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-600"
                  />
                  <span class="ml-2 text-sm text-gray-700">Portrait</span>
                </label>
                <label class="flex items-center">
                  <input
                    v-model="pageStore.badgeOrientation"
                    type="radio"
                    value="landscape"
                    class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-600"
                  />
                  <span class="ml-2 text-sm text-gray-700">Landscape</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Preview Panel -->
          <div class="border-l pl-6 flex flex-col items-center">
            <h4 class="text-sm font-medium text-gray-700 mb-1">Preview</h4>
            <div class="text-sm text-gray-500 mb-2">
              Badge Size - {{ pageStore.badgeSize }}
            </div>

            <!-- Transition Wrapper -->
            <transition name="fade-scale" mode="out-in">
              <div
                :key="pageStore.badgeOrientation"
                class="relative transition-all duration-300"
              >
                <!-- Badge Box -->
                <div
                  class="rounded-md border border-gray-300 relative bg-white"
                  style="width: 150px; height: 210px"
                  v-if="pageStore.badgeOrientation === 'portrait'"
                >
                  <div class="flex flex-col justify-between h-full p-4">
                    <!-- Top Icon -->
                    <div class="flex justify-center">
                      <NuxtIcon name="mdi:city" class="text-gray-400 text-2xl" />
                    </div>

                    <!-- Middle Lines -->
                    <div class="flex flex-col items-center space-y-2">
                      <div class="w-28 h-2 bg-gray-400 rounded"></div>
                      <div class="w-20 h-1.5 bg-gray-400 rounded"></div>
                      <div class="w-32 h-2 bg-gray-400 rounded"></div>
                      <div class="w-16 h-1.5 bg-gray-400 rounded"></div>
                    </div>

                    <!-- Bottom Row -->
                    <div class="flex justify-center items-end mt-auto">
                      <div
                        class="w-10 h-10 bg-gray-200 rounded flex items-center justify-center"
                      >
                        <NuxtIcon name="mdi:qrcode" class="text-gray-500 text-xl" />
                      </div>
                    </div>
                  </div>

                  <!-- Dimensions Labels -->
                  <div
                    class="absolute -bottom-6 left-0 right-0 text-center text-xs text-gray-500"
                  >
                    {{
                      pageStore.badgeSizePreset === "custom"
                        ? `${pageStore.customWidth}.0mm`
                        : pageStore.getWidthLabel()
                    }}
                  </div>
                  <div
                    class="absolute -right-6 top-0 bottom-0 flex items-center text-xs text-gray-500 writing-mode-vertical-rl"
                  >
                    {{
                      pageStore.badgeSizePreset === "custom"
                        ? `${pageStore.customHeight}.0mm`
                        : pageStore.getHeightLabel()
                    }}
                  </div>
                </div>
                <div
                  class="rounded-md border border-gray-300 relative bg-white"
                  style="width: 210px; height: 150px"
                  v-if="pageStore.badgeOrientation === 'landscape'"
                >
                  <div class="flex flex-col justify-between h-full p-4">
                    <!-- Top Icon -->
                    <div class="flex justify-center">
                      <NuxtIcon name="mdi:city" class="text-gray-400 text-2xl" />
                    </div>

                    <!-- Middle Lines -->
                    <div class="flex flex-col items-center space-y-2">
                      <div class="w-28 h-2 bg-gray-400 rounded"></div>
                      <div class="w-20 h-1.5 bg-gray-400 rounded"></div>
                      <div class="w-32 h-2 bg-gray-400 rounded"></div>
                      <div class="w-16 h-1.5 bg-gray-400 rounded"></div>
                    </div>

                    <!-- Bottom Row -->
                    <div class="flex justify-end items-end mt-auto">
                      <div
                        class="w-10 h-10 bg-gray-200 rounded flex items-center justify-center"
                      >
                        <NuxtIcon name="mdi:qrcode" class="text-gray-500 text-xl" />
                      </div>
                    </div>
                  </div>

                  <!-- Dimensions Labels -->
                  <div
                    class="absolute -bottom-6 left-0 right-0 text-center text-xs text-gray-500"
                  >
                    {{
                      pageStore.badgeSizePreset === "custom"
                        ? `${pageStore.customHeight}.0mm`
                        : pageStore.getHeightLabel()
                    }}
                  </div>
                  <div
                    class="absolute -right-6 top-0 bottom-0 flex items-center text-xs text-gray-500 writing-mode-vertical-rl"
                  >
                    {{
                      pageStore.badgeSizePreset === "custom"
                        ? `${pageStore.customWidth}.0mm`
                        : pageStore.getWidthLabel()
                    }}
                  </div>
                </div>
              </div>
            </transition>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="mt-6 flex justify-end space-x-3 border-t pt-4">
          <button
            @click="pageStore.toggleModal"
            class="px-4 py-2 text-gray-700 rounded-md hover:bg-gray-100"
          >
            Cancel
          </button>
          <button
            @click="pageStore.saveBadgeConfig()"
            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
          >
            OK
          </button>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { usePageStore } from "@badge/stores/usePageStore";
import { useCanvasStore } from "@badge/stores/useCanvasStore";

const pageStore = usePageStore();

const store = useCanvasStore();
const route = useRoute();
const router = useRouter();

// const props = defineProps({
//   show: Boolean,
//   badgeSizePreset: String,
//   badgeSize: String,
//   badgeOrientation: String,
//   customWidth: Number,
//   customHeight: Number,
// });

// const emit = defineEmits([
//   "update:badgeSizePreset",
//   "update:badgeSize",
//   "update:badgeOrientation",
//   "update:customWidth",
//   "update:customHeight",
//   "close",
//   "save",
// ]);

// const pageStore.badgeSizePreset = ref(props.badgeSizePreset);
// const pageStore.badgeSize = ref(props.badgeSize);
// const pageStore.badgeOrientation = ref(props.badgeOrientation);
// const pageStore.customWidth = ref(props.customWidth);
// const pageStore.customHeight = ref(props.customHeight);

// const getWidthLabel = () => {
//   if (pageStore.badgeSizePreset === "preset") {
//     switch (pageStore.badgeSize) {
//       case "A7":
//         return pageStore.badgeOrientation === "landscape"
//           ? "105.0mm"
//           : "74.0mm";
//       case "A6":
//         return pageStore.badgeOrientation === "landscape"
//           ? "148.0mm"
//           : "105.0mm";
//       case "A4":
//       default:
//         return pageStore.badgeOrientation === "landscape"
//           ? "297.0mm"
//           : "210.0mm";
//     }
//   }
//   return "0.0mm"; // Default case for custom
// };

// const getHeightLabel = () => {
//   if (pageStore.badgeSizePreset === "preset") {
//     switch (pageStore.badgeSize) {
//       case "A7":
//         return pageStore.badgeOrientation === "landscape"
//           ? "74.0mm"
//           : "105.0mm";
//       case "A6":
//         return pageStore.badgeOrientation === "landscape"
//           ? "105.0mm"
//           : "148.0mm";
//       case "A4":
//       default:
//         return pageStore.badgeOrientation === "landscape"
//           ? "210.0mm"
//           : "297.0mm";
//     }
//   }
//   return "0.0mm"; // Default case for custom
// };

// watch(
//   () => props.badgeSizePreset,
//   (val) => (pageStore.badgeSizePreset = val)
// );
// watch(
//   () => props.badgeSize,
//   (val) => (pageStore.badgeSize = val)
// );
// watch(
//   () => props.badgeOrientation,
//   (val) => (pageStore.badgeOrientation = val)
// );
// watch(
//   () => props.customWidth,
//   (val) => (pageStore.customWidth.value = val)
// );
// watch(
//   () => props.customHeight,
//   (val) => (pageStore.customHeight.value = val)
// );

// watch(pageStore.badgeSizePreset, (val) => emit("update:badgeSizePreset", val));
// watch(pageStore.badgeSize, (val) => emit("update:badgeSize", val));
// watch(pageStore.badgeOrientation, (val) => emit("update:badgeOrientation", val));
// watch(pageStore.customWidth, (val) => emit("update:customWidth", val));
// watch(pageStore.customHeight, (val) => emit("update:customHeight", val));

const save = () => {
  emit("save");
};
</script>

<style scoped>
.flip-enter-active {
  transition: all 0.6s ease;
}
.flip-leave-active {
  transition: all 0.3s ease;
}
.flip-enter-from,
.flip-leave-to {
  opacity: 0;
}
.flip-enter-from .modal-content,
.flip-leave-to .modal-content {
  transform: rotateY(180deg);
}
.flip-enter-to .modal-content,
.flip-leave-from .modal-content {
  transform: rotateY(0deg);
}
.writing-mode-vertical-rl {
  writing-mode: vertical-rl;
  transform: rotate(180deg);
}
</style>
