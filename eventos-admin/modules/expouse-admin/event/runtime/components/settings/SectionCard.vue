<script setup lang="ts">
defineProps<{ title: string, subtitle?: string }>()
</script>

<template>
  <div class="card">
    <div class="flex items-center gap-3 mb-4">
      <div class="w-9 h-9 rounded-xl bg-brand-soft grid place-items-center shrink-0 text-brand">
        <slot name="icon" />
      </div>
      <div>
        <p class="font-semibold text-[.95rem] text-ink mb-0.5">{{ title }}</p>
        <p v-if="subtitle" class="text-[.82rem] text-muted">{{ subtitle }}</p>
      </div>
    </div>
    <slot />
  </div>
</template>
