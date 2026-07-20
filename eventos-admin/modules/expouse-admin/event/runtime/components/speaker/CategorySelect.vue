<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'

interface SpeakerCategory {
  id: string
  name: string
}

defineProps<{
  modelValue?: string | null
  categories?: SpeakerCategory[]
  busy?: boolean
  placeholder?: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: string): void
  (e: 'add', name: string): void
  (e: 'rename', payload: { id: string, name: string }): void
  (e: 'remove', id: string): void
}>()

const root      = ref<HTMLElement | null>(null)
const open      = ref(false)
const newName   = ref('')
const editingId = ref<string | null>(null)
const editName  = ref('')

function toggle() {
  open.value = !open.value
}

function select(name: string) {
  emit('update:modelValue', name)
  open.value = false
}

function submitNew() {
  const val = newName.value.trim()
  if (!val) return
  emit('add', val)
  newName.value = ''
}

function startEdit(cat: SpeakerCategory) {
  editingId.value = cat.id
  editName.value = cat.name
}

function submitEdit(cat: SpeakerCategory) {
  const val = editName.value.trim()
  if (val && val !== cat.name) emit('rename', { id: cat.id, name: val })
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
  <div ref="root">
    <!-- Trigger: chevron on the left, selected value (if any) after it -->
    <button
      type="button"
      class="w-full flex items-center gap-3 bg-white border rounded-[11px] px-[15px] py-3 text-left transition-colors"
      :class="open ? 'border-brand' : 'border-[#d7dae1]'"
      @click="toggle"
    >
      <span class="text-[#5f6b7a] text-[.6rem] leading-none order-2 ml-auto">▼</span>
      <span v-if="modelValue" class="text-ink text-[.9rem] order-1">{{ modelValue }}</span>
      <span v-else-if="placeholder" class="text-[#9aa0ac] text-[.9rem] order-1">{{ placeholder }}</span>
    </button>

    <!-- Panel (in-flow so it is never clipped by the drawer's scroll area) -->
    <div v-if="open" class="mt-2 bg-white border border-[#d7dae1] rounded-[11px] p-3">
      <!-- Add row -->
      <div class="flex items-center gap-2 border border-[#e3e3ee] rounded-[10px] px-2.5 py-2 mb-1">
        <input
          v-model="newName"
          placeholder="Enter Category Name"
          class="flex-1 m-0 border-0 px-0 py-0.5 focus:outline-0 bg-transparent text-[.9rem] placeholder:text-[#9aa0ac]"
          @keydown.enter.prevent="submitNew"
        >
        <button
          type="button"
          class="px-3.5 py-1.5 rounded-[8px] bg-brand-soft text-brand font-semibold text-[.78rem] tracking-wide disabled:opacity-50"
          :disabled="!newName.trim() || busy"
          @click="submitNew"
        >ADD</button>
      </div>

      <!-- Category list -->
      <div class="max-h-56 overflow-auto">
        <div
          v-for="cat in categories"
          :key="cat.id"
          class="flex items-center gap-2 px-1 py-2.5 border-b border-[#f0f0f5] last:border-b-0"
        >
          <!-- Editing -->
          <template v-if="editingId === cat.id">
            <input
              v-model="editName"
              class="flex-1 m-0 border border-line rounded-md px-2 py-1 focus:outline-0 text-[.9rem]"
              @keydown.enter.prevent="submitEdit(cat)"
              @keydown.esc="editingId = null"
            >
            <button type="button" class="text-brand text-[.8rem] px-1.5" @click="submitEdit(cat)">Save</button>
            <button type="button" class="text-muted text-[.8rem] px-1.5" @click="editingId = null">Cancel</button>
          </template>

          <!-- Display -->
          <template v-else>
            <button
              type="button"
              class="flex-1 text-left text-[.9rem]"
              :class="modelValue === cat.name ? 'text-brand font-semibold' : 'text-ink'"
              @click="select(cat.name)"
            >{{ cat.name }}</button>
            <button
              type="button"
              class="text-[#3b82f6] p-1 hover:opacity-70"
              title="Rename"
              @click="startEdit(cat)"
            >
              <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9" /><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
            </button>
            <button
              type="button"
              class="text-[#dc2626] p-1 hover:opacity-70"
              title="Delete"
              @click="emit('remove', cat.id)"
            >
              <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18" /><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /><path d="M10 11v6M14 11v6" /></svg>
            </button>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>
