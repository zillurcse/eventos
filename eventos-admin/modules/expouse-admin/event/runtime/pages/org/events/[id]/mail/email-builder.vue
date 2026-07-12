<script setup lang="ts">
import type { Block, EmailSettings } from '../../../../../composables/useEmailBlocks'

definePageMeta({ middleware: 'organizer', layout: 'event' })

interface TemplateDto {
  id: string
  name: string
  key?: string | null
  subject?: string | null
  from_name?: string | null
  from_email?: string | null
  reply_to?: string | null
  status?: string
  blocks?: Block[]
  settings?: Partial<EmailSettings>
  updated_at?: string | null
}

const route = useRoute()
const api = useApi()
const eventId = route.params.id as string

const templates = ref<TemplateDto[]>([])
const loading = ref(true)
const seeding = ref(false)
const editing = ref<TemplateDto | null>(null)
const editorOpen = ref(false)
const search = ref('')
const activeCategory = ref('all')

const CATEGORIES: { key: string, label: string }[] = [
  { key: 'all',          label: 'All' },
  { key: 'admin',        label: 'Admin' },
  { key: 'registration', label: 'Registration' },
  { key: 'auth',         label: 'Auth' },
  { key: 'onboarding',   label: 'Onboarding' },
  { key: 'event',        label: 'Event Lifecycle' },
  { key: 'engagement',   label: 'Engagement' },
  { key: 'meeting',      label: 'Meetings' },
  { key: 'session',      label: 'Sessions' },
  { key: 'lead',         label: 'Leads' },
  { key: 'action',       label: 'User Actions' },
  { key: 'exhibitor',    label: 'Exhibitor' },
  { key: 'post_event',   label: 'Post Event' },
]

const filtered = computed(() => {
  let list = templates.value
  if (activeCategory.value !== 'all') {
    list = list.filter(t => (t.key ?? '').startsWith(activeCategory.value + '.') || t.key === activeCategory.value)
  }
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    list = list.filter(t => t.name.toLowerCase().includes(q) || (t.subject ?? '').toLowerCase().includes(q))
  }
  return list
})

// only show tabs that have at least one template
const visibleCategories = computed(() => {
  const usedPrefixes = new Set(templates.value.map(t => (t.key ?? '').split('.')[0]))
  return CATEGORIES.filter(c => c.key === 'all' || usedPrefixes.has(c.key))
})

async function load() {
  loading.value = true
  try {
    templates.value = (await api<{ data: TemplateDto[] }>(`/email-templates?event=${eventId}`)).data
  } finally {
    loading.value = false
  }
}

async function seedDefaults() {
  seeding.value = true
  try {
    const res = await api<{ data: TemplateDto[] }>('/email-templates/seed', {
      method: 'POST',
      body: { event: eventId },
    })
    templates.value = res.data
  } finally {
    seeding.value = false
  }
}

function openNew() {
  editing.value = null
  editorOpen.value = true
}
async function openEdit(t: TemplateDto) {
  // index payload already carries blocks/settings, but refetch for the freshest design
  editing.value = (await api<{ data: TemplateDto }>(`/email-templates/${t.id}`)).data
  editorOpen.value = true
}
async function duplicate(t: TemplateDto) {
  await api(`/email-templates/${t.id}/duplicate`, { method: 'POST' })
  await load()
}
async function remove(t: TemplateDto) {
  if (!confirm(`Delete template "${t.name}"?`)) return
  await api(`/email-templates/${t.id}`, { method: 'DELETE' })
  await load()
}
function onSaved() {
  load()
}
function fmtDate(d?: string | null) {
  if (!d) return ''
  return new Date(d).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}

onMounted(load)
</script>

<template>
  <div>
    <div class="flex items-start justify-between gap-4 mb-4">
      <div>
        <h2 class="section-title m-0">Email Builder</h2>
        <p class="muted text-[.86rem] mt-0.5 mb-0">Design professional, responsive emails with dynamic variables and reuse them across your event.</p>
      </div>
    </div>

    <!-- search + category filters (only when templates exist) -->
    <template v-if="!loading && templates.length">
      <div class="flex items-center gap-2 mb-3">
        <div class="relative flex-1 max-w-[280px]">
          <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[#8b93a7]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
          <input v-model="search" class="m-0 pl-8 text-[.86rem]" placeholder="Search templates…">
        </div>
        <span v-if="filtered.length !== templates.length" class="text-[.78rem] text-[#8b93a7]">{{ filtered.length }} of {{ templates.length }}</span>
      </div>
      <div class="flex gap-1.5 flex-wrap mb-4">
        <button
          v-for="cat in visibleCategories"
          :key="cat.key"
          class="px-3 py-1 rounded-full text-[.76rem] font-semibold border cursor-pointer transition-colors"
          :class="activeCategory === cat.key
            ? 'bg-[#6352e7] border-[#6352e7] text-white'
            : 'bg-white border-line text-[#5f6b7a] hover:border-[#6352e7] hover:text-[#6352e7]'"
          @click="activeCategory = cat.key"
        >{{ cat.label }}</button>
      </div>
    </template>

    <div v-if="loading" class="card muted text-center py-10">Loading templates…</div>

    <div v-else-if="!templates.length" class="card text-center py-14">
      <div class="text-4xl mb-3">✉️</div>
      <h3 class="m-0 mb-1">No email templates yet</h3>
      <p class="muted text-[.88rem] mb-5">Set up all 36 default templates in one click: invitations, reminders, confirmations, meetings and more. Then customise them as needed.</p>
      <div class="flex items-center justify-center gap-3 flex-wrap">
        <button class="btn" :disabled="seeding" @click="seedDefaults">
          <svg v-if="seeding" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
          <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83"/><circle cx="12" cy="12" r="3"/></svg>
          {{ seeding ? 'Setting up templates…' : 'Set Up Default Templates' }}
        </button>
        <button class="btn ghost" @click="openNew"><AppIcon name="plus" class="w-[15px] h-[15px]" /> Create manually</button>
      </div>
    </div>

    <div v-else-if="templates.length && !filtered.length" class="card text-center py-10 text-[#8b93a7] text-[.88rem]">
      No templates match your search.
      <button class="ml-2 text-[#6352e7] underline bg-transparent border-0 cursor-pointer" @click="search = ''; activeCategory = 'all'">Clear filters</button>
    </div>

    <div v-else class="grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-4">
      <div
        v-for="t in filtered"
        :key="t.id"
        class="card !p-0 overflow-hidden cursor-pointer group hover:shadow-md transition-shadow"
        @click="openEdit(t)"
      >
        <div class="h-[120px] bg-gradient-to-br from-[#f5f3ff] to-[#eef0fb] grid place-items-center border-b border-line">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#a99ff0" stroke-width="1.6"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 7l9 6 9-6" /></svg>
        </div>
        <div class="p-3.5">
          <div class="flex items-center gap-2">
            <h3 class="m-0 text-[.95rem] truncate flex-1">{{ t.name }}</h3>
            <span class="text-[.64rem] uppercase tracking-wide px-1.5 py-0.5 rounded" :class="t.status === 'published' ? 'text-[#15803d] bg-[#dcfce7]' : 'text-[#b45309] bg-[#fef3c7]'">{{ t.status || 'draft' }}</span>
          </div>
          <div v-if="t.key" class="mt-1 mb-1">
            <span class="text-[.62rem] text-[#6352e7] bg-[#f0eefe] px-1.5 py-0.5 rounded font-medium">{{ (t.key.split('.')[0] ?? t.key).replace('_', ' ') }}</span>
          </div>
          <p class="muted text-[.78rem] mt-1 mb-2 truncate">{{ t.subject || 'No subject set' }}</p>
          <div class="flex items-center justify-between">
            <span class="muted text-[.72rem]">{{ fmtDate(t.updated_at) }}</span>
            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity" @click.stop>
              <button class="text-[#6352e7] bg-transparent border-0 cursor-pointer px-1.5 text-[.95rem]" title="Edit" @click="openEdit(t)">✎</button>
              <button class="text-[#5f6b7a] bg-transparent border-0 cursor-pointer px-1.5 text-[.95rem]" title="Duplicate" @click="duplicate(t)">⧉</button>
              <button class="text-[#dc2626] bg-transparent border-0 cursor-pointer px-1.5 text-[.95rem]" title="Delete" @click="remove(t)">🗑</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <MailEmailEditor
      v-if="editorOpen"
      :event-id="eventId"
      :template="editing"
      @close="editorOpen = false"
      @saved="onSaved"
    />
  </div>
</template>
