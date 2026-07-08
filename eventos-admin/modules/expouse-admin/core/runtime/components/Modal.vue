<script setup lang="ts">
defineProps<{ title?: string, subtitle?: string, size?: 'lg' }>()
const emit = defineEmits<{ (e: 'close'): void }>()
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div class="modal relative" :class="size">
      <button
        type="button"
        class="absolute top-4 right-4 w-8 h-8 rounded-lg grid place-items-center text-muted hover:text-ink hover:bg-[#f1f1f5] transition-colors border-0 bg-transparent cursor-pointer"
        title="Close"
        @click="emit('close')"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
      <h2 v-if="title" class="pr-8" :class="subtitle ? 'mb-0' : ''">{{ title }}</h2>
      <p v-if="subtitle" class="text-[.85rem] text-muted pr-8 mb-0">{{ subtitle }}</p>
      <slot />
    </div>
  </div>
</template>
