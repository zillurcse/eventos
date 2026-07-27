<script setup lang="ts">
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'auth', layout: 'default', title: 'Profile', subtitle: 'Update your personal information' })

const auth = useAuthStore()

const form = reactive({ name: '', email: '', locale: '', timezone: '' })
const errors = reactive<Record<string, string>>({})
const saving = ref(false)

const pwd = reactive({ current_password: '', password: '', password_confirmation: '' })
const pwdErrors = reactive<Record<string, string>>({})
const changingPwd = ref(false)

const initials = computed(() =>
  (auth.user?.name || auth.user?.email || 'EE').split(/\s+/).map(s => s[0]).slice(0, 2).join('').toUpperCase(),
)

const locales = [
  { value: 'en', label: 'English' },
  { value: 'es', label: 'Español' },
  { value: 'fr', label: 'Français' },
  { value: 'de', label: 'Deutsch' },
  { value: 'pt', label: 'Português' },
  { value: 'ar', label: 'العربية' },
]

function syncFromUser() {
  form.name = auth.user?.name ?? ''
  form.email = auth.user?.email ?? ''
  form.locale = auth.user?.locale ?? ''
  form.timezone = auth.user?.timezone ?? ''
}

onMounted(async () => {
  auth.init()
  if (auth.isAuthed && !auth.user) await auth.fetchMe()
  syncFromUser()
})

watch(() => auth.user, syncFromUser)

async function save() {
  Object.keys(errors).forEach(k => delete errors[k])
  saving.value = true
  try {
    await auth.updateProfile({
      name: form.name,
      email: form.email,
      locale: form.locale || null,
      timezone: form.timezone || null,
    })
    toast.success('Profile updated.')
  } catch (e: any) {
    const fieldErrors = e?.data?.errors as Record<string, string[]> | undefined
    if (fieldErrors) {
      for (const [k, v] of Object.entries(fieldErrors)) errors[k] = v[0]
    }
    toast.error(e?.data?.message || 'Could not update your profile.')
  } finally {
    saving.value = false
  }
}

async function changePassword() {
  Object.keys(pwdErrors).forEach(k => delete pwdErrors[k])
  if (pwd.password !== pwd.password_confirmation) {
    pwdErrors.password_confirmation = 'Passwords do not match.'
    return
  }
  changingPwd.value = true
  try {
    const { public: { apiBase } } = useRuntimeConfig()
    await $fetch(`${apiBase}/auth/change-password`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${auth.token}` },
      body: { ...pwd },
    })
    pwd.current_password = ''; pwd.password = ''; pwd.password_confirmation = ''
    toast.success('Password updated.')
  } catch (e: any) {
    const fieldErrors = e?.data?.errors as Record<string, string[]> | undefined
    if (fieldErrors) {
      for (const [k, v] of Object.entries(fieldErrors)) pwdErrors[k] = v[0]
    }
    toast.error(e?.data?.message || 'Could not update your password.')
  } finally {
    changingPwd.value = false
  }
}
</script>

<template>
  <div class="max-w-[640px]">
    <div class="card">
      <div class="flex items-center gap-3.5 mb-5">
        <span class="avatar !w-14 !h-14 text-[1.1rem]">{{ initials }}</span>
        <div>
          <div class="font-semibold text-[1.05rem]">{{ auth.user?.name }}</div>
          <div class="muted text-[.85rem]">{{ auth.user?.email }}</div>
        </div>
      </div>

      <h2>Basic information</h2>
      <div class="grid gap-4">
        <AppInput v-model="form.name" label="Full name" required :error="errors.name" placeholder="Your name" />
        <AppInput v-model="form.email" label="Email" type="email" required :error="errors.email" placeholder="you@example.com" />
        <div>
          <label class="block mb-1.5">Language</label>
          <select v-model="form.locale" class="h-12 w-full px-3 rounded-lg border border-[#cbd5e1]">
            <option value="">System default</option>
            <option v-for="l in locales" :key="l.value" :value="l.value">{{ l.label }}</option>
          </select>
          <p v-if="errors.locale" class="error mt-1 mb-0">{{ errors.locale }}</p>
        </div>
        <AppInput
          v-model="form.timezone"
          label="Timezone"
          :error="errors.timezone"
          placeholder="e.g. Asia/Dhaka"
          hint="Enter an IANA timezone name (leave blank to use the default)."
        />
      </div>

      <div class="mt-5 flex justify-end">
        <button class="btn" :disabled="saving || !form.name || !form.email" @click="save">
          {{ saving ? 'Saving…' : 'Save changes' }}
        </button>
      </div>
    </div>

    <div class="card">
      <h2>Change password</h2>
      <div class="grid gap-4">
        <AppInput v-model="pwd.current_password" label="Current password" type="password" :error="pwdErrors.current_password" />
        <AppInput v-model="pwd.password" label="New password" type="password" :error="pwdErrors.password" hint="At least 8 characters." />
        <AppInput v-model="pwd.password_confirmation" label="Confirm new password" type="password" :error="pwdErrors.password_confirmation" />
      </div>
      <div class="mt-5 flex justify-end">
        <button
          class="btn"
          :disabled="changingPwd || !pwd.current_password || !pwd.password || !pwd.password_confirmation"
          @click="changePassword"
        >
          {{ changingPwd ? 'Updating…' : 'Update password' }}
        </button>
      </div>
    </div>
  </div>
</template>
