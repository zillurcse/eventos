<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '../../../../../composables/useApi'

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
  { key: 'teams_connections', label: "Team's Connections", countable: false },
  { key: 'recommended_leads', label: 'Recommended leads',  countable: false },
  { key: 'lead_analytics',    label: 'Lead Analytics',      countable: false },
  { key: 'lead_export',       label: 'Lead Export',         countable: false },
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

function enabledCount(pkg: Package) {
  return (pkg.entitlements ?? []).filter(f => f.enabled).length
}

function enabledLabels(pkg: Package) {
  return (pkg.entitlements ?? [])
    .filter(f => f.enabled)
    .map(f => featureLabel(f.key))
    .join(', ') || '—'
}

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
  <div>
    <div class="mb-4">
      <h2 class="section-title m-0">Exhibitor Packages</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Define feature packages available to exhibitors in the Showcase Arena.</p>
    </div>

    <div class="card">
      <div class="flex items-center justify-between gap-4 mb-4">
        <div>
          <div class="font-bold text-base">Packages</div>
          <div class="muted text-[.84rem]">Configure what features each exhibitor package includes.</div>
        </div>
        <button class="btn" @click="openAdd">
          <Icon name="plus" class="w-3.75 h-3.75" /> PACKAGE
        </button>
      </div>

      <table>
        <thead>
          <tr>
            <th>NAME</th>
            <th>FEATURES</th>
            <th class="text-right">ACTIONS</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="pkg in packages" :key="pkg.id">
            <td class="font-semibold text-brand">{{ pkg.name }}</td>
            <td>
              <span class="muted text-[.84rem]">
                <span class="font-semibold text-ink">{{ enabledCount(pkg) }}</span>
                / {{ ALL_FEATURES.length }} —
                <span class="text-[.82rem]">{{ enabledLabels(pkg) }}</span>
              </span>
            </td>
            <td class="text-right whitespace-nowrap">
              <button
                class="bg-transparent border-0 cursor-pointer text-base px-2 py-1 text-brand"
                title="Edit" @click="openEdit(pkg)"
              >✎</button>
              <button
                class="bg-transparent border-0 cursor-pointer text-base px-2 py-1 text-[#dc2626]"
                title="Delete" @click="removePackage(pkg)"
              >🗑</button>
            </td>
          </tr>
          <tr v-if="!packages.length">
            <td colspan="3" class="muted text-center py-8">
              No packages yet. Click <strong>+ PACKAGE</strong> to add one.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

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
