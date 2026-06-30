<template>
  <div v-if="sidebarInfo?.data?.designGroups?.length">
    <div class="flex mb-4">
      <button
        class="flex-1 p-2"
        :class="{ 'bg-blue-500 text-white': store.activeTab === 'design' }"
        @click="store.activeTab = 'design'"
      >
        Design
      </button>
      <button
        class="flex-1 p-2"
        :class="{ 'bg-blue-500 text-white': store.activeTab === 'properties' }"
        @click="store.activeTab = 'properties'"
      >
        Properties
      </button>
      <button
        class="flex-1 p-2"
        :class="{ 'bg-blue-500 text-white': store.activeTab === 'layers' }"
        @click="store.activeTab = 'layers'"
      >
        Layers
      </button>
    </div>

    <!-- Design Tab Content -->
    <div
      v-if="store.activeTab === 'design'"
      class="space-y-1"
    >
      <div class="py-3 px-2 flex">
        <NuxtIcon name="quill:paper" class="text-2xl text-blue-600" />
        <span
          >{{ pageStore.badgeSize }}({{ pageStore.presetWidth.toFixed(2) }}mmX{{
            pageStore.presetHeight.toFixed(2)
          }}mm) </span
        ><a
          href="#"
          class="text-blue-600 font-semibold"
          @click="pageStore.toggleModal"
        >
          &nbsp;Change</a
        >
      </div>
      <div
        v-for="group in sidebarInfo.data.designGroups"
        :key="group.type"
        class="bg-white border-b border-gray-300"
      >
        <!-- Group Header -->
        <div
          @click="toggleGroup(group.type)"
          class="flex items-center p-2 cursor-pointer hover:bg-gray-50"
        >
          <NuxtIcon
            :name="group.icon || 'mdi:folder-outline'"
            class="text-2xl mr-2 bg-blue-600 shrink-0"
            aria-hidden="true"
          />
          <span class="leading-none">{{ group.label }}</span>
          <NuxtIcon
            name="mdi:chevron-down"
            class="ml-auto text-2xl"
            :class="{
              'transform rotate-180': sidebarInfo.data.openGroups[group.type],
            }"
          />
        </div>

        <!-- Group Items -->
        <div
          v-if="sidebarInfo.data.openGroups[group.type]"
          class="pl-6 space-y-2 py-2"
        >
          <!-- No Punching Option (Hardcoded to ensure it always appears) -->
          <div
            v-if="group.type === 'punching_area'"
            class="flex items-center p-2 bg-white rounded hover:bg-slate-100 max-w-sm cursor-pointer"
            @click="setPunchingArea('none')"
          >
            <button
              class="w-full border rounded-md py-1 px-5 border-gray-300 flex items-center justify-center font-bold text-gray-600 hover:bg-gray-50"
              :class="selectedPunchArea('none')"
            >
              No Punching
            </button>
          </div>
          <div
            v-for="item in group.items"
            :key="item.type"
            :draggable="
              item.type !== 'img' &&
              item.type !== 'background' &&
              item.type !== 'gradient' &&
              item.type !== 'color' &&
              item.type !== 'none' &&
              item.type !== 'circle-center' &&
              item.type !== 'circle-left-right' &&
              item.type !== 'long-center' &&
              item.type !== 'long-left-right'
                ? true
                : false
            "
            @dragstart="(e) => startSidebarDrag(e, item)"
            @dragend="(e) => emitDragEnd(e, item)"
            class="draggable-item flex items-center p-2 bg-white rounded hover:bg-slate-100 max-w-sm"
            :class="{
              'cursor-move':
                item.type !== 'img' &&
                item.type !== 'background' &&
                item.type !== 'gradient' &&
                item.type !== 'color' &&
                item.type !== 'none' &&
                item.type !== 'circle-center' &&
                item.type !== 'circle-left-right' &&
                item.type !== 'long-center' &&
                item.type !== 'long-left-right',
              'cursor-pointer':
                item.type === 'img' ||
                item.type === 'background' ||
                item.type === 'gradient' ||
                item.type === 'color' ||
                item.type === 'none' ||
                item.type === 'circle-center' ||
                item.type === 'circle-left-right' ||
                item.type === 'long-center' ||
                item.type === 'long-left-right',
            }"
            :data-type="item.type"
          >
            <!-- QR Code -->
            <div
              v-if="item.type === 'qrcode'"
              class="flex items-center flex-row"
            >
              <NuxtIcon
                :name="item.icon || 'mdi:shape-outline'"
                class="text-xl mr-2 shrink-0"
                aria-hidden="true"
              />
              <span class="leading-none">{{ item.label }}</span>
            </div>

            <!-- Image -->
            <div
              class="flex items-center"
              v-else-if="item.type === 'img'"
              @click="openImageUploadModal(item)"
            >
              <NuxtIcon
                :name="item.icon || 'mdi:shape-outline'"
                class="text-xl mr-2 shrink-0"
                aria-hidden="true"
              />
              <span class="leading-none">{{ item.label }}</span>
            </div>

            <!-- Background -->
            <div
              class="flex items-center"
              v-else-if="item.type === 'background'"
              @click="openImageUploadModal(item)"
            >
              <NuxtIcon
                :name="item.icon || 'mdi:shape-outline'"
                class="text-xl mr-2 shrink-0"
                aria-hidden="true"
              />
              <span class="leading-none">{{ item.label }}</span>
            </div>

            <!-- Gradient -->
            <div
              class="flex items-center"
              v-else-if="item.type === 'gradient'"
              @click="openGradientModal"
            >
              <NuxtIcon
                :name="item.icon || 'mdi:shape-outline'"
                class="text-xl mr-2 shrink-0"
                aria-hidden="true"
              />
              <span class="leading-none">{{ item.label }}</span>
            </div>

            <!-- Color -->
            <div
              class="flex items-center"
              v-else-if="item.type === 'color'"
              @click="openColorModal"
            >
              <NuxtIcon
                :name="item.icon || 'mdi:shape-outline'"
                class="text-xl mr-2 shrink-0"
                aria-hidden="true"
              />
              <span class="leading-none">{{ item.label }}</span>
            </div>


            <!-- Background None -->
            <div
              class="flex items-center"
              v-else-if="item.type === 'none'"
              @click="removeBackground"
            >
              <NuxtIcon
                :name="item.icon || 'mdi:shape-outline'"
                class="text-xl mr-2 shrink-0"
                aria-hidden="true"
              />
              <span class="leading-none">{{ item.label }}</span>
            </div>

            <!-- Circle Center -->
            <button
              @click="setPunchingArea('circle-center')"
              class="w-full border rounded-md py-1 px-5 border-gray-300 flex items-center justify-center"
              :class="selectedPunchArea(item.type)"
              v-else-if="item.type === 'circle-center'"
            >
              <div
                class="w-5 h-5 bg-transparent border border-gray-300 rounded-xl z-10"
              ></div>
            </button>
            <!-- Circle Left Right -->
            <button
              @click="setPunchingArea('circle-left-right')"
              class="w-full border rounded-md py-1 px-5 border-gray-300 flex items-center justify-between"
              :class="selectedPunchArea(item.type)"
              v-else-if="item.type === 'circle-left-right'"
            >
              <div
                class="w-5 h-5 bg-transparent border border-gray-300 rounded-xl z-10"
              ></div>
              <div
                class="w-5 h-5 bg-transparent border border-gray-300 rounded-xl z-10"
              ></div>
            </button>
            <!-- Long Center -->
            <button
              @click="setPunchingArea('long-center')"
              class="w-full border rounded-md py-1 px-5 border-gray-300 flex items-center justify-center"
              :class="selectedPunchArea(item.type)"
              v-else-if="item.type === 'long-center'"
            >
              <div
                class="w-16 h-4 bg-transparent border border-gray-300 rounded-xl z-10"
              ></div>
            </button>
            <!-- Long Left Right -->
            <button
              @click="setPunchingArea('long-left-right')"
              class="w-full border rounded-md py-1 px-5 border-gray-300 flex items-center justify-between"
              :class="selectedPunchArea(item.type)"
              v-else-if="item.type === 'long-left-right'"
            >
              <div
                class="w-16 h-4 bg-transparent border border-gray-300 rounded-xl z-10"
              ></div>
              <div
                class="w-16 h-4 bg-transparent border border-gray-300 rounded-xl z-10"
              ></div>
            </button>

            <!-- Default Items -->
            <div class="flex items-center" v-else>
              <NuxtIcon
                :name="item.icon || 'mdi:shape-outline'"
                class="text-xl mr-2 shrink-0"
                aria-hidden="true"
              />
              <span class="leading-none">{{ item.label }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- Properties Tab Content -->
    <div v-if="store.activeTab === 'properties'" class="space-y-2">
      <Properties />
    </div>
    <div v-if="store.activeTab === 'layers'" class="space-y-2">
      <ul>
        <li
          v-for="(layer, index) in layers"
          :key="layer.id"
          draggable="true"
          @dragstart="startLayerDrag($event, index)"
          @dragover.prevent
          @drop="onLayerDrop($event, index)"
          @click="selectLayer(layer.id)"
          class="p-2 rounded cursor-move hover:bg-gray-200 border border-gray-200"
          :class="{ 'bg-blue-200': selectedLayer === layer.id }"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <NuxtIcon name="mdi:drag" class="text-xl" />
              <span> {{ layer.name }} ({{ layer.type }})</span>
            </div>
            <button
              @click.stop="store.toggleLayerVisibility(layer.id)"
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
import { usePageStore } from "@badge/stores/usePageStore";
import { useQRCodeStore } from "@badge/stores/useQRCodeStore";
import { ref } from "vue";

const pageStore = usePageStore();
const qrcodeStore = useQRCodeStore();
const store = useCanvasStore();

defineProps({
  selectedElement: [String, Number, null],
  selectedElementType: String,
  layers: Array,
  selectedLayer: [Number, null],
  currentProperties: Object,
  displayOption: String,
});

const emit = defineEmits(["drag-start", "drag-end"]);

function startLayerDrag(event, index) {
  event.dataTransfer.setData("text/plain", index);
}

function onLayerDrop(event, dropIndex) {
  event.preventDefault();
  const dragIndex = parseInt(event.dataTransfer.getData("text/plain"));
  if (dragIndex === dropIndex) return;

  const boxes =
    store.activeSide === "front" ? store.frontBoxes : store.backBoxes;
  const [movedBox] = boxes.splice(dragIndex, 1);
  boxes.splice(dropIndex, 0, movedBox);

  boxes.forEach((box, index) => {
    box.zIndex = boxes.length - index; // Higher index = lower z-index
  });

  if (store.activeSide === "front") {
    store.frontBoxes = [...boxes]; // Trigger reactivity for front side
  } else {
    store.backBoxes = [...boxes]; // Trigger reactivity for back side
  }
}

function selectLayer(layerId) {
  store.selectLayer(layerId);
}

let openGroups = ref({});
let designGroups = ref([]);

const sidebarInfo = ref(null);

// The badge editor is mounted at /org/events/:id/badge, so the event uuid is the
// `id` route param. The draggable element catalogue is loaded from the EventOS
// API via the admin's bearer-authenticated client (useApi) — replacing the old
// base64 token gate against admin.expouse.com.
const route = useRoute();
const api = useApi();
const eventId = route.params.id || route.query.event || "";

if (eventId) {
  try {
    sidebarInfo.value = await api(`/events/${eventId}/badge-designs/element-library`);
  } catch (err) {
    console.error("Failed to load badge element library", err);
  }
}

function startSidebarDrag(event, item) {
  emit("drag-start", item);
}

function emitDragEnd(event, item) {
  const x = event.clientX;
  const y = event.clientY;
  emit("drag-end", { item, x, y });
}

function toggleGroup(groupType) {
  sidebarInfo.value.data.openGroups[groupType] =
    !sidebarInfo.value.data.openGroups[groupType];
}

function openImageUploadModal(item) {
  store.imageItem = item;
  store.showImageModal = true;
}

function openGradientModal() {
  store.showGradientModal = true;
}

function openColorModal() {
  store.showColorModal = true;
}

function removeBackground() {
  store.setBackground(null, store.activeSide);
}

function setPunchingArea(area) {
  selectedPunchArea(area);
  store.setPunchArea(area, store.activeSide);
}

const selectedPunchArea = (area) => {
  if (area === "none") {
    return !store.punchArea ? "border border-blue-500 bg-blue-50" : "";
  }
  switch (area) {
    case "circle-center":
      return store.punchCircle === "circle-center" && store.punchArea
        ? "border border-blue-500 bg-blue-50"
        : "";
    case "circle-left-right":
      return store.punchCircle === "circle-left-right" && store.punchArea
        ? "border border-blue-500 bg-blue-50"
        : "";
    case "long-center":
      return store.punchLong === "long-center" && store.punchArea
        ? "border border-blue-500 bg-blue-50"
        : "";
    case "long-left-right":
      return store.punchLong === "long-left-right" && store.punchArea
        ? "border border-blue-500 bg-blue-50"
        : "";
    default:
      return "";
  }
};
</script>

<style scoped>
li[draggable="true"] {
  user-select: none;
}

li[draggable="true"]:hover {
  background-color: #e5e7eb;
}

li[draggable="true"][dragging] {
  opacity: 0.5;
}
.transparent {
  display: flex;
  align-items: center;
  cursor: pointer;
  padding: 5px 10px;
  border: 1px solid #ccc;
  border-radius: 4px;
  margin-right: 10px;
}

.transparent span {
  margin-left: 5px;
}
</style>
