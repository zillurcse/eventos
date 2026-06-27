<script setup lang="ts">
defineProps<{ section?: string, title: string }>()
</script>

<template>
  <div>
    <div v-if="section" class="muted text-[.78rem] font-[700] uppercase tracking-[.06em] mb-1.5">{{ section }}</div>
    <h2 class="section-title m-0 mb-4">{{ title }}</h2>
    <div class="card text-center py-[52px] px-5">
      <div class="w-[54px] h-[54px] rounded-[14px] bg-[#f3f0ff] text-[#6352e7] grid place-items-center mx-auto mb-3.5">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M12 7v5l3 2"/></svg>
      </div>
      <p class="muted m-0">This section is coming soon.</p>
    </div>
  </div>
</template>
