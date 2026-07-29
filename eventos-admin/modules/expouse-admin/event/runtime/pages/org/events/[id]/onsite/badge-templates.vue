<script setup lang="ts">
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch } from 'vue'
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
  format: string | null
  is_default: boolean
  width: string | null
  height: string | null
  badge_json: any
  created_at: string | null
  updated_at: string | null
}

// Print-size presets the editor understands (millimetres).
const SIZES: { key: string, label: string, width: number, height: number }[] = [
  { key: 'A6', label: 'A6 (105 × 148 mm)', width: 105, height: 148 },
  { key: 'A7', label: 'A7 (74 × 105 mm)', width: 74, height: 105 },
  { key: 'A4', label: 'A4 (210 × 297 mm)', width: 210, height: 297 },
  { key: 'card', label: 'Card (85.6 × 54 mm)', width: 86, height: 54 },
]

/**
 * Badge audiences, matching App\Support\BadgeAudience server-side. `guest` is
 * absent on purpose: guest badges carry a sub-type (Media, VVIP) and are made
 * through the guest wizard, not this drawer.
 */
const TYPES: { value: string, label: string }[] = [
  { value: 'attendee', label: 'Attendee' },
  { value: 'speaker', label: 'Speaker' },
  { value: 'exhibitor', label: 'Exhibitor' },
  { value: 'sponsor', label: 'Sponsor' },
  { value: 'staff', label: 'Staff' },
  { value: 'organizer', label: 'Organizer' },
]

function typeLabel(value: string | null) {
  return TYPES.find(t => t.value === value)?.label ?? value ?? ''
}

const tab = ref<'default' | 'guest'>('default')

const templates = ref<BadgeTemplate[]>([])
const loading = ref(true)
const search = ref('')

const columns = [
  { key: 'name', label: 'Name', sortable: true },
  { key: 'badge_for', label: 'Type', sortable: true },
  { key: 'format', label: 'Format' },
  { key: 'created_at', label: 'Created Date', sortable: true },
]

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

/** One ready-made badge per audience, for an event starting from nothing. */
const seeding = ref(false)

async function seedDefaults() {
  seeding.value = true
  try {
    const res: any = await api(`/events/${id}/badge-designs/seed-defaults`, { method: 'POST' })
    toast.success(`${res?.meta?.created ?? 0} badge templates created`)
    await load()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not create the default badges.')
  } finally {
    seeding.value = false
  }
}

// ── Guest badge batches ───────────────────────────────────────────────────────
interface GuestBatch {
  id: number
  name: string
  guest_type: string | null
  guest_count: number
  delivery: { method: string, at: string } | null
  design: any
}

const batches = ref<GuestBatch[]>([])
const batchesLoading = ref(false)

async function loadBatches() {
  batchesLoading.value = true
  try {
    const res: any = await api(`/events/${id}/guest-badges`)
    batches.value = res?.data ?? []
  } catch {
    toast.error('Could not load guest badges.')
    batches.value = []
  } finally {
    batchesLoading.value = false
  }
}

function newGuestBadge() {
  navigateTo(`/org/events/${id}/onsite/guest-badges`)
}

function openGuestBadge(b: GuestBatch) {
  navigateTo(`/org/events/${id}/onsite/guest-badges?batch=${b.id}`)
}

// Loaded on first switch to the tab rather than on mount — most visits to this
// page are about the default templates.
watch(tab, (t) => { if (t === 'guest' && !batches.value.length) loadBatches() })

// ── Create ────────────────────────────────────────────────────────────────────
const drawerOpen = ref(false)
const saving = ref(false)
const error = ref('')
const form = reactive({ name: '', badge_for: '', size: 'A6' })

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
    const size = SIZES.find(s => s.key === form.size) || SIZES[0]!
    const res: any = await api(`/events/${id}/badge-designs`, {
      method: 'POST',
      body: {
        name: form.name.trim(),
        badge_for: form.badge_for.trim() || null,
        format: size.key,
        // First template of the event becomes the default one.
        is_default: templates.value.length === 0,
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

// ── Row actions ───────────────────────────────────────────────────────────────
// The popover is teleported to <body> so it can't be clipped by the table's
// own overflow/scroll container; position is computed from the trigger
// button's rect each time it opens, right-edge aligned like the old absolute layout.
const openMenu = ref<number | null>(null)
const menuPos = reactive({ top: 0, right: 0 })
const previewing = ref<BadgeTemplate | null>(null)
const previewSide = ref<'front' | 'back'>('front')

function toggleMenu(t: BadgeTemplate, e: MouseEvent) {
  if (openMenu.value === t.id) { openMenu.value = null; return }
  const rect = (e.currentTarget as HTMLElement).getBoundingClientRect()
  menuPos.top = rect.bottom + 4
  menuPos.right = window.innerWidth - rect.right
  openMenu.value = t.id
}

function onDocClick() { openMenu.value = null }

function editTemplate(t: BadgeTemplate) {
  navigateTo(`/org/events/${id}/badge?design=${t.id}`)
}

function viewTemplate(t: BadgeTemplate) {
  openMenu.value = null
  previewSide.value = 'front'
  previewing.value = t
}

const previewHasBack = computed(() => (previewing.value?.badge_json?.backBoxes ?? []).length > 0)

async function makeDefault(t: BadgeTemplate) {
  openMenu.value = null
  if (t.is_default) return
  try {
    await api(`/badge-designs/${t.id}`, { method: 'PATCH', body: { is_default: true } })
    // The API clears the flag on the event's other designs.
    templates.value = templates.value.map(x => ({ ...x, is_default: x.id === t.id }))
    toast.success(`"${t.name}" is now the default badge`)
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not set the default badge.')
  }
}

async function duplicateTemplate(t: BadgeTemplate) {
  openMenu.value = null
  try {
    const full: any = await api(`/badge-designs/${t.id}`)
    const d = full?.data ?? {}
    await api(`/events/${id}/badge-designs`, {
      method: 'POST',
      body: { ...d, id: undefined, name: `${t.name} (copy)`, is_default: false },
    })
    toast.success('Badge template duplicated')
    load()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not duplicate the template.')
  }
}

async function deleteTemplate(t: BadgeTemplate) {
  openMenu.value = null
  if (!confirm(`Delete badge template "${t.name}"? This cannot be undone.`)) return
  try {
    await api(`/badge-designs/${t.id}`, { method: 'DELETE' })
    templates.value = templates.value.filter(x => x.id !== t.id)
    toast.success('Badge template deleted')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not delete the template.')
  }
}

// ── Cell formatting ───────────────────────────────────────────────────────────
/**
 * Format = the print size. The editor rewrites the page config on every save
 * (page_config.presetWidth/presetHeight are millimetres), so that wins over the
 * width/height captured when the template was first created.
 */
function formatOf(t: BadgeTemplate) {
  const cfg = t.badge_json?.page_config
  const w = cfg?.presetWidth ?? t.width
  const h = cfg?.presetHeight ?? t.height
  if (!w || !h) return t.format || '—'

  const dims = `${w} × ${h} mm`
  const preset = cfg
    ? (cfg.badgeSizePreset === 'preset' ? cfg.badgeSize : 'Custom')
    : SIZES.find(s => String(s.width) === t.width && String(s.height) === t.height)?.key
  return preset ? `${preset} · ${dims}` : dims
}

function searchText(t: BadgeTemplate) {
  return `${t.name} ${t.badge_for ?? ''} ${t.format ?? ''}`
}

function createdOn(t: BadgeTemplate) {
  if (!t.created_at) return '—'
  const d = new Date(t.created_at)
  return Number.isNaN(d.getTime())
    ? '—'
    : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}

onMounted(() => {
  load()
  if (import.meta.client) document.addEventListener('click', onDocClick)
})
onBeforeUnmount(() => {
  if (import.meta.client) document.removeEventListener('click', onDocClick)
})
</script>

<template>
  <div class="w-full" @click="openMenu = null">
    <!-- Header -->
    <div class="mb-5">
      <h2 class="section-title m-0">Badges</h2>
      <p class="muted text-[.86rem] mt-1 mb-0">
        Create a single badge template or create multiple. The Default badge will be used badge template.
      </p>
    </div>

    <!-- Tabs + primary action -->
    <div class="flex items-end justify-between gap-3 flex-wrap mb-4">
      <div class="flex gap-1">
        <button
          class="px-1 mr-5 py-2.5 text-[.95rem] font-bold border-b-2 -mb-px bg-transparent cursor-pointer"
          :class="tab === 'default' ? 'border-brand text-ink' : 'border-transparent text-muted hover:text-ink'"
          @click="tab = 'default'"
        >Default</button>
        <button
          class="px-1 py-2.5 text-[.95rem] font-bold border-b-2 -mb-px bg-transparent cursor-pointer"
          :class="tab === 'guest' ? 'border-brand text-ink' : 'border-transparent text-muted hover:text-ink'"
          @click="tab = 'guest'"
        >Guest Badges</button>
      </div>

      <div class="flex items-center gap-2 mb-2">
        <SearchInput v-if="tab === 'default' && templates.length" v-model="search" placeholder="Search templates…" class="max-w-55" />
        <button class="btn" @click="tab === 'guest' ? newGuestBadge() : openCreate()">
          <AppIcon name="plus" class="w-3.5 h-3.5" />
          Create A Badge
        </button>
      </div>
    </div>

    <!-- ── Default ── -->
    <template v-if="tab === 'default'">
      <div v-if="loading" class="card flex items-center justify-center gap-2.5 py-12 text-muted text-[.88rem]">
        <svg class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
          <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
        </svg>
        Loading templates…
      </div>

      <DataTable
        v-else
        :columns="columns"
        :items="templates"
        :search="search"
        row-key="id"
        storage-key="badge-templates"
        :search-text="searchText"
      >
        <template #cell-name="{ row }">
          <span class="font-semibold text-ink">{{ row.name }}</span>
        </template>

        <template #cell-badge_for="{ row }">
          <span class="text-ink">{{ row.badge_for ? (row.guest_type || typeLabel(row.badge_for)) : '—' }}</span>
        </template>

        <template #cell-format="{ row }">
          <span class="text-ink">{{ formatOf(row) }}</span>
        </template>

        <template #cell-created_at="{ row }">
          <span class="text-muted">{{ createdOn(row) }}</span>
        </template>

        <template #actions="{ row }">
          <div class="inline-flex items-center gap-3 relative">
            <span v-if="row.is_default" class="badge">Default</span>

            <button
              type="button"
              class="relative w-10 h-5.5 rounded-full transition-colors shrink-0"
              :class="row.is_default ? 'bg-brand' : 'bg-[#d7dae1]'"
              :disabled="row.is_default"
              :aria-label="row.is_default ? 'Already the default badge' : 'Set as default badge'"
              @click="makeDefault(row)"
            >
              <span class="absolute top-0.75 left-0.75 w-4 h-4 rounded-full bg-white transition-transform" :class="row.is_default ? 'translate-x-4.5' : ''" />
            </button>

            <button
              class="w-7 h-7 rounded-lg grid place-items-center text-faint hover:text-ink hover:bg-[#f1f1f5] border-0 bg-transparent cursor-pointer"
              title="More actions"
              @click.stop="toggleMenu(row, $event)"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
            </button>

            <Teleport to="body">
              <div
                v-if="openMenu === row.id"
                class="menu-pop text-[.85rem]"
                :style="{ position: 'fixed', top: menuPos.top + 'px', right: menuPos.right + 'px', left: 'auto' }"
                @click.stop
              >
                <button @click="viewTemplate(row)">View</button>
                <button @click="editTemplate(row)">Edit</button>
                <button v-if="!row.is_default" @click="makeDefault(row)">Set as default</button>
                <button @click="duplicateTemplate(row)">Duplicate</button>
                <button class="text-[#dc2626]!" @click="deleteTemplate(row)">Delete</button>
              </div>
            </Teleport>
          </div>
        </template>

        <template #empty>
          <div class="py-6">
            <div class="w-13.5 h-13.5 rounded-[14px] bg-[#f3f0ff] text-[#6352e7] grid place-items-center mx-auto mb-3.5">
              <AppIcon name="layers" class="w-6.5 h-6.5" />
            </div>
            <p class="muted m-0 mb-1">No badge templates yet.</p>
            <p class="muted text-[.82rem] m-0 mb-3">
              Start from a ready-made badge for each role, or design one from scratch.
            </p>
            <div class="flex items-center justify-center gap-2">
              <button class="btn" :disabled="seeding" @click="seedDefaults">
                {{ seeding ? 'Creating…' : 'Create default badges' }}
              </button>
              <button class="btn ghost" @click="openCreate">Start from scratch</button>
            </div>
          </div>
        </template>
      </DataTable>
    </template>

    <!-- ── Guest badges ── -->
    <template v-else>
      <div v-if="batchesLoading" class="card flex items-center justify-center py-12 text-muted text-[.88rem]">
        Loading guest badges…
      </div>

      <div v-else-if="!batches.length" class="card text-center py-13 px-5">
        <div class="w-13.5 h-13.5 rounded-[14px] bg-[#f3f0ff] text-[#6352e7] grid place-items-center mx-auto mb-3.5">
          <AppIcon name="users" class="w-6.5 h-6.5" />
        </div>
        <p class="font-semibold text-ink m-0 mb-1">No guest badges yet</p>
        <p class="muted m-0 mb-3">
          Guest badges are passes for people who never registered — press, VVIPs, a partner's delegation.
        </p>
        <button class="btn" @click="newGuestBadge">Create A Badge</button>
      </div>

      <div v-else class="grid gap-4 grid-cols-[repeat(auto-fill,minmax(15rem,1fr))]">
        <div
          v-for="b in batches"
          :key="b.id"
          class="card p-0 overflow-hidden cursor-pointer hover:shadow-md transition-shadow"
          @click="openGuestBadge(b)"
        >
          <div class="bg-[#f7f7fa] grid place-items-center py-4 border-b border-line">
            <BadgePreview v-if="b.design" :badge-json="b.design.badge_json" :max-width="120" :max-height="170" />
          </div>
          <div class="p-4">
            <div class="flex items-center gap-2 mb-1">
              <span class="font-semibold text-ink">{{ b.name }}</span>
              <span v-if="b.guest_type" class="badge draft">{{ b.guest_type }}</span>
            </div>
            <p class="muted text-[.82rem] m-0">
              {{ b.guest_count }} guest{{ b.guest_count === 1 ? '' : 's' }}
              <template v-if="b.delivery"> · delivered by {{ b.delivery.method }}</template>
            </p>
          </div>
        </div>
      </div>
    </template>

    <!-- Preview -->
    <Modal
      v-if="previewing"
      :title="previewing.name"
      :subtitle="formatOf(previewing)"
      @close="previewing = null"
    >
      <div class="mt-4 flex flex-col items-center gap-3">
        <div v-if="previewHasBack" class="flex gap-1 p-1 rounded-lg bg-[#f1f1f5]">
          <button
            class="px-3 py-1 rounded-md text-[.82rem] font-medium border-0 cursor-pointer"
            :class="previewSide === 'front' ? 'bg-white text-ink' : 'bg-transparent text-muted'"
            @click="previewSide = 'front'"
          >Front</button>
          <button
            class="px-3 py-1 rounded-md text-[.82rem] font-medium border-0 cursor-pointer"
            :class="previewSide === 'back' ? 'bg-white text-ink' : 'bg-transparent text-muted'"
            @click="previewSide = 'back'"
          >Back</button>
        </div>

        <div class="border border-line rounded-lg overflow-hidden shadow-sm">
          <BadgePreview :badge-json="previewing.badge_json" :side="previewSide" :max-width="320" :max-height="440" />
        </div>
      </div>

      <div class="modal-actions border-t border-line pt-4 mt-5">
        <button class="btn ghost" @click="previewing = null">CLOSE</button>
        <button class="btn" @click="editTemplate(previewing)">EDIT DESIGN</button>
      </div>
    </Modal>

    <Drawer v-if="drawerOpen" title="Create a badge" @close="drawerOpen = false">
      <div class="mb-4">
        <AppInput v-model="form.name" label="Badge template name" required placeholder="e.g. Attendee Badge" autofocus />
      </div>

      <div class="mb-4">
        <AppSelect
          v-model="form.badge_for"
          label="Type"
          hint="Who this badge is printed for."
          :options="[{ value: '', label: 'Not set' }, ...TYPES]"
        />
      </div>

      <div class="mb-1">
        <AppSelect v-model="form.size" label="Format" :options="SIZES.map(s => ({ value: s.key, label: s.label }))" />
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
