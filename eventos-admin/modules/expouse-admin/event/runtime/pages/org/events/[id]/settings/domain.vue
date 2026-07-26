<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface DnsRecord { type: string, host: string, value: string, note: string }
interface Domain {
  apex: string
  cname_target: string
  ingress_ip: string
  subdomain: string | null
  subdomain_url: string | null
  custom_domain: string | null
  custom_domain_url: string | null
  primary_url: string | null
  status: 'unconfigured' | 'pending' | 'active' | 'failed'
  verified_at: string | null
  checked_at: string | null
  error: string | null
  dns_records: DnsRecord[]
}

const state = ref<Domain | null>(null)
const loading = ref(true)
const form = reactive({ subdomain: '', custom_domain: '' })
const savingSub = ref(false)
const savingCustom = ref(false)
const removingCustom = ref(false)
const verifying = ref(false)
const subEditing = ref(false)
const customEditing = ref(false)

const subCheck = ref<{ available: boolean | null, reason: string | null, checking: boolean }>({
  available: null, reason: null, checking: false,
})
const customCheck = ref<{ available: boolean | null, reason: string | null, checking: boolean }>({
  available: null, reason: null, checking: false,
})

let subTimer: ReturnType<typeof setTimeout> | null = null
let customTimer: ReturnType<typeof setTimeout> | null = null

async function load() {
  loading.value = true
  try {
    const d = (await api<any>(`/events/${id}/domain`)).data as Domain
    state.value = d
    form.subdomain = d.subdomain || ''
    form.custom_domain = d.custom_domain || ''
    subEditing.value = !d.subdomain
    customEditing.value = !d.custom_domain
    subCheck.value = { available: null, reason: null, checking: false }
    customCheck.value = { available: null, reason: null, checking: false }
  } catch {
    toast.error('Could not load domain settings.')
  } finally {
    loading.value = false
  }
}

const primaryUrl = computed(() => state.value?.primary_url || state.value?.subdomain_url || null)

const statusBadge = computed(() => {
  const s = state.value?.status || 'unconfigured'
  return badge[s] || badge.unconfigured
})

async function checkAvailability(kind: 'subdomain' | 'custom_domain', value: string) {
  const target = kind === 'subdomain' ? subCheck : customCheck
  const trimmed = value.trim()
  if (!trimmed) {
    target.value = { available: null, reason: null, checking: false }
    return
  }
  // Skip check if unchanged from saved value.
  if (kind === 'subdomain' && trimmed === (state.value?.subdomain || '')) {
    target.value = { available: true, reason: null, checking: false }
    return
  }
  if (kind === 'custom_domain' && trimmed.toLowerCase() === (state.value?.custom_domain || '')) {
    target.value = { available: true, reason: null, checking: false }
    return
  }

  target.value = { ...target.value, checking: true }
  try {
    const res = await api<any>(`/events/${id}/domain/check`, {
      query: { [kind]: trimmed },
    })
    target.value = {
      available: !!res.data.available,
      reason: res.data.reason || null,
      checking: false,
    }
    if (res.data.normalized && kind === 'subdomain') form.subdomain = res.data.normalized
    if (res.data.normalized && kind === 'custom_domain') form.custom_domain = res.data.normalized
  } catch {
    target.value = { available: null, reason: null, checking: false }
  }
}

watch(() => form.subdomain, (v) => {
  if (!subEditing.value) return
  if (subTimer) clearTimeout(subTimer)
  subTimer = setTimeout(() => checkAvailability('subdomain', v), 400)
})

watch(() => form.custom_domain, (v) => {
  if (!customEditing.value) return
  if (customTimer) clearTimeout(customTimer)
  customTimer = setTimeout(() => checkAvailability('custom_domain', v), 400)
})

async function saveSubdomain() {
  if (!form.subdomain.trim()) return
  if (subCheck.value.available === false) {
    toast.error(subCheck.value.reason || 'That subdomain is not available.')
    return
  }
  savingSub.value = true
  try {
    const d = (await api<any>(`/events/${id}/domain`, {
      method: 'PUT',
      body: { subdomain: form.subdomain || null },
    })).data as Domain
    state.value = d
    form.subdomain = d.subdomain || ''
    subEditing.value = false
    subCheck.value = { available: null, reason: null, checking: false }
    toast.success('Subdomain saved')
  } catch (e: any) {
    toast.error(e?.data?.errors?.subdomain?.[0] || e?.data?.message || 'Could not save subdomain.')
  } finally {
    savingSub.value = false
  }
}

async function saveCustom() {
  if (!form.custom_domain.trim()) return
  if (customCheck.value.available === false) {
    toast.error(customCheck.value.reason || 'That domain is not available.')
    return
  }
  savingCustom.value = true
  try {
    const d = (await api<any>(`/events/${id}/domain`, {
      method: 'PUT',
      body: { custom_domain: form.custom_domain || null },
    })).data as Domain
    state.value = d
    form.custom_domain = d.custom_domain || ''
    customEditing.value = false
    customCheck.value = { available: null, reason: null, checking: false }
    toast.success('Custom domain saved. Add the DNS records below, then verify.')
  } catch (e: any) {
    toast.error(e?.data?.errors?.custom_domain?.[0] || e?.data?.message || 'Could not save custom domain.')
  } finally {
    savingCustom.value = false
  }
}

async function removeCustom() {
  removingCustom.value = true
  try {
    const d = (await api<any>(`/events/${id}/domain`, {
      method: 'PUT',
      body: { custom_domain: null },
    })).data as Domain
    state.value = d
    form.custom_domain = ''
    customEditing.value = true
    customCheck.value = { available: null, reason: null, checking: false }
    toast.success('Custom domain removed. Your free subdomain is the live URL again.')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not remove custom domain.')
  } finally {
    removingCustom.value = false
  }
}

async function verify() {
  verifying.value = true
  try {
    const res = await api<any>(`/events/${id}/domain/verify`, { method: 'POST' })
    state.value = res.data
    if (res.data.status === 'active') toast.success('Domain verified and live.')
    else toast.error(res.data.error || 'Verification failed. Check your DNS records.')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not verify domain.')
  } finally {
    verifying.value = false
  }
}

async function copy(text: string) {
  try {
    await navigator.clipboard.writeText(text)
    toast.success('Copied')
  } catch {
    toast.error('Copy failed')
  }
}

const badge: Record<string, { label: string, cls: string }> = {
  unconfigured: { label: 'Not configured', cls: 'draft' },
  pending: { label: 'Pending DNS', cls: 'pending' },
  active: { label: 'Active', cls: 'active' },
  failed: { label: 'Verification failed', cls: 'suspended' },
}

onMounted(load)
</script>

<template>
  <div class="w-full">
    <div class="mb-6">
      <h1 class="text-[1.35rem] font-bold text-ink mb-0.5">Domains</h1>
      <p class="text-muted text-[.88rem]">Your event gets a free subdomain. Optionally connect your own domain.</p>
    </div>

    <div v-if="loading" class="card muted text-center py-12">Loading domain settings…</div>

    <template v-else-if="state">
      <!-- Live URL -->
      <div v-if="primaryUrl" class="card mb-4 p-4 flex items-center gap-3 flex-wrap">
        <span class="text-muted text-[.85rem] font-medium">Live URL</span>
        <a :href="primaryUrl" target="_blank" rel="noopener" class="text-brand hover:underline text-[.95rem] break-all">{{ primaryUrl }}</a>
        <button type="button" class="text-[#6352e7] text-[.82rem] font-medium hover:underline" @click="copy(primaryUrl!)">Copy</button>
        <span
          v-if="state.custom_domain && state.status === 'active'"
          class="badge active ml-auto"
        >Custom domain</span>
        <span v-else class="badge draft ml-auto">Free subdomain</span>
      </div>

      <!-- Free subdomain -->
      <div class="card p-0 mb-4">
        <div class="p-6 border-b border-line">
          <h2 class="font-semibold text-[1.1rem] text-ink mb-1">Free Expouse subdomain</h2>
          <p class="muted text-[.85rem] m-0">Assigned when the event is created. Change it anytime if the new name is available.</p>
        </div>

        <div class="p-6 max-w-3xl">
          <div class="flex items-center gap-2 flex-wrap">
            <div class="flex items-stretch border border-line rounded-lg overflow-hidden h-10">
              <input
                v-model="form.subdomain"
                :disabled="!subEditing"
                placeholder="my-event"
                class="border-0 rounded-none m-0 w-[180px] disabled:bg-[#F7F7FB] disabled:text-muted"
                @keyup.enter="saveSubdomain"
              >
              <span class="px-3 flex items-center bg-[#F7F7FB] text-muted text-[.85rem] whitespace-nowrap border-l border-line">.{{ state.apex }}</span>
            </div>

            <button
              v-if="subEditing"
              type="button"
              class="icon-btn"
              :class="{ on: form.subdomain.trim() && subCheck.available !== false }"
              :disabled="savingSub || !form.subdomain.trim() || subCheck.available === false || subCheck.checking"
              title="Save"
              @click="saveSubdomain"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
            <button v-else type="button" class="icon-btn on" title="Edit" @click="subEditing = true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                <path d="M12 20h9" /><path d="M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4L16.5 3.5z" />
              </svg>
            </button>
            <button
              v-if="subEditing && state.subdomain"
              type="button"
              class="text-muted text-[.82rem] hover:text-ink"
              @click="form.subdomain = state.subdomain || ''; subEditing = false; subCheck = { available: null, reason: null, checking: false }"
            >
              Cancel
            </button>
          </div>

          <p v-if="subEditing && subCheck.checking" class="muted text-[.8rem] mt-2">Checking availability…</p>
          <p v-else-if="subEditing && subCheck.available === true" class="text-green-700 text-[.8rem] mt-2">Available</p>
          <p v-else-if="subEditing && subCheck.available === false" class="error mt-2 text-[.8rem]">{{ subCheck.reason }}</p>
        </div>
      </div>

      <!-- Custom domain -->
      <div class="card p-0">
        <div class="p-6 border-b border-line flex items-start justify-between gap-4 flex-wrap">
          <div>
            <h2 class="font-semibold text-[1.1rem] text-ink mb-1">Custom domain</h2>
            <p class="muted text-[.85rem] m-0">
              Point a domain you already own (e.g. <span class="font-mono">events.yourcompany.com</span>) at this event.
              After DNS verifies, it becomes the live URL; your free subdomain still works.
            </p>
          </div>
          <span v-if="state.custom_domain" class="badge" :class="statusBadge.cls">{{ statusBadge.label }}</span>
        </div>

        <div class="p-6 max-w-3xl">
          <div class="flex items-center gap-2 flex-wrap">
            <input
              v-model="form.custom_domain"
              :disabled="!customEditing"
              placeholder="events.yourcompany.com"
              class="w-[280px] m-0 h-10 rounded-lg disabled:bg-[#f7f7f7] disabled:text-muted"
              @keyup.enter="saveCustom"
            >

            <button
              v-if="customEditing"
              type="button"
              class="icon-btn"
              :class="{ on: form.custom_domain.trim() && customCheck.available !== false }"
              :disabled="savingCustom || !form.custom_domain.trim() || customCheck.available === false || customCheck.checking"
              title="Save"
              @click="saveCustom"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
            <button v-else type="button" class="icon-btn on" title="Edit" @click="customEditing = true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                <path d="M12 20h9" /><path d="M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4L16.5 3.5z" />
              </svg>
            </button>

            <button
              v-if="state.custom_domain && !customEditing"
              type="button"
              class="text-red-600 text-[.82rem] font-medium hover:underline"
              :disabled="removingCustom"
              @click="removeCustom"
            >
              {{ removingCustom ? 'Removing…' : 'Remove' }}
            </button>
          </div>

          <p v-if="customEditing && customCheck.checking" class="muted text-[.8rem] mt-2">Checking availability…</p>
          <p v-else-if="customEditing && customCheck.available === true" class="text-green-700 text-[.8rem] mt-2">Available</p>
          <p v-else-if="customEditing && customCheck.available === false" class="error mt-2 text-[.8rem]">{{ customCheck.reason }}</p>

          <!-- How-to when no custom domain yet -->
          <div v-if="!state.custom_domain" class="mt-5 rounded-lg border border-line bg-[#fafbfc] p-4 text-[.85rem]">
            <p class="font-semibold text-ink mb-2">How to connect a custom domain</p>
            <ol class="m-0 pl-4 space-y-1.5 text-muted">
              <li>Enter the hostname you want (subdomain like <span class="font-mono">events.company.com</span>, or an apex like <span class="font-mono">company.com</span>).</li>
              <li>Save - we’ll give you DNS records to create at your registrar (Cloudflare, GoDaddy, Namecheap…).</li>
              <li>Add those records, wait a few minutes for DNS, then click <strong>Verify domain</strong>.</li>
              <li>Once active, visitors use your custom domain. TLS is issued at the edge.</li>
            </ol>
          </div>

          <!-- DNS instructions -->
          <template v-if="state.custom_domain && state.dns_records.length">
            <div class="mt-5 border-t border-line pt-5">
              <div class="font-semibold text-ink text-[.95rem] mb-1">1 · Add these DNS records</div>
              <p class="muted text-[.82rem] mb-3">
                At your DNS provider, create the following for
                <span class="font-mono text-ink">{{ state.custom_domain }}</span>.
                Then come back and verify.
              </p>

              <div class="overflow-x-auto">
                <table class="w-full text-[.82rem] border border-line rounded-lg overflow-hidden">
                  <thead class="bg-[#fafbfc] text-muted text-left">
                    <tr>
                      <th class="p-2 font-semibold">Type</th>
                      <th class="p-2 font-semibold">Name / Host</th>
                      <th class="p-2 font-semibold">Value</th>
                      <th class="p-2" />
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(r, i) in state.dns_records" :key="i" class="border-t border-line align-top">
                      <td class="p-2 font-mono font-semibold">{{ r.type }}</td>
                      <td class="p-2 font-mono break-all">
                        {{ r.host }}
                        <button type="button" class="block text-[#6352e7] font-sans font-medium hover:underline mt-0.5" @click="copy(r.host)">Copy</button>
                      </td>
                      <td class="p-2 font-mono break-all">
                        {{ r.value }}
                        <div class="text-muted font-sans text-[.74rem] mt-0.5">{{ r.note }}</div>
                      </td>
                      <td class="p-2 whitespace-nowrap">
                        <button type="button" class="text-[#6352e7] font-medium hover:underline" @click="copy(r.value)">Copy</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="mt-4 flex items-center gap-3 flex-wrap">
                <div class="font-semibold text-ink text-[.95rem]">2 · Verify</div>
                <button class="btn" :disabled="verifying" @click="verify">
                  {{ verifying ? 'Checking DNS…' : 'Verify domain' }}
                </button>
              </div>

              <p v-if="state.status === 'failed' && state.error" class="error mt-2">{{ state.error }}</p>
              <p v-else-if="state.status === 'pending'" class="muted text-[.8rem] mt-2">
                DNS changes can take a few minutes (sometimes up to an hour) to propagate.
              </p>
              <p v-else-if="state.status === 'active'" class="text-green-700 text-[.82rem] mt-2">
                Verified<span v-if="state.verified_at"> on {{ new Date(state.verified_at).toLocaleString() }}</span>.
                Your custom domain is now the live URL.
              </p>
            </div>
          </template>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.icon-btn {
  width: 40px; height: 40px; border-radius: 8px; border: 0; flex: 0 0 auto;
  display: inline-grid; place-items: center; background: #eceef2; color: #9aa1ad;
  cursor: pointer; transition: background .15s, color .15s;
}
.icon-btn.on { background: var(--brand); color: #fff; }
.icon-btn:disabled { cursor: not-allowed; opacity: .55; }
</style>
