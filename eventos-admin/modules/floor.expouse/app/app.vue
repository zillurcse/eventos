<script setup lang="ts">
import { useCanvasStore } from "@floorplan/stores/canvasStore";

const canvasStore = useCanvasStore();

onMounted(async () => {
  if (canvasStore.currentFloorId) {
    await canvasStore.loadFloorOnPageReload();
  }
});
</script>

<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>

<!-- <template>
  <NuxtLayout>
    <div class="h-screen bg-gray-100 flex flex-col">
      <Toolbar />
      <div class="flex flex-1 overflow-hidden">
        <Sidebar />
        <main class="flex-1 overflow-hidden">
          <NuxtPage />
        </main>
      </div>
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { onMounted } from "vue";
import { useCanvasStore } from "@floorplan/stores/canvasStore";

const store = useCanvasStore();

onMounted(() => {
  store.init();
});
</script> -->
