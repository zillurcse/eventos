<!-- components/TextPropertiesPanel.vue -->
<template>
  <div v-if="selectedTextElement" class="text-properties-panel space-y-4">
    <!-- Font Properties -->
    <div class="space-y-3">
      <h4 class="text-sm font-semibold text-gray-800">Text Properties</h4>

      <!-- Font Size & Color -->
      <div class="flex items-center gap-3">
        <div class="flex-1">
          <label class="block text-xs text-gray-600 mb-1">Font Size</label>
          <input
            v-model.number="fontSize"
            type="number"
            min="8"
            max="100"
            class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
            @change="updateTextProperties"
          />
        </div>
        <div class="flex-1">
          <label class="block text-xs text-gray-600 mb-1">Color</label>
          <div class="flex items-center">
            <input
              v-model="textColor"
              type="color"
              class="w-8 h-8 cursor-pointer"
              @input="updateTextProperties"
            />
            <span
              class="text-sm font-mono bg-gray-100 px-3 py-1 min-w-[85px] text-center"
            >
              {{ textColor.toUpperCase() }}
            </span>
          </div>
        </div>
      </div>

      <!-- Font Family & Line Height -->
      <div class="flex items-center gap-3">
        <div class="flex-1">
          <label class="block text-xs text-gray-600 mb-1">Font Family</label>
          <select
            v-model="fontFamily"
            class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
            @change="updateTextProperties"
          >
            <option value="Arial">Arial</option>
            <option value="Verdana">Verdana</option>
            <option value="Helvetica">Helvetica</option>
            <option value="Times New Roman">Times New Roman</option>
            <option value="Georgia">Georgia</option>
            <option value="Courier New">Courier New</option>
          </select>
        </div>
        <div class="flex-1">
          <label class="block text-xs text-gray-600 mb-1">Line Height</label>
          <input
            v-model.number="lineHeight"
            type="number"
            step="0.1"
            min="0.8"
            max="3"
            class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
            @change="updateTextProperties"
          />
        </div>
      </div>

      <!-- Text Formatting -->
      <div class="space-y-2">
        <label class="block text-xs text-gray-600">Text Formatting</label>
        <div class="flex items-center gap-1">
          <button
            @click="toggleTextStyle('bold')"
            :class="[
              'p-2 border rounded text-sm font-medium transition-colors',
              isBold
                ? 'bg-blue-500 text-white border-blue-500'
                : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
            ]"
            title="Bold"
          >
            B
          </button>
          <button
            @click="toggleTextStyle('italic')"
            :class="[
              'p-2 border rounded text-sm font-medium transition-colors italic',
              isItalic
                ? 'bg-blue-500 text-white border-blue-500'
                : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
            ]"
            title="Italic"
          >
            I
          </button>
          <button
            @click="toggleTextStyle('underline')"
            :class="[
              'p-2 border rounded text-sm font-medium transition-colors underline',
              isUnderline
                ? 'bg-blue-500 text-white border-blue-500'
                : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
            ]"
            title="Underline"
          >
            U
          </button>
          <button
            @click="toggleTextStyle('strikethrough')"
            :class="[
              'p-2 border rounded text-sm font-medium transition-colors line-through',
              isStrikethrough
                ? 'bg-blue-500 text-white border-blue-500'
                : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
            ]"
            title="Strikethrough"
          >
            S
          </button>
        </div>
      </div>

      <!-- Text Alignment -->
      <div class="space-y-2">
        <label class="block text-xs text-gray-600">Text Alignment</label>
        <div class="flex items-center gap-1">
          <button
            v-for="align in textAlignments"
            :key="align.value"
            @click="setTextAlignment(align.value)"
            :class="[
              'p-2 border rounded text-sm font-medium transition-colors flex items-center justify-center',
              textAlign === align.value
                ? 'bg-blue-500 text-white border-blue-500'
                : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
            ]"
            :title="align.label"
          >
            <NuxtIcon :name="align.icon" class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Text Transform -->
      <div class="space-y-2">
        <label class="block text-xs text-gray-600">Text Transform</label>
        <div class="flex items-center gap-1">
          <button
            v-for="transform in textTransforms"
            :key="transform.value"
            @click="setTextTransform(transform.value)"
            :class="[
              'p-2 border rounded text-sm font-medium transition-colors',
              textTransform === transform.value
                ? 'bg-blue-500 text-white border-blue-500'
                : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
            ]"
            :title="transform.label"
          >
            {{ transform.label }}
          </button>
        </div>
      </div>
    </div>

    <!-- Background & Shadow -->
    <div class="space-y-3">
      <h4 class="text-sm font-semibold text-gray-800">Background & Shadow</h4>

      <!-- Background Color -->
      <div>
        <label class="block text-xs text-gray-600 mb-1">Background Color</label>
        <ClientOnly>
          <ColorPicker
            v-model="backgroundColor"
            class="w-full"
            @change="updateTextProperties"
          />
        </ClientOnly>
      </div>

      <!-- Shadow Properties -->
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs text-gray-600 mb-1"
            >Shadow Offset X</label
          >
          <input
            v-model.number="shadowOffsetX"
            type="number"
            class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
            @change="updateTextProperties"
          />
        </div>
        <div>
          <label class="block text-xs text-gray-600 mb-1"
            >Shadow Offset Y</label
          >
          <input
            v-model.number="shadowOffsetY"
            type="number"
            class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
            @change="updateTextProperties"
          />
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <div class="flex-1">
            <label class="block text-xs text-gray-600 mb-1">Shadow Color</label>
            <div class="flex items-center">
              <input
                v-model="shadowColor"
                type="color"
                class="w-8 h-8 cursor-pointer"
                @input="updateTextProperties"
              />
              <span
                class="text-sm font-mono bg-gray-100 px-3 py-1 min-w-[85px] text-center"
              >
                {{ shadowColor.toUpperCase() }}
              </span>
            </div>
          </div>
        </div>
        <div>
          <label class="block text-xs text-gray-600 mb-1">Blur</label>
          <input
            v-model.number="shadowBlur"
            type="number"
            min="0"
            max="50"
            class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
            @change="updateTextProperties"
          />
        </div>
      </div>
    </div>

    <!-- Advanced Text Properties -->
    <div class="space-y-3">
      <h4 class="text-sm font-semibold text-gray-800">Advanced</h4>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs text-gray-600 mb-1">Letter Spacing</label>
          <input
            v-model.number="letterSpacing"
            type="number"
            min="0"
            step="0.1"
            class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
            @change="updateTextProperties"
          />
        </div>
        <div>
          <label class="block text-xs text-gray-600 mb-1">Word Spacing</label>
          <input
            v-model.number="wordSpacing"
            type="number"
            min="0"
            step="0.1"
            class="w-full px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
            @change="updateTextProperties"
          />
        </div>
      </div>

      <div>
        <label class="block text-xs text-gray-600 mb-1">Text Opacity</label>
        <input
          v-model.number="textOpacity"
          type="range"
          min="0"
          max="1"
          step="0.1"
          class="w-full"
          @change="updateTextProperties"
        />
        <div class="text-xs text-gray-500 text-right">
          {{ (textOpacity * 100).toFixed(0) }}%
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";

const canvasStore = useCanvasStore();

// Text alignment options
const textAlignments = [
  { value: "left", label: "Align Left", icon: "ph:text-align-left" },
  { value: "center", label: "Align Center", icon: "ph:text-align-center" },
  { value: "right", label: "Align Right", icon: "ph:text-align-right" },
  { value: "justify", label: "Justify", icon: "ph:text-align-justify" },
];

// Text transform options
const textTransforms = [
  { value: "none", label: "Aa" },
  { value: "uppercase", label: "AA" },
  { value: "lowercase", label: "aa" },
  { value: "capitalize", label: "Aa" },
];

// Reactive properties
const fontSize = ref(24);
const textColor = ref("#000000");
const fontFamily = ref("Verdana");
const lineHeight = ref(1.2);
const isBold = ref(false);
const isItalic = ref(false);
const isUnderline = ref(false);
const isStrikethrough = ref(false);
const textAlign = ref("left");
const textTransform = ref("none");
const backgroundColor = ref("rgba(255, 255, 255, 0)");
const shadowOffsetX = ref(0);
const shadowOffsetY = ref(0);
const shadowColor = ref("#000000");
const shadowBlur = ref(0);
const letterSpacing = ref(0);
const wordSpacing = ref(0);
const textOpacity = ref(1);

// Computed properties
const selectedTextElement = computed(() => {
  if (canvasStore.selectedElementId) {
    return canvasStore.domElements.find(
      (el) => el.id === canvasStore.selectedElementId && el.type === "text"
    );
  }
  return null;
});

const selectedTextObject = computed(() => {
  return canvasStore.selectedObjects.find((obj) => obj.type === "drawing");
});

// ========== FUNCTION DECLARATIONS ==========

// Sync properties from DOM element
const syncPropertiesFromElement = (element: any) => {
  const style = element.styleProps || {};

  fontSize.value = style.fontSize || 24;
  textColor.value = style.color || "#000000";
  fontFamily.value = style.fontFamily || "Verdana";
  lineHeight.value = style.lineHeight || 1.2;
  isBold.value = style.fontWeight === "bold";
  isItalic.value = style.fontStyle === "italic";
  isUnderline.value = style.textDecoration === "underline";
  isStrikethrough.value = style.textDecoration === "line-through";
  textAlign.value = style.textAlign || "left";
  textTransform.value = style.textTransform || "none";
  backgroundColor.value = style.backgroundColor || "rgba(255, 255, 255, 0)";
  shadowOffsetX.value = style.shadowOffsetX || 0;
  shadowOffsetY.value = style.shadowOffsetY || 0;
  shadowColor.value = style.shadowColor || "#000000";
  shadowBlur.value = style.shadowBlur || 0;
  letterSpacing.value = style.letterSpacing || 0;
  wordSpacing.value = style.wordSpacing || 0;
  textOpacity.value = style.opacity !== undefined ? style.opacity : 1;
};

// Sync properties from canvas object
const syncPropertiesFromObject = (object: any) => {
  // For canvas text objects, map properties accordingly
  fontSize.value = object.fontSize || 24;
  textColor.value = object.color || "#000000";
  // Add other property mappings as needed
};

// Helper functions
const getTextDecoration = () => {
  const decorations = [];
  if (isUnderline.value) decorations.push("underline");
  if (isStrikethrough.value) decorations.push("line-through");
  return decorations.length > 0 ? decorations.join(" ") : "none";
};

const toggleTextStyle = (style: string) => {
  switch (style) {
    case "bold":
      isBold.value = !isBold.value;
      break;
    case "italic":
      isItalic.value = !isItalic.value;
      break;
    case "underline":
      isUnderline.value = !isUnderline.value;
      break;
    case "strikethrough":
      isStrikethrough.value = !isStrikethrough.value;
      break;
  }
  updateTextProperties();
};

const setTextAlignment = (alignment: string) => {
  textAlign.value = alignment;
  updateTextProperties();
};

const setTextTransform = (transform: string) => {
  textTransform.value = transform;
  updateTextProperties();
};

// Update text properties - FIXED VERSION
const updateTextProperties = () => {
  console.log("🔄 Updating text properties:", {
    fontSize: fontSize.value,
    selectedTextElement: selectedTextElement.value,
    selectedTextObject: selectedTextObject.value,
  });

  if (selectedTextElement.value) {
    const updates = {
      styleProps: {
        fontSize: Number(fontSize.value), // Ensure it's a number
        color: textColor.value,
        fontFamily: fontFamily.value,
        lineHeight: Number(lineHeight.value),
        fontWeight: isBold.value ? "bold" : "normal",
        fontStyle: isItalic.value ? "italic" : "normal",
        textDecoration: getTextDecoration(),
        textAlign: textAlign.value,
        textTransform: textTransform.value,
        backgroundColor: backgroundColor.value,
        shadowOffsetX: Number(shadowOffsetX.value),
        shadowOffsetY: Number(shadowOffsetY.value),
        shadowColor: shadowColor.value,
        shadowBlur: Number(shadowBlur.value),
        letterSpacing: `${letterSpacing.value}px`,
        wordSpacing: `${wordSpacing.value}px`,
        opacity: Number(textOpacity.value),
      },
    };

    console.log("📝 Sending updates to store:", updates);
    canvasStore.updateElement(selectedTextElement.value.id, updates);
  } else if (selectedTextObject.value) {
    // Update canvas text object properties
    const updates = {
      fontSize: Number(fontSize.value),
      color: textColor.value,
    };
    console.log("📝 Sending object updates to store:", updates);
    canvasStore.updateObject(selectedTextObject.value.id, updates);
  }
};

// ========== WATCHERS (MUST BE AFTER FUNCTION DECLARATIONS) ==========

// Watch for selection changes and sync properties
watch(
  selectedTextElement,
  (newElement) => {
    console.log("👀 Selected text element changed:", newElement);
    if (newElement) {
      syncPropertiesFromElement(newElement);
    }
  },
  { immediate: true }
);

watch(
  selectedTextObject,
  (newObject) => {
    console.log("👀 Selected text object changed:", newObject);
    if (newObject) {
      syncPropertiesFromObject(newObject);
    }
  },
  { immediate: true }
);

// Add watcher for debugging fontSize changes
watch(fontSize, (newSize) => {
  console.log("🔢 Font size changed to:", newSize, typeof newSize);
});
</script>

<style scoped></style>
