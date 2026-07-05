<script setup lang="ts">
defineProps<{
  coverUrl: string | null
  logoUrl:  string | null
}>()

const emit = defineEmits<{
  (e: 'coverUploaded', v: { id: number; url: string }): void
  (e: 'logoUploaded',  v: { url: string | null }): void
}>()

function onLogoChange(v: string | string[] | null) {
  emit('logoUploaded', { url: Array.isArray(v) ? v[0] ?? null : v })
}
</script>

<template>
  <div class="card">
    <!-- Section header -->
    <div class="flex items-center gap-2.5 mb-5">
      <div class="w-7 h-7 rounded-lg bg-brand-soft grid place-items-center shrink-0">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
          <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9l5-5 4 4 3-3 6 6"/><circle cx="8.5" cy="8.5" r="1.5"/>
        </svg>
      </div>
      <div>
        <h2 class="mb-0!">Logo &amp; Cover</h2>
        <p class="text-[.8rem] text-muted mt-0.5">Images shown across the event app and event cards.</p>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-6">
      <!-- Cover -->
      <div>
        <label class="block mb-1.5">Cover image</label>
        <p class="text-[.8rem] text-muted mb-3">Displayed on event cards and the event page hero.</p>
        <ImageField
          :model-value="coverUrl"
          :aspect="1200 / 630"
          :output-width="1200"
          :output-height="630"
          collection="cover"
          hint="1200×630px recommended"
          :removable="false"
          @uploaded="emit('coverUploaded', $event)"
        />
      </div>
      <!-- Logo -->
      <div>
        <label class="block mb-1.5">Logo</label>
        <p class="text-[.8rem] text-muted mb-3">Your event logo shown in the app header.</p>
        <ImageField
          :model-value="logoUrl"
          :aspect="1"
          :output-width="512"
          :output-height="512"
          collection="logo"
          hint="512×512px recommended"
          @update:model-value="onLogoChange"
        />
      </div>
    </div>
  </div>
</template>
