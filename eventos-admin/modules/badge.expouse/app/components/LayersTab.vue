<template>
  <div class="space-y-2">
    <ul>
      <li
        v-for="(layer, index) in layers"
        :key="index"
        @click="$emit('update:selected-layer', index)"
        class="p-2 rounded cursor-pointer hover:bg-gray-200"
        :class="{ 'bg-blue-200': selectedLayer === index }"
      >
        <div class="flex items-center justify-between">
          <span>{{ layer.name }} ({{ layer.type }})</span>
          <button
            @click.stop="$emit('toggle-layer-visibility', index)"
            class="text-blue-500"
          >
            <iconify-icon
              :icon="layer.visible ? 'mdi:eye' : 'mdi:eye-off'"
              class="w-5 h-5"
              aria-hidden="true"
            ></iconify-icon>
          </button>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { defineProps, defineEmits } from "vue";

defineProps({
  layers: Array,
  selectedLayer: [Number, null],
});

defineEmits(["update:selected-layer", "toggle-layer-visibility"]);
</script>
