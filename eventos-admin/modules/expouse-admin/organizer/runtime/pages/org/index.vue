<script setup lang="ts">
definePageMeta({ middleware: 'organizer', title: 'Overview', subtitle: 'Organization overview' })

const auth = useAuthStore()
const api = useApi()
const org = ref<any>(null)
const sub = ref<any>(null)
const events = ref<any[]>([])

onMounted(async () => {
  try { org.value = await api<any>('/organization') } catch { /* */ }
  try { sub.value = (await api<any>('/subscription')).data } catch { /* */ }
  try { events.value = (await api<any>('/events')).data } catch { /* */ }
})
</script>

<template>
  <div>
    <div class="stats">
      <div class="stat"><div class="n">{{ events.length }}</div><div class="l">Events</div></div>
      <div class="stat"><div class="n">{{ org?.plan?.name || sub?.plan?.name || '—' }}</div><div class="l">Plan</div></div>
      <div class="stat"><div class="n">{{ sub?.status || org?.data?.status || '—' }}</div><div class="l">Subscription</div></div>
    </div>

    <div v-if="org?.features" class="card">
      <h2>Enabled features</h2>
      <span v-for="(v, k) in org.features" :key="k" class="badge mr-1.5 my-0.5">{{ k }}</span>
    </div>

    <div class="card">
      <h2>Quick links</h2>
      <NuxtLink class="btn" to="/org/events">Manage events →</NuxtLink>
      <NuxtLink class="btn ghost ml-2" to="/org/team">Team →</NuxtLink>
    </div>
  </div>
</template>
