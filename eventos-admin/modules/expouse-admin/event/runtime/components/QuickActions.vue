<script setup lang="ts">
const props = defineProps<{ eventId: string }>()

const r = (p: string) => `/org/events/${props.eventId}/${p}`

// Curated shortcuts into the sections an organizer touches most while
// building an event. Each icon is inner SVG markup (stroked, 24×24 grid).
const actions = [
  {
    title: 'Users',
    desc: 'Invite and manage your attendees, delegates and event admins.',
    to: r('users/all'),
    icon: '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>',
  },
  {
    title: 'Sessions',
    desc: 'Build an engaging agenda with talks, tracks and speakers.',
    to: r('showcase/sessions'),
    icon: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
  },
  {
    title: 'Meetings',
    desc: 'Enable one-to-one networking meetings between participants.',
    to: r('communication/meetings'),
    icon: '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>',
  },
  {
    title: 'Exhibitors',
    desc: 'Add exhibitors, sponsors and their booth details.',
    to: r('showcase/exhibitors'),
    icon: '<path d="M3 9l1-5h16l1 5M4 9v11a1 1 0 001 1h14a1 1 0 001-1V9M3 9h18"/>',
  },
  {
    title: 'Lounge',
    desc: 'Set up live video networking tables for your audience.',
    to: r('communication/lounge'),
    icon: '<path d="M2 12h20M4 12V7a2 2 0 012-2h12a2 2 0 012 2v5M6 19v-3M18 19v-3"/><path d="M5 12v4h14v-4"/>',
  },
  {
    title: 'Breakout Rooms',
    desc: 'Create live breakout video rooms for smaller groups.',
    to: r('engagement/breakout-rooms'),
    icon: '<path d="M15 10l4.55-2.28A1 1 0 0121 8.62v6.76a1 1 0 01-1.45.9L15 14M3 6h10a2 2 0 012 2v8a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2z"/>',
  },
  {
    title: 'Speakers',
    desc: 'Showcase your speaker lineup and their sessions.',
    to: r('showcase/speakers'),
    icon: '<path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/>',
  },
  {
    title: 'Badge & QR',
    desc: 'Design attendee badges and QR check-in passes.',
    to: r('onsite/badge-templates'),
    icon: '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h3M15 12h3M7 15h10"/>',
  },
  {
    title: 'ADs',
    desc: 'Promote sponsors and partners across the event app.',
    to: r('ads/manage'),
    icon: '<path d="M3 11l18-5v12L3 14v-3zM11.6 16.8a3 3 0 01-5.8-1.6"/>',
  },
]
const NuxtLink = resolveComponent('NuxtLink')
</script>

<template>
  <section>
    <h2 class="text-[1.05rem] font-bold text-ink mb-3.5">Quick Actions</h2>

    <div class="grid grid-cols-3 gap-4 max-[900px]:grid-cols-2 max-[600px]:grid-cols-1">
      <component
        :is="NuxtLink"
        v-for="a in actions" :key="a.title"
        :to="a.to"
        class="group card mb-0! flex flex-col gap-3 no-underline transition-all duration-150 hover:border-[#c7c2f5] hover:shadow-[0_4px_16px_rgba(99,82,231,0.08)]"
      >
        <div class="flex items-start gap-3">
          <div class="w-9 h-9 rounded-xl bg-brand-soft grid place-items-center shrink-0 text-brand">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" v-html="a.icon" />
          </div>
          <div class="min-w-0">
            <p class="text-[.95rem] font-bold text-ink leading-snug">{{ a.title }}</p>
            <p class="text-muted text-[.82rem] leading-relaxed mt-0.5">{{ a.desc }}</p>
          </div>
        </div>
        <div class="flex justify-end mt-auto pt-1">
          <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-[10px] text-[.82rem] font-semibold bg-brand-soft text-brand transition-colors group-hover:bg-brand group-hover:text-white">
            Open
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </span>
        </div>
      </component>
    </div>
  </section>
</template>
