<script setup lang="ts">
definePageMeta({ layout: 'event' })

const profile = useProfileStore()
profile.fetch()
</script>

<template>
  <div class="profile-page">
    <ProfileSidebar />

    <section class="content">
      <NuxtPage />
    </section>
  </div>
</template>

<style scoped>
.profile-page { display: grid; grid-template-columns: 280px 1fr; gap: 24px; align-items: start; }
@media (max-width: 860px) { .profile-page { grid-template-columns: 1fr; } }

.content { background: #fff; border-radius: 16px; box-shadow: 0 1px 2px rgba(15,23,42,.05); padding: 26px 28px 32px; }
.content :deep(h1) { margin: 0 0 18px; font-size: 1.3rem; font-weight: 800; color: #1e293b; }
</style>
