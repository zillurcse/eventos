<script setup lang="ts">
// Signed-in attendee chrome: branded topbar + the event navigation, over the
// grey app canvas. Used by the reception page (and future event tabs).
const contact = useExhibitorContactStore()
</script>

<template>
  <div class="event-shell">
    <EventTopbar />
    <EventNav />
    <main class="event-main">
      <slot />
    </main>
    <!-- App-wide so it can open from the chat drawer / chat page too. -->
    <ExhibitorsContactModal v-if="contact.open" />
  </div>
</template>

<style scoped>
.event-shell { min-height: 100vh; background: #e9ebee; }
.event-main { max-width: 1180px; margin: 0 auto; padding: 20px 18px 56px; }
</style>
