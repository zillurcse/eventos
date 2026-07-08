<script setup lang="ts">
import { ref, computed } from 'vue'

interface EventSpeaker {
  id: string; name: string; email: string
  designation: string; image_url: string | null
}

const props = defineProps<{
  speakers: EventSpeaker[]
  selectedIds: string[]
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'toggle', id: string): void
}>()

const search = ref('')

const filtered = computed(() => {
  const q = search.value.toLowerCase()
  return q ? props.speakers.filter(s => s.name?.toLowerCase().includes(q)) : props.speakers
})

function initials(name: string | null | undefined): string {
  if (!name) return '?'
  return name.split(' ').slice(0, 2).map(w => w[0] ?? '').join('').toUpperCase()
}
</script>

<template>
  <div
    class="fixed inset-0 bg-black/40 flex items-center justify-center z-[60] p-4"
    @click.self="emit('close')"
  >
    <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[80vh] flex flex-col overflow-hidden">
      <div class="flex items-start justify-between p-5 border-b border-line">
        <div>
          <div class="font-bold text-[1.05rem] text-ink">Speakers</div>
          <div class="muted text-[.84rem]">Choose speakers</div>
        </div>
        <button class="text-muted hover:text-ink text-[1.3rem] leading-none" @click="emit('close')">×</button>
      </div>
      <div class="p-5 pb-3">
        <input v-model="search" placeholder="Search" class="m-0 w-full">
      </div>
      <div class="px-5 overflow-y-auto flex-1">
        <div v-if="!filtered.length" class="text-center muted py-10 text-[.88rem]">
          No speakers found. Add them in <strong>Showcase › Speakers</strong> first.
        </div>
        <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 pb-4">
          <button
            v-for="sp in filtered"
            :key="sp.id"
            type="button"
            class="border-2 rounded-xl p-2 flex flex-col items-center gap-2 transition-colors"
            :class="selectedIds.includes(sp.id) ? 'border-brand bg-brand-soft' : 'border-line hover:bg-[#fafbfc]'"
            @click="emit('toggle', sp.id)"
          >
            <div class="w-full aspect-square rounded-lg overflow-hidden bg-brand-soft text-brand flex items-center justify-center text-[1.4rem] font-bold">
              <img v-if="sp.image_url" :src="sp.image_url" :alt="sp.name" class="w-full h-full object-cover">
              <span v-else>{{ initials(sp.name) }}</span>
            </div>
            <span class="text-[.8rem] font-medium text-center truncate w-full">{{ sp.name }}</span>
          </button>
        </div>
      </div>
      <div class="flex justify-end gap-3 p-4 border-t border-line">
        <button class="btn ghost" @click="emit('close')">Cancel</button>
        <button class="btn" @click="emit('close')">SELECT</button>
      </div>
    </div>
  </div>
</template>
