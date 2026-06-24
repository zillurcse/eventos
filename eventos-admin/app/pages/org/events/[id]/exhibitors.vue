<script setup lang="ts">
import { ref, reactive, onMounted, watch, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '../../../../composables/useApi'
import { useUpload } from '../../../../composables/useUpload'

declare const definePageMeta: (meta: Record<string, unknown>) => void
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const { upload } = useUpload()
const id = route.params.id as string

// ── Types ─────────────────────────────────────────────────────────────
interface CtaItem  { id: string; type: string; label: string; value: string; open: boolean }
interface Social   { facebook: string; linkedin: string; twitter: string; instagram: string; whatsapp: string; youtube: string }
interface Contact  { full_name: string; company_name: string; position: string; email: string; phone_code: string; phone: string }
interface Draft {
  name: string; email: string; logo_url: string; logo_file_id: number | null
  package_id: number | string; stall_no: string; type: string
  phone_code: string; phone: string
  rating: boolean; featured: boolean; premium: boolean
  about: string
  street: string; city: string; state: string; zip: string; country: string
  location_url: string; website_url: string
  tags: string[]; filter_id: string
  spotlight_type: 'image' | 'video'; spotlight_url: string; spotlight_file_id: number | null
  cta: CtaItem[]; social: Social; contact: Contact
}

// ── Constants ─────────────────────────────────────────────────────────
const PHONE_CODES = [
  { code: '+880', flag: '🇧🇩' }, { code: '+1',   flag: '🇺🇸' }, { code: '+44',  flag: '🇬🇧' },
  { code: '+971', flag: '🇦🇪' }, { code: '+91',  flag: '🇮🇳' }, { code: '+966', flag: '🇸🇦' },
  { code: '+974', flag: '🇶🇦' }, { code: '+65',  flag: '🇸🇬' }, { code: '+60',  flag: '🇲🇾' },
]
const TYPE_OPTIONS  = ['Exhibitor', 'Sponsor', 'Partner']
const STALL_OPTIONS = ['A1','A2','A3','B1','B2','B3','C1','C2','C3','D1','D2','D3']
const COUNTRIES     = ['Bangladesh','United States','United Kingdom','UAE','India','Saudi Arabia','Qatar','Kuwait','Singapore','Malaysia','Canada','Australia']
const TABS          = ['Details','Members','Documents','Projects','Products','Permissions']
const PARTNER_LIMIT = 50

// ── State ─────────────────────────────────────────────────────────────
const partners    = ref<any[]>([])
const packages    = ref<any[]>([])
const filters     = ref<any[]>([])
const drawerMode  = ref<'add' | 'edit' | null>(null)
const editingId   = ref<string | null>(null)
const activeTab   = ref('Details')
const saving      = ref(false)
const logoUploading = ref(false)
const spotlightUploading = ref(false)
const error       = ref('')
const tagInput    = ref('')
const aboutRef    = ref<HTMLElement | null>(null)

// ── Table / filter / pagination state ────────────────────────────────
const search        = ref('')
const filterType    = ref('')
const filterPackage = ref('')
const page          = ref(1)
const perPage       = ref(10)
const actionsOpenId = ref<string | null>(null)

watch(perPage, () => { page.value = 1 })
watch([search, filterType, filterPackage], () => { page.value = 1 })

// ── Computed ──────────────────────────────────────────────────────────
const filtered = computed(() => {
  let list = partners.value
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter((p: any) => p.name?.toLowerCase().includes(q))
  }
  if (filterType.value) {
    list = list.filter((p: any) => p.type === filterType.value.toLowerCase())
  }
  if (filterPackage.value) {
    // eslint-disable-next-line eqeqeq
    list = list.filter((p: any) => p.package_id == filterPackage.value)
  }
  return list
})

const paginated = computed(() => {
  const start = (page.value - 1) * perPage.value
  return filtered.value.slice(start, start + perPage.value)
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage.value)))

const paginationLabel = computed(() => {
  if (!filtered.value.length) return '0 - 0 of 0'
  const from = (page.value - 1) * perPage.value + 1
  const to   = Math.min(page.value * perPage.value, filtered.value.length)
  return `${from} - ${to} of ${filtered.value.length}`
})

// ── Draft ─────────────────────────────────────────────────────────────
function freshDraft(): Draft {
  return {
    name: '', email: '', logo_url: '', logo_file_id: null,
    package_id: '', stall_no: '', type: 'Exhibitor',
    phone_code: '+880', phone: '',
    rating: false, featured: false, premium: false,
    about: '',
    street: '', city: '', state: '', zip: '', country: '',
    location_url: '', website_url: '',
    tags: [], filter_id: '',
    spotlight_type: 'image', spotlight_url: '', spotlight_file_id: null,
    cta: [],
    social: { facebook: '', linkedin: '', twitter: '', instagram: '', whatsapp: '', youtube: '' },
    contact: { full_name: '', company_name: '', position: '', email: '', phone_code: '+880', phone: '' },
  }
}

const draft = reactive<Draft>(freshDraft())

watch(aboutRef, (el) => { if (el) el.innerHTML = draft.about || '' })

// ── API ───────────────────────────────────────────────────────────────
async function load() {
  try { partners.value = (await api<any>(`/partners?event=${id}`)).data } catch { /* */ }
}
async function loadMeta() {
  try {
    const [pkgRes, settingsRes] = await Promise.all([
      api<any>(`/partner-packages?event=${id}`),
      api<any>(`/events/${id}/settings`),
    ])
    packages.value = pkgRes.data
    filters.value  = settingsRes.data.filters || []
  } catch { /* */ }
}

// ── Open drawers ──────────────────────────────────────────────────────
function openAdd() {
  Object.assign(draft, freshDraft()); editingId.value = null
  activeTab.value = 'Details'; error.value = ''; drawerMode.value = 'add'
}

function openEdit(p: any) {
  Object.assign(draft, {
    ...freshDraft(),
    name: p.name || '', email: p.email || '',
    logo_url: p.logo_url || '', logo_file_id: p.logo_file_id ?? null,
    package_id: p.package_id || '', stall_no: p.stall_no || '', type: p.type || 'Exhibitor',
    phone_code: p.phone_code || '+880', phone: p.phone || '',
    rating: !!p.rating, featured: !!p.featured, premium: !!p.premium,
    about: p.about || '',
    street: p.street || '', city: p.city || '', state: p.state || '',
    zip: p.zip || '', country: p.country || '',
    location_url: p.location_url || '', website_url: p.website_url || '',
    tags: Array.isArray(p.tags) ? [...p.tags] : [], filter_id: p.filter_id || '',
    spotlight_type: p.spotlight_type || 'image',
    spotlight_url: p.spotlight_url || '', spotlight_file_id: p.spotlight_file_id ?? null,
    cta: Array.isArray(p.cta) ? p.cta.map((c: any) => ({ ...c, open: false })) : [],
    social: { facebook: '', linkedin: '', twitter: '', instagram: '', whatsapp: '', youtube: '', ...(p.social || {}) },
    contact: { full_name: '', company_name: '', position: '', email: '', phone_code: '+880', phone: '', ...(p.contact || {}) },
  })
  editingId.value = p.id; activeTab.value = 'Details'; error.value = ''
  drawerMode.value = 'edit'
}

// ── Save ──────────────────────────────────────────────────────────────
function buildPayload() {
  return {
    event: id, name: draft.name, email: draft.email,
    logo_file_id: draft.logo_file_id,
    package_id: draft.package_id, stall_no: draft.stall_no,
    type: draft.type.toLowerCase(),
    phone_code: draft.phone_code, phone: draft.phone,
    rating: draft.rating, featured: draft.featured, premium: draft.premium,
    about: draft.about, street: draft.street, city: draft.city,
    state: draft.state, zip: draft.zip, country: draft.country,
    location_url: draft.location_url, website_url: draft.website_url,
    tags: draft.tags, filter_id: draft.filter_id,
    spotlight_type: draft.spotlight_type, spotlight_file_id: draft.spotlight_file_id,
    cta: draft.cta, social: draft.social, contact: draft.contact,
  }
}

async function create() {
  error.value = ''; saving.value = true
  try {
    await api('/partners', { method: 'POST', body: buildPayload() })
    drawerMode.value = null; await load()
  } catch (e: any) { error.value = e?.data?.message || 'Could not create.' }
  finally { saving.value = false }
}

async function update() {
  error.value = ''; saving.value = true
  try {
    await api(`/partners/${editingId.value}`, { method: 'PUT', body: buildPayload() })
    drawerMode.value = null; await load()
  } catch (e: any) { error.value = e?.data?.message || 'Could not update.' }
  finally { saving.value = false }
}

async function remove(p: any) {
  if (!confirm(`Delete "${p.name}"?`)) return
  try { await api(`/partners/${p.id}`, { method: 'DELETE' }); await load() } catch { /* */ }
}

// ── Logo upload ───────────────────────────────────────────────────────
async function pickLogo(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]; if (!file) return
  logoUploading.value = true
  try { const r = await upload(file, { collection: 'partner_logo' }); draft.logo_url = r.url; draft.logo_file_id = r.id }
  finally { logoUploading.value = false }
}

async function pickSpotlight(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]; if (!file) return
  spotlightUploading.value = true
  try { const r = await upload(file, { collection: 'partner_spotlight' }); draft.spotlight_url = r.url; draft.spotlight_file_id = r.id }
  finally { spotlightUploading.value = false }
}

// ── Tags ──────────────────────────────────────────────────────────────
function addTag(e: KeyboardEvent) {
  if (e.key !== 'Enter') return; e.preventDefault()
  const t = tagInput.value.trim()
  if (t && !draft.tags.includes(t)) draft.tags.push(t)
  tagInput.value = ''
}

// ── CTA ───────────────────────────────────────────────────────────────
function addCta() { draft.cta.push({ id: 'cta_' + Date.now(), type: 'TEXT', label: '', value: '', open: false }) }

// ── About ─────────────────────────────────────────────────────────────
function fmtAbout(cmd: string) {
  document.execCommand(cmd, false)
  if (aboutRef.value) draft.about = aboutRef.value.innerHTML
}
function onAboutInput(e: Event) {
  draft.about = (e.target as HTMLElement).innerHTML
}

// ── Helpers ───────────────────────────────────────────────────────────
// eslint-disable-next-line eqeqeq
function packageName(pid: string | number) { return packages.value.find((p: any) => p.id == pid)?.name || (pid ? String(pid) : '—') }

function initials(name: string) {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  return (parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? parts[0]?.[1] ?? '')
}

function statusLabel(p: any) {
  const s = p.status || 'active'
  return s.charAt(0).toUpperCase() + s.slice(1)
}

function resetFilters() {
  search.value = ''; filterType.value = ''; filterPackage.value = ''; page.value = 1
}

function toggleActions(pid: string) {
  actionsOpenId.value = actionsOpenId.value === pid ? null : pid
}

onMounted(() => { load(); loadMeta() })
</script>

<template>
  <div @click="actionsOpenId = null">
    <!-- Page header -->
    <div class="mb-4">
      <h2 class="section-title m-0">Exhibitors</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Manage the exhibitors that appear in your event.</p>
    </div>

    <div class="card">
      <!-- Card header row -->
      <div class="flex items-start justify-between gap-4 mb-5">
        <div>
          <div class="font-bold text-base">Exhibitors</div>
          <div class="muted text-[.83rem] mt-0.5">Events exhibitors. Use drag and drop to rearrange the position</div>
          <div class="mt-2.5 flex flex-col gap-1.5">
            <div class="inline-flex">
              <span class="bg-brand text-white text-[.76rem] font-bold px-3 py-1 rounded-full leading-none">
                {{ partners.length }} of {{ PARTNER_LIMIT }}
              </span>
            </div>
            <div class="w-52 h-1.75 bg-line rounded-full overflow-hidden">
              <div
                class="h-full bg-brand rounded-full transition-all"
                :style="{ width: Math.min(100, Math.round((partners.length / PARTNER_LIMIT) * 100)) + '%' }"
              />
            </div>
          </div>
        </div>
        <div class="flex items-center gap-2 shrink-0 flex-wrap justify-end">
          <button class="btn ghost text-[.82rem] tracking-wide px-4 py-2">PREVIOUS EXHIBITORS</button>
          <button class="btn ghost text-[.82rem] px-4 py-2">Exhibitors Directory</button>
          <button class="btn text-[.82rem] tracking-wide px-4 py-2" @click="openAdd">
            + EXHIBITOR
          </button>
        </div>
      </div>

      <!-- Filters row -->
      <div class="flex items-center gap-3 mb-5 flex-wrap">
        <div class="relative flex-1 min-w-45 max-w-70">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted pointer-events-none">
            <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
          </svg>
          <input
            v-model="search"
            placeholder="Search Exhibitors"
            style="padding-left:2.2rem;"
          >
        </div>
        <select v-model="filterType" style="width:170px;">
          <option value="">Select Type</option>
          <option v-for="t in TYPE_OPTIONS" :key="t" :value="t.toLowerCase()">{{ t }}</option>
        </select>
        <select v-model="filterPackage" style="width:190px;">
          <option value="">Select Package</option>
          <option v-for="pkg in packages" :key="pkg.id" :value="String(pkg.id)">{{ pkg.name }}</option>
        </select>
        <button class="btn ghost text-[.82rem] tracking-wide px-4 py-2" @click="resetFilters">RESET FILTERS</button>
      </div>

      <!-- Table -->
      <table>
        <thead>
          <tr>
            <th>IMAGE</th>
            <th>NAME</th>
            <th>TYPE</th>
            <th>STATUS</th>
            <th>PACKAGE</th>
            <th>TEAM</th>
            <th>STALL NO</th>
            <th>ACTIONS</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in paginated" :key="p.id">
            <!-- Image -->
            <td>
              <div class="w-10 h-10 rounded-lg overflow-hidden shrink-0">
                <img v-if="p.logo_url" :src="p.logo_url" class="w-full h-full object-cover" :alt="p.name">
                <div v-else class="w-full h-full bg-brand-soft flex items-center justify-center text-brand font-bold text-[.78rem] uppercase">
                  {{ initials(p.name) }}
                </div>
              </div>
            </td>
            <!-- Name -->
            <td class="font-medium text-ink">{{ p.name }}</td>
            <!-- Type -->
            <td class="capitalize text-ink">{{ p.type || 'Exhibitor' }}</td>
            <!-- Status -->
            <td>
              <span
                class="font-medium text-[.9rem]"
                :class="(p.status || 'active') === 'active' ? 'text-green-600' : 'text-muted'"
              >
                {{ statusLabel(p) }}
              </span>
            </td>
            <!-- Package -->
            <td class="font-semibold text-ink">{{ packageName(p.package_id) }}</td>
            <!-- Team -->
            <td class="text-muted">{{ p.members_count ?? 0 }} of {{ p.team_limit ?? 1 }}</td>
            <!-- Stall No -->
            <td class="text-muted">{{ p.stall_no || '' }}</td>
            <!-- Actions -->
            <td>
              <div class="relative inline-block" @click.stop>
                <button
                  class="btn flex items-center gap-1.5 text-[.82rem] tracking-wide px-4 py-2"
                  @click="toggleActions(p.id)"
                >
                  ACTIONS
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-3.5 h-3.5 transition-transform" :class="actionsOpenId === p.id ? 'rotate-180' : ''">
                    <path d="M6 9l6 6 6-6"/>
                  </svg>
                </button>
                <div
                  v-if="actionsOpenId === p.id"
                  class="absolute right-0 top-full mt-1 bg-white border border-line rounded-xl shadow-lg z-20 min-w-35 overflow-hidden"
                >
                  <button
                    class="w-full text-left px-4 py-2.5 text-[.88rem] hover:bg-[#f7f8fa] text-ink transition-colors"
                    @click="openEdit(p); actionsOpenId = null"
                  >Edit</button>
                  <button
                    class="w-full text-left px-4 py-2.5 text-[.88rem] hover:bg-[#f7f8fa] text-[#dc2626] transition-colors"
                    @click="remove(p); actionsOpenId = null"
                  >Delete</button>
                </div>
              </div>
            </td>
          </tr>

          <tr v-if="!paginated.length">
            <td colspan="8" class="text-center py-12 muted">
              No exhibitors found.
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="filtered.length > 0" class="flex items-center justify-end gap-4 mt-4 pt-4 border-t border-line flex-wrap">
        <div class="flex items-center gap-2 text-[.85rem] text-muted">
          <span>Nb / page</span>
          <select v-model="perPage" style="width:64px;padding:6px 8px;font-size:.84rem;">
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
          </select>
        </div>
        <div class="flex items-center gap-2 text-[.85rem] text-muted">
          <span>Page</span>
          <select v-model="page" style="width:64px;padding:6px 8px;font-size:.84rem;">
            <option v-for="n in totalPages" :key="n" :value="n">{{ n }}</option>
          </select>
        </div>
        <span class="text-[.85rem] text-muted">{{ paginationLabel }}</span>
        <div class="flex items-center gap-1">
          <button
            class="w-7 h-7 flex items-center justify-center border border-line rounded-lg hover:bg-[#f0f0f7] disabled:opacity-40 transition-colors"
            :disabled="page <= 1"
            @click="page--"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M15 18l-6-6 6-6"/></svg>
          </button>
          <button
            class="w-7 h-7 flex items-center justify-center border border-line rounded-lg hover:bg-[#f0f0f7] disabled:opacity-40 transition-colors"
            :disabled="page >= totalPages"
            @click="page++"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M9 18l6-6-6-6"/></svg>
          </button>
        </div>
      </div>
    </div>

    <!-- ══ ADD EXHIBITOR DRAWER ═══════════════════════════════════════════ -->
    <Drawer v-if="drawerMode === 'add'" title="Add Exhibitor" @close="drawerMode = null">

      <!-- Logo uploader -->
      <div class="flex justify-center mb-5">
        <label class="relative cursor-pointer block" style="width:100%;max-width:285px;">
          <div class="w-full rounded-2xl overflow-hidden bg-[#e8eaed] flex items-center justify-center" style="height:155px;">
            <img v-if="draft.logo_url" :src="draft.logo_url" class="w-full h-full object-cover" alt="logo">
            <svg v-else viewBox="0 0 285 155" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
              <rect width="285" height="155" fill="#dde1e7"/>
              <ellipse cx="200" cy="110" rx="120" ry="60" fill="#7ec8c0"/>
              <ellipse cx="100" cy="120" rx="90" ry="50" fill="#5aa8a0"/>
              <circle cx="185" cy="55" r="28" fill="#f0b04a"/>
              <ellipse cx="75" cy="115" rx="70" ry="40" fill="#4a9890"/>
            </svg>
          </div>
          <div class="absolute inset-0 flex items-center justify-center">
            <div class="w-10 h-10 bg-white rounded-xl shadow-md flex items-center justify-center text-brand text-2xl font-light select-none">
              {{ logoUploading ? '…' : '+' }}
            </div>
          </div>
          <input type="file" accept="image/*" class="hidden" @change="pickLogo">
        </label>
      </div>

      <label>Exhibitor Name</label>
      <input v-model="draft.name" placeholder="Enter the exhibitor Name">

      <label>Exhibitor Email</label>
      <input v-model="draft.email" type="email" placeholder="Enter the exhibitor email">

      <label>Package</label>
      <select v-model="draft.package_id">
        <option value="">Select Package</option>
        <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
      </select>

      <label>Stall No</label>
      <select v-model="draft.stall_no">
        <option value="">Select Stall No</option>
        <option v-for="s in STALL_OPTIONS" :key="s" :value="s">{{ s }}</option>
      </select>

      <label>Type</label>
      <select v-model="draft.type">
        <option value="">Select Type</option>
        <option v-for="t in TYPE_OPTIONS" :key="t" :value="t">{{ t }}</option>
      </select>

      <!-- Flags row -->
      <div class="flex items-center gap-5 my-3">
        <label class="flex items-center gap-2 m-0 cursor-pointer text-[.92rem] text-ink">
          <input v-model="draft.rating" type="checkbox" class="w-4.25 h-4.25 m-0 accent-brand"> Rating
        </label>
        <label class="flex items-center gap-2 m-0 cursor-pointer text-[.92rem] text-ink">
          <input v-model="draft.featured" type="checkbox" class="w-4.25 h-4.25 m-0 accent-brand"> Featured
        </label>
        <label class="flex items-center gap-2 m-0 cursor-pointer text-[.92rem] text-ink">
          <input v-model="draft.premium" type="checkbox" class="w-4.25 h-4.25 m-0 accent-brand"> Premium
        </label>
      </div>

      <!-- Contact details -->
      <div class="mt-4 mb-2">
        <p class="font-semibold text-[.92rem] text-ink m-0">Contact details <span class="muted font-normal">(For internal use only)</span></p>
      </div>

      <label>Full name</label>
      <input v-model="draft.contact.full_name" placeholder="Enter Full name">

      <label>Company name</label>
      <input v-model="draft.contact.company_name" placeholder="Enter Company name">

      <label>Position</label>
      <input v-model="draft.contact.position" placeholder="Enter Position">

      <label>Email address</label>
      <input v-model="draft.contact.email" type="email" placeholder="Enter Email address">

      <label>Mobile number</label>
      <div class="flex items-center rounded-[11px] overflow-hidden border border-[#d7dae1] my-1.5 bg-white focus-within:border-brand" style="transition:border-color .15s">
        <select v-model="draft.contact.phone_code" style="border:0;box-shadow:none;margin:0;border-radius:0;background:#f7f8fa;width:auto;padding:10px 8px;border-right:1px solid #d7dae1;cursor:pointer;">
          <option v-for="p in PHONE_CODES" :key="p.code" :value="p.code">{{ p.flag }} {{ p.code }}</option>
        </select>
        <input v-model="draft.contact.phone" type="tel" placeholder="Enter a phone number" style="border:0;box-shadow:none;margin:0;border-radius:0;flex:1;outline:none;">
      </div>

      <p v-if="error" class="error mt-2">{{ error }}</p>

      <div class="pt-4 mt-2">
        <button class="btn w-full py-3 text-[.95rem] tracking-widest" :disabled="saving || !draft.name.trim()" @click="create">
          {{ saving ? 'CREATING…' : 'CREATE' }}
        </button>
      </div>
    </Drawer>

    <!-- ══ EDIT EXHIBITOR DRAWER ══════════════════════════════════════════ -->
    <Drawer v-if="drawerMode === 'edit'" :key="'edit-' + editingId" title="Edit Exhibitor" @close="drawerMode = null">

      <!-- Sticky tabs -->
      <div class="sticky top-0 bg-white z-10 -mx-5.5 px-5.5 border-b border-line mb-4" style="margin-top:-22px;padding-top:4px;">
        <div class="flex gap-0 overflow-x-auto">
          <button
            v-for="tab in TABS" :key="tab"
            class="px-3.5 py-3 text-[.88rem] font-[550] whitespace-nowrap border-b-2 transition-colors"
            :class="activeTab === tab ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink'"
            @click="activeTab = tab"
          >{{ tab }}</button>
        </div>
      </div>

      <!-- ── DETAILS TAB ─────────────────────────────────────────────── -->
      <template v-if="activeTab === 'Details'">

        <!-- Logo uploader -->
        <div class="flex justify-center mb-5">
          <label class="relative cursor-pointer block" style="width:100%;max-width:285px;">
            <div class="w-full rounded-2xl overflow-hidden bg-[#e8eaed] flex items-center justify-center" style="height:155px;">
              <img v-if="draft.logo_url" :src="draft.logo_url" class="w-full h-full object-cover" alt="logo">
              <svg v-else viewBox="0 0 285 155" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                <rect width="285" height="155" fill="#dde1e7"/>
                <ellipse cx="200" cy="110" rx="120" ry="60" fill="#7ec8c0"/>
                <ellipse cx="100" cy="120" rx="90" ry="50" fill="#5aa8a0"/>
                <circle cx="185" cy="55" r="28" fill="#f0b04a"/>
                <ellipse cx="75" cy="115" rx="70" ry="40" fill="#4a9890"/>
              </svg>
            </div>
            <div class="absolute inset-0 flex items-center justify-center">
              <div class="w-10 h-10 bg-white rounded-xl shadow-md flex items-center justify-center text-brand text-2xl font-light select-none">
                {{ logoUploading ? '…' : '+' }}
              </div>
            </div>
            <input type="file" accept="image/*" class="hidden" @change="pickLogo">
          </label>
        </div>

        <label>Exhibitor Name</label>
        <input v-model="draft.name" placeholder="Enter the exhibitor Name">

        <label>Exhibitor Email</label>
        <input v-model="draft.email" type="email" placeholder="Enter the exhibitor email">

        <label>Package</label>
        <select v-model="draft.package_id">
          <option value="">Select Package</option>
          <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.name }}</option>
        </select>

        <label>Mobile number</label>
        <div class="flex items-center rounded-[11px] overflow-hidden border border-[#d7dae1] my-1.5 bg-white focus-within:border-brand">
          <select v-model="draft.phone_code" style="border:0;box-shadow:none;margin:0;border-radius:0;background:#f7f8fa;width:auto;padding:10px 8px;border-right:1px solid #d7dae1;cursor:pointer;">
            <option v-for="p in PHONE_CODES" :key="p.code" :value="p.code">{{ p.flag }} {{ p.code }}</option>
          </select>
          <input v-model="draft.phone" type="tel" placeholder="Enter a phone number" style="border:0;box-shadow:none;margin:0;border-radius:0;flex:1;outline:none;">
        </div>

        <label>Stall No</label>
        <select v-model="draft.stall_no">
          <option value="">Select Stall No</option>
          <option v-for="s in STALL_OPTIONS" :key="s" :value="s">{{ s }}</option>
        </select>

        <label>Type</label>
        <select v-model="draft.type">
          <option value="">Select Type</option>
          <option v-for="t in TYPE_OPTIONS" :key="t" :value="t">{{ t }}</option>
        </select>

        <!-- About (rich text) -->
        <div class="flex items-center gap-1 mt-3 mb-1">
          <label class="m-0 flex-1">About</label>
        </div>
        <div class="border border-line rounded-xl overflow-hidden my-1.5">
          <div class="flex items-center gap-0.5 px-3 py-2 bg-[#f7f8fa] border-b border-line">
            <button type="button" class="w-7 h-7 font-bold text-ink hover:bg-line rounded text-[.9rem]" @click="fmtAbout('bold')">B</button>
            <button type="button" class="w-7 h-7 italic text-ink hover:bg-line rounded text-[.9rem]" @click="fmtAbout('italic')">I</button>
            <button type="button" class="w-7 h-7 underline text-ink hover:bg-line rounded text-[.9rem]" @click="fmtAbout('underline')">U</button>
            <button type="button" class="w-7 h-7 line-through text-ink hover:bg-line rounded text-[.9rem]" @click="fmtAbout('strikeThrough')">S</button>
          </div>
          <div
            ref="aboutRef"
            contenteditable="true"
            class="min-h-30 p-3 text-[.93rem] text-ink outline-none"
            @input="onAboutInput"
          />
        </div>

        <label>Street address</label>
        <input v-model="draft.street" placeholder="Enter Street address">

        <label>City</label>
        <input v-model="draft.city" placeholder="Enter City">

        <div class="flex gap-3">
          <div class="flex-1">
            <label>State</label>
            <input v-model="draft.state" placeholder="Enter State">
          </div>
          <div class="flex-1">
            <label>ZIP code</label>
            <input v-model="draft.zip" placeholder="Enter Zip Code">
          </div>
        </div>

        <label>Country</label>
        <select v-model="draft.country">
          <option value="">Select Country</option>
          <option v-for="c in COUNTRIES" :key="c" :value="c">{{ c }}</option>
        </select>

        <label>Location</label>
        <input v-model="draft.location_url" placeholder="URL of the venue location">

        <label>Website</label>
        <input v-model="draft.website_url" placeholder="URL of the website">

        <!-- Custom Tags -->
        <label>Custom Tags</label>
        <div class="border border-line rounded-[11px] px-3 pt-2 pb-1.5 my-1.5 bg-white flex flex-wrap gap-1.5 min-h-11">
          <span
            v-for="tag in draft.tags" :key="tag"
            class="inline-flex items-center gap-1 bg-brand-soft text-brand-dark text-[.8rem] font-semibold px-2.5 py-0.5 rounded-full"
          >
            {{ tag }}
            <button type="button" class="border-0 bg-transparent cursor-pointer text-brand-dark font-bold leading-none p-0" @click="draft.tags = draft.tags.filter(t => t !== tag)">×</button>
          </span>
          <input
            v-model="tagInput"
            placeholder="Add tag & press enter"
            style="border:0;box-shadow:none;margin:0;padding:0;flex:1;min-width:120px;outline:none;background:transparent;"
            @keydown="addTag"
          >
        </div>

        <!-- Manage Filters -->
        <label>Manage filters</label>
        <select v-model="draft.filter_id">
          <option value="">Main filter title</option>
          <option v-for="f in filters" :key="f.id" :value="f.id">{{ f.title }}</option>
        </select>

        <!-- Spotlight Banner -->
        <div class="mt-3 mb-1.5">
          <label class="block mb-2">Spotlight Banner</label>
          <div class="flex gap-5">
            <label class="flex items-center gap-2 m-0 cursor-pointer text-[.92rem] text-ink">
              <input v-model="draft.spotlight_type" type="radio" value="image" class="w-4.25 h-4.25 m-0 accent-brand"> Image
            </label>
            <label class="flex items-center gap-2 m-0 cursor-pointer text-[.92rem] text-ink">
              <input v-model="draft.spotlight_type" type="radio" value="video" class="w-4.25 h-4.25 m-0 accent-brand"> Video
            </label>
          </div>
        </div>
        <label class="uploader mt-1.5" style="height:130px;">
          <img v-if="draft.spotlight_url" :src="draft.spotlight_url" alt="">
          <span v-else class="text-[.88rem]">{{ spotlightUploading ? 'Uploading…' : '+ Click to upload' }}</span>
          <input type="file" :accept="draft.spotlight_type === 'image' ? 'image/*' : 'video/*'" @change="pickSpotlight">
        </label>

        <!-- CTA -->
        <div class="flex items-center justify-between mt-4 mb-2">
          <label class="m-0 text-ink font-semibold text-[.92rem]">CTA</label>
          <button class="btn sm" @click="addCta">ADD CTA</button>
        </div>
        <div v-for="(cta, i) in draft.cta" :key="cta.id" class="border border-line rounded-xl mb-2 overflow-hidden">
          <div class="flex items-center gap-2 px-4 py-3 bg-[#f7f8fa] cursor-pointer" @click="cta.open = !cta.open">
            <span class="font-bold text-[.9rem]">CTA {{ i + 1 }}</span>
            <span class="bg-white border border-line rounded px-2 py-0.5 text-[.78rem] font-semibold">{{ cta.type }}</span>
            <div class="flex-1" />
            <button type="button" class="border-0 bg-transparent cursor-pointer text-[#dc2626] p-1" @click.stop="draft.cta.splice(i,1)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
            </button>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 text-muted transition-transform" :class="cta.open ? 'rotate-180' : ''"><path d="M6 9l6 6 6-6"/></svg>
          </div>
          <div v-if="cta.open" class="p-4 border-t border-line">
            <label>Type</label>
            <select v-model="cta.type">
              <option>TEXT</option><option>LINK</option><option>BUTTON</option>
            </select>
            <label>Label</label>
            <input v-model="cta.label" placeholder="Button label">
            <label>Value / URL</label>
            <input v-model="cta.value" placeholder="Link or text value">
          </div>
        </div>

        <!-- Social Links -->
        <label class="mt-2">Social Links</label>

        <div class="flex items-center border border-line rounded-[11px] overflow-hidden my-1.5 bg-white">
          <input v-model="draft.social.facebook" placeholder="Facebook URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
          <div class="w-10 h-10 flex items-center justify-center shrink-0 border-l border-line bg-[#f7f8fa]">
            <svg viewBox="0 0 24 24" class="w-5 h-5 text-[#6b7280]" fill="currentColor"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
          </div>
        </div>

        <div class="flex items-center border border-line rounded-[11px] overflow-hidden my-1.5 bg-white">
          <input v-model="draft.social.linkedin" placeholder="Linkedin URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
          <div class="w-10 h-10 flex items-center justify-center shrink-0 border-l border-line bg-[#f7f8fa]">
            <svg viewBox="0 0 24 24" class="w-5 h-5 text-[#6b7280]" fill="currentColor"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/></svg>
          </div>
        </div>

        <div class="flex items-center border border-line rounded-[11px] overflow-hidden my-1.5 bg-white">
          <input v-model="draft.social.twitter" placeholder="Twitter URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
          <div class="w-10 h-10 flex items-center justify-center shrink-0 border-l border-line bg-[#f7f8fa]">
            <svg viewBox="0 0 24 24" class="w-5 h-5 text-[#6b7280]" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.737-8.858L1.999 2.25H8.056l4.261 5.638L18.244 2.25z"/></svg>
          </div>
        </div>

        <div class="flex items-center border border-line rounded-[11px] overflow-hidden my-1.5 bg-white">
          <input v-model="draft.social.instagram" placeholder="Instagram URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
          <div class="w-10 h-10 flex items-center justify-center shrink-0 border-l border-line bg-[#f7f8fa]">
            <svg viewBox="0 0 24 24" class="w-5 h-5 text-[#6b7280]" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
          </div>
        </div>

        <div class="flex items-center border border-line rounded-[11px] overflow-hidden my-1.5 bg-white">
          <span class="px-3 py-2.5 text-[.82rem] font-semibold text-muted bg-[#f7f8fa] border-r border-line whitespace-nowrap shrink-0">https://wa.me/</span>
          <input v-model="draft.social.whatsapp" placeholder="Enter WhatsApp number" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
          <div class="w-10 h-10 flex items-center justify-center shrink-0 border-l border-line bg-[#f7f8fa]">
            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="#25d366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          </div>
        </div>

        <div class="flex items-center border border-line rounded-[11px] overflow-hidden my-1.5 bg-white">
          <input v-model="draft.social.youtube" placeholder="YouTube URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
          <div class="w-10 h-10 flex items-center justify-center shrink-0 border-l border-line bg-[#f7f8fa]">
            <svg viewBox="0 0 24 24" class="w-5 h-5 text-[#6b7280]" fill="currentColor"><path d="M22.54 6.42a2.78 2.78 0 00-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 00-1.95 1.96A29 29 0 001 12a29 29 0 00.46 5.58A2.78 2.78 0 003.41 19.6C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 001.95-1.95A29 29 0 0023 12a29 29 0 00-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="white"/></svg>
          </div>
        </div>

        <!-- Flags row -->
        <div class="flex items-center gap-5 mt-4 mb-3">
          <label class="flex items-center gap-2 m-0 cursor-pointer text-[.92rem] text-ink">
            <input v-model="draft.rating" type="checkbox" class="w-4.25 h-4.25 m-0 accent-brand"> Rating
          </label>
          <label class="flex items-center gap-2 m-0 cursor-pointer text-[.92rem] text-ink">
            <input v-model="draft.featured" type="checkbox" class="w-4.25 h-4.25 m-0 accent-brand"> Featured
          </label>
          <label class="flex items-center gap-2 m-0 cursor-pointer text-[.92rem] text-ink">
            <input v-model="draft.premium" type="checkbox" class="w-4.25 h-4.25 m-0 accent-brand"> Premium
          </label>
        </div>

        <!-- Contact details -->
        <div class="mt-3 mb-2">
          <p class="font-semibold text-[.92rem] text-ink m-0">Contact details <span class="muted font-normal">(For internal use only)</span></p>
        </div>

        <label>Full name</label>
        <input v-model="draft.contact.full_name" placeholder="Enter Full name">

        <label>Company name</label>
        <input v-model="draft.contact.company_name" placeholder="Enter Company name">

        <label>Position</label>
        <input v-model="draft.contact.position" placeholder="Enter Position">

        <label>Email address</label>
        <input v-model="draft.contact.email" type="email" placeholder="Enter Email address">

        <label>Mobile number</label>
        <div class="flex items-center rounded-[11px] overflow-hidden border border-[#d7dae1] my-1.5 bg-white focus-within:border-brand">
          <select v-model="draft.contact.phone_code" style="border:0;box-shadow:none;margin:0;border-radius:0;background:#f7f8fa;width:auto;padding:10px 8px;border-right:1px solid #d7dae1;cursor:pointer;">
            <option v-for="p in PHONE_CODES" :key="p.code" :value="p.code">{{ p.flag }} {{ p.code }}</option>
          </select>
          <input v-model="draft.contact.phone" type="tel" placeholder="Enter a phone number" style="border:0;box-shadow:none;margin:0;border-radius:0;flex:1;outline:none;">
        </div>

        <p v-if="error" class="error mt-2">{{ error }}</p>

        <div class="flex gap-2 pt-4 mt-2">
          <button class="btn danger px-5 py-3 text-[.92rem]" @click="remove({ id: editingId, name: draft.name })">DELETE</button>
          <button class="btn flex-1 py-3 text-[.95rem] tracking-widest" :disabled="saving || !draft.name.trim()" @click="update">
            {{ saving ? 'UPDATING…' : 'UPDATE' }}
          </button>
        </div>
      </template>

      <!-- ── MEMBERS TAB ─────────────────────────────────────────────── -->
      <template v-else-if="activeTab === 'Members'">
        <p class="muted text-center py-10">Member management coming soon.</p>
      </template>

      <!-- ── DOCUMENTS TAB ──────────────────────────────────────────── -->
      <template v-else-if="activeTab === 'Documents'">
        <p class="muted text-center py-10">No documents uploaded yet.</p>
      </template>

      <!-- ── PROJECTS TAB ───────────────────────────────────────────── -->
      <template v-else-if="activeTab === 'Projects'">
        <p class="muted text-center py-10">No projects added yet.</p>
      </template>

      <!-- ── PRODUCTS TAB ───────────────────────────────────────────── -->
      <template v-else-if="activeTab === 'Products'">
        <p class="muted text-center py-10">No products added yet.</p>
      </template>

      <!-- ── PERMISSIONS TAB ────────────────────────────────────────── -->
      <template v-else-if="activeTab === 'Permissions'">
        <p class="muted text-center py-10">Permission settings coming soon.</p>
      </template>

    </Drawer>
  </div>
</template>
