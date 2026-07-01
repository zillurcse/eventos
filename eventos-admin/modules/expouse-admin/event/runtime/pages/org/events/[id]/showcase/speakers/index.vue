<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api   = useApi()
const id    = route.params.id as string

// ── Types ────────────────────────────────────────────────────────────────────

interface SpeakerCategory {
  id: string
  name: string
}

interface Speaker {
  id: string
  name: string
  email: string
  designation: string
  company: string
  category: string
  presentation_title: string
  presentation_file: string | null
  presentation_file_name: string
  bio: string
  image_url: string | null
  facebook: string
  linkedin: string
  twitter: string
  instagram: string
  whatsapp: string
  tags: string[]
  can_rate: boolean
  is_featured: boolean
  is_public: boolean
  sort_order: number
}

type SpeakerDraft = Omit<Speaker, 'id' | 'sort_order'>

// ── State ────────────────────────────────────────────────────────────────────

const speakers   = ref<Speaker[]>([])
const categories = ref<SpeakerCategory[]>([])
const catBusy    = ref(false)
const search     = ref('')
const drawerOpen = ref(false)
const editing    = ref<Speaker | null>(null)
const saving     = ref(false)
const error      = ref('')

// ── Computed ─────────────────────────────────────────────────────────────────

const MAX_SPEAKERS = 50

const progressPct = computed(() =>
  Math.min(100, Math.round((speakers.value.length / MAX_SPEAKERS) * 100)),
)

const filtered = computed(() => {
  const q = search.value.toLowerCase()
  if (!q) return speakers.value
  return speakers.value.filter(s =>
    [s.name, s.company, s.designation, s.email].some(f =>
      (f ?? '').toLowerCase().includes(q)
    )
  )
})

// ── Speakers API ─────────────────────────────────────────────────────────────

async function load() {
  try {
    const res = await api<{ data: Speaker[] }>(`/events/${id}/speakers`)
    speakers.value = res.data
  } catch { /* */ }
}

async function saveDraft(payload: SpeakerDraft) {
  error.value = ''
  saving.value = true
  try {
    if (editing.value) {
      const res = await api<{ data: Speaker }>(`/events/${id}/speakers/${editing.value.id}`, {
        method: 'PUT', body: payload,
      })
      const idx = speakers.value.findIndex(s => s.id === editing.value!.id)
      if (idx >= 0) speakers.value[idx] = res.data
    } else {
      const res = await api<{ data: Speaker }>(`/events/${id}/speakers`, {
        method: 'POST', body: payload,
      })
      speakers.value.push(res.data)
    }
    drawerOpen.value = false
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save speaker.'
  } finally {
    saving.value = false
  }
}

async function removeSpeaker(s: Speaker) {
  if (!confirm(`Remove speaker "${s.name}"?`)) return
  try {
    await api(`/events/${id}/speakers/${s.id}`, { method: 'DELETE' })
    speakers.value = speakers.value.filter(x => x.id !== s.id)
  } catch { /* */ }
}

// ── Categories API ───────────────────────────────────────────────────────────

async function loadCategories() {
  try {
    const res = await api<{ data: SpeakerCategory[] }>(`/events/${id}/speaker-categories`)
    categories.value = res.data
  } catch { /* */ }
}

async function addCategory(name: string) {
  catBusy.value = true
  try {
    const res = await api<{ data: SpeakerCategory[] }>(`/events/${id}/speaker-categories`, {
      method: 'POST', body: { name },
    })
    categories.value = res.data
  } catch { /* */ } finally {
    catBusy.value = false
  }
}

async function renameCategory({ id: catId, name }: { id: string, name: string }) {
  try {
    const res = await api<{ data: SpeakerCategory[] }>(`/events/${id}/speaker-categories/${catId}`, {
      method: 'PUT', body: { name },
    })
    categories.value = res.data
  } catch { /* */ }
}

async function removeCategory(catId: string) {
  const cat = categories.value.find(c => c.id === catId)
  if (cat && !confirm(`Delete category "${cat.name}"?`)) return
  try {
    const res = await api<{ data: SpeakerCategory[] }>(`/events/${id}/speaker-categories/${catId}`, {
      method: 'DELETE',
    })
    categories.value = res.data
  } catch { /* */ }
}

// ── Drawer ───────────────────────────────────────────────────────────────────

function openAdd() {
  editing.value = null
  error.value = ''
  drawerOpen.value = true
}

function openEdit(s: Speaker) {
  editing.value = s
  error.value = ''
  drawerOpen.value = true
}

// TODO: wire to real features — reuse speakers from a past event / pick from the
// org-wide speakers directory. Stubbed for now so the buttons are non-destructive.
function openPrevious() {
  alert('Previous speakers — coming soon.')
}

function openDirectory() {
  alert('Speakers directory — coming soon.')
}

// ── Init ─────────────────────────────────────────────────────────────────────

onMounted(() => { load(); loadCategories() })
</script>

<template>
  <div>
    <div class="card">
      <!-- Header -->
      <div class="flex items-start justify-between gap-4 pb-4 mb-4 border-b border-line">
        <div>
          <h2 class="section-title m-0">Speakers</h2>
          <p class="muted text-[.86rem] mt-0.5 mb-2.5">Events speakers. Use drag and drop to rearrange the position</p>

          <!-- Counter + progress -->
          <span class="inline-block px-3.5 py-1.5 rounded-lg bg-brand text-white font-semibold text-[.82rem]">
            {{ speakers.length }} of {{ MAX_SPEAKERS }}
          </span>
          <div class="relative w-full max-w-[360px] h-4 rounded-full bg-[#eceef3] mt-2 overflow-hidden">
            <div class="h-full rounded-full bg-brand transition-all" :style="{ width: progressPct + '%' }" />
            <span class="absolute inset-0 flex items-center justify-center text-white text-[.62rem] font-semibold">{{ progressPct }}%</span>
          </div>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
          <button
            class="inline-flex items-center px-4 py-2.5 rounded-[11px] bg-[#f2f1fb] text-brand font-[650] text-[.82rem] tracking-wide hover:bg-[#e9e7f8]"
            @click="openPrevious"
          >PREVIOUS SPEAKERS</button>
          <button
            class="inline-flex items-center px-4 py-2.5 rounded-[11px] bg-[#f2f1fb] text-brand font-[650] text-[.82rem] tracking-wide hover:bg-[#e9e7f8]"
            @click="openDirectory"
          >SPEAKERS DIRECTORY</button>
          <button class="btn" @click="openAdd">
            <Icon name="plus" class="w-3.75 h-3.75" /> SPEAKERS
          </button>
        </div>
      </div>

      <!-- Search -->
      <div class="mb-4">
        <AppInput v-model="search" placeholder="Search" class="max-w-[400px]" />
      </div>

      <SpeakerTable
        :speakers="filtered"
        :searching="!!search"
        @edit="openEdit"
        @remove="removeSpeaker"
      />
    </div>

    <!-- Add / Edit Drawer -->
    <SpeakerFormDrawer
      v-if="drawerOpen"
      :speaker="editing"
      :categories="categories"
      :saving="saving"
      :error="error"
      :cat-busy="catBusy"
      @close="drawerOpen = false"
      @save="saveDraft"
      @add-category="addCategory"
      @rename-category="renameCategory"
      @remove-category="removeCategory"
    />
  </div>
</template>
