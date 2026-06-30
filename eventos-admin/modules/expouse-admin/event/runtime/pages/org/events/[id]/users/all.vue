<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })
</script>

<template>
  <EventUsersTable
    title="All Users"
    subtitle="Everyone taking part in this event — attendees, speakers and staff."
    empty-text="No users yet — registrations and speakers will appear here."
  />
</template>
