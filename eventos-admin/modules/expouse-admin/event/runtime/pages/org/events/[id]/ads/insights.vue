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

onMounted(load)
</script>

<template>
  <div class="max-w-[1100px]">
    <div class="mb-4">
      <h2 class="section-title m-0">Ad Insights</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Advertising performance across your event.</p>
    </div>

    <div v-if="loading" class="card muted text-center py-12">Loading insights…</div>

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
        <div class="font-bold text-[.98rem] mb-4">By ad</div>
        <table>
          <thead>
            <tr>
              <th>AD</th><th>PLACEMENT</th><th>STATUS</th>
              <th class="text-right">IMPRESSIONS</th><th class="text-right">CLICKS</th><th class="text-right">CTR</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in data.ads" :key="a.id">
              <td>
                <div class="flex items-center gap-2.5">
                  <div class="w-12 h-8 rounded-md overflow-hidden bg-[#f1f1f5] shrink-0 flex items-center justify-center">
                    <img v-if="a.image_url" :src="a.image_url" class="w-full h-full object-cover" :alt="a.title">
                    <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-4 h-4 text-muted"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                  </div>
                  <span class="font-medium text-ink truncate">{{ a.title }}</span>
                </div>
              </td>
              <td class="text-ink">{{ PLACEMENT_LABEL[a.placement] ?? a.placement }}</td>
              <td>
                <span class="px-2 py-0.5 rounded-full text-[.72rem] font-semibold" :class="a.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'">
                  {{ a.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="text-right text-ink font-medium">{{ fmt(a.impressions) }}</td>
              <td class="text-right text-ink font-medium">{{ fmt(a.clicks) }}</td>
              <td class="text-right text-ink font-medium">{{ a.ctr }}%</td>
            </tr>
            <tr v-if="!data.ads.length">
              <td colspan="6" class="text-center py-12 muted">No ads yet — create some in <strong>Manage ADs</strong> to see insights here.</td>
            </tr>
          </tbody>
        </table>
        <p v-if="data.ads.length && !data.totals.impressions" class="muted text-[.82rem] mt-3">
          No impressions recorded yet — metrics populate as your ads are shown and clicked in the event app.
        </p>
      </div>
    </template>

    <div v-else class="card muted text-center py-12">Could not load insights.</div>
  </div>
</template>
