<script setup lang="ts">
definePageMeta({ layout: false })

interface Field {
  key: string
  label: string | null
  help_text?: string | null
  type: string
  required: boolean
  options?: Array<{ label: string, value: string }>
}

const auth = useAuthStore()
const site = useSiteStore()
const api = useApi()

const step = ref<'email' | 'password' | 'register' | 'otp'>('email')
const email = ref('')
const password = ref('')
const agreed = ref(false)
const forgot = ref(false)
const error = ref('')
const loading = ref(false)

// OTP sign-in state.
const otpCode = ref('')
const otpInfo = ref('')

/**
 * Which sign-in doors the organizer opened (Settings › Access authentication).
 * The API already hides a social channel with no OAuth app, so a `true` here is
 * a channel we can actually render.
 */
const channels = computed(() => site.site?.login?.channels ?? { signup: true, otp: false })
const signupOpen = computed(() => channels.value.signup !== false)
const otpEnabled = computed(() => !!channels.value.otp)

const SOCIAL = [
  { key: 'google', label: 'Continue with Google' },
  { key: 'facebook', label: 'Continue with Facebook' },
  { key: 'linkedin', label: 'Continue with LinkedIn' },
] as const
const socialChannels = computed(() => SOCIAL.filter(s => channels.value[s.key]))

// Registration (inline signup) state — shown on the same page, no navigation.
const regFields = ref<Field[]>([])
const regValues = reactive<Record<string, any>>({})
const regPassword = ref('')

const textTypes = new Set(['text', 'email', 'tel', 'phone', 'number', 'url', 'date'])
function inputType(t: string) { return t === 'phone' ? 'tel' : (textTypes.has(t) ? t : 'text') }

// Fallback fields when the organizer hasn't built a form (mirrors the API default).
const DEFAULT_FIELDS: Field[] = [
  { key: 'first_name', label: 'First name', type: 'text', required: true },
  { key: 'last_name', label: 'Last name', type: 'text', required: true },
  { key: 'email', label: 'Email', type: 'email', required: true },
  { key: 'phone', label: 'Phone', type: 'tel', required: false },
  { key: 'company', label: 'Company', type: 'text', required: false },
  { key: 'job_title', label: 'Job title', type: 'text', required: false },
]

onMounted(async () => {
  auth.init()

  // Social sign-in comes home with the token in the URL fragment (never a query
  // string, so it stays out of server logs). Adopt it and scrub the address bar.
  if (import.meta.client) {
    const frag = new URLSearchParams(window.location.hash.slice(1))
    const token = frag.get('token')
    if (token) {
      history.replaceState(null, '', window.location.pathname + window.location.search)
      try {
        await auth.adoptToken(token)
      } catch { /* stale token → fall through to the normal login screen */ }
    }

    // A social sign-in that failed sends back an ?error= we can explain.
    const err = new URLSearchParams(window.location.search).get('error')
    if (err) error.value = socialError(err)
  }

  if (auth.isAuthed) navigateTo('/reception')
})

function socialError(code: string): string {
  switch (code) {
    case 'social_no_email': return 'That account has no email we can use. Try another sign-in method.'
    case 'signup_closed': return 'This event is invite-only — ask the organizer to add you.'
    case 'account_disabled': return 'This account has been disabled.'
    default: return 'We could not complete that sign-in. Please try again.'
  }
}

/** Send the browser off to the provider; it returns to this page with a token. */
function signInWith(provider: string) {
  const { public: { apiBase } } = useRuntimeConfig()
  const sub = useEventSubdomain()
  const url = `${apiBase}/auth/social/${provider}/redirect?subdomain=${encodeURIComponent(sub || '')}`
  window.location.href = url
}

const initials = computed(() =>
  (site.name || 'EV').trim().slice(0, 4).toUpperCase())

async function onContinue() {
  error.value = ''
  if (!agreed.value) { error.value = 'Please agree to the Terms of Service and Privacy Policy.'; return }
  if (!email.value.includes('@')) { error.value = 'Enter a valid email address.'; return }

  loading.value = true
  try {
    const { exists, has_password } = await auth.checkEmail(email.value)

    if (exists && has_password && signupOpen.value) {
      // Known account, password door — only offered while Signup stays open;
      // closing Signup retires password sign-in in favour of OTP/social.
      step.value = 'password'
    } else if (otpEnabled.value) {
      // OTP is a self-sufficient door: it logs in an existing account (with or
      // without a password) and self-enrols a brand-new email, independent of
      // whatever Signup is set to.
      await sendOtp()
    } else if (!exists && signupOpen.value) {
      await startRegister()            // unknown email → show the signup form inline
    } else if (exists && has_password) {
      // Signup is closed and there's no OTP to fall back on.
      error.value = 'Password sign-in is currently disabled for this event. Contact the organizer.'
    } else if (!exists) {
      // Invite-only event and we don't know this address.
      error.value = 'This event is invite-only — ask the organizer for access.'
    } else {
      // Known passwordless account but OTP is off — nothing we can offer.
      error.value = 'This account can only sign in with a method that’s currently disabled. Contact the organizer.'
    }
  } catch (e: any) {
    error.value = e?.data?.message || 'Something went wrong. Please try again.'
  } finally {
    loading.value = false
  }
}

// ── OTP ─────────────────────────────────────────────────────────────────────
async function sendOtp() {
  error.value = ''
  if (!agreed.value) { error.value = 'Please agree to the Terms of Service and Privacy Policy.'; return }
  if (!email.value.includes('@')) { error.value = 'Enter a valid email address.'; return }

  loading.value = true
  try {
    await auth.requestOtp(email.value)
    otpInfo.value = `We’ve emailed a 6-digit code to ${email.value}. It expires in 10 minutes.`
    step.value = 'otp'
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not send a code. Please try again.'
  } finally {
    loading.value = false
  }
}

async function onVerifyOtp() {
  error.value = ''
  if (otpCode.value.trim().length < 6) { error.value = 'Enter the 6-digit code from your email.'; return }

  loading.value = true
  try {
    await auth.verifyOtp(email.value, otpCode.value.trim())
    navigateTo('/reception')
  } catch (e: any) {
    error.value = e?.data?.message || 'That code is not right.'
  } finally {
    loading.value = false
  }
}

async function startRegister() {
  // Signup collects whatever the organizer designed in admin › Event Settings ›
  // Profile › Attendee, limited to the fields flagged "Add field to user
  // registration". Older events with only a standalone registration form fall
  // back to that, and an event with neither gets the built-in defaults.
  let fields: Field[] = site.registrationFields.map(f => ({
    key: f.key,
    label: f.label,
    help_text: f.help_text,
    type: f.type,
    required: f.required,
    options: (f.options || []).map(o => ({ label: o.label, value: o.value ?? o.label })),
  }))

  if (!fields.length && site.registrationFormUuid) {
    try {
      const res = await api<{ data: any }>(`/forms/${site.registrationFormUuid}`)
      if (Array.isArray(res.data?.fields) && res.data.fields.length) fields = res.data.fields
    } catch { /* fall back to the default field set */ }
  }
  if (!fields.length) fields = DEFAULT_FIELDS

  regFields.value = fields
  for (const f of fields) regValues[f.key] = ['checkbox', 'multiselect'].includes(f.type) ? [] : ''
  const ef = fields.find(f => f.type === 'email' || f.key === 'email')
  if (ef) regValues[ef.key] = email.value     // prefill the email they just typed
  step.value = 'register'
}

async function onSignIn() {
  error.value = ''
  loading.value = true
  try {
    await auth.login(email.value, password.value)
    navigateTo('/reception')
  } catch (e: any) {
    error.value = e?.data?.message || 'Those credentials do not match our records.'
  } finally {
    loading.value = false
  }
}

async function onRegister() {
  error.value = ''
  loading.value = true
  try {
    await api(`/events/${site.event!.uuid}/register`, {
      method: 'POST',
      body: { ...toRaw(regValues), password: regPassword.value },
    })
    // Registration created a login — sign straight in.
    const ef = regFields.value.find(f => f.type === 'email' || f.key === 'email')
    const em = ef ? regValues[ef.key] : email.value
    await auth.login(em, regPassword.value)
    navigateTo('/reception')
  } catch (e: any) {
    error.value = e?.data?.message || 'We could not complete your registration. Please check the form and try again.'
  } finally {
    loading.value = false
  }
}

function backToEmail() {
  step.value = 'email'
  password.value = ''
  regPassword.value = ''
  otpCode.value = ''
  otpInfo.value = ''
  error.value = ''
}
</script>

<template>
  <div class="landing">
    <!-- Left: branded auth panel -->
    <section class="panel">
      <div class="panel-inner">
        <div v-if="site.notFound" class="notfound">
          <h1>Event not found</h1>
          <p class="muted">This address doesn't match any published event. Check the link, or contact the organizer.</p>
        </div>

        <template v-else>
          <div class="brand-mark">
            <img v-if="site.logoUrl" :src="site.logoUrl" :alt="site.name" />
            <span v-else class="brand-badge">{{ initials }}</span>
          </div>
          <h1 class="event-name">{{ site.name }}</h1>

          <div class="divider">
            <span>{{ step === 'register' ? 'create your account to join' : 'enter your email to login/ signup' }}</span>
          </div>

          <!-- OTP: enter the code we emailed -->
          <form v-if="step === 'otp'" @submit.prevent="onVerifyOtp()">
            <p v-if="otpInfo" class="muted small otp-note">{{ otpInfo }}</p>
            <label class="field">
              <span class="icon">&#128273;</span>
              <input v-model="otpCode" type="text" inputmode="numeric" autocomplete="one-time-code"
                     maxlength="6" placeholder="Enter 6-digit code" />
            </label>

            <p v-if="error" class="error">{{ error }}</p>

            <button class="continue" type="submit" :disabled="loading">
              {{ loading ? 'PLEASE WAIT…' : 'VERIFY & SIGN IN' }}
            </button>

            <p class="switch">
              <a href="#" @click.prevent="sendOtp()">Resend code</a>
              <span class="sep">·</span>
              <a href="#" @click.prevent="backToEmail()">← Use a different email</a>
            </p>
          </form>

          <!-- Email / password (login) + social — grouped under one branch so the
               registration form's v-else below only ever fires on step 'register',
               never while step is 'otp' or 'password'. -->
          <template v-else-if="step !== 'register'">
            <form @submit.prevent="step === 'email' ? onContinue() : onSignIn()">
              <label class="field">
                <span class="icon">&#128100;</span>
                <input v-model="email" type="email" placeholder="Enter email address"
                       autocomplete="username" :disabled="step === 'password'" />
              </label>

              <label v-if="step === 'password'" class="field">
                <span class="icon">&#128274;</span>
                <input v-model="password" type="password" placeholder="Enter password" autocomplete="current-password" />
              </label>

              <div class="forgot">
                <a href="#" @click.prevent="forgot = !forgot">Forget password</a>
              </div>
              <p v-if="forgot" class="muted small">Password resets are handled by the event organizer — please reach out to them.</p>

              <label v-if="step === 'email'" class="agree">
                <input type="checkbox" v-model="agreed" />
                <span>I agree to expouse <a href="#" @click.prevent>Terms of Service</a> and <a href="#" @click.prevent>Privacy Policy</a></span>
              </label>

              <p v-if="error" class="error">{{ error }}</p>

              <button class="continue" type="submit" :disabled="loading">
                {{ loading ? 'PLEASE WAIT…' : (step === 'email' ? 'CONTINUE' : 'SIGN IN') }}
              </button>

              <!-- Sign in with an emailed code instead (Access authentication › OTP). -->
              <p v-if="step === 'email' && otpEnabled" class="switch">
                <a href="#" @click.prevent="sendOtp()">Email me a sign-in code instead</a>
              </p>

              <p v-if="step === 'password'" class="switch">
                <a href="#" @click.prevent="backToEmail()">← Use a different email</a>
              </p>
            </form>

            <!-- Social sign-in — only on the email step, only the channels the
                 organizer enabled, and only providers this platform actually has
                 an OAuth app for. -->
            <template v-if="step === 'email' && socialChannels.length">
              <div class="or"><span>or</span></div>
              <div class="social">
                <button
                  v-for="s in socialChannels" :key="s.key"
                  type="button" class="social-btn" :class="s.key"
                  @click="signInWith(s.key)"
                >{{ s.label }}</button>
              </div>
            </template>
          </template>

          <!-- Registration (signup) — inline, same page -->
          <form v-else @submit.prevent="onRegister()">
            <div v-for="f in regFields" :key="f.key" class="rfield">
              <label class="rlabel">{{ f.label || f.key }} <span v-if="f.required" class="req">*</span></label>

              <textarea v-if="f.type === 'textarea'" v-model="regValues[f.key]" :required="f.required" rows="2" />

              <select v-else-if="f.type === 'select'" v-model="regValues[f.key]" :required="f.required">
                <option value="" disabled>Select…</option>
                <option v-for="o in f.options" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>

              <div v-else-if="f.type === 'radio'" class="opts">
                <label v-for="o in f.options" :key="o.value" class="opt">
                  <input type="radio" :value="o.value" v-model="regValues[f.key]" /> {{ o.label }}
                </label>
              </div>

              <div v-else-if="['checkbox', 'multiselect'].includes(f.type)" class="opts">
                <label v-for="o in f.options" :key="o.value" class="opt">
                  <input type="checkbox" :value="o.value" v-model="regValues[f.key]" /> {{ o.label }}
                </label>
              </div>

              <input v-else :type="inputType(f.type)" v-model="regValues[f.key]" :required="f.required" />
            </div>

            <div class="rfield">
              <label class="rlabel">Create a password <span class="req">*</span></label>
              <input type="password" v-model="regPassword" required minlength="8" autocomplete="new-password" />
            </div>

            <p v-if="error" class="error">{{ error }}</p>

            <button class="continue" type="submit" :disabled="loading">
              {{ loading ? 'PLEASE WAIT…' : 'REGISTER' }}
            </button>

            <p class="switch"><a href="#" @click.prevent="backToEmail()">← Use a different email</a></p>
          </form>
        </template>

        <div class="powered">
          <span>POWERED BY</span>
          <strong>{{ site.poweredBy }}</strong>
        </div>
      </div>
    </section>

    <!-- Right: decorative artwork -->
    <section class="art" aria-hidden="true">
      <svg viewBox="0 0 800 900" preserveAspectRatio="xMidYMid slice">
        <circle cx="560" cy="300" r="170" fill="var(--brand-accent)" opacity="0.85" />
        <path d="M0 520 C 180 360 360 460 520 540 C 660 610 740 600 800 560 L800 900 L0 900 Z"
              fill="var(--brand-primary)" opacity="0.55" />
        <path d="M0 640 C 200 520 380 600 560 660 C 680 700 760 690 800 660 L800 900 L0 900 Z"
              fill="var(--brand-primary)" opacity="0.9" />
      </svg>
    </section>
  </div>
</template>

<style scoped>
.landing { display: flex; min-height: 100vh; background: #eceef1; }
.panel { flex: 0 0 42%; max-width: 560px; display: flex; align-items: center; justify-content: center; padding: 32px; overflow-y: auto; }
.panel-inner { width: 100%; max-width: 340px; position: relative; min-height: 78vh; display: flex; flex-direction: column; }

.brand-mark { margin-bottom: 10px; }
.brand-mark img { max-height: 92px; max-width: 200px; object-fit: contain; border-radius: 12px; }
.brand-badge {
  display: inline-flex; align-items: center; justify-content: center;
  width: 92px; height: 92px; border-radius: 14px; color: #fff; font-weight: 800;
  letter-spacing: 1px; background: var(--brand-primary);
}
.event-name { color: var(--brand-primary); font-size: 1.5rem; font-weight: 800; margin: 6px 0 26px; }

.divider { display: flex; align-items: center; gap: 12px; color: #6b7280; font-size: .92rem; margin-bottom: 18px; }
.divider::before, .divider::after { content: ''; height: 1px; background: #d5d8dd; width: 26px; }
.divider span { white-space: nowrap; }

.field { display: flex; align-items: center; border-bottom: 2px solid #cfd3d9; }
.field:focus-within { border-color: var(--brand-primary); }
.field .icon { color: #9aa0a8; font-size: 1rem; padding: 0 8px 0 2px; }
.field input { border: none; background: transparent; padding: 12px 4px; margin: 0; border-radius: 0; }
.field input:focus { outline: none; }
.field input:disabled { color: #6b7280; }

/* Inline registration fields */
.rfield { margin-bottom: 12px; }
.rlabel { display: block; font-size: .8rem; font-weight: 600; color: #4b5563; margin-bottom: 3px; }
.req { color: #dc2626; }
.rfield input, .rfield select, .rfield textarea {
  width: 100%; padding: 9px 10px; margin: 0; border: 1px solid #cfd3d9; border-radius: 8px; font: inherit; background: #fff;
}
.rfield input:focus, .rfield select:focus, .rfield textarea:focus { outline: none; border-color: var(--brand-primary); }
.opts { display: flex; flex-direction: column; gap: 5px; }
.opt { display: flex; align-items: center; gap: 8px; font-size: .9rem; }
.opt input { width: auto; }

.forgot { text-align: right; margin: 10px 0 4px; }
.forgot a, .agree a { color: var(--brand-primary); font-weight: 600; }
.small { font-size: .8rem; margin: 2px 0 0; }

.agree { display: flex; gap: 9px; align-items: flex-start; margin: 16px 0 6px; font-size: .9rem; color: #4b5563; }
.agree input { width: 16px; height: 16px; margin: 2px 0 0; flex: none; }

.continue {
  margin-top: 18px; align-self: flex-start; border: none; cursor: pointer;
  background: var(--brand-primary); color: #fff; font-weight: 700; letter-spacing: .4px;
  padding: 12px 30px; border-radius: 999px; font-size: .9rem;
}
.continue:disabled { opacity: .6; cursor: default; }
.switch { margin-top: 14px; font-size: .86rem; }
.switch a { color: var(--brand-primary); }
.switch .sep { color: #cbd0d8; margin: 0 8px; }

.otp-note { margin-bottom: 14px; }

/* "or" divider between password and social sign-in. */
.or { display: flex; align-items: center; gap: 12px; margin: 20px 0 16px; color: #9aa0a8; font-size: .78rem; }
.or::before, .or::after { content: ''; flex: 1; height: 1px; background: #e5e7eb; }

.social { display: flex; flex-direction: column; gap: 10px; }
.social-btn {
  width: 100%; padding: 12px 16px; border-radius: 10px; border: 1px solid #d7dae1; background: #fff;
  font: inherit; font-size: .9rem; font-weight: 600; color: #334155; cursor: pointer; transition: background .15s, border-color .15s;
}
.social-btn:hover { background: #f8fafc; border-color: #c7ccd5; }
.social-btn.google:hover { border-color: #ea4335; }
.social-btn.facebook:hover { border-color: #1877f2; }
.social-btn.linkedin:hover { border-color: #0a66c2; }

.error { color: #b91c1c; font-size: .88rem; margin-top: 12px; }

.powered { margin-top: auto; padding-top: 28px; color: #9aa0a8; font-size: .7rem; letter-spacing: 1.5px; }
.powered strong { display: block; color: #6b7280; font-size: 1rem; letter-spacing: .5px; margin-top: 2px; }

.notfound { margin-top: 40px; }

.art { flex: 1; background: #e7e9ec; overflow: hidden; }
.art svg { width: 100%; height: 100%; display: block; }

@media (max-width: 760px) {
  .art { display: none; }
  .panel { flex: 1; max-width: none; }
}
</style>
