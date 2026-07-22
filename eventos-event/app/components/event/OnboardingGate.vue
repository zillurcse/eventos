<script setup lang="ts">
/**
 * Profile-completion onboarding gate (Settings › Onboarding).
 *
 * Renderless. When the organizer has onboarding on and the server says this
 * attendee still needs it (`meta.needs_onboarding`), the first authed page
 * load redirects to the dedicated /onboarding PAGE — a full-width form built
 * from the attendee profile form's onboarding surface (Event Settings ›
 * Profile), where the organizer's 50%/100% field widths actually mean
 * something. The server owns the "should we ask?" decision; this just routes.
 */
const site = useSiteStore()
const auth = useAuthStore()
const api = useApi()
const route = useRoute()

const checked = ref(false)

async function check() {
  if (checked.value || !auth.isAuthed || !site.event?.uuid) return
  if (!site.site?.login?.onboarding) return // organizer has it off
  if (route.path === '/onboarding') return  // already there

  checked.value = true
  try {
    const r = await api<any>(`/events/${site.event.uuid}/profile`)
    if (r.meta?.needs_onboarding) navigateTo('/onboarding')
  } catch { /* a failed check just means no onboarding this time */ }
}

// Auth and the site payload land at different times; re-check when either does.
watch(() => [auth.user, site.site], check, { immediate: true })
onMounted(check)
</script>

<template>
  <span style="display: none" aria-hidden="true" />
</template>
