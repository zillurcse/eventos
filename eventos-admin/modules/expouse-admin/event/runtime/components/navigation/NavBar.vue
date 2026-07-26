<script setup lang="ts">
const props = defineProps<{
  navBar: { icons: boolean; labels: boolean }
}>()

const emit = defineEmits<{
  (e: 'change'): void
}>()
</script>

<template>
  <div class="px-6 py-6">
    <h2 class="text-[1.05rem] font-bold text-ink mb-1">Navigation</h2>
    <p class="text-[.85rem] text-muted mb-4">Select your desired theme color option for your web app.</p>

    <div class="flex flex-wrap gap-x-8 gap-y-3">
      <AppCheckbox
        :model-value="navBar.icons"
        label="Show icons in nav bar"
        @update:model-value="navBar.icons = $event; emit('change')"
      />
      <AppCheckbox
        :model-value="navBar.labels"
        label="Show labels in nav bar"
        @update:model-value="navBar.labels = $event; emit('change')"
      />
    </div>
  </div>
</template>
