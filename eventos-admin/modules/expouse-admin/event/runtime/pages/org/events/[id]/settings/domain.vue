<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string
const form = reactive({ subdomain: '', custom_domain: '' })
const saving = ref(false)
const saved = ref(false)

async function load() {
  const s = (await api<any>(`/events/${id}/settings`)).data
  form.subdomain = s.domain?.subdomain || ''
  form.custom_domain = s.domain?.custom_domain || ''
}
async function save() {
  saving.value = true
  try {
    await api(`/events/${id}/settings`, { method: 'PUT', body: { domain: { subdomain: form.subdomain || null, custom_domain: form.custom_domain || null } } })
    saved.value = true; setTimeout(() => (saved.value = false), 1500)
  } finally {
    saving.value = false
  }
}
const preview = computed(() => `${form.subdomain || 'your-event'}.eventos.app`)

onMounted(load)
</script>

<template>
  <div class="max-w-180">

    <!-- Page header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-[1.35rem] font-bold text-ink mb-0.5">Domain</h1>
        <p class="text-muted text-[.88rem]">Where attendees reach this event.</p>
      </div>
      <button class="btn" :disabled="saving" @click="save">
        <svg v-if="saving" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
          <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
        </svg>
        <svg v-else-if="saved" width="14" height="14" viewBox="0 0 24 24" fill="none">
          <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        {{ saving ? 'Saving…' : saved ? 'Saved' : 'Save changes' }}
      </button>
    </div>

    <!-- Info notice -->
    <!-- <div class="flex items-start gap-2.5 px-4 py-3 rounded-xl bg-brand-soft/50 border border-brand-soft mb-5">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand shrink-0 mt-0.5">
        <circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 015.8 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>
      </svg>
      <p class="text-[.82rem] text-brand-dark leading-snug">Display only for now — domain settings aren't yet wired to DNS or routing.</p>
    </div> -->

    <!-- Subdomain -->
    <div class="card mb-4">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-9 h-9 rounded-xl bg-brand-soft grid place-items-center shrink-0">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
            <circle cx="12" cy="12" r="10"/><path d="M2 12h20"/>
            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
          </svg>
        </div>
        <div>
          <p class="font-semibold text-[.95rem] text-ink mb-0.5">EventOS Subdomain</p>
          <p class="text-[.82rem] text-muted">Included free with every event.</p>
        </div>
      </div>

      <label>Subdomain</label>
      <div class="flex items-center gap-2">
        <input v-model="form.subdomain" placeholder="your-event" class="max-w-[220px] m-0">
        <span class="text-muted text-[.86rem]">.eventos.app</span>
      </div>

      <div class="mt-3 flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl bg-bg border border-line">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-faint shrink-0">
          <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
        </svg>
        <span class="text-[.86rem] font-medium text-ink truncate">https://{{ preview }}</span>
        <span class="badge active ml-auto shrink-0">Active</span>
      </div>
    </div>

    <!-- Custom domain -->
    <div class="card">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-9 h-9 rounded-xl bg-brand-soft grid place-items-center shrink-0">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
            <path d="M10 13a5 5 0 007.5.5l3-3a5 5 0 00-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 00-7.5-.5l-3 3a5 5 0 007 7l1.7-1.7"/>
          </svg>
        </div>
        <div>
          <p class="font-semibold text-[.95rem] text-ink mb-0.5">Custom Domain</p>
          <p class="text-[.82rem] text-muted">Optional — point your own domain at this event.</p>
        </div>
      </div>

      <label>Custom domain</label>
      <input v-model="form.custom_domain" placeholder="events.yourcompany.com">
      <p class="text-[.78rem] text-muted mt-2">Coming soon: we'll show the DNS records to configure here.</p>
    </div>

  </div>
</template>
