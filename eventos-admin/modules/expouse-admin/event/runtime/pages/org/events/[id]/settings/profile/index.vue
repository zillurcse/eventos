<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface ServiceRow {
  audience: string
  id: string
  name: string
  status: 'draft' | 'published' | 'closed'
  version: number
  fields_count: number
  submissions_count: number
  pending_count: number
}

const rows = ref<ServiceRow[]>([])
const loading = ref(true)
const openMenu = ref<string | null>(null)
// Actions dropdown is teleported to <body> (the DataTable wrapper clips overflow),
// so we track the trigger button's viewport position to anchor it there.
const menuAnchor = ref<{ top: number; right: number } | null>(null)

// Publish & Share modal
const shareRow = ref<ServiceRow | null>(null)
const publishing = ref(false)

// Reset confirm modal
const resetRow = ref<ServiceRow | null>(null)
const resetting = ref(false)

// Same base the layout's "Go to Event" uses — the public event app.
const SITE_BASE = 'http://localhost:3001'
const publicUrl = (r: ServiceRow) => `${SITE_BASE}/f/${r.id}`
const embedCode = (r: ServiceRow) =>
  `<iframe src="${publicUrl(r)}?embed=1" width="100%" height="720" style="border:0;border-radius:12px" title="${r.name} form"></iframe>`

async function load() {
  loading.value = true
  try { rows.value = (await api<any>(`/events/${id}/profile-forms`)).data }
  catch { toast.error('Could not load profile forms.') }
  finally { loading.value = false }
}

function toggleMenu(audience: string, ev: MouseEvent) {
  if (openMenu.value === audience) {
    closeMenu()
    return
  }
  openMenu.value = audience
  const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
  menuAnchor.value = { top: rect.bottom + 4, right: window.innerWidth - rect.right }
}
function closeMenu() { openMenu.value = null; menuAnchor.value = null }

function openShare(r: ServiceRow) {
  closeMenu()
  shareRow.value = r
}

async function publishForm(r: ServiceRow) {
  publishing.value = true
  try {
    await api(`/events/${id}/profile-forms/${r.audience}/publish`, { method: 'POST' })
    toast.success(`${r.name} form published`)
    await load()
    shareRow.value = rows.value.find(x => x.audience === r.audience) || null
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not publish the form.')
  } finally { publishing.value = false }
}

async function copy(text: string, what: string) {
  try { await navigator.clipboard.writeText(text); toast.success(`${what} copied`) }
  catch { toast.error('Copy failed') }
}

async function confirmReset() {
  if (!resetRow.value) return
  resetting.value = true
  try {
    await api(`/events/${id}/profile-forms/${resetRow.value.audience}`, { method: 'DELETE' })
    toast.success(`${resetRow.value.name} form reset to defaults`)
    resetRow.value = null
    await load()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not reset the form.')
  } finally { resetting.value = false }
}

const onWindowClick = () => closeMenu()
// Fixed-position teleported menu doesn't scroll with the table, so drop it on scroll.
const onWindowScroll = () => closeMenu()
onMounted(() => {
  load()
  window.addEventListener('click', onWindowClick)
  window.addEventListener('scroll', onWindowScroll, true)
})
onBeforeUnmount(() => {
  window.removeEventListener('click', onWindowClick)
  window.removeEventListener('scroll', onWindowScroll, true)
})
</script>

<template>
  <div>
    <div class="mb-5">
      <h2 class="section-title m-0">Profile Settings</h2>
      <p class="muted text-[.88rem] mt-1 mb-0">Configure user profile fields for different user types at your event.</p>
    </div>

    <DataTable
      :items="rows"
      :columns="[
        { key: 'name', label: 'Services' },
        { key: 'fields_count', label: 'Fields' },
        { key: 'submissions_count', label: 'Submissions' },
        { key: 'status', label: 'Status' },
      ]"
      row-key="audience"
      storage-key="profile-forms"
      :empty-text="loading ? 'Loading profile forms…' : 'No profile forms found.'"
    >
      <template #cell-name="{ row }">
        <NuxtLink :to="`/org/events/${id}/settings/profile/${row.audience}`" class="font-semibold text-ink no-underline hover:text-brand">
          {{ row.name }}
        </NuxtLink>
      </template>

      <template #cell-submissions_count="{ row }">
        <NuxtLink
          :to="`/org/events/${id}/settings/profile/${row.audience}/submissions`"
          class="text-brand font-semibold underline underline-offset-2"
        >{{ row.submissions_count }}</NuxtLink>
        <span v-if="row.pending_count" class="ml-2 text-[.72rem] font-bold text-amber-600 bg-amber-50 rounded-full px-2 py-0.5">{{ row.pending_count }} pending</span>
      </template>

      <template #cell-status="{ row }">
        <span
          class="badge"
          :class="row.status === 'published' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'"
        >{{ row.status === 'published' ? `Published · v${row.version}` : 'Draft' }}</span>
      </template>

      <template #actions="{ row }">
        <div class="relative inline-block" @click.stop>
          <button
            class="w-8 h-8 rounded-lg bg-transparent border-none cursor-pointer flex items-center justify-center hover:bg-brand-soft"
            aria-label="Actions"
            @click="toggleMenu(row.audience, $event)"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" class="text-muted"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
          </button>
          <Teleport to="body">
            <div
              v-if="openMenu === row.audience && menuAnchor"
              class="fixed bg-white border border-line rounded-xl shadow-lg z-30 min-w-47.5 overflow-hidden py-1"
              :style="{ top: `${menuAnchor.top}px`, right: `${menuAnchor.right}px` }"
              @click.stop
            >
              <NuxtLink
                :to="`/org/events/${id}/settings/profile/${row.audience}`"
                class="flex items-center gap-2.5 px-4 py-2.5 text-[.85rem] text-ink no-underline hover:bg-[#f7f8fa]"
                @click="closeMenu"
              >
                <AppIcon name="pencil" class="w-4 h-4 text-muted" /> Manage Fields
              </NuxtLink>
              <NuxtLink
                :to="`/org/events/${id}/settings/profile/${row.audience}/submissions`"
                class="flex items-center gap-2.5 px-4 py-2.5 text-[.85rem] text-ink no-underline hover:bg-[#f7f8fa]"
                @click="closeMenu"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="text-muted"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                View Submissions
              </NuxtLink>
              <button
                class="w-full flex items-center gap-2.5 px-4 py-2.5 text-[.85rem] text-ink bg-transparent border-none cursor-pointer text-left hover:bg-[#f7f8fa]"
                @click="openShare(row)"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="text-muted"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.59 13.51 6.83 3.98M15.41 6.51l-6.82 3.98"/></svg>
                Publish &amp; Share
              </button>
              <button
                class="w-full flex items-center gap-2.5 px-4 py-2.5 text-[.85rem] text-[#dc2626] bg-transparent border-none cursor-pointer text-left hover:bg-[#fef2f2]"
                @click="closeMenu(); resetRow = row"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                Delete &amp; Reset
              </button>
            </div>
          </Teleport>
        </div>
      </template>
    </DataTable>

    <!-- ── Publish & Share ─────────────────────────────────────── -->
    <Modal v-if="shareRow" :title="`Share ${shareRow.name} Form`" @close="shareRow = null">
      <template v-if="shareRow.status !== 'published'">
        <p class="muted text-[.9rem] mt-0">
          This form is still a <strong>draft</strong>. Publish it to get a public link you can
          share anywhere or embed on your own website.
        </p>
        <div class="modal-actions">
          <button class="btn ghost" @click="shareRow = null">Cancel</button>
          <button class="btn" :disabled="publishing" @click="publishForm(shareRow)">
            {{ publishing ? 'Publishing…' : 'Publish Form' }}
          </button>
        </div>
      </template>

      <template v-else>
        <label class="block mb-1.5">Public link</label>
        <div class="flex gap-2 items-center">
          <input :value="publicUrl(shareRow)" readonly class="flex-1 m-0 text-[.85rem]" @focus="($event.target as HTMLInputElement).select()">
          <button class="btn ghost sm shrink-0" @click="copy(publicUrl(shareRow), 'Link')">Copy</button>
          <a :href="publicUrl(shareRow)" target="_blank" class="btn ghost sm shrink-0 no-underline">Open</a>
        </div>
        <p class="muted text-[.78rem] mt-1.5">
          Anyone with this link can submit the form — share it in emails, socials or QR codes.
        </p>

        <label class="block mb-1.5 mt-4">Embed on your website</label>
        <textarea :value="embedCode(shareRow)" readonly rows="4" class="w-full m-0 font-mono text-[.78rem] leading-relaxed" @focus="($event.target as HTMLTextAreaElement).select()" />
        <div class="flex justify-end mt-2">
          <button class="btn ghost sm" @click="copy(embedCode(shareRow), 'Embed code')">Copy embed code</button>
        </div>
        <p class="muted text-[.78rem] mt-1.5">
          Paste this snippet into any website's HTML — the form renders inside the page and
          submissions land here under <strong>{{ shareRow.name }} › Submissions</strong>.
        </p>

        <div class="modal-actions">
          <button class="btn ghost" @click="shareRow = null">Done</button>
          <button class="btn" :disabled="publishing" @click="publishForm(shareRow)">
            {{ publishing ? 'Publishing…' : 'Republish (v' + (shareRow.version + 1) + ')' }}
          </button>
        </div>
      </template>
    </Modal>

    <!-- ── Reset confirm ───────────────────────────────────────── -->
    <Modal v-if="resetRow" title="Delete & reset form?" @close="resetRow = null">
      <p class="text-[.92rem] mt-0">
        This deletes the <strong>{{ resetRow.name }}</strong> form — including its
        <strong>{{ resetRow.submissions_count }} submission{{ resetRow.submissions_count === 1 ? '' : 's' }}</strong> —
        and recreates it with the default fields as an unpublished draft.
      </p>
      <p class="muted text-[.85rem]">Shared links and embeds stop working until you publish again. This cannot be undone.</p>
      <div class="modal-actions">
        <button class="btn ghost" @click="resetRow = null">Cancel</button>
        <button class="btn bg-[#dc2626] border-[#dc2626] hover:bg-[#b91c1c]" :disabled="resetting" @click="confirmReset">
          {{ resetting ? 'Resetting…' : 'Delete & Reset' }}
        </button>
      </div>
    </Modal>
  </div>
</template>
