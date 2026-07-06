<script setup lang="ts">
defineProps<{
  mode: 'light' | 'dark' | 'auto'
  headerStyle: 'solid' | 'transparent' | 'gradient'
  buttonRadius: 'rounded' | 'sharp' | 'pill'
}>()

const emit = defineEmits<{
  (e: 'update:mode', v: 'light' | 'dark' | 'auto'): void
  (e: 'update:headerStyle', v: 'solid' | 'transparent' | 'gradient'): void
  (e: 'update:buttonRadius', v: 'rounded' | 'sharp' | 'pill'): void
}>()

const MODES = [
  { v: 'light' as const, label: '☀ Light' },
  { v: 'dark' as const, label: '🌙 Dark' },
  { v: 'auto' as const, label: '⚙ Auto' },
]

const HEADER_STYLES = [
  { v: 'solid' as const, label: 'Solid' },
  { v: 'transparent' as const, label: 'Transparent' },
  { v: 'gradient' as const, label: 'Gradient' },
]

const BUTTON_RADII = [
  { v: 'rounded' as const, label: 'Rounded', cls: 'rounded-lg' },
  { v: 'sharp' as const, label: 'Sharp', cls: 'rounded-none' },
  { v: 'pill' as const, label: 'Pill', cls: 'rounded-full' },
]

function pillClass(active: boolean) {
  return active
    ? 'border-brand bg-brand-soft text-brand'
    : 'border-line bg-white text-muted hover:border-brand hover:text-brand'
}
</script>

<template>
  <div class="card">
    <div class="flex items-center gap-2.5 mb-5">
      <div class="w-7 h-7 rounded-lg bg-brand-soft grid place-items-center shrink-0">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
          <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>
        </svg>
      </div>
      <div>
        <h2 class="mb-0!">Appearance</h2>
        <p class="text-[.8rem] text-muted mt-0.5">Fine-tune how your site's chrome looks.</p>
      </div>
    </div>

    <div class="flex flex-col gap-5">
      <div>
        <label class="block mb-2">Color Mode</label>
        <div class="flex gap-2.5 flex-wrap">
          <button
            v-for="opt in MODES" :key="opt.v"
            type="button"
            class="px-4 py-2 rounded-lg border text-sm font-semibold transition-all duration-150"
            :class="pillClass(mode === opt.v)"
            @click="emit('update:mode', opt.v)"
          >{{ opt.label }}</button>
        </div>
      </div>

      <div>
        <label class="block mb-2">Header Style</label>
        <div class="flex gap-2.5 flex-wrap">
          <button
            v-for="opt in HEADER_STYLES" :key="opt.v"
            type="button"
            class="px-4 py-2 rounded-lg border text-sm font-semibold transition-all duration-150"
            :class="pillClass(headerStyle === opt.v)"
            @click="emit('update:headerStyle', opt.v)"
          >{{ opt.label }}</button>
        </div>
      </div>

      <div>
        <label class="block mb-2">Button Style</label>
        <div class="flex gap-2.5 flex-wrap">
          <button
            v-for="opt in BUTTON_RADII" :key="opt.v"
            type="button"
            class="px-4 py-2 border text-sm font-semibold transition-all duration-150"
            :class="[opt.cls, pillClass(buttonRadius === opt.v)]"
            @click="emit('update:buttonRadius', opt.v)"
          >{{ opt.label }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
