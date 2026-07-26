<script setup lang="ts">
defineProps<{
  eventId: string
  logoUrl: string | null
}>()

const emit = defineEmits<{
  (e: 'logoUploaded', v: { url: string | null }): void
}>()

function onLogoChange(v: string | string[] | null) {
  emit('logoUploaded', { url: Array.isArray(v) ? v[0] ?? null : v })
}
</script>

<template>
  <div>
    <!-- Section header -->
    <h2 class="text-[1.05rem] font-bold text-ink mb-1">Logo</h2>
    <p class="text-[.85rem] text-muted mb-4">Event logo that appears across your event.</p>

    <ImageField
      :model-value="logoUrl"
      :aspect="1"
      :output-width="512"
      :output-height="512"
      collection="logo"
      hint="512×512px recommended"
      :gallery-path="`/events/${eventId}/gallery`"
      @update:model-value="onLogoChange"
    />
  </div>
</template>
