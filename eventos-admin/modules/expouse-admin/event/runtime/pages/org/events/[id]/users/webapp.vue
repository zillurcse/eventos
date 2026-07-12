<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })
</script>

<template>
  <EventUsersTable
    title="WebApp users"
    subtitle="Participants who can sign in to the event app."
    :query="{ has_login: 1 }"
    count-label="app user"
    empty-text="No web-app users yet. Participants get a login when they register with a password."
  />
</template>
