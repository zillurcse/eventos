<template>
  <div
    class="flex flex-col md:flex-row h-screen bg-gray-100 sm:overflow-y-auto"
  >
    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col items-center p-4 order-2 md:order-1">
      <!-- Top Controls -->
      <div class="w-full">
        <div
          class="flex flex-wrap items-center justify-center md:justify-between gap-3 w-full"
        >
          <button
            @click="sendData"
            class="flex gap-1 px-5 py-2 text-sm bg-blue-500 text-white rounded-lg font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-400"
          >
            <NuxtIcon
              name="material-symbols:published-with-changes-rounded"
              class="text-xl"
            />
            <span>Save Badge</span>
          </button>

          <!-- Side Tabs -->
          <div
            class="flex border border-gray-300 rounded-lg overflow-hidden shadow-sm ml-12"
          >
            <button
              class="px-4 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="{
                'bg-blue-500 text-white': store.activeSide === 'front',
                'bg-gray-200 text-gray-700 hover:bg-gray-300':
                  store.activeSide !== 'front',
              }"
              @click="switchSideTab('front')"
              :disabled="isFlipping"
            >
              Front
            </button>
            <button
              class="px-4 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="{
                'bg-blue-500 text-white': store.activeSide === 'back',
                'bg-gray-200 text-gray-700 hover:bg-gray-300':
                  store.activeSide !== 'back',
              }"
              @click="switchSideTab('back')"
              :disabled="isFlipping"
            >
              Back
            </button>
          </div>
          <!-- Zoom & Grid Controls -->
          <div
            class="flex items-center space-x-2 bg-white rounded-lg p-1 shadow-sm"
          >
            <button
              class="w-9 h-9 flex items-center justify-center bg-gray-200 rounded hover:bg-gray-300 transition"
              @click="zoom(-10)"
            >
              <NuxtIcon name="mdi:minus" class="w-4 h-4" />
            </button>
            <span
              class="w-12 h-9 flex items-center justify-center bg-gray-100 rounded text-sm font-medium"
            >
              {{ zoomLevel }}%
            </span>
            <button
              class="w-9 h-9 flex items-center justify-center bg-gray-200 rounded hover:bg-gray-300 transition"
              @click="zoom(10)"
            >
              <NuxtIcon name="mdi:plus" class="w-4 h-4" />
            </button>
            <button
              class="w-9 h-9 flex items-center justify-center bg-gray-200 rounded hover:bg-gray-300 transition"
              :class="{ 'text-blue-500 bg-white': showGrid }"
              @click="toggleGrid"
            >
              <NuxtIcon name="mdi:grid" class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
      <!-- Design Page -->
      <div
        class="flex-1 w-full flex justify-center items-start overflow-auto mt-3"
      >
        <div
          ref="dropzone"
          class="design-page bg-white"
          :style="{
            width: `${pageStore.presetWidth}mm`,
            height: `${pageStore.presetHeight}mm`,
            transform: `scale(${zoomScale})`,
            transformOrigin: 'center top',
          }"
          :class="{
            'grid-overlay': showGrid,
            flipped: store.activeSide === 'back',
          }"
          @dragover.prevent
          @drop="handleDrop"
        >
          <div class="card w-full h-full relative">
            <div class="front w-full h-full absolute top-0 left-0">
              <Canvas
                v-if="store.activeSide === 'front'"
                :modelValue="store.frontBoxes"
              />
            </div>
            <div class="back w-full h-full absolute top-0 left-0">
              <Canvas
                v-if="store.activeSide === 'back'"
                :modelValue="store.backBoxes"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- Sidebar -->
    <div
      class="w-full p-4 md:w-1/5 bg-gray-50 shadow-sm border-t md:border-t-0 md:border-l order-1 md:order-2 lg:overflow-y-auto"
    >
      <Sidebar
        :selected-element="store.selectedElement"
        :selected-element-type="selectedElementType"
        :layers="layers"
        :selected-layer="selectedLayer"
        :current-properties="store.currentProperties"
        :display-option="displayOption"
        @drag-start="onDragStart"
        @drag-end="onDragEnd"
      />
    </div>
    <!-- Modals -->
    <ImageUploadModal
      v-if="store.showImageModal"
      :side="store.pendingImageSide"
      @uploaded="handleImageUploaded"
      @close="store.showImageModal = false"
    />
    <GradientPickerModal
      v-if="store.showGradientModal"
      :show="store.showGradientModal"
      :side="store.activeSide"
      @selected="(gradient) => store.applyGradient(gradient, store.activeSide)"
      @close="store.showGradientModal = false"
    />
    <ColorPickerModal
      v-if="store.showColorModal"
      :show="store.showColorModal"
      :side="store.activeSide"
      @selected="(color) => store.applyColor(color, store.activeSide)"
      @close="store.showColorModal = false"
    />
    <BadgeOptionsModal />
    <QRCodeModal />
  </div>
</template>
<script setup>
import { useBadgeEditor } from "@badge/composables/useBadgeEditor";
import { useCanvasStore } from "@badge/stores/useCanvasStore";
import { usePageStore } from "@badge/stores/usePageStore";

// Initialize the composable
const {
  dropzone,
  zoomLevel,
  showGrid,
  isFlipping,
  zoomScale,
  selectedLayer,
  layers,
  displayOption,
  selectedElementType,

  sendData,
  switchSideTab,
  zoom,
  toggleGrid,
  onDragStart,
  onDragEnd,
  handleDrop,
  handleImageUploaded,
} = useBadgeEditor();

const store = useCanvasStore();
const pageStore = usePageStore();

// Router and Route
const router = useRouter();
const route = useRoute();
</script>

<style scoped>
.design-page {
  position: relative;
  width: 794px;
  height: 1123px;
}
.card {
  position: relative;
  transform-style: preserve-3d;
  transition: transform 0.6s ease-in-out;
}

.design-page.flipped .card {
  transform: rotateY(180deg);
}

.front,
.back {
  position: absolute;
  width: 100%;
  height: 100%;
  backface-visibility: hidden;
}

.back {
  transform: rotateY(180deg);
}

.grid-overlay::before {
  content: "";
  position: absolute;
  inset: 0;
  background-image: repeating-linear-gradient(
      to right,
      rgba(156, 163, 175, 0.2) 0px,
      rgba(156, 163, 175, 0.2) 1px,
      transparent 1px,
      transparent 20px
    ),
    repeating-linear-gradient(
      to bottom,
      rgba(156, 163, 175, 0.2) 0px,
      rgba(156, 163, 175, 0.2) 1px,
      transparent 1px,
      transparent 20px
    ),
    repeating-linear-gradient(
      to right,
      rgba(100, 100, 100, 0.5) 0px,
      rgba(100, 100, 100, 0.5) 1px,
      transparent 1px,
      transparent 100px
    ),
    repeating-linear-gradient(
      to bottom,
      rgba(100, 100, 100, 0.5) 0px,
      rgba(100, 100, 100, 0.5) 1px,
      transparent 1px,
      transparent 100px
    );
  background-size: 20px 20px, 20px 20px, 100px 100px, 100px 100px;
  pointer-events: none;
  z-index: 10;
}
</style>
