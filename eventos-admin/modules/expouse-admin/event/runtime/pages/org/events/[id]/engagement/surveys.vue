<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

type Phase = 'upcoming' | 'ongoing' | 'ended'
type QuestionType = 'text' | 'textarea' | 'date' | 'select' | 'multiselect' | 'radio' | 'file'

const QUESTION_TYPES: { key: QuestionType, label: string, icon: string, hasOptions?: boolean }[] = [
  { key: 'text', label: 'Text', icon: 'M4 7h16M4 12h10M4 17h7' },
  { key: 'textarea', label: 'Text Area', icon: 'M4 6h16M4 10h16M4 14h10M4 18h6' },
  { key: 'date', label: 'Date', icon: 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z' },
  { key: 'select', label: 'Dropdown', icon: 'M6 9l6 6 6-6', hasOptions: true },
  { key: 'multiselect', label: 'Checkboxes', icon: 'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11', hasOptions: true },
  { key: 'radio', label: 'Radio', icon: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z', hasOptions: true },
  { key: 'file', label: 'File', icon: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6' },
]
const typeLabel = (k: string) => QUESTION_TYPES.find(t => t.key === k)?.label ?? k
const typeHasOptions = (k: string) => !!QUESTION_TYPES.find(t => t.key === k)?.hasOptions

interface Question {
  label: string
  type: QuestionType
  is_required: boolean
  options?: { label: string, value?: string }[]
}

interface Survey {
  id: number
  title: string
  description: string | null
  phase: Phase
  is_anonymous: boolean
  opens_at: string | null
  closes_at: string | null
  questions_count: number
  questions: Question[]
}

const surveys = ref<Survey[]>([])
const loading = ref(true)
const filter = ref<'all' | Phase>('ongoing')
const search = ref('')

const shown = computed(() => {
  const q = search.value.trim().toLowerCase()
  return surveys.value
    .filter(s => filter.value === 'all' || s.phase === filter.value)
    .filter(s => !q || s.title.toLowerCase().includes(q))
})

async function load() {
  loading.value = true
  try {
    surveys.value = (await api<any>(`/events/${id}/surveys`)).data
  } catch { toast.error('Could not load surveys.') } finally { loading.value = false }
}

// ── Drawer (create / edit) ──────────────────────────────────────────────────
const drawer = reactive({ open: false, mode: 'create' as 'create' | 'edit', surveyId: 0 })
const saving = ref(false)
const error = ref('')

function freshForm() {
  return {
    title: '',
    description: '',
    is_anonymous: false,
    opens_at: '',
    closes_at: '',
    questions: [] as Question[],
  }
}
const form = reactive(freshForm())
const activeQuestion = ref(0)

function toLocalInput(iso: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}
const fromLocalInput = (v: string): string | null => (v ? new Date(v).toISOString() : null)

function openCreate() {
  Object.assign(form, freshForm())
  drawer.mode = 'create'; drawer.surveyId = 0
  activeQuestion.value = 0
  error.value = ''; drawer.open = true
}

function openEdit(s: Survey) {
  Object.assign(form, {
    title: s.title,
    description: s.description || '',
    is_anonymous: s.is_anonymous,
    opens_at: toLocalInput(s.opens_at),
    closes_at: toLocalInput(s.closes_at),
    questions: (s.questions || []).map(q => ({
      label: q.label,
      type: q.type,
      is_required: q.is_required,
      options: (q.options || []).map(o => ({ label: o.label, value: o.value })),
    })),
  })
  drawer.mode = 'edit'; drawer.surveyId = s.id
  activeQuestion.value = 0
  error.value = ''; drawer.open = true
}

function addQuestion(type: QuestionType) {
  form.questions.push({
    label: '',
    type,
    is_required: false,
    options: typeHasOptions(type) ? [{ label: '' }] : undefined,
  })
  activeQuestion.value = form.questions.length - 1
}

function removeQuestion(index: number) {
  form.questions.splice(index, 1)
  if (activeQuestion.value >= form.questions.length) activeQuestion.value = form.questions.length - 1
}

function moveQuestion(index: number, dir: -1 | 1) {
  const to = index + dir
  if (to < 0 || to >= form.questions.length) return
  const [q] = form.questions.splice(index, 1)
  form.questions.splice(to, 0, q)
  activeQuestion.value = to
}

function addOption(question: Question) {
  question.options ??= []
  question.options.push({ label: '' })
}

function removeOption(question: Question, index: number) {
  question.options?.splice(index, 1)
}

async function save() {
  if (!form.title.trim()) { error.value = 'Please enter a survey name.'; return }
  if (form.opens_at && form.closes_at && form.closes_at <= form.opens_at) {
    error.value = 'End time must be after the start time.'; return
  }
  for (const q of form.questions) {
    if (!q.label.trim()) { error.value = 'Please give every question a label.'; return }
    if (typeHasOptions(q.type) && !(q.options || []).some(o => o.label.trim())) {
      error.value = `"${q.label}" needs at least one option.`; return
    }
  }

  error.value = ''; saving.value = true
  const body = {
    title: form.title.trim(),
    description: form.description.trim() || null,
    is_anonymous: form.is_anonymous,
    opens_at: fromLocalInput(form.opens_at),
    closes_at: fromLocalInput(form.closes_at),
    questions: form.questions.map(q => ({
      label: q.label.trim(),
      type: q.type,
      is_required: q.is_required,
      options: typeHasOptions(q.type) ? (q.options || []).filter(o => o.label.trim()) : undefined,
    })),
  }
  try {
    if (drawer.mode === 'create') await api(`/events/${id}/surveys`, { method: 'POST', body })
    else await api(`/surveys/${drawer.surveyId}`, { method: 'PUT', body })
    await load()
    drawer.open = false
    toast.success(drawer.mode === 'create' ? 'Survey created' : 'Survey updated')
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save survey.'
    toast.error(error.value)
  } finally { saving.value = false }
}

async function remove(s: Survey) {
  if (!confirm(`Delete "${s.title}"?`)) return
  try {
    await api(`/surveys/${s.id}`, { method: 'DELETE' })
    surveys.value = surveys.value.filter(x => x.id !== s.id)
    toast.success('Survey deleted')
  } catch (e: any) { toast.error(e?.data?.message || 'Could not delete survey.') }
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
          <div class="font-bold text-base">Surveys</div>
          <div class="muted text-[.85rem] mt-0.5">Collect attendee feedback with custom, question-by-question surveys.</div>
        </div>
        <button class="btn" @click="openCreate">+ NEW SURVEY</button>
      </div>

      <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
        <div class="inline-flex bg-[#f7f7fa] border border-line rounded-xl p-1 gap-1">
          <button
            v-for="f in (['ongoing', 'upcoming', 'ended', 'all'] as const)" :key="f"
            class="px-3.5 py-1.5 rounded-lg text-[.8rem] font-semibold capitalize transition-colors"
            :class="filter === f ? 'bg-[#6352e7] text-white' : 'text-muted hover:text-ink'"
            @click="filter = f"
          >{{ f }}</button>
        </div>

        <SearchInput v-model="search" placeholder="Search surveys…" class="max-w-65" />
      </div>

      <div v-if="loading" class="flex items-center justify-center gap-2.5 py-14 text-muted text-[.88rem]">
        <svg class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
          <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
        </svg>
        Loading surveys…
      </div>

      <template v-else>
        <div v-if="!shown.length" class="text-center py-13 px-5">
          <div class="w-13.5 h-13.5 rounded-[14px] bg-[#f3f0ff] text-[#6352e7] grid place-items-center mx-auto mb-3.5">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          </div>
          <p class="muted m-0 mb-3">{{ search ? 'No surveys match your search.' : `No ${filter === 'all' ? '' : filter} surveys yet.` }}</p>
          <button class="btn" @click="openCreate">+ NEW SURVEY</button>
        </div>

        <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr))">
          <div
            v-for="s in shown" :key="s.id"
            class="border border-line rounded-xl p-3.5 flex flex-col gap-2.5"
          >
            <div class="flex items-start justify-between gap-2">
              <span class="font-semibold text-ink">{{ s.title }}</span>
              <span class="shrink-0 px-2 py-0.5 rounded-full text-[.7rem] font-semibold capitalize" :class="phaseStyle[s.phase]">{{ phaseLabel[s.phase] }}</span>
            </div>
            <p v-if="s.description" class="text-[.8rem] text-muted line-clamp-2 m-0">{{ s.description }}</p>

            <div v-if="s.opens_at || s.closes_at" class="text-[.76rem] text-muted">
              🗓 {{ fmtWhen(s.opens_at) }}<template v-if="s.closes_at"> – {{ fmtWhen(s.closes_at) }}</template>
            </div>
            <div class="text-[.76rem] text-muted flex flex-wrap gap-x-3 gap-y-0.5">
              <span>{{ s.questions_count }} question{{ s.questions_count === 1 ? '' : 's' }}</span>
              <span v-if="s.is_anonymous">· anonymous</span>
            </div>

            <div class="flex items-center gap-1.5 mt-auto pt-2">
              <button class="btn ghost text-[.78rem] px-2.5 py-1" @click="openEdit(s)">Edit</button>
              <button class="text-[#dc2626] text-[.78rem] font-medium px-2 hover:underline ml-auto" @click="remove(s)">Delete</button>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- ── Create / Edit Drawer ─────────────────────────────────────────── -->
    <Drawer
      v-if="drawer.open"
      :title="drawer.mode === 'create' ? 'New Survey' : 'Edit Survey'"
      @close="drawer.open = false"
    >
      <p class="text-muted text-[.8rem] mb-3 mt-0 font-bold uppercase tracking-wide">Survey Details</p>

      <div class="mb-4">
        <AppInput v-model="form.title" label="Survey Name" required placeholder="e.g. Post-Session Feedback" />
      </div>

      <div class="mb-4">
        <AppTextarea v-model="form.description" label="Survey Description" :rows="3" placeholder="What's this survey about?" />
      </div>

      <div class="flex gap-3 mb-4 flex-wrap">
        <div class="flex-1 min-w-40">
          <FormField label="Survey Start">
            <input v-model="form.opens_at" type="datetime-local" class="m-0 w-full">
          </FormField>
        </div>
        <div class="flex-1 min-w-40">
          <FormField label="Survey End">
            <input v-model="form.closes_at" type="datetime-local" class="m-0 w-full">
          </FormField>
        </div>
      </div>

      <div class="mb-5">
        <AppCheckbox v-model="form.is_anonymous" label="Anonymous responses" description="Don't record which attendee submitted each response" />
      </div>

      <!-- Question builder -->
      <p class="text-muted text-[.8rem] mb-3 mt-6 font-bold uppercase tracking-wide border-t border-line pt-5">Questions</p>

      <div class="flex flex-wrap gap-2 mb-4">
        <button
          v-for="t in QUESTION_TYPES" :key="t.key"
          type="button"
          class="flex items-center gap-1.5 border border-line rounded-lg px-2.5 py-1.5 text-[.78rem] font-medium text-muted hover:border-[#c9c4f5] hover:text-brand hover:bg-[#f7f6ff] transition-colors"
          @click="addQuestion(t.key)"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path :d="t.icon"/></svg>
          {{ t.label }}
        </button>
      </div>

      <div v-if="!form.questions.length" class="text-center border border-dashed border-line rounded-xl py-8 mb-4 text-muted text-[.85rem]">
        No questions yet — add one above.
      </div>

      <div v-else class="flex flex-col gap-2.5 mb-4">
        <div
          v-for="(q, i) in form.questions" :key="i"
          class="border rounded-xl p-3 transition-colors"
          :class="activeQuestion === i ? 'border-[#6352e7] bg-[#f7f6ff]' : 'border-line'"
        >
          <div class="flex items-center gap-2 cursor-pointer" @click="activeQuestion = activeQuestion === i ? -1 : i">
            <span class="w-6 h-6 rounded-md grid place-items-center shrink-0 bg-[#f1f1f5] text-muted text-[.7rem] font-bold">{{ i + 1 }}</span>
            <span class="flex-1 font-medium text-ink text-[.88rem] truncate">{{ q.label || 'Untitled question' }}</span>
            <span class="text-[.72rem] text-muted px-1.5 py-0.5 bg-white border border-line rounded shrink-0">{{ typeLabel(q.type) }}</span>
            <button type="button" class="text-muted hover:text-ink bg-transparent border-0 cursor-pointer p-1" title="Move up" @click.stop="moveQuestion(i, -1)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
            </button>
            <button type="button" class="text-muted hover:text-ink bg-transparent border-0 cursor-pointer p-1" title="Move down" @click.stop="moveQuestion(i, 1)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
            </button>
            <button type="button" class="text-[#dc2626] hover:underline bg-transparent border-0 cursor-pointer p-1 text-[.78rem]" @click.stop="removeQuestion(i)">Delete</button>
          </div>

          <div v-if="activeQuestion === i" class="mt-3 pt-3 border-t border-line flex flex-col gap-3">
            <AppInput v-model="q.label" label="Question Label" placeholder="Type the question here" />

            <div v-if="typeHasOptions(q.type)">
              <label class="block mb-1.5">Options</label>
              <div class="flex flex-col gap-2">
                <div v-for="(opt, oi) in q.options" :key="oi" class="flex items-center gap-2">
                  <input v-model="opt.label" type="text" class="m-0 flex-1" :placeholder="`Option ${oi + 1}`">
                  <button type="button" class="text-muted hover:text-[#dc2626] bg-transparent border-0 cursor-pointer p-1 shrink-0" @click="removeOption(q, oi)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                  </button>
                </div>
              </div>
              <button type="button" class="text-brand text-[.8rem] font-semibold mt-2 bg-transparent border-0 cursor-pointer p-0" @click="addOption(q)">+ Add option</button>
            </div>

            <AppCheckbox v-model="q.is_required" label="Mandatory" description="Attendees must answer this question" />
          </div>
        </div>
      </div>

      <p v-if="error" class="error mt-3">{{ error }}</p>

      <div class="modal-actions border-t border-line pt-4 mt-2">
        <button class="btn ghost" @click="drawer.open = false">CANCEL</button>
        <button class="btn" :disabled="saving || !form.title.trim()" @click="save">
          {{ saving ? 'Saving…' : (drawer.mode === 'create' ? 'CREATE' : 'UPDATE') }}
        </button>
      </div>
    </Drawer>
  </div>
</template>
