<script setup lang="ts">
// Legacy chrome for the pre-microsite app pages (dashboard, events browser).
// The branded landing/auth (pages/index.vue) opts out with `layout: false`;
// the attendee reception uses the richer `event` layout.
const auth = useAuthStore()
const site = useSiteStore()
</script>

<template>
  <div>
    <nav v-if="auth.isAuthed" class="topbar">
      <div class="container">
        <NuxtLink to="/dashboard" class="brand">{{ site.name }}</NuxtLink>
        <NuxtLink to="/dashboard">Dashboard</NuxtLink>
        <NuxtLink to="/events">Events</NuxtLink>
        <a href="#" @click.prevent="auth.logout()">Logout</a>
      </div>
    </nav>

    <main class="container">
      <slot />
    </main>
  </div>
</template>
