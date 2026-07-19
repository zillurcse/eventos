<script setup lang="ts">
const props = defineProps<{
  eventId: string
  counts: Record<string, number>
}>()

const emit = defineEmits<{ (e: 'refresh'): void }>()

const r = (p: string) => `/org/events/${props.eventId}/${p}`

type Card = {
  title: string
  desc: string
  icon: string
  countKey?: string
  noun?: string
  create?: 'session' | 'room'
  to?: string
}

// Reference's 3×3 card set. Cards with a clean single-step create endpoint
// open the QuickAddModal; the rest navigate to their full section.
const cards: Card[] = [
  { title: 'Users', desc: 'Invite and manage your event participants.', countKey: 'users', noun: 'users', to: r('users/all'),
    icon: '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>' },
  { title: 'Sessions', desc: 'Add talks and build out your agenda.', countKey: 'sessions', noun: 'sessions', create: 'session',
    icon: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>' },
  { title: 'Meetings', desc: 'One-to-one networking between participants.', countKey: 'meetings', noun: 'meetings', to: r('communication/meetings'),
    icon: '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/>' },
  { title: 'Booths', desc: 'Add exhibitors, sponsors and their booths.', countKey: 'booths', noun: 'booths', to: r('showcase/exhibitors'),
    icon: '<path d="M3 9l1-5h16l1 5M4 9v11a1 1 0 001 1h14a1 1 0 001-1V9M3 9h18"/>' },
  { title: 'Lounge', desc: 'Set up live video networking tables.', to: r('communication/lounge'),
    icon: '<path d="M4 12V7a2 2 0 012-2h12a2 2 0 012 2v5M2 12h20M6 19v-3M18 19v-3M5 12v4h14v-4"/>' },
  { title: 'Rooms', desc: 'Create live breakout video rooms.', countKey: 'rooms', noun: 'rooms', create: 'room',
    icon: '<path d="M15 10l4.55-2.28A1 1 0 0121 8.62v6.76a1 1 0 01-1.45.9L15 14M3 6h10a2 2 0 012 2v8a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2z"/>' },
  { title: 'Leaderboard', desc: 'Drive engagement with points and rewards.', to: r('communication/gamification'),
    icon: '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0V4zM17 5h3v2a3 3 0 01-3 3M7 5H4v2a3 3 0 003 3"/>' },
  { title: 'Upcoming Sessions', desc: 'Schedule the next talks on your agenda.', countKey: 'upcoming_sessions', noun: 'upcoming', create: 'session',
    icon: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>' },
  { title: 'Rooms', desc: 'Add another breakout room for your event.', countKey: 'rooms', noun: 'rooms', create: 'room',
    icon: '<path d="M15 10l4.55-2.28A1 1 0 0121 8.62v6.76a1 1 0 01-1.45.9L15 14M3 6h10a2 2 0 012 2v8a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2z"/>' },
]

const NuxtLink = resolveComponent('NuxtLink')

// Active create modal (entity type + which card triggered it).
const modalType = ref<'session' | 'room' | null>(null)

function onCreated() {
  modalType.value = null
  emit('refresh')
}
</script>

<template>
  <section>
    <h2 class="text-[1.05rem] font-bold text-ink mb-3.5">Quick Actions</h2>

    <div class="grid grid-cols-3 gap-4 max-[900px]:grid-cols-2 max-[600px]:grid-cols-1">
      <div
        v-for="(c, i) in cards" :key="`${c.title}-${i}`"
        class="card mb-0! flex flex-col"
      >
        <!-- Icon + title + count -->
        <div class="flex items-start gap-2.5 mb-2">
          <div class="w-9 h-9 rounded-xl bg-brand-soft grid place-items-center shrink-0 text-brand">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" v-html="c.icon" />
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-[.95rem] font-bold text-ink leading-snug">{{ c.title }}</p>
            <p v-if="c.countKey" class="text-[.78rem] text-muted mt-0.5">
              <span class="font-semibold text-ink">{{ counts?.[c.countKey] ?? 0 }}</span> {{ c.noun }}
            </p>
          </div>
        </div>

        <!-- Description -->
        <p class="text-muted text-[.82rem] leading-relaxed">{{ c.desc }}</p>

        <!-- Add: opens modal for create-capable cards, else navigates -->
        <div class="flex justify-end mt-4 pt-1">
          <button
            v-if="c.create"
            class="inline-flex items-center gap-1.5 px-5 py-2 rounded-[10px] text-[.83rem] font-semibold bg-brand-soft text-brand transition-colors hover:bg-brand hover:text-white cursor-pointer"
            @click="modalType = c.create"
          >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            Add
          </button>
          <component
            :is="NuxtLink"
            v-else
            :to="c.to"
            class="inline-flex items-center gap-1.5 px-5 py-2 rounded-[10px] text-[.83rem] font-semibold no-underline bg-brand-soft text-brand transition-colors hover:bg-brand hover:text-white"
          >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            Add
          </component>
        </div>
      </div>
    </div>

    <QuickAddModal
      v-if="modalType"
      :type="modalType"
      :event-id="eventId"
      @close="modalType = null"
      @created="onCreated"
    />
  </section>
</template>
