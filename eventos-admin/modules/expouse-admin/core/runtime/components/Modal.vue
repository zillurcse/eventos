<script setup lang="ts">
defineProps<{ title?: string }>()
const emit = defineEmits<{ (e: 'close'): void }>()
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div class="modal">
      <h2 v-if="title">{{ title }}</h2>
      <slot />
    </div>
  </div>
</template>
