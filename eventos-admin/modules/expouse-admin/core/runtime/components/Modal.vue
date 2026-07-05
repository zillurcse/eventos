<script setup lang="ts">
defineProps<{ title?: string, size?: 'lg' }>()
const emit = defineEmits<{ (e: 'close'): void }>()
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div class="modal" :class="size">
      <h2 v-if="title">{{ title }}</h2>
      <slot />
    </div>
  </div>
</template>
