<script setup lang="ts">
interface AttendeeTable {
  id: string
  name: string
  capacity: number
  image_file_id: number | null
  image_url: string | null
  design: string          // round | boardroom | lounge
  accent: string | null   // hex accent, null = event brand color
}

const props = defineProps<{
  tables: AttendeeTable[]
  saving: boolean
}>()

const emit = defineEmits<{
  (e: 'save'): void
  (e: 'close'): void
}>()

const { upload } = useUpload()

const TABLE_DESIGNS = [
  { key: 'round', label: 'Round' },
  { key: 'boardroom', label: 'Boardroom' },
  { key: 'lounge', label: 'Lounge' },
]

function addTable() {
  props.tables.push({ id: 't' + Date.now(), name: 'New table', capacity: 4, image_file_id: null, image_url: null, design: 'round', accent: null })
}
function removeTable(i: number) { props.tables.splice(i, 1) }
async function uploadTableImage(e: Event, t: AttendeeTable) {
  const f = (e.target as HTMLInputElement).files?.[0]
  if (!f) return
  const r = await upload(f, { collection: 'lounge' })
  t.image_file_id = r.id; t.image_url = r.url
}

const dragIndex = ref<number | null>(null)
function onDragStart(i: number) { dragIndex.value = i }
function onDragOver(i: number, e: DragEvent) {
  e.preventDefault()
  if (dragIndex.value === null || dragIndex.value === i) return
  const [moved] = props.tables.splice(dragIndex.value, 1)
  props.tables.splice(i, 0, moved)
  dragIndex.value = i
}
function onDragEnd() { dragIndex.value = null }
</script>

<template>
  <Drawer title="Attendee Tables" @close="emit('close')">
    <div class="flex justify-end -mt-2 mb-3">
      <button class="text-brand font-semibold text-[.88rem] bg-transparent border-0 cursor-pointer" @click="addTable">+ Add Table</button>
    </div>

    <div class="flex flex-col gap-2.5">
      <div
        v-for="(t, i) in tables" :key="t.id"
        class="flex items-center gap-3 border border-line rounded-xl p-3 bg-white"
        :class="{ 'opacity-50 border-brand': dragIndex === i }"
        draggable="true" @dragstart="onDragStart(i)" @dragover="onDragOver(i, $event)" @dragend="onDragEnd"
      >
        <span class="cursor-grab text-[#b8bcc6] select-none">⠿</span>
        <label class="w-12 h-12 rounded-lg overflow-hidden bg-[#f3f4f6] border border-line grid place-items-center cursor-pointer shrink-0">
          <img v-if="t.image_url" :src="t.image_url" alt="" class="w-full h-full object-cover">
          <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#9aa0ad" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
          <input type="file" accept="image/*" class="hidden" @change="uploadTableImage($event, t)">
        </label>
        <div class="flex-1 flex flex-col gap-2">
          <input v-model="t.name" class="m-0" placeholder="Table name">
          <div class="flex items-center gap-2 flex-wrap">
            <input v-model.number="t.capacity" type="number" min="0" class="m-0 w-20">
            <span class="text-[.82rem] text-muted">Chairs</span>
            <span class="text-[.82rem] text-[#d1d5db]">·</span>
            <div class="inline-flex rounded-lg border border-line overflow-hidden">
              <button
                v-for="d in TABLE_DESIGNS" :key="d.key" type="button"
                class="px-2.5 py-1 text-[.76rem] font-semibold cursor-pointer transition-colors"
                :class="t.design === d.key ? 'bg-brand text-white' : 'bg-white text-muted hover:bg-brand-soft'"
                @click="t.design = d.key"
              >{{ d.label }}</button>
            </div>
            <input
              :value="t.accent || '#6366f1'" type="color" title="Accent color (clear to use event brand)"
              class="w-7 h-7 rounded-md border border-line cursor-pointer p-0 bg-transparent"
              @input="t.accent = ($event.target as HTMLInputElement).value"
            >
            <button v-if="t.accent" type="button" class="text-[.72rem] text-[#9aa0ad] hover:text-[#dc2626] cursor-pointer bg-transparent border-0" @click="t.accent = null">clear</button>
          </div>
        </div>
        <button class="text-[#dc2626] bg-transparent border-0 cursor-pointer p-1 self-start" title="Remove" @click="removeTable(i)">🗑</button>
      </div>
    </div>
    <p v-if="!tables.length" class="muted text-[.84rem] py-6 text-center">No tables yet. Click <strong>+ Add Table</strong>.</p>

    <div class="modal-actions">
      <button class="btn" :disabled="saving" @click="emit('save')">{{ saving ? 'Saving…' : 'SAVE' }}</button>
    </div>
  </Drawer>
</template>
