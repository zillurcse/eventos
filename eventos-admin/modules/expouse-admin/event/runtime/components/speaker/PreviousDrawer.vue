<script setup lang="ts">
import { toast } from 'vue-sonner'

/**
 * PREVIOUS SPEAKERS — pick people who have spoken at the organizer's other
 * events and re-seat them at this one. Self-contained: the page only says when
 * to show it, and reloads its list when something was imported.
 *
 * Candidates come back one row per person (most recent appearance) and flagged
 * `already_added` when they already speak here — those stay visible but greyed
 * out, because "why isn't Grace in the list?" is worse than seeing her marked.
 */
interface Candidate {
  id: string
  name: string
  email: string
  image_url: string | null
  designation: string
  company: string
  category: string
  presentation_title: string
  event: { id: string, name: string, starts_at: string | null }
  already_added: boolean
}

const props = defineProps<{ eventId: string }>()
const emit = defineEmits<{ (e: 'close'): void, (e: 'imported'): void }>()

const api = useApi()

const loading = ref(false)
const importing = ref(false)
const error = ref('')
const candidates = ref<Candidate[]>([])
const selected = ref<string[]>([])
const search = ref('')

// Off by default: they are giving a new talk this year, so last year's title and
// deck are the exception, not the rule.
const include = reactive({ presentation: false })

const importable = computed(() => candidates.value.filter(c => !c.already_added))

const visible = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return candidates.value
  return candidates.value.filter(c =>
    [c.name, c.email, c.company, c.event.name].some(f => (f ?? '').toLowerCase().includes(q)),
  )
})

const allSelected = computed(() =>
  importable.value.length > 0 && selected.value.length === importable.value.length,
)

function initials(name: string) {
  const parts = (name || '').trim().split(/\s+/)
  return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')) || '?'
}

function eventYear(iso: string | null) {
  return iso ? new Date(iso).getFullYear() : ''
}

function toggle(c: Candidate) {
  if (c.already_added) return
  selected.value = selected.value.includes(c.id)
    ? selected.value.filter(id => id !== c.id)
    : [...selected.value, c.id]
}

function toggleAll() {
  selected.value = allSelected.value ? [] : importable.value.map(c => c.id)
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const r = await api<{ data: Candidate[] }>(`/events/${props.eventId}/speakers/importable`)
    candidates.value = r.data
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not load your previous speakers.'
  } finally {
    loading.value = false
  }
}

async function runImport() {
  if (!selected.value.length || importing.value) return

  importing.value = true
  error.value = ''
  try {
    const r = await api<{ meta: { imported: number, skipped: { name: string, reason: string }[] } }>(
      `/events/${props.eventId}/speakers/import`,
      { method: 'POST', body: { speakers: selected.value, include } },
    )

    const { imported, skipped } = r.meta
    toast.success(
      `${imported} speaker${imported === 1 ? '' : 's'} imported`,
      skipped.length ? { description: `${skipped.length} skipped — already speaking here.` } : undefined,
    )

    emit('imported')
    emit('close')
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not import.'
    toast.error(error.value)
  } finally {
    importing.value = false
  }
}

onMounted(load)
</script>

<template>
  <Drawer title="Previous Speakers" @close="emit('close')">
    <p class="muted text-[.85rem] mt-0 mb-4">
      People who have spoken at your other events. Importing copies their profile —
      photo, bio, title and socials. Their sessions are not carried over.
    </p>

    <AppInput v-model="search" placeholder="Search by name, company or event" class="w-full mb-3" />

    <div class="border border-line rounded-xl p-4 mb-4 bg-[#f7f8fa]">
      <AppCheckbox v-model="include.presentation" label="Also copy their presentation title and deck" />
      <p class="muted text-[.78rem] mt-1.5 mb-0">
        Off by default — most speakers give a new talk. Their login always carries over.
      </p>
    </div>

    <div v-if="importable.length" class="flex items-center justify-between mb-2">
      <button class="btn ghost sm" @click="toggleAll">
        {{ allSelected ? 'Clear selection' : `Select all (${importable.length})` }}
      </button>
      <span class="muted text-[.82rem]">{{ selected.length }} selected</span>
    </div>

    <p v-if="loading" class="muted text-[.85rem]">Loading…</p>
    <p v-else-if="!visible.length" class="muted text-[.85rem] py-6 text-center">
      {{ search ? 'No speaker matches that search.' : 'You have no speakers at any other event yet.' }}
    </p>

    <div v-else class="flex flex-col gap-2">
      <label
        v-for="c in visible" :key="c.id"
        class="flex items-center gap-3 border border-line rounded-xl p-3 transition-colors"
        :class="c.already_added
          ? 'opacity-55 cursor-default bg-[#f7f8fa]'
          : selected.includes(c.id) ? 'border-brand bg-brand-soft cursor-pointer' : 'cursor-pointer hover:border-brand'"
      >
        <input
          type="checkbox" class="w-4.5 h-4.5 m-0 accent-brand shrink-0"
          :checked="selected.includes(c.id)"
          :disabled="c.already_added"
          @change="toggle(c)"
        >

        <div class="w-10 h-10 rounded-full overflow-hidden shrink-0">
          <img v-if="c.image_url" :src="c.image_url" class="w-full h-full object-cover" :alt="c.name">
          <div v-else class="w-full h-full bg-brand-soft flex items-center justify-center text-brand font-bold text-[.75rem] uppercase">
            {{ initials(c.name) }}
          </div>
        </div>

        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <span class="font-semibold text-ink text-[.9rem] truncate">{{ c.name }}</span>
            <span v-if="c.already_added" class="badge">Already added</span>
          </div>
          <div class="muted text-[.78rem] truncate">
            {{ [c.designation, c.company].filter(Boolean).join(' · ') || c.email }}
          </div>
          <div class="muted text-[.76rem] mt-0.5 truncate">
            {{ c.event.name }} {{ eventYear(c.event.starts_at) }}
            <span v-if="c.presentation_title"> · “{{ c.presentation_title }}”</span>
          </div>
        </div>
      </label>
    </div>

    <p v-if="error" class="error mt-3">{{ error }}</p>

    <div class="pt-4 mt-2 sticky bottom-0 bg-white">
      <button
        class="btn w-full py-3 text-[.95rem] tracking-widest"
        :disabled="!selected.length || importing"
        @click="runImport"
      >
        {{ importing ? 'IMPORTING…' : selected.length ? `IMPORT ${selected.length} SPEAKER${selected.length === 1 ? '' : 'S'}` : 'IMPORT' }}
      </button>
    </div>
  </Drawer>
</template>
