<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const section = computed(() => (route.query.s as string) || 'This section')
</script>

<template>
  <div class="card max-w-[560px] mx-auto mt-10 text-center">
    <div class="w-14 h-14 rounded-[14px] bg-[#f3f0ff] text-[#6352e7] grid place-items-center mx-auto mb-4 mt-2">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M12 7v5l3 2"/></svg>
    </div>
    <h2 class="text-[1.3rem]">{{ section }}</h2>
    <p class="muted">This section is coming soon.</p>
  </div>
</template>
