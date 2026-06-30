<!-- ColorPickerModal.vue -->
<template>
  <div
    v-if="show"
    class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
  >
    <div class="bg-white rounded-lg p-6 w-96">
      <h2 class="text-lg font-semibold mb-4">Select Color</h2>
      <input
        type="color"
        v-model="selectedColor"
        class="w-full h-16 rounded cursor-pointer"
      />
      <div class="mt-6 flex justify-end space-x-2">
        <button
          class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
          @click="close"
        >
          Cancel
        </button>
        <button
          class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
          @click="selectColor"
        >
          Apply
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";

defineProps({
  show: Boolean,
});

const emit = defineEmits(["selected", "close"]);

const selectedColor = ref("#ffffff");

function selectColor() {
  emit("selected", selectedColor.value);
  emit("close");
}

function close() {
  emit("close");
}
</script>

<style scoped>
/* Additional styling for color picker */
</style>
