<script setup lang="ts">
// The event's "Manage Filters" definitions rendered as a two-level accordion
// (filter title → heading → options). Selections are stored on the exhibitor as
// filterId → heading → chosen options. Reads the exhibitor context directly so
// it drops into both the add drawer and the full-page editor unchanged.
const { draft, filters } = useExhibitorContext()

const openFilter = ref<string | null>(null)
const openHeading = reactive<Record<string, string | null>>({})

function toggleFilter(f: EventFilter) {
  if (openFilter.value === f.id) { openFilter.value = null; return }
  openFilter.value = f.id
  // Expand the first heading by default, like the reference layout.
  if (openHeading[f.id] === undefined) openHeading[f.id] = f.headings?.[0]?.heading ?? null
}
function toggleHeading(f: EventFilter, heading: string) {
  openHeading[f.id] = openHeading[f.id] === heading ? null : heading
}
function headingOpen(f: EventFilter, heading: string) {
  const cur = openHeading[f.id]
  return (cur === undefined ? f.headings?.[0]?.heading : cur) === heading
}
function isChecked(fid: string, heading: string, opt: string) {
  return !!draft.filter_selections?.[fid]?.[heading]?.includes(opt)
}
function toggleOption(fid: string, heading: string, opt: string) {
  const sel = draft.filter_selections
  const group = (sel[fid] ||= {})
  const arr = (group[heading] ||= [])
  const i = arr.indexOf(opt)
  if (i >= 0) arr.splice(i, 1)
  else arr.push(opt)
  // Prune empties so the payload stays tidy.
  if (!arr.length) delete group[heading]
  if (!Object.keys(group).length) delete sel[fid]
}
function filterCount(fid: string) {
  const group = draft.filter_selections?.[fid]
  return group ? Object.values(group).reduce((n, a) => n + a.length, 0) : 0
}
// Flat list of the selected options for a filter, for the chip row.
function filterChips(fid: string) {
  const group = draft.filter_selections?.[fid] || {}
  return Object.entries(group).flatMap(([heading, opts]) => opts.map(opt => ({ heading, opt })))
}
</script>

<template>
  <div>
    <p v-if="!filters.length" class="muted text-[.84rem] my-1.5">
      No filters configured yet. Add them in Showcase › Manage Filters.
    </p>
    <div v-else class="flex flex-col gap-2">
      <div v-for="f in filters" :key="f.id" class="border border-line rounded-xl overflow-hidden">
        <!-- Filter title bar -->
        <button
          type="button"
          class="w-full flex items-center justify-between gap-3 px-4 py-3 bg-[#f7f8fa] cursor-pointer text-left border-0"
          @click="toggleFilter(f)"
        >
          <span class="flex items-center gap-2 min-w-0">
            <span class="font-semibold text-[.9rem] text-ink truncate">{{ f.title }}</span>
            <span v-if="filterCount(f.id)" class="bg-brand-soft text-brand-dark text-[.72rem] font-semibold px-2 py-0.5 rounded-full shrink-0">{{ filterCount(f.id) }}</span>
          </span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 text-muted transition-transform shrink-0" :class="openFilter === f.id ? 'rotate-180' : ''"><path d="M6 9l6 6 6-6"/></svg>
        </button>

        <!-- Selected chips -->
        <div v-if="openFilter === f.id && filterChips(f.id).length" class="flex flex-wrap gap-1.5 px-4 pt-3 border-t border-line">
          <span
            v-for="chip in filterChips(f.id)"
            :key="chip.heading + '::' + chip.opt"
            class="inline-flex items-center gap-1 bg-brand-soft text-brand-dark text-[.8rem] font-semibold px-2.5 py-0.5 rounded-full"
          >
            {{ chip.opt }}
            <button type="button" class="border-0 bg-transparent cursor-pointer text-brand-dark font-bold leading-none p-0" @click="toggleOption(f.id, chip.heading, chip.opt)">×</button>
          </span>
        </div>

        <!-- Headings + options -->
        <div v-if="openFilter === f.id" class="p-3 border-t border-line flex flex-col gap-2">
          <div v-for="(h, hi) in f.headings" :key="hi" class="rounded-lg border border-line overflow-hidden">
            <button
              type="button"
              class="w-full flex items-center gap-2 px-3 py-2.5 text-left border-0 bg-white cursor-pointer"
              @click="toggleHeading(f, h.heading)"
            >
              <span class="w-4 text-center font-bold text-muted leading-none shrink-0">{{ headingOpen(f, h.heading) ? '−' : '+' }}</span>
              <span class="font-semibold text-[.85rem] text-ink truncate">{{ h.heading || ('Heading ' + (hi + 1)) }}</span>
              <span v-if="h.mandatory" class="badge shrink-0">Required</span>
            </button>
            <div v-if="headingOpen(f, h.heading)" class="px-3 pb-3 pt-0.5 flex flex-col gap-1.5">
              <label v-for="opt in h.options" :key="opt" class="flex items-center gap-2.5 cursor-pointer text-[.88rem] text-ink m-0">
                <input
                  type="checkbox"
                  class="w-4 h-4 m-0 accent-brand shrink-0"
                  :checked="isChecked(f.id, h.heading, opt)"
                  @change="toggleOption(f.id, h.heading, opt)"
                >
                {{ opt }}
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
