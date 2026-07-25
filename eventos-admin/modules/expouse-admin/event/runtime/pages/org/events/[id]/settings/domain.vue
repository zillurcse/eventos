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

// Which of the two domain options is selected — defaults to whichever is
// already configured, favouring the custom domain if both somehow are.
const mode = ref<'sub' | 'custom'>('sub')

// Each field starts locked (showing the saved value + an edit pencil) once a
// value exists, and switches to an editable input when the pencil is clicked.
const subEditing = ref(true)
const customEditing = ref(true)

async function load() {
  loading.value = true
  try {
    const d = (await api<any>(`/events/${id}/domain`)).data as Domain
    state.value = d
    form.subdomain = d.subdomain || ''
    form.custom_domain = d.custom_domain || ''
    mode.value = d.custom_domain ? 'custom' : 'sub'
    subEditing.value = !d.subdomain
    customEditing.value = !d.custom_domain
  } catch { toast.error('Could not load domain settings.') } finally { loading.value = false }
}

const activeUrl = computed(() => {
  if (mode.value === 'custom') return state.value?.custom_domain_url || null
  return state.value?.subdomain_url || null
})

async function saveSubdomain() {
  if (!form.subdomain.trim()) return
  savingSub.value = true
  try {
    const d = (await api<any>(`/events/${id}/domain`, { method: 'PUT', body: { subdomain: form.subdomain || null } })).data
    state.value = d; form.subdomain = d.subdomain || ''
    subEditing.value = false
    toast.success('Subdomain saved')
  } catch (e: any) {
    toast.error(e?.data?.errors?.subdomain?.[0] || e?.data?.message || 'Could not save subdomain.')
  } finally { savingSub.value = false }
}

async function saveCustom() {
  if (!form.custom_domain.trim()) return
  savingCustom.value = true
  try {
    const d = (await api<any>(`/events/${id}/domain`, { method: 'PUT', body: { custom_domain: form.custom_domain || null } })).data
    state.value = d; form.custom_domain = d.custom_domain || ''
    customEditing.value = false
    toast.success(d.custom_domain ? 'Custom domain saved. Now add the DNS records below.' : 'Custom domain removed')
  } catch (e: any) {
    toast.error(e?.data?.errors?.custom_domain?.[0] || e?.data?.message || 'Could not save custom domain.')
  } finally { savingCustom.value = false }
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
  } finally { verifying.value = false }
}

async function copy(text: string) {
  try { await navigator.clipboard.writeText(text); toast.success('Copied') }
  catch { toast.error('Copy failed') }
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
      <p class="text-muted text-[.88rem]">Manage the domain for your event website</p>
    </div>

    <div v-if="loading" class="card muted text-center py-12">Loading domain settings…</div>

    <div v-else-if="state" class="card p-0">
      <div class="p-6 border-b border-line">
        <h2 class="font-semibold text-[1.1rem] text-ink mb-1">Configure Domain</h2>
        <p class="muted text-[.85rem] m-0">After you make any changes for domain, it will update within 48 hours.</p>
      </div>

      <div class="p-6 max-w-3xl">
        <!-- URL of whichever option is currently live -->
        <div v-if="activeUrl" class="flex items-center gap-3 mb-5">
          <span class="text-muted text-[.85rem] font-medium">URL</span>
          <a :href="activeUrl" target="_blank" class="text-brand hover:underline text-[.95rem]">{{ activeUrl }}</a>
        </div>

        <!-- ── Free subdomain ────────────────────────────────── -->
        <div class="flex items-start gap-4 py-3">
          <button type="button" class="radio-dot mt-1 shrink-0" :class="{ on: mode === 'sub' }" @click="mode = 'sub'">
            <i />
          </button>
          <div class="flex-1 min-w-0 cursor-pointer" @click="mode = 'sub'">
            <p class="font-semibold text-ink text-[.95rem] mb-0.5">Get a free Expouse Domain</p>
            <p class="muted text-[.83rem] m-0">Instantly connect a customized domain for free</p>
          </div>

          <div v-if="mode === 'sub'" class="flex items-center gap-2 shrink-0">
            <div class="flex items-stretch border border-line rounded-lg overflow-hidden h-10">
              <input
                v-model="form.subdomain" :disabled="!subEditing" placeholder="mywebsite"
                class="border-0 rounded-none m-0 w-[160px] disabled:bg-[#F7F7FB] disabled:text-muted"
                @keyup.enter="saveSubdomain"
              >
              <span class="px-3 flex items-center bg-[#F7F7FB] text-muted text-[.85rem] whitespace-nowrap border-l border-line">.{{ state.apex }}</span>
            </div>

            <button
              v-if="subEditing" type="button" class="icon-btn" :class="{ on: form.subdomain.trim() }"
              :disabled="savingSub || !form.subdomain.trim()" title="Save" @click="saveSubdomain"
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
          </div>
        </div>

        <!-- <div class="border-t border-line" /> -->

        <!-- ── Custom domain ─────────────────────────────────── -->
        <div class="flex items-start gap-4 py-3">
          <button type="button" class="radio-dot mt-1 shrink-0" :class="{ on: mode === 'custom' }" @click="mode = 'custom'">
            <i />
          </button>
          <div class="flex-1 min-w-0 cursor-pointer" @click="mode = 'custom'">
            <div class="flex items-center gap-2 mb-0.5">
              <p class="font-semibold text-ink text-[.95rem] m-0">Personal Domain Name</p>
              <span class="badge upgrade">UPGRADE</span>
            </div>
            <p class="muted text-[.83rem] m-0">Connect a domain purchased through a web hosting service</p>
          </div>

          <div v-if="mode === 'custom'" class="flex items-center gap-2 shrink-0 h-10">
            <input
              v-model="form.custom_domain" :disabled="!customEditing" placeholder="mywebsite"
              class="w-[220px] m-0 h-full rounded-lg disabled:bg-[#f7f7f7] disabled:text-muted"
              @keyup.enter="saveCustom"
            >

            <button
              v-if="customEditing" type="button" class="icon-btn" :class="{ on: form.custom_domain.trim() }"
              :disabled="savingCustom || !form.custom_domain.trim()" title="Save" @click="saveCustom"
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
          </div>
        </div>

        <!-- DNS instructions (shown once a custom domain is set and selected) -->
        <template v-if="mode === 'custom' && state.custom_domain && state.dns_records.length">
          <div class="mt-2 border-t border-line pt-4">
            <div class="flex items-center gap-2 mb-1">
              <div class="font-semibold text-ink text-[.95rem]">1 · Add these DNS records</div>
              <span class="badge" :class="badge[state.status].cls">{{ badge[state.status].label }}</span>
            </div>
            <p class="muted text-[.82rem] mb-3">At your DNS provider (Cloudflare, GoDaddy, Namecheap…), create the following. Then come back and verify.</p>

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
            </div>

            <p v-if="state.status === 'failed' && state.error" class="error mt-2">{{ state.error }}</p>
            <p v-else-if="state.status === 'pending'" class="muted text-[.8rem] mt-2">DNS changes can take a few minutes (sometimes up to an hour) to propagate.</p>
            <p v-else-if="state.status === 'active'" class="text-green-700 text-[.82rem] mt-2">
              Verified<span v-if="state.verified_at"> on {{ new Date(state.verified_at).toLocaleString() }}</span>. TLS is issued automatically at the edge.
            </p>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.radio-dot {
  position: relative; width: 18px; height: 18px; border-radius: 9999px; border: 2px solid #d7dae1;
  background: #fff; display: inline-grid; place-items: center; padding: 0; cursor: pointer;
}
.radio-dot.on { border-color: var(--brand); }
.radio-dot.on i { display: block; width: 8px; height: 8px; border-radius: 9999px; background: var(--brand); }
.radio-dot i { display: none; }

.icon-btn {
  width: 40px; height: 40px; border-radius: 8px; border: 0; flex: 0 0 auto;
  display: inline-grid; place-items: center; background: #eceef2; color: #9aa1ad;
  cursor: pointer; transition: background .15s, color .15s;
}
.icon-btn.on { background: var(--brand); color: #fff; }
.icon-btn:disabled { cursor: not-allowed; }
</style>
