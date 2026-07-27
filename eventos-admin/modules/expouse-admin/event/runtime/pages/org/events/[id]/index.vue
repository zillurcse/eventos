<script setup lang="ts">
const route = useRoute()
definePageMeta({ middleware: 'organizer' })
navigateTo(`/org/events/${route.params.id}/overview`, { replace: true })
</script>

<template>
  <div />
</template>
