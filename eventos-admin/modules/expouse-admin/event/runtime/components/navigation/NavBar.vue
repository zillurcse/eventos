<script setup lang="ts">
const props = defineProps<{
  navBar: { icons: boolean; labels: boolean }
}>()

const emit = defineEmits<{
  (e: 'change'): void
}>()
</script>

<template>
  <div class="px-5 py-5">
    <div class="flex items-center gap-3 mb-4">
      <div class="w-9 h-9 rounded-xl bg-brand-soft grid place-items-center shrink-0">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
          <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 15h18"/><path d="M9 21V15"/>
        </svg>
      </div>
      <div>
        <p class="font-semibold text-[.95rem] text-ink mb-0.5">Navigation</p>
        <p class="text-[.82rem] text-muted">Control how the bottom navigation bar appears in your event app.</p>
      </div>
    </div>

    <div class="pt-3 border-t border-line divide-y divide-line">
      <NavigationToggleSwitch
        :model-value="navBar.icons"
        label="Show icons in nav bar"
        @update:model-value="navBar.icons = $event; emit('change')"
      />
      <NavigationToggleSwitch
        :model-value="navBar.labels"
        label="Show labels in nav bar"
        @update:model-value="navBar.labels = $event; emit('change')"
      />
    </div>
  </div>
</template>
