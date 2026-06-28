<template>
  <div
    v-if="show"
    class="fixed inset-0 bg-black/60 flex items-center justify-center z-50"
  >
    <div
      class="bg-white rounded-xl p-8 w-[600px] max-h-[85vh] overflow-y-auto shadow-2xl"
    >
      <h2 class="text-2xl font-bold text-gray-800 mb-6">Select Gradient</h2>

      <!-- Predefined Gradients -->
      <h3 class="text-lg font-semibold text-gray-700 mb-3">
        Predefined Gradients
      </h3>
      <div class="grid grid-cols-3 gap-4 mb-8">
        <div
          v-for="gradient in predefinedGradients"
          :key="gradient.name"
          class="relative h-20 rounded-lg cursor-pointer hover:scale-105 transition-transform duration-200"
          :class="[
            gradient.name === 'Blue Wave Top' &&
              'bg-gradient-to-tl from-blue-900 to-white',
            gradient.name === 'Blue Wave Bottom' &&
              'bg-gradient-to-tr from-blue-900 to-white',
            gradient.name === 'Top Wave - Purple' &&
              'bg-gradient-to-tl from-purple-800 to-yellow-100',
            gradient.name === 'Bottom Wave - Blue' &&
              'bg-gradient-to-tr from-blue-700 to-blue-100',
            gradient.name === 'Right Wave - Green' &&
              'bg-gradient-to-r from-green-700 to-green-100',
            gradient.name === 'Left Wave - Orange' &&
              'bg-gradient-to-l from-orange-700 to-orange-100',
            gradient.name === 'Top-Left Wave - Teal' &&
              'bg-gradient-to-tl from-teal-700 to-teal-100',
            gradient.name === 'Top-Right Wave - Pink' &&
              'bg-gradient-to-tr from-pink-700 to-pink-100',
            gradient.name === 'Bottom-Left Wave - Yellow' &&
              'bg-gradient-to-bl from-yellow-700 to-yellow-100',
            gradient.name === 'Bottom-Right Wave - Indigo' &&
              'bg-gradient-to-br from-indigo-700 to-indigo-100',
            gradient.name === 'Top Wave - Coral' &&
              'bg-gradient-to-tl from-red-700 to-red-100',
            gradient.name === 'Bottom Wave - Mint' &&
              'bg-gradient-to-tr from-green-600 to-green-100',
            gradient.name === 'Sunset' &&
              'bg-gradient-to-r from-orange-500 to-orange-200',
            gradient.name === 'Ocean' &&
              'bg-gradient-to-r from-cyan-500 to-cyan-200',
            gradient.name === 'Forest' &&
              'bg-gradient-to-r from-teal-800 to-teal-400',
            gradient.name === 'Twilight' &&
              'bg-gradient-to-r from-gray-800 to-purple-600',
            gradient.name === 'Aurora' &&
              'bg-gradient-to-r from-blue-500 to-cyan-400',
            gradient.name === 'Blaze' &&
              'bg-gradient-to-r from-red-600 to-red-200',
            gradient.name === 'Dusk' &&
              'bg-gradient-to-r from-blue-800 to-indigo-700',
            gradient.name === 'Meadow' &&
              'bg-gradient-to-r from-lime-600 to-lime-300',
            gradient.name === 'Coral' &&
              'bg-gradient-to-r from-pink-500 to-pink-200',
            gradient.name === 'Nightfall' &&
              'bg-gradient-to-r from-blue-800 to-blue-500',
            gradient.name === 'Citrus' &&
              'bg-gradient-to-r from-yellow-500 to-yellow-200',
            gradient.name === 'Berry' &&
              'bg-gradient-to-r from-red-600 to-yellow-100',
            gradient.name === 'Tropical' &&
              'bg-gradient-to-r from-cyan-500 to-red-500',
            gradient.name === 'Horizon' &&
              'bg-gradient-to-r from-purple-800 to-indigo-500',
            gradient.name === 'Mint' &&
              'bg-gradient-to-r from-green-300 to-blue-400',
            gradient.name === 'Peach' &&
              'bg-gradient-to-r from-orange-500 to-red-500',
            gradient.name === 'Lavender' &&
              'bg-gradient-to-r from-indigo-300 to-pink-300',
            gradient.name === 'Serenity' &&
              'bg-gradient-to-r from-gray-800 to-blue-400',
            gradient.name === 'Emerald' &&
              'bg-gradient-to-r from-teal-600 to-lime-400',
            gradient.name === 'Candy' &&
              'bg-gradient-to-r from-red-300 to-orange-200',
            gradient.name === 'Vintage' &&
              'bg-gradient-to-r from-gray-400 to-gray-800',
            gradient.name === 'Skyline' &&
              'bg-gradient-to-r from-blue-500 to-indigo-600',
            gradient.name === 'Rose' &&
              'bg-gradient-to-r from-pink-300 to-orange-200',
            gradient.name === 'Galaxy' &&
              'bg-gradient-to-r from-indigo-800 to-pink-200',
          ]"
          @click="selectGradient(gradient.style)"
        >
          <span
            class="absolute bottom-1 left-2 text-xs text-white font-medium drop-shadow"
          >
            {{ gradient.name }}
          </span>
        </div>
      </div>

      <!-- Custom Gradient -->
      <h3 class="text-lg font-semibold text-gray-700 mb-3">Custom Gradient</h3>
      <div class="space-y-6 bg-gray-50 p-4 rounded-lg">
        <!-- Color Pickers -->
        <div class="flex space-x-6">
          <div class="flex-1">
            <label class="text-sm font-medium text-gray-600">Start Color</label>
            <input
              type="color"
              v-model="startColor"
              class="w-full h-12 rounded-md border border-gray-300 cursor-pointer mt-1"
            />
          </div>
          <div class="flex-1">
            <label class="text-sm font-medium text-gray-600">End Color</label>
            <input
              type="color"
              v-model="endColor"
              class="w-full h-12 rounded-md border border-gray-300 cursor-pointer mt-1"
            />
          </div>
        </div>

        <!-- Direction Selection -->
        <div>
          <label class="text-sm font-medium text-gray-600">Direction</label>
          <div class="grid grid-cols-4 gap-3 mt-2">
            <button
              v-for="dir in directions"
              :key="dir.value"
              class="relative p-3 rounded-lg border border-gray-300 hover:bg-blue-50 transition-colors duration-200"
              :class="{
                'bg-blue-100 border-blue-500': direction === dir.value,
              }"
              @click="direction = dir.value"
            >
              <NuxtIcon :name="dir.icon" class="w-6 h-6 mx-auto text-gray-600" />
              <span
                class="absolute -top-2 -right-2 bg-blue-500 text-white text-xs rounded-full px-2 py-1 opacity-0 hover:opacity-100 transition-opacity duration-200"
              >
                {{ dir.label }}
              </span>
            </button>
          </div>
        </div>

        <!-- Preview -->
        <div>
          <label class="text-sm font-medium text-gray-600">Preview</label>
          <div
            class="h-24 rounded-lg mt-2"
            :class="`bg-gradient-to-${direction.value} from-${startColor.slice(
              1
            )} to-${endColor.slice(1)}`"
          ></div>
        </div>
      </div>

      <!-- Actions -->
      <div class="mt-8 flex justify-end space-x-3">
        <button
          class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200"
          @click="close"
        >
          Cancel
        </button>
        <button
          class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200"
          @click="applyCustomGradient"
        >
          Apply Custom
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";

defineProps({
  show: Boolean,
});

const emit = defineEmits(["selected", "close"]);

const predefinedGradients = [
  {
    name: "Blue Wave Top",
    style: "linear-gradient(340deg, #1E3A8A, #60A5FA, #E0F2FE, #FFFFFF)",
  },
  {
    name: "Blue Wave Bottom",
    style: "linear-gradient(200deg, #1E3A8A, #60A5FA, #E0F2FE, #FFFFFF)",
  },
  {
    name: "Top Wave - Purple",
    style: "linear-gradient(340deg, #6B46C1, #ED64A6, #FFFFFF, #F7FAAC)",
  },
  {
    name: "Bottom Wave - Blue",
    style: "linear-gradient(200deg, #2B6CB0, #90CDF4, #FFFFFF, #BEE3F8)",
  },
  {
    name: "Right Wave - Green",
    style: "linear-gradient(110deg, #38A169, #9AE6B4, #FFFFFF, #C6F6D5)",
  },
  {
    name: "Left Wave - Orange",
    style: "linear-gradient(70deg, #DD6B20, #F6AD55, #FFFFFF, #FBD38D)",
  },
  {
    name: "Top-Left Wave - Teal",
    style: "linear-gradient(315deg, #2C7A7B, #81E6D9, #FFFFFF, #E6FFFA)",
  },
  {
    name: "Top-Right Wave - Pink",
    style: "linear-gradient(45deg, #D53F8C, #F687B3, #FFFFFF, #FED7E2)",
  },
  {
    name: "Bottom-Left Wave - Yellow",
    style: "linear-gradient(225deg, #D69E2E, #F6E05E, #FFFFFF, #FAF089)",
  },
  {
    name: "Bottom-Right Wave - Indigo",
    style: "linear-gradient(135deg, #4C51BF, #A3BFFA, #FFFFFF, #E6E8FF)",
  },
  {
    name: "Top Wave - Coral",
    style: "linear-gradient(340deg, #E53E3E, #FC8181, #FFFFFF, #FED7D7)",
  },
  {
    name: "Bottom Wave - Mint",
    style: "linear-gradient(200deg, #48BB78, #9AE6B4, #FFFFFF, #C6F6D5)",
  },
  { name: "Sunset", style: "linear-gradient(to right, #ff7e5f, #feb47b)" },
  { name: "Ocean", style: "linear-gradient(to right, #00c4cc, #7bd3f7)" },
  { name: "Forest", style: "linear-gradient(to right, #134e4a, #4cceac)" },
  { name: "Twilight", style: "linear-gradient(to right, #2c3e50, #8e44ad)" },
  { name: "Aurora", style: "linear-gradient(to right, #4facfe, #00f2fe)" },
  { name: "Blaze", style: "linear-gradient(to right, #ff0844, #ffb199)" },
  { name: "Dusk", style: "linear-gradient(to right, #2b5876, #4e4376)" },
  { name: "Meadow", style: "linear-gradient(to right, #a8e063, #56ab2f)" },
  { name: "Coral", style: "linear-gradient(to right, #ff6a88, #ff99ac)" },
  { name: "Nightfall", style: "linear-gradient(to right, #1e3c72, #2a5298)" },
  { name: "Citrus", style: "linear-gradient(to right, #f7971e, #ffd200)" },
  { name: "Berry", style: "linear-gradient(to right, #ed4264, #ffedbc)" },
  { name: "Tropical", style: "linear-gradient(to right, #00ddeb, #ff6b6b)" },
  { name: "Horizon", style: "linear-gradient(to right, #614385, #516395)" },
  { name: "Mint", style: "linear-gradient(to right, #a2f2b1, #64b3f4)" },
  { name: "Peach", style: "linear-gradient(to right, #ff9966, #ff5e62)" },
  { name: "Lavender", style: "linear-gradient(to right, #b2b2ff, #ffccff)" },
  { name: "Serenity", style: "linear-gradient(to right, #2c3e50, #3498db)" },
  { name: "Emerald", style: "linear-gradient(to right, #00b09b, #96c93d)" },
  { name: "Candy", style: "linear-gradient(to right, #ff9a9e, #fad0c4)" },
  { name: "Vintage", style: "linear-gradient(to right, #d7d2cc, #304352)" },
  { name: "Skyline", style: "linear-gradient(to right, #1488cc, #2b32b2)" },
  { name: "Rose", style: "linear-gradient(to right, #ffafbd, #ffc3a0)" },
  { name: "Galaxy", style: "linear-gradient(to right, #1d2b64, #f8cdda)" },
];

const directions = [
  { label: "To Right", value: "to right", icon: "mdi:arrow-right" },
  { label: "To Bottom", value: "to bottom", icon: "mdi:arrow-down" },
  { label: "To Left", value: "to left", icon: "mdi:arrow-left" },
  { label: "To Top", value: "to top", icon: "mdi:arrow-up" },
  { label: "Top-Left", value: "to top left", icon: "mdi:arrow-top-left" },
  { label: "Top-Right", value: "to top right", icon: "mdi:arrow-top-right" },
  {
    label: "Bottom-Left",
    value: "to bottom left",
    icon: "mdi:arrow-bottom-left",
  },
  {
    label: "Bottom-Right",
    value: "to bottom right",
    icon: "mdi:arrow-bottom-right",
  },
];

const startColor = ref("#ff0000");
const endColor = ref("#0000ff");
const direction = ref("to right");

const customGradientStyle = computed(() => {
  return `linear-gradient(${direction.value}, ${startColor.value}, ${endColor.value})`;
});

function selectGradient(style) {
  emit("selected", style);
  emit("close");
}

function applyCustomGradient() {
  emit("selected", customGradientStyle.value);
  emit("close");
}

function close() {
  emit("close");
}
</script>

<style scoped>
/* Smooth transitions for buttons */
button {
  transition: all 0.2s ease-in-out;
}

/* Hover effect for direction buttons */
button:hover {
  transform: scale(1.05);
}

/* Ensure buttons are visually distinct when selected */
button.bg-blue-100 {
  background-color: #dbeafe;
  border-color: #3b82f6;
}

/* Tooltip styling */
button span {
  font-size: 0.75rem;
  pointer-events: none;
}

/* Custom gradient section background */
.bg-gray-50 {
  background-color: #f9fafb;
}

/* Ensure modal has a modern look */
.rounded-xl {
  border-radius: 1rem;
}

/* Shadow for depth */
.shadow-2xl {
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
    0 4px 6px -2px rgba(0, 0, 0, 0.05);
}
</style>
