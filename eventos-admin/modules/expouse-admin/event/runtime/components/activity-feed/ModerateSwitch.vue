<script setup lang="ts">
/**
 * The MODERATE pill switch on Manage Activity Feed. Presentational — the page
 * owns the setting and persists it; this just reflects `on` and emits.
 */
defineProps<{ on: boolean, saving?: boolean }>()

defineEmits<{ (e: 'toggle'): void }>()
</script>

<template>
  <button
    type="button"
    class="flex items-center gap-2.5 select-none bg-white border border-line rounded-full pl-4 pr-1.5 py-1.5 cursor-pointer"
    :disabled="saving"
    :title="on ? 'Moderation is on. Posts need approval.' : 'Moderation is off. Posts publish instantly.'"
    @click="$emit('toggle')"
  >
    <span class="text-[.78rem] font-bold tracking-wide" :class="on ? 'text-[#16a34a]' : 'text-muted'">MODERATE</span>
    <span
      class="relative inline-block w-11 h-6 rounded-full transition-colors"
      :class="on ? 'bg-[#16a34a]' : 'bg-[#d3d5dd]'"
    >
      <span
        class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all"
        :class="on ? 'left-[22px]' : 'left-0.5'"
      />
    </span>
  </button>
</template>
