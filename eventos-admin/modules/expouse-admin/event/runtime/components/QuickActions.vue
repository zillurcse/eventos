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
  { title: 'Meetings', desc: 'One-to-one networking between participants.', countKey: 'meetings', noun: 'meetings', to: r('settings/communication/meetings'),
    icon: '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/>' },
  { title: 'Booths', desc: 'Add exhibitors, sponsors and their booths.', countKey: 'booths', noun: 'booths', to: r('showcase/exhibitors'),
    icon: '<path d="M3 9l1-5h16l1 5M4 9v11a1 1 0 001 1h14a1 1 0 001-1V9M3 9h18"/>' },
  { title: 'Lounge', desc: 'Set up live video networking tables.', to: r('settings/communication/lounge'),
    icon: '<path d="M4 12V7a2 2 0 012-2h12a2 2 0 012 2v5M2 12h20M6 19v-3M18 19v-3M5 12v4h14v-4"/>' },
  { title: 'Rooms', desc: 'Create live breakout video rooms.', countKey: 'rooms', noun: 'rooms', create: 'room',
    icon: '<path d="M15 10l4.55-2.28A1 1 0 0121 8.62v6.76a1 1 0 01-1.45.9L15 14M3 6h10a2 2 0 012 2v8a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2z"/>' },
  { title: 'Leaderboard', desc: 'Drive engagement with points and rewards.', to: r('settings/communication/gamification'),
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
        <!-- Icon + title -->
        <div class="flex items-start gap-3 mb-2">
          <div class="w-10 h-10 rounded-lg bg-[#f3f4f6] grid place-items-center shrink-0 text-[#64676A]">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M10 0.75C11.2147 0.75 12.4178 0.989246 13.54 1.4541C14.6622 1.91896 15.6821 2.60009 16.541 3.45898C17.3999 4.31788 18.081 5.33777 18.5459 6.45996C19.0108 7.58222 19.25 8.78527 19.25 10C19.25 11.8293 18.7076 13.6176 17.6914 15.1387C16.675 16.6598 15.2302 17.8458 13.54 18.5459C11.8498 19.246 9.98964 19.4292 8.19531 19.0723C6.40103 18.7153 4.7526 17.8346 3.45898 16.541C2.16537 15.2474 1.28466 13.599 0.927734 11.8047C0.570821 10.0104 0.753991 8.15018 1.4541 6.45996C2.15422 4.76982 3.34023 3.32496 4.86133 2.30859C6.3824 1.29236 8.17068 0.750001 10 0.75ZM13.3486 1.91602C11.7498 1.25375 9.99029 1.08036 8.29297 1.41797C6.59564 1.75559 5.03621 2.58879 3.8125 3.8125C2.58879 5.03621 1.75559 6.59564 1.41797 8.29297C1.08036 9.99029 1.25375 11.7498 1.91602 13.3486C2.57828 14.9474 3.6998 16.314 5.13867 17.2754C6.5776 18.2369 8.26941 18.75 10 18.75C12.3206 18.75 14.5466 17.8284 16.1875 16.1875C17.8284 14.5466 18.75 12.3206 18.75 10C18.75 8.26941 18.2369 6.5776 17.2754 5.13867C16.314 3.6998 14.9474 2.57828 13.3486 1.91602ZM13.4248 7.24902C13.4744 7.24902 13.5228 7.26393 13.5635 7.29102L13.6016 7.32227C13.625 7.34551 13.6436 7.37384 13.6562 7.4043C13.6689 7.43468 13.6758 7.46709 13.6758 7.5C13.6758 7.53291 13.6689 7.56532 13.6562 7.5957C13.6436 7.62616 13.625 7.65449 13.6016 7.67773L8.60156 12.6777C8.55502 12.7238 8.49222 12.7497 8.42676 12.75H8.42578C8.393 12.7502 8.36045 12.7438 8.33008 12.7314C8.3149 12.7252 8.29975 12.718 8.28613 12.709L8.24805 12.6777L5.90137 10.3203L5.88086 10.2998L5.85742 10.2803C5.83141 10.2579 5.81085 10.2301 5.7959 10.1992C5.78096 10.1683 5.7719 10.1349 5.77051 10.1006C5.76918 10.0662 5.77552 10.0311 5.78809 9.99902C5.80063 9.96715 5.81955 9.93831 5.84375 9.91406C5.86811 9.8897 5.8976 9.86999 5.92969 9.85742C5.96164 9.84498 5.99601 9.8395 6.03027 9.84082C6.06449 9.84217 6.09806 9.85038 6.12891 9.86523C6.15982 9.88019 6.18759 9.90168 6.20996 9.92773L6.22949 9.9502L8.43066 12.1514L8.96094 11.6201L13.248 7.32129L13.249 7.32227C13.2958 7.27575 13.3588 7.24908 13.4248 7.24902Z" fill="currentColor" stroke="currentColor" stroke-width="1.5"/>
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-bold text-[#4D5154] leading-snug mb-0.5">{{ c.title }}</p>
             <!-- Description -->
            <p class="text-[#64676A] text-sm leading-relaxed">{{ c.desc }}</p>
          </div>
        </div>

       

        <!-- Add: opens modal for create-capable cards, else navigates -->
        <div class="flex justify-end mt-4 pt-1">
          <button
            v-if="c.create"
            class="inline-flex items-center px-4 py-2.5 rounded-lg max-h-10 text-sm font-bold bg-[#F0EEFD] text-brand-dark transition-colors hover:bg-brand hover:text-white cursor-pointer"
            @click="modalType = c.create"
          >
            Add
          </button>
          <component
            :is="NuxtLink"
            v-else
            :to="c.to"
            class="inline-flex items-center px-4 py-2.5 rounded-lg max-h-10 text-sm font-bold no-underline bg-[#F0EEFD] text-brand-dark transition-colors hover:bg-brand hover:text-white"
          >
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
