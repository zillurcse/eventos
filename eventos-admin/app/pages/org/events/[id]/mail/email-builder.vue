<script setup lang="ts">
import type { Block, EmailSettings } from '~/composables/useEmailBlocks'

definePageMeta({ middleware: 'organizer', layout: 'event' })

interface TemplateDto {
  id: string
  name: string
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
const editing = ref<TemplateDto | null>(null)
const editorOpen = ref(false)

async function load() {
  loading.value = true
  try {
    templates.value = (await api<{ data: TemplateDto[] }>(`/email-templates?event=${eventId}`)).data
  } finally {
    loading.value = false
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
      <button class="btn" @click="openNew"><Icon name="plus" class="w-[15px] h-[15px]" /> NEW TEMPLATE</button>
    </div>

    <div v-if="loading" class="card muted text-center py-10">Loading templates…</div>

    <div v-else-if="!templates.length" class="card text-center py-14">
      <div class="text-4xl mb-2">✉️</div>
      <h3 class="m-0 mb-1">No email templates yet</h3>
      <p class="muted text-[.88rem] mb-4">Build your first template — invitations, reminders, confirmations and more.</p>
      <button class="btn" @click="openNew"><Icon name="plus" class="w-[15px] h-[15px]" /> Create your first template</button>
    </div>

    <div v-else class="grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-4">
      <div
        v-for="t in templates"
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
