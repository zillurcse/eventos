<script setup lang="ts">
const auth = useAuthStore()

onMounted(() => {
  auth.init()
  if (auth.isAuthed && !auth.user) auth.fetchMe()
})
</script>

<template>
  <div>
    <nav class="topbar">
      <div class="container">
        <NuxtLink to="/" class="brand">EventOS · Event</NuxtLink>
        <template v-if="auth.isAuthed">
          <NuxtLink to="/">Dashboard</NuxtLink>
          <NuxtLink to="/events">Events</NuxtLink>
          <a href="#" @click.prevent="auth.logout()">Logout</a>
        </template>
        <NuxtLink v-else to="/login">Sign in</NuxtLink>
      </div>
    </nav>

    <main class="container">
      <NuxtPage />
    </main>
  </div>
</template>
