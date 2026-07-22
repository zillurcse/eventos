<script setup lang="ts">
import { computed } from 'vue'
import type { FormDesign } from '../../utils/profileFormTypes'

/**
 * Appearance controls for the shared / embedded public form. Mutates the
 * design object in place; the builder page owns saving and dirty-tracking.
 *
 * `brand_color` deliberately supports "inherit": left unset, the form follows
 * the event's primary colour, so a rebrand carries automatically instead of
 * leaving stale hexes on every form.
 */
const props = defineProps<{ design: FormDesign, eventPrimary: string, eventId: string }>()

const PRESETS = [
  { label: 'Cloud', value: '#f1f3f9' },
  { label: 'White', value: '#ffffff' },
  { label: 'Sand', value: '#f6f1e9' },
  { label: 'Mint', value: '#eaf6f0' },
  { label: 'Blush', value: '#fdeef1' },
  { label: 'Slate', value: '#1e293b' },
]

const usingBrand = computed({
  get: () => props.design.brand_color !== null,
  set: (v: boolean) => { props.design.brand_color = v ? props.eventPrimary : null },
})

const effectiveBrand = computed(() => props.design.brand_color || props.eventPrimary)
</script>

<template>
  <div class="card">
    <h3 class="m-0 text-[1.02rem] font-bold text-ink">Design</h3>
    <p class="muted text-[.8rem] mt-1 mb-4 leading-relaxed">
      How the public form looks when you share its link. Embedded copies keep a
      transparent background so they blend into your own site.
    </p>

    <!-- ── Background ─────────────────────────────────────────── -->
    <div class="border-t border-line pt-4">
      <div class="font-bold text-[.92rem] mb-2.5">Background</div>

      <div class="flex items-center gap-2 mb-3.5">
        <button
          v-for="t in (['color', 'image'] as const)" :key="t"
          class="flex-1 px-3 py-1.5 rounded-lg text-[.8rem] font-semibold cursor-pointer border transition-colors duration-150"
          :class="design.background_type === t ? 'bg-[#6352e7] text-white border-[#6352e7]' : 'bg-white text-[#5f6b7a] border-line hover:border-[#6352e7]'"
          @click="design.background_type = t"
        >{{ t === 'color' ? 'Solid colour' : 'Image' }}</button>
      </div>

      <template v-if="design.background_type === 'color'">
        <div class="flex flex-wrap gap-2 mb-3">
          <button
            v-for="p in PRESETS" :key="p.value"
            class="w-9 h-9 rounded-lg cursor-pointer transition-transform duration-150 hover:scale-110"
            :class="design.background_color.toLowerCase() === p.value ? 'border-2 border-[#6352e7] shadow-[0_0_0_2px_#fff_inset]' : 'border border-[#d7dae1]'"
            :style="{ background: p.value }"
            :title="p.label"
            @click="design.background_color = p.value"
          />
        </div>
        <div class="flex items-center gap-2">
          <input v-model="design.background_color" type="color" class="w-11 h-9 m-0 p-0.5 border border-line rounded-lg cursor-pointer bg-white">
          <input v-model="design.background_color" class="m-0 flex-1 font-mono text-[.82rem]" placeholder="#f1f3f9" maxlength="7">
        </div>
      </template>

      <template v-else>
        <ImageField
          :model-value="design.background_image_url"
          collection="form_background"
          :gallery-path="`/events/${eventId}/gallery`"
          :aspect="16 / 9"
          card-width="260px"
          hint="Upload a new image or pick one from the event gallery. Wide, low-contrast images work best — the form card sits on top."
          @update:model-value="design.background_image_url = ($event as string | null)"
        />
        <p v-if="!design.background_image_url" class="muted text-[.76rem] mt-2 mb-0">
          No image chosen yet — the form falls back to the solid colour.
        </p>
      </template>
    </div>

    <!-- ── Form card ──────────────────────────────────────────── -->
    <div class="border-t border-line pt-4 mt-4">
      <div class="font-bold text-[.92rem] mb-2.5">Form card</div>
      <div class="flex items-center gap-2">
        <button
          v-for="s in (['solid', 'glass'] as const)" :key="s"
          class="flex-1 px-3 py-1.5 rounded-lg text-[.8rem] font-semibold cursor-pointer border transition-colors duration-150"
          :class="design.card_style === s ? 'bg-[#6352e7] text-white border-[#6352e7]' : 'bg-white text-[#5f6b7a] border-line hover:border-[#6352e7]'"
          @click="design.card_style = s"
        >{{ s === 'solid' ? 'Solid white' : 'Frosted glass' }}</button>
      </div>
      <p class="muted text-[.76rem] mt-2 mb-0">Frosted glass lets a background image show through the card.</p>
    </div>

    <!-- ── Accent colour ──────────────────────────────────────── -->
    <div class="border-t border-line pt-4 mt-4">
      <div class="font-bold text-[.92rem] mb-2.5">Accent colour</div>
      <label class="flex items-center gap-2 cursor-pointer select-none text-[.86rem] font-medium mb-3">
        <input v-model="usingBrand" type="checkbox" class="w-4 h-4 m-0 accent-[#6352e7]">
        Override the event colour
      </label>
      <div v-if="usingBrand" class="flex items-center gap-2">
        <input v-model="design.brand_color" type="color" class="w-11 h-9 m-0 p-0.5 border border-line rounded-lg cursor-pointer bg-white">
        <input v-model="design.brand_color" class="m-0 flex-1 font-mono text-[.82rem]" placeholder="#6352e7" maxlength="7">
      </div>
      <p v-else class="muted text-[.76rem] mt-0 mb-0">
        Following the event colour
        <span class="inline-block w-3 h-3 rounded-sm align-middle ml-0.5" :style="{ background: effectiveBrand }" />
        — a rebrand updates this form automatically.
      </p>
    </div>

    <!-- ── Header ─────────────────────────────────────────────── -->
    <div class="border-t border-line pt-4 mt-4">
      <div class="flex items-center justify-between gap-3">
        <span class="text-[.9rem] font-semibold">Show event header</span>
        <button
          type="button" role="switch" :aria-checked="design.show_header"
          class="w-10 h-[22px] rounded-full border-none cursor-pointer transition-colors duration-150 relative shrink-0"
          :class="design.show_header ? 'bg-[#6352e7]' : 'bg-[#d7dae1]'"
          @click="design.show_header = !design.show_header"
        >
          <span class="absolute top-[3px] w-4 h-4 rounded-full bg-white transition-[left] duration-150" :style="{ left: design.show_header ? '21px' : '3px' }" />
        </button>
      </div>
      <p class="muted text-[.76rem] mt-1.5 mb-0">The event cover, logo, name and dates above the form.</p>
    </div>
  </div>
</template>
