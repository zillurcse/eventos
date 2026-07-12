<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface BadgeTemplate {
  id: number
  name: string
  badge_for: string | null
  width: string | null
  height: string | null
  updated_at: string | null
}

// Print-size presets the editor understands (millimetres).
const SIZES: { key: string, label: string, width: number, height: number }[] = [
  { key: 'A6', label: 'A6 — 105 × 148 mm', width: 105, height: 148 },
  { key: 'A7', label: 'A7 — 74 × 105 mm', width: 74, height: 105 },
  { key: 'A4', label: 'A4 — 210 × 297 mm', width: 210, height: 297 },
  { key: 'card', label: 'Card — 85.6 × 54 mm', width: 86, height: 54 },
]

const templates = ref<BadgeTemplate[]>([])
const loading = ref(true)
const search = ref('')

const shown = computed(() => {
  const q = search.value.trim().toLowerCase()
  return q ? templates.value.filter(t => t.name.toLowerCase().includes(q)) : templates.value
})

const drawerOpen = ref(false)
const saving = ref(false)
const error = ref('')
const form = reactive({ name: '', badge_for: '', size: 'A6' })

async function load() {
  loading.value = true
  try {
    const res: any = await api(`/events/${id}/badge-designs`)
    templates.value = res?.data ?? res ?? []
  } catch {
    toast.error('Could not load badge templates.')
    templates.value = []
  } finally {
    loading.value = false
  }
}

function openCreate() {
  form.name = ''
  form.badge_for = ''
  form.size = 'A6'
  error.value = ''
  drawerOpen.value = true
}

async function createTemplate() {
  if (!form.name.trim()) { error.value = 'Please enter a template name.'; return }
  error.value = ''
  saving.value = true
  try {
    const size = SIZES.find(s => s.key === form.size) || SIZES[0]
    const res: any = await api(`/events/${id}/badge-designs`, {
      method: 'POST',
      body: {
        name: form.name.trim(),
        badge_for: form.badge_for.trim() || null,
        measurements_type: 'mm',
        width: String(size.width),
        height: String(size.height),
        badge_json: {},
        layers: [],
      },
    })
    const newId = res?.data?.id
    drawerOpen.value = false
    toast.success('Badge template created')
    // Straight into the editor for the freshly created template.
    if (newId) navigateTo(`/org/events/${id}/badge?design=${newId}`)
    else load()
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not create the template.'
    toast.error(error.value)
  } finally {
    saving.value = false
  }
}

function editTemplate(t: BadgeTemplate) {
  navigateTo(`/org/events/${id}/badge?design=${t.id}`)
}

async function deleteTemplate(t: BadgeTemplate) {
  if (!confirm(`Delete badge template "${t.name}"? This cannot be undone.`)) return
  try {
    await api(`/badge-designs/${t.id}`, { method: 'DELETE' })
    templates.value = templates.value.filter(x => x.id !== t.id)
    toast.success('Badge template deleted')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not delete the template.')
  }
}

function dims(t: BadgeTemplate) {
  return t.width && t.height ? `${t.width} × ${t.height} mm` : '—'
}
function when(t: BadgeTemplate) {
  if (!t.updated_at) return ''
  try { return new Date(t.updated_at).toLocaleDateString() } catch { return '' }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="card">
      <div class="flex items-start justify-between gap-4 flex-wrap mb-3">
        <div>
          <div class="font-bold text-base">Badge templates</div>
          <div class="muted text-[.85rem] mt-0.5">Design the badges printed for this event's attendees, staff and exhibitors.</div>
        </div>
        <button class="btn" @click="openCreate">+ NEW TEMPLATE</button>
      </div>

      <div v-if="templates.length" class="flex items-center justify-end gap-3 flex-wrap mb-4">
        <SearchInput v-model="search" placeholder="Search templates…" class="max-w-65" />
      </div>

      <div v-if="loading" class="flex items-center justify-center gap-2.5 py-14 text-muted text-[.88rem]">
        <svg class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
          <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
        </svg>
        Loading templates…
      </div>

      <template v-else>
        <div v-if="!shown.length" class="text-center py-13 px-5">
          <div class="w-13.5 h-13.5 rounded-[14px] bg-[#f3f0ff] text-[#6352e7] grid place-items-center mx-auto mb-3.5">
            <AppIcon name="layers" class="w-6.5 h-6.5" />
          </div>
          <p class="muted m-0 mb-3">{{ search ? 'No templates match your search.' : 'No badge templates yet.' }}</p>
          <button class="btn" @click="openCreate">+ NEW TEMPLATE</button>
        </div>

        <div v-else class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(240px, 1fr))">
          <div v-for="t in shown" :key="t.id" class="border border-line rounded-xl overflow-hidden flex flex-col">
            <div class="h-33 bg-[#f1f1f5] flex items-center justify-center border-b border-line">
              <AppIcon name="box" class="w-8 h-8 text-brand opacity-70" />
            </div>

            <div class="p-3 flex flex-col gap-2 flex-1">
              <span class="font-semibold text-ink truncate" :title="t.name">{{ t.name }}</span>
              <span v-if="t.badge_for" class="px-1.5 py-0.5 rounded text-[.68rem] bg-[#eef0ff] text-[#6352e7] font-medium self-start">{{ t.badge_for }}</span>
              <div class="text-[.76rem] text-muted flex flex-wrap gap-x-1.5">
                <span>{{ dims(t) }}</span>
                <span v-if="when(t)">· {{ when(t) }}</span>
              </div>

              <div class="flex items-center gap-1.5 mt-auto pt-2">
                <button class="btn ghost text-[.78rem] px-2.5 py-1 inline-flex items-center gap-1.5" @click="editTemplate(t)">
                  <AppIcon name="pencil" class="w-3.5 h-3.5" /> Edit
                </button>
                <button class="text-[#dc2626] text-[.78rem] font-medium px-2 hover:underline ml-auto" @click="deleteTemplate(t)">Delete</button>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <Drawer v-if="drawerOpen" title="New badge template" @close="drawerOpen = false">
      <div class="mb-4">
        <AppInput v-model="form.name" label="Template name" required placeholder="e.g. Attendee Badge" autofocus />
      </div>

      <div class="mb-4">
        <AppInput v-model="form.badge_for" label="Badge for" hint="Optional — e.g. Attendee, Speaker, Exhibitor" placeholder="Attendee, Speaker, Exhibitor…" />
      </div>

      <div class="mb-1">
        <AppSelect v-model="form.size" label="Size" :options="SIZES.map(s => ({ value: s.key, label: s.label }))" />
      </div>

      <p v-if="error" class="error mt-3">{{ error }}</p>

      <div class="modal-actions border-t border-line pt-4 mt-4">
        <button class="btn ghost" @click="drawerOpen = false">CANCEL</button>
        <button class="btn" :disabled="saving || !form.name.trim()" @click="createTemplate">
          {{ saving ? 'Creating…' : 'CREATE & DESIGN' }}
        </button>
      </div>
    </Drawer>
  </div>
</template>
