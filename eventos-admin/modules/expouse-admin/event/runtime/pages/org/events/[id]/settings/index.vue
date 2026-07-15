<script setup lang="ts">
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string
const { public: { apiBase } } = useRuntimeConfig()

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

// An organizer may plug in their own OAuth app per social channel instead of
// the platform's; the secret is write-only (see hasSecret) so it's never
// pre-filled — a blank value on save just keeps whatever is already stored.
const SOCIAL_KEYS = CHANNELS.filter(c => c.social).map(c => c.key)
const socialCreds = reactive<Record<string, { client_id: string, client_secret: string }>>(
  Object.fromEntries(SOCIAL_KEYS.map(k => [k, { client_id: '', client_secret: '' }])),
)
const hasSecret = reactive<Record<string, boolean>>(Object.fromEntries(SOCIAL_KEYS.map(k => [k, false])))

/** The redirect URI to whitelist in the provider's own OAuth app console. */
function redirectUri(provider: string) {
  return `${apiBase}/auth/social/${provider}/callback`
}

async function copy(text: string) {
  try { await navigator.clipboard.writeText(text); toast.success('Copied') }
  catch { toast.error('Copy failed') }
}

async function load() {
  try {
    const s = (await api<any>(`/events/${id}/settings`)).data
    const m = s.login?.methods || {}
    for (const c of CHANNELS) methods[c.key] = c.key === 'signup' ? m.signup !== false : !!m[c.key]
    requireLogin.value = s.login?.require_login !== false
    onboarding.value = !!s.login?.onboarding
    // `social_available` is computed by the API from either the organizer's own
    // credentials or the platform's, so we can flag providers with no app yet.
    available.value = s.login?.social_available || {}

    const sc = s.login?.social_credentials || {}
    for (const key of SOCIAL_KEYS) {
      socialCreds[key].client_id = sc[key]?.client_id || ''
      socialCreds[key].client_secret = ''
      hasSecret[key] = !!sc[key]?.has_client_secret
    }
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not load the login settings.')
  }
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
          social_credentials: Object.fromEntries(
            SOCIAL_KEYS.map(key => [key, {
              client_id: socialCreds[key].client_id.trim(),
              client_secret: socialCreds[key].client_secret,
            }]),
          ),
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
  loadAdmins()
})
</script>

<template>
  <div class="max-w-180">
    <div class="mb-6">
      <h1 class="text-[1.35rem] font-bold text-ink mb-0.5">Login Setup</h1>
      <p class="text-muted text-[.88rem]">Login setup will appear at the event login page.</p>
    </div>

    <div class="card p-0">
      <!-- Require login -->
      <div class="flex items-start justify-between gap-6 p-6 border-b border-line">
        <div>
          <p class="font-semibold text-[1rem] text-ink mb-1">Require login on app start?</p>
          <p class="text-[.85rem] text-muted m-0">Require login setup will appear at the event login page.</p>
        </div>
        <button type="button" class="toggle" :class="{ on: requireLogin }" @click="requireLogin = !requireLogin">
          <i />
        </button>
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
          no OAuth app configured yet — plug in your own app below, or ask a platform administrator to add one.
        </p>

        <!-- Per-channel OAuth app: an organizer's own credentials, used instead of
             the platform's when present. -->
        <div
          v-for="c in CHANNELS.filter(c => c.social && methods[c.key])" :key="c.key"
          class="mt-4 border border-line rounded-xl p-4"
        >
          <p class="font-semibold text-[.88rem] text-ink mb-3">{{ c.label }} app credentials</p>
          <div class="grid sm:grid-cols-2 gap-3">
            <AppInput v-model="socialCreds[c.key].client_id" label="Client ID" placeholder="App client ID" />
            <AppInput
              v-model="socialCreds[c.key].client_secret"
              type="password"
              label="Client Secret"
              :placeholder="hasSecret[c.key] ? '•••••• (leave blank to keep)' : 'App client secret'"
              :hint="hasSecret[c.key] ? 'A secret is already saved. Leave blank to keep it.' : undefined"
            />
          </div>
          <p class="text-[.8rem] text-muted mt-3 mb-0">
            Leave both blank to use the platform's own {{ c.label }} app, if one is configured. To use your own,
            register an app with {{ c.label }} and whitelist this redirect URI:
            <code class="bg-faint px-1.5 py-0.5 rounded font-mono text-[.78rem] break-all">{{ redirectUri(c.key) }}</code>
            <button type="button" class="text-[#6352e7] font-medium hover:underline ml-1" @click="copy(redirectUri(c.key))">Copy</button>
          </p>
        </div>
      </div>

      <!-- Event admins -->
      <div class="p-6 border-b border-line">
        <div class="flex items-center justify-between gap-4 bg-faint rounded-xl p-4 mb-4">
          <div>
            <p class="font-semibold text-[.95rem] text-ink mb-0.5">Add event admin</p>
            <p class="text-[.83rem] text-muted m-0">Users added to the web app are provided web app access.</p>
          </div>
          <button class="btn shrink-0" @click="addOpen = true">+ Add User</button>
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
        <button type="button" class="toggle" :class="{ on: onboarding }" @click="onboarding = !onboarding">
          <i />
        </button>
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

<style scoped>
/* Standalone on/off toggle (no label), for the Require-login and Onboarding rows. */
.toggle {
  position: relative; width: 44px; height: 24px; flex: 0 0 auto; border: 0; border-radius: 999px;
  background: #cdd2dc; cursor: pointer; transition: background .15s;
}
.toggle.on { background: var(--brand); }
.toggle i {
  position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; border-radius: 50%;
  background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.2); transition: transform .15s;
}
.toggle.on i { transform: translateX(20px); }
</style>
