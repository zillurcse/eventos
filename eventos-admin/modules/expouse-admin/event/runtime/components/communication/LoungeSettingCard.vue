<script setup lang="ts">
defineProps<{
  title:        string
  description:  string
  modelValue?:  boolean // omit to render a plain (switch-less) card
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
}>()
</script>

<template>
  <div class="card">
    <div class="flex items-start justify-between gap-4">
      <div class="flex-1">
        <h3 class="font-bold text-base text-[#1a1a2e] m-0">{{ title }}</h3>
        <p class="muted text-[.86rem] mt-1 mb-0">{{ description }}</p>
        <div v-if="$slots.default" class="mt-3">
          <slot />
        </div>
      </div>
      <button
        v-if="modelValue !== undefined"
        type="button" role="switch" :aria-checked="modelValue"
        class="relative w-11 h-6 rounded-full shrink-0 transition-colors duration-150"
        :class="modelValue ? 'bg-brand' : 'bg-[#d1d5db]'"
        @click="emit('update:modelValue', !modelValue)"
      >
        <span
          class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform duration-150"
          :class="modelValue ? 'translate-x-5' : ''"
        />
      </button>
    </div>
  </div>
</template>
