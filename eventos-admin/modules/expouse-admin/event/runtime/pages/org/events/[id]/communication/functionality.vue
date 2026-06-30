<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'

declare const definePageMeta: (meta: Record<string, unknown>) => void
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

// ── Catalogue ─────────────────────────────────────────────────────────
const ROLES = [
  { key: 'attendee',  label: 'Attendee' },
  { key: 'speaker',   label: 'Speaker' },
  { key: 'exhibitor', label: 'Exhibitor' },
  { key: 'sponsor',   label: 'Sponsor' },
]
const OPERATIONS = [
  { key: 'create_feed_text',        label: 'Create feed text post' },
  { key: 'create_feed_image',       label: 'Create feed image post' },
  { key: 'create_feed_video',       label: 'Create feed video post' },
  { key: 'create_feed_polls',       label: 'Create feed polls post' },
  { key: 'create_feed_offering',    label: 'Create feed offering post' },
  { key: 'create_feed_looking_for', label: 'Create feed looking for post' },
  { key: 'comment_feed_post',       label: 'Comment feed post' },
  { key: 'feed_post_likes',         label: 'Feed post likes' },
  { key: 'create_agenda_post',      label: 'Create sessions/ Agenda post' },
  { key: 'create_agenda_qa',        label: 'Create sessions/ Agenda Q&A' },
  { key: 'create_agenda_polls',     label: 'Create sessions/ Agenda polls' },
  { key: 'vote_feed_polls',         label: 'Vote feed Polls' },
  { key: 'vote_agenda_polls',       label: 'Vote sessions/ Agenda polls' },
]
const MODERATION = [
  { key: 'agenda_question', label: 'Agenda question' },
  { key: 'create_post',     label: 'Create post' },
  { key: 'create_polls',    label: 'Create polls' },
]
const DEFAULT_TABS = [
  { key: 'all',         label: 'All',         enabled: true },
  { key: 'images',      label: 'Images',      enabled: true },
  { key: 'video',       label: 'Video',       enabled: true },
  { key: 'pdf',         label: 'Pdf',         enabled: true },
  { key: 'polls',       label: 'Polls',       enabled: true },
  { key: 'offers',      label: 'Offers',      enabled: true },
  { key: 'looking_for', label: 'Looking For', enabled: true },
  { key: 'my_posts',    label: 'My Posts',    enabled: true },
]

// ── State ─────────────────────────────────────────────────────────────
const func       = reactive<Record<string, Record<string, boolean>>>({})
const moderation = reactive<Record<string, boolean>>({})
const feedTabs   = ref<{ key: string, label: string, enabled: boolean }[]>([])
const drawerOpen = ref(false)
const saving     = ref(false)
const loading    = ref(true)

function hydrate(c: any) {
  OPERATIONS.forEach((op) => {
    func[op.key] = {}
    ROLES.forEach((r) => { func[op.key][r.key] = c?.functionality?.[op.key]?.[r.key] ?? true })
  })
  MODERATION.forEach((m) => { moderation[m.key] = c?.moderation?.[m.key] ?? true })
  // Always show the full tab catalogue: honour the saved order/enabled state,
  // then append any catalogue tabs not yet present.
  const saved: any[] = Array.isArray(c?.feed_tabs) ? c.feed_tabs : []
  const savedKeys = new Set(saved.map((t: any) => t.key))
  feedTabs.value = [
    ...saved
      .filter((t: any) => DEFAULT_TABS.some(d => d.key === t.key))
      .map((t: any) => ({ key: t.key, label: DEFAULT_TABS.find(d => d.key === t.key)!.label, enabled: t.enabled !== false })),
    ...DEFAULT_TABS.filter(d => !savedKeys.has(d.key)).map(d => ({ ...d })),
  ]
}

// Seed defaults synchronously so the matrix exists on first render (the real
// values arrive from the async load() below).
hydrate({})

// Tick every cell of a column / row at once (quality-of-life helpers).
function setColumn(role: string, value: boolean) { OPERATIONS.forEach(op => (func[op.key][role] = value)) }
function columnAll(role: string) { return OPERATIONS.every(op => func[op.key]?.[role]) }

async function load() {
  loading.value = true
  try {
    const res = await api<any>(`/events/${id}/settings`)
    hydrate(res.data?.communication || {})
  } catch { hydrate({}) }
  finally { loading.value = false }
}

async function save() {
  saving.value = true
  try {
    const communication = {
      functionality: JSON.parse(JSON.stringify(func)),
      moderation: JSON.parse(JSON.stringify(moderation)),
      feed_tabs: JSON.parse(JSON.stringify(feedTabs.value)),
    }
    await api(`/events/${id}/settings`, { method: 'PUT', body: { communication } })
    toast.success('Functionality saved')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not save.')
  } finally { saving.value = false }
}

onMounted(load)
</script>

<template>
  <div class="card">
    <!-- ── Functionality matrix ──────────────────────────────────────── -->
    <div class="mb-4">
      <h2 class="section-title m-0">Functionality</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Categories &amp; assign User authentication for each type of user in web/Mobile App.</p>
    </div>

    <table>
      <thead>
        <tr>
          <th>Sections</th>
          <th v-for="r in ROLES" :key="r.key" class="text-center">
            <div class="flex flex-col items-center gap-1">
              <span>{{ r.label }}</span>
              <input
                type="checkbox" class="w-4 h-4 m-0 accent-brand cursor-pointer"
                :checked="columnAll(r.key)" title="Toggle all"
                @change="setColumn(r.key, ($event.target as HTMLInputElement).checked)"
              >
            </div>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="op in OPERATIONS" :key="op.key">
          <td class="text-ink">{{ op.label }}</td>
          <td v-for="r in ROLES" :key="r.key" class="text-center">
            <input v-model="func[op.key][r.key]" type="checkbox" class="w-4.5 h-4.5 m-0 accent-brand cursor-pointer rounded">
          </td>
        </tr>
      </tbody>
    </table>

    <!-- ── Moderation ────────────────────────────────────────────────── -->
    <div class="border-t border-line mt-6 pt-5">
      <h2 class="section-title m-0">Moderation</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-3">Moderate user entries to maintain a healthy feed.</p>
      <div class="flex items-center gap-6 flex-wrap">
        <label v-for="m in MODERATION" :key="m.key" class="flex items-center gap-2 m-0 cursor-pointer text-[.92rem] text-ink">
          <input v-model="moderation[m.key]" type="checkbox" class="w-4.5 h-4.5 m-0 accent-brand rounded"> {{ m.label }}
        </label>
      </div>
    </div>

    <!-- ── Allowed feed tabs ─────────────────────────────────────────── -->
    <div class="border-t border-line mt-6 pt-5">
      <h2 class="section-title m-0">Allowed feed tabs</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-3">Choose the tabs you want to be displayed on the feed page</p>
      <button class="btn ghost tracking-wide px-5 py-2.5" @click="drawerOpen = true">MANAGE</button>
      <div class="mt-3 flex flex-wrap gap-1.5">
        <span
          v-for="t in feedTabs.filter((t: any) => t.enabled)" :key="t.key"
          class="bg-brand-soft text-brand-dark text-[.8rem] font-semibold px-2.5 py-0.5 rounded-full"
        >{{ t.label }}</span>
      </div>
    </div>

    <!-- ── Save ──────────────────────────────────────────────────────── -->
    <div class="border-t border-line mt-6 pt-4 flex justify-end">
      <button class="btn px-8 py-3 tracking-widest" :disabled="saving || loading" @click="save">
        {{ saving ? 'SAVING…' : 'SAVE' }}
      </button>
    </div>

    <!-- ── Feed Tabs drawer ──────────────────────────────────────────── -->
    <Drawer v-if="drawerOpen" title="Feed Tabs" @close="drawerOpen = false">
      <p class="muted text-[.84rem] mt-0 mb-3">Drag to reorder, toggle to show/hide on the feed page.</p>
      <SortableList v-model="feedTabs" />
    </Drawer>
  </div>
</template>
