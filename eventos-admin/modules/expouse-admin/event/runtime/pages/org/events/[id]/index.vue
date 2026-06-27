<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string
const ov = ref<any>(null)
const copied = ref(false)
const NuxtLink = resolveComponent('NuxtLink')

async function load() {
  try { ov.value = (await api<any>(`/events/${id}/overview`)).data } catch { /* */ }
}

function sectionLink(to: string | null): string | null {
  if (!to) return null
  if (to === 'team') return '/org/team'
  return `/org/events/${id}/${to}`
}
async function copyCode() {
  try {
    await navigator.clipboard.writeText(ov.value?.credentials?.access_code ?? '')
    copied.value = true; setTimeout(() => (copied.value = false), 1500)
  } catch { /* */ }
}

onMounted(load)
</script>

<template>
  <div v-if="ov" class="grid grid-cols-[1.5fr_1fr] gap-5 items-start">
    <!-- Setup checklist -->
    <div class="card">
      <h2 class="text-[1.3rem]">Welcome {{ ov.name }}, let's power your event</h2>
      <div class="flex items-center gap-3.5 my-3.5 mb-[18px]">
        <strong>Setup your event</strong>
        <span class="muted text-[.85rem]">{{ ov.completed }}/{{ ov.total }} completed</span>
        <div class="flex-1 h-2 bg-[#eef0f4] rounded-full overflow-hidden">
          <div :style="`width:${(ov.completed / ov.total) * 100}%; height:100%; background:var(--brand);`" />
        </div>
      </div>
      <component
        :is="sectionLink(item.to) ? NuxtLink : 'div'"
        v-for="item in ov.checklist" :key="item.key"
        :to="sectionLink(item.to)"
        class="flex items-center gap-3 p-3.5 border border-line rounded-xl mb-2.5 bg-[#fbfbfd] no-underline hover:bg-[#f6f7f9]"
      >
        <span
          class="w-[22px] h-[22px] rounded-md border-2 border-[#cdd2dc] grid place-items-center text-white text-[.8rem] shrink-0"
          :class="{ 'bg-brand border-brand': item.done }"
        >{{ item.done ? '✓' : '' }}</span>
        <span class="flex-1 text-ink font-[550]">{{ item.label }}</span>
        <span v-if="sectionLink(item.to)" class="text-brand font-bold">→</span>
      </component>
    </div>

    <div>
      <!-- Mobile access -->
      <div class="card">
        <h2>Setup your mobile access</h2>
        <p class="muted text-[.86rem] leading-[1.5]">
          Here's how you and your team access the mobile app and start capturing leads instantly: logging in with the
          Username &amp; Access Code grants admin access to view all captured leads and scanning insights.
        </p>
      </div>
      <!-- Credentials -->
      <div class="card">
        <h2>Credentials</h2>
        <label>Username</label>
        <input :value="ov.credentials?.username" readonly>
        <label>Access Code</label>
        <div class="flex gap-2 items-center">
          <input :value="ov.credentials?.access_code" readonly class="my-1.5">
          <button class="btn px-3 py-2.5" @click="copyCode">{{ copied ? '✓' : 'Copy' }}</button>
        </div>
      </div>
    </div>
  </div>
  <p v-else class="muted">Loading…</p>
</template>
