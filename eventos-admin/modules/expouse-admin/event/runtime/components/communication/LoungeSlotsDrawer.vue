<script setup lang="ts">
const props = defineProps<{
  eventDates:   string[]
  selectedDate: string
  slotsOpenAll: boolean
  slots:        Record<string, string[]>
  saving:       boolean
}>()

const emit = defineEmits<{
  (e: 'update:selectedDate', v: string): void
  (e: 'update:slotsOpenAll', v: boolean): void
  (e: 'save'): void
  (e: 'close'): void
}>()

const HOURS = ['10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00']

function hourLabel(h: string): string {
  const n = Number(h.split(':')[0])
  const ampm = n >= 12 ? 'PM' : 'AM'
  const h12 = n % 12 === 0 ? 12 : n % 12
  return `${String(h12).padStart(2, '0')} ${ampm}`
}

function fmtDateTab(iso: string): string {
  const [y, m, dd] = iso.split('-').map(Number)
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  return `${String(dd).padStart(2, '0')} ${months[m - 1]} ${y}`
}

function slotsForDate(): string[] {
  const key = props.selectedDate
  if (!props.slots[key]) props.slots[key] = []
  return props.slots[key] as string[]
}
function addSlot(hour: string) {
  const h = Number(hour.split(':')[0] ?? 0)
  const pad = (n: number) => String(n).padStart(2, '0')
  const firstHalf = `${pad(h)}:00-${pad(h)}:30`
  const secondHalf = `${pad(h)}:30-${pad(h + 1)}:00`
  const existing = slotsForDate()
  // Add the :00–:30 slot first, then the :30–:00 slot, avoiding duplicates.
  const next = !existing.includes(firstHalf) ? firstHalf : !existing.includes(secondHalf) ? secondHalf : null
  if (next) existing.push(next)
}
function removeSlot(i: number) { slotsForDate().splice(i, 1) }
function slotsAtHour(hour: string): { slot: string, index: number }[] {
  const hh = hour.split(':')[0] ?? ''
  return slotsForDate()
    .map((slot: string, index: number) => ({ slot, index }))
    .filter((s: { slot: string, index: number }) => s.slot.startsWith(hh + ':'))
}
</script>

<template>
  <Drawer title="Manage Available Slots" @close="emit('close')">
    <div v-if="eventDates.length" class="flex gap-2 overflow-x-auto pb-2 mb-4">
      <button
        v-for="d in eventDates" :key="d"
        class="px-4 py-2.5 rounded-lg border text-[.84rem] font-bold whitespace-nowrap transition-colors"
        :class="selectedDate === d ? 'border-brand text-brand bg-brand-soft' : 'border-line text-muted bg-white hover:border-brand'"
        @click="emit('update:selectedDate', d)"
      >{{ fmtDateTab(d) }}</button>
    </div>
    <p v-else class="muted text-[.84rem] mb-4">Set the event start &amp; end dates first to manage slots.</p>

    <AppCheckbox
      :model-value="slotsOpenAll" label="Open all meeting slot" class="mb-4"
      @update:model-value="emit('update:slotsOpenAll', $event)"
    />

    <div v-if="selectedDate && !slotsOpenAll" class="flex">
      <div class="w-16 shrink-0">
        <div v-for="h in HOURS" :key="h" class="h-14 text-[.8rem] font-semibold text-muted">{{ hourLabel(h) }}</div>
      </div>
      <div class="flex-1 border-l border-line pl-3">
        <div v-for="h in HOURS" :key="h" class="min-h-14 py-1 flex flex-wrap gap-1.5 content-start">
          <span
            v-for="s in slotsAtHour(h)" :key="s.index"
            class="inline-flex items-center gap-1 bg-brand-soft text-brand-dark text-[.82rem] font-semibold px-2.5 py-1 rounded-md"
          >
            {{ s.slot }}
            <button class="text-brand-dark font-bold leading-none cursor-pointer bg-transparent border-0 p-0" @click="removeSlot(s.index)">×</button>
          </span>
          <button class="text-[.78rem] text-[#9aa0ad] hover:text-brand cursor-pointer bg-transparent border-0 px-1.5 py-1" title="Add slot" @click="addSlot(h)">+ add</button>
        </div>
      </div>
    </div>
    <p v-else-if="slotsOpenAll" class="muted text-[.84rem]">All meeting slots are open for the selected days.</p>

    <div class="modal-actions">
      <button class="btn" :disabled="saving" @click="emit('save')">{{ saving ? 'Saving…' : 'SAVE' }}</button>
    </div>
  </Drawer>
</template>
