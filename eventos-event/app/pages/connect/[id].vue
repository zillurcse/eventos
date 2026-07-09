<script setup lang="ts">
import type { Delegate } from '~/stores/delegates'

definePageMeta({ layout: 'event', middleware: 'auth' })

const route = useRoute()
const site = useSiteStore()
const delegates = useDelegatesStore()
const chat = useChatStore()

const id = computed(() => route.params.id as string)

const person = ref<Delegate | null>(null)
const loading = ref(true)
const state = ref<'idle' | 'sending' | 'sent' | 'error'>('idle')

const isSelf = computed(() => !!chat.me && chat.me === id.value)

function initials(n?: string | null) {
  const p = (n || '?').trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?'
}

async function load() {
  loading.value = true
  if (!chat.me) chat.fetchInbox()
  try {
    const found = await delegates.resolveByIds([id.value])
    person.value = found[0] ?? null
  } finally {
    loading.value = false
  }
}

async function connect() {
  const uuid = site.event?.uuid
  if (!uuid || state.value === 'sending') return
  state.value = 'sending'
  try {
    const api = useApi()
    await api(`/events/${uuid}/connections`, { method: 'POST', body: { to: id.value } })
    state.value = 'sent'
  } catch {
    state.value = 'error'
  }
}

async function message() {
  if (!chat.drawerOpen) chat.toggleDrawer()
  await chat.openWith(id.value)
}

onMounted(load)
watch(id, load)
</script>

<template>
  <div class="wrap">
    <div class="card">
      <div v-if="loading" class="state">Loading…</div>

      <div v-else-if="isSelf" class="state">
        <p class="big">This is your own QR code 🙂</p>
        <NuxtLink to="/delegates" class="btn ghost">Browse delegates</NuxtLink>
      </div>

      <div v-else-if="!person" class="state">
        <p class="big">Attendee not found</p>
        <p class="muted">This connect code may be invalid or from another event.</p>
        <NuxtLink to="/delegates" class="btn ghost">Browse delegates</NuxtLink>
      </div>

      <template v-else>
        <span class="av">
          <img v-if="person.avatar_url" :src="person.avatar_url" :alt="person.name || ''">
          <template v-else>{{ initials(person.name) }}</template>
        </span>
        <h1 class="name">{{ person.name }}</h1>
        <p v-if="person.job_title || person.company" class="sub">
          {{ [person.job_title, person.company].filter(Boolean).join(' · ') }}
        </p>

        <p v-if="state !== 'sent'" class="lead">You scanned {{ (person.name || 'this attendee') }}’s code — send a connection request?</p>

        <div v-if="state === 'sent'" class="done">
          <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
          Connection request sent!
        </div>

        <div class="acts">
          <button
            v-if="state !== 'sent'"
            class="btn"
            type="button"
            :disabled="state === 'sending'"
            @click="connect"
          >
            <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM19 8v6M22 11h-6" /></svg>
            {{ state === 'sending' ? 'Sending…' : 'Connect' }}
          </button>
          <button class="btn ghost" type="button" @click="message">
            <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" /></svg>
            Message
          </button>
        </div>

        <p v-if="state === 'error'" class="err">Couldn’t send the request. Please try again.</p>
      </template>
    </div>
  </div>
</template>

<style scoped>
.wrap { max-width: 460px; margin: 24px auto; }
.card { background: #fff; border-radius: 18px; padding: 34px 26px; text-align: center; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.state { padding: 24px 0; display: flex; flex-direction: column; align-items: center; gap: 12px; }
.big { font-size: 1.05rem; font-weight: 700; color: #1e293b; margin: 0; }
.muted { color: #64748b; font-size: .88rem; margin: 0; }

.av { width: 84px; height: 84px; border-radius: 50%; background: var(--brand-primary); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.8rem; overflow: hidden; }
.av img { width: 100%; height: 100%; object-fit: cover; }
.name { margin: 14px 0 2px; font-size: 1.35rem; font-weight: 800; color: #1e293b; }
.sub { margin: 0; color: #64748b; font-size: .9rem; }
.lead { margin: 18px 0 20px; color: #475569; font-size: .92rem; line-height: 1.5; }

.done { display: inline-flex; align-items: center; gap: 8px; margin: 18px 0 20px; color: #15803d; font-weight: 700; }
.done svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 2.4; stroke-linecap: round; stroke-linejoin: round; }

.acts { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
.btn { display: inline-flex; align-items: center; gap: 8px; border: none; border-radius: 12px; padding: 12px 22px; font: inherit; font-size: .9rem; font-weight: 700; cursor: pointer; background: var(--brand-primary); color: #fff; text-decoration: none; }
.btn:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
.btn:disabled { opacity: .6; cursor: default; }
.btn.ghost { background: #f1f5f9; color: #475569; }
.btn.ghost:hover { background: #e7ebf0; }
.btn svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
.err { margin: 14px 0 0; color: #dc2626; font-size: .84rem; }
</style>
