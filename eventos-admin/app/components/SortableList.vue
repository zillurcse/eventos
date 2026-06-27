<script setup lang="ts">
interface Item { key: string, label: string, enabled: boolean }
const props = defineProps<{ modelValue: Item[], editable?: boolean }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: Item[]): void }>()

const dragIndex = ref<number | null>(null)
const editingIndex = ref<number | null>(null)

function set(v: Item[]) { emit('update:modelValue', v) }
function onDragStart(i: number) { dragIndex.value = i }
function onDragOver(i: number, e: DragEvent) {
  e.preventDefault()
  if (dragIndex.value === null || dragIndex.value === i) return
  const arr = [...props.modelValue]
  const [moved] = arr.splice(dragIndex.value, 1)
  arr.splice(i, 0, moved)
  dragIndex.value = i
  set(arr)
}
function onDragEnd() { dragIndex.value = null }
function toggle(i: number) { const arr = [...props.modelValue]; arr[i] = { ...arr[i], enabled: !arr[i].enabled }; set(arr) }
function rename(i: number, val: string) { const arr = [...props.modelValue]; arr[i] = { ...arr[i], label: val }; set(arr) }
</script>

<template>
  <div class="flex flex-col gap-2.5">
    <div
      v-for="(it, i) in modelValue" :key="it.key"
      class="flex items-center gap-3 px-4 py-[13px] border border-line rounded-xl bg-white"
      :class="{ 'opacity-50 border-[#6352e7]': dragIndex === i }"
      draggable="true" @dragstart="onDragStart(i)" @dragover="onDragOver(i, $event)" @dragend="onDragEnd"
    >
      <span class="cursor-grab text-[#b8bcc6] text-[1.05rem] select-none">⠿</span>
      <input
        v-if="editable && editingIndex === i" :value="it.label"
        class="flex-1 m-0 px-2 py-1.5 font-semibold"
        @input="rename(i, ($event.target as HTMLInputElement).value)"
        @blur="editingIndex = null" @keyup.enter="editingIndex = null"
      >
      <span v-else class="flex-1 font-bold text-[.9rem]" :class="editable ? 'text-[#6352e7]' : 'text-[#475569]'">{{ it.label }}</span>
      <button v-if="editable" class="bg-transparent border-none text-[#8b93ff] cursor-pointer text-[.95rem] px-1.5 py-0.5" title="Rename" @click="editingIndex = editingIndex === i ? null : i">✎</button>
      <button
        class="w-7 h-7 rounded-full border-2 grid place-items-center cursor-pointer shrink-0"
        :class="it.enabled ? 'bg-[#6352e7] border-[#6352e7]' : 'bg-white border-[#d7dae1]'"
        :title="it.enabled ? 'Active' : 'Inactive'" @click="toggle(i)"
      >
        <svg v-if="it.enabled" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
      </button>
    </div>
  </div>
</template>
