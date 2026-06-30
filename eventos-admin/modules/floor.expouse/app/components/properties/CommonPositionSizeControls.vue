<!-- components/CommonPositionSizeControls.vue -->
<template>
  <div class="common-position-size-controls space-y-3">
    <h4 class="text-sm font-semibold text-gray-800">Position & Size</h4>

    <div class="space-y-3">
      <!-- Row 1: X and Y -->
      <div class="flex flex-row gap-3">
        <div class="border rounded flex items-center flex-1">
          <span class="p-1 bg-gray-200 text-gray-700 w-8 text-center">X</span>
          <input
            v-model.number="xPositionDisplay"
            type="number"
            class="w-full px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-300 rounded-r"
            @input="handleXChange"
            @change="handleXChange"
            @blur="handleXBlur"
          />
        </div>
        <div class="border rounded flex items-center flex-1">
          <span class="p-1 bg-gray-200 text-gray-700 w-8 text-center">Y</span>
          <input
            v-model.number="yPositionDisplay"
            type="number"
            class="w-full px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-300 rounded-r"
            placeholder="0"
            @input="handleYChange"
            @change="handleYChange"
            @blur="handleYBlur"
          />
        </div>
      </div>

      <!-- Row 2: Width and Height -->
        <div class="flex flex-row gap-3">
        <div class="border rounded flex items-center flex-1">
          <span class="p-1 bg-gray-200 text-gray-700 w-8 text-center">W</span>
          <input
            v-model.number="widthDisplay"
            type="number"
            class="w-full px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-300 rounded-r"
            placeholder="0"
            @change="handleWidthChange"
            @blur="handleWidthBlur"
          />
        </div>
        <div class="border rounded flex items-center flex-1">
          <span class="p-1 bg-gray-200 text-gray-700 w-8 text-center">H</span>
          <input
            v-model.number="heightDisplay"
            type="number"
            class="w-full px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-300 rounded-r"
            placeholder="0"
            @change="handleHeightChange"
            @blur="handleHeightBlur"
          />
        </div>
      </div>

      <!-- Row 3: Rotation -->
      <div class="flex flex-row gap-3">
        <div class="border rounded flex items-center flex-1">
          <span class="p-1 bg-gray-200 text-gray-700 w-8 text-center">
            <NuxtIcon name="ph:arrow-clockwise" class="text-base" />
          </span>
          <input
            v-model.number="rotationDisplay"
            type="number"
            class="w-full px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-300 rounded-r"
            placeholder="0"
            min="0"
            max="360"
            @input="handleRotationChange"
            @change="handleRotationChange"
            @blur="handleRotationBlur"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<!-- components/CommonPositionSizeControls.vue -->
<script setup lang="ts">
import { ref, watch, computed, onMounted } from "vue";
import {
  formatNumberWithDecimals,
  parseFormattedNumber,
} from "@floorplan/utils/numberFormat";
import { useUiStore } from "@floorplan/stores/uiStore"; // ← নতুন ইম্পোর্ট

const uiStore = useUiStore(); // ← UI store

// Props
interface Props {
  position?: { x: number; y: number };
  size?: { width: number; height: number };
  rotation?: number;
}

const props = withDefaults(defineProps<Props>(), {
  position: () => ({ x: 0, y: 0 }),
  size: () => ({ width: 0, height: 0 }),
  rotation: 0,
});

// Emits
const emit = defineEmits<{
  update: [
    updates: {
      position?: { x: number; y: number };
      size?: { width: number; height: number };
      rotation?: number;
    }
  ];
}>();

// Internal values in **centimeters** (যেহেতু বাকি সিস্টেম cm-এ কাজ করে)
const xPosition = ref(props.position.x);
const yPosition = ref(props.position.y);
const widthCm = ref(props.size.width); // ← cm-এ রাখি
const heightCm = ref(props.size.height); // ← cm-এ রাখি
const rotation = ref(props.rotation);

// Display values – current unit অনুযায়ী convert করে দেখাই
const xPositionDisplay = ref(formatNumberWithDecimals(props.position.x));
const yPositionDisplay = ref(formatNumberWithDecimals(props.position.y));

const widthDisplay = computed({
  get(): string {
    const converted = uiStore.convertToCurrentUnit(widthCm.value);
    return formatNumberWithDecimals(converted.value);
  },
  set(val: string) {
    const parsed = parseFormattedNumber(val);
    let cmValue = parsed;
    switch (uiStore.measurementUnit) {
      case "meter":
        cmValue = parsed * 100;
        break;
      case "feet":
        cmValue = parsed * 30.48;
        break;
      case "inches":
        cmValue = parsed * 2.54;
        break;
    }
    widthCm.value = cmValue;

    // ✅ Immediately trigger property update
    updateProperties();
  },
});

const heightDisplay = computed({
  get(): string {
    const converted = uiStore.convertToCurrentUnit(heightCm.value);
    return formatNumberWithDecimals(converted.value);
  },
  set(val: string) {
    const parsed = parseFormattedNumber(val);
    let cmValue = parsed;
    switch (uiStore.measurementUnit) {
      case "meter":
        cmValue = parsed * 100;
        break;
      case "feet":
        cmValue = parsed * 30.48;
        break;
      case "inches":
        cmValue = parsed * 2.54;
        break;
    }
    heightCm.value = cmValue;

    // ✅ Immediately trigger property update
    updateProperties();
  },
});
const rotationDisplay = ref(formatNumberWithDecimals(props.rotation));

// ────────────────────── Update properties ──────────────────────
const updateProperties = () => {
  const updates: any = {};

  if (
    xPosition.value !== props.position.x ||
    yPosition.value !== props.position.y
  ) {
    updates.position = { x: xPosition.value, y: yPosition.value };
  }

  if (
    widthCm.value !== props.size.width ||
    heightCm.value !== props.size.height
  ) {
    updates.size = { width: widthCm.value, height: heightCm.value };
  }

  if (rotation.value !== props.rotation) {
    let newRotation = ((rotation.value % 360) + 360) % 360;
    updates.rotation = newRotation;
    rotation.value = newRotation;
    rotationDisplay.value = formatNumberWithDecimals(newRotation);
  }

  if (Object.keys(updates).length > 0) {
    console.log("🔄 PositionSizeControls emitting dynamic updates:", updates);
    emit("update", updates);
  }
};

// ────────────────────── Handlers ──────────────────────
const handleXChange = () => {
  xPosition.value = parseFormattedNumber(xPositionDisplay.value);
  updateProperties();
};

const handleXBlur = () => {
  xPositionDisplay.value = formatNumberWithDecimals(xPosition.value);
};

const handleYChange = () => {
  yPosition.value = parseFormattedNumber(yPositionDisplay.value);
  updateProperties();
};

const handleYBlur = () => {
  yPositionDisplay.value = formatNumberWithDecimals(yPosition.value);
};

// Width & Height change – computed setter ব্যবহার করায় কোনো অতিরিক্ত handler লাগবে না
const handleWidthChange = () => updateProperties();
const handleHeightChange = () => updateProperties();

const handleWidthBlur = () => {
  // blur-এ display আবার সুন্দর করে দেখাই
  const converted = uiStore.convertToCurrentUnit(widthCm.value);
  widthDisplay.value = formatNumberWithDecimals(converted.value);
};

const handleHeightBlur = () => {
  const converted = uiStore.convertToCurrentUnit(heightCm.value);
  heightDisplay.value = formatNumberWithDecimals(converted.value);
};

const handleRotationChange = () => {
  rotation.value = parseFormattedNumber(rotationDisplay.value);
  updateProperties();
};

const handleRotationBlur = () => {
  rotationDisplay.value = formatNumberWithDecimals(rotation.value);
};

// ────────────────────── Watch props ──────────────────────
watch(
  () => props.position,
  (newPos) => {
    if (newPos) {
      xPosition.value = newPos.x;
      yPosition.value = newPos.y;
      xPositionDisplay.value = formatNumberWithDecimals(newPos.x);
      yPositionDisplay.value = formatNumberWithDecimals(newPos.y);
    }
  },
  { deep: true, immediate: true }
);

watch(
  () => props.size,
  (newSize) => {
    if (newSize) {
      widthCm.value = newSize.width;
      heightCm.value = newSize.height;
      // display computed দিয়ে automatically আপডেট হবে
    }
  },
  { deep: true, immediate: true }
);

watch(
  () => props.rotation,
  (newRot) => {
    rotation.value = newRot;
    rotationDisplay.value = formatNumberWithDecimals(newRot);
  },
  { immediate: true }
);

// ────────────────────── Unit change → display refresh ──────────────────────
watch(
  () => uiStore.measurementUnit,
  () => {
    // unit পরিবর্তন হলে current cm value থেকে নতুন unit-এ display আপডেট করি
    const w = uiStore.convertToCurrentUnit(widthCm.value);
    const h = uiStore.convertToCurrentUnit(heightCm.value);
    widthDisplay.value = formatNumberWithDecimals(w.value);
    heightDisplay.value = formatNumberWithDecimals(h.value);
  },
  { immediate: true }
);
</script>
