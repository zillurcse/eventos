<script setup lang="ts">
import type { Delegate } from '~/stores/delegates'

const emit = defineEmits<{ close: [] }>()

const meetings = useMeetingsStore()
const delegates = useDelegatesStore()

const step = ref<'pick' | 'form'>('pick')
const search = ref('')
const selected = ref<Delegate | null>(null)
const title = ref('')
const agenda = ref('')
const startsAt = ref('')
const errorMsg = ref('')

onMounted(() => { if (!delegates.loaded) delegates.fetchDelegates() })

const filtered = computed<Delegate[]>(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return delegates.delegates
  return delegates.delegates.filter(d =>
    `${d.name} ${d.job_title} ${d.company}`.toLowerCase().includes(q))
})

function initials(name?: string | null) {
  const p = (name || '?').trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?'
}

function choose(d: Delegate) {
  selected.value = d
  title.value = `Meeting with ${d.name || 'you'}`
  step.value = 'form'
}

async function submit() {
  if (!selected.value) return
  errorMsg.value = ''
  const ok = await meetings.request({
    to: selected.value.id,
    title: title.value.trim() || undefined,
    agenda: agenda.value.trim() || undefined,
    starts_at: startsAt.value ? new Date(startsAt.value).toISOString() : undefined,
  })
  if (ok) emit('close')
  else errorMsg.value = 'Could not send the request. Please try again.'
}
</script>

<template>
  <div class="overlay" @click.self="emit('close')">
    <div class="modal" role="dialog" aria-modal="true">
      <header class="head">
        <h2>{{ step === 'pick' ? 'Request a meeting' : 'Meeting details' }}</h2>
        <button class="x" type="button" aria-label="Close" @click="emit('close')">
          <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
        </button>
      </header>

      <!-- Step 1: choose a delegate -->
      <div v-if="step === 'pick'" class="body">
        <div class="search">
          <input v-model="search" type="text" placeholder="Search people">
          <svg viewBox="0 0 24 24"><path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3" /></svg>
        </div>

        <div v-if="delegates.loading && !delegates.loaded" class="state">Loading people…</div>
        <div v-else-if="!filtered.length" class="state">No one matches your search.</div>

        <ul v-else class="people">
          <li v-for="d in filtered" :key="d.id">
            <button type="button" class="person" @click="choose(d)">
              <span class="pa">
                <img v-if="d.avatar_url" :src="d.avatar_url" :alt="d.name || ''">
                <span v-else>{{ initials(d.name) }}</span>
              </span>
              <span class="pi">
                <strong>{{ d.name }}</strong>
                <small v-if="d.job_title || d.company">{{ [d.job_title, d.company].filter(Boolean).join(' · ') }}</small>
              </span>
              <svg class="chev" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
            </button>
          </li>
        </ul>
      </div>

      <!-- Step 2: fill in the details -->
      <div v-else class="body">
        <div class="chosen">
          <span class="pa">
            <img v-if="selected?.avatar_url" :src="selected.avatar_url" :alt="selected?.name || ''">
            <span v-else>{{ initials(selected?.name) }}</span>
          </span>
          <div>
            <strong>{{ selected?.name }}</strong>
            <button type="button" class="change" @click="step = 'pick'">Change</button>
          </div>
        </div>

        <label class="field">
          <span>Title</span>
          <input v-model="title" type="text" maxlength="200" placeholder="What's this meeting about?">
        </label>

        <label class="field">
          <span>Agenda <em>(optional)</em></span>
          <textarea v-model="agenda" rows="3" maxlength="1000" placeholder="Add a note for the invitee" />
        </label>

        <label class="field">
          <span>Proposed time <em>(optional)</em></span>
          <input v-model="startsAt" type="datetime-local">
        </label>

        <p v-if="errorMsg" class="err">{{ errorMsg }}</p>

        <div class="foot">
          <button type="button" class="btn ghost" @click="step = 'pick'">Back</button>
          <button type="button" class="btn primary" :disabled="meetings.sending" @click="submit">
            {{ meetings.sending ? 'Sending…' : 'Send request' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(15,23,42,.5); display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 60; }
.modal { background: #fff; border-radius: 18px; width: 100%; max-width: 460px; max-height: 88vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 50px rgba(15,23,42,.28); }

.head { display: flex; align-items: center; justify-content: space-between; padding: 18px 20px; border-bottom: 1px solid #eef0f3; }
.head h2 { margin: 0; font-size: 1.05rem; font-weight: 800; color: #1e293b; }
.x { border: none; background: #f1f5f9; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.x svg { width: 16px; height: 16px; fill: none; stroke: #64748b; stroke-width: 2; stroke-linecap: round; }

.body { padding: 18px 20px; overflow-y: auto; }

.search { position: relative; margin-bottom: 12px; }
.search input { width: 100%; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 42px 12px 14px; font: inherit; font-size: .92rem; outline: none; }
.search input:focus { border-color: var(--brand-primary); }
.search svg { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; fill: none; stroke: var(--brand-primary); stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }

.state { padding: 28px 0; text-align: center; color: #94a3b8; font-size: .88rem; }

.people { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 2px; }
.person { width: 100%; display: flex; align-items: center; gap: 12px; border: none; background: none; padding: 10px; border-radius: 12px; cursor: pointer; text-align: left; }
.person:hover { background: #f7f8fa; }
.pa { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; flex: 0 0 auto; background: color-mix(in srgb, var(--brand-primary) 12%, #fff); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .85rem; color: color-mix(in srgb, var(--brand-primary) 75%, #fff); }
.pa img { width: 100%; height: 100%; object-fit: cover; }
.pi { min-width: 0; flex: 1; display: flex; flex-direction: column; }
.pi strong { font-size: .9rem; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pi small { color: #64748b; font-size: .78rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chev { width: 18px; height: 18px; fill: none; stroke: #cbd5e1; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

.chosen { display: flex; align-items: center; gap: 12px; padding: 10px 12px; background: #f7f8fa; border-radius: 12px; margin-bottom: 16px; }
.chosen strong { display: block; font-size: .92rem; color: #1e293b; }
.change { border: none; background: none; color: var(--brand-primary); font: inherit; font-size: .78rem; font-weight: 600; cursor: pointer; padding: 0; }

.field { display: block; margin-bottom: 14px; }
.field span { display: block; font-size: .82rem; font-weight: 600; color: #334155; margin-bottom: 6px; }
.field em { color: #94a3b8; font-style: normal; font-weight: 400; }
.field input, .field textarea { width: 100%; border: 1px solid #e2e8f0; border-radius: 10px; padding: 11px 13px; font: inherit; font-size: .9rem; outline: none; resize: vertical; }
.field input:focus, .field textarea:focus { border-color: var(--brand-primary); }

.err { color: #dc2626; font-size: .84rem; margin: 0 0 12px; }

.foot { display: flex; gap: 10px; margin-top: 4px; }
.btn { flex: 1; border: none; border-radius: 10px; padding: 12px; font: inherit; font-size: .9rem; font-weight: 600; cursor: pointer; }
.btn:disabled { opacity: .6; cursor: default; }
.btn.primary { background: var(--brand-primary); color: #fff; }
.btn.ghost { background: #f1f5f9; color: #475569; }
</style>
