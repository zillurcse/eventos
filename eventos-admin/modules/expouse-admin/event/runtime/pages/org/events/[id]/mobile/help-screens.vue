<script setup lang="ts">
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface HelpScreen {
  id: string
  title: string
  description: string
  image_file_id: number | null
  image_url: string | null
  active: boolean
}

const screens = ref<HelpScreen[]>([])
const drawerOpen = ref(false)
const editingId = ref<string | null>(null)
const formError = ref('')

const draft = reactive<HelpScreen>({
  id: '',
  title: '',
  description: '',
  image_file_id: null,
  image_url: null,
  active: true,
})

async function load() {
  try {
    screens.value = (await api<any>(`/events/${id}/settings`)).data.help_screens || []
  } catch { /* */ }
}

async function persist() {
  try {
    await api(`/events/${id}/settings`, {
      method: 'PUT',
      body: { help_screens: JSON.parse(JSON.stringify(screens.value)) },
    })
    toast.success('Help screens saved')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not save.')
  }
}

function openAdd() {
  editingId.value = null
  Object.assign(draft, {
    id: 'hs' + Date.now(),
    title: '',
    description: '',
    image_file_id: null,
    image_url: null,
    active: true,
  })
  formError.value = ''
  drawerOpen.value = true
}

function openEdit(s: HelpScreen) {
  editingId.value = s.id
  Object.assign(draft, { ...s })
  formError.value = ''
  drawerOpen.value = true
}

async function saveDraft() {
  if (!draft.image_url) {
    formError.value = 'A screen image is required.'
    return
  }
  const clean: HelpScreen = JSON.parse(JSON.stringify(draft))
  clean.title = clean.title.trim()
  clean.description = clean.description.trim()
  if (editingId.value) {
    const i = screens.value.findIndex((s: HelpScreen) => s.id === editingId.value)
    if (i >= 0) screens.value[i] = clean
  } else {
    screens.value.push(clean)
  }
  await persist()
  drawerOpen.value = false
}

async function removeScreen(s: HelpScreen) {
  if (!confirm(`Remove help screen${s.title ? ` "${s.title}"` : ''}?`)) return
  screens.value = screens.value.filter((x: HelpScreen) => x.id !== s.id)
  await persist()
}

async function move(index: number, dir: -1 | 1) {
  const target = index + dir
  if (target < 0 || target >= screens.value.length) return
  const arr = screens.value
  ;[arr[index], arr[target]] = [arr[target], arr[index]]
  await persist()
}

async function toggleActive(s: HelpScreen) {
  s.active = !s.active
  await persist()
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-4">
      <h2 class="section-title m-0">Help Screens</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">
        Splash and onboarding slides shown when attendees open the mobile app.
      </p>
    </div>

    <div class="card">
      <div class="flex items-center justify-between gap-4 mb-5">
        <div>
          <div class="font-bold text-base">Screens</div>
          <div class="muted text-[.84rem]">
            Order matches the swipe sequence in the app (first = splash / intro).
          </div>
        </div>
        <button class="btn" @click="openAdd">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
          ADD SCREEN
        </button>
      </div>

      <div v-if="screens.length" class="flex flex-wrap gap-4">
        <div
          v-for="(s, i) in screens" :key="s.id"
          class="relative w-[180px] rounded-xl border border-line bg-white p-3 flex flex-col gap-2.5 shrink-0"
          :class="{ 'opacity-55': !s.active }"
        >
          <span class="absolute top-2 left-2 z-1 inline-flex items-center justify-center min-w-6 h-6 px-1.5 rounded-full bg-ink/80 text-white text-[.7rem] font-semibold">
            {{ i + 1 }}
          </span>
          <span
            v-if="!s.active"
            class="absolute top-2 right-2 z-1 badge draft"
          >Hidden</span>

          <div class="rounded-xl overflow-hidden bg-[#f3f4f6] border border-line" style="aspect-ratio: 9 / 16">
            <img
              v-if="s.image_url"
              :src="s.image_url"
              :alt="s.title || `Help screen ${i + 1}`"
              class="w-full h-full object-cover"
            >
            <div v-else class="w-full h-full grid place-items-center text-muted text-[.78rem]">No image</div>
          </div>

          <div class="min-w-0">
            <div class="font-semibold text-ink text-[.88rem] leading-tight truncate">
              {{ s.title || `Screen ${i + 1}` }}
            </div>
            <div class="muted text-[.78rem] line-clamp-2 mt-0.5">
              {{ s.description || '—' }}
            </div>
          </div>

          <div class="flex items-center gap-1 border-t border-line pt-2 -mb-0.5">
            <button class="icon-btn" title="Move up" :disabled="i === 0" @click="move(i, -1)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 15l-6-6-6 6"/></svg>
            </button>
            <button class="icon-btn" title="Move down" :disabled="i === screens.length - 1" @click="move(i, 1)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="flex-1" />
            <button class="icon-btn" :title="s.active ? 'Hide' : 'Show'" @click="toggleActive(s)">
              <svg v-if="!s.active" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19M1 1l22 22"/></svg>
              <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
            <button class="icon-btn" title="Edit" @click="openEdit(s)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
            </button>
            <button class="icon-btn danger" title="Remove" @click="removeScreen(s)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
            </button>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-13 px-5">
        <div class="w-13.5 h-13.5 rounded-[14px] bg-[#f3f0ff] text-[#6352e7] grid place-items-center mx-auto mb-3.5">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <rect x="7" y="2" width="10" height="20" rx="2"/>
            <path d="M11 18h2"/>
          </svg>
        </div>
        <p class="muted m-0 mb-1 font-medium text-ink">No help screens yet</p>
        <p class="muted m-0 mb-3 text-[.86rem]">
          Add splash and onboarding images like “Engage &amp; Lead Smartly” for the mobile app.
        </p>
        <button class="btn" @click="openAdd">+ ADD SCREEN</button>
      </div>
    </div>

    <Drawer
      v-if="drawerOpen"
      :title="editingId ? 'Edit Help Screen' : 'Add Help Screen'"
      @close="drawerOpen = false"
    >
      <div class="mb-5">
        <FormField label="Screen image" required>
          <ImageField
            :model-value="draft.image_url"
            :aspect="9 / 16"
            :output-width="720"
            :output-height="1280"
            collection="help_screen"
            card-width="140px"
            hint="Portrait phone image (9:16). Full splash artwork or illustration."
            :gallery-path="`/events/${id}/gallery`"
            @update:model-value="draft.image_url = (Array.isArray($event) ? $event[0] : $event) || null"
            @uploaded="(v: any) => draft.image_file_id = v.id"
          />
        </FormField>
        <p v-if="formError" class="text-[#dc2626] text-[.82rem] mt-2 mb-0">{{ formError }}</p>
      </div>

      <div class="mb-4">
        <AppInput
          v-model="draft.title"
          label="Title"
          placeholder="e.g. Engage & Lead Smartly"
        />
      </div>

      <div class="mb-4">
        <AppTextarea
          v-model="draft.description"
          label="Description"
          :rows="3"
          placeholder="e.g. Capture, manage, and convert leads through real-time engagement"
        />
      </div>

      <AppCheckbox
        v-model="draft.active"
        label="Active"
        description="Show this screen in the mobile app onboarding flow"
      />

      <div class="modal-actions border-t border-line pt-4 mt-5">
        <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
        <button class="btn" @click="saveDraft">
          {{ editingId ? 'UPDATE' : 'ADD' }}
        </button>
      </div>
    </Drawer>
  </div>
</template>

<style scoped>
.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  color: var(--muted, #6b7280);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: background .15s, color .15s;
}
.icon-btn:hover:not(:disabled) {
  background: #f3f0ff;
  color: #6352e7;
}
.icon-btn.danger:hover:not(:disabled) {
  background: #fef2f2;
  color: #dc2626;
}
.icon-btn:disabled {
  opacity: .35;
  cursor: not-allowed;
}
</style>
