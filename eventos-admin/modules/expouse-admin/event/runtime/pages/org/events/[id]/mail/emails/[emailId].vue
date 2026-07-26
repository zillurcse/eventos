<script setup lang="ts">
/**
 * Mail journey wizard — Basic Details → From → Template.
 * Edits one entry in event settings `mail_emails`.
 */
import type { MailEmail } from '../../../../../../types/mailEmail'

definePageMeta({ middleware: 'organizer', layout: 'event' })

interface TemplateDto {
  id: string
  name: string
  subject?: string | null
  status?: string
  updated_at?: string | null
}

const STEPS = [
  { key: 'basic', label: 'Basic Details' },
  { key: 'from', label: 'From' },
  { key: 'template', label: 'Template' },
] as const

type StepKey = typeof STEPS[number]['key']

const route = useRoute()
const api = useApi()
const eventId = route.params.id as string
const emailId = route.params.emailId as string

const listRoute = `/org/events/${eventId}/mail/emails`
const step = ref<StepKey>('basic')
const loading = ref(true)
const saving = ref(false)
const saved = ref(false)
const error = ref('')
const emails = ref<MailEmail[]>([])
const templates = ref<TemplateDto[]>([])

const draft = reactive<MailEmail>({
  id: emailId,
  name: 'Invite Email',
  description: 'Send an invitation email to the people when they are added to the people section.',
  mode: 'automated',
  subject: 'Invite Email for Event',
  event_state: 'Pre-Event',
  sent_to: 'Attendee',
  type: 'One time',
  active: true,
  status: 'active',
  date_label: '',
  from_name: '',
  from_email: '',
  cc: '',
  bcc: '',
  template_id: null,
})

const stepIndex = computed(() => STEPS.findIndex(s => s.key === step.value))
const isLast = computed(() => step.value === 'template')
const subjectChars = computed(() => draft.subject.length)
const fromNameChars = computed(() => draft.from_name.length)
const fromEmailChars = computed(() => draft.from_email.length)

const selectedTemplate = computed(() =>
  templates.value.find(t => t.id === draft.template_id) || null,
)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [settings, tpls] = await Promise.all([
      api<{ data: { mail_emails?: MailEmail[], sender?: any } }>(`/events/${eventId}/settings`),
      api<{ data: TemplateDto[] }>(`/email-templates?event=${eventId}`).catch(() => ({ data: [] as TemplateDto[] })),
    ])
    emails.value = settings.data.mail_emails || []
    templates.value = tpls.data || []

    const found = emails.value.find(e => e.id === emailId)
    if (found) {
      Object.assign(draft, JSON.parse(JSON.stringify(found)))
    } else if (emailId === 'new') {
      const snd = settings.data.sender || {}
      draft.id = `e-${Date.now()}`
      draft.from_name = snd.sender_name || ''
      draft.from_email = snd.from || ''
      draft.cc = snd.cc || ''
      draft.bcc = snd.bcc || ''
    } else {
      error.value = 'That email journey no longer exists.'
    }

    // Pre-select first template if none chosen yet.
    if (!draft.template_id && templates.value.length) {
      draft.template_id = templates.value[0].id
    }
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not load this email.'
  } finally {
    loading.value = false
  }
}

async function persist() {
  saving.value = true
  error.value = ''
  try {
    const clean: MailEmail = JSON.parse(JSON.stringify(draft))
    clean.status = clean.active ? (clean.status === 'draft' ? 'draft' : 'active') : clean.status
    const list = [...emails.value]
    const i = list.findIndex(e => e.id === clean.id)
    if (i >= 0) list[i] = clean
    else list.push(clean)
    emails.value = list
    await api(`/events/${eventId}/settings`, {
      method: 'PUT',
      body: { mail_emails: list },
    })
    saved.value = true
    setTimeout(() => (saved.value = false), 1500)
    if (route.params.emailId === 'new') {
      await navigateTo(`/org/events/${eventId}/mail/emails/${clean.id}`, { replace: true })
    }
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save.'
  } finally {
    saving.value = false
  }
}

async function toggleActive() {
  draft.active = !draft.active
  if (draft.active && draft.status === 'draft') draft.status = 'active'
  await persist()
}

function goStep(key: StepKey) {
  step.value = key
}

async function next() {
  if (step.value === 'basic') {
    step.value = 'from'
    return
  }
  if (step.value === 'from') {
    step.value = 'template'
    return
  }
  await persist()
  await navigateTo(listRoute)
}

function cancel() {
  return navigateTo(listRoute)
}

function createTemplate() {
  return navigateTo(`/org/events/${eventId}/mail/email-builder/new`)
}

function editTemplate(id: string) {
  return navigateTo(`/org/events/${eventId}/mail/email-builder/${id}`)
}

async function previewTemplate(id: string) {
  try {
    const res = await api<{ html: string }>(`/email-templates/${id}/preview`, { method: 'POST', body: {} })
    const w = window.open('', '_blank')
    if (w) {
      w.document.write(res.html || '<p>No preview</p>')
      w.document.close()
    }
  } catch {
    /* ignore */
  }
}

onMounted(load)
</script>

<template>
  <div>
    <!-- Breadcrumb -->
    <nav class="flex items-center gap-1.5 text-[.82rem] mb-4">
      <NuxtLink :to="listRoute" class="text-muted no-underline hover:text-brand">Emails</NuxtLink>
      <span class="text-faint">›</span>
      <span class="text-ink font-semibold truncate">{{ draft.name || 'Email' }}</span>
    </nav>

    <div v-if="loading" class="card muted text-center py-10">Loading…</div>
    <div v-else-if="error && !draft.name" class="card text-center py-10">
      <p class="error m-0 mb-4">{{ error }}</p>
      <button class="btn ghost" @click="cancel">Back to Emails</button>
    </div>

    <template v-else>
      <!-- Header card -->
      <div class="card">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <h2 class="section-title m-0">{{ draft.name }}</h2>
            <p class="muted text-[.86rem] mt-1 mb-3 max-w-2xl">
              {{ draft.description || 'Configure this email journey.' }}
              <span v-if="saved" class="badge active ml-2">saved ✓</span>
            </p>
            <div class="flex items-center gap-2 flex-wrap">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[.72rem] font-semibold bg-[#dbeafe] text-[#1d4ed8]">
                {{ draft.mode === 'manual' ? 'Manual' : 'Automated' }}
              </span>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[.72rem] font-semibold bg-[#f1f3f7] text-[#475569]">
                {{ draft.event_state || 'Pre-Event' }}
              </span>
            </div>
          </div>
          <div class="flex items-center gap-2.5 shrink-0">
            <span
              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[.72rem] font-semibold"
              :class="draft.active ? 'bg-[#dcfce7] text-[#15803d]' : 'bg-[#f1f3f7] text-[#64748b]'"
            >{{ draft.active ? 'Active' : 'Inactive' }}</span>
            <button
              type="button"
              class="relative w-11 h-6 rounded-full shrink-0 border-0 cursor-pointer transition-colors duration-150 p-0"
              :class="draft.active ? 'bg-brand' : 'bg-[#cdd2dc]'"
              :aria-label="draft.active ? 'Deactivate' : 'Activate'"
              :disabled="saving"
              @click="toggleActive"
            >
              <i
                class="absolute top-[3px] left-[3px] w-[18px] h-[18px] rounded-full bg-white transition-transform duration-150 shadow-sm block"
                :class="draft.active ? 'translate-x-[20px]' : 'translate-x-0'"
              />
            </button>
          </div>
        </div>
      </div>

      <!-- Journey card -->
      <div class="card">
        <!-- Stepper -->
        <div class="flex items-center justify-center gap-0 mb-8 px-2">
          <template v-for="(s, i) in STEPS" :key="s.key">
            <button
              type="button"
              class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl border bg-transparent cursor-pointer transition-colors"
              :class="step === s.key
                ? 'border-brand text-brand bg-[#f5f3ff]'
                : 'border-transparent text-muted hover:text-ink'"
              @click="goStep(s.key)"
            >
              <span
                class="w-7 h-7 rounded-full grid place-items-center text-[.82rem] font-bold shrink-0"
                :class="step === s.key
                  ? 'bg-brand text-white'
                  : i < stepIndex
                    ? 'bg-brand text-white'
                    : 'bg-[#e8eaf0] text-[#64748b]'"
              >{{ i + 1 }}</span>
              <span class="text-[.9rem] font-semibold whitespace-nowrap">{{ s.label }}</span>
            </button>
            <div
              v-if="i < STEPS.length - 1"
              class="flex-1 max-w-24 h-0 border-t border-dashed mx-1"
              :class="i < stepIndex ? 'border-brand' : 'border-[#c9cdd6]'"
            />
          </template>
        </div>

        <!-- Step 1: Basic Details -->
        <div v-if="step === 'basic'">
          <h3 class="text-[1.05rem] font-bold text-ink m-0 mb-3">Basic Details</h3>
          <div class="flex flex-col gap-1.5 mb-5 text-[.88rem]">
            <div><span class="text-muted">Subject :</span> <span class="text-ink font-medium">{{ draft.subject || '—' }}</span></div>
            <div class="flex items-center gap-1.5">
              <span class="text-muted">Event State :</span>
              <span class="text-ink font-medium">{{ draft.event_state || 'Pre-event' }}</span>
              <span class="w-4 h-4 rounded-full border border-[#c9cdd6] text-[.65rem] text-muted grid place-items-center" title="When this email fires relative to the event timeline.">i</span>
            </div>
          </div>

          <label class="block text-[.88rem] font-semibold text-ink mb-1.5">Email Subject</label>
          <input v-model="draft.subject" class="m-0 max-w-xl" placeholder="Invite Email for Event" maxlength="300">
          <p class="muted text-[.78rem] mt-1.5 mb-0">{{ subjectChars }} Characters</p>
        </div>

        <!-- Step 2: From -->
        <div v-else-if="step === 'from'">
          <h3 class="text-[1.05rem] font-bold text-ink m-0 mb-5">From</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 max-w-3xl">
            <div>
              <label class="block text-[.88rem] font-semibold text-ink mb-1.5">Sender's Name</label>
              <input v-model="draft.from_name" class="m-0" placeholder="Team Company Name" maxlength="120">
              <p class="muted text-[.78rem] mt-1.5 mb-0">{{ fromNameChars }} Characters</p>
            </div>
            <div>
              <label class="block text-[.88rem] font-semibold text-ink mb-1.5">Sender's Email</label>
              <input v-model="draft.from_email" class="m-0" type="email" placeholder="noreply@abc.com" maxlength="180">
              <p class="muted text-[.78rem] mt-1.5 mb-0">{{ fromEmailChars }} Characters</p>
            </div>
            <div>
              <label class="block text-[.88rem] font-semibold text-ink mb-1.5">CC (Optional)</label>
              <input v-model="draft.cc" class="m-0" placeholder="CC Email">
            </div>
            <div>
              <label class="block text-[.88rem] font-semibold text-ink mb-1.5">BCC (Optional)</label>
              <input v-model="draft.bcc" class="m-0" placeholder="BCC Email">
            </div>
          </div>
        </div>

        <!-- Step 3: Template -->
        <div v-else>
          <div class="flex items-center justify-between gap-4 mb-5 flex-wrap">
            <h3 class="text-[1.05rem] font-bold text-ink m-0">Select a template and unleash your creativity here</h3>
            <button type="button" class="inline-flex items-center gap-1.5 text-brand font-semibold text-[.88rem] bg-transparent border-0 cursor-pointer hover:text-brand-dark" @click="createTemplate">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
              Create New Template
            </button>
          </div>

          <div v-if="!templates.length" class="rounded-xl border border-dashed border-line bg-[#fafbfc] text-center py-12 px-4">
            <p class="muted text-[.9rem] m-0 mb-4">No templates yet. Create one to use in this journey.</p>
            <button class="btn" @click="createTemplate">
              <AppIcon name="plus" class="w-[14px] h-[14px]" /> Create New Template
            </button>
          </div>

          <div v-else class="grid grid-cols-[repeat(auto-fill,minmax(220px,1fr))] gap-4">
            <div
              v-for="t in templates"
              :key="t.id"
              class="rounded-xl border overflow-hidden bg-white transition-shadow hover:shadow-md cursor-pointer"
              :class="draft.template_id === t.id ? 'border-brand ring-2 ring-brand/20' : 'border-line'"
              @click="draft.template_id = t.id"
            >
              <MailTemplateThumb :template-id="t.id" />
              <div class="p-3">
                <div class="font-semibold text-[.9rem] text-ink truncate mb-2.5">{{ t.name }}</div>
                <div class="flex gap-2">
                  <button
                    type="button"
                    class="flex-1 py-1.5 rounded-lg text-[.78rem] font-semibold bg-[#f0eefe] text-brand border-0 cursor-pointer hover:bg-[#e8e4fc]"
                    @click.stop="editTemplate(t.id)"
                  >Edit</button>
                  <button
                    type="button"
                    class="flex-1 py-1.5 rounded-lg text-[.78rem] font-semibold bg-[#f0eefe] text-brand border-0 cursor-pointer hover:bg-[#e8e4fc]"
                    @click.stop="previewTemplate(t.id)"
                  >Preview</button>
                </div>
              </div>
            </div>
          </div>

          <p v-if="selectedTemplate" class="muted text-[.8rem] mt-4 mb-0">
            Selected: <span class="text-ink font-medium">{{ selectedTemplate.name }}</span>
          </p>
        </div>

        <p v-if="error" class="error mt-4 mb-0">{{ error }}</p>

        <!-- Footer actions -->
        <div class="flex items-center gap-2.5 mt-8 pt-5 border-t border-line">
          <button class="btn min-w-[100px]" :disabled="saving" @click="next">
            {{ saving ? 'Saving…' : (isLast ? 'Save' : 'Next') }}
          </button>
          <button class="btn min-w-[100px] !bg-[#f0eefe] !text-brand hover:!bg-[#e8e4fc]" :disabled="saving" @click="cancel">Cancel</button>
        </div>
      </div>
    </template>
  </div>
</template>
