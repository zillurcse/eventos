<script setup lang="ts">
defineProps<{
  modelValue?:  string | null
  label?:       string
  placeholder?: string
  rows?:        number
  required?:    boolean
  error?:       string
  hint?:        string
  disabled?:    boolean
  resize?:      'none' | 'vertical' | 'both'
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: string): void
}>()
</script>

<template>
  <div>
    <label v-if="label" class="block mb-1.5">
      {{ label }}<span v-if="required" class="text-[#dc2626] ml-0.5">*</span>
    </label>
    <textarea
      :value="modelValue ?? ''"
      :placeholder="placeholder"
      :rows="rows ?? 3"
      :disabled="disabled"
      class="m-0"
      :class="[
        error ? '!border-[#dc2626]' : '',
        resize === 'none'     ? 'resize-none'
        : resize === 'both'   ? 'resize'
        : 'resize-y',
      ]"
      @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    />
    <p v-if="hint && !error" class="text-[.8rem] text-muted mt-1 mb-0">{{ hint }}</p>
    <p v-if="error" class="error mt-1 mb-0">{{ error }}</p>
  </div>
</template>
