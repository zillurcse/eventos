<template>
  <div>
    <div
      ref="canvas"
      class="absolute w-full h-full bg-white select-none border-none"
      :style="{
        background:
          props.modelValue === store.frontBoxes
            ? store.frontBackground || 'white'
            : store.backBackground || 'white',
      }"
    >
      <!-- Canvas Background -->
      <!-- Punch Area Indicators -->
      <div class="punch-area">
        <div class="punch-long">
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
      <!-- Draggable Elements -->
      <template v-for="(box, index) in props.modelValue" :key="box.id">
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
        >
          <!-- Content -->
          <component
            v-if="checkElementTypes.includes(box.type)"
            :is="box.type"
            class="leading-tight w-full h-full flex"
            :class="[
              box.type === 'p'
                ? [
                    'flex-col',
                    'break-words',
                    'whitespace-normal',
                    verticalToJustifyClass(box),
                    horizontalToTextAlignClass(box),
                  ]
                : [verticalAlignClass(box), horizontalAlignClass(box)],
            ]"
            :style="textStyles(box)"
          >
            {{ box.text }}
          </component>
          <!-- Images and Background -->
          <img
            v-if="box.type === 'img'"
            :src="box.properties.src.url"
            class="w-full h-full cursor-pointer select-none"
            :class="[objectPositionClass(box), objectFitPositionClass(box)]"
          />

          <img
            v-if="box.type === 'background'"
            :src="box.properties.src.url"
            class="w-full h-full transition-all duration-300 cursor-pointer select-none"
            :class="[objectPositionClass(box), objectFitPositionClass(box)]"
          />
          <!-- Redesigned Avatar -->
          <!-- {{ box.text }} -->
          <div
            v-if="box.type === 'avatar'"
            class="w-full h-full overflow-hidden shadow-sm transition-transform hover:scale-[1.02] flex items-center justify-center bg-gray-100"
            :class="[
              box.properties.avatar.showBorder ? 'border border-gray-300' : '',
              box.properties.avatar.showRing
                ? 'ring-2 ring-offset-2 ring-gray-400'
                : '',
            ]"
            :style="box.properties.avatar.containerStyle"
          >
            <img
              :src="box.text"
              class="w-full h-full object-cover"
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
            class="w-full h-full"
          />
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { useCanvasStore } from "@badge/stores/useCanvasStore";
import { ref, computed, watch, nextTick, onMounted } from "vue";

const store = useCanvasStore();
const props = defineProps({
  modelValue: Array,
});

const canvas = ref(null);
const checkElementTypes = ["h1", "h2", "h3", "h4", "h6", "p", "a", "span"];
const textElements = ref({});

onMounted(() => {
  const resizeObserver = new ResizeObserver(() => {
    canvas.value?.offsetWidth;
    canvas.value?.offsetHeight;
  });
  resizeObserver.observe(canvas.value);
});

function setTextElementRef(id, el) {
  if (el) {
    textElements.value[id] = el;
  }
}

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
      const box = props.modelValue.find((b) => b.id === newId);
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
