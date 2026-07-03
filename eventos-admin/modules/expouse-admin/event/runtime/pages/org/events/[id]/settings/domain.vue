<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface DnsRecord { type: string, host: string, value: string, note: string }
interface Domain {
  apex: string
  subdomain: string | null
  subdomain_url: string | null
  custom_domain: string | null
  custom_domain_url: string | null
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
const verifying = ref(false)

async function load() {
  loading.value = true
  try {
    const d = (await api<any>(`/events/${id}/domain`)).data as Domain
    state.value = d
    form.subdomain = d.subdomain || ''
    form.custom_domain = d.custom_domain || ''
  } catch { toast.error('Could not load domain settings.') } finally { loading.value = false }
}

const subPreview = computed(() =>
  `${(form.subdomain || 'your-event').toLowerCase()}.${state.value?.apex || 'eventos.app'}`,
)

async function saveSubdomain() {
  savingSub.value = true
  try {
    const d = (await api<any>(`/events/${id}/domain`, { method: 'PUT', body: { subdomain: form.subdomain || null } })).data
    state.value = d; form.subdomain = d.subdomain || ''
    toast.success('Subdomain saved')
  } catch (e: any) {
    toast.error(e?.data?.errors?.subdomain?.[0] || e?.data?.message || 'Could not save subdomain.')
  } finally { savingSub.value = false }
}

async function saveCustom() {
  savingCustom.value = true
  try {
    const d = (await api<any>(`/events/${id}/domain`, { method: 'PUT', body: { custom_domain: form.custom_domain || null } })).data
    state.value = d; form.custom_domain = d.custom_domain || ''
    toast.success(d.custom_domain ? 'Custom domain saved — now add the DNS records below.' : 'Custom domain removed')
  } catch (e: any) {
    toast.error(e?.data?.errors?.custom_domain?.[0] || e?.data?.message || 'Could not save custom domain.')
  } finally { savingCustom.value = false }
}

async function verify() {
  verifying.value = true
  try {
    const res = await api<any>(`/events/${id}/domain/verify`, { method: 'POST' })
    state.value = res.data
    if (res.data.status === 'active') toast.success('Domain verified and live! 🎉')
    else toast.error(res.data.error || 'Verification failed — check your DNS records.')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not verify domain.')
  } finally { verifying.value = false }
}

async function copy(text: string) {
  try { await navigator.clipboard.writeText(text); toast.success('Copied') }
  catch { toast.error('Copy failed') }
}

const badge: Record<string, { label: string, cls: string }> = {
  unconfigured: { label: 'Not configured', cls: 'bg-gray-100 text-gray-600' },
  pending: { label: 'Pending DNS', cls: 'bg-amber-50 text-amber-700' },
  active: { label: 'Active', cls: 'bg-green-50 text-green-700' },
  failed: { label: 'Verification failed', cls: 'bg-red-50 text-red-600' },
}

onMounted(load)
</script>

<template>
  <div class="max-w-[720px] flex flex-col gap-4">
    <div v-if="loading" class="card muted text-center py-12">Loading domain settings…</div>

    <template v-else-if="state">
      <!-- ── Subdomain ─────────────────────────────────────────── -->
      <div class="card">
        <h2 class="mb-1">Subdomain</h2>
        <p class="muted text-[.86rem] -mt-1">Your free EventOS address. Works instantly — no DNS setup needed.</p>

        <label class="block mt-3 mb-1.5">Subdomain</label>
        <div class="flex items-center gap-2 flex-wrap">
          <input v-model="form.subdomain" placeholder="your-event" class="max-w-[220px] m-0" @keyup.enter="saveSubdomain">
          <span class="muted">.{{ state.apex }}</span>
          <button class="btn ghost" :disabled="savingSub" @click="saveSubdomain">{{ savingSub ? 'Saving…' : 'Save' }}</button>
        </div>
        <p class="muted text-[.78rem] mt-1.5">3–63 letters, numbers or hyphens.</p>

        <div class="mt-3 flex items-center gap-2">
          <a :href="`https://${subPreview}`" target="_blank" class="badge active">https://{{ subPreview }}</a>
        </div>
      </div>

      <!-- ── Custom domain ─────────────────────────────────────── -->
      <div class="card">
        <div class="flex items-center gap-2 mb-1">
          <h2 class="m-0">Custom domain</h2>
          <span class="px-2 py-0.5 rounded-full text-[.72rem] font-semibold" :class="badge[state.status].cls">{{ badge[state.status].label }}</span>
        </div>
        <p class="muted text-[.86rem]">Use your own domain, e.g. <code>events.yourcompany.com</code>. Requires DNS records at your domain provider.</p>

        <label class="block mt-3 mb-1.5">Domain</label>
        <div class="flex items-center gap-2 flex-wrap">
          <input v-model="form.custom_domain" placeholder="events.yourcompany.com" class="max-w-[320px] m-0" @keyup.enter="saveCustom">
          <button class="btn" :disabled="savingCustom" @click="saveCustom">{{ savingCustom ? 'Saving…' : 'Save' }}</button>
        </div>

        <!-- DNS instructions (shown once a custom domain is set) -->
        <template v-if="state.custom_domain && state.dns_records.length">
          <div class="mt-5 border-t border-line pt-4">
            <div class="font-semibold text-ink text-[.95rem] mb-1">1 · Add these DNS records</div>
            <p class="muted text-[.82rem] mb-3">At your DNS provider (Cloudflare, GoDaddy, Namecheap…), create the following. Then come back and verify.</p>

            <div class="overflow-x-auto">
              <table class="w-full text-[.82rem] border border-line rounded-lg overflow-hidden">
                <thead class="bg-[#fafbfc] text-muted text-left">
                  <tr>
                    <th class="p-2 font-semibold">Type</th>
                    <th class="p-2 font-semibold">Name / Host</th>
                    <th class="p-2 font-semibold">Value</th>
                    <th class="p-2"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(r, i) in state.dns_records" :key="i" class="border-t border-line align-top">
                    <td class="p-2 font-mono font-semibold">{{ r.type }}</td>
                    <td class="p-2 font-mono break-all">{{ r.host }}</td>
                    <td class="p-2 font-mono break-all">
                      {{ r.value }}
                      <div class="text-muted font-sans text-[.74rem] mt-0.5">{{ r.note }}</div>
                    </td>
                    <td class="p-2 whitespace-nowrap">
                      <button class="text-[#6352e7] font-medium hover:underline" @click="copy(r.value)">Copy</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="mt-4 flex items-center gap-3 flex-wrap">
              <div class="font-semibold text-ink text-[.95rem]">2 · Verify</div>
              <button class="btn" :disabled="verifying" @click="verify">{{ verifying ? 'Checking DNS…' : 'Verify domain' }}</button>
              <a v-if="state.status === 'active'" :href="state.custom_domain_url!" target="_blank" class="badge active">Visit {{ state.custom_domain }} ↗</a>
            </div>

            <p v-if="state.status === 'failed' && state.error" class="error mt-2">{{ state.error }}</p>
            <p v-else-if="state.status === 'pending'" class="muted text-[.8rem] mt-2">DNS changes can take a few minutes (sometimes up to an hour) to propagate.</p>
            <p v-else-if="state.status === 'active'" class="text-green-700 text-[.82rem] mt-2">
              Verified<span v-if="state.verified_at"> on {{ new Date(state.verified_at).toLocaleString() }}</span>. TLS is issued automatically at the edge.
            </p>
          </div>
        </template>
      </div>
    </template>
  </div>
</template>
