<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })
</script>

<template>
  <EventUsersTable
    title="Blocked users"
    subtitle="Users who have been blocked from this event. Unblock to restore their access."
    :query="{ blocked: 1 }"
    count-label="blocked user"
    empty-text="No blocked users yet."
  />
</template>
