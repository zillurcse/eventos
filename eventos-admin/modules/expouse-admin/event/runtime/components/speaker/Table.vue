<script setup lang="ts">
import { ref } from 'vue'

interface SpeakerRow {
  id: string
  name: string
  email: string
  designation: string
  company: string
  category: string
  image_url: string | null
  has_login: boolean
}

defineProps<{
  speakers: SpeakerRow[]
  searching?: boolean
}>()

const emit = defineEmits<{
  (e: 'edit', s: SpeakerRow): void
  (e: 'remove', s: SpeakerRow): void
  (e: 'login', s: SpeakerRow): void
}>()

const columns = [
  { key: 'image', label: 'Image', width: '68px' },
  { key: 'name', label: 'Name' },
  { key: 'email', label: 'Email' },
  { key: 'designation', label: 'Designation' },
  { key: 'company', label: 'Company' },
]

function initials(name: string): string {
  return name.split(' ').slice(0, 2).map(w => w[0] ?? '').join('').toUpperCase()
}

const actionsFor = ref<string | null>(null)
</script>

<template>
  <DataTable
    :items="speakers"
    :columns="columns"
    row-key="id"
    storage-key="speakers"
    :empty-text="searching ? 'No speakers match your search.' : 'No speakers yet. Click + SPEAKERS to add one.'"
  >
    <template #cell-image="{ row }">
      <div class="w-10 h-10 rounded-full overflow-hidden shrink-0 bg-[#e8effb] flex items-center justify-center text-[#5b8def] font-semibold text-[.8rem]">
        <img v-if="row.image_url" :src="row.image_url" :alt="row.name" class="w-full h-full object-cover">
        <span v-else>{{ initials(row.name) }}</span>
      </div>
    </template>

    <template #cell-name="{ row }">
      <button
        class="bg-transparent border-0 p-0 cursor-pointer text-brand font-medium text-[.92rem] text-left"
        @click="emit('edit', row)"
      >{{ row.name }}</button>
      <span v-if="row.category" class="block mt-0.5 text-muted text-[.75rem]">{{ row.category }}</span>
    </template>

    <template #cell-email="{ row }">
      <span class="text-muted text-[.88rem]">{{ row.email }}</span>
      <!-- A speaker with no login can't sign in to the event site, which means
           they can't take the stage on their own session. -->
      <span
        v-if="!row.has_login"
        class="block mt-0.5 text-[.7rem] font-semibold uppercase tracking-wide text-[#b45309]"
      >No login</span>
    </template>

    <template #cell-designation="{ row }">
      <span class="text-ink text-[.88rem]">{{ row.designation || '—' }}</span>
    </template>

    <template #cell-company="{ row }">
      <span class="text-ink text-[.88rem]">{{ row.company || '—' }}</span>
    </template>

    <template #actions="{ row }">
      <div class="relative inline-block" @click.stop>
        <button class="w-8 h-8 rounded-lg grid place-items-center text-muted hover:bg-[#f1f2f6] border-0 bg-transparent cursor-pointer" aria-label="Actions" @click="actionsFor = actionsFor === row.id ? null : row.id">
          <svg viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
        </button>
        <div v-if="actionsFor === row.id" class="fixed inset-0 z-30" @click="actionsFor = null" />
        <div v-if="actionsFor === row.id" class="absolute right-0 top-full mt-1 bg-white border border-line rounded-xl shadow-lg z-40 min-w-48 overflow-hidden divide-y divide-line">
          <button class="w-full flex items-center gap-2.5 px-4 py-2.5 text-[.88rem] hover:bg-[#f7f8fa] text-ink transition-colors" @click="actionsFor = null; emit('edit', row)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-muted"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            Edit
          </button>
          <button
            class="w-full flex items-center gap-2.5 px-4 py-2.5 text-[.88rem] hover:bg-[#f7f8fa] transition-colors"
            :class="row.has_login ? 'text-ink' : 'text-[#b45309]'"
            @click="actionsFor = null; emit('login', row)"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            {{ row.has_login ? 'Reset Password' : 'Create Login' }}
          </button>
          <button class="w-full flex items-center gap-2.5 px-4 py-2.5 text-[.88rem] hover:bg-[#fef2f2] text-[#dc2626] transition-colors" @click="actionsFor = null; emit('remove', row)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
            Delete
          </button>
        </div>
      </div>
    </template>
  </DataTable>
</template>
