<script setup lang="ts">
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
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
  description: string | null
  tier: string
  booth_size: string | null
  price_cents: number
  currency: string
  entitlements: FeatureLine[] | null
}

// ── Feature catalogue ─────────────────────────────────────────────────
// `countable: false` → on/off only (no quantity stepper in the drawer)
// `group: 'perks'` → shown under "Marketing Perks" instead of "Features"
const ALL_FEATURES: { key: string; label: string; countable?: boolean; group?: 'perks' }[] = [
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
  { key: 'all_leads',          label: 'All Leads',          countable: false, group: 'perks' },
  { key: 'team_connections',   label: 'Team Connections',   countable: false, group: 'perks' },
  { key: 'recommended_leads',  label: 'Recommended Leads',  countable: false, group: 'perks' },
  { key: 'lead_qualification', label: 'Lead Qualification', countable: false, group: 'perks' },
  { key: 'lead_analytics',     label: 'Leads Analytics',    countable: false, group: 'perks' },
  { key: 'lead_export',        label: 'Lead Export',         countable: false, group: 'perks' },
  { key: 'analytics',         label: 'Analytics',           countable: false, group: 'perks' },
]

function isCountable(key: string) {
  return ALL_FEATURES.find(f => f.key === key)?.countable !== false
}

const TIER_OPTIONS = ['Standard', 'Premium', 'VIP']
const BOOTH_SIZE_OPTIONS = ['10x10 ft', '10x20 ft', '20x20 ft', '20x30 ft']

// ── State ─────────────────────────────────────────────────────────────
const packages   = ref<Package[]>([])
const drawerOpen = ref(false)
const editingId  = ref<number | null>(null)
const saving     = ref(false)
const error      = ref('')

interface DraftShape {
  name: string
  description: string
  tier: string
  boothSize: string
  price: number | null
  features: FeatureLine[]
}
const draft = reactive<DraftShape>({ name: '', description: '', tier: 'Standard', boothSize: '', price: null, features: [] })
const mainFeatures = computed(() => draft.features.filter(f => ALL_FEATURES.find(a => a.key === f.key)?.group !== 'perks'))
const perkFeatures = computed(() => draft.features.filter(f => ALL_FEATURES.find(a => a.key === f.key)?.group === 'perks'))

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
// The actions menu is teleported to <body> (DataTable clips overflow), so track
// the trigger button's viewport position to anchor the menu there.
const actionsAnchor = ref<{ top: number; right: number } | null>(null)

function toggleActions(pkgId: number, ev: MouseEvent) {
  if (actionsFor.value === pkgId) {
    actionsFor.value = null
    actionsAnchor.value = null
    return
  }
  actionsFor.value = pkgId
  const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
  actionsAnchor.value = { top: rect.bottom + 4, right: window.innerWidth - rect.right }
}
function closeActions() { actionsFor.value = null; actionsAnchor.value = null }

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
  draft.description = ''
  draft.tier = 'Standard'
  draft.boothSize = ''
  draft.price = null
  draft.features = freshFeatures()
  error.value = ''
  drawerOpen.value = true
}

function openEdit(pkg: Package) {
  editingId.value = pkg.id
  draft.name = pkg.name
  draft.description = pkg.description || ''
  draft.tier = TIER_OPTIONS.find(t => t.toLowerCase() === pkg.tier) || 'Standard'
  draft.boothSize = pkg.booth_size || ''
  draft.price = pkg.price_cents ? pkg.price_cents / 100 : null
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
      description:  draft.description || null,
      tier:         draft.tier.toLowerCase(),
      booth_size:   draft.boothSize || null,
      price_cents:  Math.round((draft.price || 0) * 100),
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

// Fixed-position teleported menu lives outside the page root, so close it on
// any window click/scroll rather than relying solely on the root div's handler.
const onWindowClick = () => closeActions()
const onWindowScroll = () => closeActions()
onMounted(() => {
  load()
  window.addEventListener('click', onWindowClick)
  window.addEventListener('scroll', onWindowScroll, true)
})
onBeforeUnmount(() => {
  window.removeEventListener('click', onWindowClick)
  window.removeEventListener('scroll', onWindowScroll, true)
})
</script>

<template>
  <div @click="closeActions">
    <div class="flex items-center justify-between gap-4 flex-wrap mb-5">
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
        <span class="text-[#212529] font-semibold text-sm">{{ row.name }}</span>
      </template>
      <template #actions="{ row }">
        <div class="relative inline-block" @click.stop>
          <button class="w-8 h-8 rounded-lg grid place-items-center text-muted hover:bg-[#f1f2f6] border-0 bg-transparent cursor-pointer" aria-label="Actions" @click="toggleActions(row.id, $event)">
            <svg viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
          </button>
          <Teleport to="body">
            <div
              v-if="actionsFor === row.id && actionsAnchor"
              class="fixed bg-white rounded-xl border border-[#E8E8EE] shadow-xl z-30 min-w-40 overflow-hidden p-2"
              :style="{ top: `${actionsAnchor.top}px`, right: `${actionsAnchor.right}px` }"
              @click.stop
            >
              <button
                class="w-full flex items-center gap-3 px-3.5 py-2.5 max-h-10 rounded-lg text-[.92rem] font-medium text-brand hover:bg-[#F7F7FB] cursor-pointer bg-transparent border-0 text-left"
                @click="closeActions(); openEdit(row)"
              >
                 <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 15 15" fill="none">
                    <path d="M2.25 12.0043H5.43C5.5287 12.0049 5.62655 11.986 5.71793 11.9487C5.80931 11.9114 5.89242 11.8564 5.9625 11.7868L11.1525 6.58935L13.2825 4.50435C13.3528 4.43463 13.4086 4.35168 13.4467 4.26028C13.4847 4.16889 13.5043 4.07086 13.5043 3.97185C13.5043 3.87284 13.4847 3.77481 13.4467 3.68342C13.4086 3.59202 13.3528 3.50907 13.2825 3.43935L10.1025 0.221849C10.0328 0.151552 9.94983 0.0957567 9.85843 0.0576802C9.76704 0.0196037 9.66901 0 9.57 0C9.47099 0 9.37296 0.0196037 9.28157 0.0576802C9.19017 0.0957567 9.10722 0.151552 9.0375 0.221849L6.9225 2.34435L1.7175 7.54185C1.64799 7.61193 1.593 7.69504 1.55567 7.78642C1.51835 7.8778 1.49943 7.97564 1.5 8.07435V11.2543C1.5 11.4533 1.57902 11.644 1.71967 11.7847C1.86032 11.9253 2.05109 12.0043 2.25 12.0043ZM9.57 1.81185L11.6925 3.93435L10.6275 4.99935L8.505 2.87685L9.57 1.81185ZM3 8.38185L7.4475 3.93435L9.57 6.05685L5.1225 10.5043H3V8.38185ZM14.25 13.5043H0.75C0.551088 13.5043 0.360322 13.5834 0.21967 13.724C0.0790176 13.8647 0 14.0554 0 14.2543C0 14.4533 0.0790176 14.644 0.21967 14.7847C0.360322 14.9253 0.551088 15.0043 0.75 15.0043H14.25C14.4489 15.0043 14.6397 14.9253 14.7803 14.7847C14.921 14.644 15 14.4533 15 14.2543C15 14.0554 14.921 13.8647 14.7803 13.724C14.6397 13.5834 14.4489 13.5043 14.25 13.5043Z" fill="#6452E7"/>
                  </svg>
                Edit
              </button>
              <button
                class="w-full flex items-center gap-3 px-3.5 py-2.5 max-h-10 rounded-lg text-[.92rem] font-medium text-ink hover:bg-[#f7f8fa] cursor-pointer bg-transparent border-0 text-left"
                @click="closeActions(); removePackage(row)"
              >
                 <svg xmlns="http://www.w3.org/2000/svg" width="14" height="15" viewBox="0 0 14 15" fill="none">
                  <path d="M5.25 12C5.44891 12 5.63968 11.921 5.78033 11.7803C5.92098 11.6397 6 11.4489 6 11.25V6.75C6 6.55109 5.92098 6.36032 5.78033 6.21967C5.63968 6.07902 5.44891 6 5.25 6C5.05109 6 4.86032 6.07902 4.71967 6.21967C4.57902 6.36032 4.5 6.55109 4.5 6.75V11.25C4.5 11.4489 4.57902 11.6397 4.71967 11.7803C4.86032 11.921 5.05109 12 5.25 12ZM12.75 3H9.75V2.25C9.75 1.65326 9.51295 1.08097 9.09099 0.65901C8.66903 0.237053 8.09674 0 7.5 0H6C5.40326 0 4.83097 0.237053 4.40901 0.65901C3.98705 1.08097 3.75 1.65326 3.75 2.25V3H0.75C0.551088 3 0.360322 3.07902 0.21967 3.21967C0.0790176 3.36032 0 3.55109 0 3.75C0 3.94891 0.0790176 4.13968 0.21967 4.28033C0.360322 4.42098 0.551088 4.5 0.75 4.5H1.5V12.75C1.5 13.3467 1.73705 13.919 2.15901 14.341C2.58097 14.7629 3.15326 15 3.75 15H9.75C10.3467 15 10.919 14.7629 11.341 14.341C11.7629 13.919 12 13.3467 12 12.75V4.5H12.75C12.9489 4.5 13.1397 4.42098 13.2803 4.28033C13.421 4.13968 13.5 3.94891 13.5 3.75C13.5 3.55109 13.421 3.36032 13.2803 3.21967C13.1397 3.07902 12.9489 3 12.75 3ZM5.25 2.25C5.25 2.05109 5.32902 1.86032 5.46967 1.71967C5.61032 1.57902 5.80109 1.5 6 1.5H7.5C7.69891 1.5 7.88968 1.57902 8.03033 1.71967C8.17098 1.86032 8.25 2.05109 8.25 2.25V3H5.25V2.25ZM10.5 12.75C10.5 12.9489 10.421 13.1397 10.2803 13.2803C10.1397 13.421 9.94891 13.5 9.75 13.5H3.75C3.55109 13.5 3.36032 13.421 3.21967 13.2803C3.07902 13.1397 3 12.9489 3 12.75V4.5H10.5V12.75ZM8.25 12C8.44891 12 8.63968 11.921 8.78033 11.7803C8.92098 11.6397 9 11.4489 9 11.25V6.75C9 6.55109 8.92098 6.36032 8.78033 6.21967C8.63968 6.07902 8.44891 6 8.25 6C8.05109 6 7.86032 6.07902 7.71967 6.21967C7.57902 6.36032 7.5 6.55109 7.5 6.75V11.25C7.5 11.4489 7.57902 11.6397 7.71967 11.7803C7.86032 11.921 8.05109 12 8.25 12Z" fill="#64676A"/>
                </svg>
                Delete
              </button>
            </div>
          </Teleport>
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
      <AppInput v-model="draft.name" label="Package Name" placeholder="e.g. Premium booth package" />

      <AppTextarea v-model="draft.description" label="Description" class="mt-3" rows="3" placeholder="What's included in this package" />

      <AppSelect v-model="draft.tier" label="Tier" :options="TIER_OPTIONS" class="mt-3" />

      <div class="grid grid-cols-2 gap-3 mt-3">
        <AppSelect v-model="draft.boothSize" label="Booth Size" placeholder="Select size" :options="BOOTH_SIZE_OPTIONS" />
        <AppInput v-model="draft.price" label="Price (USD)" type="number" min="0" step="0.01" placeholder="0" />
      </div>

      <label class="mt-4 block text-ink font-semibold text-[.92rem]">Features</label>
      <div class="flex flex-col gap-3 mt-2">
        <EntitlementRow
          v-for="f in mainFeatures"
          :key="f.key"
          :model-value="f"
          :label="featureLabel(f.key)"
          :countable="isCountable(f.key)"
          @update:model-value="Object.assign(f, $event)"
          :isFeatures="true"
        />
      </div>

      <label class="mt-4 block text-ink font-semibold text-[.92rem]">Marketing Perks</label>
      <div class="flex flex-col gap-3 mt-2">
        <EntitlementRow
          v-for="f in perkFeatures"
          :key="f.key"
          :model-value="f"
          :label="featureLabel(f.key)"
          :countable="isCountable(f.key)"
          @update:model-value="Object.assign(f, $event)"
          :isFeatures="false"
        />
      </div>

      <p v-if="error" class="error mt-3">{{ error }}</p>

      <template #footer>
        <div class="modal-actions border-t border-line px-5.5 py-4 justify-start">
          <button class="btn" :disabled="!draft.name.trim() || saving" @click="saveDraft">
            {{ saving ? 'Saving…' : editingId ? 'Update Package' : 'Create and Add Package' }}
          </button>
          <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
        </div>
      </template>
    </Drawer>
  </div>
</template>
