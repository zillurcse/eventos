<script setup lang="ts">
defineProps<{
  label: string
  modelValue: string
  placeholder?: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: string): void
}>()
</script>

<template>
  <div>
    <label class="block mb-2">{{ label }}</label>
    <div class="flex items-center gap-2.5">
      <label class="relative cursor-pointer shrink-0">
        <span
          class="block w-10 h-10 rounded-xl border-2 border-white shadow-md transition-transform hover:scale-105"
          :style="`background:${modelValue}`"
        />
        <input
          :value="modelValue"
          type="color"
          class="absolute inset-0 opacity-0 w-full h-full cursor-pointer border-0 p-0 m-0"
          @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        >
      </label>
      <input
        :value="modelValue"
        class="w-[116px] font-mono text-[.88rem]"
        :placeholder="placeholder"
        @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      >
    </div>
  </div>
</template>
