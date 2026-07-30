<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'
import SpeakerRatingsDrawer from '../../../../../../components/speaker/RatingsDrawer.vue'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api   = useApi()
const id    = route.params.id as string

// ── Types ────────────────────────────────────────────────────────────────────

interface SpeakerCategory {
  id: string
  name: string
}

interface Speaker {
  id: string
  name: string
  email: string
  has_login: boolean
  designation: string
  company: string
  category: string
  presentation_title: string
  presentation_file: string | null
  presentation_file_name: string
  bio: string
  image_url: string | null
  facebook: string
  linkedin: string
  twitter: string
  instagram: string
  whatsapp: string
  tags: string[]
  can_rate: boolean
  is_featured: boolean
  is_public: boolean
  sort_order: number
}

type SpeakerDraft = Omit<Speaker, 'id' | 'sort_order'>

// ── State ────────────────────────────────────────────────────────────────────

const speakers   = ref<Speaker[]>([])
const categories = ref<SpeakerCategory[]>([])
const catBusy    = ref(false)
const search     = ref('')
const drawerOpen = ref(false)
const editing    = ref<Speaker | null>(null)
const saving     = ref(false)
const error      = ref('')

// ── Computed ─────────────────────────────────────────────────────────────────

const MAX_SPEAKERS = 50

const progressPct = computed(() =>
  Math.min(100, Math.round((speakers.value.length / MAX_SPEAKERS) * 100)),
)

const filtered = computed(() => {
  const q = search.value.toLowerCase()
  if (!q) return speakers.value
  return speakers.value.filter(s =>
    [s.name, s.company, s.designation, s.email].some(f =>
      (f ?? '').toLowerCase().includes(q)
    )
  )
})

// ── Speakers API ─────────────────────────────────────────────────────────────

async function load() {
  try {
    const res = await api<{ data: Speaker[] }>(`/events/${id}/speakers`)
    speakers.value = res.data
  } catch { /* */ }
}

async function saveDraft(payload: SpeakerDraft) {
  error.value = ''
  saving.value = true
  try {
    if (editing.value) {
      const res = await api<{ data: Speaker }>(`/events/${id}/speakers/${editing.value.id}`, {
        method: 'PUT', body: payload,
      })
      const idx = speakers.value.findIndex(s => s.id === editing.value!.id)
      if (idx >= 0) speakers.value[idx] = res.data
    } else {
      const res = await api<{ data: Speaker }>(`/events/${id}/speakers`, {
        method: 'POST', body: payload,
      })
      speakers.value.push(res.data)
    }
    drawerOpen.value = false
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save speaker.'
  } finally {
    saving.value = false
  }
}

async function removeSpeaker(s: Speaker) {
  if (!confirm(`Remove speaker "${s.name}"?`)) return
  try {
    await api(`/events/${id}/speakers/${s.id}`, { method: 'DELETE' })
    speakers.value = speakers.value.filter(x => x.id !== s.id)
  } catch { /* */ }
}

// ── Speaker login ────────────────────────────────────────────────────────────
// A speaker is a contact + participation with no account, so until this runs
// they cannot sign in to the event site — and a session host is identified by
// their signed-in participation, so they also cannot go on camera.

const loginFor = ref<Speaker | null>(null)
const ratingsFor = ref<Speaker | null>(null)
const loginMode = ref<'auto' | 'manual'>('auto')
const loginPassword = ref('')
const loginSaving = ref(false)
const loginError = ref('')
const issued = ref<{ email: string, password: string } | null>(null)

function openLogin(s: Speaker) {
  loginFor.value = s
  loginMode.value = 'auto'
  loginPassword.value = ''
  loginError.value = ''
  issued.value = null
}
function closeLogin() {
  loginFor.value = null
  issued.value = null
}

async function submitLogin() {
  const s = loginFor.value
  if (!s || loginSaving.value) return
  loginSaving.value = true
  loginError.value = ''
  try {
    const { data } = await api<any>(`/events/${id}/speakers/${s.id}/reset-password`, {
      method: 'POST',
      body: {
        mode: loginMode.value,
        password: loginMode.value === 'manual' ? loginPassword.value.trim() : undefined,
      },
    })
    issued.value = data
    // The row now has an account — reflect it without a full reload.
    const row = speakers.value.find(x => x.id === s.id)
    if (row) row.has_login = true
  } catch (e: any) {
    loginError.value = e?.data?.message || 'Could not set the password.'
  } finally {
    loginSaving.value = false
  }
}

async function copyLogin() {
  if (!issued.value) return
  const text = `Email: ${issued.value.email}\nPassword: ${issued.value.password}`
  try { await navigator.clipboard.writeText(text) } catch { /* clipboard blocked */ }
}

// ── Categories API ───────────────────────────────────────────────────────────

async function loadCategories() {
  try {
    const res = await api<{ data: SpeakerCategory[] }>(`/events/${id}/speaker-categories`)
    categories.value = res.data
  } catch { /* */ }
}

async function addCategory(name: string) {
  catBusy.value = true
  try {
    const res = await api<{ data: SpeakerCategory[] }>(`/events/${id}/speaker-categories`, {
      method: 'POST', body: { name },
    })
    categories.value = res.data
  } catch { /* */ } finally {
    catBusy.value = false
  }
}

async function renameCategory({ id: catId, name }: { id: string, name: string }) {
  try {
    const res = await api<{ data: SpeakerCategory[] }>(`/events/${id}/speaker-categories/${catId}`, {
      method: 'PUT', body: { name },
    })
    categories.value = res.data
  } catch { /* */ }
}

async function removeCategory(catId: string) {
  const cat = categories.value.find(c => c.id === catId)
  if (cat && !confirm(`Delete category "${cat.name}"?`)) return
  try {
    const res = await api<{ data: SpeakerCategory[] }>(`/events/${id}/speaker-categories/${catId}`, {
      method: 'DELETE',
    })
    categories.value = res.data
  } catch { /* */ }
}

// ── Drawer ───────────────────────────────────────────────────────────────────

function openAdd() {
  editing.value = null
  error.value = ''
  drawerOpen.value = true
}

function openEdit(s: Speaker) {
  editing.value = s
  error.value = ''
  drawerOpen.value = true
}

// TODO: wire to real features — reuse speakers from a past event / pick from the
// org-wide speakers directory. Stubbed for now so the buttons are non-destructive.
// Re-seat someone who has spoken at one of this organizer's other events; the
// drawer owns the picking and the import, we just reload the list afterwards.
const previousOpen = ref(false)

function openPrevious() {
  previousOpen.value = true
}

function openDirectory() {
  toast.info('Coming soon.')
}

// ── Init ─────────────────────────────────────────────────────────────────────

onMounted(() => { load(); loadCategories() })
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex items-start justify-between gap-4 mb-5">
      <div>
        <h1 class="text-[1.35rem] font-bold text-ink mb-0.5">Speakers</h1>
        <p class="text-muted text-[.88rem]">Manage the speakers that appear in your event.</p>

        <!-- Counter + progress -->
        <div class="flex flex-col gap-1.5 mt-2.5">
          <div class="inline-flex">
            <span class="bg-brand text-white text-[.76rem] font-bold px-3 py-1 rounded-full leading-none">
              {{ speakers.length }} of {{ MAX_SPEAKERS }}
            </span>
          </div>
          <div class="w-52 h-1.75 bg-line rounded-full overflow-hidden">
            <div class="h-full bg-brand rounded-full transition-all" :style="{ width: progressPct + '%' }" />
          </div>
        </div>
      </div>

      <button class="btn ghost text-[.82rem] tracking-wide px-4 py-2 shrink-0" @click="openPrevious">
        PREVIOUS SPEAKERS
      </button>
    </div>

    <!-- Toolbar: search + directory/add -->
    <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
      <!-- <AppInput v-model="search" placeholder="Search" class="max-w-[400px] m-0" /> -->
      <SearchInput v-model="search" placeholder="Search" class="flex-1 min-w-45 max-w-70" />

      <div class="flex items-center gap-2 shrink-0">
        <button
          class="inline-flex items-center gap-2 px-4 py-2.5 rounded-md bg-[#F0EEFD] text-brand font-semibold text-[.82rem] border-0 cursor-pointer hover:bg-[#e9e7f8]"
          @click="openDirectory"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
          Speakers Directory
        </button>
        <button class="btn text-[.82rem] tracking-wide px-4 py-2" @click="openAdd">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
          Add Speaker
        </button>
      </div>
    </div>

    <SpeakerTable
      :speakers="filtered"
      :searching="!!search"
      @edit="openEdit"
      @remove="removeSpeaker"
      @login="openLogin"
      @ratings="(s) => ratingsFor = s"
    />

    <!-- Import speakers from the organizer's other events. -->
    <SpeakerPreviousDrawer
      v-if="previousOpen"
      :event-id="id"
      @close="previousOpen = false"
      @imported="load"
    />

    <!-- Speaker login: a speaker has no account until this runs, and without one
         they can't sign in to the event site or host their own session. -->
    <div v-if="loginFor" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="closeLogin">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h3 class="font-semibold text-[1rem] text-ink m-0 mb-1">
          {{ loginFor.has_login ? 'Reset password' : 'Create a login' }}
        </h3>
        <p class="muted text-[.85rem] mt-0 mb-4">
          {{ loginFor.name }} — <span class="font-mono">{{ loginFor.email }}</span>
        </p>

        <template v-if="!issued">
          <p v-if="!loginFor.has_login" class="text-[.83rem] text-[#b45309] bg-[#fffbeb] border border-[#fde68a] rounded-lg px-3 py-2 mb-4">
            This speaker has no account yet. They need one to sign in to the event site and go
            on camera for their session.
          </p>

          <div class="flex flex-col gap-2 mb-4">
            <label class="flex items-center gap-2.5 cursor-pointer">
              <input v-model="loginMode" type="radio" value="auto" class="m-0 accent-brand">
              <span class="text-[.9rem] text-ink">Generate a password for me</span>
            </label>
            <label class="flex items-center gap-2.5 cursor-pointer">
              <input v-model="loginMode" type="radio" value="manual" class="m-0 accent-brand">
              <span class="text-[.9rem] text-ink">Set it myself</span>
            </label>
          </div>

          <div v-if="loginMode === 'manual'" class="mb-4">
            <input
              v-model="loginPassword"
              type="text"
              placeholder="At least 8 characters"
              class="m-0 w-full"
            >
          </div>

          <p v-if="loginError" class="error mb-3">{{ loginError }}</p>

          <div class="flex justify-end gap-2">
            <button class="btn ghost sm" @click="closeLogin">Cancel</button>
            <button
              class="btn sm"
              :disabled="loginSaving || (loginMode === 'manual' && loginPassword.trim().length < 8)"
              @click="submitLogin"
            >{{ loginSaving ? 'Saving…' : (loginFor.has_login ? 'RESET PASSWORD' : 'CREATE LOGIN') }}</button>
          </div>
        </template>

        <!-- Shown once: we store only the hash, so this can't be recovered later. -->
        <template v-else>
          <p class="text-[.85rem] text-ink mb-3">
            Give these details to the speaker. The password is shown once. It cannot be
            retrieved later, only reset.
          </p>
          <div class="border border-line rounded-xl p-3 mb-4 bg-[#fcfcfd] font-mono text-[.85rem]">
            <div class="mb-1"><span class="muted">Email:</span> {{ issued.email }}</div>
            <div><span class="muted">Password:</span> <strong>{{ issued.password }}</strong></div>
          </div>
          <div class="flex justify-end gap-2">
            <button class="btn ghost sm" @click="copyLogin">Copy</button>
            <button class="btn sm" @click="closeLogin">Done</button>
          </div>
        </template>
      </div>
    </div>

    <!-- Add / Edit Drawer -->
    <SpeakerFormDrawer
      v-if="drawerOpen"
      :event-id="id"
      :speaker="editing"
      :categories="categories"
      :saving="saving"
      :error="error"
      :cat-busy="catBusy"
      @close="drawerOpen = false"
      @save="saveDraft"
      @add-category="addCategory"
      @rename-category="renameCategory"
      @remove-category="removeCategory"
    />

    <SpeakerRatingsDrawer
      v-if="ratingsFor"
      :event-id="id"
      :speaker-id="ratingsFor.id"
      :speaker-name="ratingsFor.name"
      @close="ratingsFor = null"
    />
  </div>
</template>
