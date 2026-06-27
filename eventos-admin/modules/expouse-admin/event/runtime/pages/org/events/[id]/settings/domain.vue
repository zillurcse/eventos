<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string
const form = reactive({ subdomain: '', custom_domain: '' })
const saved = ref(false)

async function load() {
  const s = (await api<any>(`/events/${id}/settings`)).data
  form.subdomain = s.domain?.subdomain || ''
  form.custom_domain = s.domain?.custom_domain || ''
}
async function save() {
  await api(`/events/${id}/settings`, { method: 'PUT', body: { domain: { subdomain: form.subdomain || null, custom_domain: form.custom_domain || null } } })
  saved.value = true; setTimeout(() => (saved.value = false), 1500)
}
const preview = computed(() => `${form.subdomain || 'your-event'}.eventos.app`)

onMounted(load)
</script>

<template>
  <div class="card max-w-[620px]">
    <h2>Domain <span v-if="saved" class="badge active">saved ✓</span></h2>
    <p class="muted text-[.86rem] -mt-1">Where attendees reach this event. <em>Display only for now — not yet wired to DNS/routing.</em></p>

    <label>Subdomain</label>
    <div class="flex items-center gap-2">
      <input v-model="form.subdomain" placeholder="your-event" class="max-w-[220px] m-0">
      <span class="muted">.eventos.app</span>
    </div>
    <div class="my-3"><span class="badge">https://{{ preview }}</span></div>

    <label>Custom domain (optional)</label>
    <input v-model="form.custom_domain" placeholder="events.yourcompany.com">

    <div class="mt-4"><button class="btn" @click="save">Save domain</button></div>
  </div>
</template>
