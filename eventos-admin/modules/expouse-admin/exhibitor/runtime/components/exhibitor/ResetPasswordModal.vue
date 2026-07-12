<script setup lang="ts">
const {
  resetTarget, resetMode, resetPassword, resetMustChange,
  resetSaving, resetError, resetResult,
  closeResetPassword, submitResetPassword,
} = useExhibitorContext()

const showPw = ref(false)
const copied = ref(false)

async function copyPw() {
  if (!resetResult.value?.password) return
  try {
    await navigator.clipboard.writeText(resetResult.value.password)
    copied.value = true
    setTimeout(() => (copied.value = false), 1500)
  } catch { /* clipboard blocked — user can select manually */ }
}
</script>

<template>
  <div class="rp-backdrop" @click.self="closeResetPassword">
    <div class="rp-modal">
      <!-- Header -->
      <div class="rp-head">
        <div>
          <div class="rp-head-sub">Reset password for</div>
          <div class="rp-head-name">{{ resetTarget?.name }}</div>
        </div>
        <button class="rp-x" type="button" aria-label="Close" @click="closeResetPassword">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Step 2: generated password reveal (auto mode) -->
      <div v-if="resetResult" class="rp-body">
        <p class="rp-lead">The password has been reset. Copy it now. It will not be shown again.</p>

        <label class="rp-field-label">Email</label>
        <div class="rp-readonly">{{ resetResult.email }}</div>

        <label class="rp-field-label mt-3">New password</label>
        <div class="rp-copyrow">
          <input class="rp-copyinput" :value="resetResult.password" readonly @focus="($event.target as HTMLInputElement).select()">
          <button type="button" class="rp-copybtn" @click="copyPw">
            {{ copied ? 'Copied!' : 'Copy' }}
          </button>
        </div>

        <div class="rp-foot">
          <button type="button" class="btn" @click="closeResetPassword">Done</button>
        </div>
      </div>

      <!-- Step 1: choose mode -->
      <div v-else class="rp-body">
        <p class="rp-lead">Choose how to reset the password:</p>

        <!-- Auto generate -->
        <label class="rp-option" :class="{ 'rp-option--active': resetMode === 'auto' }">
          <input type="radio" value="auto" v-model="resetMode" class="rp-radio">
          <span>
            <span class="rp-option-title">Automatically generate a password</span>
            <span class="rp-option-desc">You’ll be able to view and copy the password in the next step</span>
          </span>
        </label>

        <!-- Create password -->
        <label class="rp-option" :class="{ 'rp-option--active': resetMode === 'manual' }">
          <input type="radio" value="manual" v-model="resetMode" class="rp-radio">
          <span class="flex-1">
            <span class="rp-option-title">Create password</span>
            <span class="rp-option-desc">Set a specific password for this exhibitor</span>

            <span v-if="resetMode === 'manual'" class="rp-manual" @click.prevent>
              <span class="rp-field-label rp-req">New Password *</span>
              <span class="rp-pwwrap">
                <input
                  :type="showPw ? 'text' : 'password'"
                  v-model="resetPassword"
                  class="rp-pwinput"
                  placeholder="Enter new password"
                  autocomplete="new-password"
                >
                <button type="button" class="rp-eye" @click="showPw = !showPw" :aria-label="showPw ? 'Hide' : 'Show'">
                  <svg v-if="!showPw" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-7-10-7a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>
                </button>
              </span>
              <span class="rp-hint">Password must have at least 8 characters</span>

              <label class="rp-check">
                <input type="checkbox" v-model="resetMustChange">
                <span>Ask user to change their password when they sign in</span>
              </label>
            </span>
          </span>
        </label>

        <p v-if="resetError" class="rp-err">{{ resetError }}</p>

        <div class="rp-foot">
          <button type="button" class="btn ghost" @click="closeResetPassword">Cancel</button>
          <button
            type="button"
            class="btn"
            :disabled="resetSaving || (resetMode === 'manual' && resetPassword.length < 8)"
            @click="submitResetPassword"
          >
            {{ resetSaving ? 'Resetting…' : 'Reset Password' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.rp-backdrop { position: fixed; inset: 0; background: rgba(17,20,30,.45); display: flex; align-items: center; justify-content: center; z-index: 60; padding: 16px; }
.rp-modal { background: #fff; width: 100%; max-width: 540px; border-radius: 16px; overflow: hidden; box-shadow: 0 24px 60px rgba(0,0,0,.25); }

.rp-head { background: #6352e7; color: #fff; padding: 20px 22px; display: flex; align-items: flex-start; justify-content: space-between; }
.rp-head-sub { font-size: .82rem; opacity: .85; }
.rp-head-name { font-size: 1.25rem; font-weight: 700; margin-top: 2px; }
.rp-x { color: #fff; opacity: .9; padding: 2px; }
.rp-x svg { width: 20px; height: 20px; }
.rp-x:hover { opacity: 1; }

.rp-body { padding: 22px; }
.rp-lead { color: var(--muted); font-size: .9rem; margin-bottom: 16px; }

.rp-option { display: flex; gap: 12px; align-items: flex-start; border: 1.5px solid var(--line); border-radius: 12px; padding: 14px 16px; margin-bottom: 12px; cursor: pointer; transition: border-color .12s, background .12s; }
.rp-option--active { border-color: #6352e7; background: #f3f1fe; }
.rp-radio { width: 18px; height: 18px; margin-top: 2px; accent-color: #6352e7; flex: none; }
.rp-option-title { display: block; font-weight: 650; color: var(--ink); font-size: .95rem; }
.rp-option-desc { display: block; color: var(--muted); font-size: .82rem; margin-top: 3px; }

.rp-manual { display: block; margin-top: 14px; }
.rp-field-label { display: block; font-size: .8rem; font-weight: 600; color: var(--ink); margin-bottom: 5px; }
.rp-req { color: #6352e7; }
.rp-pwwrap { position: relative; display: block; }
.rp-pwinput { width: 100%; border: 0; border-bottom: 1.5px solid var(--line); border-radius: 0; padding: 8px 34px 8px 2px; font-size: .92rem; background: transparent; }
.rp-pwinput:focus { outline: none; border-bottom-color: #6352e7; }
.rp-eye { position: absolute; right: 4px; top: 50%; transform: translateY(-50%); color: var(--muted); padding: 2px; }
.rp-eye svg { width: 18px; height: 18px; }
.rp-hint { display: block; color: var(--faint); font-size: .76rem; margin-top: 6px; }
.rp-check { display: flex; align-items: center; gap: 8px; margin-top: 14px; cursor: pointer; font-size: .85rem; color: var(--ink); }
.rp-check input { width: 16px; height: 16px; accent-color: #6352e7; }

.rp-readonly { border: 1px solid var(--line); border-radius: 9px; padding: 9px 12px; font-size: .9rem; color: var(--ink); background: #f7f8fa; }
.rp-copyrow { display: flex; gap: 8px; }
.rp-copyinput { flex: 1; border: 1px solid var(--line); border-radius: 9px; padding: 9px 12px; font-size: .95rem; font-family: ui-monospace, monospace; letter-spacing: .02em; }
.rp-copyinput:focus { outline: none; border-color: #6352e7; }
.rp-copybtn { background: #6352e7; color: #fff; border-radius: 9px; padding: 0 16px; font-size: .85rem; font-weight: 600; }
.rp-copybtn:hover { background: #5544d8; }

.rp-err { color: #dc2626; font-size: .82rem; margin-top: 10px; }
.rp-foot { display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px; }
.mt-3 { margin-top: 12px; }
.flex-1 { flex: 1; }
</style>
