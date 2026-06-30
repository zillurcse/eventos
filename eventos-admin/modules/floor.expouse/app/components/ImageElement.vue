<template>
  <div
    :data-element="id"
    class="absolute cursor-move select-none"
    :style="{
      left: `${screenPosition.x}px`,
      top: `${screenPosition.y}px`,
      width: `${screenSize.width}px`,
      height: `${screenSize.height}px`,
      transform: `rotate(${rotation}deg)`,
      transformOrigin: 'center',
      zIndex: zIndex ?? 0,
      display: isVisible ? 'block' : 'none',
      ...styleProps,
    }"
    @pointerdown="handlePointerDown"
  >
    <img :src="src" class="w-full h-full object-contain" alt="Dynamic image" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";
import type { Point } from "@floorplan/types/canvas";

const props = defineProps<{
  id: string;
  position: Point;
  size: { width: number; height: number };
  rotation: number;
  src: string;
  styleProps?: Record<string, string | number>;
  zIndex?: number;
  isLocked?: boolean;
  isVisible?: boolean;
}>();

const emit = defineEmits(["select"]);

const canvasStore = useCanvasStore();

// Computed screen position and size
const screenPosition = computed(() => ({
  x: (props.position.x - canvasStore.offset.x) * canvasStore.zoom,
  y: (props.position.y - canvasStore.offset.y) * canvasStore.zoom,
}));

const screenSize = computed(() => ({
  width: props.size.width * canvasStore.zoom,
  height: props.size.height * canvasStore.zoom,
}));

// Drag handling
const isDragging = ref(false);
const dragStart = ref<Point | null>(null);
const dragStartPos = ref<Point | null>(null);

const handlePointerDown = (e: PointerEvent) => {
  if (props.isLocked) {
    e.preventDefault();
    e.stopPropagation();
    return;
  }

  emit("select", props.id);
  e.stopPropagation();
  isDragging.value = true;
  dragStart.value = { x: e.clientX, y: e.clientY };
  dragStartPos.value = { ...props.position };
  document.addEventListener("pointermove", handlePointerMove);
  document.addEventListener("pointerup", handlePointerUp, { once: true });
};

const handlePointerMove = (e: PointerEvent) => {
  if (!isDragging.value || !dragStart.value || !dragStartPos.value) return;

  const deltaX = (e.clientX - dragStart.value.x) / canvasStore.zoom;
  const deltaY = (e.clientY - dragStart.value.y) / canvasStore.zoom;
  canvasStore.updateElement(props.id, {
    position: {
      x: dragStartPos.value.x + deltaX,
      y: dragStartPos.value.y + deltaY,
    },
  });
};

const handlePointerUp = () => {
  isDragging.value = false;
  document.removeEventListener("pointermove", handlePointerMove);
};

onMounted(() => {
  // Any image-specific setup if needed
});
</script>

<style scoped>
/* Add any image-specific styles if needed */
</style>
