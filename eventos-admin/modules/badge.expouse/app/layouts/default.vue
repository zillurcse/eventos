<template>
  <div class="flex flex-col min-h-screen">
    <!-- <Navbar /> -->

    <!-- Full width content without max-w and centered padding. As a layout this
         renders the page through the default slot (NOT <NuxtPage/>, which would
         re-trigger route matching and double-render). -->
    <main class="flex-1 w-full">
      <slot />
    </main>
  </div>
</template>

<script setup>
// import Navbar from '~/components/Navbar.vue'
</script>

<style scoped>
/* Add custom styles if needed */
</style>
