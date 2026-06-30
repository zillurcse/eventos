<script setup lang="ts">
const props = defineProps<{
  primary: string
  accent: string
}>()

const emit = defineEmits<{
  (e: 'update:primary', v: string): void
  (e: 'update:accent', v: string): void
}>()
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
        <h2 class="mb-0!">Theme Color</h2>
        <p class="text-[.8rem] text-muted mt-0.5">Select your desired theme color for your web app.</p>
      </div>
    </div>

    <div class="flex items-end gap-6">
      <!-- Primary -->
      <div class="flex-1">
        <label class="block mb-2">Primary</label>
        <div class="flex items-center gap-2.5">
          <label class="relative cursor-pointer shrink-0">
            <span
              class="block w-10 h-10 rounded-xl border-2 border-white shadow-md transition-transform hover:scale-105"
              :style="`background:${primary}`"
            />
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
            @input="emit('update:primary', ($event.target as HTMLInputElement).value)"
          >
        </div>
      </div>

      <!-- Accent -->
      <div class="flex-1">
        <label class="block mb-2">Accent</label>
        <div class="flex items-center gap-2.5">
          <label class="relative cursor-pointer shrink-0">
            <span
              class="block w-10 h-10 rounded-xl border-2 border-white shadow-md transition-transform hover:scale-105"
              :style="`background:${accent}`"
            />
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
            placeholder="#22d3ee"
            @input="emit('update:accent', ($event.target as HTMLInputElement).value)"
          >
        </div>
      </div>

      <!-- Preview -->
      <div class="flex-1">
        <label class="block mb-2">Preview</label>
        <div
          class="h-10 rounded-xl shadow-sm"
          :style="`background:linear-gradient(135deg,${primary},${accent})`"
        />
      </div>
    </div>
  </div>
</template>
