<template>
  <div class="w-1/4 bg-slate-50 p-4 overflow-y-auto">
    <div class="flex mb-4">
      <button
        class="flex-1 p-2"
        :class="{ 'bg-blue-500 text-white': activeTab === 'design' }"
        @click="activeTab = 'design'"
      >
        Design
      </button>
      <button
        class="flex-1 p-2"
        :class="{ 'bg-blue-500 text-white': activeTab === 'properties' }"
        @click="activeTab = 'properties'"
      >
        Properties
      </button>
      <button
        class="flex-1 p-2"
        :class="{ 'bg-blue-500 text-white': activeTab === 'layers' }"
        @click="activeTab = 'layers'"
      >
        Layers
      </button>
    </div>

    <!-- Design Tab -->
    <div v-if="activeTab === 'design'" class="space-y-1">
      <div
        v-for="group in designGroups"
        :key="group.type"
        class="bg-white rounded"
      >
        <div
          @click="toggleGroup(group.type)"
          class="flex items-center p-2 cursor-pointer hover:bg-gray-50"
        >
          <NuxtIcon
            :name="group.icon"
            class="text-2xl mr-2 bg-blue-600"
            aria-hidden="true"
          />
          <span>{{ group.label }}</span>
          <NuxtIcon
            name="mdi:chevron-down"
            class="ml-auto text-2xl"
            :class="{ 'transform rotate-180': openGroups[group.type] }"
            aria-hidden="true"
          />
        </div>
        <div v-if="openGroups[group.type]" class="pl-6 space-y-2">
          <div
            v-for="item in group.items"
            :key="item.type"
            draggable="true"
            @dragstart="(e) => startSidebarDrag(e, item)"
            @dragend="(e) => emitDragEnd(e, item)"
            class="draggable-item flex items-center p-2 bg-white rounded cursor-move hover:bg-gray-50"
            data-type="h1"
          >
            <NuxtIcon :name="item.icon" class="w-5 h-5 mr-2" aria-hidden="true" />
            <span>{{ item.label }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Properties Tab -->
    <div v-if="activeTab === 'properties'" class="space-y-4">
      <div v-if="!selectedElement">
        <p class="text-gray-500">
          Select an element to view and edit its properties.
        </p>
      </div>
      <template v-else>
        <!--  <h3 class="font-bold">{{ selectedElementType }} Properties</h3> -->
        <div class="space-y-4">
          <!-- Geometry Section -->
          <div class="bg-white p-3">
            <div class="grid grid-cols-6 mb-5">
              <NuxtIcon
                @click="store.alignHorizontal('left')"
                name="solar:align-left-broken"
                class="text-2xl p-2 m-1 hover:text-blue-600 cursor-pointer"
                :class="{
                  'text-blue-700':
                    store.currentProperties.horizontalAlign === 'left',
                }"
                aria-hidden="true"
              />

              <NuxtIcon
                @click="store.alignHorizontal('center')"
                name="streamline-ultimate:align-center"
                class="text-2xl p-2 m-1 hover:text-blue-600 cursor-pointer"
                :class="{
                  'text-blue-700':
                    store.currentProperties.horizontalAlign === 'center',
                }"
                aria-hidden="true"
              />

              <NuxtIcon
                @click="store.alignHorizontal('right')"
                name="solar:align-right-broken"
                class="text-2xl p-2 m-1 hover:text-blue-600 cursor-pointer"
                :class="{
                  'text-blue-700':
                    store.currentProperties.horizontalAlign === 'right',
                }"
                aria-hidden="true"
              />

              <NuxtIcon
                @click="store.alignVertical('top')"
                name="mdi:format-align-top"
                class="text-2xl p-2 m-1 hover:text-blue-600 cursor-pointer"
                :class="{
                  'text-blue-700':
                    store.currentProperties.verticalAlign === 'top',
                }"
                aria-hidden="true"
              />

              <NuxtIcon
                @click="store.alignVertical('middle')"
                name="mdi:format-align-middle"
                class="text-2xl p-2 m-1 hover:text-blue-600 cursor-pointer"
                :class="{
                  'text-blue-700':
                    store.currentProperties.verticalAlign === 'middle',
                }"
                aria-hidden="true"
              />

              <NuxtIcon
                @click="store.alignVertical('bottom')"
                name="mdi:format-align-bottom"
                class="text-2xl p-2 m-1 hover:text-blue-600 cursor-pointer"
                :class="{
                  'text-blue-700':
                    store.currentProperties.verticalAlign === 'bottom',
                }"
                aria-hidden="true"
              />
            </div>
            <div class="flex items-center mb-2">
              <NuxtIcon
                name="tabler:geometry"
                class="text-2xl p-2 m-1 hover:text-blue-600 cursor-pointer mr-2 text-gray-600"
                aria-hidden="true"
              />
              <span class="font-semibold">Geometry</span>
            </div>
            <div class="grid grid-cols-2 gap-4 mt-1">
              <!-- Row 1: X and Y -->

              <div class="flex items-center border border-gray-300 rounded-md">
                <span class="px-3 py-2 bg-gray-300 text-gray-700">X</span>
                <input
                  type="number"
                  v-model.number="store.currentProperties.x"
                  placeholder="Username"
                  class="w-full p-2 focus:outline-none"
                  @input="store.updateProperties(store.currentProperties)"
                />
              </div>
              <div class="flex items-center border border-gray-300 rounded-md">
                <span class="px-3 py-2 bg-gray-300 text-gray-700">Y</span>
                <input
                  type="number"
                  v-model.number="store.currentProperties.y"
                  placeholder="Username"
                  class="w-full p-2 focus:outline-none"
                  @input="store.updateProperties(store.currentProperties)"
                />
              </div>

              <div class="flex items-center border border-gray-300 rounded-md">
                <span class="px-3 py-2 bg-gray-300 text-gray-700">W</span>
                <input
                  type="number"
                  v-model.number="store.currentProperties.size.width"
                  placeholder="Username"
                  class="w-full p-2 focus:outline-none"
                  @input="store.updateProperties(store.currentProperties)"
                />
              </div>
              <div class="flex items-center border border-gray-300 rounded-md">
                <span class="px-3 py-2 bg-gray-300 text-gray-700">H</span>
                <input
                  type="number"
                  v-model.number="store.currentProperties.size.height"
                  placeholder="Username"
                  class="w-full p-2 focus:outline-none"
                  @input="store.updateProperties(store.currentProperties)"
                />
              </div>

              <div class="flex items-center border border-gray-300 rounded-md">
                <span class="px-3 py-2 bg-gray-300 text-gray-700"
                  ><NuxtIcon name="ph:angle-bold" />
                </span>
                <input
                  type="number"
                  v-model.number="store.currentProperties.r"
                  placeholder="Username"
                  class="w-full p-2 focus:outline-none"
                  @input="store.updateProperties(store.currentProperties)"
                />
              </div>
            </div>
          </div>

          <!-- Text Specific Properties -->
          <template v-if="selectedElementType === 'h1'">
            <!-- Font Section -->
            <div class="bg-white p-2 rounded shadow">
              <div class="flex items-center mb-2">
                <NuxtIcon
                  name="mdi:format-font"
                  class="w-5 h-5 mr-2 text-gray-600"
                  aria-hidden="true"
                />
                <span class="font-semibold">Font</span>
              </div>
              <label class="block">
                Font:
                <select
                  v-model="store.currentProperties.font"
                  class="border p-1 w-full rounded"
                  @change="store.applyTextStyle(store.currentProperties)"
                >
                  <option>Roboto</option>
                  <option>Arial</option>
                  <option>Helvetica</option>
                </select>
              </label>
              <label class="block mt-2">
                Font Size:
                <select
                  v-model.number="store.currentProperties.fontSize"
                  class="border p-1 w-full rounded"
                  @change="store.applyTextStyle(store.currentProperties)"
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
                <NuxtIcon
                  name="mdi:palette"
                  class="w-5 h-5 mr-2 text-gray-600"
                  aria-hidden="true"
                />
                <span class="font-semibold">Fill</span>
              </div>
              <div class="flex items-center mb-2">
                <div class="fill-color">
                  <color-picker
                    v-model="store.currentProperties.fillColor"
                    v-slot="{ color, show }"
                    @change="console.log('New color:', $event)"
                    @close="console.log('ColorPicker is closed')"
                  >
                    {{ color }}
                    <button @click="show" class="bg-customBlue">OPEN</button>
                  </color-picker>
                </div>
                <div class="transparent ml-2" @click="store.makeTransparent">
                  <span>☐</span>
                  <span>Transparent</span>
                </div>
              </div>
              <!-- Color Picker Popup -->
              <div class="color-popup" v-if="showPopup">
                <div class="color-picker"></div>
              </div>
            </div>

            <!-- Text Alignment Section -->
            <div class="bg-white p-2 rounded shadow">
              <div class="flex items-center mb-2">
                <NuxtIcon
                  name="mdi:format-align-left"
                  class="w-5 h-5 mr-2 text-gray-600"
                  aria-hidden="true"
                />
                <span class="font-semibold">Text Alignment</span>
              </div>
              <div class="grid grid-cols-3 gap-2">
                <button
                  @click="store.applyTextAlign('left')"
                  :class="{
                    'bg-blue-500 text-white':
                      store.currentProperties.textAlign === 'left',
                  }"
                  class="p-2 bg-gray-200 rounded hover:bg-gray-300"
                >
                  <NuxtIcon
                    name="mdi:format-align-left"
                    class="w-5 h-5"
                    aria-hidden="true"
                  />
                </button>
                <button
                  @click="store.applyTextAlign('center')"
                  :class="{
                    'bg-blue-500 text-white':
                      store.currentProperties.textAlign === 'center',
                  }"
                  class="p-2 bg-gray-200 rounded hover:bg-gray-300"
                >
                  <NuxtIcon
                    name="mdi:format-align-center"
                    class="w-5 h-5"
                    aria-hidden="true"
                  />
                </button>
                <button
                  @click="store.applyTextAlign('right')"
                  :class="{
                    'bg-blue-500 text-white':
                      store.currentProperties.textAlign === 'right',
                  }"
                  class="p-2 bg-gray-200 rounded hover:bg-gray-300"
                >
                  <NuxtIcon
                    name="mdi:format-align-right"
                    class="w-5 h-5"
                    aria-hidden="true"
                  />
                </button>
              </div>
            </div>

            <!-- Text Area Alignment Section -->
            <div class="bg-white p-2 rounded shadow">
              <div class="flex items-center mb-2">
                <NuxtIcon
                  name="mdi:format-align-top"
                  class="w-5 h-5 mr-2 text-gray-600"
                  aria-hidden="true"
                />
                <span class="font-semibold">Text Area Alignment</span>
              </div>
              <div class="grid grid-cols-3 gap-2">
                <button
                  @click="store.applyVerticalAlign('top')"
                  :class="{
                    'bg-blue-500 text-white':
                      store.currentProperties.verticalAlign === 'top',
                  }"
                  class="p-2 bg-gray-200 rounded hover:bg-gray-300"
                >
                  <NuxtIcon
                    name="mdi:format-align-top"
                    class="w-5 h-5"
                    aria-hidden="true"
                  />
                </button>
                <button
                  @click="store.applyVerticalAlign('middle')"
                  :class="{
                    'bg-blue-500 text-white':
                      store.currentProperties.verticalAlign === 'middle',
                  }"
                  class="p-2 bg-gray-200 rounded hover:bg-gray-300"
                >
                  <NuxtIcon
                    name="mdi:format-align-middle"
                    class="w-5 h-5"
                    aria-hidden="true"
                  />
                </button>
                <button
                  @click="store.applyVerticalAlign('bottom')"
                  :class="{
                    'bg-blue-500 text-white':
                      store.currentProperties.verticalAlign === 'bottom',
                  }"
                  class="p-2 bg-gray-200 rounded hover:bg-gray-300"
                >
                  <NuxtIcon
                    name="mdi:format-align-bottom"
                    class="w-5 h-5"
                    aria-hidden="true"
                  />
                </button>
              </div>
            </div>

            <!-- Text Transform Section -->
            <div class="bg-white p-2 rounded shadow">
              <div class="flex items-center mb-2">
                <NuxtIcon
                  name="mdi:format-text"
                  class="w-5 h-5 mr-2 text-gray-600"
                  aria-hidden="true"
                />
                <span class="font-semibold">Text Transform</span>
              </div>
              <div class="grid grid-cols-3 gap-2">
                <button
                  @click="store.applyTextTransform('uppercase')"
                  :class="{
                    'bg-blue-500 text-white':
                      store.currentProperties.textTransform === 'uppercase',
                  }"
                  class="p-2 bg-gray-200 rounded hover:bg-gray-300"
                >
                  <NuxtIcon
                    name="mdi:format-letter-uppercase"
                    class="w-5 h-5"
                    aria-hidden="true"
                  />
                </button>
                <button
                  @click="store.applyTextTransform('lowercase')"
                  :class="{
                    'bg-blue-500 text-white':
                      store.currentProperties.textTransform === 'lowercase',
                  }"
                  class="p-2 bg-gray-200 rounded hover:bg-gray-300"
                >
                  <NuxtIcon
                    name="mdi:format-letter-lowercase"
                    class="w-5 h-5"
                    aria-hidden="true"
                  />
                </button>
                <button
                  @click="store.applyTextTransform('capitalize')"
                  :class="{
                    'bg-blue-500 text-white':
                      store.currentProperties.textTransform === 'capitalize',
                  }"
                  class="p-2 bg-gray-200 rounded hover:bg-gray-300"
                >
                  <NuxtIcon
                    name="mdi:format-letter-case"
                    class="w-5 h-5"
                    aria-hidden="true"
                  />
                </button>
              </div>
            </div>

            <!-- Text Style Section -->
            <!-- Text Style Section -->
            <div class="bg-white p-2 rounded shadow">
              <div class="flex items-center mb-2">
                <NuxtIcon
                  name="mdi:text"
                  class="w-5 h-5 mr-2 text-gray-600"
                  aria-hidden="true"
                />
                <span class="font-semibold">Text Style</span>
              </div>
              <div class="flex space-x-2 mb-2">
                <button
                  @click="store.toggleTextStyle('bold')"
                  :class="{
                    'bg-blue-500 text-white':
                      store.currentProperties.fontStyle === 'Bold',
                  }"
                  class="p-2 bg-gray-200 rounded hover:bg-gray-300"
                >
                  <NuxtIcon
                    name="mdi:format-bold"
                    class="w-5 h-5"
                    aria-hidden="true"
                  />
                </button>
                <button
                  @click="store.toggleTextStyle('italic')"
                  :class="{
                    'bg-blue-500 text-white':
                      store.currentProperties.fontStyle === 'Italic',
                  }"
                  class="p-2 bg-gray-200 rounded hover:bg-gray-300"
                >
                  <NuxtIcon
                    name="mdi:format-italic"
                    class="w-5 h-5"
                    aria-hidden="true"
                  />
                </button>
                <button
                  @click="store.toggleTextStyle('underline')"
                  :class="{
                    'bg-blue-500 text-white':
                      store.currentProperties.textDecoration === 'underline',
                  }"
                  class="p-2 bg-gray-200 rounded hover:bg-gray-300"
                >
                  <NuxtIcon
                    name="mdi:format-underline"
                    class="w-5 h-5"
                    aria-hidden="true"
                  />
                </button>
              </div>
              <label class="block">
                Text:
                <input
                  v-model="store.currentProperties.text"
                  class="border p-1 w-full rounded"
                  @input="store.applyTextStyle(store.currentProperties)"
                />
              </label>
              <div class="flex space-x-2 mt-2">
                <input
                  type="radio"
                  :value="'both sides'"
                  :checked="displayOption === 'both sides'"
                  @change="store.applyDisplayOption('both sides')"
                />
                Both sides
                <input
                  type="radio"
                  :value="'left side only'"
                  :checked="displayOption === 'left side only'"
                  @change="store.applyDisplayOption('left side only')"
                />
                Left side only
                <input
                  type="radio"
                  :value="'right side only'"
                  :checked="displayOption === 'right side only'"
                  @change="store.applyDisplayOption('right side only')"
                />
                Right side only
              </div>
            </div>
          </template>

          <!-- Image Specific Properties -->
          <template v-if="selectedElementType === 'img'">
            <!-- Associated Data Section -->
            <div class="bg-white p-2 rounded shadow">
              <div class="flex items-center mb-2">
                <NuxtIcon
                  name="mdi:database"
                  class="w-5 h-5 mr-2 text-gray-600"
                  aria-hidden="true"
                />
                <span class="font-semibold">Associated Data</span>
              </div>
              <label class="block">
                Data:
                <select
                  v-model="store.currentProperties.associatedData"
                  class="border p-1 w-full rounded"
                  @change="store.updateProperties(store.currentProperties)"
                >
                  <option>User ID</option>
                </select>
              </label>
            </div>

            <!-- Stroke Section -->
            <div class="bg-white p-2 rounded shadow">
              <div class="flex items-center mb-2">
                <NuxtIcon
                  name="mdi:brush"
                  class="w-5 h-5 mr-2 text-gray-600"
                  aria-hidden="true"
                />
                <span class="font-semibold">Stroke</span>
              </div>
              <div class="flex space-x-2">
                <label class="block flex-1">
                  Color:
                  <input
                    v-model="store.currentProperties.strokeColor"
                    type="color"
                    class="border p-1 w-full rounded mt-1"
                    @input="store.updateProperties(store.currentProperties)"
                  />
                </label>
                <label class="block flex-1">
                  Width:
                  <input
                    v-model.number="store.currentProperties.strokeWidth"
                    type="number"
                    class="border p-1 w-full rounded mt-1"
                    @input="store.updateProperties(store.currentProperties)"
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
                <NuxtIcon
                  name="mdi:qrcode"
                  class="w-5 h-5 mr-2 text-gray-600"
                  aria-hidden="true"
                />
                <span class="font-semibold">Content</span>
              </div>
              <label class="block">
                Content:
                <input
                  v-model="store.currentProperties.content"
                  class="border p-1 w-full rounded"
                  @input="store.updateProperties(store.currentProperties)"
                />
              </label>
            </div>
          </template>
        </div>
      </template>
    </div>

    <!-- Layers Tab -->
    <div v-if="activeTab === 'layers'" class="space-y-2">
      <ul>
        <li
          v-for="(layer, index) in layers"
          :key="index"
          @click="$emit('select-layer', index)"
          class="p-2 rounded cursor-pointer hover:bg-gray-200"
          :class="{ 'bg-blue-200': selectedLayer === index }"
        >
          <div class="flex items-center justify-between">
            <span>{{ layer.name }} ({{ layer.type }})</span>
            <button
              @click.stop="store.toggleLayerVisibility(index)"
              class="text-blue-500"
            >
              <NuxtIcon
                :name="layer.visible ? 'mdi:eye' : 'mdi:eye-off'"
                class="w-5 h-5"
                aria-hidden="true"
              />
            </button>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { useCanvasStore } from "@badge/stores/useCanvasStore";

defineProps({
  selectedElement: [String, Number, null],
  selectedElementType: String,
  layers: Array,
  selectedLayer: [Number, null],
  currentProperties: Object,
  displayOption: String,
});

const emit = defineEmits([
  "drag-start",
  "drag-end",
  "align-horizontal",
  "align-vertical",
  "update-properties",
  "apply-text-style",
  "make-transparent",
  "apply-color",
  "apply-text-align",
  "apply-vertical-align",
  "apply-text-transform",
  "toggle-text-style",
  "apply-display-option",
  "select-layer",
  "toggle-layer-visibility",
]);

const store = useCanvasStore();

function startSidebarDrag(event, item) {
  emit("drag-start", item);
}

function emitDragEnd(event, item) {
  // console.log(`Drag ended for ${item}`);

  const x = event.clientX;
  const y = event.clientY;

  emit("drag-end", {
    item: item,
    y,
    x,
  });
}

const activeTab = ref("design");
const openGroups = ref({
  user_info: false,
  event_info: false,
  qr_code: false,
  background: false,
  static_field: false,
  punching_area: false,
});
const showPopup = ref(false);
const selectedColor = ref("#000000");
const hue = ref(0);
const isPicking = ref(false);

const designGroups = [
  {
    type: "user_info",
    label: "User Info",
    icon: "mdi:account",
    items: [
      {
        type: "h1",
        label: "LAST NAME",
        icon: "streamline-flex-color:copy-2-flat",
      },
      {
        type: "h1",
        label: "FIRST NAME",
        icon: "streamline-flex-color:copy-2-flat",
      },
      {
        type: "h1",
        label: "Full Name",
        icon: "streamline-flex-color:copy-2-flat",
      },
      {
        type: "p",
        label: "EMAIL",
        icon: "streamline-flex-color:copy-2-flat",
      },
      {
        type: "p",
        label: "COMPANY NAME",
        icon: "streamline-flex-color:copy-2-flat",
      },
      {
        type: "p",
        label: "DESIGNATION",
        icon: "streamline-flex-color:copy-2-flat",
      },
      {
        type: "p",
        label: "ROLE",
        icon: "streamline-flex-color:copy-2-flat",
      },
      {
        type: "p",
        label: "USER ID",
        icon: "streamline-flex-color:copy-2-flat",
      },
      {
        type: "eventlogo",
        label: "AVATAR",
        icon: "streamline-flex-color:copy-2-flat",
      },
    ],
  },
  {
    type: "event_info",
    label: "Event Info",
    icon: "mdi:calendar",
    items: [
      {
        type: "h1",
        label: "Event Name",
        icon: "streamline-flex-color:copy-2-flat",
      },
      { type: "p", label: "Venue", icon: "streamline-flex-color:copy-2-flat" },
      { type: "p", label: "Date", icon: "streamline-flex-color:copy-2-flat" },
      {
        type: "p",
        label: "ZIP Code",
        icon: "streamline-flex-color:copy-2-flat",
      },
      { type: "p", label: "City", icon: "streamline-flex-color:copy-2-flat" },
      {
        type: "img",
        label: "Event Logo",
        icon: "streamline-flex-color:copy-2-flat",
      },
    ],
  },
  {
    type: "qr_code",
    label: "QR Code",
    icon: "mdi:qrcode",
    items: [{ type: "p", label: "QR Code", icon: "mdi:qrcode" }],
  },
  {
    type: "background",
    label: "Background",
    icon: "mdi:image",
    items: [
      { type: "img", label: "Image", icon: "mdi:image" },
      { type: "background", label: "Gradient", icon: "mdi:gradient" },
      { type: "background", label: "Color", icon: "mdi:palette" },
      { type: "background", label: "None", icon: "mdi:close" },
    ],
  },
  {
    type: "static_field",
    label: "Static Fields",
    icon: "mdi:shape",
    items: [
      { type: "p", label: "Text", icon: "mdi:text" },
      { type: "img", label: "Image", icon: "mdi:image" },
      { type: "line", label: "Rectangle", icon: "mdi:rectangle" },
    ],
  },
  {
    type: "punching_area",
    label: "Punching Area Reference",
    icon: "mdi:gesture-tap",
    items: [
      { type: "punching_area", label: "No Punching", icon: "mdi:close" },
      { type: "punching_area", label: "Top", icon: "mdi:arrow-up" },
      { type: "punching_area", label: "Bottom", icon: "mdi:arrow-down" },
    ],
  },
];

const toggleGroup = (groupType) => {
  openGroups.value[groupType] = !openGroups.value[groupType];
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

button {
  transition: background-color 0.2s;
}

button:hover {
  background-color: #e0e0e0;
}

button:active {
  background-color: #c0c0e0;
}

Icon {
  display: inline-block;
  vertical-align: middle;
}
</style>
