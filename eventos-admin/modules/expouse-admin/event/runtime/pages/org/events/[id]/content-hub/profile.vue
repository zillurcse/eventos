<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface ParticipantProfile {
  id: string
  name: string
  icon: string
  active: boolean
}

const DEFAULT_ICON = 'users'

const profiles = ref<ParticipantProfile[]>([])
const drawerOpen = ref(false)
const editingId = ref<string | null>(null)
const saved = ref(false)
const openMenuId = ref<string | null>(null)
const iconChooserOpen = ref(false)

const draft = reactive<ParticipantProfile>({ id: '', name: '', icon: DEFAULT_ICON, active: true })

async function load() {
  try { profiles.value = (await api<any>(`/events/${id}/settings`)).data.participant_profiles || [] } catch { /* */ }
}

async function persist() {
  await api(`/events/${id}/settings`, { method: 'PUT', body: { participant_profiles: JSON.parse(JSON.stringify(profiles.value)) } })
  saved.value = true; setTimeout(() => (saved.value = false), 1500)
}

function openAdd() {
  editingId.value = null
  Object.assign(draft, { id: 'p' + Date.now(), name: '', icon: DEFAULT_ICON, active: true })
  drawerOpen.value = true
}

function openEdit(p: ParticipantProfile) {
  openMenuId.value = null
  editingId.value = p.id
  Object.assign(draft, { ...p })
  drawerOpen.value = true
}

async function saveDraft() {
  if (!draft.name.trim()) return
  const clean: ParticipantProfile = JSON.parse(JSON.stringify(draft))
  clean.name = clean.name.trim()
  if (editingId.value) {
    const i = profiles.value.findIndex(p => p.id === editingId.value)
    if (i >= 0) profiles.value[i] = clean
  } else {
    profiles.value.push(clean)
  }
  await persist()
  drawerOpen.value = false
}

async function removeProfile(p: ParticipantProfile) {
  openMenuId.value = null
  if (!confirm(`Remove participant profile "${p.name}"?`)) return
  profiles.value = profiles.value.filter(x => x.id !== p.id)
  await persist()
}

async function toggleActive(p: ParticipantProfile) {
  openMenuId.value = null
  p.active = !p.active
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
      <h2 class="section-title m-0">Participate Profile</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Profile types that can register for this event.</p>
    </div>

    <div class="card">
      <div class="flex items-center justify-between gap-4 mb-5">
        <div>
          <div class="font-bold text-base">
            Participate Profile
            <span v-if="saved" class="badge active ml-2">saved ✓</span>
          </div>
          <div class="muted text-[.84rem]">Shown as registration options on the event landing page.</div>
        </div>
        <button class="btn" @click="openAdd">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
          ADD PROFILE
        </button>
      </div>

      <div v-if="profiles.length" class="grid grid-cols-4 gap-4">
        <div
          v-for="p in profiles" :key="p.id"
          class="relative rounded-xl border p-4 bg-[#fafbfc]"
          :class="p.active ? 'border-brand' : 'border-line'"
        >
          <div class="flex justify-end" @click.stop="toggleMenu(p.id)">
            <div class="relative w-6.5 h-6.5 bg-white rounded-lg cursor-pointer flex items-center justify-center">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="text-muted"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
              <div
                v-if="openMenuId === p.id"
                class="absolute right-0 top-full mt-1 bg-white border border-line rounded-xl shadow-lg z-20 min-w-36 overflow-hidden"
                @click.stop
              >
                <button class="w-full text-left px-4 py-2.5 text-[.85rem] hover:bg-[#f7f8fa] text-ink" @click="openEdit(p)">Edit</button>
                <button class="w-full text-left px-4 py-2.5 text-[.85rem] hover:bg-[#f7f8fa] text-ink" @click="toggleActive(p)">{{ p.active ? 'Deactivate' : 'Activate' }}</button>
                <button class="w-full text-left px-4 py-2.5 text-[.85rem] hover:bg-[#f7f8fa] text-[#dc2626]" @click="removeProfile(p)">Delete</button>
              </div>
            </div>
          </div>
          <div class="py-2.5 flex justify-center">
            <AppIcon :name="p.icon" class="w-9 h-9" :class="p.active ? 'text-ink' : 'text-faint'" />
          </div>
          <div class="flex justify-center pb-1">
            <h3 class="font-bold text-[.9rem] text-ink text-center">{{ p.name }}</h3>
          </div>
        </div>
      </div>

      <p v-else class="muted text-[.86rem] py-10 text-center">
        No participate profiles yet. Click <strong>+ ADD PROFILE</strong> to get started.
      </p>
    </div>

    <!-- Add / Edit drawer -->
    <Drawer
      v-if="drawerOpen"
      :title="editingId ? 'Edit Participate Profile' : 'Add Participate Profile'"
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
      <input v-model="draft.name" placeholder="e.g. Attendee">

      <label class="flex items-center gap-3 cursor-pointer select-none mt-4 mb-2">
        <input v-model="draft.active" type="checkbox" class="w-4.5 h-4.5 m-0 accent-brand">
        <span class="text-[.93rem] font-medium text-ink">Active</span>
      </label>

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
      title="Choose Profile Icon"
      @select="draft.icon = $event"
      @close="iconChooserOpen = false"
    />
  </div>
</template>
