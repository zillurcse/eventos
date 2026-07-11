<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'

const props = defineProps<{
  modelValue?:    boolean
  label?:         string
  description?:   string
  disabled?:      boolean
  indeterminate?: boolean
  error?:         boolean
  size?:          'sm' | 'md'
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
}>()

const input = ref<HTMLInputElement | null>(null)

watch(() => props.indeterminate, (v) => { if (input.value) input.value.indeterminate = !!v }, { immediate: true })
onMounted(() => { if (input.value) input.value.indeterminate = !!props.indeterminate })

function onChange(e: Event) {
  emit('update:modelValue', (e.target as HTMLInputElement).checked)
}

// Box appearance is state-driven (checked/indeterminate, error, disabled) — kept out of the
// template as a computed so the markup doesn't turn into a nested-ternary soup.
const boxClass = computed(() => {
  const active = props.modelValue || props.indeterminate
  if (props.disabled) {
    return active ? 'border-brand/35 bg-brand/35' : 'border-line bg-bg'
  }
  if (active) {
    return 'border-brand bg-brand shadow-[0_1px_2px_rgba(79,70,229,.28)]'
  }
  if (props.error) {
    return 'border-[#dc2626] bg-white group-hover:border-[#b91c1c]'
  }
  return 'border-[#d7dae1] bg-white group-hover:border-brand/55 group-hover:bg-brand-soft/40'
})

const ringClass = computed(() => props.error
  ? 'peer-focus-visible:shadow-[0_0_0_3px_rgba(220,38,38,.14)]'
  : 'peer-focus-visible:shadow-[0_0_0_3px_var(--brand-soft)]')
</script>

<template>
  <label
    class="inline-flex items-start gap-2.5 cursor-pointer select-none group"
    :class="disabled ? 'opacity-50 cursor-not-allowed' : ''"
  >
    <span class="relative inline-flex shrink-0" :class="size === 'sm' ? 'w-5 h-5 mt-0.5' : 'w-4.5 h-4.5 mt-0.5'">
      <input
        ref="input"
        type="checkbox"
        :checked="modelValue"
        :disabled="disabled"
        class="peer absolute inset-0 z-10 w-full h-full m-0 opacity-0 cursor-pointer disabled:cursor-not-allowed"
        @change="onChange"
      >
      <span
        class="pointer-events-none absolute inset-0 rounded-sm border flex items-center justify-center transition-all duration-180 ease-out"
        :class="[boxClass, ringClass]"
      >
        <svg v-if="indeterminate && !modelValue" viewBox="0 0 16 16" fill="none" class="w-3 h-3">
          <path d="M4 8h8" stroke="white" stroke-width="2" stroke-linecap="round" />
        </svg>
        <svg v-else viewBox="0 0 16 16" fill="none" class="w-3 h-3 overflow-visible">
          <path
            d="M3.5 8.2 6.4 11l6-6.6"
            stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            pathLength="1"
            class="transition-[stroke-dashoffset] duration-180 ease-out"
            :style="{ strokeDasharray: 1, strokeDashoffset: modelValue ? 0 : 1, transitionDelay: modelValue ? '45ms' : '0ms' }"
          />
        </svg>
      </span>
    </span>
    <div v-if="label">
      <span class="block font-medium text-ink text-[.93rem] leading-snug">{{ label }}</span>
      <span v-if="description" class="block text-[.8rem] mt-0.5" :class="error ? 'text-[#dc2626]' : 'text-muted'">{{ description }}</span>
    </div>
  </label>
</template>
