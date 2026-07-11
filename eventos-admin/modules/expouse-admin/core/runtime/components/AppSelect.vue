<script setup lang="ts">
export interface SelectOption {
  value: string | number
  label: string
}

const props = defineProps<{
  modelValue?:  string | number | null
  label?:       string
  options?:     SelectOption[] | string[]
  placeholder?: string
  required?:    boolean
  error?:       string
  hint?:        string
  disabled?:    boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: string | number): void
}>()

function normalised(): SelectOption[] {
  if (!props.options) return []
  return props.options.map(o =>
    typeof o === 'string' ? { value: o, label: o } : o,
  )
}

function onChange(e: Event) {
  emit('update:modelValue', (e.target as HTMLSelectElement).value)
}
</script>

<template>
  <div>
    <label v-if="label" class="block mb-1.5">
      {{ label }}<span v-if="required" class="text-[#dc2626] ml-0.5">*</span>
    </label>
    <div class="w-full bg-white border border-[#d7dae1] rounded-[11px] px-[13px] py-2.5">
      <select
        :value="modelValue ?? ''"
        :disabled="disabled"
        class="m-0 w-full border-0 focus:outline-0"
        :class="error ? '!border-[#dc2626]' : ''"
        @change="onChange"
      >
        <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
        <!-- Named options via prop -->
        <option
          v-for="opt in normalised()"
          :key="opt.value"
          :value="opt.value"
        >{{ opt.label }}</option>
        <!-- Or pass <option> elements directly via default slot -->
        <slot />
      </select>
    </div>
    <p v-if="hint && !error" class="text-[.8rem] text-muted mt-1 mb-0">{{ hint }}</p>
    <p v-if="error" class="error mt-1 mb-0">{{ error }}</p>
  </div>
</template>
