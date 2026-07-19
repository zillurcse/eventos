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
    <div class="flex items-center justify-between gap-4 mb-4">
      <p class="text-[1.05rem] font-bold text-ink leading-snug">Setup your event</p>
      <span class="text-[.82rem] font-semibold text-muted shrink-0">{{ completed }}/{{ total }} completed</span>
    </div>

    <!-- Progress bar -->
    <div class="h-1.5 bg-[#eef0f4] rounded-full overflow-hidden mb-5">
      <div
        class="h-full rounded-full transition-all duration-500"
        style="background: var(--brand)"
        :style="`width:${pct}%`"
      />
    </div>

    <!-- Items -->
    <div class="flex flex-col gap-2.5">
      <component
        :is="sectionLink(item.to) ? NuxtLink : 'div'"
        v-for="item in checklist" :key="item.key"
        :to="sectionLink(item.to)"
        class="group flex items-center gap-3.5 px-4 py-3.5 rounded-xl border transition-all duration-150 no-underline"
        :class="[
          item.key === nextKey
            ? 'border-line bg-[#fafbfc] shadow-[0_1px_2px_rgba(0,0,0,0.03)]'
            : 'border-transparent',
          sectionLink(item.to) ? 'cursor-pointer hover:border-line hover:bg-[#fafbfc]' : '',
        ]"
      >
        <!-- Status circle -->
        <span
          class="w-5.5 h-5.5 rounded-full shrink-0 grid place-items-center transition-colors"
          :class="item.done
            ? 'bg-[#22c55e] text-white'
            : 'border-2 border-[#d3d8e0] text-transparent group-hover:border-brand'"
        >
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none">
            <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>

        <span
          class="flex-1 text-[.92rem] font-[550] leading-snug"
          :class="item.done ? 'text-ink' : 'text-ink'"
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
