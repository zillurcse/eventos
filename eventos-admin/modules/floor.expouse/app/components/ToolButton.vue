<template>
  <button
    @click="$emit('click')"
    :class="[
      'w-10 h-10 flex items-center justify-center rounded-lg transition-colors relative group',
      isActive
        ? 'bg-blue-100 text-blue-600 border border-blue-200'
        : 'bg-gray-50 hover:bg-gray-100 text-gray-600',
    ]"
  >
    <NuxtIcon :name="tool.icon" class="text-2xl" />
    <div
      class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-50"
    >
      {{ tool.name }}
    </div>
  </button>
</template>

<script setup lang="ts">
defineProps<{
  tool: any;
  isActive: boolean;
}>();

defineEmits<{
  click: [];
}>();
</script>
