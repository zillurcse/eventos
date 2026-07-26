<script setup lang="ts">
const NuxtLink = resolveComponent('NuxtLink')

const props = defineProps<{
  checklist: { key: string; label: string; done: boolean; to: string | null }[]
  completed: number
  total: number
  eventId: string
}>()

function sectionLink(to: string | null): string | null {
  if (!to) return null
  if (to === 'team') return '/org/team'
  return `/org/events/${props.eventId}/${to}`
}

const pct = computed(() => Math.round((props.completed / props.total) * 100))

// The first not-yet-done step is the one to nudge the organizer toward.
const nextKey = computed(() => props.checklist.find(i => !i.done)?.key ?? null)
</script>

<template>
  <div class="card mb-0!">
    <!-- Header -->
    <div class="flex items-start justify-between gap-4 mb-5">
      <p class="text-[1.05rem] font-bold text-ink leading-snug">Setup your event</p>
      <div class="flex flex-col items-end gap-1.5 shrink-0">
        <span class="text-[.82rem] font-semibold text-muted">{{ completed }}/{{ total }} completed</span>
        <div class="w-36 h-1.5 bg-[#eef0f4] rounded-full overflow-hidden">
          <div
            class="h-full rounded-full transition-all duration-500"
            style="background: var(--brand)"
            :style="`width:${pct}%`"
          />
        </div>
      </div>
    </div>

    <!-- Items -->
    <div class="flex flex-col gap-3">
      <component
        :is="sectionLink(item.to) ? NuxtLink : 'div'"
        v-for="item in checklist" :key="item.key"
        :to="sectionLink(item.to)"
        class="group flex items-center gap-3.5 px-4 py-4 rounded-xl transition-all duration-150 no-underline"
        :class="[
          item.key === nextKey ? 'bg-[#f8f9fb]' : '',
          sectionLink(item.to) ? 'cursor-pointer hover:bg-[#f8f9fb]' : '',
        ]"
      >
        <!-- Status circle -->
        <span
          class="w-6 h-6 rounded-full shrink-0 grid place-items-center transition-colors"
        >
          <!-- <svg width="11" height="11" viewBox="0 0 24 24" fill="none">
            <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
          </svg> -->
          <svg v-if="item.done" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 20 20" fill="none">
              <path d="M10 0.75C11.2147 0.75 12.4178 0.989246 13.54 1.4541C14.6622 1.91896 15.6821 2.60009 16.541 3.45898C17.3999 4.31788 18.081 5.33777 18.5459 6.45996C19.0108 7.58222 19.25 8.78527 19.25 10C19.25 11.8293 18.7076 13.6176 17.6914 15.1387C16.675 16.6598 15.2302 17.8458 13.54 18.5459C11.8498 19.246 9.98964 19.4292 8.19531 19.0723C6.40103 18.7153 4.7526 17.8346 3.45898 16.541C2.16537 15.2474 1.28466 13.599 0.927734 11.8047C0.570821 10.0104 0.753991 8.15018 1.4541 6.45996C2.15422 4.76982 3.34023 3.32496 4.86133 2.30859C6.3824 1.29236 8.17068 0.750001 10 0.75ZM13.3486 1.91602C11.7498 1.25375 9.99029 1.08036 8.29297 1.41797C6.59564 1.75559 5.03621 2.58879 3.8125 3.8125C2.58879 5.03621 1.75559 6.59564 1.41797 8.29297C1.08036 9.99029 1.25375 11.7498 1.91602 13.3486C2.57828 14.9474 3.6998 16.314 5.13867 17.2754C6.5776 18.2369 8.26941 18.75 10 18.75C12.3206 18.75 14.5466 17.8284 16.1875 16.1875C17.8284 14.5466 18.75 12.3206 18.75 10C18.75 8.26941 18.2369 6.5776 17.2754 5.13867C16.314 3.6998 14.9474 2.57828 13.3486 1.91602ZM13.4248 7.24902C13.4744 7.24902 13.5228 7.26393 13.5635 7.29102L13.6016 7.32227C13.625 7.34551 13.6436 7.37384 13.6562 7.4043C13.6689 7.43468 13.6758 7.46709 13.6758 7.5C13.6758 7.53291 13.6689 7.56532 13.6562 7.5957C13.6436 7.62616 13.625 7.65449 13.6016 7.67773L8.60156 12.6777C8.55502 12.7238 8.49222 12.7497 8.42676 12.75H8.42578C8.393 12.7502 8.36045 12.7438 8.33008 12.7314C8.3149 12.7252 8.29975 12.718 8.28613 12.709L8.24805 12.6777L5.90137 10.3203L5.88086 10.2998L5.85742 10.2803C5.83141 10.2579 5.81085 10.2301 5.7959 10.1992C5.78096 10.1683 5.7719 10.1349 5.77051 10.1006C5.76918 10.0662 5.77552 10.0311 5.78809 9.99902C5.80063 9.96715 5.81955 9.93831 5.84375 9.91406C5.86811 9.8897 5.8976 9.86999 5.92969 9.85742C5.96164 9.84498 5.99601 9.8395 6.03027 9.84082C6.06449 9.84217 6.09806 9.85038 6.12891 9.86523C6.15982 9.88019 6.18759 9.90168 6.20996 9.92773L6.22949 9.9502L8.43066 12.1514L8.96094 11.6201L13.248 7.32129L13.249 7.32227C13.2958 7.27575 13.3588 7.24908 13.4248 7.24902Z" fill="#4CBB3E" stroke="#4CBB3E" stroke-width="1.5"/>
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 20 20" fill="none">
<path d="M10 0.75C11.2147 0.75 12.4178 0.989246 13.54 1.4541C14.6622 1.91896 15.6821 2.60009 16.541 3.45898C17.3999 4.31788 18.081 5.33777 18.5459 6.45996C19.0108 7.58222 19.25 8.78527 19.25 10C19.25 11.8293 18.7076 13.6176 17.6914 15.1387C16.675 16.6598 15.2302 17.8458 13.54 18.5459C11.8498 19.246 9.98964 19.4292 8.19531 19.0723C6.40103 18.7153 4.7526 17.8346 3.45898 16.541C2.16537 15.2474 1.28466 13.599 0.927734 11.8047C0.570821 10.0104 0.753991 8.15018 1.4541 6.45996C2.15422 4.76982 3.34023 3.32496 4.86133 2.30859C6.3824 1.29236 8.17068 0.750001 10 0.75ZM13.3486 1.91602C11.7498 1.25375 9.99029 1.08036 8.29297 1.41797C6.59564 1.75559 5.03621 2.58879 3.8125 3.8125C2.58879 5.03621 1.75559 6.59564 1.41797 8.29297C1.08036 9.99029 1.25375 11.7498 1.91602 13.3486C2.57828 14.9474 3.6998 16.314 5.13867 17.2754C6.5776 18.2369 8.26941 18.75 10 18.75C12.3206 18.75 14.5466 17.8284 16.1875 16.1875C17.8284 14.5466 18.75 12.3206 18.75 10C18.75 8.26941 18.2369 6.5776 17.2754 5.13867C16.314 3.6998 14.9474 2.57828 13.3486 1.91602ZM13.4248 7.24902C13.4744 7.24902 13.5228 7.26393 13.5635 7.29102L13.6016 7.32227C13.625 7.34551 13.6436 7.37384 13.6562 7.4043C13.6689 7.43468 13.6758 7.46709 13.6758 7.5C13.6758 7.53291 13.6689 7.56532 13.6562 7.5957C13.6436 7.62616 13.625 7.65449 13.6016 7.67773L8.60156 12.6777C8.55502 12.7238 8.49222 12.7497 8.42676 12.75H8.42578C8.393 12.7502 8.36045 12.7438 8.33008 12.7314C8.3149 12.7252 8.29975 12.718 8.28613 12.709L8.24805 12.6777L5.90137 10.3203L5.88086 10.2998L5.85742 10.2803C5.83141 10.2579 5.81085 10.2301 5.7959 10.1992C5.78096 10.1683 5.7719 10.1349 5.77051 10.1006C5.76918 10.0662 5.77552 10.0311 5.78809 9.99902C5.80063 9.96715 5.81955 9.93831 5.84375 9.91406C5.86811 9.8897 5.8976 9.86999 5.92969 9.85742C5.96164 9.84498 5.99601 9.8395 6.03027 9.84082C6.06449 9.84217 6.09806 9.85038 6.12891 9.86523C6.15982 9.88019 6.18759 9.90168 6.20996 9.92773L6.22949 9.9502L8.43066 12.1514L8.96094 11.6201L13.248 7.32129L13.249 7.32227C13.2958 7.27575 13.3588 7.24908 13.4248 7.24902Z" fill="#64676A" stroke="#64676A" stroke-width="1.5"/>
</svg>
        </span>

        <span
          class="flex-1 text-[1rem] leading-snug"
          :class="item.key === nextKey ? 'text-ink font-semibold' : 'text-muted font-medium'"
        >{{ item.label }}</span>

        <!-- Chevron on the active / navigable step -->
        <svg
          v-if="item.key === nextKey && sectionLink(item.to)"
          class="w-4 h-4 text-faint transition-colors group-hover:text-brand"
          viewBox="0 0 24 24" fill="none"
        >
          <path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </component>
    </div>
  </div>
</template>
