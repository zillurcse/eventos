<script setup lang="ts">
const auth = useAuthStore()

onMounted(() => {
  auth.init()
  if (auth.isAuthed && !auth.user) auth.fetchMe()
})
</script>

<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>
