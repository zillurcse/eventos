<script setup lang="ts">
const props = defineProps<{
  // Which entity this modal creates. Only entities with a clean single-step
  // create endpoint are handled here; everything else navigates instead.
  type: 'session' | 'room'
  eventId: string
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'created', count?: number): void
}>()

const api = useApi()

const CONFIG = {
  session: {
    title: 'Add Session',
    subtitle: 'Create a session — you can flesh out the rest in the agenda.',
    nameLabel: 'Session title',
    namePlaceholder: 'e.g. Opening Keynote',
    hasSchedule: true,
  },
  room: {
    title: 'Add Breakout Room',
    subtitle: 'Spin up a live breakout room for smaller group sessions.',
    nameLabel: 'Room name',
    namePlaceholder: 'e.g. Networking Lounge A',
    hasSchedule: false,
  },
} as const

const cfg = computed(() => CONFIG[props.type])

const name = ref('')
const startsAt = ref('')
const endsAt = ref('')
const saving = ref(false)
const error = ref('')

const canSave = computed(() => name.value.trim().length > 0 && !saving.value)

async function save() {
  if (!canSave.value) return
  saving.value = true
  error.value = ''
  try {
    if (props.type === 'session') {
      await api('/sessions', {
        method: 'POST',
        body: {
          event: props.eventId,
          title: name.value.trim(),
          starts_at: startsAt.value || null,
          ends_at: endsAt.value || null,
        },
      })
    } else {
      await api(`/events/${props.eventId}/breakout-rooms`, {
        method: 'POST',
        body: { name: name.value.trim(), status: 'published' },
      })
    }
    emit('created')
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not create. Please try again.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div
    class="fixed inset-0 bg-black/40 flex items-center justify-center z-[60] p-4"
    @click.self="emit('close')"
  >
    <div class="bg-white rounded-2xl w-full max-w-md flex flex-col overflow-hidden shadow-[0_20px_60px_rgba(0,0,0,0.2)]">
      <!-- Header -->
      <div class="flex items-start justify-between p-5 border-b border-line">
        <div class="min-w-0">
          <div class="font-bold text-[1.05rem] text-ink">{{ cfg.title }}</div>
          <p class="text-muted text-[.82rem] mt-0.5">{{ cfg.subtitle }}</p>
        </div>
        <button
          class="w-8 h-8 rounded-lg hover:bg-[#f5f6f8] grid place-items-center text-faint cursor-pointer shrink-0 -mr-1 -mt-1"
          @click="emit('close')"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
      </div>

      <!-- Body -->
      <div class="p-5 flex flex-col gap-4">
        <AppInput
          v-model="name"
          :label="cfg.nameLabel"
          :placeholder="cfg.namePlaceholder"
          required
          @keyup.enter="save"
        />

        <div v-if="cfg.hasSchedule" class="grid grid-cols-2 gap-3">
          <AppInput v-model="startsAt" label="Starts" type="datetime-local" />
          <AppInput v-model="endsAt" label="Ends" type="datetime-local" />
        </div>

        <p v-if="error" class="text-[.82rem] text-[#dc2626]">{{ error }}</p>
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-end gap-2.5 p-5 pt-0">
        <button
          class="px-4 py-2.5 rounded-[11px] text-[.85rem] font-semibold text-ink bg-white border border-line hover:bg-[#f5f6f8] cursor-pointer"
          @click="emit('close')"
        >Cancel</button>
        <button
          class="px-5 py-2.5 rounded-[11px] text-[.85rem] font-semibold text-white bg-brand hover:bg-brand-dark cursor-pointer transition-opacity"
          :class="canSave ? '' : 'opacity-50 pointer-events-none'"
          @click="save"
        >{{ saving ? 'Adding…' : 'Add' }}</button>
      </div>
    </div>
  </div>
</template>
