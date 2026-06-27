<template>
  <div
    class="relative h-0 overflow-visible z-20 transition-opacity duration-100 pointer-events-none"
    :class="show ? 'opacity-100' : 'opacity-0'"
  >
    <div class="absolute inset-x-0 top-0 h-[3px] bg-[#6352e7] rounded-full shadow-[0_0_0_4px_rgba(99,82,231,0.15)]">
      <div class="absolute -left-0.5 -top-[4.5px] w-3 h-3 rounded-full bg-[#6352e7] border-2 border-white shadow" />
      <div class="absolute -right-0.5 -top-[4.5px] w-3 h-3 rounded-full bg-[#6352e7] border-2 border-white shadow" />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ show: boolean }>()
</script>
