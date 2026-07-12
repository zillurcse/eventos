<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })
</script>

<template>
  <EventUsersTable
    title="All Users"
    subtitle="All attendees, speakers and staff in this event."
    empty-text="No users yet. Registrations and speakers will appear here."
  />
</template>
