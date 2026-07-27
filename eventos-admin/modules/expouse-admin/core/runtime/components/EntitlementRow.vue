<script setup lang="ts">
// A single togglable entitlement row: checkbox + optional quantity stepper.
// Shared by any drawer that edits a { key, enabled, limit } feature line
// (exhibitor package builder, exhibitor permissions editor, ...).
interface FeatureLine { key: string, enabled: boolean, limit: number }

const props = defineProps<{
  modelValue: FeatureLine
  label: string
  countable?: boolean
  isFeatures: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: FeatureLine): void
}>()

function toggle(enabled: boolean) {
  const next = { ...props.modelValue, enabled }
  if (enabled && props.countable !== false && next.limit < 1) next.limit = 1
  emit('update:modelValue', next)
}
function setLimit(limit: number) {
  emit('update:modelValue', { ...props.modelValue, limit: Math.max(0, limit) })
}
</script>

<template>
  <div
    class="flex items-center gap-3 px-4 py-2.5 max-h-12 min-h-12 border border-[#D1D2DE] rounded-lg " 
    :class="{ 'bg-[#F7F7FB] border-brand/20': modelValue.enabled }"
  >
    <AppCheckbox
      :model-value="modelValue.enabled"
      :label="label"
      class="flex-1 [&_span]:font-medium"
      @update:model-value="toggle"
    />
    <div
      v-if="countable !== false"
      class="flex items-center shrink-0 max-h-7 border border-[#d7dae1] rounded-sm overflow-hidden bg-white"
      :class="{ 'opacity-50': !modelValue.enabled }"
    >
      <button
        type="button"
        class="w-7 h-7 flex items-center justify-center text-[1.1rem] leading-none text-muted border-0 bg-transparent cursor-pointer disabled:cursor-not-allowed"
        :disabled="!modelValue.enabled"
        :aria-label="`Decrease ${label} limit`"
        @click="setLimit(modelValue.limit - 1)"
      >
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="2" viewBox="0 0 12 2" fill="none">
          <path d="M11.25 0H0.75C0.551088 0 0.360322 0.0790178 0.21967 0.21967C0.0790178 0.360322 0 0.551088 0 0.75C0 0.948912 0.0790178 1.13968 0.21967 1.28033C0.360322 1.42098 0.551088 1.5 0.75 1.5H11.25C11.4489 1.5 11.6397 1.42098 11.7803 1.28033C11.921 1.13968 12 0.948912 12 0.75C12 0.551088 11.921 0.360322 11.7803 0.21967C11.6397 0.0790178 11.4489 0 11.25 0Z" fill="#64676A"/>
      </svg>
    </button>
      <span class="w-7 h-7 flex items-center justify-center text-sm font-semibold border-x border-[#d7dae1] select-none">{{ modelValue.limit }}</span>
      <button
        type="button"
        class="w-9 h-9 flex items-center justify-center text-[1.1rem] text-muted border-0 bg-transparent cursor-pointer disabled:cursor-not-allowed"
        :disabled="!modelValue.enabled"
        :aria-label="`Increase ${label} limit`"
        @click="setLimit(modelValue.limit + 1)"
      >
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none">
        <path d="M11.25 5.25H6.75V0.75C6.75 0.551088 6.67098 0.360322 6.53033 0.21967C6.38968 0.0790178 6.19891 0 6 0C5.80109 0 5.61032 0.0790178 5.46967 0.21967C5.32902 0.360322 5.25 0.551088 5.25 0.75V5.25H0.75C0.551088 5.25 0.360322 5.32902 0.21967 5.46967C0.0790178 5.61032 0 5.80109 0 6C0 6.19891 0.0790178 6.38968 0.21967 6.53033C0.360322 6.67098 0.551088 6.75 0.75 6.75H5.25V11.25C5.25 11.4489 5.32902 11.6397 5.46967 11.7803C5.61032 11.921 5.80109 12 6 12C6.19891 12 6.38968 11.921 6.53033 11.7803C6.67098 11.6397 6.75 11.4489 6.75 11.25V6.75H11.25C11.4489 6.75 11.6397 6.67098 11.7803 6.53033C11.921 6.38968 12 6.19891 12 6C12 5.80109 11.921 5.61032 11.7803 5.46967C11.6397 5.32902 11.4489 5.25 11.25 5.25Z" fill="#64676A"/>
      </svg>
    </button>
    </div>
  </div>
</template>
