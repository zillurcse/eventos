<template>
  <div>
    <div
      ref="canvas"
      class="absolute w-full h-full bg-white select-none"
      @mousedown="handleCanvasClick"
    >
      <!-- Vertical & Horizontal Guide Lines -->
      <template v-if="selectedBox && selectedBox.isDragging">
        <div
          class="absolute top-0 bottom-0 w-px bg-blue-500 z-0"
          :style="{
            left:
              selectedBox.position.left +
              selectedBox.properties.size.width / 2 +
              'px',
          }"
        ></div>
        <div
          class="absolute left-0 right-0 h-px bg-blue-500 z-0"
          :style="{
            top:
              selectedBox.position.top +
              selectedBox.properties.size.height / 2 +
              'px',
          }"
        ></div>
      </template>

      <!-- Draggable Elements -->
      <template v-for="(box, index) in store.boxes" :key="box.id">
        <div
          v-if="box.isSelected"
          class="absolute border group border-blue-500"
          :style="{
            top: box.position.top + 'px',
            left: box.position.left + 'px',

            width: box.properties.size.width + 'px',
            height: box.properties.size.height + 'px',
            transform: `rotate(${box.properties.rotation}deg)`,
            transformOrigin: 'center center',
            backgroundColor: box.properties.fillTransparency
              ? 'transparent'
              : box.properties.fillColor,
          }"
          @mousedown.stop="activateElement(index, $event)"
        >
          <!-- {{ box.properties.x }} -->
          <!-- Label (Dynamic) -->
          <div
            v-if="box.label"
            class="absolute -top-5 left-0 px-1 text-xs text-white bg-blue-600"
          >
            {{ box.label }}
          </div>

          <!-- Distance (Only on Drag) -->
          <template v-if="box.isDragging">
            <!-- Y top -->
            <div
              class="absolute left-1/2 -top-10 -translate-x-1/2 text-xs text-red-500"
            >
              Y: {{ Math.round(box.position.top) }}
            </div>
            <!-- X left -->
            <div
              class="absolute -left-6 top-1/2 -translate-y-1/2 text-xs text-red-500"
            >
              {{
                Math.round(box.position.left + box.properties.size.width / 2)
              }}
            </div>
            <div
              class="absolute -right-6 top-1/2 -translate-y-1/2 text-xs text-red-500"
            >
              {{
                Math.round(
                  canvasWidth -
                    box.position.left -
                    box.properties.size.width / 2
                )
              }}
            </div>
            <div
              class="absolute left-1/2 -bottom-5 -translate-x-1/2 text-xs text-red-500"
            >
              {{
                Math.round(
                  canvasHeight -
                    box.position.top -
                    box.properties.size.height / 2
                )
              }}
            </div>
          </template>

          <!-- Rotate Icon -->
          <div
            class="rotate-icon"
            @mousedown.stop.prevent="startRotate(index, $event)"
          >
            🔄
          </div>

          <!-- Resizing Handles -->
          <div
            v-for="dir in directions"
            :key="dir"
            :class="['handle', dir]"
            @mousedown.stop.prevent="startResize(index, dir)"
          ></div>

          <!-- Editable Text -->
          <!-- Editable Dynamic Element -->
          <component
            v-if="box.type !== 'img'"
            :is="box.type"
            :data-element-id="box.id"
            contenteditable
            class="flex justify-center items-center outline-none cursor-move leading-tight text-center w-full h-full"
            :style="{
              fontSize:
                Math.max(
                  12,
                  Math.min(
                    48,
                    box.type == 'p'
                      ? box.properties.size.height * 0.2
                      : box.properties.size.height * 0.4
                  )
                ) + 'px',
              fontFamily: 'popins, sans-serif',
            }"
            >{{ box.text }}</component
          >

          <!-- Image Element -->
          <img
            v-else
            :src="box.text.src"
            class="w-full h-full object-contain cursor-pointer select-none"
          />
        </div>
        <div
          v-else
          class="absolute border group border-transparent"
          :style="{
            top: box.position.top + 'px',
            left: box.position.left + 'px',
            width: box.properties.size.width + 'px',
            height: box.properties.size.height + 'px',
            transform: `rotate(${box.properties.rotation}deg)`,
            transformOrigin: 'center center',
          }"
          @mousedown.stop="activateElement(index, $event)"
        >
          <!-- Editable Text -->
          <!-- Editable Dynamic Element -->
          <component
            v-if="box.type !== 'img'"
            :is="box.type"
            contenteditable
            class="flex justify-center items-center outline-none cursor-move leading-tight text-center w-full h-full"
            :style="{
              fontSize:
                Math.max(
                  12,
                  Math.min(
                    48,
                    box.type == 'p'
                      ? box.properties.size.height * 0.2
                      : box.properties.size.height * 0.4
                  )
                ) + 'px',
              fontFamily: 'popins, sans-serif',
              color: box.textColor || 'black',
            }"
            >{{ box.text }}</component
          >

          <!-- Image Element -->
          <img
            v-else
            :src="box.text.src"
            class="w-full h-full object-contain cursor-pointer select-none"
          />
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { useCanvasStore } from "@badge/stores/useCanvasStore";
const store = useCanvasStore();
const props = defineProps({
  modelValue: Array,
  sendElement: Object,
});

const emit = defineEmits([
  "addToFrontCanvas",
  "addToBackCanvas",
  "requestFrontImage",
  "requestBackImage",
  "sendSelectedElement",
]);

watch(
  () => props.sendElement,
  (newSendElement) => {
    if (newSendElement) {
      createElement(newSendElement);
    }
  }
);

const canvas = ref(null);
const boxes = computed(() => props.modelValue);
store.boxes = props.modelValue;
const directions = [
  "top-left",
  "top",
  "top-right",
  "right",
  "bottom-right",
  "bottom",
  "bottom-left",
  "left",
];

const canvasWidth = ref(0);
const canvasHeight = ref(0);
let resizeDir = "";
let dragOffset = { x: 0, y: 0 };
let selectedBoxIndex = -1;

const selectedElementId = ref(null);

onMounted(() => {
  const resizeObserver = new ResizeObserver(() => {
    canvasWidth.value = canvas.value?.offsetWidth;
    canvasHeight.value = canvas.value.offsetHeight;
  });

  resizeObserver.observe(canvas.value);
});

function createElement(element) {
  // console.log(`Creating element: ${element.item.label}`);

  // return false;

  if (element.item.type === "img") {
    if (element.side === "front") {
      emit("requestFrontImage");
    } else {
      emit("requestBackImage");
    }
    return;
  }

  const newElement = {
    id: Date.now(),
    text: element.item.label || "Sample Text",
    type: element.item.type,
    label: `${element.item.label}`,
    position: { top: element.position.top, left: element.position.left },
    properties: {
      y: element.y,
      x: element.x,
      size: { width: 200, height: 64 },
      rotation: 0,
      font: "",
      fontStyle: "",
      fontSize: "",
      fillColor: "",
      fillTransparency: "",
      text: "",
      exampleText: "",
      textDecoration: "",
      color: "",
      textAlign: "",
      verticalAlign: "",
      textTransform: "",
      src: "",
      strokeColor: "",
      strokeWidth: "",
      associatedData: "",
      content: "",
    },

    isSelected: false,
    isDragging: false,
    direction: "ltr",
  };

  if (element.side === "front") {
    emit("addToFrontCanvas", newElement);
  } else {
    emit("addToBackCanvas", newElement);
  }
}

function handleCanvasClick() {
  boxes.value.forEach((b) => (b.isSelected = false));
  selectedBoxIndex = -1;
  // emit("sendSelectedElement", null);
  // store.selectedElement = null;
  store.updateProperties();
}

function activateElement(index, event) {
  boxes.value.forEach((b, i) => (b.isSelected = i === index));
  selectedBoxIndex = index;
  startDrag(index, event); // ✅ Trigger drag when element is clicked

  emit("sendSelectedElement", boxes.value[index].id);

  store.selectedElement = boxes.value[index].id;

  // store.updateProperties();
}

function startDrag(index, event) {
  const box = boxes.value[index];
  dragOffset.x = event.clientX - box.position.left;
  dragOffset.y = event.clientY - box.position.top;
  box.isDragging = true;
  document.addEventListener("mousemove", onDrag);
  document.addEventListener("mouseup", stopActions);
}

function onDrag(event) {
  const box = boxes.value[selectedBoxIndex];
  const canvasRect = canvas.value.getBoundingClientRect();
  let newLeft = event.clientX - dragOffset.x;
  let newTop = event.clientY - dragOffset.y;

  newLeft = Math.max(
    0,
    Math.min(canvasRect.width - box.properties.size.width, newLeft)
  );
  newTop = Math.max(
    0,
    Math.min(canvasRect.height - box.properties.size.height, newTop)
  );

  box.position.left = newLeft;
  box.position.top = newTop;
  store.currentProperties.x = newLeft;
  store.currentProperties.y = newTop;
}

function startResize(index, dir) {
  selectedBoxIndex = index;
  resizeDir = dir;
  document.addEventListener("mousemove", onResize);
  document.addEventListener("mouseup", stopActions);
}

function onResize(event) {
  const box = boxes.value[selectedBoxIndex];
  const minSize = 40;
  const dx = event.movementX;
  const dy = event.movementY;

  if (resizeDir.includes("right"))
    box.properties.size.width = Math.max(
      minSize,
      box.properties.size.width + dx
    );

  if (resizeDir.includes("left")) {
    box.properties.size.width = Math.max(
      minSize,
      box.properties.size.width - dx
    );
    box.position.left += dx;
  }
  if (resizeDir.includes("bottom"))
    box.properties.size.height = Math.max(
      minSize,
      box.properties.size.height + dy
    );
  if (resizeDir.includes("top")) {
    box.properties.size.height = Math.max(
      minSize,
      box.properties.size.height - dy
    );
    box.position.top += dy;
  }

  store.currentProperties.size.width = box.properties.size.width;
  store.currentProperties.size.height = box.properties.size.height;
}

function autoResizeText(index, event) {
  //   const el = event.target;
  //   el.style.height = "auto"; // Reset first
  //   el.style.height = el.scrollHeight + "px";
  //   boxes.value[index].size.height = el.offsetHeight;
}

function startRotate(index, event) {
  const box = boxes.value[index];
  const centerX = box.position.left + box.properties.size.width / 2;
  const centerY = box.position.top + box.properties.size.height / 2;

  const startX = event.clientX;
  const startY = event.clientY;

  const dxStart = startX - centerX;
  const dyStart = startY - centerY;
  const startAngle = Math.atan2(dyStart, dxStart) * (360 / Math.PI);
  const initialRotation = box.properties.rotation;

  function rotate(e) {
    const dx = e.clientX - centerX;
    const dy = e.clientY - centerY;
    const currentAngle = Math.atan2(dy, dx) * (360 / Math.PI);

    // 🔥 Boost rotation speed (adjust multiplier as needed)
    const delta = (currentAngle - startAngle) * 12;

    box.properties.rotation = (initialRotation + delta + 360) % 360;
  }

  function stopRotate() {
    document.removeEventListener("mousemove", rotate);
    document.removeEventListener("mouseup", stopRotate);
  }

  document.addEventListener("mousemove", rotate);
  document.addEventListener("mouseup", stopRotate);
}

function stopActions() {
  boxes.value.forEach((b) => (b.isDragging = false));
  document.removeEventListener("mousemove", onDrag);
  document.removeEventListener("mousemove", onResize);
  document.removeEventListener("mouseup", stopActions);
}

const selectedBox = computed(() => boxes.value.find((b) => b.isSelected));

function emitSelectElement(id) {
  emit("selectElement", id);
}
</script>

<style scoped>
.handle {
  width: 8px;
  height: 8px;
  background-color: white;
  border: 1px solid blue;
  position: absolute;
  z-index: 10;
}

.top-left {
  top: -4px;
  left: -4px;
  cursor: nwse-resize;
}
.top {
  top: -4px;
  left: 50%;
  transform: translateX(-50%);
  cursor: ns-resize;
}
.top-right {
  top: -4px;
  right: -4px;
  cursor: nesw-resize;
}
.right {
  top: 50%;
  right: -4px;
  transform: translateY(-50%);
  cursor: ew-resize;
}
.bottom-right {
  bottom: -4px;
  right: -4px;
  cursor: nwse-resize;
}
.bottom {
  bottom: -4px;
  left: 50%;
  transform: translateX(-50%);
  cursor: ns-resize;
}
.bottom-left {
  bottom: -4px;
  left: -4px;
  cursor: nesw-resize;
}
.left {
  top: 50%;
  left: -4px;
  transform: translateY(-50%);
  cursor: ew-resize;
}

.rotate-icon {
  position: absolute;
  top: -30px;
  left: 50%;
  transform: translateX(-50%);
  cursor: grab;
  font-size: 18px;
  user-select: none;
}
</style>
