<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface JitsiState {
  domain: string | null
  app_id: string | null
  kid: string | null
  has_private_key: boolean
  has_app_secret: boolean
  configured: boolean
}
interface AgoraState {
  app_id: string | null
  has_certificate: boolean
  configured: boolean
}
interface VideoState { jitsi: JitsiState, agora: AgoraState }

const state = ref<VideoState | null>(null)
const loading = ref(true)
const savingAgora = ref(false)
const savingJitsi = ref(false)

// Secrets are write-only: the API never sends them back, so these stay blank on
// load and are only submitted when the organizer pastes a new value.
const agora = reactive({ app_id: '', app_certificate: '' })
const jitsi = reactive({ domain: '', app_id: '', kid: '', private_key: '', app_secret: '' })

// JaaS app ids look like vpaas-magic-cookie-<hex>; a self-hosted Prosody uses a
// plain app id + shared secret instead of an RSA key.
const isJaas = computed(() => jitsi.app_id.trim().startsWith('vpaas-magic-cookie-'))

// An Agora App ID / Certificate is always a 32-character hex string — catch a
// mis-paste here rather than at the start of a live session.
const HEX32 = /^[0-9a-fA-F]{32}$/
const agoraIdBad = computed(() => !!agora.app_id.trim() && !HEX32.test(agora.app_id.trim()))
const agoraCertBad = computed(() => !!agora.app_certificate.trim() && !HEX32.test(agora.app_certificate.trim()))

// Built here rather than inline in the template: a multi-line string can't live
// in a Vue binding expression (the HTML parser splits it before Vue sees it).
const PEM_HINT = '-----BEGIN PRIVATE KEY-----\n…\n-----END PRIVATE KEY-----'
const keyPlaceholder = computed(() =>
  state.value?.jitsi.has_private_key
    ? 'A key is stored — paste a new one only to replace it'
    : PEM_HINT,
)

const canSaveAgora = computed(() => {
  if (agoraIdBad.value || agoraCertBad.value) return false
  if (!agora.app_id.trim()) return false
  return !!agora.app_certificate.trim() || !!state.value?.agora.has_certificate
})
const canSaveJitsi = computed(() => {
  if (!jitsi.app_id.trim()) return false
  if (isJaas.value) return !!jitsi.private_key.trim() || !!state.value?.jitsi.has_private_key
  return !!jitsi.app_secret.trim() || !!state.value?.jitsi.has_app_secret
})

async function load() {
  loading.value = true
  try {
    const d = (await api<any>(`/events/${id}/video`)).data as VideoState
    state.value = d
    agora.app_id = d.agora.app_id || ''
    jitsi.domain = d.jitsi.domain || ''
    jitsi.app_id = d.jitsi.app_id || ''
    jitsi.kid = d.jitsi.kid || ''
  } catch {
    toast.error('Could not load video settings.')
  } finally {
    loading.value = false
  }
}

async function put(body: Record<string, unknown>, note: string) {
  state.value = (await api<any>(`/events/${id}/video`, { method: 'PUT', body })).data
  toast.success(note)
}

async function saveAgora() {
  if (!canSaveAgora.value || savingAgora.value) return
  savingAgora.value = true
  try {
    const payload: Record<string, unknown> = { app_id: agora.app_id.trim() }
    // Empty means "leave what's stored alone", not "wipe it".
    if (agora.app_certificate.trim()) payload.app_certificate = agora.app_certificate.trim()
    await put({ agora: payload }, 'Agora settings saved')
    agora.app_certificate = ''
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not save Agora settings.')
  } finally {
    savingAgora.value = false
  }
}

async function saveJitsi() {
  if (!canSaveJitsi.value || savingJitsi.value) return
  savingJitsi.value = true
  try {
    const payload: Record<string, unknown> = {
      domain: jitsi.domain.trim() || (isJaas.value ? '8x8.vc' : ''),
      app_id: jitsi.app_id.trim(),
      kid: jitsi.kid.trim(),
    }
    if (jitsi.private_key.trim()) payload.private_key = jitsi.private_key.trim()
    if (jitsi.app_secret.trim()) payload.app_secret = jitsi.app_secret.trim()
    await put({ jitsi: payload }, 'Jitsi settings saved')
    jitsi.private_key = ''
    jitsi.app_secret = ''
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not save Jitsi settings.')
  } finally {
    savingJitsi.value = false
  }
}

async function clearProvider(which: 'agora' | 'jitsi') {
  const label = which === 'agora' ? 'Agora' : 'Jitsi'
  if (!confirm(`Remove the stored ${label} credentials for this event?`)) return
  try {
    await put({ [which]: { clear: true } }, `${label} credentials removed`)
    if (which === 'agora') { agora.app_id = ''; agora.app_certificate = '' }
    else { jitsi.app_id = ''; jitsi.kid = ''; jitsi.domain = ''; jitsi.private_key = ''; jitsi.app_secret = '' }
  } catch {
    toast.error(`Could not remove the ${label} credentials.`)
  }
}

onMounted(load)
</script>

<template>
  <div class="max-w-2xl">
    <div class="mb-5">
      <h2 class="section-title m-0">Video</h2>
      <p class="muted text-[.85rem] mt-1 mb-0">
        Credentials for sessions you host in-page. Pick which one a session uses under
        <strong>Showcase › Sessions › Stream › Who will host</strong>.
      </p>
    </div>

    <div v-if="loading" class="muted text-center py-16">Loading…</div>

    <template v-else>
      <!-- ── Agora ─────────────────────────────────────────────────────── -->
      <div class="card mb-5 p-5">
        <div class="flex items-start justify-between gap-3 mb-1">
          <h3 class="font-semibold text-[.9rem] text-ink m-0">Agora</h3>
          <span
            class="shrink-0 text-[.62rem] font-bold uppercase tracking-wide px-2 py-0.5 rounded"
            :class="state?.agora.configured ? 'bg-[#dcfce7] text-[#15803d]' : 'bg-[#e2e8f0] text-[#475569]'"
          >{{ state?.agora.configured ? 'Ready' : 'Not set up' }}</span>
        </div>
        <p class="muted text-[.83rem] mt-0 mb-4">
          Broadcast video: the speaker goes on camera and everyone else watches. Best for a
          large audience — one upstream feeds thousands of viewers. Create a project at
          <a href="https://console.agora.io" target="_blank" rel="noopener" class="text-brand hover:underline">console.agora.io</a>
          and enable its App Certificate.
        </p>

        <div class="mb-4">
          <label class="block mb-1.5">App ID <span class="text-[#dc2626]">*</span></label>
          <input v-model="agora.app_id" placeholder="32-character hex string" class="m-0 font-mono text-[.85rem]">
          <p v-if="agoraIdBad" class="error text-[.8rem] mt-1.5 mb-0">
            An Agora App ID is exactly 32 hex characters — this doesn’t look like one.
          </p>
        </div>

        <div class="mb-4">
          <label class="block mb-1.5">
            App Certificate
            <span v-if="!state?.agora.has_certificate" class="text-[#dc2626]">*</span>
          </label>
          <input
            v-model="agora.app_certificate"
            type="password"
            class="m-0 font-mono text-[.85rem]"
            :placeholder="state?.agora.has_certificate ? 'A certificate is stored — type to replace it' : '32-character hex string'"
          >
          <p v-if="agoraCertBad" class="error text-[.8rem] mt-1.5 mb-0">
            An App Certificate is exactly 32 hex characters.
          </p>
          <p v-else class="muted text-[.8rem] mt-1.5 mb-0">
            From the project’s Security settings. Stored encrypted and never shown again — it
            signs each viewer’s token, so it must stay on the server.
          </p>
          <p v-if="state?.agora.has_certificate" class="text-[.8rem] text-[#16a34a] font-medium mt-1.5 mb-0">
            ✓ A certificate is stored for this event
          </p>
        </div>

        <div class="flex justify-end gap-2">
          <button v-if="state?.agora.configured" class="btn ghost sm text-[#dc2626]" @click="clearProvider('agora')">Remove</button>
          <button class="btn sm" :disabled="!canSaveAgora || savingAgora" @click="saveAgora">
            {{ savingAgora ? 'Saving…' : 'SAVE AGORA' }}
          </button>
        </div>
      </div>

      <!-- ── Jitsi ─────────────────────────────────────────────────────── -->
      <div class="card mb-5 p-5">
        <div class="flex items-start justify-between gap-3 mb-1">
          <h3 class="font-semibold text-[.9rem] text-ink m-0">Jitsi</h3>
          <span
            class="shrink-0 text-[.62rem] font-bold uppercase tracking-wide px-2 py-0.5 rounded"
            :class="state?.jitsi.configured ? 'bg-[#dcfce7] text-[#15803d]' : 'bg-[#fef3c7] text-[#b45309]'"
          >{{ state?.jitsi.configured ? 'Ready' : 'Not set up' }}</span>
        </div>
        <p class="muted text-[.83rem] mt-0 mb-4">
          Round-table video where everyone can join in. Without a signing key it falls back to the
          public <code>meet.jit.si</code>, which refuses to start a room until someone signs in as
          a moderator — your attendees would be stuck waiting. Add a
          <a href="https://jaas.8x8.vc" target="_blank" rel="noopener" class="text-brand hover:underline">JaaS</a>
          account (free tier available).
        </p>

        <div class="mb-4">
          <label class="block mb-1.5">App ID <span class="text-[#dc2626]">*</span></label>
          <input v-model="jitsi.app_id" placeholder="vpaas-magic-cookie-…" class="m-0 font-mono text-[.85rem]">
          <p class="muted text-[.8rem] mt-1.5 mb-0">
            From the JaaS console. A self-hosted Jitsi uses its Prosody app id instead.
          </p>
        </div>

        <template v-if="isJaas">
          <div class="mb-4">
            <label class="block mb-1.5">Key ID (kid)</label>
            <input v-model="jitsi.kid" placeholder="vpaas-magic-cookie-…/abc123" class="m-0 font-mono text-[.85rem]">
          </div>

          <div class="mb-4">
            <label class="block mb-1.5">
              Private Key
              <span v-if="!state?.jitsi.has_private_key" class="text-[#dc2626]">*</span>
            </label>
            <textarea
              v-model="jitsi.private_key"
              rows="5"
              class="w-full resize-y m-0 font-mono text-[.78rem]"
              :placeholder="keyPlaceholder"
            />
            <p class="muted text-[.8rem] mt-1.5 mb-0">
              The <code>.pk</code> file JaaS downloads when you generate an API key. Stored
              encrypted and never shown again.
            </p>
            <p v-if="state?.jitsi.has_private_key" class="text-[.8rem] text-[#16a34a] font-medium mt-1.5 mb-0">
              ✓ A signing key is stored for this event
            </p>
          </div>
        </template>

        <div v-else-if="jitsi.app_id.trim()" class="mb-4">
          <label class="block mb-1.5">
            App Secret
            <span v-if="!state?.jitsi.has_app_secret" class="text-[#dc2626]">*</span>
          </label>
          <input
            v-model="jitsi.app_secret"
            type="password"
            class="m-0 font-mono text-[.85rem]"
            :placeholder="state?.jitsi.has_app_secret ? 'A secret is stored — type to replace it' : 'Prosody shared secret'"
          >
          <p class="muted text-[.8rem] mt-1.5 mb-0">
            The shared secret from your self-hosted Jitsi’s token auth. Stored encrypted.
          </p>
        </div>

        <div class="mb-4">
          <label class="block mb-1.5">Jitsi Domain</label>
          <input v-model="jitsi.domain" :placeholder="isJaas ? '8x8.vc' : 'video.yourdomain.com'" class="m-0">
          <p class="muted text-[.8rem] mt-1.5 mb-0">
            Leave blank to use <code>{{ isJaas ? '8x8.vc' : 'the platform default' }}</code>.
          </p>
        </div>

        <div class="flex justify-end gap-2">
          <button v-if="state?.jitsi.configured" class="btn ghost sm text-[#dc2626]" @click="clearProvider('jitsi')">Remove</button>
          <button class="btn sm" :disabled="!canSaveJitsi || savingJitsi" @click="saveJitsi">
            {{ savingJitsi ? 'Saving…' : 'SAVE JITSI' }}
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
