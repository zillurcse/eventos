<script setup lang="ts">
defineProps<{
  modelValue?:  boolean
  label:        string
  description?: string
  disabled?:    boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
}>()
</script>

<template>
  <label
    class="flex items-start gap-3 cursor-pointer select-none"
    :class="disabled ? 'opacity-50 cursor-not-allowed' : ''"
  >
    <input
      type="checkbox"
      :checked="modelValue"
      :disabled="disabled"
      class="w-4.5 h-4.5 m-0 mt-0.5 accent-brand shrink-0"
      @change="emit('update:modelValue', ($event.target as HTMLInputElement).checked)"
    >
    <div>
      <span class="block font-medium text-ink text-[.93rem] leading-snug">{{ label }}</span>
      <span v-if="description" class="block text-muted text-[.8rem] mt-0.5">{{ description }}</span>
    </div>
  </label>
</template>
