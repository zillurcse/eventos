<script setup lang="ts">
import { toast } from 'vue-sonner'

const auth = useAuthStore()

const currentPassword = ref('')
const password = ref('')
const confirmPassword = ref('')
const saving = ref(false)
const error = ref('')

function reset() {
  currentPassword.value = ''
  password.value = ''
  confirmPassword.value = ''
  error.value = ''
}

async function save() {
  if (saving.value) return
  error.value = ''

  if (password.value.length < 8) {
    error.value = 'New password must be at least 8 characters.'
    return
  }
  if (password.value !== confirmPassword.value) {
    error.value = 'Confirm password does not match.'
    return
  }

  saving.value = true
  try {
    await auth.changePassword(currentPassword.value, password.value)
    reset()
    toast.success('Password updated')
  } catch (e: any) {
    error.value = e?.data?.errors?.current_password?.[0] ?? e?.data?.message ?? 'Could not update password.'
  } finally {
    saving.value = false
  }
}

function cancel() { reset() }
</script>

<template>
  <div class="change-password">
    <div class="field">
      <label for="cp-current">Current Password<span class="req">*</span></label>
      <input id="cp-current" v-model="currentPassword" type="password" placeholder="Enter Current Password" autocomplete="current-password">
    </div>

    <div class="field">
      <label for="cp-new">New Password<span class="req">*</span></label>
      <input id="cp-new" v-model="password" type="password" placeholder="Enter New Password" autocomplete="new-password">
    </div>

    <div class="field">
      <label for="cp-confirm">Confirm Password<span class="req">*</span></label>
      <input id="cp-confirm" v-model="confirmPassword" type="password" placeholder="Enter Confirm Password" autocomplete="new-password">
    </div>

    <p v-if="error" class="error">{{ error }}</p>

    <div class="foot">
      <button type="button" class="btn primary" :disabled="saving" @click="save">{{ saving ? 'Saving…' : 'Save' }}</button>
      <button type="button" class="btn text" :disabled="saving" @click="cancel">Cancel</button>
    </div>
  </div>
</template>

<style scoped>
.change-password { display: flex; flex-direction: column; gap: 20px; max-width: 420px; }

.field { display: flex; flex-direction: column; gap: 8px; }
.field label { font-size: .85rem; font-weight: 600; color: #334155; }
.field .req { color: #ef4444; margin-left: 2px; }
.field input {
  border: 1px solid #e2e6eb; border-radius: 10px; padding: 11px 14px; font: inherit; font-size: .9rem; color: #1e293b;
}
.field input::placeholder { color: #b0b7c3; }
.field input:focus { outline: none; border-color: var(--brand-primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand-primary) 15%, transparent); }

.error { margin: 0; color: #ef4444; font-size: .85rem; }

.foot { display: flex; align-items: center; gap: 16px; padding-top: 6px; border-top: 1px solid #f1f2f6; margin-top: 4px; padding-top: 20px; }
.btn { border: none; border-radius: 10px; font: inherit; font-size: .9rem; font-weight: 700; cursor: pointer; padding: 11px 22px; }
.btn.primary { background: var(--brand-primary); color: #fff; }
.btn.primary:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
.btn.text { background: none; color: var(--brand-primary); padding: 11px 4px; }
.btn:disabled { opacity: .6; cursor: default; }
</style>
