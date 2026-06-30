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
    <input
      :type="type ?? 'text'"
      :value="modelValue ?? ''"
      :placeholder="placeholder"
      :disabled="disabled"
      class="m-0"
      :class="error ? '!border-[#dc2626] focus:!border-[#dc2626] focus:![box-shadow:0_0_0_3px_#fee2e2]' : ''"
      @input="onInput"
    >
    <p v-if="hint && !error" class="text-[.8rem] text-muted mt-1 mb-0">{{ hint }}</p>
    <p v-if="error" class="error mt-1 mb-0">{{ error }}</p>
  </div>
</template>
