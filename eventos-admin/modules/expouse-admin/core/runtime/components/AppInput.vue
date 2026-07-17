<script setup lang="ts">
const props = defineProps<{
  modelValue?:  string | number | null
  label?:       string
  type?:        string
  placeholder?: string
  required?:    boolean
  error?:       string
  hint?:        string
  disabled?:    boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: string | number): void
}>()

function onInput(e: Event) {
  const target = e.target as HTMLInputElement
  const val = props.type === 'number' ? target.valueAsNumber : target.value
  emit('update:modelValue', val)
}
</script>

<template>
  <div>
    <label v-if="label" class="block mb-1.5">
      {{ label }}<span v-if="required" class="text-[#dc2626] ml-0.5">*</span>
    </label>
    <div class="relative">
      <span v-if="$slots.prefix" class="absolute left-3 top-1/2 -translate-y-1/2 flex items-center pointer-events-none">
        <slot name="prefix" />
      </span>
      <input
        :type="type ?? 'text'"
        :value="modelValue ?? ''"
        :placeholder="placeholder"
        :disabled="disabled"
        v-bind="$attrs"
        class="m-0"
        :class="[
          error ? '!border-[#dc2626] focus:!border-[#dc2626] focus:![box-shadow:0_0_0_3px_#fee2e2]' : '',
          $slots.prefix ? 'pl-9' : '',
          $slots.suffix || type === 'date' || type === 'datetime-local' ? 'pr-9' : '',
        ]"
        @input="onInput"
      >
      <span
        v-if="$slots.suffix"
        class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center pointer-events-none text-faint"
      >
        <slot name="suffix" />
      </span>
      <span
        v-else-if="type === 'date' || type === 'datetime-local'"
        class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center pointer-events-none text-faint"
      >
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="4.5" width="18" height="16" rx="2.5"/><path d="M3 9.5h18M8 2.5v3.5M16 2.5v3.5"/>
        </svg>
      </span>
    </div>
    <p v-if="hint && !error" class="text-[.8rem] text-muted mt-1 mb-0">{{ hint }}</p>
    <p v-if="error" class="error mt-1 mb-0">{{ error }}</p>
  </div>
</template>

<style scoped>
input[type='date']::-webkit-calendar-picker-indicator,
input[type='datetime-local']::-webkit-calendar-picker-indicator {
  opacity: 0;
  cursor: pointer;
}
</style>
