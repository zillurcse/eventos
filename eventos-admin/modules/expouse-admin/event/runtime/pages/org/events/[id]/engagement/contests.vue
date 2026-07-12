<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const { upload } = useUpload()
const id = route.params.id as string

type ContestType = 'entry' | 'response'
type Phase = 'upcoming' | 'ongoing' | 'ended'

const CONTEST_TYPES: { key: ContestType, title: string, desc: string, icon: string }[] = [
  {
    key: 'entry',
    title: 'Entry Contest',
    desc: 'Attendees participate by posting an entry. Suited for Selfie, Photo/Video or Article Writing contests.',
    icon: 'M3 3h18v18H3z M8.5 8.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z M21 15l-5-5L5 21',
  },
  {
    key: 'response',
    title: 'Response Contest',
    desc: 'Attendees participate by commenting on your post. Suited for Discussions, Suggestions or Caption contests.',
    icon: 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
  },
]

const WINNER_CHOOSERS: { key: string, title: string, desc: string, icon: string }[] = [
  {
    key: 'admin',
    title: 'Admin',
    desc: 'You choose the winner(s) manually.',
    icon: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z M9 12l2 2 4-4',
  },
  {
    key: 'most_likes',
    title: 'Most Likes',
    desc: 'The entries with the most likes win automatically.',
    icon: 'M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.8 1-1a5.5 5.5 0 0 0 0-7.8z',
  },
]

const typeLabel = (k: string) => CONTEST_TYPES.find(t => t.key === k)?.title ?? k

interface Contest {
  id: number
  title: string
  contest_type: ContestType
  phase: Phase
  description: string | null
  description_file_url: string | null
  description_file_name: string | null
  starts_at: string | null
  ends_at: string | null
  banner_url: string | null
  caption: string | null
  character_limit: number
  points_for_entry: number
  points_for_response: number
  allow_photos: boolean
  allow_videos: boolean
  allow_selfie: boolean
  winner_chooser: string
  winner_number: number
  winning_points: number
  equal_points_distribution: boolean
  attach_mandatory: boolean
  allow_multiple_entries: boolean
  allow_moderate_entries: boolean
  attendees_can_see_others_entries: boolean
  attendees_can_see_other_comments: boolean
}

const contests = ref<Contest[]>([])
const loading = ref(true)
const filter = ref<'all' | Phase>('ongoing')
const search = ref('')

const shown = computed(() => {
  const q = search.value.trim().toLowerCase()
  return contests.value
    .filter(c => filter.value === 'all' || c.phase === filter.value)
    .filter(c => !q || c.title.toLowerCase().includes(q))
})

async function load() {
  loading.value = true
  try {
    contests.value = (await api<any>(`/events/${id}/contests`)).data
  } catch { toast.error('Could not load contests.') } finally { loading.value = false }
}

// ── Drawer (create / edit) ──────────────────────────────────────────────────
const drawer = reactive({ open: false, step: 'type' as 'type' | 'form', mode: 'create' as 'create' | 'edit', contestId: 0 })
const saving = ref(false)
const error = ref('')
const uploadingFile = ref(false)

function freshForm() {
  return {
    title: '',
    contest_type: 'entry' as ContestType,
    description: '',
    description_file_url: null as string | null,
    description_file_name: '',
    starts_at: '', ends_at: '',
    banner_url: null as string | null,
    caption: '',
    character_limit: 200,
    points_for_entry: 10,
    points_for_response: 10,
    allow_photos: true,
    allow_videos: false,
    allow_selfie: false,
    winner_chooser: 'admin',
    winner_number: 3,
    winning_points: 0,
    equal_points_distribution: false,
    attach_mandatory: false,
    allow_multiple_entries: false,
    allow_moderate_entries: false,
    attendees_can_see_others_entries: false,
    attendees_can_see_other_comments: false,
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
  drawer.mode = 'create'; drawer.step = 'type'; drawer.contestId = 0
  error.value = ''; drawer.open = true
}

function pickType(type: ContestType) {
  form.contest_type = type
  drawer.step = 'form'
}

function openEdit(c: Contest) {
  Object.assign(form, {
    title: c.title,
    contest_type: c.contest_type,
    description: c.description || '',
    description_file_url: c.description_file_url,
    description_file_name: c.description_file_name || '',
    starts_at: toLocalInput(c.starts_at), ends_at: toLocalInput(c.ends_at),
    banner_url: c.banner_url,
    caption: c.caption || '',
    character_limit: c.character_limit,
    points_for_entry: c.points_for_entry,
    points_for_response: c.points_for_response,
    allow_photos: c.allow_photos,
    allow_videos: c.allow_videos,
    allow_selfie: c.allow_selfie,
    winner_chooser: c.winner_chooser,
    winner_number: c.winner_number,
    winning_points: c.winning_points,
    equal_points_distribution: c.equal_points_distribution,
    attach_mandatory: c.attach_mandatory,
    allow_multiple_entries: c.allow_multiple_entries,
    allow_moderate_entries: c.allow_moderate_entries,
    attendees_can_see_others_entries: c.attendees_can_see_others_entries,
    attendees_can_see_other_comments: c.attendees_can_see_other_comments,
  })
  drawer.mode = 'edit'; drawer.step = 'form'; drawer.contestId = c.id
  error.value = ''; drawer.open = true
}

async function uploadDescriptionFile(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  uploadingFile.value = true
  try {
    const r = await upload(file, { collection: 'document' })
    form.description_file_url = r.url
    form.description_file_name = file.name
  } catch { toast.error('Could not upload the file.') } finally { uploadingFile.value = false }
}

function clearDescriptionFile() {
  form.description_file_url = null
  form.description_file_name = ''
}

async function save() {
  if (!form.title.trim()) { error.value = 'Please enter a contest name.'; return }
  if (!form.starts_at || !form.ends_at) { error.value = 'Please set the contest start and end time.'; return }
  if (form.ends_at <= form.starts_at) { error.value = 'End time must be after the start time.'; return }

  error.value = ''; saving.value = true
  const body = {
    title: form.title.trim(),
    contest_type: form.contest_type,
    description: form.description.trim() || null,
    description_file_url: form.description_file_url,
    description_file_name: form.description_file_name || null,
    starts_at: fromLocalInput(form.starts_at),
    ends_at: fromLocalInput(form.ends_at),
    banner_url: form.banner_url,
    caption: form.caption.trim() || null,
    character_limit: form.character_limit,
    points_for_entry: form.points_for_entry,
    points_for_response: form.points_for_response,
    allow_photos: form.allow_photos,
    allow_videos: form.allow_videos,
    allow_selfie: form.allow_selfie,
    winner_chooser: form.winner_chooser,
    winner_number: form.winner_number,
    winning_points: form.winning_points,
    equal_points_distribution: form.equal_points_distribution,
    attach_mandatory: form.attach_mandatory,
    allow_multiple_entries: form.allow_multiple_entries,
    allow_moderate_entries: form.allow_moderate_entries,
    attendees_can_see_others_entries: form.attendees_can_see_others_entries,
    attendees_can_see_other_comments: form.attendees_can_see_other_comments,
  }
  try {
    if (drawer.mode === 'create') await api(`/events/${id}/contests`, { method: 'POST', body })
    else await api(`/contests/${drawer.contestId}`, { method: 'PUT', body })
    await load()
    drawer.open = false
    toast.success(drawer.mode === 'create' ? 'Contest created' : 'Contest updated')
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save contest.'
    toast.error(error.value)
  } finally { saving.value = false }
}

async function remove(c: Contest) {
  if (!confirm(`Delete "${c.title}"?`)) return
  try {
    await api(`/contests/${c.id}`, { method: 'DELETE' })
    contests.value = contests.value.filter(x => x.id !== c.id)
    toast.success('Contest deleted')
  } catch (e: any) { toast.error(e?.data?.message || 'Could not delete contest.') }
}

const phaseStyle: Record<Phase, string> = {
  upcoming: 'bg-blue-50 text-blue-700',
  ongoing: 'bg-green-50 text-green-700',
  ended: 'bg-gray-100 text-gray-600',
}
const phaseLabel: Record<Phase, string> = { upcoming: 'Upcoming', ongoing: 'Ongoing', ended: 'Ended' }

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
          <div class="font-bold text-base">Contests</div>
          <div class="muted text-[.85rem] mt-0.5">Run Entry or Response contests to engage attendees during your event.</div>
        </div>
        <button class="btn" @click="openCreate">+ NEW CONTEST</button>
      </div>

      <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
        <!-- Phase filter -->
        <div class="inline-flex bg-[#f7f7fa] border border-line rounded-xl p-1 gap-1">
          <button
            v-for="f in (['ongoing', 'upcoming', 'ended', 'all'] as const)" :key="f"
            class="px-3.5 py-1.5 rounded-lg text-[.8rem] font-semibold capitalize transition-colors"
            :class="filter === f ? 'bg-[#6352e7] text-white' : 'text-muted hover:text-ink'"
            @click="filter = f"
          >{{ f }}</button>
        </div>

        <SearchInput v-model="search" placeholder="Search contests…" class="max-w-65" />
      </div>

      <div v-if="loading" class="flex items-center justify-center gap-2.5 py-14 text-muted text-[.88rem]">
        <svg class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
          <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
        </svg>
        Loading contests…
      </div>

      <template v-else>
        <div v-if="!shown.length" class="text-center py-13 px-5">
          <div class="w-13.5 h-13.5 rounded-[14px] bg-[#f3f0ff] text-[#6352e7] grid place-items-center mx-auto mb-3.5">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17v4M17 3H7a2 2 0 0 0-2 2v4a7 7 0 0 0 14 0V5a2 2 0 0 0-2-2z"/><path d="M5 9H3a2 2 0 0 1-2-2V6a1 1 0 0 1 1-1h3M19 9h2a2 2 0 0 0 2-2V6a1 1 0 0 0-1-1h-3"/></svg>
          </div>
          <p class="muted m-0 mb-3">{{ search ? 'No contests match your search.' : `No ${filter === 'all' ? '' : filter} contests yet.` }}</p>
          <button class="btn" @click="openCreate">+ NEW CONTEST</button>
        </div>

        <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr))">
          <div
            v-for="c in shown" :key="c.id"
            class="border border-line rounded-xl overflow-hidden flex flex-col"
          >
            <div class="h-28 bg-[#f1f1f5] relative shrink-0">
              <img v-if="c.banner_url" :src="c.banner_url" class="w-full h-full object-cover" :alt="c.title">
              <div v-else class="w-full h-full flex items-center justify-center text-muted">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-7 h-7"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
              </div>
              <span class="absolute top-2 right-2 px-2 py-0.5 rounded-full text-[.7rem] font-semibold capitalize" :class="phaseStyle[c.phase]">{{ phaseLabel[c.phase] }}</span>
            </div>

            <div class="p-3 flex flex-col gap-2 flex-1">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-semibold text-ink truncate">{{ c.title }}</span>
                <span class="px-1.5 py-0.5 rounded text-[.68rem] bg-[#eef0ff] text-[#6352e7] font-medium">{{ typeLabel(c.contest_type) }}</span>
              </div>
              <p v-if="c.description" class="text-[.8rem] text-muted line-clamp-2">{{ c.description }}</p>

              <div v-if="c.starts_at" class="text-[.76rem] text-muted">
                🗓 {{ fmtWhen(c.starts_at) }}<template v-if="c.ends_at"> – {{ fmtWhen(c.ends_at) }}</template>
              </div>
              <div class="text-[.76rem] text-muted flex flex-wrap gap-x-3 gap-y-0.5">
                <span>{{ c.winner_number }} winner{{ c.winner_number === 1 ? '' : 's' }}</span>
                <span>· chosen by {{ c.winner_chooser === 'admin' ? 'admin' : 'most likes' }}</span>
              </div>

              <div class="flex items-center gap-1.5 mt-auto pt-2 flex-wrap">
                <button class="btn ghost text-[.78rem] px-2.5 py-1" @click="openEdit(c)">Edit</button>
                <button class="text-[#dc2626] text-[.78rem] font-medium px-2 hover:underline ml-auto" @click="remove(c)">Delete</button>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- ── Create / Edit Drawer ─────────────────────────────────────────── -->
    <Drawer
      v-if="drawer.open"
      :title="drawer.mode === 'create' ? (drawer.step === 'type' ? 'New Contest' : `New ${typeLabel(form.contest_type)}`) : `Edit ${typeLabel(form.contest_type)}`"
      @close="drawer.open = false"
    >
      <!-- Step 1: choose contest type -->
      <template v-if="drawer.step === 'type'">
        <p class="muted text-[.86rem] mt-0 mb-4">Conduct contests like Best Selfie, Caption a Photo and much more.</p>
        <div class="flex flex-col gap-2.5">
          <button
            v-for="t in CONTEST_TYPES" :key="t.key"
            type="button"
            class="flex items-start gap-3 border rounded-xl p-3.5 text-left cursor-pointer transition-colors border-line hover:border-[#c9c4f5] hover:bg-[#f7f6ff]"
            @click="pickType(t.key)"
          >
            <span class="w-9 h-9 rounded-lg grid place-items-center shrink-0 bg-[#f1f1f5] text-muted">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path :d="t.icon"/></svg>
            </span>
            <span>
              <span class="block font-semibold text-ink text-[.92rem]">{{ t.title }}</span>
              <span class="block text-[.8rem] text-muted mt-0.5 leading-snug">{{ t.desc }}</span>
            </span>
          </button>
        </div>
      </template>

      <!-- Step 2: contest form -->
      <template v-else>
        <!-- Contest Details -->
        <p class="text-muted text-[.8rem] mb-3 mt-0 font-bold uppercase tracking-wide">Contest Details</p>

        <div class="mb-4">
          <AppInput v-model="form.title" label="Contest Name" required placeholder="e.g. Best Conference Selfie" />
        </div>

        <div class="mb-4">
          <AppTextarea v-model="form.description" label="Contest Description" :rows="4" placeholder="What's this contest about?" />
        </div>

        <div class="mb-4">
          <FormField label="Description File" hint="PDF, PPT or DOC, up to 20 MB.">
            <div v-if="form.description_file_url" class="flex items-center justify-between gap-2 border border-line rounded-[11px] px-3 py-2.5">
              <a :href="form.description_file_url" target="_blank" class="text-brand text-[.85rem] truncate">
                {{ form.description_file_name || 'View file' }}
              </a>
              <button type="button" class="bg-transparent border-0 cursor-pointer text-[#dc2626] text-[.85rem]" @click="clearDescriptionFile">Remove</button>
            </div>
            <label v-else class="flex items-center justify-center border border-dashed border-[#d7dae1] rounded-[11px] px-3 py-3 cursor-pointer text-muted text-[.85rem] hover:border-brand">
              <span>{{ uploadingFile ? 'Uploading…' : '+ Upload description file' }}</span>
              <input type="file" class="hidden" accept=".pdf,.ppt,.pptx,.doc,.docx" @change="uploadDescriptionFile">
            </label>
          </FormField>
        </div>

        <div class="flex gap-3 mb-4 flex-wrap">
          <div class="flex-1 min-w-40">
            <FormField label="Contest Start" required>
              <input v-model="form.starts_at" type="datetime-local" class="m-0 w-full">
            </FormField>
          </div>
          <div class="flex-1 min-w-40">
            <FormField label="Contest End" required>
              <input v-model="form.ends_at" type="datetime-local" class="m-0 w-full">
            </FormField>
          </div>
        </div>

        <div class="mb-4">
          <FormField label="Banner" hint="Recommended 1036×350.">
            <ImageField
              :model-value="form.banner_url"
              :aspect="1036 / 350"
              :output-width="1036"
              :output-height="350"
              collection="contest_banner"
              :gallery-path="`/events/${id}/gallery`"
              card-width="320px"
              @update:model-value="form.banner_url = (Array.isArray($event) ? $event[0] : $event) || null"
            />
          </FormField>
        </div>

        <div class="mb-1">
          <AppInput v-model="form.caption" label="Banner Caption" placeholder="Optional caption shown under the banner" />
        </div>

        <!-- Post Details -->
        <p class="text-muted text-[.8rem] mb-3 mt-6 font-bold uppercase tracking-wide border-t border-line pt-5">Post Details</p>

        <div class="flex gap-3 mb-4 flex-wrap">
          <div class="flex-1 min-w-35">
            <AppInput v-model.number="form.character_limit" type="number" label="Character Limit" hint="Maximum characters per post" min="1" />
          </div>
          <div class="flex-1 min-w-35">
            <AppInput
              v-if="form.contest_type === 'entry'"
              v-model.number="form.points_for_entry" type="number" label="Points for Entry" min="0"
            />
            <AppInput
              v-else
              v-model.number="form.points_for_response" type="number" label="Points for Response" min="0"
            />
          </div>
        </div>

        <div class="mb-4">
          <FormField label="Supported Attachment">
            <div class="flex flex-col gap-2.5">
              <AppCheckbox v-model="form.allow_photos" label="Photos" />
              <AppCheckbox v-model="form.allow_videos" label="Videos" />
              <AppCheckbox v-model="form.allow_selfie" label="Selfie" />
            </div>
          </FormField>
        </div>

        <div class="mb-4">
          <FormField label="Who will choose the winner(s)">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <label
                v-for="w in WINNER_CHOOSERS" :key="w.key"
                class="relative flex flex-col gap-2 border rounded-xl p-3 cursor-pointer transition-colors"
                :class="form.winner_chooser === w.key ? 'border-[#6352e7] bg-[#f7f6ff]' : 'border-line hover:border-[#c9c4f5]'"
              >
                <input v-model="form.winner_chooser" type="radio" :value="w.key" class="sr-only">
                <div class="flex items-center justify-between">
                  <span
                    class="w-8 h-8 rounded-lg grid place-items-center shrink-0 transition-colors"
                    :class="form.winner_chooser === w.key ? 'bg-[#6352e7] text-white' : 'bg-[#f1f1f5] text-muted'"
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path :d="w.icon"/></svg>
                  </span>
                  <span
                    class="w-4.5 h-4.5 rounded-full border-2 grid place-items-center shrink-0"
                    :class="form.winner_chooser === w.key ? 'bg-[#6352e7] border-[#6352e7]' : 'bg-white border-[#d7dae1]'"
                  >
                    <svg v-if="form.winner_chooser === w.key" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                  </span>
                </div>
                <span class="font-semibold text-ink text-[.88rem]">{{ w.title }}</span>
                <span class="text-[.78rem] text-muted leading-snug">{{ w.desc }}</span>
              </label>
            </div>
          </FormField>
        </div>

        <div class="flex gap-3 mb-4 flex-wrap">
          <div class="flex-1 min-w-35">
            <AppInput v-model.number="form.winner_number" type="number" label="Number of Winners" min="0" />
          </div>
          <div class="flex-1 min-w-35">
            <AppInput v-model.number="form.winning_points" type="number" label="Winning Points" min="0" />
          </div>
        </div>

        <div v-if="form.contest_type === 'response'" class="mb-1">
          <AppCheckbox v-model="form.equal_points_distribution" label="Equal points distribution" description="Split winning points evenly across all winners" />
        </div>

        <!-- Settings -->
        <p class="text-muted text-[.8rem] mb-3 mt-6 font-bold uppercase tracking-wide border-t border-line pt-5">Settings</p>

        <div class="mb-5 flex flex-col gap-3">
          <AppCheckbox v-if="form.contest_type === 'entry'" v-model="form.attach_mandatory" label="Make an attachment mandatory" description="Attendees must include a photo/video/selfie to enter" />
          <AppCheckbox v-model="form.allow_multiple_entries" label="Allow multiple entries" description="Attendees can post more than once" />
          <AppCheckbox v-model="form.allow_moderate_entries" label="Moderate entries" description="Review entries before they go live" />
          <AppCheckbox v-model="form.attendees_can_see_others_entries" label="Attendees see other entries" description="Entries are visible to all attendees" />
          <AppCheckbox v-if="form.contest_type === 'entry'" v-model="form.attendees_can_see_other_comments" label="Attendees see other comments" description="Comments on entries are visible to all attendees" />
        </div>

        <p v-if="error" class="error mt-3">{{ error }}</p>

        <div class="modal-actions border-t border-line pt-4 mt-2">
          <button class="btn ghost" @click="drawer.open = false">CANCEL</button>
          <button class="btn" :disabled="saving || !form.title.trim()" @click="save">
            {{ saving ? 'Saving…' : (drawer.mode === 'create' ? 'CREATE' : 'UPDATE') }}
          </button>
        </div>
      </template>
    </Drawer>
  </div>
</template>
