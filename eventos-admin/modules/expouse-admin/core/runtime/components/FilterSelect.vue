<script setup lang="ts">
export interface FilterOption {
  label: string
  value: string
}

const props = defineProps<{
  /** Pill prefix, e.g. "Status" → "Status: All". */
  label: string
  options: FilterOption[]
}>()

const model = defineModel<string>({ required: true })

const open = ref(false)
const root = ref<HTMLElement>()

const current = computed(() => props.options.find(o => o.value === model.value) ?? props.options[0])
/** Highlighted whenever something other than the first ("All") option is chosen. */
const active = computed(() => props.options.length > 0 && model.value !== props.options[0]!.value)

function pick(value: string) {
  model.value = value
  open.value = false
}

function onDocClick(e: MouseEvent) {
  if (open.value && root.value && !root.value.contains(e.target as Node)) open.value = false
}
onMounted(() => document.addEventListener('click', onDocClick))
onBeforeUnmount(() => document.removeEventListener('click', onDocClick))
</script>

<template>
  <div ref="root" class="relative inline-block">
    <button type="button" class="filter-pill" :class="{ active }" @click="open = !open">
      <span class="text-muted" :class="{ 'text-brand': active }">{{ label }}:</span>
      {{ current?.label }}
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="transition-transform" :class="{ 'rotate-180': open }"><path d="m6 9 6 6 6-6"/></svg>
    </button>

    <div v-if="open" class="menu-pop left-0 right-auto top-[calc(100%+6px)] min-w-40">
      <button
        v-for="o in options"
        :key="o.value"
        class="flex! items-center justify-between gap-3"
        @click="pick(o.value)"
      >
        {{ o.label }}
        <svg v-if="o.value === model" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand"><path d="M5 13l4 4L19 7"/></svg>
      </button>
    </div>
  </div>
</template>
