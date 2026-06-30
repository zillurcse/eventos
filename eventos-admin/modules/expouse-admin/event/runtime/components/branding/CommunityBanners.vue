<script setup lang="ts">
const props = defineProps<{
  banners: string[]
}>()

const emit = defineEmits<{
  (e: 'add',    v: { url: string }): void
  (e: 'remove', i: number): void
}>()

const bannerKey = ref(0)

function onUploaded(v: { url: string }) {
  emit('add', v)
  bannerKey.value++
}
</script>

<template>
  <div class="card">
    <!-- Section header -->
    <div class="flex items-start justify-between gap-4 mb-1.5">
      <div class="flex items-center gap-2.5">
        <div class="w-7 h-7 rounded-lg bg-brand-soft grid place-items-center shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
            <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><path d="M4 22v-7"/>
          </svg>
        </div>
        <div>
          <h2 class="mb-0!">Community Banner</h2>
          <p class="text-[.8rem] text-muted mt-0.5">Banners displayed on the event landing page.</p>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-if="!banners.length" class="flex flex-col items-center justify-center py-10 rounded-xl border border-dashed border-line bg-[#fafbfc] mb-4 mt-4">
      <div class="w-10 h-10 rounded-xl bg-brand-soft grid place-items-center mb-3">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
          <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><path d="M4 22v-7"/>
        </svg>
      </div>
      <p class="text-[.88rem] font-semibold text-ink mb-1">No Community Banners</p>
      <p class="text-[.82rem] text-muted">Upload a banner to get started.</p>
    </div>

    <!-- Banner grid -->
    <div v-else class="flex gap-3 flex-wrap mt-4 mb-4">
      <div
        v-for="(b, i) in banners" :key="b"
        class="group relative rounded-xl overflow-hidden border border-line"
        style="width:160px;height:90px"
      >
        <img :src="b" alt="Community banner" class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/25 transition-colors" />
        <button
          class="absolute top-1.5 right-1.5 w-6 h-6 rounded-lg bg-white/90 grid place-items-center text-[#dc2626] opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer shadow-sm hover:bg-white"
          title="Remove banner"
          @click="emit('remove', i)"
        >
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- Upload trigger -->
    <div class="w-[160px]">
      <UploadButton :key="bannerKey" collection="banner" @uploaded="onUploaded" />
    </div>
  </div>
</template>
