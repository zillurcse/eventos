<script setup lang="ts">
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface ExpoLensPhoto {
  id: string
  file_id: number
  url: string
  caption: string | null
  album: string
  processing_status: string
  moderation_status: string
  faces_count: number
  matches_count?: number
  failure_reason?: string | null
  created_at: string | null
}

const photos = ref<ExpoLensPhoto[]>([])
const loading = ref(true)
const saved = ref(false)
const filter = ref<'all' | 'pending' | 'processing' | 'ready' | 'failed'>('all')

const BATCH_LIMIT = 20

const addDrawerOpen = ref(false)
const addSaving = ref(false)
const uploadingFiles = ref(false)
const uploadProgress = ref({ done: 0, total: 0 })
const fileInput = ref<HTMLInputElement | null>(null)
const pending = ref<{ id: number, url: string, name: string }[]>([])
const pendingAlbum = ref('')
const photographerName = ref('')
const { upload } = useUpload()

const matchesDrawerOpen = ref(false)
const matchesLoading = ref(false)
const matchPhoto = ref<ExpoLensPhoto | null>(null)
const matches = ref<Array<{
  participation_id: string
  name: string | null
  email: string | null
  avatar_url: string | null
  score: number
  confirmed: boolean
}>>([])

interface UploadLink {
  id: string
  label: string
  album: string | null
  token: string
  auto_approve: boolean
  expires_at: string | null
  max_uploads: number | null
  uploads_count: number
  remaining: number | null
  revoked: boolean
  usable: boolean
  last_used_at: string | null
}

// Same base the layout's "Go to Event" uses — the public event app.
const SITE_BASE = 'http://localhost:3001'
const linksDrawerOpen = ref(false)
const linksLoading = ref(false)
const links = ref<UploadLink[]>([])
const linkSaving = ref(false)
const newLink = ref({ label: '', album: '', auto_approve: false, max_uploads: '' as string | number, expires_at: '' })

const uploadUrl = (link: UploadLink) => `${SITE_BASE}/expolens/upload/${link.token}`

const filtered = computed(() => {
  if (filter.value === 'all') return photos.value
  return photos.value.filter(p => p.processing_status === filter.value)
})

const pendingApproval = computed(
  () => photos.value.filter(p => p.moderation_status === 'pending').length,
)

// Face detection is queued, so a freshly uploaded batch lands as "pending" and
// only becomes "ready" once Horizon works through it. Poll while that's true so
// the grid settles on its own instead of needing a manual reload.
const inFlight = computed(
  () => photos.value.some(p => p.processing_status === 'pending' || p.processing_status === 'processing'),
)

let poll: ReturnType<typeof setInterval> | null = null

function stopPolling() {
  if (poll) { clearInterval(poll); poll = null }
}

watch(inFlight, (busy) => {
  if (busy && !poll) {
    poll = setInterval(() => load({ quiet: true }), 5000)
  } else if (!busy) {
    stopPolling()
  }
})

onBeforeUnmount(stopPolling)

function flash() {
  saved.value = true
  setTimeout(() => (saved.value = false), 1500)
}

function asPhotoList(payload: unknown): ExpoLensPhoto[] {
  if (Array.isArray(payload)) return payload as ExpoLensPhoto[]
  if (payload && typeof payload === 'object' && Array.isArray((payload as any).data)) {
    return (payload as any).data as ExpoLensPhoto[]
  }
  return []
}

async function load({ quiet = false } = {}) {
  if (!quiet) loading.value = true
  try {
    const res = await api<any>(`/events/${id}/expolens/photos`, {
      query: { per_page: 100 },
    })
    photos.value = asPhotoList(res?.data ?? res)
  } catch (e: any) {
    // A failed background poll shouldn't wipe the grid or spam toasts.
    if (quiet) return
    photos.value = []
    toast.error(e?.data?.message || e?.message || 'Could not load ExpoLens photos.')
  } finally {
    if (!quiet) loading.value = false
  }
}

function openAddDrawer() {
  pending.value = []
  pendingAlbum.value = ''
  photographerName.value = ''
  uploadProgress.value = { done: 0, total: 0 }
  addDrawerOpen.value = true
}

function removePending(fileId: number) {
  pending.value = pending.value.filter(p => p.id !== fileId)
}

async function onFilesPicked(e: Event) {
  const input = e.target as HTMLInputElement
  const files = Array.from(input.files ?? [])
  input.value = ''
  if (!files.length) return

  const room = BATCH_LIMIT - pending.value.length
  if (room <= 0) {
    toast.error(`You can queue up to ${BATCH_LIMIT} photos at a time.`)
    return
  }

  const batch = files.slice(0, room)
  if (files.length > room) {
    toast.info(`Only the first ${room} photo(s) were taken (max ${BATCH_LIMIT} per batch).`)
  }

  const accepted: File[] = []
  for (const file of batch) {
    const err = validateImageFile(file, { maxMB: 25 })
    if (err) {
      toast.error(`${file.name}: ${err}`)
      continue
    }
    accepted.push(file)
  }
  if (!accepted.length) return

  uploadingFiles.value = true
  uploadProgress.value = { done: 0, total: accepted.length }
  let failed = 0

  for (const file of accepted) {
    try {
      const uploaded = await upload(file, { collection: 'expolens' })
      if (!pending.value.some(p => p.id === uploaded.id)) {
        pending.value.push({ id: uploaded.id, url: uploaded.url, name: file.name })
      }
    } catch (err: any) {
      failed++
      toast.error(err?.data?.message || `Could not upload ${file.name}`)
    } finally {
      uploadProgress.value = {
        done: uploadProgress.value.done + 1,
        total: uploadProgress.value.total,
      }
    }
  }

  uploadingFiles.value = false
  if (failed === 0) toast.success(`${accepted.length} photo(s) ready to queue`)
}

async function saveAdd() {
  if (!pending.value.length) return
  addSaving.value = true
  try {
    const album = pendingAlbum.value.trim() || undefined
    const payload = pending.value.map(p => ({
      file_id: p.id,
      ...(album ? { album } : {}),
    }))
    await api(`/events/${id}/expolens/photos`, {
      method: 'POST',
      body: {
        images: payload,
        photographer_name: photographerName.value.trim() || undefined,
      },
    })
    addDrawerOpen.value = false
    flash()
    toast.success(`${payload.length} photo(s) queued for face matching`)
    await load()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not register ExpoLens photos.')
  } finally {
    addSaving.value = false
  }
}

async function removePhoto(photo: ExpoLensPhoto) {
  if (!confirm('Remove this ExpoLens photo?')) return
  try {
    await api(`/events/${id}/expolens/photos/${photo.id}`, { method: 'DELETE' })
    photos.value = photos.value.filter(p => p.id !== photo.id)
    toast.success('Photo removed')
  } catch {
    toast.error('Could not remove photo.')
  }
}

async function approve(photo: ExpoLensPhoto) {
  try {
    const res = await api<{ data: ExpoLensPhoto }>(`/events/${id}/expolens/photos/${photo.id}`, {
      method: 'PATCH',
      body: { moderation_status: 'approved' },
    })
    const i = photos.value.findIndex(p => p.id === photo.id)
    if (i >= 0) photos.value[i] = res.data
  } catch {
    toast.error('Could not approve photo.')
  }
}

async function openMatches(photo: ExpoLensPhoto) {
  matchPhoto.value = photo
  matchesDrawerOpen.value = true
  matchesLoading.value = true
  try {
    const res = await api<{ data: typeof matches.value }>(`/events/${id}/expolens/photos/${photo.id}/matches`)
    matches.value = res.data
  } catch {
    toast.error('Could not load matches.')
    matches.value = []
  } finally {
    matchesLoading.value = false
  }
}

async function retry(photo: ExpoLensPhoto) {
  try {
    await api(`/events/${id}/expolens/photos/${photo.id}/retry`, { method: 'POST' })
    const i = photos.value.findIndex(p => p.id === photo.id)
    if (i >= 0) photos.value[i] = { ...photos.value[i]!, processing_status: 'pending', failure_reason: null }
    toast.success('Photo re-queued')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not retry this photo.')
  }
}

async function approveAllPending() {
  if (!pendingApproval.value) return
  if (!confirm(`Approve all ${pendingApproval.value} pending photo(s)? Attendees will be able to see them.`)) return
  try {
    const res = await api<{ message: string }>(`/events/${id}/expolens/photos/bulk`, {
      method: 'POST',
      body: { moderation_status: 'approved', all_pending: true },
    })
    toast.success(res.message)
    await load()
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not approve photos.')
  }
}

async function setMatch(participationId: string, confirmed: boolean) {
  if (!matchPhoto.value) return
  try {
    await api(`/events/${id}/expolens/photos/${matchPhoto.value.id}/matches/${participationId}`, {
      method: 'PATCH',
      body: { confirmed },
    })
    if (confirmed) {
      const m = matches.value.find(x => x.participation_id === participationId)
      if (m) m.confirmed = true
      toast.success('Match confirmed')
    } else {
      matches.value = matches.value.filter(x => x.participation_id !== participationId)
      toast.success('Match removed')
      await load({ quiet: true })
    }
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not update this match.')
  }
}

// `rematch` reuses the face vectors already stored per photo (cheap). `full`
// re-runs detection through the face service and is only needed if detection
// settings changed.
async function reprocess(mode: 'rematch' | 'full' = 'rematch') {
  if (mode === 'full' && !confirm('Re-run face detection on every photo? This is slow for large galleries.')) return
  try {
    const res = await api<{ message: string }>(`/events/${id}/expolens/reprocess`, {
      method: 'POST',
      body: { mode },
    })
    toast.success(res.message)
    await load()
  } catch {
    toast.error('Could not queue reprocessing.')
  }
}

async function openLinks() {
  linksDrawerOpen.value = true
  linksLoading.value = true
  try {
    const res = await api<{ data: UploadLink[] }>(`/events/${id}/expolens/upload-links`)
    links.value = res.data
  } catch {
    toast.error('Could not load upload links.')
    links.value = []
  } finally {
    linksLoading.value = false
  }
}

async function createLink() {
  if (!newLink.value.label.trim()) {
    toast.error('Give the link a name so you know who it went to.')
    return
  }
  linkSaving.value = true
  try {
    const res = await api<{ data: UploadLink }>(`/events/${id}/expolens/upload-links`, {
      method: 'POST',
      body: {
        label: newLink.value.label.trim(),
        album: newLink.value.album.trim() || undefined,
        auto_approve: newLink.value.auto_approve,
        max_uploads: newLink.value.max_uploads ? Number(newLink.value.max_uploads) : undefined,
        expires_at: newLink.value.expires_at || undefined,
      },
    })
    links.value.unshift(res.data)
    newLink.value = { label: '', album: '', auto_approve: false, max_uploads: '', expires_at: '' }
    toast.success('Upload link created — copy it to your photographer')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not create the upload link.')
  } finally {
    linkSaving.value = false
  }
}

async function toggleRevoke(link: UploadLink) {
  const revoking = !link.revoked
  if (revoking && !confirm(`Revoke the link for "${link.label}"? They will not be able to upload again.`)) return
  try {
    const res = await api<{ data: UploadLink }>(`/events/${id}/expolens/upload-links/${link.id}`, {
      method: 'PATCH',
      body: { revoked: revoking },
    })
    const i = links.value.findIndex(l => l.id === link.id)
    if (i >= 0) links.value[i] = res.data
    toast.success(revoking ? 'Link revoked' : 'Link re-enabled')
  } catch {
    toast.error('Could not update the link.')
  }
}

async function copy(text: string, what: string) {
  try { await navigator.clipboard.writeText(text); toast.success(`${what} copied`) }
  catch { toast.error('Copy failed') }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-4 flex items-end justify-between gap-4 flex-wrap">
      <div>
        <h2 class="section-title m-0">
          Photo Gallery
          <span v-if="saved" class="badge active ml-2">saved ✓</span>
        </h2>
        <p class="muted text-[.86rem] mt-0.5 mb-0">
          Photographers upload event photos here. Faces are matched automatically so attendees can find their shots.
        </p>
      </div>
      <div class="flex gap-2 flex-wrap">
        <button v-if="pendingApproval" class="btn ghost" @click="approveAllPending">
          APPROVE ALL ({{ pendingApproval }})
        </button>
        <button class="btn ghost" title="Re-match using stored face data (fast)" @click="reprocess('rematch')">
          REMATCH
        </button>
        <button class="btn ghost" title="Re-run face detection on every photo (slow)" @click="reprocess('full')">
          FULL REPROCESS
        </button>
        <button class="btn ghost" @click="openLinks">PHOTOGRAPHER LINKS</button>
        <button class="btn" @click="openAddDrawer">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
          UPLOAD PHOTOS
        </button>
      </div>
    </div>

    <p v-if="inFlight" class="muted text-[.82rem] mb-3">
      Face matching in progress — this list refreshes automatically.
    </p>

    <div class="flex flex-wrap gap-2 mb-4">
      <button
        v-for="f in (['all', 'pending', 'processing', 'ready', 'failed'] as const)"
        :key="f"
        class="px-3.5 py-1.5 rounded-full border text-sm font-medium transition-all duration-150"
        :class="filter === f
          ? 'border-[#6352e7] bg-[#f3f0ff] text-[#6352e7]'
          : 'border-line bg-white text-muted hover:border-[#6352e7] hover:text-[#6352e7]'"
        @click="filter = f"
      >
        {{ f }}
      </button>
    </div>

    <div v-if="loading" class="muted text-[.86rem] py-10 text-center">Loading…</div>

    <div v-else-if="filtered.length" class="grid grid-cols-[repeat(auto-fill,minmax(180px,1fr))] gap-3">
      <div
        v-for="photo in filtered"
        :key="photo.id"
        class="img-card aspect-square"
      >
        <img :src="photo.url" :alt="photo.caption || ''" loading="lazy">
        <div class="absolute top-2 left-2 flex gap-1">
          <span class="status-chip">{{ photo.processing_status }}</span>
          <span class="status-chip" :class="photo.moderation_status">{{ photo.moderation_status }}</span>
        </div>
        <div class="absolute bottom-0 left-0 right-0 px-2.5 py-1.5 bg-[rgba(0,0,0,.55)] text-white text-xs">
          <template v-if="photo.processing_status === 'failed'">
            <span :title="photo.failure_reason || ''">Processing failed</span>
          </template>
          <template v-else>
            {{ photo.faces_count }} face{{ photo.faces_count === 1 ? '' : 's' }}
            <span v-if="photo.matches_count != null"> · {{ photo.matches_count }} match{{ photo.matches_count === 1 ? '' : 'es' }}</span>
          </template>
        </div>
        <div class="img-card-actions">
          <button class="img-action" title="Matches" @click="openMatches(photo)">👁</button>
          <button
            v-if="photo.processing_status === 'failed'"
            class="img-action"
            title="Retry face detection"
            @click="retry(photo)"
          >↻</button>
          <button
            v-if="photo.moderation_status !== 'approved'"
            class="img-action"
            title="Approve"
            @click="approve(photo)"
          >✓</button>
          <button class="img-action danger" title="Remove" @click="removePhoto(photo)">✕</button>
        </div>
      </div>
    </div>

    <p v-else class="muted text-[.86rem] py-10 text-center">
      No ExpoLens photos yet. Upload photographer shots to start face matching.
    </p>

    <Drawer v-if="addDrawerOpen" title="Upload ExpoLens Photos" @close="addDrawerOpen = false">
      <label class="block mb-2">Photos</label>
      <p class="muted text-[.82rem] -mt-1 mb-3">
        Select up to {{ BATCH_LIMIT }} JPEG / PNG / WebP photos at once (max 25 MB each). No crop step — built for photographer dumps.
      </p>

      <input
        ref="fileInput"
        type="file"
        class="hidden"
        accept="image/jpeg,image/png,image/webp,image/jpg"
        multiple
        @change="onFilesPicked"
      >
      <button
        class="btn ghost w-full justify-center mb-3"
        :disabled="uploadingFiles || pending.length >= BATCH_LIMIT"
        @click="fileInput?.click()"
      >
        {{ uploadingFiles
          ? `Uploading ${uploadProgress.done}/${uploadProgress.total}…`
          : pending.length
            ? `ADD MORE (${pending.length}/${BATCH_LIMIT})`
            : `CHOOSE UP TO ${BATCH_LIMIT} PHOTOS` }}
      </button>

      <div v-if="pending.length" class="grid grid-cols-3 gap-2 mb-4 max-h-64 overflow-auto">
        <div v-for="p in pending" :key="p.id" class="relative aspect-square rounded-lg overflow-hidden border border-line bg-[#f8f8fb]">
          <img :src="p.url" :alt="p.name" class="w-full h-full object-cover">
          <button
            class="absolute top-1 right-1 w-6 h-6 rounded-full bg-[rgba(0,0,0,.55)] text-white text-xs"
            title="Remove"
            @click="removePending(p.id)"
          >✕</button>
        </div>
      </div>

      <label class="mt-2">Album</label>
      <input v-model="pendingAlbum" placeholder="e.g. Day 1, Keynotes (blank = General)">

      <label>Photographer</label>
      <input v-model="photographerName" placeholder="Optional photographer name">

      <div class="modal-actions">
        <button class="btn ghost" :disabled="uploadingFiles" @click="addDrawerOpen = false">Cancel</button>
        <button class="btn" :disabled="!pending.length || addSaving || uploadingFiles" @click="saveAdd">
          {{ addSaving ? 'Queuing…' : `QUEUE ${pending.length || ''}`.trim() }}
        </button>
      </div>
    </Drawer>

    <Drawer v-if="matchesDrawerOpen" title="Detected attendees" @close="matchesDrawerOpen = false">
      <div v-if="matchPhoto" class="mb-4">
        <img :src="matchPhoto.url" class="w-full max-h-48 object-cover rounded-lg" alt="">
      </div>
      <div v-if="matchesLoading" class="muted text-sm">Loading matches…</div>
      <div v-else-if="!matches.length" class="muted text-sm">No attendees matched yet.</div>
      <ul v-else class="space-y-3">
        <li v-for="m in matches" :key="m.participation_id" class="flex items-center gap-3">
          <img
            v-if="m.avatar_url"
            :src="m.avatar_url"
            class="w-10 h-10 rounded-full object-cover"
            alt=""
          >
          <div class="w-10 h-10 rounded-full bg-[#eee] grid place-items-center text-xs" v-else>?</div>
          <div class="min-w-0 flex-1">
            <div class="font-medium truncate">
              {{ m.name || 'Attendee' }}
              <span v-if="m.confirmed" class="badge active ml-1">confirmed</span>
            </div>
            <div class="muted text-xs truncate">{{ m.email }}</div>
          </div>
          <div class="text-sm font-medium shrink-0">{{ Math.round(m.score * 100) }}%</div>
          <div class="flex gap-1 shrink-0">
            <button
              v-if="!m.confirmed"
              class="img-action"
              title="Confirm this is them"
              @click="setMatch(m.participation_id, true)"
            >✓</button>
            <button
              class="img-action danger"
              title="Not them — remove match"
              @click="setMatch(m.participation_id, false)"
            >✕</button>
          </div>
        </li>
      </ul>
    </Drawer>

    <Drawer v-if="linksDrawerOpen" title="Photographer Upload Links" @close="linksDrawerOpen = false">
      <p class="muted text-[.82rem] mb-4">
        Share a link with a photographer so they can upload straight into this event — no account needed.
        Every upload runs through face matching, and lands in the moderation queue unless you auto-approve.
      </p>

      <div class="border border-line rounded-xl p-3 mb-5 bg-[#f8f8fb]">
        <label class="block">Who is this link for?</label>
        <input v-model="newLink.label" placeholder="e.g. Rahim (main stage photographer)">

        <label>Album</label>
        <input v-model="newLink.album" placeholder="Optional — e.g. Day 1 (blank = General)">

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label>Max uploads</label>
            <input v-model="newLink.max_uploads" type="number" min="1" placeholder="Unlimited">
          </div>
          <div>
            <label>Expires</label>
            <input v-model="newLink.expires_at" type="datetime-local">
          </div>
        </div>

        <label class="flex items-center gap-2 mt-3 font-normal">
          <input v-model="newLink.auto_approve" type="checkbox">
          <span class="text-[.86rem]">Auto-approve — skip moderation for this photographer</span>
        </label>

        <button class="btn w-full justify-center mt-3" :disabled="linkSaving" @click="createLink">
          {{ linkSaving ? 'Creating…' : 'CREATE LINK' }}
        </button>
      </div>

      <div v-if="linksLoading" class="muted text-sm text-center py-6">Loading links…</div>
      <div v-else-if="!links.length" class="muted text-sm text-center py-6">No upload links yet.</div>
      <ul v-else class="space-y-3">
        <li v-for="link in links" :key="link.id" class="border border-line rounded-xl p-3">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <div class="font-medium truncate">{{ link.label }}</div>
              <div class="muted text-xs">
                {{ link.uploads_count }} uploaded
                <span v-if="link.max_uploads"> / {{ link.max_uploads }}</span>
                <span v-if="link.album"> · {{ link.album }}</span>
                <span v-if="link.auto_approve"> · auto-approve</span>
              </div>
            </div>
            <span class="status-chip" :class="link.usable ? 'approved' : 'rejected'">
              {{ link.revoked ? 'revoked' : link.usable ? 'active' : 'unusable' }}
            </span>
          </div>

          <div class="flex gap-2 mt-2">
            <input :value="uploadUrl(link)" readonly class="flex-1 m-0 text-[.78rem]" @focus="($event.target as HTMLInputElement).select()">
            <button class="btn ghost sm shrink-0" @click="copy(uploadUrl(link), 'Link')">Copy</button>
            <button class="btn ghost sm shrink-0" @click="toggleRevoke(link)">
              {{ link.revoked ? 'Enable' : 'Revoke' }}
            </button>
          </div>
        </li>
      </ul>
    </Drawer>
  </div>
</template>

<style scoped>
.status-chip {
  display: inline-flex;
  padding: 2px 8px;
  border-radius: 999px;
  background: rgba(0, 0, 0, .55);
  color: #fff;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: .04em;
}
.status-chip.approved { background: rgba(16, 185, 129, .85); }
.status-chip.rejected { background: rgba(239, 68, 68, .85); }
.status-chip.pending { background: rgba(245, 158, 11, .85); }
.btn.sm { padding: 6px 10px; font-size: .78rem; }
</style>
