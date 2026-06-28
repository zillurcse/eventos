```vue
<template>
  <div
    class="badge-canvas-container relative w-[105mm] h-[148mm] bg-white border-2 border-gray-300"
    ref="canvas"
  >
    <!-- Center Guide Line (optional, shown when aligning) -->
    <div
      v-if="showCenterGuide"
      class="absolute w-px h-full bg-blue-500 left-1/2 transform -translate-x-1/2"
    ></div>
    <!-- Render Draggable Elements -->
    <DraggableElement
      v-for="(element, index) in elements"
      :key="element.id"
      :element="element"
      :index="index"
      :selected="selectedElement === index"
      @select="selectElement(index)"
      @update="updateElement(index, $event)"
      @edit="toggleEdit(index)"
    />
    <!-- Export Button -->
    <button
      class="absolute bottom-2 right-2 bg-blue-500 text-white px-4 py-2 rounded"
      @click="exportDesign"
    >
      Export
    </button>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";
import { v4 as uuidv4 } from "uuid";
import DraggableElement from "./DraggableElement.vue";
import html2canvas from "html2canvas";

const canvas = ref(null);
const elements = ref([]);
const selectedElement = ref(null);
const showCenterGuide = ref(false);

const addElement = (type) => {
  const newElement = {
    id: uuidv4(),
    type,
    label:
      type === "text" ? "New Text" : type === "qr" ? "QR Code" : "New Image",
    content:
      type === "text"
        ? "New Text"
        : type === "qr"
        ? "https://example.com"
        : "https://via.placeholder.com/100",
    x: 50,
    y: 50,
    width: 200,
    height: type === "text" ? 50 : 100,
    fontSize: 20,
    rotation: 0,
    zIndex: elements.value.length + 1,
    isEditing: false,
  };
  elements.value.push(newElement);
  selectedElement.value = elements.value.length - 1;
};

const selectElement = (index) => {
  selectedElement.value = index;
  showCenterGuide.value = true;
};

const updateElement = (index, updates) => {
  Object.assign(elements.value[index], updates);
};

const toggleEdit = (index) => {
  elements.value[index].isEditing = !elements.value[index].isEditing;
};

const exportDesign = async () => {
  const canvasElement = canvas.value;
  const canvasImage = await html2canvas(canvasElement, { scale: 2 });
  const link = document.createElement("a");
  link.download = "badge.png";
  link.href = canvasImage.toDataURL("image/png");
  link.click();
};

defineExpose({ addElement });
</script>

<style scoped>
.badge-canvas-container {
  position: relative;
  margin: 0 auto;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}
</style>
```
