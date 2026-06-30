<!-- components/DrawingProperties.vue - UPDATED -->
<template>
  <div class="drawing-properties space-y-5">
    <h4 class="text-sm font-semibold text-gray-800">Drawing Properties</h4>

    <!-- Dash Style -->
    <div class="flex items-center gap-3">
      <div class="w-1/2">
        <label class="block text-xs font-medium text-gray-700 mb-1">Dash</label>
        <select
          v-model="localDashStyle"
          @change="emitUpdate"
          class="w-full px-3 py-1 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-400"
        >
          <option value="Solid">Solid</option>
          <option value="Dashed">Dashed</option>
          <option value="Dotted">Dotted</option>
          <option value="Dot Dash">Dot Dash</option>
          <option value="Long Dash">Long Dash</option>
          <option value="Double Dash">Double Dash</option>
        </select>
      </div>

      <!-- Line Cap -->
      <div class="w-1/2">
        <label class="block text-xs font-medium text-gray-700 mb-1"
          >Line Cap</label
        >
        <select
          v-model="localLineCap"
          @change="emitUpdate"
          class="w-full px-3 py-1 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-400"
        >
          <option value="Butt">Butt</option>
          <option value="Round">Round</option>
          <option value="Square">Square</option>
        </select>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <!-- Line Join -->
      <div class="w-1/2">
        <label class="block text-xs font-medium text-gray-700 mb-1"
          >Line Join</label
        >
        <select
          v-model="localLineJoin"
          @change="emitUpdate"
          class="w-full px-3 py-1 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-400"
        >
          <option value="Miter">Miter</option>
          <option value="Round">Round</option>
          <option value="Bevel">Bevel</option>
        </select>
      </div>

      <!-- Corner Radius (NEW) -->
      <div v-if="showCornerRadius" class="w-1/2">
        <label class="block text-xs font-medium text-gray-700 mb-1"
          >Corner Radius</label
        >
        <input
          v-model.number="localCornerRadius"
          type="number"
          min="0"
          @input="emitUpdate"
          class="w-full px-3 py-1 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-400"
        />
      </div>
    </div>

    <!-- Shadow Properties -->
    <div class="pt-4 border-t border-gray-200">
      <h5 class="text-sm font-semibold text-gray-800 mb-3">Shadow</h5>

      <!-- Flex container: two items per row -->
      <div class="flex flex-wrap gap-3">
        <!-- Offset X -->
        <div class="flex-1 min-w-[45%]">
          <label class="block text-xs font-medium text-gray-600 mb-1"
            >Offset X</label
          >
          <input
            v-model.number="localShadowOffsetX"
            type="number"
            @input="emitUpdate"
            class="w-full px-3 py-1 text-sm border border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none transition"
          />
        </div>

        <!-- Offset Y -->
        <div class="flex-1 min-w-[45%]">
          <label class="block text-xs font-medium text-gray-600 mb-1"
            >Offset Y</label
          >
          <input
            v-model.number="localShadowOffsetY"
            type="number"
            @input="emitUpdate"
            class="w-full px-3 py-1 text-sm border border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none transition"
          />
        </div>

        <!-- Color + Blur (inline but grouped) -->
        <div class="flex min-w-full items-center gap-2">
          <!-- Color group (Color + Text grouped inline) -->
          <div class="flex flex-col">
            <label class="block text-xs font-medium text-gray-600 mb-1"
              >Color</label
            >
            <div
              class="flex items-center border border-gray-300 rounded-md overflow-hidden shadow-sm bg-white"
            >
              <!-- Color Picker -->
              <div
                class="flex items-center justify-center bg-gray-50 px-2 border-r border-gray-300"
              >
                <input
                  v-model="localShadowColor"
                  type="color"
                  @input="emitUpdate"
                  class="cursor-pointer w-8 h-8 border-none outline-none rounded-sm"
                  title="Pick shadow color"
                />
              </div>

              <!-- Text Input -->
              <input
                v-model="localShadowColor"
                type="text"
                @input="emitUpdate"
                placeholder="#000000"
                class="w-20 px-3 py-1 text-sm font-mono text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-400"
              />
            </div>
          </div>

          <!-- Blur -->
          <div class="flex flex-col">
            <label class="block text-xs font-medium text-gray-600 mb-1"
              >Blur</label
            >
            <div
              class="flex items-center gap-1 border border-gray-300 rounded-md px-2 py-1 shadow-sm bg-white"
            >
              <input
                v-model.number="localShadowBlur"
                type="number"
                min="0"
                @input="emitUpdate"
                class="w-20 focus:outline-none font-mono text-gray-800"
              />
              <span class="text-xs text-gray-500">px</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, computed } from "vue";

// Props
interface Props {
  dashStyle?: string;
  lineCap?: string;
  lineJoin?: string;
  cornerRadius?: number;
  shadowOffsetX?: number;
  shadowOffsetY?: number;
  shadowColor?: string;
  shadowBlur?: number;
  elementType?: string;
}

const props = withDefaults(defineProps<Props>(), {
  dashStyle: "Dot Dash",
  lineCap: "Square",
  lineJoin: "Bevel",
  cornerRadius: 0,
  shadowOffsetX: 0,
  shadowOffsetY: 0,
  shadowColor: "#000000",
  shadowBlur: 0,
  elementType: "",
});

// Emits
const emit = defineEmits<{
  update: [updates: Partial<Props>];
}>();

// Computed property to show corner radius for shape elements
const showCornerRadius = computed(() => {
  const shapeTypes = ["rectangle", "ellipse", "shape", "frame", "section"];
  return shapeTypes.includes(props.elementType?.toLowerCase() || "");
});

// Local reactive copies
const localDashStyle = ref(props.dashStyle);
const localLineCap = ref(props.lineCap);
const localLineJoin = ref(props.lineJoin);
const localCornerRadius = ref(props.cornerRadius);
const localShadowOffsetX = ref(props.shadowOffsetX);
const localShadowOffsetY = ref(props.shadowOffsetY);
const localShadowColor = ref(props.shadowColor);
const localShadowBlur = ref(props.shadowBlur);

// Emit all changes
const emitUpdate = () => {
  const updates: Partial<Props> = {
    dashStyle: localDashStyle.value,
    lineCap: localLineCap.value,
    lineJoin: localLineJoin.value,
    shadowOffsetX: Number(localShadowOffsetX.value),
    shadowOffsetY: Number(localShadowOffsetY.value),
    shadowColor: localShadowColor.value,
    shadowBlur: Number(localShadowBlur.value),
  };

  // Only include corner radius for supported elements
  if (showCornerRadius.value) {
    updates.cornerRadius = Number(localCornerRadius.value);
  }

  emit("update", updates);
};

// Sync when props change (from parent)
watch(
  () => props,
  (newProps) => {
    localDashStyle.value = newProps.dashStyle ?? "Dot Dash";
    localLineCap.value = newProps.lineCap ?? "Square";
    localLineJoin.value = newProps.lineJoin ?? "Bevel";
    localCornerRadius.value = newProps.cornerRadius ?? 0;
    localShadowOffsetX.value = newProps.shadowOffsetX ?? 0;
    localShadowOffsetY.value = newProps.shadowOffsetY ?? 0;
    localShadowColor.value = newProps.shadowColor ?? "#000000";
    localShadowBlur.value = newProps.shadowBlur ?? 0;
  },
  { deep: true }
);
</script>
