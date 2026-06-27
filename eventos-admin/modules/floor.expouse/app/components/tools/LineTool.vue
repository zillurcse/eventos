<template>
  <div>
    <!-- Tool logic is handled in useCanvasTools -->
  </div>
</template>

<script setup lang="ts">
import { useDrawingStore } from "@floorplan/stores/canvasStore";

const store = useDrawingStore();

// Line tool specific logic can be added here
// For example, line style options, arrow heads, etc.
</script>
