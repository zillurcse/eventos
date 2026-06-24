<script setup lang="ts">
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
    navigateTo('/')
  } catch (e: any) {
    error.value = e?.data?.message || 'Those credentials do not match our records.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="card" style="max-width: 420px; margin: 56px auto;">
    <h1>Sign in</h1>
    <p class="muted">EventOS attendee or organizer login.</p>
    <input v-model="email" type="email" placeholder="Email" autocomplete="username" @keyup.enter="submit" />
    <input v-model="password" type="password" placeholder="Password" autocomplete="current-password" @keyup.enter="submit" />
    <p v-if="error" class="error">{{ error }}</p>
    <button class="btn" :disabled="loading" style="width: 100%; margin-top: 8px;" @click="submit">
      {{ loading ? 'Signing in…' : 'Sign in' }}
    </button>
  </div>
</template>
