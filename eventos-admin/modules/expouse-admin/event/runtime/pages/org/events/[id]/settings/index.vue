<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api   = useApi()
const id    = route.params.id as string

const METHODS = [
  {
    key:  'password',
    label: 'Email & Password',
    hint:  'Attendees sign in with an email address and password.',
    icon:  'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
  },
  {
    key:  'magic_link',
    label: 'Magic Link',
    hint:  'Email a one-time sign-in link. No password is required.',
    icon:  'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
  },
  {
    key:  'guest',
    label: 'Guest Access',
    hint:  'Anyone can enter without creating an account.',
    icon:  'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
  },
]

const methods      = reactive<Record<string, boolean>>({ password: true, magic_link: false, guest: false })
const requireLogin = ref(false)
const saving       = ref(false)
const saved        = ref(false)

async function load() {
  const s = (await api<any>(`/events/${id}/settings`)).data
  const m = s.login?.methods || {}
  methods.password   = m.password !== false
  methods.magic_link = !!m.magic_link
  methods.guest      = !!m.guest
  requireLogin.value = !!s.login?.require_login
}

async function save() {
  saving.value = true
  try {
    await api(`/events/${id}/settings`, {
      method: 'PUT',
      body: { login: { methods: { ...methods }, require_login: requireLogin.value } },
    })
    saved.value = true
    setTimeout(() => (saved.value = false), 1500)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="max-w-180">

    <!-- Page header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-[1.35rem] font-bold text-ink mb-0.5">Login Settings</h1>
        <p class="text-muted text-[.88rem]">Control how attendees authenticate and access this event.</p>
      </div>
      <button class="btn" :disabled="saving" @click="save">
        <svg v-if="saving" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
          <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
        </svg>
        <svg v-else-if="saved" width="14" height="14" viewBox="0 0 24 24" fill="none">
          <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        {{ saving ? 'Saving…' : saved ? 'Saved' : 'Save changes' }}
      </button>
    </div>

    <!-- Login Methods -->
    <div class="card mb-4">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-9 h-9 rounded-xl bg-brand-soft grid place-items-center shrink-0">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
          </svg>
        </div>
        <div>
          <p class="font-semibold text-[.95rem] text-ink mb-0.5">Login Methods</p>
          <p class="text-[.82rem] text-muted">Choose how attendees can sign in to your event.</p>
        </div>
      </div>

      <div class="flex flex-col gap-2.5">
        <label
          v-for="m in METHODS" :key="m.key"
          class="flex items-center gap-4 p-4 rounded-xl border-[1.5px] cursor-pointer transition-all duration-150 select-none"
          :class="methods[m.key]
            ? 'border-brand bg-brand-soft/40'
            : 'border-line bg-white hover:border-[#c7c2f5]'"
        >
          <!-- Icon pill -->
          <div
            class="w-9 h-9 rounded-xl grid place-items-center shrink-0 transition-colors duration-150"
            :class="methods[m.key] ? 'bg-brand text-white' : 'bg-faint text-muted'"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path :d="m.icon" />
            </svg>
          </div>

          <!-- Text -->
          <div class="flex-1 min-w-0">
            <p
              class="font-semibold text-[.9rem] mb-0.5 transition-colors"
              :class="methods[m.key] ? 'text-brand-dark' : 'text-ink'"
            >{{ m.label }}</p>
            <p class="text-[.8rem] text-muted">{{ m.hint }}</p>
          </div>

          <!-- Toggle switch -->
          <span
            class="relative w-10 h-[22px] rounded-full shrink-0 transition-colors duration-150"
            :class="methods[m.key] ? 'bg-brand' : 'bg-[#cdd2dc]'"
          >
            <i
              class="absolute top-[3px] left-[3px] w-4 h-4 rounded-full bg-white shadow-sm transition-transform duration-150"
              :class="methods[m.key] ? 'translate-x-[18px]' : 'translate-x-0'"
            />
          </span>
          <input v-model="methods[m.key]" type="checkbox" class="sr-only">
        </label>
      </div>
    </div>

    <!-- Access Control -->
    <div class="card">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-9 h-9 rounded-xl bg-brand-soft grid place-items-center shrink-0">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
            <circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>
          </svg>
        </div>
        <div>
          <p class="font-semibold text-[.95rem] text-ink mb-0.5">Access Control</p>
          <p class="text-[.82rem] text-muted">Restrict who can view event content.</p>
        </div>
      </div>

      <label
        class="flex items-center gap-4 p-4 rounded-xl border-[1.5px] cursor-pointer transition-all duration-150 select-none"
        :class="requireLogin
          ? 'border-brand bg-brand-soft/40'
          : 'border-line bg-white hover:border-[#c7c2f5]'"
      >
        <!-- Icon -->
        <div
          class="w-9 h-9 rounded-xl grid place-items-center shrink-0 transition-colors duration-150"
          :class="requireLogin ? 'bg-brand text-white' : 'bg-faint text-muted'"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          </svg>
        </div>

        <!-- Text -->
        <div class="flex-1 min-w-0">
          <p
            class="font-semibold text-[.9rem] mb-0.5 transition-colors"
            :class="requireLogin ? 'text-brand-dark' : 'text-ink'"
          >Require login to view the event</p>
          <p class="text-[.8rem] text-muted">Attendees must sign in before accessing any event content.</p>
        </div>

        <!-- Toggle switch -->
        <span
          class="relative w-10 h-[22px] rounded-full shrink-0 transition-colors duration-150"
          :class="requireLogin ? 'bg-brand' : 'bg-[#cdd2dc]'"
        >
          <i
            class="absolute top-[3px] left-[3px] w-4 h-4 rounded-full bg-white shadow-sm transition-transform duration-150"
            :class="requireLogin ? 'translate-x-[18px]' : 'translate-x-0'"
          />
        </span>
        <input v-model="requireLogin" type="checkbox" class="sr-only">
      </label>
    </div>

  </div>
</template>
