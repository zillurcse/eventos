<script setup lang="ts">
/**
 * Mail journey report + template analytics (from email_sends).
 */
import type { MailEmail } from '../../../../../../../types/mailEmail'

definePageMeta({ middleware: 'organizer', layout: 'event' })

interface AnalyticsReport {
  template: { id: string, name: string, subject?: string | null, status?: string }
  range: { days: number, from: string, to: string }
  totals: {
    total: number
    in_range: number
    sent: number
    delivered: number
    opened: number
    bounced: number
    failed: number
    queued: number
    unique_recipients: number
    today: number
    open_rate: number
    delivery_rate: number
  }
  timeline: { date: string, label: string, sent: number, opened: number }[]
  by_status: { key: string, label: string, count: number, share: number }[]
  by_trigger: { key: string, label: string, count: number, share: number }[]
  recent: {
    id: string
    to_email: string
    subject?: string | null
    status: string
    trigger?: string | null
    sent_at?: string | null
    opened_at?: string | null
  }[]
}

const RANGES = [
  { days: 7, label: 'Last 7 days' },
  { days: 30, label: 'Last 30 days' },
  { days: 90, label: 'Last 90 days' },
]

const STATUS_COLORS: Record<string, string> = {
  queued: '#94a3b8',
  sent: '#6352e7',
  delivered: '#0ea5e9',
  opened: '#15803d',
  bounced: '#b45309',
  failed: '#dc2626',
}

const route = useRoute()
const api = useApi()
const eventId = route.params.id as string
const emailId = route.params.emailId as string

const listRoute = `/org/events/${eventId}/mail/emails`
const editRoute = `/org/events/${eventId}/mail/emails/${emailId}`

const loading = ref(true)
const analyticsLoading = ref(false)
const error = ref('')
const email = ref<MailEmail | null>(null)
const days = ref(30)
const report = ref<AnalyticsReport | null>(null)
const focusAnalytics = computed(() => route.hash === '#analytics')

const peakSent = computed(() => Math.max(1, ...(report.value?.timeline ?? []).map(b => b.sent)))

async function loadEmail() {
  loading.value = true
  error.value = ''
  try {
    const s = (await api<{ data: { mail_emails?: MailEmail[] } }>(`/events/${eventId}/settings`)).data
    email.value = (s.mail_emails || []).find(e => e.id === emailId) || null
    if (!email.value) {
      error.value = 'That email journey no longer exists.'
      return
    }
    if (email.value.template_id) {
      await loadAnalytics()
    }
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not load this report.'
  } finally {
    loading.value = false
  }
}

async function loadAnalytics() {
  if (!email.value?.template_id) return
  analyticsLoading.value = true
  try {
    report.value = (await api<{ data: AnalyticsReport }>(
      `/email-templates/${email.value.template_id}/analytics?days=${days.value}`,
    )).data
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not load analytics.'
    report.value = null
  } finally {
    analyticsLoading.value = false
  }
}

watch(days, () => {
  if (email.value?.template_id) loadAnalytics()
})

function fmtDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function fullDate(iso?: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
}

function statusClass(status: string) {
  const map: Record<string, string> = {
    opened: 'bg-[#dcfce7] text-[#15803d]',
    delivered: 'bg-[#e0f2fe] text-[#0369a1]',
    sent: 'bg-[#f0eefe] text-brand',
    bounced: 'bg-[#fef3c7] text-[#92400e]',
    failed: 'bg-[#fef2f2] text-[#dc2626]',
    queued: 'bg-[#f1f3f7] text-[#64748b]',
  }
  return map[status] || map.queued
}

onMounted(async () => {
  await loadEmail()
  if (focusAnalytics.value && import.meta.client) {
    await nextTick()
    document.getElementById('analytics')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
})
</script>

<template>
  <div>
    <nav class="flex items-center gap-1.5 text-[.82rem] mb-4">
      <NuxtLink :to="listRoute" class="text-muted no-underline hover:text-brand">Emails</NuxtLink>
      <span class="text-faint">›</span>
      <NuxtLink v-if="email" :to="editRoute" class="text-muted no-underline hover:text-brand truncate max-w-[180px]">{{ email.name }}</NuxtLink>
      <span v-if="email" class="text-faint">›</span>
      <span class="text-ink font-semibold">Report</span>
    </nav>

    <div v-if="loading" class="card muted text-center py-10">Loading report…</div>
    <div v-else-if="error && !email" class="card text-center py-10">
      <p class="error m-0 mb-4">{{ error }}</p>
      <NuxtLink :to="listRoute" class="btn ghost no-underline">Back to Emails</NuxtLink>
    </div>

    <template v-else-if="email">
      <!-- Journey summary -->
      <div class="card">
        <div class="flex items-start justify-between gap-4 flex-wrap">
          <div class="min-w-0">
            <h2 class="section-title m-0">{{ email.name }}</h2>
            <p class="muted text-[.86rem] mt-1 mb-3 max-w-2xl">{{ email.description || 'Email journey report and template analytics.' }}</p>
            <div class="flex items-center gap-2 flex-wrap text-[.82rem]">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[.72rem] font-semibold bg-[#dbeafe] text-[#1d4ed8]">
                {{ email.mode === 'manual' ? 'Manual' : 'Automated' }}
              </span>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[.72rem] font-semibold bg-[#f1f3f7] text-[#475569]">
                {{ email.event_state || 'Pre-Event' }}
              </span>
              <span class="text-muted">Subject: <span class="text-ink font-medium">{{ email.subject || '—' }}</span></span>
              <span class="text-muted">Sent to: <span class="text-ink font-medium">{{ email.sent_to || '—' }}</span></span>
            </div>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <NuxtLink :to="editRoute" class="btn ghost no-underline">Edit Email</NuxtLink>
            <NuxtLink
              v-if="email.template_id"
              :to="`/org/events/${eventId}/mail/email-builder/${email.template_id}`"
              class="btn no-underline"
            >Edit Template</NuxtLink>
          </div>
        </div>
      </div>

      <!-- No template linked -->
      <div v-if="!email.template_id" class="card text-center py-12">
        <h3 class="m-0 mb-2">No template linked</h3>
        <p class="muted text-[.88rem] mb-5 max-w-md mx-auto">
          Select a template in this journey to track sends, opens, and delivery for that design.
        </p>
        <NuxtLink :to="editRoute" class="btn no-underline">Choose Template</NuxtLink>
      </div>

      <template v-else>
        <div class="flex items-center gap-2 mb-4 flex-wrap">
          <button
            v-for="r in RANGES"
            :key="r.days"
            type="button"
            class="px-3 py-1.5 rounded-full text-[.76rem] font-semibold border cursor-pointer transition-colors"
            :class="days === r.days
              ? 'bg-[#6352e7] border-[#6352e7] text-white'
              : 'bg-white border-line text-[#5f6b7a] hover:border-[#6352e7] hover:text-[#6352e7]'"
            @click="days = r.days"
          >{{ r.label }}</button>
          <div class="grow" />
          <span v-if="report" class="muted text-[.8rem]">{{ fullDate(report.range.from) }} – {{ fullDate(report.range.to) }}</span>
        </div>

        <div v-if="analyticsLoading && !report" class="card muted text-center py-10">Loading analytics…</div>

        <template v-else-if="report">
          <p v-if="error" class="error mb-3">{{ error }}</p>

          <!-- Headline stats -->
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
            <div class="card !p-3.5">
              <div class="text-[.72rem] uppercase tracking-wide text-muted font-semibold mb-1">Total sends</div>
              <div class="text-[1.45rem] font-bold text-ink leading-none">{{ report.totals.total }}</div>
              <div class="muted text-[.72rem] mt-1.5">{{ report.totals.in_range }} in this window</div>
            </div>
            <div class="card !p-3.5">
              <div class="text-[.72rem] uppercase tracking-wide text-muted font-semibold mb-1">Sent</div>
              <div class="text-[1.45rem] font-bold text-ink leading-none">{{ report.totals.sent }}</div>
              <div class="muted text-[.72rem] mt-1.5">{{ report.totals.today }} today</div>
            </div>
            <div class="card !p-3.5">
              <div class="text-[.72rem] uppercase tracking-wide text-muted font-semibold mb-1">Opened</div>
              <div class="text-[1.45rem] font-bold text-ink leading-none">{{ report.totals.opened }}</div>
              <div class="muted text-[.72rem] mt-1.5">{{ report.totals.open_rate }}% open rate</div>
            </div>
            <div class="card !p-3.5">
              <div class="text-[.72rem] uppercase tracking-wide text-muted font-semibold mb-1">Delivery</div>
              <div class="text-[1.45rem] font-bold text-ink leading-none">{{ report.totals.delivery_rate }}%</div>
              <div class="muted text-[.72rem] mt-1.5">{{ report.totals.delivered }} delivered</div>
            </div>
            <div class="card !p-3.5">
              <div class="text-[.72rem] uppercase tracking-wide text-muted font-semibold mb-1">Recipients</div>
              <div class="text-[1.45rem] font-bold text-ink leading-none">{{ report.totals.unique_recipients }}</div>
              <div class="muted text-[.72rem] mt-1.5">unique addresses</div>
            </div>
            <div class="card !p-3.5">
              <div class="text-[.72rem] uppercase tracking-wide text-muted font-semibold mb-1">Failed</div>
              <div class="text-[1.45rem] font-bold text-ink leading-none">{{ report.totals.failed + report.totals.bounced }}</div>
              <div class="muted text-[.72rem] mt-1.5">{{ report.totals.bounced }} bounced</div>
            </div>
          </div>

          <div id="analytics" class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
            <!-- Timeline -->
            <div class="card lg:col-span-2">
              <h3 class="text-[.95rem] font-bold text-ink m-0 mb-1">Send activity</h3>
              <p class="muted text-[.78rem] mt-0 mb-4">Sends and opens for {{ report.template.name }}</p>
              <div v-if="!report.totals.in_range" class="text-center py-8 muted text-[.88rem]">
                No sends in this period. Use Send Test Email from the list to generate activity.
              </div>
              <div v-else class="flex items-end gap-1.5 h-40">
                <div
                  v-for="b in report.timeline"
                  :key="b.date"
                  class="flex-1 flex flex-col items-center gap-1 min-w-0 h-full justify-end"
                  :title="`${b.label}: ${b.sent} sent, ${b.opened} opened`"
                >
                  <div class="w-full flex flex-col justify-end gap-0.5" style="height: calc(100% - 1.4rem)">
                    <div
                      class="w-full rounded-t bg-[#c4b5fd] min-h-[2px] transition-all"
                      :style="{ height: `${Math.max(b.opened ? 4 : 0, (b.opened / peakSent) * 100)}%` }"
                    />
                    <div
                      class="w-full rounded-t bg-brand min-h-[2px] transition-all"
                      :style="{ height: `${Math.max(b.sent ? 6 : 0, (b.sent / peakSent) * 100)}%` }"
                    />
                  </div>
                  <span class="text-[.62rem] text-muted truncate w-full text-center">{{ b.label }}</span>
                </div>
              </div>
              <div class="flex items-center gap-4 mt-3 text-[.72rem] text-muted">
                <span class="inline-flex items-center gap-1.5"><i class="w-2.5 h-2.5 rounded-sm bg-brand inline-block" /> Sent</span>
                <span class="inline-flex items-center gap-1.5"><i class="w-2.5 h-2.5 rounded-sm bg-[#c4b5fd] inline-block" /> Opened</span>
              </div>
            </div>

            <!-- Status breakdown -->
            <div class="card">
              <h3 class="text-[.95rem] font-bold text-ink m-0 mb-4">By status</h3>
              <div class="flex flex-col gap-3">
                <div v-for="s in report.by_status.filter(x => x.count > 0 || ['sent', 'opened', 'failed'].includes(x.key))" :key="s.key">
                  <div class="flex items-center justify-between text-[.82rem] mb-1">
                    <span class="text-ink font-medium">{{ s.label }}</span>
                    <span class="text-muted">{{ s.count }} · {{ s.share }}%</span>
                  </div>
                  <div class="h-1.5 rounded-full bg-[#f1f3f7] overflow-hidden">
                    <div
                      class="h-full rounded-full transition-all"
                      :style="{ width: `${s.share}%`, background: STATUS_COLORS[s.key] || '#94a3b8' }"
                    />
                  </div>
                </div>
              </div>

              <h3 class="text-[.95rem] font-bold text-ink m-0 mt-6 mb-3">By trigger</h3>
              <div v-if="!report.by_trigger.length" class="muted text-[.82rem]">No sends yet.</div>
              <div v-else class="flex flex-col gap-2">
                <div
                  v-for="t in report.by_trigger"
                  :key="t.key"
                  class="flex items-center justify-between text-[.82rem] py-1.5 border-b border-line last:border-0"
                >
                  <span class="text-ink">{{ t.label }}</span>
                  <span class="text-muted font-medium">{{ t.count }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Recent sends -->
          <div class="card !p-0 overflow-hidden">
            <div class="px-4 py-3 border-b border-line flex items-center justify-between gap-3">
              <div>
                <h3 class="text-[.95rem] font-bold text-ink m-0">Recent sends</h3>
                <p class="muted text-[.78rem] m-0 mt-0.5">Latest activity for this template</p>
              </div>
              <span class="muted text-[.78rem]">{{ report.recent.length }} shown</span>
            </div>
            <div v-if="!report.recent.length" class="px-4 py-10 text-center muted text-[.88rem]">
              No sends recorded yet.
            </div>
            <div v-else class="overflow-x-auto">
              <table class="w-full text-left text-[.84rem]">
                <thead>
                  <tr class="border-b border-line bg-[#fafbfc] text-muted text-[.72rem] uppercase tracking-wide">
                    <th class="px-4 py-2.5 font-semibold">Recipient</th>
                    <th class="px-4 py-2.5 font-semibold">Subject</th>
                    <th class="px-4 py-2.5 font-semibold">Status</th>
                    <th class="px-4 py-2.5 font-semibold">Trigger</th>
                    <th class="px-4 py-2.5 font-semibold">Sent</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in report.recent" :key="row.id" class="border-b border-line last:border-0">
                    <td class="px-4 py-2.5 text-ink font-medium">{{ row.to_email }}</td>
                    <td class="px-4 py-2.5 text-muted truncate max-w-[220px]">{{ row.subject || '—' }}</td>
                    <td class="px-4 py-2.5">
                      <span
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[.7rem] font-semibold capitalize"
                        :class="statusClass(row.status)"
                      >{{ row.status }}</span>
                    </td>
                    <td class="px-4 py-2.5 text-muted capitalize">{{ row.trigger || '—' }}</td>
                    <td class="px-4 py-2.5 text-muted whitespace-nowrap">{{ fmtDate(row.sent_at) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </template>
      </template>
    </template>
  </div>
</template>
