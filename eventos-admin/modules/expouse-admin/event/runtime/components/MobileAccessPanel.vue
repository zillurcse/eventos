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

const DEFAULT_DETAILS = `After downloading the mobile app, use the provided Username and Access Code to log in with Admin Access and start capturing leads instantly.\n\nExhibitor Admins will be able to view all leads captured by their respective teams, along with full insights, scanning activity, statistics, and lead performance.\n\nTeam members can simply enter their name and the same Access Code to start capturing visitors instantly. Each team member will only be able to view and manage the leads they captured.\n\nAfter scanning each lead, a pop-up will appear allowing you to add a comment or rate them from 1–5.`

// ── Mobile access description ───────────────────────────────
const localDetails = ref(props.panel.details ?? DEFAULT_DETAILS)
const panelSaving = ref(false)

function cancelPanelEdit() {
  localDetails.value = props.panel.details ?? DEFAULT_DETAILS
}
async function savePanelEdit() {
  panelSaving.value = true
  try {
    await api(`/events/${props.eventId}/settings`, {
      method: 'PUT',
      body: { mobile_access_panel: { title: props.panel.title, credentials_title: props.panel.credentials_title, details: localDetails.value } },
    })
    emit('updated', { panel: { title: props.panel.title, credentials_title: props.panel.credentials_title, details: localDetails.value } })
  } finally {
    panelSaving.value = false
  }
}

// ── Username ────────────────────────────────────────────────
const localUsername = ref(props.username)
const usernameSaving = ref(false)

function cancelUsernameEdit() {
  localUsername.value = props.username
}
async function saveUsername() {
  usernameSaving.value = true
  try {
    await api(`/events/${props.eventId}/credentials`, {
      method: 'PATCH',
      body: { username: localUsername.value },
    })
    emit('updated', { username: localUsername.value })
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
  localDetails.value = p.details ?? DEFAULT_DETAILS
})
watch(() => props.username, v => { localUsername.value = v })
</script>

<template>
  <div class="flex flex-col gap-4">

    <!-- ── Credentials card ───────────────────────────────── -->
    <div class="card mb-0! order-1">
      <h2 class="mb-0! text-[1.05rem]">{{ panel.credentials_title }}</h2>
      <p class="text-muted text-[.83rem] mb-4">Login details exhibitors use to access the mobile app.</p>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Username -->
        <div>
          <label class="block mb-1.5">Edit User Name</label>
          <input v-model="localUsername" class="w-full my-0" placeholder="Enter username" />
        </div>

        <!-- Access code (read-only + copy) -->
        <div>
          <label class="block mb-1.5">Access Code</label>
          <div class="flex items-center gap-2.5 px-3.25 py-2.5 bg-[#f8f9fb] border border-line rounded-[11px] min-w-0">
            <span class="flex-1 text-[.9rem] text-ink font-mono tracking-widest truncate select-all">{{ accessCode }}</span>
            <button
              class="shrink-0 w-7 h-7 rounded-lg grid place-items-center cursor-pointer transition-colors"
              :class="copied ? 'text-[#16a34a]' : 'text-faint hover:bg-white hover:text-brand'"
              :title="copied ? 'Copied!' : 'Copy access code'"
              @click="copyCode"
            >
              <svg v-if="copied" width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
              </svg>
            </button>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-4 mt-4">
        <button
          class="inline-flex items-center px-4 py-2.5 rounded-lg max-h-10 bg-brand text-white font-semibold text-[.88rem] cursor-pointer transition-opacity"
          :class="usernameSaving ? 'opacity-50' : ''"
          :disabled="usernameSaving"
          @click="saveUsername"
        >
          Save
        </button>
        <button
          class="inline-flex items-center px-3 py-2.5 rounded-lg max-h-10 bg-[#F0EEFD] text-brand-dark font-semibold text-[.88rem] cursor-pointer transition-colors hover:bg-[#e0e4ff]"
          @click="cancelUsernameEdit"
        >
          Cancel
        </button>
      </div>
    </div>

    <!-- ── Mobile access info card ───────────────────────── -->
    <div class="card mb-0! order-2">
      <h2 class="mb-0! text-[1.05rem]">{{ panel.title }}</h2>
      <p class="text-muted text-[.83rem] mb-4">Shown to exhibitors alongside their login credentials.</p>

      <textarea
        v-model="localDetails"
        rows="5"
        class="w-full text-[.85rem] max-h-[120px] text-ink bg-white border border-line rounded-lg p-3 resize-none leading-relaxed focus:border-brand"
        style="box-shadow:none"
        placeholder="Let's write an awesome story!"
      />

      <div class="flex items-center gap-4 mt-4">
        <button
          class="inline-flex items-center px-4 py-2.5 rounded-lg max-h-10 bg-brand text-white font-semibold text-[.88rem] cursor-pointer transition-opacity"
          :class="panelSaving ? 'opacity-50' : ''"
          :disabled="panelSaving"
          @click="savePanelEdit"
        >
          Save
        </button>
        <button
          class="inline-flex items-center px-4 py-2.5 rounded-lg max-h-10 bg-[#F0EEFD] text-brand-dark font-semibold text-[.88rem] cursor-pointer transition-colors hover:bg-[#e0e4ff]"
          @click="cancelPanelEdit"
        >
          Cancel
        </button>
      </div>
    </div>

  </div>
</template>
