<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface PlacementRow { placement: string; ads: number; active: number; impressions: number; clicks: number; ctr: number }
interface AdRow { id: number; title: string; placement: string; is_active: boolean; image_url: string | null; impressions: number; clicks: number; ctr: number }
interface Insights {
  totals: { ads: number; active: number; impressions: number; clicks: number; ctr: number }
  by_placement: PlacementRow[]
  ads: AdRow[]
}

const PLACEMENT_LABEL: Record<string, string> = { main: 'Main Ad', featured: 'Featured Ad', content: 'Content Ad' }

const data = ref<Insights | null>(null)
const loading = ref(true)

async function load() {
  loading.value = true
  try { data.value = (await api<any>(`/events/${id}/ads/insights`)).data }
  catch { data.value = null }
  finally { loading.value = false }
}

const maxPlacementImp = computed(() =>
  Math.max(1, ...(data.value?.by_placement.map(p => p.impressions) ?? [0])))

function fmt(n: number): string { return n.toLocaleString() }

const cards = computed(() => {
  const t = data.value?.totals
  return [
    { label: 'Total Ads',   value: fmt(t?.ads ?? 0) },
    { label: 'Active Ads',  value: fmt(t?.active ?? 0) },
    { label: 'Impressions', value: fmt(t?.impressions ?? 0) },
    { label: 'Clicks',      value: fmt(t?.clicks ?? 0) },
    { label: 'CTR',         value: `${t?.ctr ?? 0}%` },
  ]
})

const search = ref('')
const adColumns = [
  { key: 'title', label: 'Ad', sortable: true },
  { key: 'placement', label: 'Placement' },
  { key: 'is_active', label: 'Status' },
  { key: 'impressions', label: 'Impressions', align: 'right' as const, sortable: true },
  { key: 'clicks', label: 'Clicks', align: 'right' as const, sortable: true },
  { key: 'ctr', label: 'CTR', align: 'right' as const, sortable: true },
]
function adSearchText(a: AdRow) { return `${a.title} ${PLACEMENT_LABEL[a.placement] ?? a.placement}` }

onMounted(load)
</script>

<template>
  <div class="max-w-275">
    <div class="mb-4">
      <h2 class="section-title m-0">Ad Insights</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Advertising performance across your event.</p>
    </div>

    <div v-if="loading" class="card flex items-center justify-center gap-2.5 py-12 text-muted text-[.88rem]">
      <svg class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
        <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
      </svg>
      Loading insights…
    </div>

    <template v-else-if="data">
      <!-- Summary cards -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
        <div v-for="c in cards" :key="c.label" class="card p-4">
          <div class="text-[.78rem] text-muted font-medium uppercase tracking-wide">{{ c.label }}</div>
          <div class="text-[1.6rem] font-bold text-ink mt-1 leading-none">{{ c.value }}</div>
        </div>
      </div>

      <!-- Per-placement -->
      <div class="card mb-5">
        <div class="font-bold text-[.98rem] mb-4">By placement</div>
        <div class="flex flex-col gap-4">
          <div v-for="p in data.by_placement" :key="p.placement">
            <div class="flex items-center justify-between text-[.86rem] mb-1.5">
              <span class="font-semibold text-ink">{{ PLACEMENT_LABEL[p.placement] ?? p.placement }}</span>
              <span class="text-muted">
                {{ p.ads }} ad{{ p.ads !== 1 ? 's' : '' }} ·
                <span class="text-ink font-medium">{{ fmt(p.impressions) }}</span> impressions ·
                <span class="text-ink font-medium">{{ fmt(p.clicks) }}</span> clicks ·
                CTR <span class="text-ink font-medium">{{ p.ctr }}%</span>
              </span>
            </div>
            <div class="h-2.5 bg-[#f1f1f5] rounded-full overflow-hidden">
              <div class="h-full bg-[#6352e7] rounded-full transition-all" :style="{ width: `${Math.round(p.impressions / maxPlacementImp * 100)}%` }" />
            </div>
          </div>
        </div>
      </div>

      <!-- Per-ad table -->
      <div class="card">
        <div class="flex items-center justify-between gap-3 mb-4">
          <div class="font-bold text-[.98rem]">By ad</div>
          <SearchInput v-model="search" placeholder="Search ads…" class="max-w-65" />
        </div>

        <DataTable
          :items="data.ads"
          :columns="adColumns"
          :search="search"
          :search-text="adSearchText"
          row-key="id"
          storage-key="ad-insights"
        >
          <template #cell-title="{ row }">
            <div class="flex items-center gap-2.5">
              <div class="w-12 h-8 rounded-md overflow-hidden bg-[#f1f1f5] shrink-0 flex items-center justify-center">
                <img v-if="row.image_url" :src="row.image_url" class="w-full h-full object-cover" :alt="row.title">
                <AppIcon v-else name="camera" class="w-4 h-4 text-muted" />
              </div>
              <span class="font-medium text-ink truncate">{{ row.title }}</span>
            </div>
          </template>
          <template #cell-placement="{ row }">
            <span class="text-ink">{{ PLACEMENT_LABEL[row.placement] ?? row.placement }}</span>
          </template>
          <template #cell-is_active="{ row }">
            <span class="px-2 py-0.5 rounded-full text-[.72rem] font-semibold" :class="row.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'">
              {{ row.is_active ? 'Active' : 'Inactive' }}
            </span>
          </template>
          <template #cell-impressions="{ row }">
            <span class="text-ink font-medium">{{ fmt(row.impressions) }}</span>
          </template>
          <template #cell-clicks="{ row }">
            <span class="text-ink font-medium">{{ fmt(row.clicks) }}</span>
          </template>
          <template #cell-ctr="{ row }">
            <span class="text-ink font-medium">{{ row.ctr }}%</span>
          </template>
          <template #empty>
            <span class="muted">No ads yet. Create ads in <strong>Manage ADs</strong> to see insights here.</span>
          </template>
        </DataTable>
        <p v-if="data.ads.length && !data.totals.impressions" class="muted text-[.82rem] mt-3">
          No impressions recorded yet. Metrics will appear once your ads are shown in the event app.
        </p>
      </div>
    </template>

    <div v-else class="card muted text-center py-12">Could not load insights.</div>
  </div>
</template>
