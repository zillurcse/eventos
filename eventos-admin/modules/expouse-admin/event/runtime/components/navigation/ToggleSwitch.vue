<script setup lang="ts">
const props = defineProps<{
  modelValue: boolean
  label:      string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
}>()
</script>

<template>
  <button
    type="button"
    class="flex w-full items-center justify-between gap-4 bg-transparent border-0 cursor-pointer py-2.5 px-0 text-left group"
    @click="emit('update:modelValue', !modelValue)"
  >
    <span class="text-[.9rem] font-semibold text-ink group-hover:text-brand transition-colors">{{ label }}</span>
    <span
      class="relative w-10 h-[22px] rounded-full shrink-0 transition-colors duration-150"
      :class="modelValue ? 'bg-brand' : 'bg-[#cdd2dc]'"
    >
      <i
        class="absolute top-[3px] left-[3px] w-4 h-4 rounded-full bg-white transition-transform duration-150 shadow-sm"
        :class="modelValue ? 'translate-x-[18px]' : 'translate-x-0'"
      />
    </span>
  </button>
</template>
