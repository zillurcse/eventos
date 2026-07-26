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
  <div>
    <!-- Section header -->
    <h2 class="text-[1.05rem] font-bold text-ink mb-1">Appearance</h2>
    <p class="text-[.85rem] text-muted mb-4">Customise the look, feel and color of your event on mobile and web apps.</p>

    <div class="grid grid-cols-3 gap-5 max-w-2xl">
      <button
        v-for="o in OPTIONS" :key="o.value" type="button"
        class="rounded-2xl border-[1.5px] p-2.5 text-left transition-all duration-150 cursor-pointer"
        :class="modelValue === o.value ? 'border-brand' : 'border-line hover:border-[#c7c2f5]'"
        @click="emit('update:modelValue', o.value)"
      >
        <div class="rounded-lg bg-[#F0EEFD]/60 p-2.5">
          <div class="h-2.5 rounded-full bg-white mb-2.5" style="width:55%" />
          <div class="h-16 rounded-lg bg-white mb-2.5" />
          <div class="flex gap-2">
            <div class="h-9 rounded-lg bg-white flex-1" />
            <div class="h-9 rounded-lg bg-white flex-1" />
          </div>
        </div>
        <p class="text-[.9rem] mb-0 mt-3 text-center" :class="modelValue === o.value ? 'text-brand font-semibold' : 'text-ink font-medium'">{{ o.label }}</p>
      </button>
    </div>
  </div>
</template>
