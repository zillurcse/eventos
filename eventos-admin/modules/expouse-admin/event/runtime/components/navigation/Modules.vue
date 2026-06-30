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
  <div class="card">
    <div class="flex items-center gap-3 mb-4">
      <div class="w-9 h-9 rounded-xl bg-brand-soft grid place-items-center shrink-0">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
          <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
          <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
        </svg>
      </div>
      <div>
        <p class="font-semibold text-[.95rem] text-ink mb-0.5">Modules</p>
        <p class="text-[.82rem] text-muted">Toggle which modules are visible in your event app.</p>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-x-6 gap-y-1.5 pt-3 border-t border-line">
      <label
        v-for="m in list" :key="m.key"
        class="flex items-center gap-2.5 py-2 cursor-pointer group select-none"
      >
        <!-- Toggle switch -->
        <span
          class="relative w-9 h-5 rounded-full shrink-0 transition-colors duration-150"
          :class="modules[m.key] ? 'bg-brand' : 'bg-[#cdd2dc]'"
        >
          <i
            class="absolute top-[3px] left-[3px] w-3.5 h-3.5 rounded-full bg-white transition-transform duration-150 shadow-sm"
            :class="modules[m.key] ? 'translate-x-4' : 'translate-x-0'"
          />
          <input
            :checked="modules[m.key]"
            type="checkbox"
            class="absolute inset-0 opacity-0 cursor-pointer w-full h-full m-0"
            @change="modules[m.key] = ($event.target as HTMLInputElement).checked; emit('change')"
          >
        </span>
        <span
          class="text-[.9rem] font-medium transition-colors"
          :class="modules[m.key] ? 'text-ink' : 'text-muted'"
        >{{ m.label }}</span>
      </label>
    </div>
  </div>
</template>
