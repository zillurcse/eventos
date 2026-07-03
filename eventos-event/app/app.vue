<script setup lang="ts">
const auth = useAuthStore()
const site = useSiteStore()
const route = useRoute()

// The branded landing page (login + inline signup) is full-bleed (its own
// chrome); the topbar only frames the signed-in app pages.
const showChrome = computed(() => auth.isAuthed && route.path !== '/')

onMounted(() => {
  auth.init()
  if (auth.isAuthed && !auth.user) auth.fetchMe()
})
</script>

<template>
  <div>
    <nav v-if="showChrome" class="topbar">
      <div class="container">
        <NuxtLink to="/dashboard" class="brand">{{ site.name }}</NuxtLink>
        <NuxtLink to="/dashboard">Dashboard</NuxtLink>
        <NuxtLink to="/events">Events</NuxtLink>
        <a href="#" @click.prevent="auth.logout()">Logout</a>
      </div>
    </nav>

    <main v-if="showChrome" class="container">
      <NuxtPage />
    </main>
    <NuxtPage v-else />
  </div>
</template>
