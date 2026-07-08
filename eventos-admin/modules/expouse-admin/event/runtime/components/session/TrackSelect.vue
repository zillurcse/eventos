<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'

interface Track { id: number; name: string; color: string }

defineProps<{
  modelValue?: number | ''
  tracks?: Track[]
  busy?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: number | ''): void
  (e: 'create', name: string): void
  (e: 'rename', payload: { id: number, name: string }): void
  (e: 'remove', id: number): void
}>()

const root            = ref<HTMLElement | null>(null)
const open            = ref(false)
const newName         = ref('')
const editingId       = ref<number | null>(null)
const editingName     = ref('')

function toggle() {
  open.value = !open.value
  editingId.value = null
}

function select(id: number | '') {
  emit('update:modelValue', id)
  open.value = false
}

function submitNew() {
  const val = newName.value.trim()
  if (!val) return
  emit('create', val)
  newName.value = ''
}

function startEdit(track: Track) {
  editingId.value = track.id
  editingName.value = track.name
}

function submitEdit(track: Track) {
  const val = editingName.value.trim()
  if (val) emit('rename', { id: track.id, name: val })
  editingId.value = null
}

// Close when clicking outside the component.
function onDocClick(e: MouseEvent) {
  if (open.value && root.value && !root.value.contains(e.target as Node)) {
    open.value = false
    editingId.value = null
  }
}
onMounted(() => document.addEventListener('click', onDocClick))
onBeforeUnmount(() => document.removeEventListener('click', onDocClick))
</script>

<template>
  <div ref="root" class="relative">
    <button
      type="button"
      class="w-full flex items-center justify-between px-3 py-2 border border-line rounded-xl bg-white text-[.9rem]"
      @click.stop="toggle"
    >
      <span>{{ tracks?.find(t => t.id === modelValue)?.name ?? 'Select' }}</span>
      <span class="text-muted text-xs">▾</span>
    </button>

    <div
      v-if="open"
      class="absolute left-0 right-0 top-full mt-1 z-20 bg-white border border-line rounded-xl shadow-lg py-1 max-h-56 overflow-y-auto"
      @click.stop
    >
      <button
        class="w-full text-left px-4 py-2 text-[.88rem] hover:bg-[#f7f7fb]"
        :class="!modelValue ? 'font-semibold text-brand' : ''"
        @click="select('')"
      >— No track —</button>

      <div
        v-for="t in tracks"
        :key="t.id"
        class="flex items-center gap-1.5 px-3 py-1 hover:bg-[#f7f7fb]"
      >
        <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: t.color || '#6352e7' }" />
        <template v-if="editingId === t.id">
          <input
            v-model="editingName"
            class="flex-1 m-0 py-0.5 text-[.87rem] border-b border-brand focus:outline-none bg-transparent"
            @keydown.enter="submitEdit(t)"
            @keydown.escape="editingId = null"
          >
          <button class="text-brand text-[.85rem] px-1 hover:opacity-70" @click="submitEdit(t)">✓</button>
        </template>
        <template v-else>
          <button
            class="flex-1 text-left text-[.88rem] py-0.5"
            :class="modelValue === t.id ? 'font-semibold text-brand' : ''"
            @click="select(t.id)"
          >{{ t.name }}</button>
          <button class="text-muted text-[.78rem] hover:text-brand px-1 leading-none" @click.stop="startEdit(t)" title="Rename">✎</button>
          <button class="text-muted text-[.78rem] hover:text-[#dc2626] px-1 leading-none" @click.stop="emit('remove', t.id)" title="Delete">✕</button>
        </template>
      </div>

      <div class="border-t border-line mt-1 pt-1 px-3 pb-1">
        <div class="flex gap-1.5">
          <input
            v-model="newName"
            placeholder="Enter track name"
            class="flex-1 m-0 py-1 text-[.87rem]"
            @keydown.enter="submitNew"
          >
          <button class="btn sm" :disabled="!newName.trim() || busy" @click="submitNew">ADD</button>
        </div>
      </div>
    </div>
  </div>
</template>
