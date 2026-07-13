<script setup lang="ts">
import { reactive, ref, computed, onMounted } from 'vue'

interface Track { id: number; name: string; color: string }
interface Sponsor { id: string; name: string; logo_url?: string | null }
interface SessionDocument { name: string; url: string }

interface EventSpeaker {
  id: string; name: string; email: string
  designation: string; image_url: string | null
}

interface DraftShape {
  title: string
  description: string
  date: string
  start_time: string
  end_time: string
  track_id: number | ''
  session_place: string
  logo_url: string | null
  icon_url: string | null
  capacity: number | ''
  speaker_ids: string[]
  sponsors: Sponsor[]
  documents: SessionDocument[]
  tags: string[]
  is_featured: boolean
  is_allowed_to_rate: boolean
}

const props = defineProps<{
  eventId: string
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'created', session: any): void
}>()

const api = useApi()

function freshDraft(): DraftShape {
  return {
    title: '', description: '', date: '', start_time: '', end_time: '',
    track_id: '', session_place: '', logo_url: null, icon_url: null, capacity: '',
    speaker_ids: [], sponsors: [], documents: [], tags: [],
    is_featured: false, is_allowed_to_rate: false,
  }
}

const draft   = reactive<DraftShape>(freshDraft())
const saving  = ref(false)
const error   = ref('')
const iconChooserOpen = ref(false)

// ── Data needed by this drawer (tracks / speakers / sponsors / event dates) ────

const event         = ref<any>(null)
const tracks        = ref<Track[]>([])
const eventSpeakers = ref<EventSpeaker[]>([])
const sponsorsList  = ref<Sponsor[]>([])
const trackBusy     = ref(false)

async function loadDrawerData() {
  try {
    const [evtRes, trkRes, spkRes, sponRes] = await Promise.all([
      api<any>(`/events/${props.eventId}`),
      api<any>(`/tracks?event=${props.eventId}`),
      api<any>(`/events/${props.eventId}/speakers`),
      api<any>(`/exhibitors?event=${props.eventId}&type=sponsor`),
    ])
    event.value         = evtRes.data
    tracks.value         = trkRes.data
    eventSpeakers.value  = spkRes.data
    sponsorsList.value   = (sponRes.data || []).map((e: any) => ({ id: e.id, name: e.name, logo_url: e.logo_url ?? null }))
  } catch { /* */ }
}
onMounted(loadDrawerData)

// ── Choose Day options (derived from the event's date range) ───────────────────

const dayOptions = computed<{ value: string, label: string }[]>(() => {
  if (!event.value?.starts_at) return []
  const start = new Date(event.value.starts_at)
  const end   = event.value.ends_at ? new Date(event.value.ends_at) : start
  if (Number.isNaN(start.getTime())) return []

  const out: { value: string, label: string }[] = []
  const cursor = new Date(start.getFullYear(), start.getMonth(), start.getDate())
  const last   = new Date(end.getFullYear(), end.getMonth(), end.getDate())
  let guard = 0
  while (cursor <= last && guard++ < 366) {
    const value = [
      cursor.getFullYear(),
      String(cursor.getMonth() + 1).padStart(2, '0'),
      String(cursor.getDate()).padStart(2, '0'),
    ].join('-')
    out.push({
      value,
      label: cursor.toLocaleDateString([], { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' }),
    })
    cursor.setDate(cursor.getDate() + 1)
  }
  return out
})

// End must be after start when both are set.
const timeError = computed(() =>
  draft.start_time && draft.end_time && draft.end_time <= draft.start_time
    ? 'Schedule End must be after Schedule Start'
    : '',
)

// Day + Schedule Start + Schedule End are required for a valid schedule.
const canSave = computed(() =>
  !!draft.title.trim() && !!draft.date && !!draft.start_time && !!draft.end_time && !timeError.value,
)

function buildDatetime(date: string, time: string): string | null {
  if (!date) return null
  return time ? `${date}T${time}:00` : `${date}T00:00:00`
}

// ── Track inline CRUD ─────────────────────────────────────────────────────────

async function createTrack(name: string) {
  trackBusy.value = true
  try {
    const res = await api<any>('/tracks', { method: 'POST', body: { event: props.eventId, name } })
    tracks.value.push(res.data)
    draft.track_id = res.data.id
  } catch { /* */ } finally {
    trackBusy.value = false
  }
}

async function renameTrack({ id, name }: { id: number, name: string }) {
  try {
    const res = await api<any>(`/tracks/${id}`, { method: 'PUT', body: { name } })
    const idx = tracks.value.findIndex(t => t.id === id)
    if (idx >= 0) tracks.value[idx] = res.data
  } catch { /* */ }
}

async function deleteTrack(trackId: number) {
  const track = tracks.value.find(t => t.id === trackId)
  if (!confirm(`Delete track "${track?.name}"?`)) return
  try {
    await api(`/tracks/${trackId}`, { method: 'DELETE' })
    tracks.value = tracks.value.filter(t => t.id !== trackId)
    if (draft.track_id === trackId) draft.track_id = ''
  } catch { /* */ }
}

// ── Tags ──────────────────────────────────────────────────────────────────────

const tagInput = ref('')

function addTag() {
  const val = tagInput.value.replace(/,\s*$/, '').trim()
  if (val && !draft.tags.includes(val)) draft.tags.push(val)
  tagInput.value = ''
}
function removeTag(i: number) { draft.tags.splice(i, 1) }
function onTagKey(e: KeyboardEvent) {
  if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addTag() }
}

// ── Speakers picker ───────────────────────────────────────────────────────────

const speakerModal = ref(false)
const selectedSpeakers = computed(() => eventSpeakers.value.filter(s => draft.speaker_ids.includes(s.id)))

function toggleSpeakerDraft(spkId: string) {
  const idx = draft.speaker_ids.indexOf(spkId)
  if (idx >= 0) draft.speaker_ids.splice(idx, 1)
  else draft.speaker_ids.push(spkId)
}

function initials(name: string | null | undefined): string {
  if (!name) return '?'
  return name.split(' ').slice(0, 2).map(w => w[0] ?? '').join('').toUpperCase()
}

// ── Sponsors picker ───────────────────────────────────────────────────────────

const sponsorModal = ref(false)

function toggleSponsor(s: Sponsor) {
  const i = draft.sponsors.findIndex(x => x.id === s.id)
  if (i >= 0) draft.sponsors.splice(i, 1)
  else draft.sponsors.push({ id: s.id, name: s.name, logo_url: s.logo_url ?? null })
}
function removeSponsor(sid: string) {
  draft.sponsors = draft.sponsors.filter(x => x.id !== sid)
}

// ── Save ──────────────────────────────────────────────────────────────────────

async function save() {
  if (timeError.value) { error.value = timeError.value; return }
  if (!draft.date || !draft.start_time || !draft.end_time) {
    error.value = 'Choose a day and set both Schedule Start and Schedule End.'
    return
  }
  error.value = ''
  saving.value = true
  try {
    const body: any = {
      event:              props.eventId,
      title:              draft.title,
      description:        draft.description || null,
      starts_at:          buildDatetime(draft.date, draft.start_time),
      ends_at:            buildDatetime(draft.date, draft.end_time),
      track_id:           draft.track_id    || null,
      session_place:      draft.session_place || null,
      logo_url:           draft.logo_url    || null,
      icon_url:           draft.icon_url    || null,
      capacity:           draft.capacity    || null,
      tags:               draft.tags,
      sponsors:           draft.sponsors,
      documents:          draft.documents,
      is_featured:        draft.is_featured,
      is_allowed_to_rate: draft.is_allowed_to_rate,
    }

    const res = await api<any>('/sessions', { method: 'POST', body })
    const newSession: any = res.data

    // Link selected speakers (relational — session_speaker pivot).
    for (const spkId of draft.speaker_ids) {
      const sp = eventSpeakers.value.find(s => s.id === spkId)
      if (!sp) continue
      try {
        const parts = sp.name.split(' ')
        await api(`/sessions/${newSession.id}/speakers`, {
          method: 'POST',
          body: {
            email:      sp.email,
            first_name: parts[0] ?? '',
            last_name:  parts.slice(1).join(' ') || '',
            role:       'speaker',
          },
        })
      } catch { /* */ }
    }

    // Reload session to include linked speakers
    if (draft.speaker_ids.length) {
      try {
        const fresh = await api<any>(`/sessions/${newSession.id}`)
        emit('created', fresh.data)
      } catch {
        emit('created', newSession)
      }
    } else {
      emit('created', newSession)
    }
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save session.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Drawer title="Schedule Session" @close="emit('close')">

    <!-- Choose Day -->
    <div class="mb-4">
      <AppSelect
        v-model="draft.date"
        label="Choose Day"
        required
        placeholder="Select"
        :options="dayOptions"
        :hint="!dayOptions.length ? 'Set the event start & end dates to choose a day.' : undefined"
      />
    </div>

    <!-- Schedule Start / End -->
    <div class="flex gap-3 mb-1">
      <div class="flex-1">
        <AppInput v-model="draft.start_time" label="Schedule Start" type="time" required />
      </div>
      <div class="flex-1">
        <AppInput v-model="draft.end_time" label="Schedule End" type="time" required />
      </div>
    </div>
    <p v-if="timeError" class="error mb-3">{{ timeError }}</p>
    <div v-else class="mb-3" />

    <!-- Title -->
    <AppInput v-model="draft.title" label="Title" required placeholder="Enter Title" />

    <!-- Session Place -->
    <div class="mt-4">
      <AppInput v-model="draft.session_place" label="Session Place" placeholder="Enter Session Place" />
    </div>

    <!-- Track (inline CRUD) -->
    <div class="mt-4 mb-4">
      <label class="block mb-1.5">Select Track</label>
      <SessionTrackSelect
        v-model="draft.track_id"
        :tracks="tracks"
        :busy="trackBusy"
        @create="createTrack"
        @rename="renameTrack"
        @remove="deleteTrack"
      />
    </div>

    <!-- Description (rich text) -->
    <div class="mb-4">
      <label class="block mb-1.5">Description</label>
      <SessionDescriptionEditor v-model="draft.description" />
    </div>

    <!-- Logo + Icon -->
    <div class="flex gap-6 mb-4">
      <div>
        <label class="block mb-1.5">Logo</label>
        <ImageField
          :model-value="draft.logo_url"
          :aspect="1"
          :output-width="400"
          :output-height="400"
          collection="session_logo"
          card-width="120px"
          :gallery-path="`/events/${eventId}/gallery`"
          @update:model-value="draft.logo_url = (Array.isArray($event) ? $event[0] : $event) || null"
        />
      </div>
      <div>
        <label class="block mb-1.5">Icon</label>
        <button
          type="button"
          class="w-[120px] h-[120px] rounded-xl border border-dashed border-[#d7dae1] flex items-center justify-center bg-[#fafbfc] cursor-pointer hover:border-brand"
          @click="iconChooserOpen = true"
        >
          <AppIcon v-if="draft.icon_url" :name="draft.icon_url" class="w-10 h-10 text-ink" />
          <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="w-9 h-9 text-muted"><rect x="3" y="5" width="18" height="14" rx="2"/><rect x="7" y="9" width="10" height="6" rx="1"/></svg>
        </button>
      </div>
    </div>

    <!-- Speakers -->
    <div class="mb-4">
      <label class="block mb-2">Speakers</label>
      <div class="flex flex-wrap gap-2 items-center">
        <div
          v-for="sp in selectedSpeakers"
          :key="sp.id"
          class="relative w-16 h-16 rounded-xl overflow-hidden bg-brand-soft text-brand flex items-center justify-center text-[.85rem] font-bold border border-line"
          :title="sp.name"
        >
          <img v-if="sp.image_url" :src="sp.image_url" :alt="sp.name" class="w-full h-full object-cover">
          <span v-else>{{ initials(sp.name) }}</span>
          <button
            class="absolute -top-1 -right-1 w-4 h-4 bg-[#dc2626] text-white rounded-full text-[.7rem] leading-none flex items-center justify-center"
            @click.stop="toggleSpeakerDraft(sp.id)"
          >×</button>
        </div>
        <button
          type="button"
          class="w-16 h-16 border-2 border-dashed border-line rounded-xl flex items-center justify-center text-2xl text-muted hover:border-brand hover:text-brand transition-colors"
          @click.stop="speakerModal = true"
        >+</button>
      </div>
    </div>

    <!-- Session Sponsors -->
    <div class="mb-4">
      <label class="block mb-2">Session Sponsors</label>
      <div class="flex flex-wrap gap-2 items-center">
        <div
          v-for="sp in draft.sponsors"
          :key="sp.id"
          class="relative w-16 h-16 rounded-xl overflow-hidden bg-[#f1f1f5] text-muted flex items-center justify-center text-[.8rem] font-bold border border-line p-1 text-center"
          :title="sp.name"
        >
          <img v-if="sp.logo_url" :src="sp.logo_url" :alt="sp.name" class="w-full h-full object-contain">
          <span v-else class="leading-tight line-clamp-2">{{ sp.name }}</span>
          <button
            class="absolute -top-1 -right-1 w-4 h-4 bg-[#dc2626] text-white rounded-full text-[.7rem] leading-none flex items-center justify-center"
            @click.stop="removeSponsor(sp.id)"
          >×</button>
        </div>
        <button
          type="button"
          class="w-16 h-16 border-2 border-dashed border-line rounded-xl flex items-center justify-center text-2xl text-muted hover:border-brand hover:text-brand transition-colors"
          @click.stop="sponsorModal = true"
        >+</button>
      </div>
    </div>

    <!-- Documents -->
    <div class="mb-4">
      <label class="block mb-1.5">Documents</label>
      <SessionDocumentUploader v-model="draft.documents" @error="error = $event" />
    </div>

    <!-- Custom Tags -->
    <div class="mb-4">
      <label class="block mb-1.5">Custom Tags</label>
      <div v-if="draft.tags.length" class="flex flex-wrap gap-1.5 mb-2">
        <span
          v-for="(tag, i) in draft.tags" :key="i"
          class="flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-brand-soft text-brand text-[.8rem] font-medium"
        >
          {{ tag }}
          <button
            class="bg-transparent border-0 p-0 cursor-pointer text-brand leading-none text-[.9rem]"
            @click="removeTag(i)"
          >×</button>
        </span>
      </div>
      <AppInput
        v-model="tagInput"
        placeholder="Add tag & press enter"
        @keydown="onTagKey"
        @blur="addTag"
      />
    </div>

    <!-- Checkboxes -->
    <div class="mb-5 flex flex-col gap-3">
      <AppCheckbox v-model="draft.is_allowed_to_rate" label="Attendees can rate this session" />
      <AppCheckbox v-model="draft.is_featured" label="Featured schedule" />
    </div>

    <p v-if="error" class="error mt-3">{{ error }}</p>

    <div class="modal-actions border-t border-line pt-4 mt-5">
      <button class="btn ghost" @click="emit('close')">Cancel</button>
      <button class="btn" :disabled="!canSave || saving" @click="save">
        {{ saving ? 'Saving…' : 'ADD' }}
      </button>
    </div>

    <SessionSpeakerPicker
      v-if="speakerModal"
      :speakers="eventSpeakers"
      :selected-ids="draft.speaker_ids"
      @close="speakerModal = false"
      @toggle="toggleSpeakerDraft"
    />

    <SessionSponsorPicker
      v-if="sponsorModal"
      :sponsors="sponsorsList"
      :selected="draft.sponsors"
      @close="sponsorModal = false"
      @toggle="toggleSponsor"
    />

    <IconChooserModal
      v-if="iconChooserOpen"
      :model-value="draft.icon_url"
      title="Choose Session Icon"
      @select="draft.icon_url = $event"
      @close="iconChooserOpen = false"
    />
  </Drawer>
</template>
