<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const { upload } = useUpload()
const id = route.params.id as string

const TYPES: [string, string][] = [
  ['workshop', 'Workshop'], ['networking', 'Networking'], ['round_table', 'Round Table'],
  ['sponsor_demo', 'Sponsor Demo'], ['team', 'Team Room'], ['private', 'Private Meeting'],
  ['vip', 'VIP Room'], ['interview', 'Interview Room'], ['panel', 'Panel Discussion'],
  ['ama', 'AMA Session'], ['custom', 'Custom Type'],
]
const ACCESS: { key: string, title: string, desc: string }[] = [
  { key: 'anyone', title: 'Anyone', desc: 'Open — any event attendee can join.' },
  { key: 'coded', title: 'Coded',  desc: 'Requires an access code to enter.' },
  { key: 'hidden', title: 'Hidden', desc: 'Unlisted — reachable by direct link / invite only.' },
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

async function uploadPoster(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  try { const r = await upload(file, { collection: 'breakout_room_poster' }); form.poster_url = r.url }
  catch { toast.error('Could not upload poster.') }
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
  <div class="max-w-[1100px]">
    <div class="card">
      <div class="flex items-start justify-between gap-4 flex-wrap mb-3">
        <div>
          <div class="font-bold text-base">Breakout Rooms</div>
          <div class="muted text-[.85rem] mt-0.5">Create collaborative rooms attendees can join during your event.</div>
        </div>
        <button class="btn" @click="openCreate">+ New Room</button>
      </div>

      <!-- Status filter -->
      <div class="flex gap-1.5 mb-4">
        <button
          v-for="f in (['all', 'draft', 'published', 'archived'] as const)" :key="f"
          class="px-3 py-1 rounded-full text-[.8rem] font-medium capitalize transition-colors"
          :class="filter === f ? 'bg-[#6352e7] text-white' : 'bg-[#f1f1f5] text-muted hover:text-ink'"
          @click="filter = f"
        >{{ f }}</button>
      </div>

      <div v-if="loading" class="muted text-center py-12">Loading rooms…</div>

      <template v-else>
        <div v-if="!shown.length" class="text-muted text-[.86rem] border border-dashed border-line rounded-xl p-8 text-center">
          No {{ filter === 'all' ? '' : filter }} rooms yet.
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
        <label class="block mb-1.5">Room Name <span class="text-[#dc2626]">*</span></label>
        <input v-model="form.name" placeholder="e.g. Product Deep-Dive" class="m-0">
      </div>

      <!-- Description -->
      <div class="mb-4">
        <label class="block mb-1.5">Room Description</label>
        <textarea v-model="form.description" rows="3" placeholder="What happens in this room?" class="m-0 w-full"></textarea>
      </div>

      <!-- Purpose + Type -->
      <div class="flex gap-3 mb-4 flex-wrap">
        <div class="flex-1 min-w-[160px]">
          <label class="block mb-1.5">Room Purpose</label>
          <select v-model="form.purpose" class="m-0 w-full">
            <option value="single">Single Session</option>
            <option value="multiple">Multiple Sessions</option>
          </select>
        </div>
        <div class="flex-1 min-w-[160px]">
          <label class="block mb-1.5">Room Type</label>
          <select v-model="form.type" class="m-0 w-full">
            <option v-for="[v, l] in TYPES" :key="v" :value="v">{{ l }}</option>
          </select>
        </div>
      </div>

      <!-- Schedule -->
      <div class="flex gap-3 mb-4 flex-wrap">
        <div class="flex-1 min-w-[160px]">
          <label class="block mb-1.5">Start date &amp; time</label>
          <input v-model="form.starts_at" type="datetime-local" class="m-0 w-full">
        </div>
        <div class="flex-1 min-w-[160px]">
          <label class="block mb-1.5">End date &amp; time</label>
          <input v-model="form.ends_at" type="datetime-local" class="m-0 w-full">
        </div>
      </div>

      <!-- Access -->
      <div class="mb-4">
        <label class="block mb-1.5">Who can join the room</label>
        <div class="flex flex-col gap-2">
          <label
            v-for="a in ACCESS" :key="a.key"
            class="flex items-start gap-2.5 border rounded-xl p-2.5 cursor-pointer transition-colors"
            :class="form.access_type === a.key ? 'border-[#6352e7] bg-[#f7f6ff]' : 'border-line'"
          >
            <input v-model="form.access_type" type="radio" :value="a.key" class="accent-[#6352e7] mt-0.5">
            <span>
              <span class="font-medium text-ink text-[.9rem]">{{ a.title }}</span>
              <span class="block text-[.78rem] text-muted">{{ a.desc }}</span>
            </span>
          </label>
        </div>
        <div v-if="form.access_type === 'coded'" class="mt-2">
          <label class="block mb-1.5">Access Code <span class="text-[#dc2626]">*</span></label>
          <input v-model="form.access_code" placeholder="e.g. EXPO2026" class="m-0 max-w-[240px]">
        </div>
      </div>

      <!-- Capacity -->
      <div class="mb-4">
        <label class="block mb-1.5">Capacity <span class="muted text-[.78rem]">(optional)</span></label>
        <input v-model.number="form.capacity" type="number" min="1" placeholder="Unlimited" class="m-0 max-w-[160px]">
      </div>

      <!-- Poster -->
      <div class="mb-5">
        <label class="block mb-1.5">Session Poster Image</label>
        <div class="rounded-lg overflow-hidden border border-line bg-[#f7f8fa] aspect-[2/1] max-w-[320px] flex items-center justify-center">
          <img v-if="form.poster_url" :src="form.poster_url" class="w-full h-full object-cover">
          <span v-else class="text-muted text-[.84rem]">No poster</span>
        </div>
        <label class="btn ghost mt-2 text-[.8rem] inline-flex cursor-pointer">
          <input type="file" accept="image/*" class="hidden" @change="uploadPoster">
          {{ form.poster_url ? 'Replace poster' : 'Upload poster' }}
        </label>
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
