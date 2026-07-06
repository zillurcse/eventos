<script setup lang="ts">
defineProps<{
  primary: string
  accent: string
  saved?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:primary', v: string): void
  (e: 'update:accent', v: string): void
}>()

const PRESETS = [
  { label: 'Indigo',  primary: '#6352e7', accent: '#4f46e5' },
  { label: 'Blue',    primary: '#2563eb', accent: '#1d4ed8' },
  { label: 'Emerald', primary: '#059669', accent: '#047857' },
  { label: 'Rose',    primary: '#e11d48', accent: '#be123c' },
  { label: 'Amber',   primary: '#d97706', accent: '#b45309' },
  { label: 'Purple',  primary: '#9333ea', accent: '#7e22ce' },
  { label: 'Teal',    primary: '#0d9488', accent: '#0f766e' },
  { label: 'Slate',   primary: '#475569', accent: '#334155' },
]

function applyPreset(p: typeof PRESETS[number]) {
  emit('update:primary', p.primary)
  emit('update:accent', p.accent)
}
</script>

<template>
  <div class="card">
    <!-- Section header -->
    <div class="flex items-center gap-2.5 mb-5">
      <div class="w-7 h-7 rounded-lg bg-brand-soft grid place-items-center shrink-0">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
          <circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/>
        </svg>
      </div>
      <div>
        <h2 class="mb-0!">
          Color Scheme
          <span v-if="saved" class="badge active ml-2">saved ✓</span>
        </h2>
        <p class="text-[.8rem] text-muted mt-0.5">Choose a preset or set custom brand colors.</p>
      </div>
    </div>

    <!-- Presets -->
    <div class="flex flex-wrap gap-2.5 mb-5">
      <button
        v-for="p in PRESETS" :key="p.label"
        type="button"
        class="flex items-center gap-2 px-3 py-1.5 rounded-full border text-sm font-medium transition-all duration-150"
        :class="primary === p.primary
          ? 'border-brand bg-brand-soft text-brand'
          : 'border-line bg-white text-muted hover:border-brand hover:text-brand'"
        @click="applyPreset(p)"
      >
        <span class="w-3.5 h-3.5 rounded-full shrink-0" :style="{ background: p.primary }" />
        {{ p.label }}
      </button>
    </div>

    <div class="flex items-end gap-6 flex-wrap">
      <!-- Primary -->
      <div class="flex-1 min-w-45">
        <label class="block mb-2">Primary</label>
        <div class="flex items-center gap-2.5">
          <label class="relative cursor-pointer shrink-0">
            <span class="block w-10 h-10 rounded-xl border-2 border-white shadow-md transition-transform hover:scale-105" :style="`background:${primary}`" />
            <input
              :value="primary"
              type="color"
              class="absolute inset-0 opacity-0 w-full h-full cursor-pointer border-0 p-0 m-0"
              @input="emit('update:primary', ($event.target as HTMLInputElement).value)"
            >
          </label>
          <input
            :value="primary"
            class="w-[116px] font-mono text-[.88rem]"
            placeholder="#6352e7"
            maxlength="9"
            @input="emit('update:primary', ($event.target as HTMLInputElement).value)"
          >
        </div>
      </div>

      <!-- Accent -->
      <div class="flex-1 min-w-45">
        <label class="block mb-2">Accent</label>
        <div class="flex items-center gap-2.5">
          <label class="relative cursor-pointer shrink-0">
            <span class="block w-10 h-10 rounded-xl border-2 border-white shadow-md transition-transform hover:scale-105" :style="`background:${accent}`" />
            <input
              :value="accent"
              type="color"
              class="absolute inset-0 opacity-0 w-full h-full cursor-pointer border-0 p-0 m-0"
              @input="emit('update:accent', ($event.target as HTMLInputElement).value)"
            >
          </label>
          <input
            :value="accent"
            class="w-[116px] font-mono text-[.88rem]"
            placeholder="#4f46e5"
            maxlength="9"
            @input="emit('update:accent', ($event.target as HTMLInputElement).value)"
          >
        </div>
      </div>

      <!-- Preview -->
      <div class="flex-1 min-w-45">
        <label class="block mb-2">Preview</label>
        <div class="h-10 rounded-xl shadow-sm" :style="`background:linear-gradient(135deg,${primary},${accent})`" />
      </div>
    </div>
  </div>
</template>
