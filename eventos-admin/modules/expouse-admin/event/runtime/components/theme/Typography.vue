<script setup lang="ts">
defineProps<{
  fontFamily: string
}>()

defineEmits<{
  (e: 'update:fontFamily', v: string): void
}>()

const FONTS = ['Inter', 'Roboto', 'Poppins', 'Nunito', 'Open Sans', 'Lato', 'Montserrat', 'Raleway', 'Source Sans Pro', 'Work Sans']
</script>

<template>
  <div class="card">
    <div class="flex items-center gap-2.5 mb-5">
      <div class="w-7 h-7 rounded-lg bg-brand-soft grid place-items-center shrink-0">
        <span class="text-brand font-bold text-[.8rem] leading-none">Aa</span>
      </div>
      <div>
        <h2 class="mb-0!">Typography</h2>
        <p class="text-[.8rem] text-muted mt-0.5">Select the font family used throughout your event site.</p>
      </div>
    </div>

    <label class="block mb-2">Font Family</label>
    <select
      :value="fontFamily"
      class="max-w-70"
      :style="{ fontFamily }"
      @change="$emit('update:fontFamily', ($event.target as HTMLSelectElement).value)"
    >
      <option v-for="f in FONTS" :key="f" :value="f" :style="{ fontFamily: f }">{{ f }}</option>
    </select>
    <p class="muted text-[.82rem] mt-2 mb-0">Font is loaded from Google Fonts on the attendee website.</p>
  </div>
</template>
