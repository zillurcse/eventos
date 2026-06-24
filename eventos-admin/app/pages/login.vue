<script setup lang="ts">
definePageMeta({ layout: false })
const auth = useAuthStore()
const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
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
  <div class="card max-w-[420px] mx-auto mt-14">
    <h1>Sign in to EventOS</h1>
    <p class="muted">Super admins, organizers, and exhibitor/sponsor teams.</p>
    <input v-model="email" type="email" placeholder="Email" autocomplete="username" @keyup.enter="submit" />
    <input v-model="password" type="password" placeholder="Password" autocomplete="current-password" @keyup.enter="submit" />
    <p v-if="error" class="error">{{ error }}</p>
    <button class="btn w-full mt-2" :disabled="loading" @click="submit">
      {{ loading ? 'Signing in…' : 'Sign in' }}
    </button>
  </div>
</template>
