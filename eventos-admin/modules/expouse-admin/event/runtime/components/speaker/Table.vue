<script setup lang="ts">
interface SpeakerRow {
  id: string
  name: string
  email: string
  designation: string
  company: string
  category: string
  image_url: string | null
}

defineProps<{
  speakers: SpeakerRow[]
  searching?: boolean
}>()

const emit = defineEmits<{
  (e: 'edit', s: SpeakerRow): void
  (e: 'remove', s: SpeakerRow): void
}>()

function initials(name: string): string {
  return name.split(' ').slice(0, 2).map(w => w[0] ?? '').join('').toUpperCase()
}
</script>

<template>
  <table>
    <thead>
      <tr>
        <th>IMAGE</th>
        <th>NAME</th>
        <th>EMAIL</th>
        <th>DESIGNATION</th>
        <th>COMPANY</th>
        <th class="text-right">ACTIONS</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="s in speakers" :key="s.id">
        <!-- Image -->
        <td>
          <div class="w-10 h-10 rounded-full overflow-hidden shrink-0 bg-[#e8effb] flex items-center justify-center text-[#5b8def] font-semibold text-[.8rem]">
            <img v-if="s.image_url" :src="s.image_url" :alt="s.name" class="w-full h-full object-cover">
            <span v-else>{{ initials(s.name) }}</span>
          </div>
        </td>

        <!-- Name -->
        <td>
          <button
            class="bg-transparent border-0 p-0 cursor-pointer text-brand font-medium text-[.92rem] text-left"
            @click="emit('edit', s)"
          >{{ s.name }}</button>
          <span v-if="s.category" class="block mt-0.5 text-muted text-[.75rem]">{{ s.category }}</span>
        </td>

        <!-- Email -->
        <td class="text-muted text-[.88rem]">{{ s.email }}</td>

        <!-- Designation -->
        <td class="text-ink text-[.88rem]">{{ s.designation || '—' }}</td>

        <!-- Company -->
        <td class="text-ink text-[.88rem]">{{ s.company || '—' }}</td>

        <!-- Actions -->
        <td class="text-right whitespace-nowrap">
          <button
            class="bg-transparent border-0 cursor-pointer p-1.5 text-[#5f6b7a] hover:text-brand align-middle"
            title="Edit"
            @click="emit('edit', s)"
          >
            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9" /><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
          </button>
          <button
            class="bg-transparent border-0 cursor-pointer p-1.5 text-[#dc2626] hover:opacity-70 align-middle"
            title="Remove"
            @click="emit('remove', s)"
          >
            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18" /><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /><path d="M10 11v6M14 11v6" /></svg>
          </button>
        </td>
      </tr>

      <tr v-if="!speakers.length">
        <td colspan="6" class="muted text-center py-8">
          {{ searching ? 'No speakers match your search.' : 'No speakers yet. Click + SPEAKERS to add one.' }}
        </td>
      </tr>
    </tbody>
  </table>
</template>
