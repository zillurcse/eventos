<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

const METHODS = [
  { key: 'password', label: 'Email & password', hint: 'Attendees sign in with an email and password.' },
  { key: 'magic_link', label: 'Magic link', hint: 'Email a one-time sign-in link — no password.' },
  { key: 'guest', label: 'Guest access', hint: 'Let anyone in without an account.' },
]
const methods = reactive<Record<string, boolean>>({ password: true, magic_link: false, guest: false })
const requireLogin = ref(false)
const saved = ref(false)

async function load() {
  const s = (await api<any>(`/events/${id}/settings`)).data
  const m = s.login?.methods || {}
  methods.password = m.password !== false
  methods.magic_link = !!m.magic_link
  methods.guest = !!m.guest
  requireLogin.value = !!s.login?.require_login
}

async function save() {
  await api(`/events/${id}/settings`, { method: 'PUT', body: { login: { methods: { ...methods }, require_login: requireLogin.value } } })
  saved.value = true; setTimeout(() => (saved.value = false), 1500)
}

onMounted(load)
</script>

<template>
  <div class="card max-w-[620px]">
    <h2>Login setup <span v-if="saved" class="badge active">saved ✓</span></h2>
    <p class="muted text-[.86rem] -mt-1">How attendees sign in to this event. <em>Config only for now — not yet enforced.</em></p>

    <label
      v-for="m in METHODS" :key="m.key"
      class="flex items-center gap-3 py-3.5 px-1 border-b border-line cursor-pointer last:border-b-0"
    >
      <div class="flex-1">
        <div class="text-ink font-semibold">{{ m.label }}</div>
        <div class="muted text-[.8rem]">{{ m.hint }}</div>
      </div>
      <input v-model="methods[m.key]" type="checkbox" class="w-[18px] h-[18px] m-0">
    </label>

    <label class="flex items-center gap-3 py-3.5 px-1 border-b border-line cursor-pointer last:border-b-0 mt-2">
      <div class="flex-1">
        <div class="text-ink font-semibold">Require login to view the event</div>
        <div class="muted text-[.8rem]">Attendees must sign in before seeing any content.</div>
      </div>
      <input v-model="requireLogin" type="checkbox" class="w-[18px] h-[18px] m-0">
    </label>

    <div class="mt-4"><button class="btn" @click="save">Save login setup</button></div>
  </div>
</template>
