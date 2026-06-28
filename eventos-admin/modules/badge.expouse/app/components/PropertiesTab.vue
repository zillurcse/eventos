<template>
  <div class="space-y-4">
    <div v-if="selectedElement === null">
      <p class="text-gray-500">
        Select an element to view and edit its properties.
      </p>
    </div>
    <template v-else>
      <h3 class="font-bold">{{ selectedElementType }} Properties</h3>
      <div class="space-y-4">
        <!-- Geometry Section -->
        <div class="bg-white p-2 rounded shadow">
          <div class="flex items-center mb-2">
            <iconify-icon
              icon="mdi:arrow-right"
              class="w-5 h-5 mr-2 text-gray-600"
              aria-hidden="true"
            ></iconify-icon>
            <span class="font-semibold">Geometry</span>
          </div>
          <div class="grid grid-cols-3 gap-2 mb-2">
            <button
              @click="alignHorizontal('left')"
              class="p-2 bg-gray-200 rounded hover:bg-gray-300 active:bg-gray-400"
            >
              <iconify-icon
                icon="mdi:format-align-left"
                class="w-5 h-5"
                aria-hidden="true"
              ></iconify-icon>
            </button>
            <button
              @click="alignHorizontal('center')"
              class="p-2 bg-gray-200 rounded hover:bg-gray-300 active:bg-gray-400"
            >
              <iconify-icon
                icon="mdi:format-align-center"
                class="w-5 h-5"
                aria-hidden="true"
              ></iconify-icon>
            </button>
            <button
              @click="alignHorizontal('right')"
              class="p-2 bg-gray-200 rounded hover:bg-gray-300 active:bg-gray-400"
            >
              <iconify-icon
                icon="mdi:format-align-right"
                class="w-5 h-5"
                aria-hidden="true"
              ></iconify-icon>
            </button>
            <button
              @click="alignVertical('top')"
              class="p-2 bg-gray-200 rounded hover:bg-gray-300 active:bg-gray-400"
            >
              <iconify-icon
                icon="mdi:format-align-top"
                class="w-5 h-5"
                aria-hidden="true"
              ></iconify-icon>
            </button>
            <button
              @click="alignVertical('middle')"
              class="p-2 bg-gray-200 rounded hover:bg-gray-300 active:bg-gray-400"
            >
              <iconify-icon
                icon="mdi:format-align-middle"
                class="w-5 h-5"
                aria-hidden="true"
              ></iconify-icon>
            </button>
            <button
              @click="alignVertical('bottom')"
              class="p-2 bg-gray-200 rounded hover:bg-gray-300 active:bg-gray-400"
            >
              <iconify-icon
                icon="mdi:format-align-bottom"
                class="w-5 h-5"
                aria-hidden="true"
              ></iconify-icon>
            </button>
          </div>
          <div class="grid grid-cols-2 gap-2 mt-2">
            <label class="block">
              X:
              <input
                v-model.number="currentProperties.x"
                type="number"
                class="border p-1 w-full rounded"
                @input="emitProperties"
              />
            </label>
            <label class="block">
              Y:
              <input
                v-model.number="currentProperties.y"
                type="number"
                class="border p-1 w-full rounded"
                @input="emitProperties"
              />
            </label>
            <label class="block">
              Width:
              <input
                v-model.number="currentProperties.width"
                type="number"
                class="border p-1 w-full rounded"
                @input="emitProperties"
              />
            </label>
            <label class="block">
              Height:
              <input
                v-model.number="currentProperties.height"
                type="number"
                class="border p-1 w-full rounded"
                @input="emitProperties"
              />
            </label>
            <label class="block">
              Rotation:
              <input
                v-model.number="currentProperties.rotation"
                type="number"
                class="border p-1 w-full rounded"
                @input="emitProperties"
              />
            </label>
          </div>
        </div>

        <!-- Text Specific Properties -->
        <template v-if="selectedElementType === 'text'">
          <!-- Font Section -->
          <div class="bg-white p-2 rounded shadow">
            <div class="flex items-center mb-2">
              <iconify-icon
                icon="mdi:format-font"
                class="w-5 h-5 mr-2 text-gray-600"
                aria-hidden="true"
              ></iconify-icon>
              <span class="font-semibold">Font</span>
            </div>
            <label class="block">
              Font:
              <select
                v-model="currentProperties.font"
                class="border p-1 w-full rounded"
                @change="emitProperties"
              >
                <option>Roboto</option>
                <option>Arial</option>
                <option>Helvetica</option>
              </select>
            </label>
            <label class="block mt-2">
              Font Size:
              <select
                v-model.number="currentProperties.fontSize"
                class="border p-1 w-full rounded"
                @change="emitProperties"
              >
                <option>Auto (10-50)</option>
                <option
                  v-for="size in [10, 12, 14, 16, 18, 20, 24, 36]"
                  :key="size"
                >
                  {{ size }}
                </option>
              </select>
            </label>
          </div>

          <!-- Fill Section -->
          <div class="bg-white p-2 rounded shadow">
            <div class="flex items-center mb-2">
              <iconify-icon
                icon="mdi:palette"
                class="w-5 h-5 mr-2 text-gray-600"
                aria-hidden="true"
              ></iconify-icon>
              <span class="font-semibold">Fill</span>
            </div>
            <div class="flex items-center mb-2">
              <div class="fill-color" @click="showColorPicker">
                <div
                  class="color-preview"
                  :style="{
                    backgroundColor: currentProperties.fillColor || '#000000',
                  }"
                ></div>
                <span>{{
                  currentProperties.fillColor || "rgba(0, 0, 0, 0)"
                }}</span>
              </div>
              <div class="transparent ml-2" @click="makeTransparent">
                <span>☐</span>
                <span>Transparent</span>
              </div>
            </div>
            <!-- Color Picker Popup -->
            <div class="color-popup" v-if="showPopup">
              <div class="color-picker">
                <div
                  class="gradient"
                  :style="{
                    background: `linear-gradient(to right, #fff, hsl(${hue}deg 100% 50%)), linear-gradient(to top, transparent, #000)`,
                  }"
                  @mousedown="startColorPick"
                  @mousemove="updateColor"
                  @mouseup="stopColorPick"
                  @mouseleave="stopColorPick"
                ></div>
                <div
                  class="slider"
                  @mousedown="startHuePick"
                  @mousemove="updateHue"
                  @mouseup="stopHuePick"
                  @mouseleave="stopHuePick"
                ></div>
              </div>
              <input type="text" v-model="selectedColor" readonly />
              <button @click="cancelColor">Cancel</button>
              <button @click="applyColor">Choose</button>
            </div>
          </div>

          <!-- Text Style and Alignment Section -->
          <div class="bg-white p-2 rounded shadow">
            <div class="flex items-center mb-2">
              <iconify-icon
                icon="mdi:text"
                class="w-5 h-5 mr-2 text-gray-600"
                aria-hidden="true"
              ></iconify-icon>
              <span class="font-semibold">Text Style</span>
            </div>
            <div class="flex space-x-2 mb-2">
              <button
                @click="toggleTextStyle('bold')"
                :class="{
                  'bg-blue-500 text-white':
                    currentProperties.fontStyle === 'Bold',
                }"
                class="p-2 bg-gray-200 rounded hover:bg-gray-300"
              >
                <iconify-icon
                  icon="mdi:format-bold"
                  class="w-5 h-5"
                  aria-hidden="true"
                ></iconify-icon>
              </button>
              <button
                @click="toggleTextStyle('italic')"
                :class="{
                  'bg-blue-500 text-white':
                    currentProperties.fontStyle === 'Italic',
                }"
                class="p-2 bg-gray-200 rounded hover:bg-gray-300"
              >
                <iconify-icon
                  icon="mdi:format-italic"
                  class="w-5 h-5"
                  aria-hidden="true"
                ></iconify-icon>
              </button>
              <button
                @click="toggleTextStyle('underline')"
                :class="{
                  'bg-blue-500 text-white':
                    currentProperties.textDecoration === 'underline',
                }"
                class="p-2 bg-gray-200 rounded hover:bg-gray-300"
              >
                <iconify-icon
                  icon="mdi:format-underline"
                  class="w-5 h-5"
                  aria-hidden="true"
                ></iconify-icon>
              </button>
            </div>
            <div class="grid grid-cols-3 gap-2 mb-2">
              <button
                @click="applyTextAlign('left')"
                :class="{
                  'bg-blue-500 text-white':
                    currentProperties.textAlign === 'left',
                }"
                class="p-2 bg-gray-200 rounded hover:bg-gray-300"
              >
                <iconify-icon
                  icon="mdi:format-align-left"
                  class="w-5 h-5"
                  aria-hidden="true"
                ></iconify-icon>
              </button>
              <button
                @click="applyTextAlign('center')"
                :class="{
                  'bg-blue-500 text-white':
                    currentProperties.textAlign === 'center',
                }"
                class="p-2 bg-gray-200 rounded hover:bg-gray-300"
              >
                <iconify-icon
                  icon="mdi:format-align-center"
                  class="w-5 h-5"
                  aria-hidden="true"
                ></iconify-icon>
              </button>
              <button
                @click="applyTextAlign('right')"
                :class="{
                  'bg-blue-500 text-white':
                    currentProperties.textAlign === 'right',
                }"
                class="p-2 bg-gray-200 rounded hover:bg-gray-300"
              >
                <iconify-icon
                  icon="mdi:format-align-right"
                  class="w-5 h-5"
                  aria-hidden="true"
                ></iconify-icon>
              </button>
              <button
                @click="applyVerticalAlign('top')"
                :class="{
                  'bg-blue-500 text-white':
                    currentProperties.verticalAlign === 'top',
                }"
                class="p-2 bg-gray-200 rounded hover:bg-gray-300"
              >
                <iconify-icon
                  icon="mdi:format-align-top"
                  class="w-5 h-5"
                  aria-hidden="true"
                ></iconify-icon>
              </button>
              <button
                @click="applyVerticalAlign('middle')"
                :class="{
                  'bg-blue-500 text-white':
                    currentProperties.verticalAlign === 'middle',
                }"
                class="p-2 bg-gray-200 rounded hover:bg-gray-300"
              >
                <iconify-icon
                  icon="mdi:format-align-middle"
                  class="w-5 h-5"
                  aria-hidden="true"
                ></iconify-icon>
              </button>
              <button
                @click="applyVerticalAlign('bottom')"
                :class="{
                  'bg-blue-500 text-white':
                    currentProperties.verticalAlign === 'bottom',
                }"
                class="p-2 bg-gray-200 rounded hover:bg-gray-300"
              >
                <iconify-icon
                  icon="mdi:format-align-bottom"
                  class="w-5 h-5"
                  aria-hidden="true"
                ></iconify-icon>
              </button>
            </div>
            <div class="grid grid-cols-3 gap-2 mb-2">
              <button
                @click="applyTextTransform('uppercase')"
                :class="{
                  'bg-blue-500 text-white':
                    currentProperties.textTransform === 'uppercase',
                }"
                class="p-2 bg-gray-200 rounded hover:bg-gray-300"
              >
                <iconify-icon
                  icon="mdi:format-letter-uppercase"
                  class="w-5 h-5"
                  aria-hidden="true"
                ></iconify-icon>
              </button>
              <button
                @click="applyTextTransform('lowercase')"
                :class="{
                  'bg-blue-500 text-white':
                    currentProperties.textTransform === 'lowercase',
                }"
                class="p-2 bg-gray-200 rounded hover:bg-gray-300"
              >
                <iconify-icon
                  icon="mdi:format-letter-lowercase"
                  class="w-5 h-5"
                  aria-hidden="true"
                ></iconify-icon>
              </button>
              <button
                @click="applyTextTransform('capitalize')"
                :class="{
                  'bg-blue-500 text-white':
                    currentProperties.textTransform === 'capitalize',
                }"
                class="p-2 bg-gray-200 rounded hover:bg-gray-300"
              >
                <iconify-icon
                  icon="mdi:format-letter-case"
                  class="w-5 h-5"
                  aria-hidden="true"
                ></iconify-icon>
              </button>
            </div>
            <label class="block">
              Text:
              <input
                v-model="currentProperties.text"
                class="border p-1 w-full rounded"
                @input="emitProperties"
              />
            </label>
            <div class="flex space-x-2 mt-2">
              <input
                type="radio"
                :value="'both sides'"
                :checked="displayOption === 'both sides'"
                @change="
                  $emit('update:displayOption', 'both sides');
                  emitProperties();
                "
              />
              Both sides
              <input
                type="radio"
                :value="'left side only'"
                :checked="displayOption === 'left side only'"
                @change="
                  $emit('update:displayOption', 'left side only');
                  emitProperties();
                "
              />
              Left side only
              <input
                type="radio"
                :value="'right side only'"
                :checked="displayOption === 'right side only'"
                @change="
                  $emit('update:displayOption', 'right side only');
                  emitProperties();
                "
              />
              Right side only
            </div>
          </div>
        </template>

        <!-- Image Specific Properties -->
        <template v-if="selectedElementType === 'image'">
          <!-- Associated Data Section -->
          <div class="bg-white p-2 rounded shadow">
            <div class="flex items-center mb-2">
              <iconify-icon
                icon="mdi:database"
                class="w-5 h-5 mr-2 text-gray-600"
                aria-hidden="true"
              ></iconify-icon>
              <span class="font-semibold">Associated Data</span>
            </div>
            <label class="block">
              Data:
              <select
                v-model="currentProperties.associatedData"
                class="border p-1 w-full rounded"
                @change="emitProperties"
              >
                <option>User ID</option>
              </select>
            </label>
          </div>

          <!-- Stroke Section -->
          <div class="bg-white p-2 rounded shadow">
            <div class="flex items-center mb-2">
              <iconify-icon
                icon="mdi:brush"
                class="w-5 h-5 mr-2 text-gray-600"
                aria-hidden="true"
              ></iconify-icon>
              <span class="font-semibold">Stroke</span>
            </div>
            <div class="flex space-x-2">
              <label class="block flex-1">
                Color:
                <input
                  v-model="currentProperties.strokeColor"
                  type="color"
                  class="border p-1 w-full rounded mt-1"
                  @input="emitProperties"
                />
              </label>
              <label class="block flex-1">
                Width:
                <input
                  v-model.number="currentProperties.strokeWidth"
                  type="number"
                  class="border p-1 w-full rounded mt-1"
                  @input="emitProperties"
                />
              </label>
            </div>
          </div>
        </template>

        <!-- QR Code Specific Properties -->
        <template v-if="selectedElementType === 'qrcode'">
          <!-- Content Section -->
          <div class="bg-white p-2 rounded shadow">
            <div class="flex items-center mb-2">
              <iconify-icon
                icon="mdi:qrcode"
                class="w-5 h-5 mr-2 text-gray-600"
                aria-hidden="true"
              ></iconify-icon>
              <span class="font-semibold">Content</span>
            </div>
            <label class="block">
              Content:
              <input
                v-model="currentProperties.content"
                class="border p-1 w-full rounded"
                @input="emitProperties"
              />
            </label>
          </div>
        </template>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { hexToRgb, rgbToHsl, hslToRgb, rgbToHex } from "../utils/colorUtils";

const props = defineProps({
  elements: Array,
  selectedElement: [String, Number, null],
  canvasRef: Object,
  displayOption: String,
});

const emit = defineEmits(["update:properties", "update:displayOption"]);

const currentProperties = ref({});
const showPopup = ref(false);
const selectedColor = ref("#000000");
const isPicking = ref(false);
const hue = ref(0);

const selectedElementType = computed(() => {
  if (props.selectedElement === null) return null;
  const element = props.elements.find((e) => e.id === props.selectedElement);
  return element ? element.type : null;
});

watch(
  () => props.selectedElement,
  () => {
    updateProperties();
  }
);

watch(currentProperties, () => emitProperties(), { deep: true });

const updateProperties = () => {
  if (props.selectedElement === null) {
    currentProperties.value = {};
    return;
  }
  const element = props.elements.find((e) => e.id === props.selectedElement);
  if (!element) return;

  if (element.type === "text") {
    currentProperties.value = {
      x: element.properties.x || 40.1,
      y: element.properties.y || 130,
      width: element.properties.width || 317,
      height: element.properties.height || 55,
      rotation: element.properties.rotation || 0,
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
      width: element.properties.width || 100,
      height: element.properties.height || 100,
      rotation: element.properties.rotation || 0,
      src: element.properties.src || "https://via.placeholder.com/150",
      strokeColor: element.properties.strokeColor || "#eb2f2f",
      strokeWidth: element.properties.strokeWidth || 1,
      associatedData: element.properties.associatedData || "User ID",
    };
  } else if (element.type === "qrcode") {
    currentProperties.value = {
      x: element.properties.x || 20.5,
      y: element.properties.y || 25,
      width: element.properties.width || 100,
      height: element.properties.height || 100,
      rotation: element.properties.rotation || 0,
      content: element.properties.content || "https://example.com",
      size: element.properties.size || 100,
    };
  } else {
    currentProperties.value = {
      x: element.properties.x || 0,
      y: element.properties.y || 0,
      width: element.properties.width || 100,
      height: element.properties.height || 100,
      rotation: element.properties.rotation || 0,
    };
  }
};

const emitProperties = () => {
  emit("update:properties", { ...currentProperties.value });
};

const alignHorizontal = (alignment) => {
  if (props.selectedElement === null) return;
  const element = props.elements.find((e) => e.id === props.selectedElement);
  if (!element) return;

  const canvasRect = props.canvasRef.getBoundingClientRect();
  const elementWidth =
    currentProperties.value.width || element.properties.width;

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
  emitProperties();
};

const alignVertical = (alignment) => {
  if (props.selectedElement === null) return;
  const element = props.elements.find((e) => e.id === props.selectedElement);
  if (!element) return;

  const canvasRect = props.canvasRef.getBoundingClientRect();
  const elementHeight =
    currentProperties.value.height || element.properties.height;

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
  emitProperties();
};

const toggleTextStyle = (style) => {
  if (selectedElementType.value !== "text") return;

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
  emitProperties();
};

const applyTextAlign = (align) => {
  if (selectedElementType.value === "text") {
    currentProperties.value.textAlign = align;
    emitProperties();
  }
};

const applyVerticalAlign = (align) => {
  if (selectedElementType.value === "text") {
    currentProperties.value.verticalAlign = align;
    emitProperties();
  }
};

const applyTextTransform = (transform) => {
  if (selectedElementType.value === "text") {
    currentProperties.value.textTransform = transform;
    emitProperties();
  }
};

const showColorPicker = () => {
  if (selectedElementType.value === "text") {
    selectedColor.value = currentProperties.value.fillColor || "#ffffff";
    hue.value = rgbToHsl(...hexToRgb(selectedColor.value)).h * 360;
    showPopup.value = true;
  }
};

const startColorPick = (event) => {
  isPicking.value = true;
  updateColor(event);
};

const updateColor = (event) => {
  if (!isPicking.value || !showPopup.value) return;
  const rect = event.target.getBoundingClientRect();
  const x = Math.max(0, Math.min(event.clientX - rect.left, rect.width));
  const y = Math.max(0, Math.min(event.clientY - rect.top, rect.height));
  const saturation = x / rect.width;
  const lightness = 1 - y / rect.height;
  const rgb = hslToRgb(hue.value / 360, saturation, lightness);
  selectedColor.value = rgbToHex(rgb[0], rgb[1], rgb[2]);
};

const stopColorPick = () => {
  isPicking.value = false;
};

const startHuePick = (event) => {
  isPicking.value = true;
  updateHue(event);
};

const updateHue = (event) => {
  if (!isPicking.value || !showPopup.value) return;
  const rect = event.target.getBoundingClientRect();
  const y = Math.max(0, Math.min(event.clientY - rect.top, rect.height));
  hue.value = Math.round((y / rect.height) * 360);
  const rgb = hslToRgb(hue.value / 360, 1, 0.5);
  selectedColor.value = rgbToHex(rgb[0], rgb[1], rgb[2]);
};

const stopHuePick = () => {
  isPicking.value = false;
};

const cancelColor = () => {
  showPopup.value = false;
};

const applyColor = () => {
  if (selectedElementType.value === "text") {
    currentProperties.value.fillColor = selectedColor.value;
    currentProperties.value.fillTransparency = false;
    emitProperties();
    showPopup.value = false;
  }
};

const makeTransparent = () => {
  if (selectedElementType.value === "text") {
    currentProperties.value.fillColor = "rgba(0, 0, 0, 0)";
    currentProperties.value.fillTransparency = true;
    emitProperties();
  }
};
</script>

<style scoped>
.fill-color,
.transparent {
  display: flex;
  align-items: center;
  cursor: pointer;
  padding: 5px 10px;
  border: 1px solid #ccc;
  border-radius: 4px;
  margin-right: 10px;
}

.fill-color span,
.transparent span {
  margin-left: 5px;
}

.color-preview {
  width: 20px;
  height: 20px;
  border: 1px solid #000;
}

.color-popup {
  display: block;
  background: #fff;
  border: 1px solid #ccc;
  padding: 10px;
  margin-top: 10px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.color-picker {
  display: flex;
  gap: 10px;
}

.gradient {
  width: 100px;
  height: 100px;
  position: relative;
  cursor: crosshair;
}

.slider {
  width: 20px;
  height: 100px;
  background: linear-gradient(
    to top,
    #ff0000,
    #ffff00,
    #00ff00,
    #00ffff,
    #0000ff,
    #ff00ff,
    #ff0000
  );
  cursor: ns-resize;
}

.color-popup input {
  margin-top: 10px;
  padding: 5px;
  width: 100%;
  border: 1px solid #ccc;
  border-radius: 4px;
}

.color-popup button {
  margin-top: 10px;
  padding: 5px 10px;
  margin-right: 5px;
  border: 1px solid #ccc;
  border-radius: 4px;
  background: #f0f0f0;
}

.color-popup button:hover {
  background: #e0e0e0;
}
</style>
