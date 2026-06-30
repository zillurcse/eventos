<script setup lang="ts">
const props = defineProps<{
  emailHeaderUrl: string | null
}>()

const emit = defineEmits<{
  (e: 'uploaded', v: { url: string }): void
}>()
</script>

<template>
  <div class="card">
    <!-- Section header -->
    <div class="flex items-center gap-2.5 mb-1.5">
      <div class="w-7 h-7 rounded-lg bg-brand-soft grid place-items-center shrink-0">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
          <path d="M17 20.5H7c-3 0-5-1.5-5-5v-7c0-3.5 2-5 5-5h10c3 0 5 1.5 5 5v7c0 3.5-2 5-5 5z"/>
          <path d="m17 9-3.13 2.5c-1.03.82-2.72.82-3.75 0L7 9"/>
        </svg>
      </div>
      <div>
        <h2 class="mb-0!">Email Header</h2>
        <p class="text-[.8rem] text-muted mt-0.5">Header image shown at the top of all event emails.</p>
      </div>
    </div>

    <!-- Preview if image exists -->
    <div v-if="emailHeaderUrl" class="mt-4 mb-4 relative group rounded-xl overflow-hidden border border-line" style="max-width:440px;height:130px">
      <img :src="emailHeaderUrl" alt="Email header" class="w-full h-full object-cover">
      <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors" />
    </div>

    <!-- Upload -->
    <div class="mt-4 max-w-[440px]">
      <UploadButton
        :preview="emailHeaderUrl"
        collection="email_header"
        @uploaded="emit('uploaded', $event)"
      />
    </div>
  </div>
</template>
