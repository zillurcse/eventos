<template>
  <div class="p-4 bg-gray-100 w-1/4">
    <div
      v-for="(el, index) in elements"
      :key="index"
      draggable="true"
      @dragstart="onDragStart(el)"
      class="p-2 mb-2 bg-white rounded shadow cursor-move text-center"
    >
      {{ el.name }}
    </div>
  </div>
</template>

<script setup>
const elements = [
  { name: "Heading", type: "h1" },
  { name: "Paragraph", type: "p" },
  { name: "Image", type: "img" },
  { name: "Button", type: "button" },
];

function onDragStart(el) {
  event.dataTransfer.setData("element", JSON.stringify(el));
}
</script>
