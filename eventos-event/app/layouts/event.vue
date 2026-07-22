<script setup lang="ts">
// Signed-in attendee chrome: branded topbar + the event navigation, over the
// grey app canvas. Used by the reception page (and future event tabs).
const contact = useExhibitorContactStore()
</script>

<template>
  <div class="event-shell">
    <EventHeader />
    <main class="event-main">
      <slot />
    </main>
    <!-- App-wide so it can open from the chat drawer / chat page too. -->
    <ExhibitorsContactModal v-if="contact.open" />

    <!-- Profile-completion onboarding (first-time attendees, if the organizer
         turned it on): a renderless gate that sends them to the /onboarding
         page before they settle into Reception. -->
    <EventOnboardingGate />

    <!-- The organizer's welcome video; decides for itself whether this is the
         moment to appear (after login / on the reception page). -->
    <EventWelcomeVideo />
  </div>
</template>

<style scoped>
.event-shell { min-height: 100vh; background: #F7F7FB; }
.event-main { max-width: 1440px; margin: 0 auto; padding: 30px 18px 56px; }
</style>
