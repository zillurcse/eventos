<script setup lang="ts">
const props = defineProps<{
  label: string
  modelValue: string
  placeholder?: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: string): void
}>()

const hex = computed(() => (props.modelValue || '').replace(/^#/, ''))

function onTextInput(e: Event) {
  const v = (e.target as HTMLInputElement).value.replace(/^#/, '')
  emit('update:modelValue', v ? `#${v}` : '')
}
</script>

<template>
  <div>
    <label class="block mb-1.5">{{ label }}</label>
    <div class="flex items-center gap-2.5 h-12 px-3.5 bg-white border border-line rounded-lg">
      <span class="text-faint text-[.9rem] shrink-0">#</span>
      <input
        :value="hex"
        class="flex-1 min-w-0 border-0 p-0 m-0 h-full font-mono text-[.9rem] focus:outline-none"
        style="box-shadow:none"
        :placeholder="(placeholder || '').replace(/^#/, '')"
        @input="onTextInput"
      >
      <label class="relative shrink-0 cursor-pointer">
        <span
          class="block w-6 h-6 rounded-sm border border-line"
          :style="`background:${modelValue}`"
        />
        <input
          :value="modelValue"
          type="color"
          class="absolute inset-0 opacity-0 w-full h-full cursor-pointer border-0 p-0 m-0"
          @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        >
      </label>
    </div>
  </div>
</template>
