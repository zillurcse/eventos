<template>
  <div class="flex h-screen" @keydown.delete="deleteElement">
    <!-- Canvas Area -->
    <div class="w-3/4 bg-gray-200 p-4" @dragover.prevent @drop="onDrop">
      <div
        class="w-[210mm] h-[297mm] bg-white mx-auto relative shadow-lg"
        ref="canvas"
        tabindex="0"
        @click="handleCanvasClick"
      >
        <div
          v-for="element in elements"
          :key="element.id"
          :data-element-id="element.id"
          class="draggable absolute"
          :class="{ 'ring-2 ring-blue-500': selectedElement === element.id }"
          :style="{
            left: element.properties.x + 'px',
            top: element.properties.y + 'px',
            width: element.properties.width + 'px',
            height: element.properties.height + 'px',
            transform: `rotate(${element.properties.rotation}deg)`,
            fontFamily: element.properties.font,
            color: element.properties.fillTransparency
              ? 'transparent'
              : element.properties.color,
            backgroundColor: element.properties.fillTransparency
              ? 'transparent'
              : element.properties.fillColor,
            cursor: element.properties.lock ? 'not-allowed' : 'move',
            display: layers.find((l) => l.id === element.id)?.visible
              ? 'block'
              : 'none',
          }"
          @click="selectElement(element.id)"
        >
          <div
            v-if="element.type === 'text'"
            contenteditable
            ref="textElements"
            :data-element-id="element.id"
            @input="updateText(element.id, $event)"
            @keydown="handleKeydown(element.id, $event)"
            :style="{
              color: element.properties.color,
              fontSize: `${element.properties.fontSize}px`,
              fontWeight:
                element.properties.fontStyle === 'Bold' ? 'bold' : 'normal',
              fontStyle:
                element.properties.fontStyle === 'Italic' ? 'italic' : 'normal',
              textDecoration:
                element.properties.textDecoration === 'underline'
                  ? 'underline'
                  : 'none',
              backgroundColor: element.properties.fillTransparency
                ? 'transparent'
                : element.properties.fillColor,
              textAlign:
                displayOption === 'right side only'
                  ? 'right'
                  : element.properties.textAlign || 'left',
              verticalAlign: element.properties.verticalAlign || 'top',
              textTransform: element.properties.textTransform || 'none',
              direction: displayOption === 'right side only' ? 'rtl' : 'ltr',
            }"
            :dir="displayOption === 'right side only' ? 'rtl' : 'ltr'"
          >
            {{ element.properties.text || element.properties.exampleText }}
          </div>
          <img
            v-else-if="element.type === 'image'"
            :src="element.properties.src"
            class="w-full h-full object-contain"
            :style="{
              border: `1px solid ${
                element.properties.strokeColor || '#eb2f2f'
              }`,
              borderWidth: `${element.properties.strokeWidth || 1}px`,
            }"
          />
          <qrcode
            v-else-if="element.type === 'qrcode'"
            :content="element.properties.content"
            :size="element.properties.size"
            class="w-full h-full"
          />
          <div
            v-else-if="element.type === 'line'"
            :style="{
              width: '100%',
              height: `${element.properties.thickness}px`,
              backgroundColor: element.properties.color,
            }"
          />
        </div>
      </div>
    </div>

    <!-- Sidebar -->
    <Sidebar
      :selected-element="selectedElement"
      :selected-element-type="selectedElementType"
      :layers="layers"
      :selected-layer="selectedLayer"
      :current-properties="currentProperties"
      :display-option="displayOption"
      @drag-start="dragStart"
      @align-horizontal="alignHorizontal"
      @align-vertical="alignVertical"
      @update-properties="updateProperties"
      @apply-text-style="applyTextStyle"
      @make-transparent="makeTransparent"
      @apply-color="applyColor"
      @apply-text-align="applyTextAlign"
      @apply-vertical-align="applyVerticalAlign"
      @apply-text-transform="applyTextTransform"
      @toggle-text-style="toggleTextStyle"
      @apply-display-option="applyDisplayOption"
      @select-layer="selectLayer"
      @toggle-layer-visibility="toggleLayerVisibility"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from "vue";
import interact from "interactjs";

// Load Iconify script
onMounted(() => {
  const script = document.createElement("script");
  script.src =
    "https://code.iconify.design/iconify-icon/3.0.0/iconify-icon.min.js";
  script.async = true;
  document.head.appendChild(script);
});

// Canvas and elements
const canvas = ref(null);
const canvasSize = ref({ width: 210, height: 297 }); // A4 size in mm
const elements = ref([]);
const selectedElement = ref(null);
const selectedLayer = ref(null);
const layers = ref([]);
const displayOption = ref("both sides");
const currentProperties = ref({});

// Computed property to get the selected element type
const selectedElementType = computed(() => {
  if (selectedElement.value === null) return null;
  const element = elements.value.find((e) => e.id === selectedElement.value);
  return element ? element.type : null;
});

// Element types
const elementTypeMap = {
  text: {
    properties: {
      x: 40.1,
      y: 130,
      width: 317,
      height: 55,
      rotation: 0,
      font: "Roboto",
      fontStyle: "Regular",
      fontSize: 16,
      color: "#000000",
      text: "",
      exampleText: "Sample Text",
      fillTransparency: false,
      textDecoration: "none",
      lock: false,
      fillColor: "#ffffff",
      textAlign: "left",
      verticalAlign: "top",
      textTransform: "none",
    },
  },
  image: {
    properties: {
      x: 148.5,
      y: 284,
      width: 100,
      height: 100,
      rotation: 0,
      src: "https://via.placeholder.com/150",
      lock: false,
      strokeColor: "#eb2f2f",
      strokeWidth: 1,
      associatedData: "User ID",
    },
  },
  qrcode: {
    properties: {
      x: 20.5,
      y: 25,
      width: 100,
      height: 100,
      rotation: 0,
      content: "https://example.com",
      size: 100,
      lock: false,
    },
  },
  line: {
    properties: {
      x: 0,
      y: 0,
      width: 100,
      height: 2,
      rotation: 0,
      color: "#000000",
      thickness: 2,
      lock: false,
    },
  },
  background: {
    properties: {
      x: 0,
      y: 0,
      width: 210,
      height: 297,
      rotation: 0,
      color: "#ffffff",
      lock: false,
    },
  },
  eventlogo: {
    properties: {
      x: 0,
      y: 0,
      width: 100,
      height: 100,
      rotation: 0,
      src: "https://via.placeholder.com/100",
      lock: false,
    },
  },
  useravatar: {
    properties: {
      x: 0,
      y: 0,
      width: 50,
      height: 50,
      rotation: 0,
      src: "https://via.placeholder.com/50",
      lock: false,
    },
  },
  punching_area: {
    properties: {
      x: 0,
      y: 0,
      width: 100,
      height: 20,
      rotation: 0,
      color: "#000000",
      lock: false,
    },
  },
};

const handleKeydown = (elementId, event) => {
  if (displayOption.value !== "right side only") return;
  const element = elements.value.find((e) => e.id === elementId);
  if (!element || element.type !== "text") return;

  // Handle space key
  if (event.key === " " || event.key === "Spacebar") {
    event.preventDefault(); // Prevent default space behavior
    const target = event.target;
    const text = target.textContent || "";
    target.textContent = text + " "; // Append space to the end
    element.properties.text = target.textContent; // Update element text
    currentProperties.value.text = target.textContent; // Update current properties

    // Move cursor to the end
    const range = document.createRange();
    const selection = window.getSelection();
    range.selectNodeContents(target);
    range.collapse(false); // Collapse to the end
    selection.removeAllRanges();
    selection.addRange(range);
  }
};

// Element management
const addElement = (type, x = 100, y = 100) => {
  const id = Date.now();
  const elementType = elementTypeMap[type];
  if (!elementType) return;

  const element = {
    id,
    type,
    properties: { ...elementType.properties, x, y },
  };

  elements.value.push(element);
  layers.value.push({
    id,
    name: `Layer ${layers.value.length + 1}`,
    type,
    visible: true,
    locked: false,
  });

  nextTick(() => setupInteract(element));
  return element;
};

const setupInteract = (element) => {
  const target = document.querySelector(`[data-element-id="${element.id}"]`);
  if (!target) return;

  interact(target)
    .draggable({
      enabled: !element.properties.lock,
      inertia: true,
      modifiers: [
        interact.modifiers.restrictRect({
          restriction: canvas.value,
          endOnly: true,
        }),
      ],
      onmove: (event) => {
        if (element.properties.lock) return;
        const index = elements.value.findIndex((e) => e.id === element.id);
        if (index !== -1) {
          elements.value[index].properties.x += event.dx;
          elements.value[index].properties.y += event.dy;
          updateProperties();
        }
      },
    })
    .resizable({
      enabled: !element.properties.lock,
      edges: { left: true, right: true, bottom: true, top: true },
      modifiers: [
        interact.modifiers.restrictSize({
          min: { width: 10, height: 10 },
        }),
      ],
      onmove: (event) => {
        if (element.properties.lock) return;
        const index = elements.value.findIndex((e) => e.id === element.id);
        if (index !== -1) {
          elements.value[index].properties.width = event.rect.width;
          elements.value[index].properties.height = event.rect.height;
          elements.value[index].properties.x += event.deltaRect.left;
          elements.value[index].properties.y += event.deltaRect.top;
          updateProperties();
        }
      },
    })
    .on("tap", () => selectElement(element.id));
};

const selectElement = (elementId) => {
  console.log("Selecting element:", elementId);
  selectedElement.value = elementId;
  selectedLayer.value = layers.value.findIndex(
    (layer) => layer.id === elementId
  );
  updateProperties();
};

const selectLayer = (index) => {
  console.log("Selecting layer:", index);
  selectedLayer.value = index;
  selectedElement.value = layers.value[index].id;
  updateProperties();
};

const updateProperties = (newProperties = null) => {
  if (selectedElement.value === null) return;
  const element = elements.value.find((e) => e.id === selectedElement.value);
  if (!element) return;

  if (newProperties) {
    if (element.type === "text") {
      element.properties = {
        ...element.properties,
        ...newProperties,
        width: newProperties.w,
        height: newProperties.h,
        rotation: newProperties.r,
      };
    } else if (
      element.type === "image" ||
      element.type === "eventlogo" ||
      element.type === "useravatar"
    ) {
      element.properties = {
        ...element.properties,
        ...newProperties,
        width: newProperties.w,
        height: newProperties.h,
        rotation: newProperties.r,
      };
    } else if (element.type === "qrcode") {
      element.properties = {
        ...element.properties,
        ...newProperties,
        width: newProperties.w,
        height: newProperties.h,
        rotation: newProperties.r,
      };
    } else {
      element.properties = {
        ...element.properties,
        ...newProperties,
        width: newProperties.w,
        height: newProperties.h,
        rotation: newProperties.r,
      };
    }
  }

  if (element.type === "text") {
    currentProperties.value = {
      x: element.properties.x || 40.1,
      y: element.properties.y || 130,
      w: element.properties.width || 317,
      h: element.properties.height || 55,
      r: element.properties.rotation || 0,
      font: element.properties.font || "Roboto",
      fontStyle: element.properties.fontStyle || "Regular",
      fontSize: element.properties.fontSize || 16,
      fillColor: element.properties.fillColor || "#ffffff",
      fillTransparency: element.properties.fillTransparency || false,
      text: element.properties.text || element.properties.exampleText || "",
      textDecoration: element.properties.textDecoration || "none",
      color: element.properties.color || "#000000",
      textAlign: element.properties.textAlign || "left",
      verticalAlign: element.properties.verticalAlign || "top",
      textTransform: element.properties.textTransform || "none",
    };
  } else if (
    element.type === "image" ||
    element.type === "eventlogo" ||
    element.type === "useravatar"
  ) {
    currentProperties.value = {
      x: element.properties.x || 148.5,
      y: element.properties.y || 284,
      w: element.properties.width || 100,
      h: element.properties.height || 100,
      r: element.properties.rotation || 0,
      src: element.properties.src || "https://via.placeholder.com/150",
      strokeColor: element.properties.strokeColor || "#eb2f2f",
      strokeWidth: element.properties.strokeWidth || 1,
      associatedData: element.properties.associatedData || "User ID",
    };
  } else if (element.type === "qrcode") {
    currentProperties.value = {
      x: element.properties.x || 20.5,
      y: element.properties.y || 25,
      w: element.properties.width || 100,
      h: element.properties.height || 100,
      r: element.properties.rotation || 0,
      content: element.properties.content || "https://example.com",
      size: element.properties.size || 100,
    };
  } else {
    currentProperties.value = {
      x: element.properties.x || 0,
      y: element.properties.y || 0,
      w: element.properties.width || 100,
      h: element.properties.height || 100,
      r: element.properties.rotation || 0,
    };
  }
};

const deleteElement = () => {
  if (!selectedElement.value) return;

  const index = elements.value.findIndex((e) => e.id === selectedElement.value);
  if (index === -1) return;

  elements.value.splice(index, 1);
  layers.value = layers.value.filter(
    (layer) => layer.id !== selectedElement.value
  );
  selectedElement.value = null;
  selectedLayer.value = null;
  currentProperties.value = {};
};

const savePosition = () => {
  if (selectedElement.value === null) return;

  const index = elements.value.findIndex((e) => e.id === selectedElement.value);
  if (index === -1) return;

  const element = elements.value[index];
  if (element.type === "text") {
    element.properties = {
      ...element.properties,
      x: currentProperties.value.x,
      y: currentProperties.value.y,
      width: currentProperties.value.w,
      height: currentProperties.value.h,
      rotation: currentProperties.value.r,
      font: currentProperties.value.font,
      fontStyle: currentProperties.value.fontStyle,
      fontSize: currentProperties.value.fontSize,
      fillColor: currentProperties.value.fillColor,
      fillTransparency: currentProperties.value.fillTransparency,
      text: currentProperties.value.text,
      textDecoration: currentProperties.value.textDecoration,
      color: currentProperties.value.color,
      textAlign: currentProperties.value.textAlign,
      verticalAlign: currentProperties.value.verticalAlign,
      textTransform: currentProperties.value.textTransform,
    };
  } else if (
    element.type === "image" ||
    element.type === "eventlogo" ||
    element.type === "useravatar"
  ) {
    element.properties = {
      ...element.properties,
      x: currentProperties.value.x,
      y: currentProperties.value.y,
      width: currentProperties.value.w,
      height: currentProperties.value.h,
      rotation: currentProperties.value.r,
      src: currentProperties.value.src,
      strokeColor: currentProperties.value.strokeColor,
      strokeWidth: currentProperties.value.strokeWidth,
      associatedData: currentProperties.value.associatedData,
    };
  } else if (element.type === "qrcode") {
    element.properties = {
      ...element.properties,
      x: currentProperties.value.x,
      y: currentProperties.value.y,
      width: currentProperties.value.w,
      height: currentProperties.value.h,
      rotation: currentProperties.value.r,
      content: currentProperties.value.content,
      size: currentProperties.value.size,
    };
  } else {
    element.properties = {
      ...element.properties,
      x: currentProperties.value.x,
      y: currentProperties.value.y,
      width: currentProperties.value.w,
      height: currentProperties.value.h,
      rotation: currentProperties.value.r,
    };
  }
};

const saveDesign = () => {
  const designData = {
    elements: elements.value.map((element) => ({
      ...element,
      properties: { ...element.properties },
    })),
    layers: layers.value,
    canvasSize: canvasSize.value,
  };
  console.log("Saving design:", designData);
  return designData;
};

const loadDesign = (data) => {
  elements.value = data.elements;
  layers.value = data.layers;
  canvasSize.value = data.canvasSize;
  nextTick(() => elements.value.forEach(setupInteract));
};

const handleCanvasClick = (event) => {
  if (!event.target.closest("[data-element-id]")) {
    selectedElement.value = null;
    selectedLayer.value = null;
    currentProperties.value = {};
  }
};

const updateText = (elementId, event) => {
  const element = elements.value.find((e) => e.id === elementId);
  if (element && element.type === "text") {
    element.properties.text = event.target.textContent;
    currentProperties.value.text = event.target.textContent;
    applyTextStyle();
  }
};

const dragStart = (type) => {
  event.dataTransfer.setData("text/plain", type);
};

const onDrop = (event) => {
  event.preventDefault();
  const rect = canvas.value.getBoundingClientRect();
  const x = event.clientX - rect.left;
  const y = event.clientY - rect.top;
  const type = event.dataTransfer.getData("text");

  if (type) {
    const element = addElement(type, x, y);
    if (element) {
      selectElement(element.id);
    }
  }
};

const toggleLayerVisibility = (index) => {
  layers.value[index].visible = !layers.value[index].visible;
  const elementIndex = elements.value.findIndex(
    (e) => e.id === layers.value[index].id
  );
  if (elementIndex !== -1) {
    elements.value[elementIndex].properties.lock = !layers.value[index].visible;
    updateProperties();
  }
};

// Alignment methods
const alignHorizontal = (alignment) => {
  if (selectedElement.value === null) return;
  const element = elements.value.find((e) => e.id === selectedElement.value);
  if (!element) return;

  const canvasRect = canvas.value.getBoundingClientRect();
  const elementWidth = currentProperties.value.w || element.properties.width;

  switch (alignment) {
    case "left":
      currentProperties.value.x = 0;
      break;
    case "center":
      currentProperties.value.x = (canvasRect.width - elementWidth) / 2;
      break;
    case "right":
      currentProperties.value.x = canvasRect.width - elementWidth;
      break;
  }
  savePosition();
};

const alignVertical = (alignment) => {
  if (selectedElement.value === null) return;
  const element = elements.value.find((e) => e.id === selectedElement.value);
  if (!element) return;

  const canvasRect = canvas.value.getBoundingClientRect();
  const elementHeight = currentProperties.value.h || element.properties.height;

  switch (alignment) {
    case "top":
      currentProperties.value.y = 0;
      break;
    case "middle":
      currentProperties.value.y = (canvasRect.height - elementHeight) / 2;
      break;
    case "bottom":
      currentProperties.value.y = canvasRect.height - elementHeight;
      break;
  }
  savePosition();
};

// Text style methods
const toggleTextStyle = (style) => {
  if (selectedElement.value === null) return;
  const element = elements.value.find((e) => e.id === selectedElement.value);
  if (!element || element.type !== "text") return;

  if (style === "bold") {
    currentProperties.value.fontStyle =
      currentProperties.value.fontStyle === "Bold" ? "Regular" : "Bold";
  } else if (style === "italic") {
    currentProperties.value.fontStyle =
      currentProperties.value.fontStyle === "Italic" ? "Regular" : "Italic";
  } else if (style === "underline") {
    currentProperties.value.textDecoration =
      currentProperties.value.textDecoration === "underline"
        ? "none"
        : "underline";
  }
  applyTextStyle();
};

const applyTextStyle = (newProperties = null) => {
  if (selectedElement.value === null) return;
  const element = elements.value.find((e) => e.id === selectedElement.value);
  if (!element || element.type !== "text") return;

  if (newProperties) {
    currentProperties.value = { ...currentProperties.value, ...newProperties };
  }

  const target = document.querySelector(
    `[data-element-id="${element.id}"] div[contenteditable]`
  );
  if (target) {
    target.style.fontFamily = currentProperties.value.font;
    target.style.fontSize = `${currentProperties.value.fontSize}px`;
    target.style.fontWeight =
      currentProperties.value.fontStyle === "Bold" ? "bold" : "normal";
    target.style.fontStyle =
      currentProperties.value.fontStyle === "Italic" ? "italic" : "normal";
    target.style.color = currentProperties.value.color;
    target.style.backgroundColor = currentProperties.value.fillTransparency
      ? "transparent"
      : currentProperties.value.fillColor;
    target.style.textDecoration = currentProperties.value.textDecoration;
    target.style.textAlign =
      displayOption.value === "right side only"
        ? "right"
        : currentProperties.value.textAlign;
    target.style.verticalAlign = currentProperties.value.verticalAlign;
    target.style.textTransform = currentProperties.value.textTransform;
    target.style.direction =
      displayOption.value === "right side only" ? "rtl" : "ltr";
    target.setAttribute(
      "dir",
      displayOption.value === "right side only" ? "rtl" : "ltr"
    );
    target.textContent =
      currentProperties.value.text || element.properties.exampleText;

    // Ensure cursor is at the end for RTL
    if (displayOption.value === "right side only") {
      const range = document.createRange();
      const selection = window.getSelection();
      range.selectNodeContents(target);
      range.collapse(false); // Collapse to the end
      selection.removeAllRanges();
      selection.addRange(range);
    }
  }
  savePosition();
};

const makeTransparent = () => {
  if (selectedElementType.value === "text") {
    currentProperties.value.fillColor = "rgba(0, 0, 0, 0)";
    currentProperties.value.fillTransparency = true;
    applyTextStyle();
  }
};

const applyColor = (color) => {
  if (selectedElementType.value === "text") {
    currentProperties.value.fillColor = color;
    currentProperties.value.fillTransparency = false;
    applyTextStyle();
    nextTick(() => {
      const target = document.querySelector(
        `[data-element-id="${selectedElement.value}"] div[contenteditable]`
      );
      if (target)
        target.style.backgroundColor = currentProperties.value.fillColor;
    });
  }
};

const applyTextAlign = (align) => {
  if (selectedElementType.value === "text") {
    currentProperties.value.textAlign = align;
    applyTextStyle();
  }
};

const applyVerticalAlign = (align) => {
  if (selectedElementType.value === "text") {
    currentProperties.value.verticalAlign = align;
    applyTextStyle();
  }
};

const applyTextTransform = (transform) => {
  if (selectedElementType.value === "text") {
    currentProperties.value.textTransform = transform;
    applyTextStyle();
  }
};

const applyDisplayOption = (option) => {
  displayOption.value = option;
  applyTextStyle();
};

// Watch selectedElement to update properties
watch(selectedElement, () => {
  updateProperties();
  if (selectedElement.value) applyTextStyle();
});

// Watch currentProperties to save changes
watch(currentProperties, () => savePosition(), { deep: true });

// Initialize interact.js
onMounted(() => {
  elements.value.forEach(setupInteract);
});

watch(displayOption, () => {
  applyTextStyle();
});
</script>

<script>
export default {
  components: {
    qrcode: {
      props: ["content", "size"],
      template: `<div><canvas :width="size" :height="size" ref="qr"></canvas></div>`,
      mounted() {
        this.updateQR();
      },
      watch: {
        content() {
          this.updateQR();
        },
        size() {
          this.updateQR();
        },
      },
      methods: {
        async updateQR() {
          if (!this.$refs.qr || !this.content || !this.size) return;
          try {
            const qrcode = await import("qrcode");
            const toCanvas = qrcode.default?.toCanvas || qrcode.toCanvas;
            if (toCanvas) {
              toCanvas(
                this.$refs.qr,
                this.content,
                {
                  width: this.size,
                  errorCorrectionLevel: "H",
                },
                (error) => {
                  if (error) console.error("Failed to render QR code:", error);
                }
              );
            } else {
              console.error("toCanvas function not found in qrcode module");
            }
          } catch (err) {
            console.error("Failed to load qrcode module:", err);
          }
        },
      },
    },
  },
};
</script>

<style scoped>
.draggable {
  user-select: none;
  touch-action: none;
}

[contenteditable] {
  outline: none;
  user-select: text;
  white-space: pre-wrap;
  unicode-bidi: embed;
  overflow-wrap: break-word;
}

img {
  max-width: 100%;
  max-height: 100%;
  border: 1px solid;
  border-width: 1px;
}

canvas {
  display: block;
}
</style>
