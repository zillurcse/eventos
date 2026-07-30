<script setup lang="ts">
// Signed-in attendee chrome: branded topbar + the event navigation, over the
// grey app canvas. Used by the reception page (and future event tabs).
const site = useSiteStore()
const contact = useExhibitorContactStore()
const delegates = useDelegatesStore()
</script>

<template>
  <div class="event-shell" :data-appearance="site.appearance">
    <EventHeader />
    <main class="event-main">
      <slot />
    </main>
    <!-- App-wide so it can open from the chat drawer / chat page too. -->
    <ExhibitorsContactModal v-if="contact.open" />

    <!-- Chat / Meet connect modal — opened from delegate cards, speaker
         profiles, similar-people strip, etc. -->
    <DelegatesConnectModal />

    <!-- Delegate profile — also opened from feed @mentions. -->
    <DelegatesDetailModal v-if="delegates.selected" :key="delegates.selected.id" :delegate="delegates.selected" />

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
.event-shell { min-height: 100vh; background: var(--brand-page-bg, #F7F7FB); color: var(--brand-body-text, #212529); }
.event-main { max-width: 1440px; margin: 0 auto; padding: 30px 18px 56px; }

.event-shell[data-appearance="minimal"] .event-main {
  max-width: 880px;
  padding: 18px 14px 40px;
}
</style>
