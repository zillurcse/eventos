<template>
  <div>
    <div
      ref="canvas"
      class="absolute w-full h-full bg-white select-none"
      :style="{
        background:
          props.modelValue === store.frontBoxes
            ? store.frontBackground || 'white'
            : store.backBackground || 'white',
      }"
      @mousedown="handleCanvasClick"
    >
      <!-- Canvas Background -->
      <!-- Punch Area Indicators -->
      <div v-if="store.punchArea" class="punch-area">
        <div class="punch-long">
          <!-- {{ store.punchLong }} -->
          <div
            v-if="store.punchLong == 'long-left-right'"
            class="w-16 h-4 bg-transparent border border-gray-200 rounded-xl absolute top-5 right-5 z-10"
          ></div>
          <div
            v-if="store.punchLong == 'long-left-right'"
            class="w-16 h-4 bg-transparent border border-gray-200 rounded-xl absolute top-5 left-5 z-10"
          ></div>

          <div
            v-if="store.punchLong == 'long-center'"
            class="w-16 h-4 bg-transparent border border-gray-200 rounded-xl absolute top-5 left-1/2 -translate-x-1/2 z-10"
          ></div>
        </div>

        <div class="punch-circle">
          <div
            v-if="store.punchCircle == 'circle-left-right'"
            class="w-5 h-5 bg-transparent border border-gray-200 rounded-xl absolute top-5 left-5 z-10"
          ></div>
          <div
            v-if="store.punchCircle == 'circle-left-right'"
            class="w-5 h-5 bg-transparent border border-gray-200 rounded-xl absolute top-5 right-5 z-10"
          ></div>

          <div
            v-if="store.punchCircle == 'circle-center'"
            class="w-5 h-5 bg-transparent border border-gray-200 rounded-xl absolute top-5 left-1/2 -translate-x-1/2 z-10"
          ></div>
        </div>
      </div>
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
          v-if="box.visible"
          class="absolute border group border-blue-500"
          :class="{
            'border-2 border-blue-500': box.isSelected,
            'border border-transparent': !box.isSelected,
          }"
          :style="{
            top: box.position.top + 'px',
            left: box.position.left + 'px',
            width: box.properties.size.width + 'px',
            height: box.properties.size.height + 'px',
            transform: `rotate(${box.properties.rotation}deg)`,
            transformOrigin: 'center center',
            backgroundColor: box.properties.fillTransparency
              ? 'transparent'
              : box.properties.fillColor || '#ffffff',
            border:
              box.properties.strokeWidth > 0
                ? `${box.properties.strokeWidth}px solid ${box.properties.strokeColor}`
                : 'none',
            zIndex: box.zIndex || 0,
          }"
          @mousedown.stop="activateElement(index, $event)"
          @dblclick="handleDoubleClick(box, index)"
          @keydown="deleteItem($event)"
        >
          <!-- Selected-only elements -->
          <template v-if="box.isSelected">
            <!-- Label (Dynamic) -->
            <div
              v-if="box.label"
              class="absolute -top-5 left-0 px-1 text-xs text-white bg-blue-600"
            >
              {{ box.label }}
            </div>

            <!-- Delete Button -->
            <div
              class="absolute -top-6 right-0 text-xs text-ceter cursor-pointer"
              @click.stop="deleteElement(index)"
            >
              <NuxtIcon
                name="bitcoin-icons:trash-outline"
                class="text-lg flex items-center justify-center place-content-center text-red-600"
              />
            </div>

            <!-- Distance (Only on Drag) -->
            <template v-if="box.isDragging">
              <div
                class="absolute left-1/2 -top-10 -translate-x-1/2 text-xs text-red-500"
              >
                Y: {{ Math.round(box.position.top) }}
              </div>
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
              <NuxtIcon name="line-md:rotate-270" class="text-blue-600" />
            </div>

            <!-- Resizing Handles -->
            <div
              v-for="dir in directions"
              :key="dir"
              :class="['handle', dir]"
              @mousedown.stop.prevent="startResize(index, dir)"
            ></div>
          </template>

          <!-- Content -->
          <component
            v-if="
              checkElementTypes.find((item) =>
                checkElementTypes.includes(box.type)
              )
            "
            :is="box.type"
            contenteditable
            class="focus:border focus:outline-none focus:border-blue-500 cursor-move leading-tight w-full h-full flex"
            :class="
              box.type === 'p'
                ? [
                    'flex-col',
                    'break-words',
                    'whitespace-normal',
                    verticalToJustifyClass(box),
                    horizontalToTextAlignClass(box),
                    { 'cursor-text': box.isSelected },
                  ]
                : [
                    verticalAlignClass(box),
                    horizontalAlignClass(box),
                    { 'cursor-text': box.isSelected },
                  ]
            "
            :style="textStyles(box)"
            :ref="(el) => setTextElementRef(box.id, el)"
            @input="updateText(box, $event)"
            @click="preserveCursorPosition($event)"
          >
            {{ box.text }}
          </component>

          <!-- Images -->
          <img
            v-if="box.type === 'img'"
            :src="box.properties.src.url"
            class="w-full h-full cursor-pointer select-none"
            :class="[objectPositionClass(box), objectFitPositionClass(box)]"
            @keydown="deleteItem($event)"
            @error="handleImageError"
          />

          <!-- Static Image -->
          <img
            v-if="box.type === 'background'"
            :src="box.properties.src.url"
            class="w-full h-full transition-all duration-300 cursor-pointer select-none"
            :class="[objectPositionClass(box), objectFitPositionClass(box)]"
            @keydown="deleteItem($event)"
            @error="handleImageError"
          />

          <!-- Avatar -->
          <div
            v-if="box.type === 'avatar' && box.key === 'avatar'"
            :class="[
              'overflow-hidden shadow-sm transition-transform hover:scale-[1.02] flex items-center justify-center bg-gray-100',
              box.properties.avatar.showBorder ? 'border border-gray-300' : '',
              box.properties.avatar.showRing
                ? 'ring-2 ring-offset-2 ring-gray-400'
                : '',
            ]"
            :style="box.properties.avatar.containerStyle"
          >
            <img
              :src="box.properties.avatar.avatar_src"
              class="object-cover"
              :style="box.properties.avatar.imageStyle"
            />
          </div>
          <div
            v-if="box.type === 'avatar' && box.key === 'event_logo'"
            :class="[
              'overflow-hidden shadow-sm transition-transform hover:scale-[1.02] flex items-center justify-center bg-gray-100',
              box.properties.avatar.showBorder ? 'border border-gray-300' : '',
              box.properties.avatar.showRing
                ? 'ring-2 ring-offset-2 ring-gray-400'
                : '',
            ]"
            :style="box.properties.avatar.containerStyle"
          >
            <img
              :src="box.text"
              class="object-cover"
              :style="box.properties.avatar.imageStyle"
            />
          </div>

          <!-- QR Code -->
          <Qrcode
            v-if="box.type === 'qrcode'"
            :value="box.properties.qrcode.value"
            :variant="box.properties.qrcode.variant"
            :radius="box.properties.qrcode.radius"
            :blackColor="box.properties.qrcode.blackColor"
            :whiteColor="box.properties.qrcode.whiteColor"
            @keydown="deleteItem(index, $event)"
          />
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { useCanvasStore } from "@badge/stores/useCanvasStore";
import { ref, computed, watch, nextTick } from "vue";

const store = useCanvasStore();
const props = defineProps({
  modelValue: Array,
});

const canvas = ref(null);
const checkElementTypes = ["h1", "h2", "h3", "h4", "h6", "p", "a", "span"];
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
const textElements = ref({});

function verticalToJustifyClass(box) {
  let align = "justify-center";
  if (box.properties.verticalAlign === "top") align = "justify-start";
  if (box.properties.verticalAlign === "middle") align = "justify-center";
  if (box.properties.verticalAlign === "bottom") align = "justify-end";
  return align;
}

function horizontalToTextAlignClass(box) {
  const align = box.properties.horizontalAlign;
  if (align === "left") return "text-left";
  if (align === "center") return "text-center";
  if (align === "right") return "text-right";
  if (align === "justify") return "text-justify";
  return "text-center";
}

function handleCanvasClick() {
  store.boxes.forEach((b) => (b.isSelected = false));
  selectedBoxIndex = -1;
  store.selectedElement = null;
  store.updateProperties();
}

function activateElement(index, event) {
  store.boxes.forEach((b, i) => (b.isSelected = i === index));
  selectedBoxIndex = index;
  startDrag(index, event);
  store.selectedElement = store.boxes[index].id;
  store.selectedElementType = store.boxes[index].type;
  store.activeTab = "properties";
  store.updateProperties();
}

function deleteItem(event) {
  if (event.key === "Delete" && store.selectedElement) {
    const index = store.boxes.findIndex(
      (item) => item.id === store.selectedElement
    );
    if (index !== -1) {
      store.boxes.splice(index, 1);
      store.selectedElement = null;
      store.updateProperties();
    }
  }
}

function deleteElement(index) {
  const box = store.boxes[index];
  if (box) {
    store.boxes.splice(index, 1);
    store.selectedElement = null;
    store.updateProperties();
  }
}

function handleDoubleClick(box, index) {
  if (checkElementTypes.includes(box.type)) {
    store.boxes.forEach((b, i) => (b.isSelected = i === index));
    selectedBoxIndex = index;
    store.selectedElement = box.id;
    store.selectedElementType = box.type;
    store.activeTab = "properties";
    store.updateProperties();

    nextTick(() => {
      const el = textElements.value[box.id];
      if (el) {
        el.focus();
        const range = document.createRange();
        const selection = window.getSelection();
        range.selectNodeContents(el);
        range.collapse(false);
        selection.removeAllRanges();
        selection.addRange(range);
      }
    });
  }
}

onMounted(() => {
  const resizeObserver = new ResizeObserver(() => {
    canvasWidth.value = canvas.value?.offsetWidth;
    canvasHeight.value = canvas.value?.offsetHeight;
  });
  resizeObserver.observe(canvas.value);
  document.addEventListener("keydown", deleteItem);
});

function setTextElementRef(id, el) {
  if (el) {
    textElements.value[id] = el;
  }
}

function startDrag(index, event) {
  const box = store.boxes[index];
  dragOffset.x = event.clientX - box.position.left;
  dragOffset.y = event.clientY - box.position.top;
  box.isDragging = true;
  document.addEventListener("mousemove", onDrag);
  document.addEventListener("mouseup", stopActions);
}

function onDrag(event) {
  const box = store.boxes[selectedBoxIndex];
  if (!box) return;

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
  store.currentProperties.avatar = {
    ...box.properties.avatar,
  };
  store.updateProperties(store.currentProperties);
}

function startResize(index, dir) {
  selectedBoxIndex = index;
  resizeDir = dir;
  document.addEventListener("mousemove", onResize);
  document.addEventListener("mouseup", stopActions);
}

function onResize(event) {
  const box = store.boxes[selectedBoxIndex];
  const minSize = 40;
  const dx = event.movementX;
  const dy = event.movementY;

  let newWidth = box.properties.size.width;
  let newHeight = box.properties.size.height;
  let newLeft = box.position.left;
  let newTop = box.position.top;

  if (resizeDir.includes("right")) {
    newWidth = Math.max(minSize, newWidth + dx);
  }
  if (resizeDir.includes("left")) {
    const prevWidth = newWidth;
    newWidth = Math.max(minSize, newWidth - dx);
    if (newWidth > minSize || prevWidth > minSize) {
      newLeft += dx;
    }
  }

  if (resizeDir.includes("bottom")) {
    newHeight = Math.max(minSize, newHeight + dy);
  }
  if (resizeDir.includes("top")) {
    const prevHeight = newHeight;
    newHeight = Math.max(minSize, newHeight - dy);
    if (newHeight > minSize || prevHeight > minSize) {
      newTop += dy;
    }
  }

  box.properties.size.width = newWidth;
  box.properties.size.height = newHeight;
  box.position.left = newLeft;
  box.position.top = newTop;

  store.currentProperties.size = { ...box.properties.size };
  store.currentProperties.x = newLeft;
  store.currentProperties.y = newTop;
  store.updateProperties(store.currentProperties);
}

function startRotate(index, event) {
  const box = store.boxes[index];
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

    const delta = (currentAngle - startAngle) * 12;

    box.properties.rotation = (initialRotation + delta + 360) % 360;
    store.currentProperties.rotation = Math.floor(box.properties.rotation);
    store.currentProperties.avatar = {
      ...box.properties.avatar,
    };
    store.updateProperties(store.currentProperties);
  }

  function stopRotate() {
    document.removeEventListener("mousemove", rotate);
    document.removeEventListener("mouseup", stopRotate);
  }

  document.addEventListener("mousemove", rotate);
  document.addEventListener("mouseup", stopRotate);
}

function stopActions() {
  store.boxes.forEach((b) => (b.isDragging = false));
  document.removeEventListener("mousemove", onDrag);
  document.removeEventListener("mousemove", onResize);
  document.removeEventListener("mouseup", stopActions);
}

function preserveCursorPosition(event) {
  const selection = window.getSelection();
  if (selection && selection.rangeCount > 0) {
    const range = selection.getRangeAt(0);
    store.cursorPosition = {
      node: range.startContainer,
      offset: range.startOffset,
    };
  }
}

function updateText(box, event) {
  const el = event.target;
  const selection = window.getSelection();
  let cursorOffset = 0;
  let cursorNode = null;

  if (selection && selection.rangeCount > 0) {
    const range = selection.getRangeAt(0);
    cursorNode = range.startContainer;
    cursorOffset = range.startOffset;
  }

  const newText = el.innerText;
  store.updateElementText(box.id, newText);
  store.currentProperties.text = newText;
  store.updateProperties(store.currentProperties);

  if (cursorNode && cursorOffset !== null) {
    nextTick(() => {
      const range = document.createRange();
      try {
        range.setStart(cursorNode, cursorOffset);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
      } catch (e) {
        console.warn("Failed to restore cursor position:", e);
      }
    });
  }
}

function handleImageError(event) {
  console.error("Image failed to load:", event.target.src);
}

const selectedBox = computed(() => store.boxes.find((b) => b.isSelected));

function textStyles(box) {
  const calculatedSize = Math.max(
    12,
    Math.min(
      48,
      box.type === "p"
        ? box.properties.size.height * 0.2
        : box.properties.size.height * 0.4
    )
  );
  return {
    fontSize:
      box.properties.fontSize === "Auto" || !box.properties.fontSize
        ? calculatedSize + "px"
        : box.properties.fontSize + "px",
    fontFamily: box.properties.font || "poppins, sans-serif",
    fontWeight: box.properties.fontWeight || "normal",
    fontStyle: box.properties.fontStyle || "normal",
    textDecoration: box.properties.textDecoration || "none",
    textTransform: box.properties.textTransform || "none",
    color: box.properties.color || "black",
    direction: box.properties.direction || "ltr",
  };
}

function horizontalAlignClass(box) {
  let align = "justify-center";
  if (box.properties.horizontalAlign === "left") align = "justify-start";
  if (box.properties.horizontalAlign === "center") align = "justify-center";
  if (box.properties.horizontalAlign === "right") align = "justify-end";
  return [align];
}

function verticalAlignClass(box) {
  let align = "items-center";
  if (box.properties.verticalAlign === "top") align = "items-start";
  if (box.properties.verticalAlign === "middle") align = "items-center";
  if (box.properties.verticalAlign === "bottom") align = "items-end";
  return [align];
}

function objectPositionClass(box) {
  switch (box.properties.imagePosition || box.properties.objectFit) {
    case "top-left":
      return "object-top-left";
    case "top":
      return "object-top";
    case "top-right":
      return "object-top-right";
    case "left":
      return "object-left";
    case "center":
      return "object-center";
    case "right":
      return "object-right";
    case "bottom-left":
      return "object-bottom-left";
    case "bottom":
      return "object-bottom";
    case "bottom-right":
      return "object-bottom-right";
  }
}

function objectFitPositionClass(box) {
  switch (box.properties.objectFit) {
    case "contain":
      return "object-contain";
    case "cover":
      return "object-cover";
    case "fill":
      return "object-fill";
    case "none":
      return "object-none";
    case "scale-down":
      return "object-scale-down";
  }
}

watch(
  () => store.selectedElement,
  (newId) => {
    if (newId) {
      const box = store.boxes.find((b) => b.id === newId);
      if (box && ["h1", "p"].includes(box.type)) {
        nextTick(() => {
          const el = textElements.value[newId];
          if (el) {
            el.focus();
            const range = document.createRange();
            const selection = window.getSelection();
            range.selectNodeContents(el);
            range.collapse(false);
            selection.removeAllRanges();
            selection.addRange(range);
          }
        });
      }
    }
  }
);
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

[contenteditable] {
  display: flex;
  width: 100%;
  height: 100%;
  box-sizing: border-box;
  overflow: hidden;
}
</style>
