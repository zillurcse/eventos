<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

const TYPES: [string, string][] = [
  ['workshop', 'Workshop'], ['networking', 'Networking'], ['round_table', 'Round Table'],
  ['sponsor_demo', 'Sponsor Demo'], ['team', 'Team Room'], ['private', 'Private Meeting'],
  ['vip', 'VIP Room'], ['interview', 'Interview Room'], ['panel', 'Panel Discussion'],
  ['ama', 'AMA Session'], ['custom', 'Custom Type'],
]
const ACCESS: { key: string, title: string, desc: string, icon: string }[] = [
  { key: 'anyone', title: 'Anyone', desc: 'Any event attendee can join.', icon: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z M3.6 9h16.8 M3.6 15h16.8 M12 3a13.5 13.5 0 0 1 0 18 13.5 13.5 0 0 1 0-18Z' },
  { key: 'coded', title: 'Coded', desc: 'Requires an access code to enter.', icon: 'M12 17a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z M6 11V7a6 6 0 0 1 12 0v4 M5 11h14v9a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-9Z' },
  { key: 'hidden', title: 'Hidden', desc: 'Only reachable by direct link or invite.', icon: 'M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.8 21.8 0 0 1 5.06-6.06 M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a21.8 21.8 0 0 1-2.16 3.19 M14.12 14.12a3 3 0 1 1-4.24-4.24 M1 1l22 22' },
]
const typeLabel = (k: string) => TYPES.find(([v]) => v === k)?.[1] || k

interface Room {
  id: number
  name: string
  description: string | null
  purpose: 'single' | 'multiple'
  type: string
  access_type: 'anyone' | 'coded' | 'hidden'
  access_code: string | null
  has_access_code: boolean
  capacity: number | null
  poster_url: string | null
  status: 'draft' | 'published' | 'archived'
  starts_at: string | null
  ends_at: string | null
}

const rooms   = ref<Room[]>([])
const loading = ref(true)
const filter  = ref<'all' | 'draft' | 'published' | 'archived'>('all')

const shown = computed(() =>
  filter.value === 'all' ? rooms.value : rooms.value.filter(r => r.status === filter.value),
)

async function load() {
  loading.value = true
  try {
    rooms.value = (await api<any>(`/events/${id}/breakout-rooms`)).data
  } catch { toast.error('Could not load breakout rooms.') } finally { loading.value = false }
}

// ── Drawer (create / edit) ────────────────────────────────────────────────
const drawer = reactive({ open: false, mode: 'create' as 'create' | 'edit', roomId: 0 })
const saving = ref(false)
const error  = ref('')

function freshForm() {
  return {
    name: '', description: '',
    purpose: 'single' as 'single' | 'multiple',
    type: 'workshop',
    access_type: 'anyone' as 'anyone' | 'coded' | 'hidden',
    access_code: '',
    capacity: null as number | null,
    poster_url: null as string | null,
    starts_at: '', ends_at: '',
  }
}
const form = reactive(freshForm())

// ISO <-> <input type="datetime-local"> (which is naive local time, no zone)
function toLocalInput(iso: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}
const fromLocalInput = (v: string): string | null => (v ? new Date(v).toISOString() : null)

function openCreate() {
  Object.assign(form, freshForm())
  drawer.mode = 'create'; drawer.roomId = 0
  error.value = ''; drawer.open = true
}
function openEdit(r: Room) {
  Object.assign(form, {
    name: r.name, description: r.description || '',
    purpose: r.purpose, type: r.type,
    access_type: r.access_type, access_code: r.access_code || '',
    capacity: r.capacity,
    poster_url: r.poster_url,
    starts_at: toLocalInput(r.starts_at), ends_at: toLocalInput(r.ends_at),
  })
  drawer.mode = 'edit'; drawer.roomId = r.id
  error.value = ''; drawer.open = true
}

async function save() {
  if (!form.name.trim()) { error.value = 'Please enter a room name.'; return }
  if (form.access_type === 'coded' && !form.access_code.trim()) {
    error.value = 'A coded room needs an access code.'; return
  }
  if (form.starts_at && form.ends_at && form.ends_at < form.starts_at) {
    error.value = 'End time must be after the start time.'; return
  }
  error.value = ''; saving.value = true
  const body = {
    name: form.name.trim(),
    description: form.description.trim() || null,
    purpose: form.purpose,
    type: form.type,
    access_type: form.access_type,
    access_code: form.access_type === 'coded' ? form.access_code.trim() : null,
    capacity: form.capacity || null,
    poster_url: form.poster_url,
    starts_at: fromLocalInput(form.starts_at),
    ends_at: fromLocalInput(form.ends_at),
  }
  try {
    if (drawer.mode === 'create') await api(`/events/${id}/breakout-rooms`, { method: 'POST', body })
    else await api(`/breakout-rooms/${drawer.roomId}`, { method: 'PUT', body })
    await load()
    drawer.open = false
    toast.success(drawer.mode === 'create' ? 'Room created' : 'Room updated')
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save room.'
    toast.error(error.value)
  } finally { saving.value = false }
}

async function setStatus(r: Room, status: Room['status']) {
  try {
    const res = await api<any>(`/breakout-rooms/${r.id}/status`, { method: 'PATCH', body: { status } })
    const i = rooms.value.findIndex(x => x.id === r.id)
    if (i >= 0) rooms.value[i] = res.data
    toast.success(`Room ${status}`)
  } catch (e: any) { toast.error(e?.data?.message || 'Could not update status.') }
}

async function duplicate(r: Room) {
  try {
    await api(`/breakout-rooms/${r.id}/duplicate`, { method: 'POST' })
    await load()
    toast.success('Room duplicated')
  } catch (e: any) { toast.error(e?.data?.message || 'Could not duplicate room.') }
}

async function remove(r: Room) {
  if (!confirm(`Delete "${r.name}"?`)) return
  try {
    await api(`/breakout-rooms/${r.id}`, { method: 'DELETE' })
    rooms.value = rooms.value.filter(x => x.id !== r.id)
    toast.success('Room deleted')
  } catch (e: any) { toast.error(e?.data?.message || 'Could not delete room.') }
}

const statusStyle: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-600',
  published: 'bg-green-50 text-green-700',
  archived: 'bg-amber-50 text-amber-700',
}
function fmtWhen(iso: string | null) {
  if (!iso) return ''
  return new Date(iso).toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

onMounted(load)
</script>

<template>
  <div>
    <div class="card">
      <div class="flex items-start justify-between gap-4 flex-wrap mb-3">
        <div>
          <div class="font-bold text-base">Breakout Rooms</div>
          <div class="muted text-[.85rem] mt-0.5">Create collaborative rooms attendees can join during your event.</div>
        </div>
        <button class="btn" @click="openCreate">+ New Room</button>
      </div>

      <!-- Status filter -->
      <div class="inline-flex bg-[#f7f7fa] border border-line rounded-xl p-1 gap-1 mb-4">
        <button
          v-for="f in (['all', 'draft', 'published', 'archived'] as const)" :key="f"
          class="px-3.5 py-1.5 rounded-lg text-[.8rem] font-semibold capitalize transition-colors"
          :class="filter === f ? 'bg-[#6352e7] text-white' : 'text-muted hover:text-ink'"
          @click="filter = f"
        >{{ f }}</button>
      </div>

      <div v-if="loading" class="flex items-center justify-center gap-2.5 py-14 text-muted text-[.88rem]">
        <svg class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
          <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
        </svg>
        Loading rooms…
      </div>

      <template v-else>
        <div v-if="!shown.length" class="text-center py-13 px-5">
          <div class="w-13.5 h-13.5 rounded-[14px] bg-[#f3f0ff] text-[#6352e7] grid place-items-center mx-auto mb-3.5">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 21h8M12 18v3"/></svg>
          </div>
          <p class="muted m-0">No {{ filter === 'all' ? '' : filter }} rooms yet.</p>
        </div>

        <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr))">
          <div
            v-for="r in shown" :key="r.id"
            class="border border-line rounded-xl overflow-hidden flex flex-col"
          >
            <div class="h-28 bg-[#f1f1f5] relative shrink-0">
              <img v-if="r.poster_url" :src="r.poster_url" class="w-full h-full object-cover" :alt="r.name">
              <div v-else class="w-full h-full flex items-center justify-center text-muted">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-7 h-7"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 21h8M12 18v3"/></svg>
              </div>
              <span class="absolute top-2 right-2 px-2 py-0.5 rounded-full text-[.7rem] font-semibold capitalize" :class="statusStyle[r.status]">{{ r.status }}</span>
            </div>

            <div class="p-3 flex flex-col gap-2 flex-1">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-semibold text-ink truncate">{{ r.name }}</span>
                <span class="px-1.5 py-0.5 rounded text-[.68rem] bg-[#eef0ff] text-[#6352e7] font-medium">{{ typeLabel(r.type) }}</span>
              </div>
              <p v-if="r.description" class="text-[.8rem] text-muted line-clamp-2">{{ r.description }}</p>

              <div class="text-[.76rem] text-muted flex flex-wrap gap-x-3 gap-y-0.5">
                <span class="capitalize">{{ r.purpose }} session{{ r.purpose === 'multiple' ? 's' : '' }}</span>
                <span class="capitalize">· {{ r.access_type }}<template v-if="r.access_type === 'coded' && r.has_access_code"> 🔒</template></span>
                <span v-if="r.capacity">· up to {{ r.capacity }}</span>
              </div>
              <div v-if="r.starts_at" class="text-[.76rem] text-muted">
                🗓 {{ fmtWhen(r.starts_at) }}<template v-if="r.ends_at"> – {{ fmtWhen(r.ends_at) }}</template>
              </div>

              <div class="flex items-center gap-1.5 mt-auto pt-2 flex-wrap">
                <button class="btn ghost text-[.78rem] px-2.5 py-1" @click="openEdit(r)">Edit</button>
                <button v-if="r.status !== 'published'" class="btn ghost text-[.78rem] px-2.5 py-1" @click="setStatus(r, 'published')">Publish</button>
                <button v-else class="btn ghost text-[.78rem] px-2.5 py-1" @click="setStatus(r, 'draft')">Unpublish</button>
                <button class="btn ghost text-[.78rem] px-2.5 py-1" @click="duplicate(r)">Duplicate</button>
                <button v-if="r.status !== 'archived'" class="btn ghost text-[.78rem] px-2.5 py-1" @click="setStatus(r, 'archived')">Archive</button>
                <button class="text-[#dc2626] text-[.78rem] font-medium px-2 hover:underline ml-auto" @click="remove(r)">Delete</button>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- ── Create / Edit Drawer ─────────────────────────────────────────── -->
    <Drawer v-if="drawer.open" :title="`${drawer.mode === 'create' ? 'Create' : 'Edit'} breakout room`" @close="drawer.open = false">
      <!-- Name -->
      <div class="mb-4">
        <AppInput v-model="form.name" label="Room Name" required placeholder="e.g. Product Deep-Dive" />
      </div>

      <!-- Description -->
      <div class="mb-4">
        <AppTextarea v-model="form.description" label="Room Description" :rows="3" placeholder="What happens in this room?" />
      </div>

      <!-- Purpose + Type -->
      <div class="flex gap-3 mb-4 flex-wrap">
        <div class="flex-1 min-w-[160px]">
          <AppSelect
            v-model="form.purpose"
            label="Room Purpose"
            :options="[{ value: 'single', label: 'Single Session' }, { value: 'multiple', label: 'Multiple Sessions' }]"
          />
        </div>
        <div class="flex-1 min-w-[160px]">
          <AppSelect
            v-model="form.type"
            label="Room Type"
            :options="TYPES.map(([value, label]) => ({ value, label }))"
          />
        </div>
      </div>

      <!-- Schedule -->
      <div class="flex gap-3 mb-4 flex-wrap">
        <div class="flex-1 min-w-[160px]">
          <FormField label="Start date &amp; time">
            <input v-model="form.starts_at" type="datetime-local" class="m-0 w-full">
          </FormField>
        </div>
        <div class="flex-1 min-w-[160px]">
          <FormField label="End date &amp; time">
            <input v-model="form.ends_at" type="datetime-local" class="m-0 w-full">
          </FormField>
        </div>
      </div>

      <!-- Access -->
      <div class="mb-4">
        <FormField label="Who can join the room">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
            <label
              v-for="a in ACCESS" :key="a.key"
              class="relative flex flex-col gap-2 border rounded-xl p-3 cursor-pointer transition-colors"
              :class="form.access_type === a.key ? 'border-[#6352e7] bg-[#f7f6ff]' : 'border-line hover:border-[#c9c4f5]'"
            >
              <input v-model="form.access_type" type="radio" :value="a.key" class="sr-only">
              <div class="flex items-center justify-between">
                <span
                  class="w-8 h-8 rounded-lg grid place-items-center shrink-0 transition-colors"
                  :class="form.access_type === a.key ? 'bg-[#6352e7] text-white' : 'bg-[#f1f1f5] text-muted'"
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path :d="a.icon"/></svg>
                </span>
                <span
                  class="w-4.5 h-4.5 rounded-full border-2 grid place-items-center shrink-0"
                  :class="form.access_type === a.key ? 'bg-[#6352e7] border-[#6352e7]' : 'bg-white border-[#d7dae1]'"
                >
                  <svg v-if="form.access_type === a.key" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                </span>
              </div>
              <span class="font-semibold text-ink text-[.88rem]">{{ a.title }}</span>
              <span class="text-[.78rem] text-muted leading-snug">{{ a.desc }}</span>
            </label>
          </div>
        </FormField>
        <div v-if="form.access_type === 'coded'" class="mt-3 max-w-60">
          <AppInput v-model="form.access_code" label="Access Code" required placeholder="e.g. EXPO2026" />
        </div>
      </div>

      <!-- Capacity -->
      <div class="mb-4 max-w-40">
        <AppInput v-model.number="form.capacity" type="number" label="Capacity" hint="Leave blank for unlimited" min="1" placeholder="Unlimited" />
      </div>

      <!-- Poster -->
      <div class="mb-5">
        <FormField label="Session Poster Image">
          <ImageField
            :model-value="form.poster_url"
            :aspect="2"
            collection="breakout_room_poster"
            card-width="320px"
            :gallery-path="`/events/${id}/gallery`"
            hint="Recommended 2:1 poster image."
            @update:model-value="form.poster_url = (Array.isArray($event) ? $event[0] : $event) || null"
          />
        </FormField>
      </div>

      <p v-if="error" class="error">{{ error }}</p>

      <div class="modal-actions border-t border-line pt-4 mt-2">
        <button class="btn ghost" @click="drawer.open = false">CANCEL</button>
        <button class="btn" :disabled="saving || !form.name.trim()" @click="save">
          {{ saving ? 'Saving…' : (drawer.mode === 'create' ? 'CREATE' : 'UPDATE') }}
        </button>
      </div>
    </Drawer>
  </div>
</template>
