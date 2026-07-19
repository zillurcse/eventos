<script setup lang="ts">
const props = defineProps<{
  eventId: string
  username: string
  accessCode: string
  panel: { title: string; credentials_title: string; details: string | null }
}>()

const emit = defineEmits<{
  (e: 'updated', v: { username?: string; panel?: typeof props.panel }): void
}>()

const api = useApi()

// ── Mobile access description ───────────────────────────────
const isEditingPanel = ref(false)
const localTitle = ref(props.panel.title)
const localDetails = ref(props.panel.details ?? '')
const panelSaving = ref(false)

const DEFAULT_DETAILS = `After downloading the mobile app, use the provided Username and Access Code to log in with Admin Access and start capturing leads instantly.\n\nExhibitor Admins will be able to view all leads captured by their respective teams, along with full insights, scanning activity, statistics, and lead performance.\n\nTeam members can simply enter their name and the same Access Code to start capturing visitors instantly. Each team member will only be able to view and manage the leads they captured.\n\nAfter scanning each lead, a pop-up will appear allowing you to add a comment or rate them from 1–5.`

function startPanelEdit() {
  if (!localDetails.value) localDetails.value = DEFAULT_DETAILS
  isEditingPanel.value = true
}
function cancelPanelEdit() {
  localTitle.value = props.panel.title
  localDetails.value = props.panel.details ?? ''
  isEditingPanel.value = false
}
async function savePanelEdit() {
  panelSaving.value = true
  try {
    await api(`/events/${props.eventId}/settings`, {
      method: 'PUT',
      body: { mobile_access_panel: { title: localTitle.value, credentials_title: localCredentialsTitle.value, details: localDetails.value } },
    })
    emit('updated', { panel: { title: localTitle.value, credentials_title: localCredentialsTitle.value, details: localDetails.value } })
    isEditingPanel.value = false
  } finally {
    panelSaving.value = false
  }
}

// ── Credentials section title ───────────────────────────────
const isEditingCredTitle = ref(false)
const localCredentialsTitle = ref(props.panel.credentials_title)
const credTitleSaving = ref(false)

function cancelCredTitleEdit() {
  localCredentialsTitle.value = props.panel.credentials_title
  isEditingCredTitle.value = false
}
async function saveCredTitleEdit() {
  credTitleSaving.value = true
  try {
    await api(`/events/${props.eventId}/settings`, {
      method: 'PUT',
      body: { mobile_access_panel: { title: localTitle.value, credentials_title: localCredentialsTitle.value, details: localDetails.value } },
    })
    emit('updated', { panel: { title: localTitle.value, credentials_title: localCredentialsTitle.value, details: localDetails.value } })
    isEditingCredTitle.value = false
  } finally {
    credTitleSaving.value = false
  }
}

// ── Username ────────────────────────────────────────────────
const isEditingUsername = ref(false)
const localUsername = ref(props.username)
const usernameSaving = ref(false)

function startUsernameEdit() {
  localUsername.value = props.username
  isEditingUsername.value = true
}
function cancelUsernameEdit() {
  localUsername.value = props.username
  isEditingUsername.value = false
}
async function saveUsername() {
  usernameSaving.value = true
  try {
    await api(`/events/${props.eventId}/credentials`, {
      method: 'PATCH',
      body: { username: localUsername.value },
    })
    emit('updated', { username: localUsername.value })
    isEditingUsername.value = false
  } finally {
    usernameSaving.value = false
  }
}

// ── Access code copy ────────────────────────────────────────
const copied = ref(false)
async function copyCode() {
  try {
    await navigator.clipboard.writeText(props.accessCode)
    copied.value = true
    setTimeout(() => (copied.value = false), 1500)
  } catch { /* */ }
}

// Watch prop changes (parent reload)
watch(() => props.panel, p => {
  localTitle.value = p.title
  localCredentialsTitle.value = p.credentials_title
  localDetails.value = p.details ?? ''
})
watch(() => props.username, v => { localUsername.value = v })
</script>

<template>
  <div class="flex flex-col gap-4">

    <!-- ── Mobile access info card ───────────────────────── -->
    <div class="card mb-0! order-2">
      <!-- Card header row -->
      <div class="flex items-center justify-between gap-3 mb-3">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-9 h-9 rounded-xl bg-brand-soft grid place-items-center shrink-0">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
              <path d="M17 2H7c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
              <path d="M12 18h.01"/>
            </svg>
          </div>
          <!-- Editable title -->
          <input
            v-if="isEditingPanel"
            v-model="localTitle"
            class="flex-1 font-semibold text-ink text-[1.02rem] bg-transparent border-b border-brand px-0 py-0.5 my-0 rounded-none focus:outline-none focus:border-brand"
            style="box-shadow:none"
            @keyup.enter="savePanelEdit"
            @keyup.esc="cancelPanelEdit"
          />
          <h2 v-else class="mb-0!">{{ localTitle }}</h2>
        </div>
        <!-- Edit / Save / Cancel -->
        <div class="flex items-center gap-1.5 shrink-0">
          <template v-if="isEditingPanel">
            <button
              class="w-7 h-7 rounded-lg bg-brand grid place-items-center text-white cursor-pointer transition-opacity"
              :class="panelSaving ? 'opacity-50' : ''"
              :disabled="panelSaving"
              @click="savePanelEdit"
            >
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button
              class="w-7 h-7 rounded-lg bg-[#fee2e2] grid place-items-center text-[#dc2626] cursor-pointer"
              @click="cancelPanelEdit"
            >
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
          </template>
          <button v-else class="w-7 h-7 rounded-lg hover:bg-[#f6f7f9] grid place-items-center text-faint cursor-pointer transition-colors hover:text-brand" @click="startPanelEdit">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
          </button>
        </div>
      </div>

      <!-- Description: view mode -->
      <div
        v-if="!isEditingPanel"
        class="text-muted text-[.85rem] leading-relaxed max-h-56 overflow-y-auto"
        v-html="(localDetails || DEFAULT_DETAILS).replace(/\n/g, '<br>')"
      />

      <!-- Description: edit mode -->
      <textarea
        v-else
        v-model="localDetails"
        rows="7"
        class="w-full mt-1 mb-2 text-[.85rem] text-ink bg-[#f8f9fb] border border-line rounded-xl px-3.5 py-3 resize-none leading-relaxed focus:border-brand"
        style="box-shadow:none"
        placeholder="Describe how to access the mobile app..."
      />
    </div>

    <!-- ── Credentials card ───────────────────────────────── -->
    <div class="card mb-0! order-1">
      <!-- Credentials header -->
      <div class="flex items-center justify-between gap-3 mb-4">
        <input
          v-if="isEditingCredTitle"
          v-model="localCredentialsTitle"
          class="flex-1 font-semibold text-ink text-[1.02rem] bg-transparent border-b border-brand px-0 py-0.5 my-0 rounded-none focus:outline-none"
          style="box-shadow:none"
          @keyup.enter="saveCredTitleEdit"
          @keyup.esc="cancelCredTitleEdit"
        />
        <h2 v-else class="mb-0!">{{ localCredentialsTitle }}</h2>

        <div class="flex items-center gap-1.5 shrink-0">
          <template v-if="isEditingCredTitle">
            <button
              class="w-7 h-7 rounded-lg bg-brand grid place-items-center text-white cursor-pointer transition-opacity"
              :class="credTitleSaving ? 'opacity-50' : ''"
              :disabled="credTitleSaving"
              @click="saveCredTitleEdit"
            >
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button class="w-7 h-7 rounded-lg bg-[#fee2e2] grid place-items-center text-[#dc2626] cursor-pointer" @click="cancelCredTitleEdit">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
          </template>
          <button v-else class="w-7 h-7 rounded-lg hover:bg-[#f6f7f9] grid place-items-center text-faint cursor-pointer transition-colors hover:text-brand" @click="isEditingCredTitle = true">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
          </button>
        </div>
      </div>

      <div class="flex flex-col gap-3.5">
        <!-- Username -->
        <div>
          <label class="block mb-1.5">Username</label>
          <!-- View mode -->
          <div v-if="!isEditingUsername" class="flex items-center gap-2.5 px-3.25 py-2.5 bg-[#f8f9fb] border border-line rounded-[11px]">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-faint shrink-0">
              <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
            <span class="flex-1 text-[.9rem] text-ink font-[450] select-all">{{ localUsername }}</span>
            <button class="w-6 h-6 rounded-lg hover:bg-white grid place-items-center text-faint cursor-pointer transition-colors hover:text-brand" @click="startUsernameEdit">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
              </svg>
            </button>
          </div>
          <!-- Edit mode -->
          <div v-else class="flex items-center gap-2">
            <input
              v-model="localUsername"
              class="flex-1 my-0"
              placeholder="Enter username"
              @keyup.enter="saveUsername"
              @keyup.esc="cancelUsernameEdit"
            />
            <button
              class="shrink-0 w-9 h-9 rounded-[10px] bg-brand grid place-items-center text-white cursor-pointer transition-opacity"
              :class="usernameSaving ? 'opacity-50' : ''"
              :disabled="usernameSaving"
              @click="saveUsername"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button class="shrink-0 w-9 h-9 rounded-[10px] bg-[#fee2e2] grid place-items-center text-[#dc2626] cursor-pointer" @click="cancelUsernameEdit">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
          </div>
        </div>

        <!-- Access code (read-only + copy) -->
        <div>
          <label class="block mb-1.5">Access Code</label>
          <div class="flex items-center gap-2">
            <div class="flex-1 flex items-center gap-2.5 px-3.25 py-2.5 bg-[#f8f9fb] border border-line rounded-[11px] min-w-0">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-faint shrink-0">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
              </svg>
              <span class="text-[.9rem] text-ink font-mono tracking-widest truncate select-all">{{ accessCode }}</span>
            </div>
            <button
              class="shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-[11px] text-[.83rem] font-semibold border transition-all duration-150 cursor-pointer"
              :class="copied
                ? 'bg-[#f0fdf4] border-[#86efac] text-[#16a34a]'
                : 'bg-white border-line text-ink hover:bg-[#f5f6f8] hover:border-[#c5c9d4]'"
              @click="copyCode"
            >
              <svg v-if="copied" width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <svg v-else width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
              </svg>
              {{ copied ? 'Copied' : 'Copy' }}
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>
