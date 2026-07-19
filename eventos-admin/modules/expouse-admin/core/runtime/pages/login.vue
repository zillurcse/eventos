<script setup lang="ts">
definePageMeta({ layout: false })
const auth = useAuthStore()
const email = ref('')
const password = ref('')
const showPassword = ref(false)
const error = ref('')
const notice = ref('')
const loading = ref(false)

async function submit() {
  if (loading.value) return
  loading.value = true
  error.value = ''
  try {
    await auth.login(email.value, password.value)
    navigateTo(auth.home)
  } catch (e: any) {
    error.value = e?.data?.message || 'Those credentials do not match our records.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-page">
    <div class="login-card">
      <!-- Brand -->
      <div class="brand">
        <svg class="brand-mark" viewBox="0 0 44 44" fill="none" aria-hidden="true">
          <rect x="4" y="18" width="4" height="14" rx="2" fill="currentColor" opacity=".55" />
          <rect x="11" y="10" width="4" height="26" rx="2" fill="currentColor" opacity=".8" />
          <circle cx="20" cy="8" r="2.2" fill="currentColor" opacity=".5" />
          <rect x="18" y="14" width="4" height="20" rx="2" fill="currentColor" />
          <rect x="25" y="6" width="4" height="30" rx="2" fill="currentColor" opacity=".85" />
          <circle cx="34" cy="14" r="2.2" fill="currentColor" opacity=".5" />
          <rect x="32" y="20" width="4" height="14" rx="2" fill="currentColor" opacity=".6" />
        </svg>
        <span class="brand-word">EXPOUSE</span>
      </div>

      <form class="fields" @submit.prevent="submit">
        <!-- Email -->
        <label class="field" :class="{ error: !!error }">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
            <circle cx="12" cy="7" r="4" />
          </svg>
          <input
            v-model="email"
            type="email"
            placeholder="Email address"
            autocomplete="username"
            autofocus
          />
        </label>

        <!-- Password -->
        <label class="field" :class="{ error: !!error }">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
          </svg>
          <input
            v-model="password"
            :type="showPassword ? 'text' : 'password'"
            placeholder="Password"
            autocomplete="current-password"
          />
          <button
            type="button"
            class="reveal"
            :aria-label="showPassword ? 'Hide password' : 'Show password'"
            @click="showPassword = !showPassword"
          >
            <svg v-if="showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 5c-7 0-10 7-10 7s3 7 10 7 10-7 10-7-3-7-10-7z" />
              <circle cx="12" cy="12" r="3" />
            </svg>
            <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
              <path d="M6.61 6.61A18.5 18.5 0 0 0 2 12s3 8 10 8a9.12 9.12 0 0 0 5.39-1.61" />
              <path d="M2 2l20 20" />
            </svg>
          </button>
        </label>

        <p v-if="error" class="err-msg">{{ error }}</p>
        <p v-else-if="notice" class="notice-msg">{{ notice }}</p>

        <div class="forgot">
          <button type="button" class="forgot-link" @click="notice = 'Please contact your platform administrator to reset your password.'">
            Forget password?
          </button>
        </div>

        <button type="submit" class="login-btn" :disabled="loading">
          <span v-if="loading" class="spinner" aria-hidden="true" />
          {{ loading ? 'Signing in…' : 'LOGIN' }}
        </button>
      </form>
    </div>

    <p class="copyright">© {{ new Date().getFullYear() }} EXPOUSE · Event Management Platform</p>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 20px;
  padding: 24px;
  background:
    radial-gradient(1100px 600px at 15% -10%, #eef2ff 0%, transparent 55%),
    radial-gradient(900px 620px at 100% 110%, #ede9fe 0%, transparent 55%),
    #eef0f4;
}

.login-card {
  width: 100%;
  max-width: 430px;
  background: #fff;
  border: 1px solid rgba(99, 102, 241, .08);
  border-radius: 22px;
  padding: 44px 40px 48px;
  box-shadow:
    0 1px 2px rgba(20, 23, 40, .04),
    0 24px 60px -20px rgba(31, 36, 48, .22);
}

/* ── Brand ── */
.brand {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-bottom: 40px;
  color: #4a4f5c;
}
.brand-mark { width: 44px; height: 44px; color: var(--brand); }
.brand-word {
  font-size: 1.85rem;
  font-weight: 700;
  letter-spacing: .14em;
  color: #3f4453;
}

/* ── Fields ── */
.fields { display: flex; flex-direction: column; }

.field {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 4px 14px;
  margin-bottom: 22px;
  background: #f6f7fb;
  border: 1px solid #eceef4;
  border-bottom: 2px solid #d9dce6;
  border-radius: 12px 12px 6px 6px;
  transition: border-color .16s, background .16s, box-shadow .16s;
}
.field:focus-within {
  background: #fff;
  border-color: #e3e6f0;
  border-bottom-color: var(--brand);
  box-shadow: 0 0 0 4px rgba(99, 102, 241, .08);
}
.field.error { border-bottom-color: #ef4444; }
.field > svg {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  color: #9aa1b2;
}
.field:focus-within > svg { color: var(--brand); }

.field input {
  flex: 1;
  min-width: 0;
  width: auto;
  margin: 0;
  padding: 12px 0;
  border: 0;
  background: transparent;
  font-size: .98rem;
  color: var(--ink);
}
.field input:focus { outline: none; box-shadow: none; }
.field input::placeholder { color: #9aa1b2; }

.reveal {
  display: grid;
  place-items: center;
  width: 30px;
  height: 30px;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--brand);
  cursor: pointer;
  border-radius: 8px;
}
.reveal:hover { background: rgba(99, 102, 241, .1); }
.reveal svg { width: 20px; height: 20px; }

.err-msg {
  margin: -8px 0 14px;
  color: #dc2626;
  font-size: .86rem;
}
.notice-msg {
  margin: -8px 0 14px;
  color: var(--brand-dark);
  font-size: .86rem;
}

.forgot {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 30px;
}
.forgot-link {
  padding: 0;
  border: 0;
  background: transparent;
  color: #9aa1b2;
  font-size: .92rem;
  font-weight: 500;
  cursor: pointer;
  transition: color .15s;
}
.forgot-link:hover { color: var(--brand); }

/* ── Button ── */
.login-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  padding: 15px;
  border: 0;
  border-radius: 999px;
  background: linear-gradient(180deg, #6f72f4, var(--brand));
  color: #fff;
  font-size: .98rem;
  font-weight: 700;
  letter-spacing: .06em;
  cursor: pointer;
  box-shadow: 0 12px 24px -10px rgba(99, 102, 241, .7);
  transition: transform .12s, box-shadow .16s, filter .16s;
}
.login-btn:hover:not(:disabled) {
  filter: brightness(1.04);
  box-shadow: 0 16px 30px -10px rgba(99, 102, 241, .75);
}
.login-btn:active:not(:disabled) { transform: translateY(1px); }
.login-btn:disabled { opacity: .7; cursor: not-allowed; }

.spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, .4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.copyright {
  color: #9aa1b2;
  font-size: .8rem;
}
</style>
