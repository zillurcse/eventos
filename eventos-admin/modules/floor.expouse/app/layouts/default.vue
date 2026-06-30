<template>
  <div class="relative h-screen bg-gray-100 overflow-hidden">
    <main class="absolute inset-0 overflow-hidden">
      <slot />
    </main>
    <FloorToolbar class="absolute top-0 left-0 w-full z-10" />
    <FloorSidebar class="absolute top-10 left-0 h-[calc(100%-2.5rem)] z-10" />
    <RightSidebar v-if="store.selectedObjects" />
  </div>
</template>
<script setup lang="ts">
import { useCanvasStore } from "@floorplan/stores/canvasStore";

const store = useCanvasStore();
</script>
