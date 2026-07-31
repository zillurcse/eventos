<script setup lang="ts">
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface Tab { key: string, label: string, enabled: boolean }

// The catalogue of tabs the mobile app can surface in its bottom navigation.
const DEFAULT_TABS: Tab[] = [
  { key: 'reception', label: 'Reception', enabled: true },
  { key: 'feed', label: 'Event Feed', enabled: true },
  { key: 'sessions', label: 'Sessions', enabled: true },
  { key: 'speakers', label: 'Speakers', enabled: true },
  { key: 'delegates', label: 'Delegates', enabled: true },
  { key: 'exhibitors', label: 'Exhibitors', enabled: true },
  { key: 'meetings', label: 'Meetings', enabled: true },
  { key: 'lounge', label: 'Lounge', enabled: true },
  { key: 'rooms', label: 'Rooms', enabled: true },
  { key: 'contests', label: 'Contests', enabled: true },
  { key: 'expolens', label: 'ExpoLens', enabled: true },
  { key: 'my_badges', label: 'My Badges', enabled: true },
]

const tabs = ref<Tab[]>([])
const saving = ref(false)
const loading = ref(true)

function hydrate(saved: any[]) {
  const list: any[] = Array.isArray(saved) ? saved : []
  const savedKeys = new Set(list.map((t: any) => t.key))
  tabs.value = [
    ...list
      .filter((t: any) => DEFAULT_TABS.some(d => d.key === t.key))
      .map((t: any) => {
        const def = DEFAULT_TABS.find(d => d.key === t.key)!
        return {
          key: t.key,
          label: (typeof t.label === 'string' && t.label.trim()) ? t.label.trim() : def.label,
          enabled: t.enabled !== false,
        }
      }),
    ...DEFAULT_TABS.filter(d => !savedKeys.has(d.key)).map(d => ({ ...d })),
  ]
}

hydrate([])

async function load() {
  loading.value = true
  try {
    const res = await api<any>(`/events/${id}/settings`)
    hydrate(res.data?.manage_tabs || [])
  } catch { hydrate([]) }
  finally { loading.value = false }
}

async function save() {
  saving.value = true
  try {
    await api(`/events/${id}/settings`, {
      method: 'PUT',
      body: { manage_tabs: JSON.parse(JSON.stringify(tabs.value)) },
    })
    toast.success('Tabs saved')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not save.')
  } finally { saving.value = false }
}

onMounted(load)
</script>

<template>
  <div class="card">
    <div class="mb-4">
      <h2 class="section-title m-0">Manage Tabs</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">
        Choose which tabs appear in the mobile app, rename them, and drag to set their order.
      </p>
    </div>

    <div class="max-w-[560px]">
      <p class="muted text-[.84rem] mt-0 mb-3">Drag to reorder, click the pencil to rename, toggle to show/hide in the app navigation.</p>
      <SortableList v-model="tabs" editable />
    </div>

    <div class="mt-4 flex flex-wrap gap-1.5">
      <span
        v-for="t in tabs.filter((t: Tab) => t.enabled)" :key="t.key"
        class="bg-[#F0EEFD] text-brand-dark text-[.8rem] font-semibold px-2.5 py-0.5 rounded-full"
      >{{ t.label }}</span>
    </div>

    <div class="border-t border-line mt-6 pt-4 flex justify-end">
      <button class="btn px-8 py-3 tracking-widest" :disabled="saving || loading" @click="save">
        {{ saving ? 'SAVING…' : 'SAVE' }}
      </button>
    </div>
  </div>
</template>
