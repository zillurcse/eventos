<script setup lang="ts">
defineProps<{ modelValue: string }>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: string): void
}>()

/** Order: Advanced is the current eventos-event UI; Minimal & Modern follow. */
const OPTIONS = [
  { value: 'advanced', label: 'Advanced', hint: 'Full reception layout' },
  { value: 'minimal',  label: 'Minimal',  hint: 'Clean & compact' },
  { value: 'modern',   label: 'Modern',   hint: 'Soft & spacious' },
] as const
</script>

<template>
  <div>
    <h2 class="text-[1.05rem] font-bold text-ink mb-1">Appearance</h2>
    <p class="text-[.85rem] text-muted mb-4">Choose the layout theme for your event web app. Colors below apply to every theme.</p>

    <div class="grid grid-cols-3 gap-5 max-w-2xl">
      <button
        v-for="o in OPTIONS" :key="o.value" type="button"
        class="rounded-2xl border-[1.5px] p-2.5 text-left transition-all duration-150 cursor-pointer"
        :class="modelValue === o.value ? 'border-brand' : 'border-line hover:border-[#c7c2f5]'"
        @click="emit('update:modelValue', o.value)"
      >
        <!-- Distinct sketch per theme so organizers can tell them apart. -->
        <div
          class="p-2.5"
          :class="{
            'rounded-lg bg-[#F0EEFD]/60': o.value === 'advanced',
            'rounded-md bg-[#F4F4F5]': o.value === 'minimal',
            'rounded-xl bg-[#EEF6FF]': o.value === 'modern',
          }"
        >
          <template v-if="o.value === 'advanced'">
            <div class="h-2.5 rounded-full bg-white mb-2.5" style="width:55%" />
            <div class="h-16 rounded-lg bg-white mb-2.5" />
            <div class="flex gap-2">
              <div class="h-9 rounded-lg bg-white flex-1" />
              <div class="h-9 rounded-lg bg-white flex-1" />
            </div>
          </template>
          <template v-else-if="o.value === 'minimal'">
            <div class="h-2 rounded bg-white/90 mb-2" style="width:40%" />
            <div class="h-10 rounded bg-white mb-2" />
            <div class="space-y-1.5">
              <div class="h-3 rounded bg-white/80" />
              <div class="h-3 rounded bg-white/80" style="width:70%" />
            </div>
          </template>
          <template v-else>
            <div class="h-3 rounded-full bg-white mb-2.5 shadow-sm" style="width:50%" />
            <div class="h-14 rounded-xl bg-white mb-2.5 shadow-sm" />
            <div class="flex gap-2">
              <div class="h-8 rounded-xl bg-white flex-1 shadow-sm" />
              <div class="h-8 rounded-xl bg-white flex-1 shadow-sm" />
            </div>
          </template>
        </div>
        <p class="text-[.9rem] mb-0 mt-3 text-center" :class="modelValue === o.value ? 'text-brand font-semibold' : 'text-ink font-medium'">{{ o.label }}</p>
        <p class="text-[.72rem] text-muted text-center mb-0 mt-0.5">{{ o.hint }}</p>
      </button>
    </div>
  </div>
</template>
