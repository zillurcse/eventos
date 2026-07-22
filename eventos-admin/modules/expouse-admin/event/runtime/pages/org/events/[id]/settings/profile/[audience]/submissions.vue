<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string
const audience = route.params.audience as string

interface SubmissionRow {
  id: string
  source: string
  review_status: 'pending' | 'approved' | 'rejected'
  submitter: { name: string | null, email: string | null }
  form_version: number
  submitted_at: string | null
}

interface SubmissionDetail extends SubmissionRow {
  answers: { key: string, label: string, type: string, value: any }[]
}

const rows = ref<SubmissionRow[]>([])
const loading = ref(true)
const formName = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const filter = ref<'all' | 'pending' | 'approved' | 'rejected'>('all')
const openMenu = ref<string | null>(null)

const detail = ref<SubmissionDetail | null>(null)
const detailLoading = ref(false)
const acting = ref(false)
const exporting = ref(false)
const deleteTarget = ref<SubmissionRow | null>(null)

const title = computed(() => `${formName.value || audience.charAt(0).toUpperCase() + audience.slice(1)} Submission List`)

const SOURCE_LABELS: Record<string, string> = {
  link: 'Link', embed: 'Embed', onboarding: 'Onboarding', registration: 'Registration', admin: 'Admin',
}

const STATUS_CLS: Record<string, string> = {
  pending: 'bg-amber-50 text-amber-700',
  approved: 'bg-green-50 text-green-700',
  rejected: 'bg-red-50 text-red-600',
}

async function load() {
  loading.value = true
  try {
    const q = new URLSearchParams({ page: String(page.value), per_page: '20' })
    if (filter.value !== 'all') q.set('status', filter.value)
    const r = await api<any>(`/events/${id}/profile-forms/${audience}/submissions?${q}`)
    rows.value = r.data
    total.value = r.meta.total
    lastPage.value = r.meta.last_page
    formName.value = r.meta.form_name
  } catch {
    toast.error('Could not load submissions.')
  } finally { loading.value = false }
}

function setFilter(f: typeof filter.value) {
  filter.value = f
  page.value = 1
  load()
}

async function openDetail(row: SubmissionRow) {
  openMenu.value = null
  detailLoading.value = true
  detail.value = { ...row, answers: [] }
  try {
    detail.value = (await api<any>(`/profile-submissions/${row.id}`)).data
  } catch {
    toast.error('Could not load the submission.')
    detail.value = null
  } finally { detailLoading.value = false }
}

async function review(row: { id: string }, action: 'approve' | 'reject') {
  acting.value = true
  try {
    const r = (await api<any>(`/profile-submissions/${row.id}`, { method: 'PATCH', body: { action } })).data
    toast.success(action === 'approve'
      ? (r.participation ? 'Approved — submitter added as a participant' : 'Approved')
      : 'Submission rejected')
    if (detail.value?.id === row.id) detail.value.review_status = r.review_status
    await load()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not update the submission.')
  } finally { acting.value = false }
}

/** One row per submission, one column per field — exports what the filter shows. */
async function exportCsv() {
  exporting.value = true
  try {
    const q = filter.value === 'all' ? '' : `?status=${filter.value}`
    const res = await api<any>(`/events/${id}/profile-forms/${audience}/submissions/export${q}`, { method: 'POST' })
    const url = URL.createObjectURL(new Blob([res.data.csv], { type: 'text/csv;charset=utf-8;' }))
    const a = document.createElement('a')
    a.href = url; a.download = res.data.filename; a.click()
    URL.revokeObjectURL(url)
    toast.success(`Exported ${res.data.count} submission${res.data.count === 1 ? '' : 's'}`)
  } catch {
    toast.error('Could not export submissions.')
  } finally { exporting.value = false }
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  acting.value = true
  try {
    await api(`/profile-submissions/${deleteTarget.value.id}`, { method: 'DELETE' })
    toast.success('Submission deleted')
    if (detail.value?.id === deleteTarget.value.id) detail.value = null
    deleteTarget.value = null
    await load()
  } catch {
    toast.error('Could not delete the submission.')
  } finally { acting.value = false }
}

const fmt = (iso: string | null) => iso
  ? new Date(iso).toLocaleString(undefined, { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' })
  : '—'

const displayValue = (v: any) => {
  if (v === null || v === undefined || v === '') return '—'
  if (Array.isArray(v)) return v.join(', ')
  if (typeof v === 'boolean') return v ? 'Yes' : 'No'
  return String(v)
}

const onWindowClick = () => (openMenu.value = null)
onMounted(() => { load(); window.addEventListener('click', onWindowClick) })
onBeforeUnmount(() => window.removeEventListener('click', onWindowClick))
</script>

<template>
  <div>
    <div class="flex items-center gap-2 text-[.9rem] mb-3">
      <NuxtLink :to="`/org/events/${id}/settings/profile`" class="text-[#6352e7] font-semibold no-underline hover:underline">Profile</NuxtLink>
      <span class="text-faint">›</span>
      <span class="text-ink font-semibold">{{ title }}</span>
    </div>

    <div class="mb-4">
      <h2 class="section-title m-0">{{ title }}</h2>
      <p class="muted text-[.88rem] mt-1 mb-0">Here you can see the list of {{ audience }} submissions.</p>
    </div>

    <div class="flex items-center gap-2 mb-3.5">
      <button
        v-for="f in (['all', 'pending', 'approved', 'rejected'] as const)"
        :key="f"
        class="px-3.5 py-1.5 rounded-full text-[.8rem] font-semibold cursor-pointer border transition-colors duration-150"
        :class="filter === f ? 'bg-[#6352e7] text-white border-[#6352e7]' : 'bg-white text-[#5f6b7a] border-line hover:border-[#6352e7]'"
        @click="setFilter(f)"
      >{{ f === 'all' ? `All (${filter === 'all' ? total : '…'})` : f.charAt(0).toUpperCase() + f.slice(1) }}</button>

      <div class="flex-1" />
      <button class="btn ghost sm" :disabled="exporting || !rows.length" @click="exportCsv">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
        {{ exporting ? 'Exporting…' : 'Export CSV' }}
      </button>
    </div>

    <div class="card p-0 overflow-visible">
      <table class="w-full">
        <thead>
          <tr class="text-left">
            <th class="px-5 py-3.5 w-14">SL</th>
            <th class="px-5 py-3.5">Submitter</th>
            <th class="px-5 py-3.5">Data Source</th>
            <th class="px-5 py-3.5">Status</th>
            <th class="px-5 py-3.5">Created at</th>
            <th class="px-5 py-3.5 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6" class="px-5 py-10 text-center muted">Loading submissions…</td>
          </tr>
          <tr v-else-if="!rows.length">
            <td colspan="6" class="px-5 py-12 text-center muted">
              No submissions yet. Share the published form link — submissions land here.
            </td>
          </tr>
          <tr v-for="(r, i) in rows" v-else :key="r.id" class="border-t border-[#f0f0f5]">
            <td class="px-5 py-4 muted">{{ (page - 1) * 20 + i + 1 }}</td>
            <td class="px-5 py-4">
              <button class="bg-transparent border-none p-0 cursor-pointer text-left font-[inherit]" @click="openDetail(r)">
                <span class="font-semibold text-ink block">{{ r.submitter.name || 'Anonymous' }}</span>
                <span class="muted text-[.78rem]">{{ r.submitter.email || '—' }}</span>
              </button>
            </td>
            <td class="px-5 py-4">{{ SOURCE_LABELS[r.source] || r.source }}</td>
            <td class="px-5 py-4">
              <span class="badge" :class="STATUS_CLS[r.review_status]">{{ r.review_status.charAt(0).toUpperCase() + r.review_status.slice(1) }}</span>
            </td>
            <td class="px-5 py-4 whitespace-nowrap">{{ fmt(r.submitted_at) }}</td>
            <td class="px-5 py-4">
              <div class="flex justify-end" @click.stop>
                <div class="relative">
                  <button
                    class="w-8 h-8 rounded-lg bg-transparent border-none cursor-pointer flex items-center justify-center hover:bg-[#f3f0ff]"
                    aria-label="Actions"
                    @click="openMenu = openMenu === r.id ? null : r.id"
                  >
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" class="text-muted"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
                  </button>
                  <div v-if="openMenu === r.id" class="absolute right-0 top-full mt-1 bg-white border border-line rounded-xl shadow-lg z-30 min-w-[170px] overflow-hidden py-1">
                    <button class="w-full flex items-center gap-2.5 px-4 py-2.5 text-[.85rem] text-ink bg-transparent border-none cursor-pointer text-left hover:bg-[#f7f8fa]" @click="openDetail(r)">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="text-muted"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                      View Details
                    </button>
                    <button
                      v-if="r.review_status !== 'approved'"
                      class="w-full flex items-center gap-2.5 px-4 py-2.5 text-[.85rem] text-green-700 bg-transparent border-none cursor-pointer text-left hover:bg-green-50"
                      :disabled="acting"
                      @click="openMenu = null; review(r, 'approve')"
                    >
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                      Approve
                    </button>
                    <button
                      v-if="r.review_status !== 'rejected'"
                      class="w-full flex items-center gap-2.5 px-4 py-2.5 text-[.85rem] text-amber-700 bg-transparent border-none cursor-pointer text-left hover:bg-amber-50"
                      :disabled="acting"
                      @click="openMenu = null; review(r, 'reject')"
                    >
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                      Reject
                    </button>
                    <button
                      class="w-full flex items-center gap-2.5 px-4 py-2.5 text-[.85rem] text-[#dc2626] bg-transparent border-none cursor-pointer text-left hover:bg-[#fef2f2]"
                      @click="openMenu = null; deleteTarget = r"
                    >
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                      Delete
                    </button>
                  </div>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="lastPage > 1" class="flex items-center justify-end gap-2 mt-3.5">
      <button class="btn ghost sm" :disabled="page <= 1" @click="page--; load()">‹ Prev</button>
      <span class="muted text-[.84rem]">Page {{ page }} of {{ lastPage }}</span>
      <button class="btn ghost sm" :disabled="page >= lastPage" @click="page++; load()">Next ›</button>
    </div>

    <!-- ── Details drawer ─────────────────────────────────────── -->
    <Drawer v-if="detail" :title="detail.submitter.name || 'Submission details'" @close="detail = null">
      <div v-if="detailLoading" class="muted text-center py-10">Loading…</div>
      <template v-else>
        <div class="flex items-center gap-2 mb-4 flex-wrap">
          <span class="badge" :class="STATUS_CLS[detail.review_status]">{{ detail.review_status.charAt(0).toUpperCase() + detail.review_status.slice(1) }}</span>
          <span class="badge bg-gray-100 text-gray-600">{{ SOURCE_LABELS[detail.source] || detail.source }}</span>
          <span class="muted text-[.8rem]">v{{ detail.form_version }} · {{ fmt(detail.submitted_at) }}</span>
        </div>

        <div class="flex flex-col gap-3">
          <div v-for="a in detail.answers" :key="a.key" class="border border-line rounded-xl px-4 py-3">
            <div class="muted text-[.76rem] font-semibold uppercase tracking-wide">{{ a.label }}</div>
            <div class="text-[.92rem] text-ink mt-1 break-words">
              <a v-if="a.type === 'link' && a.value" :href="String(a.value)" target="_blank" class="text-[#6352e7]">{{ a.value }}</a>
              <template v-else>{{ displayValue(a.value) }}</template>
            </div>
          </div>
          <p v-if="!detail.answers.length" class="muted text-center py-6">No answers recorded.</p>
        </div>

        <div class="modal-actions">
          <button
            v-if="detail.review_status !== 'rejected'"
            class="btn ghost text-amber-700"
            :disabled="acting"
            @click="review(detail, 'reject')"
          >Reject</button>
          <button
            v-if="detail.review_status !== 'approved'"
            class="btn"
            :disabled="acting"
            @click="review(detail, 'approve')"
          >{{ acting ? 'Working…' : 'Approve' }}</button>
        </div>
      </template>
    </Drawer>

    <!-- ── Delete confirm ─────────────────────────────────────── -->
    <Modal v-if="deleteTarget" title="Delete submission?" @close="deleteTarget = null">
      <p class="text-[.92rem] mt-0">
        Delete the submission from
        <strong>{{ deleteTarget.submitter.name || deleteTarget.submitter.email || 'this person' }}</strong>?
        This cannot be undone.
      </p>
      <div class="modal-actions">
        <button class="btn ghost" @click="deleteTarget = null">Cancel</button>
        <button class="btn bg-[#dc2626] border-[#dc2626] hover:bg-[#b91c1c]" :disabled="acting" @click="confirmDelete">
          {{ acting ? 'Deleting…' : 'Delete' }}
        </button>
      </div>
    </Modal>
  </div>
</template>
