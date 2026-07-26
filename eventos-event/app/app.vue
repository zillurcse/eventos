<script setup lang="ts">
import { Toaster } from 'vue-sonner'

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
  <Toaster position="top-right" rich-colors close-button />
</template>
