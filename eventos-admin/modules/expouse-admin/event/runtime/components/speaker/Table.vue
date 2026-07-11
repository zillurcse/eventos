<script setup lang="ts">
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
      <button
        class="bg-transparent border-0 cursor-pointer p-1.5 align-middle"
        :class="row.has_login ? 'text-[#5f6b7a] hover:text-brand' : 'text-[#b45309] hover:opacity-70'"
        :title="row.has_login ? 'Reset login password' : 'Create a login so they can host their session'"
        @click="emit('login', row)"
      >
        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
      </button>
      <button
        class="bg-transparent border-0 cursor-pointer p-1.5 text-[#5f6b7a] hover:text-brand align-middle"
        title="Edit"
        @click="emit('edit', row)"
      >
        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9" /><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
      </button>
      <button
        class="bg-transparent border-0 cursor-pointer p-1.5 text-[#dc2626] hover:opacity-70 align-middle"
        title="Remove"
        @click="emit('remove', row)"
      >
        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18" /><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /><path d="M10 11v6M14 11v6" /></svg>
      </button>
    </template>
  </DataTable>
</template>
