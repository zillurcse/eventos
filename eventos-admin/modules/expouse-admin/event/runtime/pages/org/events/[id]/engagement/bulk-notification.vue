<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

type Status = 'draft' | 'scheduled' | 'sent'
type Timing = 'now' | 'scheduled'

const DISPLAY_AREAS: { value: string, label: string }[] = [
  { value: 'all_pages', label: 'All Page' },
  { value: 'single_exhibitor', label: 'Single Exhibitor Page' },
  { value: 'all_exhibitors', label: 'All Exhibitors Page' },
  { value: 'single_sponsor', label: 'Single Sponsor Page' },
  { value: 'all_sponsors', label: 'All Sponsors Page' },
  { value: 'single_session', label: 'Single Session Page' },
  { value: 'all_sessions', label: 'All Sessions Page' },
  { value: 'single_contest', label: 'Single Contest Page' },
  { value: 'all_contests', label: 'All Contests Page' },
  { value: 'reception', label: 'Reception Page' },
  { value: 'event_feed', label: 'Event Feed Page' },
  { value: 'speakers', label: 'Speakers Page' },
  { value: 'delegates', label: 'Delegates Page' },
  { value: 'meetings_feed', label: 'Meetings Feed Page' },
]

const SINGLE_AREAS = new Set(['single_exhibitor', 'single_sponsor', 'single_session', 'single_contest'])

interface Announcement {
  id: number
  title: string
  body: string | null
  display_area: string | null
  audience: {
    all?: boolean
    specific?: boolean
    user_ids?: string[]
    target_id?: string | null
    target_label?: string | null
  }
  channels: { web?: boolean, mobile?: boolean }
  status: Status
  scheduled_at: string | null
  sent_at: string | null
  created_at: string | null
}

interface Participant {
  id: string
  name: string | null
  email: string | null
}

interface EntityOpt { id: string, label: string }

const items = ref<Announcement[]>([])
const loading = ref(true)
const filter = ref<'all' | Status>('all')
const search = ref('')

const participants = ref<Participant[]>([])
const exhibitors = ref<EntityOpt[]>([])
const sponsors = ref<EntityOpt[]>([])
const sessions = ref<EntityOpt[]>([])
const contests = ref<EntityOpt[]>([])

const shown = computed(() => {
  const q = search.value.trim().toLowerCase()
  return items.value
    .filter(n => filter.value === 'all' || n.status === filter.value)
    .filter(n => !q || n.title.toLowerCase().includes(q))
})

const areaLabel = (k: string | null) => DISPLAY_AREAS.find(a => a.value === k)?.label ?? (k || '—')

const statusStyle: Record<Status, string> = {
  draft: 'bg-gray-100 text-gray-700',
  scheduled: 'bg-blue-50 text-blue-700',
  sent: 'bg-green-50 text-green-700',
}

function stripHtml(html: string): string {
  return html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
}

function toLocalInput(iso: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}
function fromLocalInput(v: string): string | null {
  return v ? new Date(v).toISOString() : null
}
function fmtWhen(iso: string | null) {
  if (!iso) return ''
  return new Date(iso).toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function load() {
  loading.value = true
  try {
    items.value = (await api<{ data: Announcement[] }>('/announcements', { query: { event: id } })).data
  } catch {
    toast.error('Could not load notifications.')
  } finally {
    loading.value = false
  }
}

async function loadLookups() {
  try {
    const [pRes, eRes, spRes, sessRes, cRes] = await Promise.all([
      api<{ data: Participant[] }>(`/events/${id}/participants`).catch(() => ({ data: [] })),
      api<{ data: any[] }>(`/exhibitors?event=${id}`).catch(() => ({ data: [] })),
      api<{ data: any[] }>(`/exhibitors?event=${id}&type=sponsor`).catch(() => ({ data: [] })),
      api<{ data: any[] }>(`/sessions?event=${id}`).catch(() => ({ data: [] })),
      api<{ data: any[] }>(`/events/${id}/contests`).catch(() => ({ data: [] })),
    ])
    participants.value = pRes.data || []
    exhibitors.value = (eRes.data || []).map((e: any) => ({ id: String(e.id), label: e.name || e.title || 'Exhibitor' }))
    sponsors.value = (spRes.data || []).map((s: any) => ({ id: String(s.id), label: s.name || s.title || 'Sponsor' }))
    sessions.value = (sessRes.data || []).map((s: any) => ({ id: String(s.id), label: s.title || 'Session' }))
    contests.value = (cRes.data || []).map((c: any) => ({ id: String(c.id), label: c.title || 'Contest' }))
  } catch { /* lookups are optional for the form */ }
}

// ── Create / Edit drawer ────────────────────────────────────────────────────
const drawer = reactive({ open: false, mode: 'create' as 'create' | 'edit', announcementId: 0 })
const saving = ref(false)
const error = ref('')
const userQuery = ref('')

function freshForm() {
  return {
    title: '',
    body: '',
    display_area: '' as string,
    timing: 'now' as Timing,
    scheduled_at: '',
    web: true,
    mobile: true,
    specific: false,
    all: true,
    user_ids: [] as string[],
    target_id: '' as string,
  }
}
const form = reactive(freshForm())

const entityOptions = computed<EntityOpt[]>(() => {
  switch (form.display_area) {
    case 'single_exhibitor': return exhibitors.value
    case 'single_sponsor': return sponsors.value
    case 'single_session': return sessions.value
    case 'single_contest': return contests.value
    default: return []
  }
})

const needsEntity = computed(() => SINGLE_AREAS.has(form.display_area))

const filteredUsers = computed(() => {
  const q = userQuery.value.trim().toLowerCase()
  const list = participants.value
  if (!q) return list.slice(0, 40)
  return list.filter(p =>
    (p.name || '').toLowerCase().includes(q) || (p.email || '').toLowerCase().includes(q),
  ).slice(0, 40)
})

watch(() => form.display_area, () => { form.target_id = '' })

function setTiming(t: Timing) {
  form.timing = t
  if (t === 'now') form.scheduled_at = ''
}

function setTargetAll(on: boolean) {
  form.all = on
  if (on) {
    form.specific = false
    form.user_ids = []
  }
}

function setTargetSpecific(on: boolean) {
  form.specific = on
  if (on) form.all = false
  else form.user_ids = []
}

function toggleUser(uid: string) {
  const i = form.user_ids.indexOf(uid)
  if (i >= 0) form.user_ids.splice(i, 1)
  else form.user_ids.push(uid)
}

function openCreate() {
  Object.assign(form, freshForm())
  drawer.mode = 'create'
  drawer.announcementId = 0
  error.value = ''
  userQuery.value = ''
  drawer.open = true
}

function openEdit(n: Announcement) {
  Object.assign(form, {
    title: n.title,
    body: n.body || '',
    display_area: n.display_area || '',
    timing: (n.status === 'scheduled' ? 'scheduled' : 'now') as Timing,
    scheduled_at: toLocalInput(n.scheduled_at),
    web: n.channels?.web !== false,
    mobile: !!n.channels?.mobile,
    specific: !!n.audience?.specific,
    all: n.audience?.all !== false && !n.audience?.specific,
    user_ids: [...(n.audience?.user_ids || [])],
    target_id: n.audience?.target_id || '',
  })
  drawer.mode = 'edit'
  drawer.announcementId = n.id
  error.value = ''
  userQuery.value = ''
  drawer.open = true
}

function buildPayload(status: Status) {
  const target = entityOptions.value.find(o => o.id === form.target_id)
  return {
    event: id,
    title: form.title.trim(),
    body: form.body || null,
    display_area: form.display_area || null,
    status,
    scheduled_at: status === 'scheduled' ? fromLocalInput(form.scheduled_at) : null,
    channels: { web: form.web, mobile: form.mobile },
    audience: {
      all: form.all && !form.specific,
      specific: form.specific,
      user_ids: form.specific ? form.user_ids : [],
      target_id: needsEntity.value ? (form.target_id || null) : null,
      target_label: needsEntity.value ? (target?.label || null) : null,
    },
  }
}

function validate(): string | null {
  if (!form.title.trim()) return 'Please enter a title.'
  if (!form.display_area) return 'Please select a notification display area.'
  if (needsEntity.value && !form.target_id) return 'Please select the target page.'
  if (!form.web && !form.mobile) return 'Select at least one target: Web app or Mobile app.'
  if (!form.all && !form.specific) return 'Select All or Specific Users under Target Users.'
  if (form.specific && !form.user_ids.length) return 'Select at least one user.'
  if (form.timing === 'scheduled' && !form.scheduled_at) return 'Please pick a schedule date and time.'
  return null
}

async function save(as: 'draft' | 'send') {
  const status: Status = as === 'draft' ? 'draft' : (form.timing === 'scheduled' ? 'scheduled' : 'sent')
  const msg = validate()
  if (msg) { error.value = msg; return }

  error.value = ''
  saving.value = true
  try {
    const body = buildPayload(status)
    if (drawer.mode === 'create') {
      await api('/announcements', { method: 'POST', body })
    } else {
      await api(`/announcements/${drawer.announcementId}`, { method: 'PUT', body })
    }
    await load()
    drawer.open = false
    toast.success(
      status === 'draft' ? 'Draft saved'
        : status === 'scheduled' ? 'Notification scheduled'
          : 'Notification sent',
    )
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save notification.'
    toast.error(error.value)
  } finally {
    saving.value = false
  }
}

async function sendNow(n: Announcement) {
  try {
    await api(`/announcements/${n.id}/send`, { method: 'POST' })
    await load()
    toast.success('Notification sent')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not send notification.')
  }
}

async function remove(n: Announcement) {
  if (!confirm(`Delete "${n.title}"?`)) return
  try {
    await api(`/announcements/${n.id}`, { method: 'DELETE' })
    await load()
    toast.success('Notification deleted')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not delete notification.')
  }
}

onMounted(() => {
  load()
  loadLookups()
})
</script>

<template>
  <div>
    <div class="card">
      <div class="flex items-start justify-between gap-4 flex-wrap mb-3">
        <div>
          <div class="font-bold text-base">Bulk Notification</div>
          <div class="muted text-[.85rem] mt-0.5">
            Create and send notifications to attendees on web and mobile.
          </div>
        </div>
        <button class="btn" type="button" @click="openCreate">+ CREATE NOTIFICATION</button>
      </div>

      <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
        <div class="inline-flex bg-[#f7f7fa] border border-line rounded-xl p-1 gap-1">
          <button
            v-for="f in (['all', 'draft', 'scheduled', 'sent'] as const)"
            :key="f"
            type="button"
            class="px-3.5 py-1.5 rounded-lg text-[.8rem] font-semibold capitalize transition-colors"
            :class="filter === f ? 'bg-[#6352e7] text-white' : 'text-muted hover:text-ink'"
            @click="filter = f"
          >{{ f }}</button>
        </div>
        <SearchInput v-model="search" placeholder="Search notifications…" class="max-w-65" />
      </div>

      <div v-if="loading" class="flex items-center justify-center gap-2.5 py-14 text-muted text-[.88rem]">
        <svg class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
          <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
        </svg>
        Loading notifications…
      </div>

      <template v-else>
        <div v-if="!shown.length" class="text-center py-13 px-5">
          <div class="w-13.5 h-13.5 rounded-[14px] bg-[#f3f0ff] text-[#6352e7] grid place-items-center mx-auto mb-3.5">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
          </div>
          <p class="muted m-0 mb-3">
            {{ search ? 'No notifications match your search.' : `No ${filter === 'all' ? '' : filter + ' '}notifications yet.` }}
          </p>
          <button class="btn" type="button" @click="openCreate">+ CREATE NOTIFICATION</button>
        </div>

        <div v-else class="flex flex-col gap-2.5">
          <div
            v-for="n in shown"
            :key="n.id"
            class="border border-line rounded-xl px-4 py-3.5 flex items-start gap-3 flex-wrap"
          >
            <div class="flex-1 min-w-50">
              <div class="flex items-center gap-2 flex-wrap mb-1">
                <span class="font-semibold text-ink">{{ n.title }}</span>
                <span
                  class="px-2 py-0.5 rounded-full text-[.7rem] font-semibold capitalize"
                  :class="statusStyle[n.status]"
                >{{ n.status }}</span>
              </div>
              <p v-if="n.body" class="text-[.82rem] text-muted m-0 mb-1.5 line-clamp-2">{{ stripHtml(n.body) }}</p>
              <div class="text-[.76rem] text-muted flex flex-wrap gap-x-3 gap-y-0.5">
                <span>{{ areaLabel(n.display_area) }}</span>
                <span v-if="n.channels?.web">· Web</span>
                <span v-if="n.channels?.mobile">· Mobile</span>
                <span v-if="n.status === 'scheduled' && n.scheduled_at">· {{ fmtWhen(n.scheduled_at) }}</span>
                <span v-else-if="n.status === 'sent' && n.sent_at">· Sent {{ fmtWhen(n.sent_at) }}</span>
              </div>
            </div>
            <div class="flex items-center gap-1.5 flex-wrap">
              <button
                v-if="n.status !== 'sent'"
                class="btn ghost text-[.78rem] px-2.5 py-1"
                type="button"
                @click="openEdit(n)"
              >Edit</button>
              <button
                v-if="n.status === 'draft' || n.status === 'scheduled'"
                class="btn text-[.78rem] px-2.5 py-1"
                type="button"
                @click="sendNow(n)"
              >Send now</button>
              <button
                class="text-[#dc2626] text-[.78rem] font-medium px-2 hover:underline"
                type="button"
                @click="remove(n)"
              >Delete</button>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Create / Edit drawer (right panel) -->
    <Drawer
      v-if="drawer.open"
      :title="drawer.mode === 'create' ? 'Create Notification' : 'Edit Notification'"
      @close="drawer.open = false"
    >
      <div class="mb-4">
        <AppInput v-model="form.title" label="Title" placeholder="Enter Name" required />
      </div>

      <div class="mb-4">
        <FormField label="Description">
          <SessionDescriptionEditor v-model="form.body" />
        </FormField>
      </div>

      <div class="mb-4">
        <AppSelect
          v-model="form.display_area"
          label="Notification display area"
          :options="DISPLAY_AREAS"
          placeholder="Select display area"
          required
        />
      </div>

      <div v-if="needsEntity" class="mb-4">
        <AppSelect
          v-model="form.target_id"
          :label="areaLabel(form.display_area)"
          :options="entityOptions.map(o => ({ value: o.id, label: o.label }))"
          placeholder="Select…"
          required
        />
      </div>

      <div class="flex items-center gap-5 flex-wrap mb-4">
        <AppCheckbox
          label="Send now"
          :model-value="form.timing === 'now'"
          @update:model-value="(v: boolean) => v && setTiming('now')"
        />
        <AppCheckbox
          label="Scheduled"
          :model-value="form.timing === 'scheduled'"
          @update:model-value="(v: boolean) => v && setTiming('scheduled')"
        />
      </div>

      <div v-if="form.timing === 'scheduled'" class="mb-4">
        <FormField label="Schedule at" required>
          <input v-model="form.scheduled_at" type="datetime-local" class="m-0 w-full">
        </FormField>
      </div>

      <div class="mb-4">
        <p class="text-[.85rem] text-muted mb-2.5 mt-0">Target Users</p>
        <div class="flex items-center gap-4 flex-wrap mb-3">
          <AppCheckbox v-model="form.web" label="Web app" />
          <AppCheckbox v-model="form.mobile" label="Mobile app" />
          <AppCheckbox
            label="Specific Users"
            :model-value="form.specific"
            @update:model-value="setTargetSpecific"
          />
          <AppCheckbox
            label="All"
            :model-value="form.all"
            @update:model-value="setTargetAll"
          />
        </div>

        <div v-if="form.specific" class="border border-line rounded-xl p-3 bg-[#fafbfc]">
          <SearchInput v-model="userQuery" placeholder="Search users…" class="mb-2.5" />
          <div class="max-h-44 overflow-auto flex flex-col gap-1.5">
            <AppCheckbox
              v-for="p in filteredUsers"
              :key="p.id"
              size="sm"
              :label="p.name || p.email || 'User'"
              :description="p.name && p.email ? p.email : undefined"
              :model-value="form.user_ids.includes(p.id)"
              @update:model-value="toggleUser(p.id)"
            />
            <p v-if="!filteredUsers.length" class="text-[.8rem] text-muted m-0 py-2">No users found.</p>
          </div>
          <p v-if="form.user_ids.length" class="text-[.78rem] text-muted m-0 mt-2">
            {{ form.user_ids.length }} selected
          </p>
        </div>
      </div>

      <p v-if="error" class="error m-0 mb-3">{{ error }}</p>

      <div class="modal-actions !justify-between items-center border-t border-line pt-4 mt-2">
        <button
          type="button"
          class="bg-transparent border-0 cursor-pointer font-bold text-[.82rem] tracking-wide text-ink hover:text-brand px-0"
          :disabled="saving"
          @click="save('draft')"
        >SAVE AS DRAFT</button>
        <div class="flex gap-2.5">
          <button type="button" class="btn ghost" :disabled="saving" @click="drawer.open = false">Cancel</button>
          <button type="button" class="btn" :disabled="saving" @click="save('send')">
            {{ saving ? 'Saving…' : (form.timing === 'scheduled' ? 'Schedule' : 'Send now') }}
          </button>
        </div>
      </div>
    </Drawer>
  </div>
</template>
