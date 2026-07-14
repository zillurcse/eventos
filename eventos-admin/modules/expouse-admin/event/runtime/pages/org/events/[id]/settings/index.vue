<script setup lang="ts">
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

/**
 * How attendees get into the event web app.
 *
 * The social channels are the platform's OAuth apps, not this event's — ticking
 * Google here says "offer Google on my login page", and the attendee site only
 * renders a button when the platform actually holds credentials for it. The API
 * reports that back to us in `available`, so we can say so rather than let the
 * organizer switch on a button that would dead-end.
 *
 * Email + password is not a channel: someone who already has an account can
 * always use it. Signup governs whether a *new* email may create one — closing
 * it turns the event into an invite-only list.
 */
const CHANNELS = [
  { key: 'facebook', label: 'Facebook', social: true },
  { key: 'google', label: 'Google', social: true },
  { key: 'linkedin', label: 'Linkedin', social: true },
  { key: 'signup', label: 'Signup', social: false },
  { key: 'otp', label: 'OTP', social: false },
]

interface EventAdmin {
  id: string
  name: string
  email: string
  has_login: boolean
  added_at: string | null
}

const methods = reactive<Record<string, boolean>>({
  facebook: false, google: false, linkedin: false, signup: true, otp: false,
})
const requireLogin = ref(true)
const onboarding = ref(true)

/** Which social providers this installation can actually honour. */
const available = ref<Record<string, boolean>>({})
const saving = ref(false)

async function load() {
  try {
    const s = (await api<any>(`/events/${id}/settings`)).data
    const m = s.login?.methods || {}
    for (const c of CHANNELS) methods[c.key] = c.key === 'signup' ? m.signup !== false : !!m[c.key]
    requireLogin.value = s.login?.require_login !== false
    onboarding.value = !!s.login?.onboarding
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not load the login settings.')
  }
}

/** The public payload tells us which providers have credentials configured. */
async function loadCapabilities() {
  try {
    const site = (await api<any>(`/events/${id}/settings`)).data
    // `social_available` is computed by the API from the platform's OAuth config.
    available.value = site.login?.social_available || {}
  } catch { /* capability is a hint; the checkboxes still work without it */ }
}

async function save() {
  saving.value = true
  try {
    await api(`/events/${id}/settings`, {
      method: 'PUT',
      body: {
        login: {
          methods: { ...methods },
          require_login: requireLogin.value,
          onboarding: onboarding.value,
        },
      },
    })
    toast.success('Login settings saved')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not save the login settings.')
  } finally {
    saving.value = false
  }
}

// ── Event admins ────────────────────────────────────────────────────────────
// "Users added to the web app are provided web app access": an event admin is a
// staff participation — they sign in to the attendee site and can moderate
// sessions (Session::isModeratedBy treats staff as a host).
const admins = ref<EventAdmin[]>([])
const addOpen = ref(false)
const addEmail = ref('')
const addName = ref('')
const adding = ref(false)

async function loadAdmins() {
  try {
    admins.value = (await api<{ data: EventAdmin[] }>(`/events/${id}/admins`)).data
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not load the event admins.')
  }
}

async function addAdmin() {
  const email = addEmail.value.trim()
  if (!email || adding.value) return

  adding.value = true
  try {
    const res = await api<any>(`/events/${id}/admins`, {
      method: 'POST',
      body: { email, name: addName.value.trim() || undefined },
    })
    addOpen.value = false
    addEmail.value = ''
    addName.value = ''
    await loadAdmins()
    toast.success('Event admin added', {
      description: res?.meta?.had_login
        ? 'They can sign in with the password they already have.'
        : 'A 6-digit access code was emailed to them.',
    })
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not add the event admin.')
  } finally {
    adding.value = false
  }
}

async function removeAdmin(a: EventAdmin) {
  if (!confirm(`Remove web app admin access for ${a.email}? They stay registered for the event.`)) return
  try {
    await api(`/events/${id}/admins/${a.id}`, { method: 'DELETE' })
    await loadAdmins()
    toast.success('Admin access removed')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not remove the event admin.')
  }
}

onMounted(() => {
  load()
  loadCapabilities()
  loadAdmins()
})
</script>

<template>
  <div class="max-w-180">
    <div class="mb-6">
      <h1 class="text-[1.35rem] font-bold text-ink mb-0.5">Login Settings</h1>
      <p class="text-muted text-[.88rem]">Control how attendees authenticate and access this event.</p>
    </div>

    <div class="card p-0">
      <!-- Require login -->
      <div class="flex items-start justify-between gap-6 p-6 border-b border-line">
        <div>
          <p class="font-semibold text-[1rem] text-ink mb-1">Require login on app start?</p>
          <p class="text-[.85rem] text-muted m-0">Require login setup will appear at the event login page.</p>
        </div>
        <NavigationToggleSwitch v-model="requireLogin" />
      </div>

      <!-- Access authentication -->
      <div class="p-6 border-b border-line">
        <p class="font-semibold text-[1rem] text-ink mb-1">Access authentication</p>
        <p class="text-[.85rem] text-muted mb-4">
          Choose how users can sign in (e.g. OTP, Signup, social). Unchecking a channel hides it on the event login page.
        </p>

        <div class="flex items-center gap-6 flex-wrap">
          <label
            v-for="c in CHANNELS" :key="c.key"
            class="inline-flex items-center gap-2 cursor-pointer select-none"
            :class="{ 'text-brand': methods[c.key] }"
          >
            <input v-model="methods[c.key]" type="checkbox" class="w-4.5 h-4.5 m-0 accent-brand">
            <span class="text-[.9rem]">{{ c.label }}</span>
          </label>
        </div>

        <!-- A social provider with no OAuth app on this installation would put a
             dead button on the login page — say so rather than let it happen. -->
        <p
          v-if="CHANNELS.some(c => c.social && methods[c.key] && available[c.key] === false)"
          class="text-[.82rem] mt-3 mb-0 px-3 py-2 rounded-lg bg-[#fef3c7] text-[#b45309]"
        >
          {{ CHANNELS.filter(c => c.social && methods[c.key] && available[c.key] === false).map(c => c.label).join(', ') }}
          {{ CHANNELS.filter(c => c.social && methods[c.key] && available[c.key] === false).length > 1 ? 'have' : 'has' }}
          no OAuth app configured on this platform yet, so the button stays hidden on the login page until an
          administrator adds the credentials.
        </p>
      </div>

      <!-- Event admins -->
      <div class="p-6 border-b border-line">
        <div class="flex items-center justify-between gap-4 bg-faint rounded-xl p-4 mb-4">
          <div>
            <p class="font-semibold text-[.95rem] text-ink mb-0.5">Add event admin</p>
            <p class="text-[.83rem] text-muted m-0">Users added to the web app are provided web app access.</p>
          </div>
          <button class="btn shrink-0" @click="addOpen = true">+ ADD USERS</button>
        </div>

        <p v-if="!admins.length" class="text-[.85rem] text-muted m-0">No event admins added yet.</p>

        <div v-else class="flex flex-col gap-2">
          <div
            v-for="a in admins" :key="a.id"
            class="flex items-center gap-3 border border-line rounded-xl px-4 py-3"
          >
            <div class="w-9 h-9 rounded-full bg-brand-soft text-brand grid place-items-center font-bold text-[.78rem] shrink-0 uppercase">
              {{ (a.name || a.email || '?').slice(0, 2) }}
            </div>
            <div class="min-w-0 flex-1">
              <div class="font-semibold text-ink text-[.9rem] truncate">{{ a.name }}</div>
              <div class="muted text-[.8rem] truncate">{{ a.email }}</div>
            </div>
            <span v-if="!a.has_login" class="badge">No login yet</span>
            <button class="btn ghost sm text-[#dc2626]" @click="removeAdmin(a)">Remove</button>
          </div>
        </div>
      </div>

      <!-- Onboarding -->
      <div class="flex items-start justify-between gap-6 p-6">
        <div>
          <p class="font-semibold text-[1rem] text-ink mb-1">Onboarding</p>
          <p class="text-[.85rem] text-muted m-0">
            First-time attendees complete their profile (photo, job title, company, interests) before reaching
            Reception. They can skip it; it's what fills the delegate directory.
          </p>
        </div>
        <NavigationToggleSwitch v-model="onboarding" />
      </div>
    </div>

    <div class="flex justify-end mt-5">
      <button class="btn px-8" :disabled="saving" @click="save">
        {{ saving ? 'SAVING…' : 'SAVE' }}
      </button>
    </div>

    <!-- Add users -->
    <Drawer v-if="addOpen" title="Add event admin" @close="addOpen = false">
      <p class="muted text-[.85rem] mt-0 mb-4">
        They get web app access to this event and can moderate sessions. If they have no login yet, a 6-digit
        access code is emailed to them.
      </p>

      <div class="flex flex-col gap-3">
        <AppInput v-model="addEmail" type="email" label="Email *" placeholder="name@company.com" />
        <AppInput v-model="addName" label="Name" placeholder="Optional" />
      </div>

      <div class="pt-4 mt-2">
        <button class="btn w-full py-3 tracking-widest" :disabled="!addEmail.trim() || adding" @click="addAdmin">
          {{ adding ? 'ADDING…' : 'ADD USER' }}
        </button>
      </div>
    </Drawer>
  </div>
</template>
