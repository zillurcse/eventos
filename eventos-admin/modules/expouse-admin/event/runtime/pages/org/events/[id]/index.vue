<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const auth = useAuthStore()
const id = route.params.id as string
const ov = ref<any>(null)

const firstName = computed(() => (auth.user?.name || '').trim().split(/\s+/)[0] || 'there')

async function load() {
  try { ov.value = (await api<any>(`/events/${id}/overview`)).data } catch { /* */ }
}

function handleUpdated(v: { username?: string; panel?: any }) {
  if (!ov.value) return
  if (v.username) ov.value.credentials.username = v.username
  if (v.panel) ov.value.mobile_access_panel = { ...ov.value.mobile_access_panel, ...v.panel }
}

onMounted(() => {
  auth.init()
  if (auth.isAuthed && !auth.user) auth.fetchMe()
  load()
})
</script>

<template>
  <div v-if="ov">
    <!-- Greeting -->
    <div class="mb-6">
      <h1 class="text-[1.6rem] font-bold text-ink mb-1">Hey {{ firstName }}!</h1>
      <p class="text-muted text-[.92rem]">Welcome {{ ov.name }}, Let's Power Your Event.</p>
    </div>

    <div class="grid grid-cols-[1fr_380px] gap-5 items-start">
      <SetupChecklist
        :checklist="ov.checklist"
        :completed="ov.completed"
        :total="ov.total"
        :event-id="id"
      />
      <MobileAccessPanel
        :event-id="id"
        :username="ov.credentials.username"
        :access-code="ov.credentials.access_code"
        :panel="ov.mobile_access_panel"
        @updated="handleUpdated"
      />
    </div>

    <!-- Quick Actions -->
    <QuickActions :event-id="id" :counts="ov.quick_counts" class="mt-7" @refresh="load" />
  </div>

  <!-- Loading state -->
  <div v-else class="flex flex-col items-center justify-center py-24">
    <div class="w-9 h-9 rounded-full border-[3px] border-brand/20 border-t-brand animate-spin mb-4" />
    <p class="text-muted text-[.88rem]">Loading overview…</p>
  </div>
</template>
