<script setup lang="ts">
const props = defineProps<{
  modules: Record<string, boolean>
  list: { key: string; label: string }[]
}>()

const emit = defineEmits<{
  (e: 'change'): void
}>()
</script>

<template>
  <div class="px-6 py-6">
    <h2 class="text-[1.05rem] font-bold text-ink mb-1">Modules</h2>
    <p class="text-[.85rem] text-muted mb-4">Select your desired theme color option for your web app.</p>

    <div class="flex flex-wrap gap-x-8 gap-y-3">
      <AppCheckbox
        v-for="m in list" :key="m.key"
        :model-value="modules[m.key]"
        :label="m.label"
        @update:model-value="modules[m.key] = $event; emit('change')"
      />
    </div>
  </div>
</template>
