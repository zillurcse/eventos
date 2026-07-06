<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface EventHighlight {
  id: string
  name: string
  icon: string
  count: string
}

const DEFAULT_ICON = 'pie'

const highlights = ref<EventHighlight[]>([])
const drawerOpen = ref(false)
const editingId = ref<string | null>(null)
const saved = ref(false)
const openMenuId = ref<string | null>(null)
const iconChooserOpen = ref(false)

const draft = reactive<EventHighlight>({ id: '', name: '', icon: DEFAULT_ICON, count: '' })

async function load() {
  try { highlights.value = (await api<any>(`/events/${id}/settings`)).data.event_highlights || [] } catch { /* */ }
}

async function persist() {
  await api(`/events/${id}/settings`, { method: 'PUT', body: { event_highlights: JSON.parse(JSON.stringify(highlights.value)) } })
  saved.value = true; setTimeout(() => (saved.value = false), 1500)
}

function openAdd() {
  editingId.value = null
  Object.assign(draft, { id: 'h' + Date.now(), name: '', icon: DEFAULT_ICON, count: '' })
  drawerOpen.value = true
}

function openEdit(h: EventHighlight) {
  openMenuId.value = null
  editingId.value = h.id
  Object.assign(draft, { ...h })
  drawerOpen.value = true
}

async function saveDraft() {
  if (!draft.name.trim()) return
  const clean: EventHighlight = JSON.parse(JSON.stringify(draft))
  clean.name = clean.name.trim()
  if (editingId.value) {
    const i = highlights.value.findIndex(h => h.id === editingId.value)
    if (i >= 0) highlights.value[i] = clean
  } else {
    highlights.value.push(clean)
  }
  await persist()
  drawerOpen.value = false
}

async function removeHighlight(h: EventHighlight) {
  openMenuId.value = null
  if (!confirm(`Remove highlight "${h.name}"?`)) return
  highlights.value = highlights.value.filter(x => x.id !== h.id)
  await persist()
}

function toggleMenu(id: string) {
  openMenuId.value = openMenuId.value === id ? null : id
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-4">
      <h2 class="section-title m-0">Event Highlights</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Key numbers featured on the event landing page.</p>
    </div>

    <div class="card">
      <div class="flex items-center justify-between gap-4 mb-5">
        <div>
          <div class="font-bold text-base">
            Event Highlights
            <span v-if="saved" class="badge active ml-2">saved ✓</span>
          </div>
          <div class="muted text-[.84rem]">Shown as stat cards on the event landing page.</div>
        </div>
        <button class="btn" @click="openAdd">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
          ADD HIGHLIGHT
        </button>
      </div>

      <div v-if="highlights.length" class="grid grid-cols-3 gap-4">
        <div
          v-for="h in highlights" :key="h.id"
          class="relative rounded-xl border border-line p-4 bg-[#fafbfc]"
        >
          <div class="flex justify-end" @click.stop="toggleMenu(h.id)">
            <div class="relative w-6.5 h-6.5 bg-white rounded-lg cursor-pointer flex items-center justify-center">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="text-muted"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
              <div
                v-if="openMenuId === h.id"
                class="absolute right-0 top-full mt-1 bg-white border border-line rounded-xl shadow-lg z-20 min-w-36 overflow-hidden"
                @click.stop
              >
                <button class="w-full text-left px-4 py-2.5 text-[.85rem] hover:bg-[#f7f8fa] text-ink" @click="openEdit(h)">Edit</button>
                <button class="w-full text-left px-4 py-2.5 text-[.85rem] hover:bg-[#f7f8fa] text-[#dc2626]" @click="removeHighlight(h)">Delete</button>
              </div>
            </div>
          </div>
          <div class="py-2.5 flex justify-center">
            <AppIcon :name="h.icon" class="w-9 h-9 text-ink" />
          </div>
          <div class="flex justify-center pb-0.5">
            <h3 class="font-bold text-[.9rem] text-ink text-center">{{ h.name }}</h3>
          </div>
          <div class="flex justify-center">
            <p class="muted text-[.82rem]">{{ h.count }}</p>
          </div>
        </div>
      </div>

      <p v-else class="muted text-[.86rem] py-10 text-center">
        No highlights yet. Click <strong>+ ADD HIGHLIGHT</strong> to get started.
      </p>
    </div>

    <!-- Add / Edit drawer -->
    <Drawer
      v-if="drawerOpen"
      :title="editingId ? 'Edit Event Highlight' : 'Add Event Highlight'"
      @close="drawerOpen = false"
    >
      <label class="block mb-2">Icon</label>
      <button
        type="button"
        class="w-20 h-20 rounded-xl border border-dashed border-[#d7dae1] flex items-center justify-center bg-[#fafbfc] cursor-pointer hover:border-brand mb-4"
        @click="iconChooserOpen = true"
      >
        <AppIcon :name="draft.icon" class="w-8 h-8 text-ink" />
      </button>

      <label>
        Name
        <span class="text-[#dc2626] ml-0.5">*</span>
      </label>
      <input v-model="draft.name" placeholder="e.g. Attendees">

      <label>Highlight Count</label>
      <input v-model="draft.count" placeholder="e.g. 5000+">

      <div class="modal-actions">
        <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
        <button class="btn" :disabled="!draft.name.trim()" @click="saveDraft">
          {{ editingId ? 'UPDATE' : 'ADD' }}
        </button>
      </div>
    </Drawer>

    <IconChooserModal
      v-if="iconChooserOpen"
      :model-value="draft.icon"
      title="Choose Highlight Icon"
      @select="draft.icon = $event"
      @close="iconChooserOpen = false"
    />
  </div>
</template>
