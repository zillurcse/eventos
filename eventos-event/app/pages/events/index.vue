<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const api = useApi()
const events = ref<any[]>([])
const loaded = ref(false)

onMounted(async () => {
  try { events.value = (await api<any>('/events')).data } catch { /* attendee w/o org */ }
  loaded.value = true
})
</script>

<template>
  <div>
    <h1>Events</h1>
    <p v-if="loaded && !events.length" class="muted">
      No events visible here — attendees open an event directly by its link.
    </p>
    <NuxtLink
      v-for="e in events"
      :key="e.id"
      :to="`/events/${e.id}`"
      class="card"
      style="display: block;"
    >
      <h2>{{ e.name }}</h2>
      <p class="muted">{{ e.status }} · {{ e.resolved_timezone }}</p>
    </NuxtLink>
  </div>
</template>
