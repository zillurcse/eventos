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

          <!-- Social sign-in — only on the email step, only the channels the
               organizer enabled, and only providers this platform actually has
               an OAuth app for. -->
          <template v-if="step === 'email' && socialChannels.length">
            <p class="login-with">Login with</p>
            <div class="social">
              <button v-for="s in socialChannels" :key="s.key" type="button" class="social-btn" :class="s.key"
                :title="s.label" @click="signInWith(s.key)">
                <span v-if="s.key === 'google'">G</span>
                <span v-else-if="s.key === 'facebook'">f</span>
                <span v-else>in</span>
              </button>
            </div>
          </template>

          <div class="divider">
            <span>{{ step === 'register' ? 'create your account to join' : 'or enter your email to login/ signup'
              }}</span>
          </div>

          <!-- OTP: enter the code we emailed -->
          <form v-if="step === 'otp'" @submit.prevent="onVerifyOtp()">
            <p v-if="otpInfo" class="muted small otp-note">{{ otpInfo }}</p>
            <label class="field">
              <span class="icon">&#128273;</span>
              <input v-model="otpCode" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                placeholder="Enter 6-digit code" />
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
                <span class="icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="17" viewBox="0 0 14 17" fill="none">
                    <path d="M12.4177 17.0002C12.0509 17.0002 11.7537 16.7029 11.7537 16.3362C11.7537 13.7365 9.63862 11.6214 7.03891 11.6214H6.04284C3.44312 11.6214 1.3281 13.7365 1.3281 16.3362C1.3281 16.7029 1.0308 17.0002 0.664048 17.0002C0.297294 17.0002 0 16.7029 0 16.3362C0 13.0041 2.71081 10.2933 6.04284 10.2933H7.03891C10.3709 10.2933 13.0817 13.0041 13.0817 16.3362C13.0817 16.7029 12.7845 17.0002 12.4177 17.0002Z" fill="#C4C4C4"/>
                    <path d="M6.47451 8.96465C4.00296 8.96465 1.99219 6.95388 1.99219 4.48232C1.99219 2.01077 4.00296 0 6.47451 0C8.94606 0 10.9568 2.01077 10.9568 4.48232C10.9568 6.95388 8.94606 8.96465 6.47451 8.96465ZM6.47451 1.3281C4.73527 1.3281 3.32028 2.74308 3.32028 4.48232C3.32028 6.22157 4.73527 7.63655 6.47451 7.63655C8.21375 7.63655 9.62874 6.22157 9.62874 4.48232C9.62874 2.74308 8.21375 1.3281 6.47451 1.3281Z" fill="#C4C4C4"/>
                  </svg>
                </span>
                <input v-model="email" type="email" placeholder="Enter email address" autocomplete="username"
                  :disabled="step === 'password'" />
              </label>

              <label v-if="step === 'password'" class="field field-pass">
                <span class="icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="10" height="14" viewBox="0 0 10 14" fill="none">
                    <path d="M8.75 4.66667H8.125V3.33333C8.125 1.49333 6.725 0 5 0C3.275 0 1.875 1.49333 1.875 3.33333H3.125C3.125 2.22667 3.9625 1.33333 5 1.33333C6.0375 1.33333 6.875 2.22667 6.875 3.33333V4.66667H1.25C0.5625 4.66667 0 5.26667 0 6V12.6667C0 13.4 0.5625 14 1.25 14H8.75C9.4375 14 10 13.4 10 12.6667V6C10 5.26667 9.4375 4.66667 8.75 4.66667ZM8.75 12.6667H1.25V6H8.75V12.6667ZM5 10.6667C5.6875 10.6667 6.25 10.0667 6.25 9.33333C6.25 8.6 5.6875 8 5 8C4.3125 8 3.75 8.6 3.75 9.33333C3.75 10.0667 4.3125 10.6667 5 10.6667Z" fill="#C4C4C4"/>
                  </svg>
                </span>
                <input v-model="password" type="password" placeholder="Enter password"
                  autocomplete="current-password" />
              </label>

              <div class="forgot">
                <a href="#" @click.prevent="forgot = !forgot">Forget password</a>
              </div>
              <p v-if="forgot" class="muted small">Password resets are handled by the event organizer — please reach out
                to them.</p>

              <label v-if="step === 'email'" class="agree">
                <input type="checkbox" v-model="agreed" />
                <span>I agree to expouse <a href="#" @click.prevent>Terms of Service</a> and <a href="#"
                    @click.prevent>Privacy Policy</a></span>
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
          </template>

          <!-- Registration (signup) — inline, same page -->
          <form v-else @submit.prevent="onRegister()">
            <div v-for="f in regFields" :key="f.key" class="rfield">
              <!-- <label class="rlabel">{{ f.label || f.key }} <span v-if="f.required" class="req">*</span></label> -->

              <textarea v-if="f.type === 'textarea'" v-model="regValues[f.key]" :required="f.required" rows="2" :placeholder="f.label || f.key" />

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

              <input v-else :type="inputType(f.type)" v-model="regValues[f.key]" :required="f.required" :placeholder="f.label || f.key" />
            </div>

            <div class="rfield">
              <!-- <label class="rlabel">Create a password <span class="req">*</span></label> -->
              <input type="password" v-model="regPassword" required minlength="8" autocomplete="new-password"  placeholder="Create a password"/>
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
        <path d="M0 460 C 150 300 300 260 450 340 C 600 420 700 520 800 480 L800 900 L0 900 Z" fill="#6fb8ba"
          opacity="0.85" />
        <circle cx="640" cy="380" r="250" fill="#f0b86e" opacity="0.9" />
      </svg>
    </section>
  </div>
</template>

<style scoped>
.landing {
  display: flex;
  height: 100vh;
  background: #e5e5e5;
}

.panel {
  flex: 0 0 42%;
  max-width: 530px;
  display: flex;
  /* align-items: center; */
  justify-content: center;
  padding: 32px;
  overflow-y: auto;
}

.panel-inner {
  width: 100%;
  max-width: 355px;
  position: relative;
  min-height: 78vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.panel-inner form,
.panel-inner .notfound {
  width: 100%;
  text-align: left;
}

.brand-mark {
  margin-bottom: 10px;
}

.brand-mark img {
  width: 90px;
  height: 90px;
  object-fit: contain;
  border-radius: 5px;
}

.brand-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 90px;
  height: 90px;
  border-radius: 5px;
  color: #fff;
  font-weight: 800;
  letter-spacing: 1px;
  background: var(--brand-primary);
}

.event-name {
  color: var(--brand-primary);
  font-size: 20px;
  line-height: 1.2;
  font-weight: 700;
  margin: 16px 0 28px;
}

.login-with {
  color: #565656;
  font-size: .92rem;
  margin-bottom: 12px;
}

.divider {
  display: flex;
  align-items: center;
  gap: 12px;
  color: #565656;
  font-size: .92rem;
  margin: 24px 0;
  width: 100%;
}

.divider::before,
.divider::after {
  content: '';
  height: 1px;
  background: #cfd0d2;
  flex: 1;
}

.divider span {
  white-space: nowrap;
}

.field {
  display: flex;
  align-items: center;
  border-bottom: 1px solid var(--brand-primary);
  position: relative;
}
.field-pass{
  margin-top: 10px;

}
.field:focus-within {
  border-color: var(--brand-primary);
}

.field .icon {
  color: #9aa0a8;
  padding: 0 8px 0 2px;
  display: flex;
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
}
.field .icon svg{
  width: 13px;
  height: 17px;
}

.field input {
  border: none;
  background: transparent;
  padding: 10px 4px;
  margin: 0;
  border-radius: 0;
  font-size: 14px;
  padding-left: 38px;
}

.field input:focus {
  outline: none;
}

.field input:disabled {
  color: #6b7280;
}

/* Inline registration fields */
.rfield {
  margin-bottom: 12px;
}

.rlabel {
  display: block;
  font-size: .8rem;
  font-weight: 600;
  color: #4b5563;
  margin-bottom: 3px;
}

.req {
  color: #dc2626;
}

.rfield input,
.rfield select,
.rfield textarea {
  width: 100%;
  padding: 7px 14px;
  margin: 0;
  border: none;
  border-bottom: 1px solid var(--brand-primary);
  box-shadow: none;
  border-radius: 0;
  font-size: 14px;
  background: transparent;
}

.rfield input:focus,
.rfield select:focus,
.rfield textarea:focus {
  outline: none;
  border-color: var(--brand-primary);
}

.opts {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.opt {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: .9rem;
}

.opt input {
  width: auto;
}

.forgot {
  text-align: right;
  margin: 10px 0 4px;
  font-size: 14px;
}

.forgot a,
.agree a {
  color: var(--brand-primary);
  font-weight: 600;
}

.small {
  font-size: .8rem;
  margin: 2px 0 0;
}

.agree {
  display: flex;
  gap: 9px;
  align-items:center;
  margin: 16px 0 6px;
  font-size: 14px;
  color: #4b5563;
}

.agree input {
  width: 16px;
  height: 16px;
  margin: 2px 0 0;
  flex: none;
}

.continue {
  margin-top: 18px;
  display: flex;
  align-self: flex-start;
  border: none;
  cursor: pointer;
  background: var(--brand-primary);
  color: #fff;
  font-weight: 400;
  letter-spacing: .4px;
  padding: 8px 30px;
  border-radius: 999px;
  font-size: .9rem;
  margin-left: auto;
}

.continue:disabled {
  opacity: .6;
  cursor: default;
}

.switch {
  margin-top: 14px;
  font-size: .86rem;
}

.switch a {
  color: var(--brand-primary);
}

.switch .sep {
  color: #cbd0d8;
  margin: 0 8px;
}

.otp-note {
  margin-bottom: 14px;
}

.social {
  display: flex;
  justify-content: center;
  gap: 16px;
}

.social-btn {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  border: none;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.3rem;
  font-weight: 700;
  color: #7b8190;
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(0, 0, 0, .16);
  transition: transform .15s, box-shadow .15s;
}

.social-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 10px rgba(0, 0, 0, .22);
}

.social-btn.google {
  color: #ea4335;
}

.social-btn.facebook {
  color: #1877f2;
}

.social-btn.linkedin {
  color: #0a66c2;
}

.error {
  color: #b91c1c;
  font-size: .88rem;
  margin-top: 12px;
}

.powered {
  margin-top: auto;
  padding-top: 28px;
  color: #9aa0a8;
  font-size: .7rem;
  letter-spacing: 1.5px;
}

.powered strong {
  display: block;
  color: #6b7280;
  font-size: 1rem;
  letter-spacing: .5px;
  margin-top: 2px;
}

.notfound {
  margin-top: 40px;
}

.art {
  flex: 1;
  background: #eef0f2;
  overflow: hidden;
}

.art svg {
  width: 100%;
  height: 100%;
  display: block;
}

@media (max-width: 760px) {
  .art {
    display: none;
  }

  .panel {
    flex: 1;
    max-width: none;
  }
}
</style>
