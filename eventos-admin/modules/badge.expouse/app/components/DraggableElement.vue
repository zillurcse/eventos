```vue
<template>
  <vue-draggable-resizable
    :x="element.x"
    :y="element.y"
    :w="element.width"
    :h="element.height"
    :z="element.zIndex"
    :rotatable="true"
    :rotation="element.rotation"
    :resizable="true"
    :draggable="true"
    @dragging="onDrag"
    @resizing="onResize"
    @rotating="onRotate"
    @click="emit('select')"
    class="draggable-element border border-gray-400"
  >
    <!-- Label -->
    <div
      class="absolute -top-6 bg-gray-800 text-white text-xs px-2 py-1 rounded"
    >
      {{ element.label }}
    </div>
    <!-- Content -->
    <div
      v-if="element.type === 'text'"
      :class="{ hidden: element.isEditing }"
      class="p-2 text-center w-full h-full overflow-hidden"
      :style="{ fontSize: `${element.fontSize}px` }"
      @dblclick="emit('edit')"
    >
      {{ element.content }}
    </div>
    <input
      v-if="element.type === 'text' && element.isEditing"
      v-model="element.content"
      @blur="emit('edit')"
      @keyup.enter="emit('edit')"
      class="w-full h-full p-2 border"
      :style="{ fontSize: `${element.fontSize}px` }"
    />
    <qrcode-vue
      v-if="element.type === 'qr'"
      :value="element.content"
      :size="element.width"
      class="w-full h-full"
    />
    <img
      v-if="element.type === 'image'"
      :src="element.content"
      class="w-full h-full object-contain"
    />
    <!-- Resize Handles (custom styling for visibility) -->
    <div
      v-for="handle in ['tl', 'tr', 'bl', 'br']"
      :key="handle"
      class="absolute w-4 h-4 bg-blue-500 rounded-full cursor-move"
      :class="{
        'top-0 left-0 transform -translate-x-1/2 -translate-y-1/2':
          handle === 'tl',
        'top-0 right-0 transform -translate-y-1/2 translate-x-1/2':
          handle === 'tr',
        'bottom-0 left-0 transform translate-x-1/2 translate-y-1/2':
          handle === 'bl',
        'bottom-0 right-0 transform -translate-x-1/2 translate-y-1/2':
          handle === 'br',
      }"
    ></div>
  </vue-draggable-resizable>
</template>

<script setup>
import { vueDraggableResizable } from "vue3-draggable-resizable";
import QRCodeVue from "qrcode.vue";

defineProps({
  element: Object,
  index: Number,
  selected: Boolean,
});

const emit = defineEmits(["select", "update", "edit"]);

const onDrag = (x, y) => {
  emit("update", { x, y });
};

const onResize = (x, y, width, height) => {
  emit("update", { x, y, width, height });
};

const onRotate = (rotation) => {
  emit("update", { rotation });
};
</script>

<style scoped>
.draggable-element {
  position: absolute;
  background: rgba(255, 255, 255, 0.8);
}
</style>
```
