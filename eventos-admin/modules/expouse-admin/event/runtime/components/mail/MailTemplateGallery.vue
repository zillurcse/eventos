<script setup lang="ts">
import { TEMPLATE_PRESETS } from '../../composables/useEmailBlocks'
import type { TemplatePreset } from '../../composables/useEmailBlocks'

const emit = defineEmits<{ (e: 'select', preset: TemplatePreset): void, (e: 'close'): void }>()

function accentStyle(preset: TemplatePreset) {
  return { background: preset.accent + '18', borderColor: preset.accent + '55', color: preset.accent }
}
</script>

<template>
  <div class="fixed inset-0 z-[200] bg-black/40 grid place-items-center p-4" @click.self="emit('close')">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-[860px] max-h-[88vh] flex flex-col overflow-hidden">
      <!-- header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-line shrink-0">
        <div>
          <h2 class="m-0 text-[1.1rem] font-bold">Choose a template</h2>
          <p class="m-0 text-[.82rem] text-[#8b93a7]">Pick a starting point — you can customise everything afterwards.</p>
        </div>
        <button class="w-9 h-9 rounded-lg border border-line grid place-items-center cursor-pointer hover:bg-[#f5f5fa] bg-white" @click="emit('close')">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#5f6b7a" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- grid -->
      <div class="overflow-y-auto p-6 grid grid-cols-[repeat(auto-fill,minmax(220px,1fr))] gap-4">
        <button
          v-for="preset in TEMPLATE_PRESETS"
          :key="preset.id"
          class="text-left rounded-xl border-2 overflow-hidden cursor-pointer transition-all hover:shadow-md hover:-translate-y-0.5 focus:outline-none"
          :style="{ borderColor: preset.accent + '55' }"
          @click="emit('select', preset)"
        >
          <!-- thumbnail -->
          <div class="h-[130px] flex flex-col items-center justify-center gap-2 p-4" :style="{ background: preset.accent + '12' }">
            <div v-if="preset.id === 'blank'" class="w-10 h-10 rounded-full border-2 border-dashed grid place-items-center" :style="{ borderColor: preset.accent }">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" :stroke="preset.accent" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            </div>
            <template v-else>
              <!-- mini email preview sketch -->
              <div class="w-full max-w-[160px] bg-white rounded-lg shadow-sm p-2 flex flex-col gap-1.5">
                <div class="h-2 rounded-full w-1/2 mx-auto" :style="{ background: preset.accent }"></div>
                <div class="h-10 rounded bg-[#e2e8f0]"></div>
                <div class="h-1.5 rounded-full bg-[#cbd5e1] w-4/5"></div>
                <div class="h-1.5 rounded-full bg-[#e2e8f0] w-3/5"></div>
                <div class="h-6 rounded-lg mx-auto w-3/5" :style="{ background: preset.accent }"></div>
                <div class="flex gap-1 justify-center mt-1">
                  <div v-for="n in 3" :key="n" class="w-3.5 h-3.5 rounded-full" :style="{ background: preset.accent + '60' }"></div>
                </div>
              </div>
            </template>
          </div>

          <!-- info -->
          <div class="p-3.5 bg-white">
            <div class="font-semibold text-[.9rem] text-[#1f2430]">{{ preset.name }}</div>
            <div class="text-[.76rem] text-[#8b93a7] mt-0.5">{{ preset.description }}</div>
          </div>
        </button>
      </div>
    </div>
  </div>
</template>
