<script setup lang="ts">
const props = defineProps<{
  eventId: string
  active: string
}>()

const TABS = [
  { key: 'functionality', label: 'Functionality' },
  { key: 'lounge',        label: 'Lounge' },
  { key: 'meetings',      label: 'Meetings' },
  { key: 'chats',         label: 'Chats' },
  { key: 'cta',           label: 'CTA' },
  { key: 'gamification',  label: 'Gamification' },
  { key: 'notification',  label: 'Notification' },
]

const to = (key: string) => `/org/events/${props.eventId}/settings/communication/${key}`
</script>

<template>
  <div class="card !p-0 mb-4">
    <div class="flex items-center gap-1 px-5 overflow-x-auto">
      <NuxtLink
        v-for="t in TABS" :key="t.key"
        :to="to(t.key)"
        class="px-1 mr-6 py-4 text-[.95rem] font-semibold border-b-2 -mb-px whitespace-nowrap no-underline transition-colors"
        :class="active === t.key ? 'border-brand text-ink' : 'border-transparent text-muted hover:text-ink'"
      >{{ t.label }}</NuxtLink>
    </div>
  </div>
</template>
