<script setup lang="ts">
const NuxtLink = resolveComponent('NuxtLink')

const props = defineProps<{
  name: string
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
</script>

<template>
  <div class="card mb-0!">
    <!-- Header -->
    <div class="flex items-start justify-between gap-4 mb-4">
      <div>
        <p class="text-[1.02rem] font-bold text-ink leading-snug">{{ name }}</p>
        <p class="text-muted text-[.85rem] mt-0.5">Complete the steps below to power your event</p>
      </div>
      <span class="badge shrink-0 mt-0.5">{{ completed }}/{{ total }} done</span>
    </div>

    <!-- Progress bar -->
    <div class="mb-5">
      <div class="flex justify-between items-center mb-1.5">
        <span class="text-[.78rem] text-muted font-medium">Setup progress</span>
        <span class="text-[.78rem] text-brand font-semibold">{{ pct }}%</span>
      </div>
      <div class="h-1.75 bg-[#eef0f4] rounded-full overflow-hidden">
        <div
          class="h-full rounded-full transition-all duration-500"
          style="background: var(--brand)"
          :style="`width:${pct}%`"
        />
      </div>
    </div>

    <!-- Items -->
    <div class="flex flex-col gap-2">
      <component
        :is="sectionLink(item.to) ? NuxtLink : 'div'"
        v-for="item in checklist" :key="item.key"
        :to="sectionLink(item.to)"
        class="group flex items-center gap-3.5 px-4 py-3.5 rounded-xl border transition-all duration-150 no-underline"
        :class="item.done
          ? 'border-brand-soft bg-brand-soft'
          : 'border-line bg-[#fafbfc] hover:border-[#c7c2f5] hover:bg-brand-soft/30 cursor-pointer'"
      >
        <span
          class="w-5 h-5 rounded-md shrink-0 grid place-items-center transition-colors"
          :class="item.done
            ? 'bg-brand text-white'
            : 'border-2 border-[#cdd2dc] group-hover:border-brand'"
        >
          <svg v-if="item.done" width="10" height="10" viewBox="0 0 24 24" fill="none">
            <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
        <span
          class="flex-1 text-[.9rem] font-[550] leading-snug"
          :class="item.done ? 'text-brand-dark line-through decoration-brand/40' : 'text-ink'"
        >{{ item.label }}</span>
        <svg
          v-if="item.done"
          class="w-4 h-4 text-brand"
          viewBox="0 0 24 24" fill="none"
        >
          <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <svg
          v-else-if="sectionLink(item.to)"
          class="w-4 h-4 text-faint transition-colors group-hover:text-brand"
          viewBox="0 0 24 24" fill="none"
        >
          <path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </component>
    </div>
  </div>
</template>
