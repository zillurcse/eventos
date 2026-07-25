<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'

declare const definePageMeta: (meta: Record<string, unknown>) => void
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

// ── Types ─────────────────────────────────────────────────────────────
interface FeatureLine {
  key: string
  enabled: boolean
  limit: number
}

interface Package {
  id: number
  name: string
  kind: string
  entitlements: FeatureLine[] | null
}

// ── Feature catalogue ─────────────────────────────────────────────────
// `countable: false` → on/off only (no quantity stepper in the drawer)
const ALL_FEATURES: { key: string; label: string; countable?: boolean }[] = [
  { key: 'teams',             label: 'Teams' },
  { key: 'projects',          label: 'Projects' },
  { key: 'products',          label: 'Products' },
  { key: 'documents',         label: 'Documents' },
  { key: 'videos',            label: 'Videos' },
  { key: 'cta',               label: 'CTA' },
  { key: 'meetings',          label: 'Meetings' },
  { key: 'lounge',            label: 'Lounge' },
  // Leads — on/off only. Keep in sync with the exhibitor entitlements
  // catalogue (exhibitor/runtime/utils/exhibitor.ts ALL_FEATURES).
  { key: 'all_leads',          label: 'All Leads',          countable: false },
  { key: 'team_connections',   label: 'Team Connections',   countable: false },
  { key: 'recommended_leads',  label: 'Recommended Leads',  countable: false },
  { key: 'lead_qualification', label: 'Lead Qualification', countable: false },
  { key: 'lead_analytics',     label: 'Leads Analytics',    countable: false },
  { key: 'lead_export',        label: 'Lead Export',         countable: false },
  { key: 'analytics',         label: 'Analytics',           countable: false },
]

function isCountable(key: string) {
  return ALL_FEATURES.find(f => f.key === key)?.countable !== false
}

// ── State ─────────────────────────────────────────────────────────────
const packages   = ref<Package[]>([])
const drawerOpen = ref(false)
const editingId  = ref<number | null>(null)
const saving     = ref(false)
const error      = ref('')

interface DraftShape { name: string; features: FeatureLine[] }
const draft = reactive<DraftShape>({ name: '', features: [] })

// ── Helpers ───────────────────────────────────────────────────────────
function freshFeatures(): FeatureLine[] {
  return ALL_FEATURES.map(f => ({ key: f.key, enabled: false, limit: 1 }))
}

function mergeFeatures(saved: FeatureLine[] | null): FeatureLine[] {
  const map = new Map((saved ?? []).map(f => [f.key, f]))
  return ALL_FEATURES.map(f => {
    const s = map.get(f.key)
    return s ? { ...s } : { key: f.key, enabled: false, limit: 1 }
  })
}

function featureLabel(key: string) {
  return ALL_FEATURES.find(f => f.key === key)?.label ?? key
}

const columns = [
  { key: 'name', label: 'Name' },
]

const actionsFor = ref<number | null>(null)

// ── API ───────────────────────────────────────────────────────────────
async function load() {
  try {
    packages.value = (await api<any>(`/exhibitor-packages?event=${id}`)).data
  } catch { /* */ }
}

// ── Open drawers ──────────────────────────────────────────────────────
function openAdd() {
  editingId.value = null
  draft.name = ''
  draft.features = freshFeatures()
  error.value = ''
  drawerOpen.value = true
}

function openEdit(pkg: Package) {
  editingId.value = pkg.id
  draft.name = pkg.name
  draft.features = mergeFeatures(pkg.entitlements)
  error.value = ''
  drawerOpen.value = true
}

// ── Save ──────────────────────────────────────────────────────────────
async function saveDraft() {
  error.value = ''
  saving.value = true
  try {
    const payload = {
      event:        id,
      name:         draft.name,
      entitlements: draft.features.map((f: FeatureLine) => ({
        key:     f.key,
        enabled: f.enabled,
        limit:   isCountable(f.key) ? f.limit : 0,
      })),
    }

    if (editingId.value) {
      const res = await api<any>(`/exhibitor-packages/${editingId.value}`, { method: 'PUT', body: payload })
      const idx = packages.value.findIndex(p => p.id === editingId.value)
      if (idx >= 0) packages.value[idx] = res.data
    } else {
      const res = await api<any>('/exhibitor-packages', { method: 'POST', body: payload })
      packages.value.push(res.data)
    }

    drawerOpen.value = false
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save package.'
  } finally {
    saving.value = false
  }
}

async function removePackage(pkg: Package) {
  if (!confirm(`Delete package "${pkg.name}"?`)) return
  try {
    await api(`/exhibitor-packages/${pkg.id}`, { method: 'DELETE' })
    packages.value = packages.value.filter(p => p.id !== pkg.id)
  } catch { /* */ }
}

onMounted(load)
</script>

<template>
  <div @click="actionsFor = null">
    <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
      <div>
        <h1 class="text-[1.35rem] font-bold text-ink mb-0.5">Exhibitor Packages</h1>
        <p class="text-muted text-[.88rem]">Manage the packages available to your exhibitors.</p>
      </div>
      <button class="btn" @click="openAdd">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        Add Package
      </button>
    </div>

    <DataTable
      :items="packages"
      :columns="columns"
      row-key="id"
      storage-key="showcase-packages"
    >
      <template #cell-name="{ row }">
        <span class="font-semibold text-ink">{{ row.name }}</span>
      </template>
      <template #actions="{ row }">
        <div class="relative inline-block" @click.stop>
          <button class="w-8 h-8 rounded-lg grid place-items-center text-muted hover:bg-[#f1f2f6] border-0 bg-transparent cursor-pointer" aria-label="Actions" @click="actionsFor = actionsFor === row.id ? null : row.id">
            <svg viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
          </button>
          <div v-if="actionsFor === row.id" class="absolute right-0 top-full mt-1 bg-white border border-line rounded-xl shadow-lg z-20 min-w-36 overflow-hidden">
            <button class="w-full text-left px-4 py-2.5 text-[.86rem] hover:bg-[#f7f8fa]" @click="actionsFor = null; openEdit(row)">Edit</button>
            <button class="w-full text-left px-4 py-2.5 text-[.86rem] text-[#dc2626] hover:bg-[#fef2f2]" @click="actionsFor = null; removePackage(row)">Delete</button>
          </div>
        </div>
      </template>
      <template #empty>
        <div class="flex flex-col items-center gap-2.5 text-muted">
          <p class="m-0 text-[.88rem]">No packages yet.</p>
          <button class="btn sm" @click="openAdd">Add your first package</button>
        </div>
      </template>
    </DataTable>

    <!-- Add / Edit Drawer -->
    <Drawer v-if="drawerOpen" title="Exhibitor Packages" @close="drawerOpen = false">
      <label>Name</label>
      <input v-model="draft.name" placeholder="Enter packages name" class="mb-5">

      <div class="flex flex-col gap-2">
        <div
          v-for="f in draft.features"
          :key="f.key"
          class="flex items-center gap-3 px-4 py-2.75 border border-line rounded-xl bg-[#fafbfc]"
          :class="{ 'bg-brand-soft border-brand/20': f.enabled }"
        >
          <input
            v-model="f.enabled"
            type="checkbox"
            class="w-4.5 h-4.5 m-0 rounded shrink-0 cursor-pointer accent-brand"
          >
          <span class="flex-1 text-[.93rem] font-medium text-ink select-none">{{ featureLabel(f.key) }}</span>
          <div
            v-if="isCountable(f.key)"
            class="flex items-center shrink-0 border border-[#d7dae1] rounded-xl overflow-hidden bg-white"
          >
            <button
              class="w-9 h-9 flex items-center justify-center text-[1.1rem] text-muted border-0 bg-transparent cursor-pointer hover:bg-[#f0f0f7] transition-colors select-none"
              @click="f.limit = Math.max(0, f.limit - 1)"
            >−</button>
            <span class="w-8 h-9 flex items-center justify-center text-[.91rem] font-semibold border-x border-[#d7dae1] select-none">{{ f.limit }}</span>
            <button
              class="w-9 h-9 flex items-center justify-center text-[1.1rem] text-muted border-0 bg-transparent cursor-pointer hover:bg-[#f0f0f7] transition-colors select-none"
              @click="f.limit++"
            >+</button>
          </div>
        </div>
      </div>

      <p v-if="error" class="error mt-3">{{ error }}</p>

      <div class="modal-actions border-t border-line pt-4 mt-5">
        <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
        <button class="btn" :disabled="!draft.name.trim() || saving" @click="saveDraft">
          {{ saving ? 'Saving…' : editingId ? 'UPDATE' : 'ADD' }}
        </button>
      </div>
    </Drawer>
  </div>
</template>
