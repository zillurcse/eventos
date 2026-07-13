<script setup lang="ts">
/** The trash button in a sub-resource table's actions column (members,
 *  documents, projects, products) — it was the same 6 lines of markup in each. */
defineProps<{ title: string }>()
</script>

<template>
  <button
    class="w-8 h-8 inline-flex items-center justify-center bg-transparent border-0 rounded-lg cursor-pointer text-muted hover:text-[#dc2626] hover:bg-[#fef2f2] transition-colors"
    :title="title"
  >
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
      <path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" />
      <path d="M10 11v6M14 11v6" />
    </svg>
  </button>
</template>
