<script setup lang="ts">
defineProps<{ modelValue: string }>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: string): void
}>()

const OPTIONS = [
  { value: 'minimal',  label: 'Minimal'  },
  { value: 'modern',   label: 'Modern'   },
  { value: 'advanced', label: 'Advanced' },
] as const
</script>

<template>
  <div class="card">
    <!-- Section header -->
    <div class="flex items-center gap-2.5 mb-5">
      <div class="w-7 h-7 rounded-lg bg-brand-soft grid place-items-center shrink-0">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
          <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/>
        </svg>
      </div>
      <div>
        <h2 class="mb-0!">Appearance</h2>
        <p class="text-[.8rem] text-muted mt-0.5">Customise the look, feel and color of your event on mobile and web apps.</p>
      </div>
    </div>

    <div class="grid grid-cols-3 gap-4">
      <button
        v-for="o in OPTIONS" :key="o.value" type="button"
        class="rounded-2xl border-[1.5px] p-3 text-left transition-all duration-150 cursor-pointer"
        :class="modelValue === o.value ? 'border-brand ring-2 ring-brand-soft' : 'border-line hover:border-[#c7c2f5]'"
        @click="emit('update:modelValue', o.value)"
      >
        <div class="rounded-lg border border-line bg-[#fafbfc] p-2 mb-2.5">
          <div class="h-2.5 rounded bg-line mb-2" style="width:55%" />
          <div class="flex gap-1.5">
            <div v-if="o.value !== 'minimal'" class="h-9 rounded bg-line" :style="{ width: o.value === 'advanced' ? '28%' : '35%' }" />
            <div class="h-9 rounded bg-line flex-1" />
            <div v-if="o.value === 'advanced'" class="h-9 rounded bg-line" style="width:18%" />
          </div>
        </div>
        <p class="text-[.85rem] font-semibold mb-0 text-center" :class="modelValue === o.value ? 'text-brand' : 'text-ink'">{{ o.label }}</p>
      </button>
    </div>
  </div>
</template>
