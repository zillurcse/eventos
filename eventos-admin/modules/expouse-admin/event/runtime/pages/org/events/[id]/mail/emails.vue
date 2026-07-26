<script setup lang="ts">
import type { MailEmail } from '../../../../../types/mailEmail'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const eventId = route.params.id as string

const tab = ref<'automated' | 'manual'>('automated')
const search = ref('')
const sortBy = ref('name')
const emails = ref<MailEmail[]>([])
const loading = ref(true)
const selected = ref<(string | number)[]>([])
const openMenuId = ref<string | null>(null)
const saving = ref(false)

const testModal = ref<{ row: MailEmail } | null>(null)
const testEmail = ref('')
const testSending = ref(false)
const testError = ref('')
const testOk = ref('')

const DEFAULTS: MailEmail[] = [
  {
    id: 'e-reg',
    name: 'Event Registration',
    description: 'Sent when a person completes registration for the event.',
    mode: 'automated',
    subject: 'Invite Email for Event',
    event_state: 'Pre-Event',
    sent_to: 'Attendee',
    type: 'One time',
    active: true,
    status: 'active',
    date_label: 'May 4th to Jun 1st, 2021',
    from_name: '',
    from_email: '',
    cc: '',
    bcc: '',
    template_id: null,
  },
  {
    id: 'e-invite',
    name: 'Invite Email',
    description: 'Send an invitation email to the people when they are added to the people section.',
    mode: 'automated',
    subject: 'Invite Email for Event',
    event_state: 'During-Event',
    sent_to: 'Speakers',
    type: 'One time',
    active: false,
    status: 'active',
    date_label: 'May 4th to Jun 1st, 2021',
    from_name: '',
    from_email: '',
    cc: '',
    bcc: '',
    template_id: null,
  },
  {
    id: 'e-txn',
    name: 'Successful Transaction',
    description: 'Confirmation after a successful ticket or package purchase.',
    mode: 'automated',
    subject: 'Have you join event community?',
    event_state: 'During-Event',
    sent_to: 'Speakers',
    type: 'One time',
    active: false,
    status: 'draft',
    date_label: 'May 4th to Jun 1st, 2021',
    from_name: '',
    from_email: '',
    cc: '',
    bcc: '',
    template_id: null,
  },
  {
    id: 'e-welcome',
    name: 'Attendee Welcome',
    description: 'A warm welcome blast for newly registered attendees.',
    mode: 'manual',
    subject: "Curious about who you'll meet?",
    event_state: 'Pre-Event',
    sent_to: 'Attendee',
    type: 'One time',
    active: false,
    status: 'active',
    date_label: 'May 4th to Jun 1st, 2021',
    from_name: '',
    from_email: '',
    cc: '',
    bcc: '',
    template_id: null,
  },
  {
    id: 'e-speakers',
    name: 'Speakers Reveal Blast',
    description: 'Announce the speaker lineup to your audience.',
    mode: 'manual',
    subject: 'Have you join event community?',
    event_state: 'Pre-Event',
    sent_to: 'Speakers',
    type: 'Continuous',
    active: false,
    status: 'draft',
    date_label: 'May 4th to Jun 1st, 2021',
    from_name: '',
    from_email: '',
    cc: '',
    bcc: '',
    template_id: null,
  },
]

const filtered = computed(() => {
  let list = emails.value.filter(e => e.mode === tab.value)
  const q = search.value.trim().toLowerCase()
  if (q) {
    list = list.filter(e =>
      e.name.toLowerCase().includes(q)
      || e.subject.toLowerCase().includes(q)
      || e.sent_to.toLowerCase().includes(q))
  }
  const key = sortBy.value
  return [...list].sort((a, b) => String((a as any)[key] ?? '').localeCompare(String((b as any)[key] ?? ''), undefined, { numeric: true }))
})

const automatedColumns = [
  { key: 'emails', label: 'Emails' },
  { key: 'status', label: 'Status' },
  { key: 'sent_to', label: 'Sent to' },
  { key: 'event_state', label: 'State' },
  { key: 'date_label', label: 'Date' },
]

const manualColumns = [
  { key: 'emails', label: 'Emails' },
  { key: 'status', label: 'Status' },
  { key: 'sent_to', label: 'Sent to' },
  { key: 'type', label: 'Type' },
  { key: 'date_label', label: 'Date' },
]

const columns = computed(() => tab.value === 'automated' ? automatedColumns : manualColumns)

async function load() {
  loading.value = true
  try {
    const s = (await api<{ data: { mail_emails?: MailEmail[], sender?: any } }>(`/events/${eventId}/settings`)).data
    const stored = s.mail_emails
    if (Array.isArray(stored) && stored.length) {
      emails.value = stored
    } else {
      // Prefill sender identity into defaults, then persist so the next visit is stable.
      const snd = s.sender || {}
      emails.value = DEFAULTS.map(e => ({
        ...e,
        from_name: snd.sender_name || e.from_name,
        from_email: snd.from || e.from_email,
        cc: snd.cc || e.cc,
        bcc: snd.bcc || e.bcc,
      }))
      await persist()
    }
  } catch {
    emails.value = [...DEFAULTS]
  } finally {
    loading.value = false
  }
}

async function persist() {
  saving.value = true
  try {
    await api(`/events/${eventId}/settings`, {
      method: 'PUT',
      body: { mail_emails: JSON.parse(JSON.stringify(emails.value)) },
    })
  } finally {
    saving.value = false
  }
}

function editRoute(id: string) {
  return `/org/events/${eventId}/mail/emails/${id}`
}

function reportRoute(id: string, hash = '') {
  return `/org/events/${eventId}/mail/emails/report/${id}${hash}`
}

function openTestModal(row: MailEmail) {
  openMenuId.value = null
  if (!row.template_id) {
    alert('Link a template to this email first (Edit Email → Template step), then send a test.')
    return
  }
  testModal.value = { row }
  testEmail.value = ''
  testError.value = ''
  testOk.value = ''
}

function closeTestModal() {
  if (testSending.value) return
  testModal.value = null
}

async function sendTestEmail() {
  const row = testModal.value?.row
  if (!row?.template_id || !testEmail.value.trim()) return
  testSending.value = true
  testError.value = ''
  testOk.value = ''
  try {
    await api(`/email-templates/${row.template_id}/send-test`, {
      method: 'POST',
      body: { to: testEmail.value.trim() },
    })
    testOk.value = `Test email sent to ${testEmail.value.trim()}.`
    setTimeout(() => { testModal.value = null }, 1200)
  } catch (e: any) {
    testError.value = e?.data?.message || 'Could not send test email.'
  } finally {
    testSending.value = false
  }
}

async function toggleActive(row: MailEmail) {
  row.active = !row.active
  if (row.active) row.status = 'active'
  openMenuId.value = null
  await persist()
}

async function removeEmail(row: MailEmail) {
  openMenuId.value = null
  if (!confirm(`Delete "${row.name}"?`)) return
  emails.value = emails.value.filter(e => e.id !== row.id)
  await persist()
}

function openNew() {
  const id = `e-${Date.now()}`
  const draft: MailEmail = {
    id,
    name: 'New Email',
    description: '',
    mode: tab.value,
    subject: '',
    event_state: 'Pre-Event',
    sent_to: 'Attendee',
    type: 'One time',
    active: false,
    status: 'draft',
    date_label: '',
    from_name: '',
    from_email: '',
    cc: '',
    bcc: '',
    template_id: null,
  }
  emails.value = [...emails.value, draft]
  persist().then(() => navigateTo(editRoute(id)))
}

function toggleMenu(id: string) {
  openMenuId.value = openMenuId.value === id ? null : id
}

function onDocClick() {
  openMenuId.value = null
}

watch(tab, () => {
  selected.value = []
  openMenuId.value = null
})

onMounted(() => {
  load()
  if (import.meta.client) document.addEventListener('click', onDocClick)
})
onBeforeUnmount(() => {
  if (import.meta.client) document.removeEventListener('click', onDocClick)
})
</script>

<template>
  <div>
    <div class="mb-4">
      <h2 class="section-title m-0">Emails</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Design, automate, and track attendee communication from one place.</p>
    </div>

    <div class="flex items-center gap-4 flex-wrap mb-4">
      <div class="tabs !mb-0 flex-1 min-w-0">
        <button class="tab" :class="{ active: tab === 'automated' }" @click="tab = 'automated'">Automated</button>
        <button class="tab" :class="{ active: tab === 'manual' }" @click="tab = 'manual'">Manual</button>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <SearchInput v-model="search" placeholder="Search" class="w-[220px]" />
        <select v-model="sortBy" class="w-auto m-0 py-2 px-3 text-[.85rem] h-10">
          <option value="name">Sort by</option>
          <option value="name">Name</option>
          <option value="status">Status</option>
          <option value="sent_to">Sent to</option>
          <option value="date_label">Date</option>
        </select>
        <button class="btn" @click="openNew">
          <AppIcon name="plus" class="w-[14px] h-[14px]" /> New Email
        </button>
      </div>
    </div>

    <div v-if="loading" class="card muted text-center py-10">Loading emails…</div>

    <DataTable
      v-else
      v-model:selected="selected"
      :items="filtered"
      :columns="columns"
      selectable
      row-key="id"
      storage-key="mail-emails"
      empty-text="No emails yet. Click + New Email to get started."
    >
      <template #cell-emails="{ row }">
        <div class="min-w-0">
          <div class="font-semibold text-ink text-[.92rem] truncate">{{ row.name }}</div>
          <div class="muted text-[.78rem] truncate">Subject: {{ row.subject || '—' }}</div>
        </div>
      </template>

      <template #cell-status="{ row }">
        <span
          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[.72rem] font-semibold"
          :class="row.status === 'draft'
            ? 'bg-[#fef3c7] text-[#92400e]'
            : 'bg-[#dcfce7] text-[#15803d]'"
        >{{ row.status === 'draft' ? 'Draft' : 'Active' }}</span>
      </template>

      <template #cell-sent_to="{ row }">
        <span class="text-ink">{{ row.sent_to || '—' }}</span>
      </template>

      <template #cell-event_state="{ row }">
        <span class="text-ink">{{ row.event_state || '—' }}</span>
      </template>

      <template #cell-type="{ row }">
        <span class="text-ink">{{ row.type || '—' }}</span>
      </template>

      <template #cell-date_label="{ row }">
        <span class="text-muted text-[.86rem]">{{ row.date_label || '—' }}</span>
      </template>

      <template #actions="{ row }">
        <div class="inline-flex items-center gap-2.5 justify-end">
          <template v-if="tab === 'automated'">
            <NuxtLink
              v-if="row.active"
              :to="reportRoute(row.id)"
              class="inline-flex items-center gap-1.5 text-[.82rem] font-semibold text-brand no-underline hover:text-brand-dark"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              View Report
            </NuxtLink>
            <NuxtLink
              v-else
              :to="editRoute(row.id)"
              class="inline-flex items-center gap-1.5 text-[.82rem] font-semibold text-brand no-underline hover:text-brand-dark"
            >
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              Edit
            </NuxtLink>

            <button
              type="button"
              class="relative w-10 h-[22px] rounded-full shrink-0 border-0 cursor-pointer transition-colors duration-150 p-0"
              :class="row.active ? 'bg-brand' : 'bg-[#cdd2dc]'"
              :aria-label="row.active ? 'Deactivate' : 'Activate'"
              :disabled="saving"
              @click="toggleActive(row)"
            >
              <i
                class="absolute top-[3px] left-[3px] w-4 h-4 rounded-full bg-white transition-transform duration-150 shadow-sm block"
                :class="row.active ? 'translate-x-[18px]' : 'translate-x-0'"
              />
            </button>
          </template>

          <div class="relative" @click.stop>
            <button
              type="button"
              class="w-8 h-8 rounded-lg grid place-items-center text-muted hover:bg-[#f1f2f6] border-0 bg-transparent cursor-pointer"
              aria-label="Actions"
              @click="toggleMenu(row.id)"
            >
              <svg viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
            </button>
            <div
              v-if="openMenuId === row.id"
              class="absolute right-0 top-full mt-1 bg-white border border-[#1a1a2e] rounded-lg shadow-lg z-30 min-w-[160px] overflow-hidden py-1"
            >
              <NuxtLink
                :to="editRoute(row.id)"
                class="block w-full text-left px-4 py-2.5 text-[.88rem] no-underline text-ink hover:bg-[#f7f8fa]"
                @click="openMenuId = null"
              >Edit Email</NuxtLink>
              <button
                type="button"
                class="block w-full text-left px-4 py-2.5 text-[.88rem] text-ink hover:bg-[#f7f8fa] bg-transparent border-0 cursor-pointer"
                @click="openTestModal(row)"
              >Send Test Email</button>
              <NuxtLink
                :to="reportRoute(row.id, '#analytics')"
                class="block w-full text-left px-4 py-2.5 text-[.88rem] no-underline text-ink hover:bg-[#f7f8fa]"
                @click="openMenuId = null"
              >Analytics</NuxtLink>
              <button class="block w-full text-left px-4 py-2.5 text-[.88rem] text-[#dc2626] hover:bg-[#fef2f2] bg-transparent border-0 cursor-pointer" @click="removeEmail(row)">Delete</button>
            </div>
          </div>
        </div>
      </template>
    </DataTable>

    <!-- Send test email modal -->
    <div
      v-if="testModal"
      class="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"
      @click.self="closeTestModal"
    >
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-5">
        <h3 class="m-0 mb-1 text-[1.05rem] font-bold text-ink">Send test email</h3>
        <p class="muted text-[.84rem] mt-0 mb-4">
          Sends <span class="text-ink font-medium">{{ testModal.row.name }}</span> using its linked template.
        </p>
        <label class="block text-[.85rem] font-semibold text-ink mb-1.5">Recipient</label>
        <input
          v-model="testEmail"
          type="email"
          class="m-0"
          placeholder="you@example.com"
          :disabled="testSending"
          @keyup.enter="sendTestEmail"
        >
        <p v-if="testError" class="error text-[.82rem] mt-2 mb-0">{{ testError }}</p>
        <p v-else-if="testOk" class="text-[.82rem] text-[#15803d] mt-2 mb-0">{{ testOk }}</p>
        <div class="flex items-center gap-2 mt-5 justify-end">
          <button type="button" class="btn ghost" :disabled="testSending" @click="closeTestModal">Cancel</button>
          <button
            type="button"
            class="btn"
            :disabled="testSending || !testEmail.trim()"
            @click="sendTestEmail"
          >{{ testSending ? 'Sending…' : 'Send test' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
