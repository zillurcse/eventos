<script setup lang="ts">
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

type Status = 'draft' | 'scheduled' | 'sent'
type Timing = 'now' | 'scheduled'
type AudienceRole = 'attendee' | 'sponsor' | 'exhibitor' | 'speaker'

const DISPLAY_AREAS: { value: string, label: string }[] = [
  { value: 'all_pages', label: 'All Pages' },
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

const ROLE_OPTIONS: { value: AudienceRole, label: string }[] = [
  { value: 'attendee', label: 'Attendees' },
  { value: 'sponsor', label: 'Sponsors' },
  { value: 'exhibitor', label: 'Exhibitors' },
  { value: 'speaker', label: 'Speakers' },
]

const ROLE_LABEL: Record<AudienceRole, string> = {
  attendee: 'Attendees',
  sponsor: 'Sponsors',
  exhibitor: 'Exhibitors',
  speaker: 'Speakers',
}

interface Announcement {
  id: number
  title: string
  body: string | null
  display_area: string | null
  audience: {
    all?: boolean
    specific?: boolean
    roles?: AudienceRole[]
    user_ids?: string[]
    target_id?: string | null
    target_label?: string | null
  }
  channels: { web?: boolean, mobile?: boolean }
  status: Status
  scheduled_at: string | null
  sent_at: string | null
  created_at: string | null
  reach?: number
  clicked?: number
}

interface Participant {
  id: string
  name: string | null
  email: string | null
}

interface EntityOpt { id: string, label: string }

const items = ref<Announcement[]>([])
const loading = ref(true)
const filter = ref<Status>('draft')
const search = ref('')
const selected = ref<(string | number)[]>([])
const openMenuId = ref<number | null>(null)
const menuPos = reactive({ top: 0, right: 0 })

const participants = ref<Participant[]>([])
const exhibitors = ref<EntityOpt[]>([])
const sponsors = ref<EntityOpt[]>([])
const sessions = ref<EntityOpt[]>([])
const contests = ref<EntityOpt[]>([])

const shown = computed(() => {
  const q = search.value.trim().toLowerCase()
  return items.value
    .filter(n => n.status === filter.value)
    .filter(n => !q || n.title.toLowerCase().includes(q))
})

const columns = [
  { key: 'notifications', label: 'Notifications' },
  { key: 'target', label: 'Target' },
  { key: 'send_at', label: 'Send at' },
  { key: 'reach', label: 'Reach' },
  { key: 'clicked', label: 'Clicked' },
]

const emptyCopy: Record<Status, string> = {
  draft: 'Notifications currently not available in drafts.',
  scheduled: 'No scheduled notifications yet.',
  sent: 'No sent notifications yet.',
}

const areaLabel = (k: string | null) => DISPLAY_AREAS.find(a => a.value === k)?.label ?? (k || '—')

function targetLabel(n: Announcement): string {
  const a = n.audience || {}
  if (a.specific) return 'Specific Users'
  if (a.all !== false && !a.roles?.length) return 'All'
  if (a.roles?.length) return a.roles.map(r => ROLE_LABEL[r] || r).join(', ')
  return 'All'
}

function fmtWhen(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString([], {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

function fmtSub(iso: string | null) {
  if (!iso) return ''
  return new Date(iso).toLocaleString([], {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
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
    all: false,
    roles: [] as AudienceRole[],
    specific: false,
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

const canSend = computed(() => {
  if (!form.title.trim() || !form.display_area) return false
  if (needsEntity.value && !form.target_id) return false
  if (!form.all && !form.specific && !form.roles.length) return false
  if (form.specific && !form.user_ids.length) return false
  if (form.timing === 'scheduled' && !form.scheduled_at) return false
  return true
})

watch(() => form.display_area, () => { form.target_id = '' })
watch(filter, () => {
  selected.value = []
  openMenuId.value = null
})

function setTiming(t: Timing) {
  form.timing = t
  if (t === 'now') form.scheduled_at = ''
}

function setTargetAll(on: boolean) {
  form.all = on
  if (on) {
    form.specific = false
    form.roles = []
    form.user_ids = []
  }
}

function toggleRole(role: AudienceRole, on: boolean) {
  form.all = false
  if (on) {
    if (!form.roles.includes(role)) form.roles.push(role)
  } else {
    form.roles = form.roles.filter(r => r !== role)
  }
}

function setTargetSpecific(on: boolean) {
  form.specific = on
  if (on) {
    form.all = false
    form.roles = []
  } else {
    form.user_ids = []
  }
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
  const roles = (n.audience?.roles || []).filter((r): r is AudienceRole =>
    ['attendee', 'sponsor', 'exhibitor', 'speaker'].includes(r),
  )
  Object.assign(form, {
    title: n.title,
    body: n.body || '',
    display_area: n.display_area || '',
    timing: (n.status === 'scheduled' ? 'scheduled' : 'now') as Timing,
    scheduled_at: toLocalInput(n.scheduled_at),
    specific: !!n.audience?.specific,
    all: n.audience?.all !== false && !n.audience?.specific && !roles.length,
    roles: n.audience?.specific ? [] : roles,
    user_ids: [...(n.audience?.user_ids || [])],
    target_id: n.audience?.target_id || '',
  })
  drawer.mode = 'edit'
  drawer.announcementId = n.id
  error.value = ''
  userQuery.value = ''
  openMenuId.value = null
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
    channels: { web: true, mobile: true },
    audience: {
      all: form.all && !form.specific && !form.roles.length,
      specific: form.specific,
      roles: form.specific || form.all ? [] : form.roles,
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
  if (!form.all && !form.specific && !form.roles.length) return 'Select at least one target audience.'
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
    if (as === 'send') filter.value = status
    else filter.value = 'draft'
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
  openMenuId.value = null
  try {
    await api(`/announcements/${n.id}/send`, { method: 'POST' })
    await load()
    filter.value = 'sent'
    toast.success('Notification sent')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not send notification.')
  }
}

async function remove(n: Announcement) {
  openMenuId.value = null
  if (!confirm(`Delete "${n.title}"?`)) return
  try {
    await api(`/announcements/${n.id}`, { method: 'DELETE' })
    await load()
    toast.success('Notification deleted')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not delete notification.')
  }
}

function toggleMenu(rowId: number, e: MouseEvent) {
  if (openMenuId.value === rowId) {
    openMenuId.value = null
    return
  }
  const el = e.currentTarget as HTMLElement
  const rect = el.getBoundingClientRect()
  menuPos.top = rect.bottom + 4
  menuPos.right = window.innerWidth - rect.right
  openMenuId.value = rowId
}

function onDocClick() {
  openMenuId.value = null
}

onMounted(() => {
  load()
  loadLookups()
  if (import.meta.client) document.addEventListener('click', onDocClick)
})
onBeforeUnmount(() => {
  if (import.meta.client) document.removeEventListener('click', onDocClick)
})
</script>

<template>
  <div>
    <div class="mb-4">
      <h2 class="section-title m-0">Bulk Notification</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Send notifications to users</p>
    </div>

    <div class="flex items-center gap-4 flex-wrap mb-4">
      <div class="tabs border-b-0 !mb-0 flex-1 min-w-0">
        <button
          v-for="f in (['draft', 'scheduled', 'sent'] as const)"
          :key="f"
          type="button"
          class="tab font-bold capitalize"
          :class="{ active: filter === f }"
          @click="filter = f"
        >{{ f }}</button>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <SearchInput v-model="search" placeholder="Search" class="search-lg w-60" />
        <button class="btn bg-brand" type="button" @click="openCreate">
          <AppIcon name="plus" class="w-[14px] h-[14px]" /> Notification
        </button>
      </div>
    </div>

    <div v-if="loading" class="card muted text-center py-10">Loading notifications…</div>

    <div
      v-else-if="!shown.length"
      class="card text-center py-16 px-5"
    >
      <div class="w-16 h-16 mx-auto mb-4 text-[#c5c7d0]">
        <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="w-full h-full">
          <path d="M32 10a14 14 0 0 0-14 14c0 16-6 20-6 20h40s-6-4-6-20A14 14 0 0 0 32 10z"/>
          <path d="M28 50a4.5 4.5 0 0 0 8 0"/>
          <path d="M20 24h24" opacity=".35"/>
        </svg>
      </div>
      <p class="font-semibold text-ink text-[1.05rem] m-0 mb-1">No Notifications</p>
      <p class="muted text-[.88rem] m-0 mb-5">
        {{ search ? 'No notifications match your search.' : emptyCopy[filter] }}
      </p>
      <button class="btn bg-brand" type="button" @click="openCreate">
        <AppIcon name="plus" class="w-[14px] h-[14px]" /> Notification
      </button>
    </div>

    <DataTable
      v-else
      v-model:selected="selected"
      :items="shown"
      :columns="columns"
      selectable
      row-key="id"
      storage-key="bulk-notification"
      per-page-label="Records per page"
      :show-range-text="false"
      class="round-pager"
    >
      <template #cell-notifications="{ row }">
        <div class="min-w-0">
          <div class="font-semibold text-ink text-[.92rem] truncate">{{ row.title }}</div>
          <div class="muted text-[.78rem] truncate">
            {{ fmtSub(row.status === 'sent' ? row.sent_at : (row.scheduled_at || row.created_at)) }}
          </div>
        </div>
      </template>

      <template #cell-target="{ row }">
        <span class="text-ink">{{ targetLabel(row) }}</span>
      </template>

      <template #cell-send_at="{ row }">
        <span class="text-muted text-[.86rem]">
          {{ fmtWhen(row.status === 'sent' ? row.sent_at : row.scheduled_at) }}
        </span>
      </template>

      <template #cell-reach="{ row }">
        <span class="text-ink">{{ row.reach ?? 0 }}</span>
      </template>

      <template #cell-clicked="{ row }">
        <span class="text-ink">{{ row.clicked ?? 0 }}</span>
      </template>

      <template #actions="{ row }">
        <div class="relative inline-flex" @click.stop>
          <button
            type="button"
            class="w-8 h-8 rounded-lg grid place-items-center text-muted hover:bg-[#f1f2f6] border-0 bg-transparent cursor-pointer"
            aria-label="Actions"
            @click="toggleMenu(row.id, $event)"
          >
            <svg viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
          </button>
          <Teleport to="body">
            <div
              v-if="openMenuId === row.id"
              class="bg-white border border-[#1a1a2e] rounded-lg shadow-lg z-30 min-w-40 overflow-hidden py-1"
              :style="{ position: 'fixed', top: menuPos.top + 'px', right: menuPos.right + 'px', left: 'auto' }"
              @click.stop
            >
              <button
                v-if="row.status !== 'sent'"
                type="button"
                class="block w-full text-left px-4 py-2.5 text-[.88rem] text-ink hover:bg-[#f7f8fa] bg-transparent border-0 cursor-pointer"
                @click="openEdit(row)"
              >Edit</button>
              <button
                v-if="row.status === 'draft' || row.status === 'scheduled'"
                type="button"
                class="block w-full text-left px-4 py-2.5 text-[.88rem] text-ink hover:bg-[#f7f8fa] bg-transparent border-0 cursor-pointer"
                @click="sendNow(row)"
              >Send now</button>
              <button
                type="button"
                class="block w-full text-left px-4 py-2.5 text-[.88rem] text-[#dc2626] hover:bg-[#fef2f2] bg-transparent border-0 cursor-pointer"
                @click="remove(row)"
              >Delete</button>
            </div>
          </Teleport>
        </div>
      </template>
    </DataTable>

    <!-- Create / Edit drawer -->
    <Drawer
      v-if="drawer.open"
      :title="drawer.mode === 'create' ? 'New Notification' : 'Edit Notification'"
      @close="drawer.open = false"
    >
      <div class="mb-4">
        <AppInput v-model="form.title" label="Title" placeholder="Enter Title" required />
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
          placeholder="Select"
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

      <hr class="border-0 border-t border-line my-5">

      <div class="mb-5">
        <p class="font-bold text-ink text-[.95rem] m-0 mb-3">Target Users</p>
        <div class="grid grid-cols-3 gap-x-4 gap-y-3">
          <AppCheckbox
            label="All"
            :model-value="form.all"
            @update:model-value="setTargetAll"
          />
          <AppCheckbox
            v-for="opt in ROLE_OPTIONS"
            :key="opt.value"
            :label="opt.label"
            :model-value="form.roles.includes(opt.value)"
            @update:model-value="(v: boolean) => toggleRole(opt.value, v)"
          />
          <AppCheckbox
            label="Specific Users"
            :model-value="form.specific"
            @update:model-value="setTargetSpecific"
          />
        </div>

        <div v-if="form.specific" class="border border-line rounded-xl p-3 bg-[#fafbfc] mt-3">
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

      <div class="mb-5">
        <p class="font-bold text-ink text-[.95rem] m-0 mb-3">Sending Option</p>
        <div class="grid grid-cols-2 gap-3">
          <button
            type="button"
            class="flex items-center gap-3 px-4 py-3.5 rounded-xl border text-left cursor-pointer bg-white transition-colors"
            :class="form.timing === 'now'
              ? 'border-brand bg-[#f3f0ff]'
              : 'border-line hover:border-[#c9c5e8]'"
            @click="setTiming('now')"
          >
            <span
              class="w-[18px] h-[18px] rounded-full border-2 grid place-items-center shrink-0"
              :class="form.timing === 'now' ? 'border-brand' : 'border-[#c5c7d0]'"
            >
              <i v-if="form.timing === 'now'" class="block w-2 h-2 rounded-full bg-brand" />
            </span>
            <span class="font-semibold text-ink text-[.9rem]">Send Now</span>
          </button>
          <button
            type="button"
            class="flex items-center gap-3 px-4 py-3.5 rounded-xl border text-left cursor-pointer bg-white transition-colors"
            :class="form.timing === 'scheduled'
              ? 'border-brand bg-[#f3f0ff]'
              : 'border-line hover:border-[#c9c5e8]'"
            @click="setTiming('scheduled')"
          >
            <span
              class="w-[18px] h-[18px] rounded-full border-2 grid place-items-center shrink-0"
              :class="form.timing === 'scheduled' ? 'border-brand' : 'border-[#c5c7d0]'"
            >
              <i v-if="form.timing === 'scheduled'" class="block w-2 h-2 rounded-full bg-brand" />
            </span>
            <span class="font-semibold text-ink text-[.9rem]">Scheduled</span>
          </button>
        </div>
      </div>

      <div v-if="form.timing === 'scheduled'" class="mb-4">
        <FormField label="Schedule at" required>
          <input v-model="form.scheduled_at" type="datetime-local" class="m-0 w-full">
        </FormField>
      </div>

      <p v-if="error" class="error m-0 mb-3">{{ error }}</p>

      <div class="modal-actions !justify-start items-center gap-2.5 border-t border-line pt-4 mt-2 flex-wrap">
        <button
          type="button"
          class="btn"
          :class="canSend && !saving ? 'bg-brand' : '!bg-[#e8e8ee] !text-[#9a9aab] !border-[#e8e8ee] cursor-not-allowed'"
          :disabled="saving || !canSend"
          @click="save('send')"
        >
          {{ saving ? 'Saving…' : (form.timing === 'scheduled' ? 'Schedule' : 'Send Now') }}
        </button>
        <button
          type="button"
          class="btn !bg-[#f3f0ff] !text-brand !border-[#f3f0ff] hover:!bg-[#ebe6ff]"
          :disabled="saving"
          @click="drawer.open = false"
        >Cancel</button>
        <button
          type="button"
          class="btn !bg-[#f3f0ff] !text-brand !border-[#f3f0ff] hover:!bg-[#ebe6ff] ml-auto"
          :disabled="saving"
          @click="save('draft')"
        >Save as Draft</button>
      </div>
    </Drawer>
  </div>
</template>
