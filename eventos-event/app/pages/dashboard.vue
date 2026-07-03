<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const auth = useAuthStore()
const api = useApi()
const org = ref<any>(null)
const sub = ref<any>(null)

onMounted(async () => {
  await auth.fetchMe()
  // Organizer-only endpoints — attendees simply won't have these (403 → ignored).
  try { org.value = await api<any>('/organization') } catch { /* attendee */ }
  try { sub.value = (await api<any>('/subscription')).data } catch { /* attendee */ }
})
</script>

<template>
  <div>
    <h1>Welcome, {{ auth.user?.name }}</h1>
    <p class="muted">{{ auth.user?.email }}</p>

    <div class="card" v-if="org">
      <h2>Organization</h2>
      <p><strong>{{ org.data?.name }}</strong> <span class="badge">{{ org.plan?.name }} plan</span></p>
      <div v-if="org.features">
        <span v-for="(v, k) in org.features" :key="k" class="badge" style="margin: 2px 6px 2px 0;">{{ k }}</span>
      </div>
    </div>

    <div class="card" v-if="sub">
      <h2>Subscription</h2>
      <p>{{ sub.plan?.name }} — <span class="badge">{{ sub.status }}</span></p>
    </div>

    <div class="card" v-if="!org">
      <h2>Attendee</h2>
      <p class="muted">You're signed in as an attendee. Open an event to see its feed, agenda and networking.</p>
    </div>

    <NuxtLink class="btn" to="/events">Browse events →</NuxtLink>
  </div>
</template>
