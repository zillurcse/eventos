<template>
  <div class="flex-1 w-full h-full">
    <ClientOnly>
      <Whiteboard />
    </ClientOnly>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from "vue";

import { useCanvasStore } from "@floorplan/stores/canvasStore";

definePageMeta({ layout: "floorplan" });

const store = useCanvasStore();

onMounted(() => {
  store.init(); // canvas store initialize
});
</script>
