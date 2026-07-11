<script setup lang="ts">
interface Partner { id: string, type: string, name: string, logo_url: string | null }

const props = defineProps<{
  title:           string
  items:           Partner[]
  meetings:        Record<string, number>
  defaultMeetings: number
  saving:          boolean
  emptyText:       string
}>()

const emit = defineEmits<{
  (e: 'save'): void
  (e: 'close'): void
}>()

function meetingsFor(pid: string): number {
  return Number.isFinite(props.meetings[pid]) ? (props.meetings[pid] as number) : props.defaultMeetings
}
function setMeetings(pid: string, val: number) {
  props.meetings[pid] = Math.max(0, Math.trunc(Number(val) || 0))
}

const dragIndex = ref<number | null>(null)
function onDragStart(i: number) { dragIndex.value = i }
function onDragOver(i: number, e: DragEvent) {
  e.preventDefault()
  if (dragIndex.value === null || dragIndex.value === i) return
  const [moved] = props.items.splice(dragIndex.value, 1)
  props.items.splice(i, 0, moved)
  dragIndex.value = i
}
function onDragEnd() { dragIndex.value = null }
</script>

<template>
  <Drawer :title="title" @close="emit('close')">
    <div class="flex flex-col gap-2.5">
      <div
        v-for="(p, i) in items" :key="p.id"
        class="flex items-center gap-3 border border-line rounded-xl p-3 bg-white"
        :class="{ 'opacity-50 border-brand': dragIndex === i }"
        draggable="true" @dragstart="onDragStart(i)" @dragover="onDragOver(i, $event)" @dragend="onDragEnd"
      >
        <span class="cursor-grab text-[#b8bcc6] select-none">⠿</span>
        <div class="w-11 h-11 rounded-lg overflow-hidden bg-[#f3f4f6] border border-line grid place-items-center shrink-0">
          <img v-if="p.logo_url" :src="p.logo_url" :alt="p.name" class="w-full h-full object-contain">
          <span v-else class="text-[.7rem] font-bold text-muted uppercase">{{ p.name.slice(0, 2) }}</span>
        </div>
        <div class="flex-1 min-w-0">
          <div class="font-bold text-[.9rem] text-ink truncate">{{ p.name }}</div>
          <div class="flex items-center gap-1.5 mt-1">
            <input
              :value="meetingsFor(p.id)" type="number" min="0" class="m-0 w-20 py-1"
              @input="setMeetings(p.id, ($event.target as HTMLInputElement).valueAsNumber)"
            >
            <span class="text-[.82rem] text-muted">Meetings</span>
          </div>
        </div>
      </div>
    </div>
    <p v-if="!items.length" class="muted text-[.84rem] py-6 text-center">{{ emptyText }}</p>

    <div class="modal-actions">
      <button class="btn" :disabled="saving" @click="emit('save')">{{ saving ? 'Saving…' : 'SAVE' }}</button>
    </div>
  </Drawer>
</template>
