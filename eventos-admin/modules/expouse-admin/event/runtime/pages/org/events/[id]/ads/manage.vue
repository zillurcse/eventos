<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const { upload } = useUpload()
const id = route.params.id as string

const MAX_PER_PLACEMENT = 4
const MAX_IMAGES = 5

const PLACEMENTS = [
  { key: 'main',     title: 'Event Main Ad',     desc: 'Create and manage advertisements that will be displayed on your event all pages.' },
  { key: 'featured', title: 'Event featured Ad', desc: 'Create and manage advertisements that will be displayed on your event featured ads.' },
  { key: 'content',  title: 'Event Content Ad',  desc: 'Create and manage advertisements that will be displayed on your event sessions page.' },
]
const GROUPS: [string, string][] = [
  ['attendees', 'All Attendees'], ['vip', 'VIP Members'], ['speakers', 'Speakers'],
  ['exhibitors', 'Exhibitors'], ['sponsors', 'Sponsors'], ['organizers', 'Organizers'],
]
const PAGES: [string, string][] = [
  ['reception', 'Reception Page'], ['feed', 'Event Feed'], ['delegates', 'Delegates Page'],
  ['speakers', 'Speakers Page'], ['exhibitors', 'Exhibitors Page'], ['sponsors', 'Sponsors Page'],
  ['sessions', 'Sessions Page'], ['meetings', 'Meetings'], ['lounge', 'Lounge'], ['rooms', 'Rooms'],
]
const REDIRECT_TYPES: [string, string][] = [
  ['none', 'No redirect'], ['url', 'External URL'],
  ['exhibitor', 'Exhibitor'], ['session', 'Session'], ['speaker', 'Speaker'],
]

interface AdImage {
  name: string
  image_url: string | null
  redirect_type: string
  redirect_target_id: string
  redirect_target_label: string
  is_active: boolean
  _open?: boolean
}
interface Ad {
  id: number
  placement: string
  title: string
  is_active: boolean
  images: AdImage[]
  targeted_groups: string[]
  targeted_pages: string[]
}

const ads        = ref<Ad[]>([])
const exhibitors = ref<any[]>([])
const sessions   = ref<any[]>([])
const speakers   = ref<any[]>([])
const loading    = ref(true)

const adsByPlacement = computed<Record<string, Ad[]>>(() => {
  const m: Record<string, Ad[]> = { main: [], featured: [], content: [] }
  for (const a of ads.value) (m[a.placement] ??= []).push(a)
  return m
})

async function load() {
  loading.value = true
  try {
    const [adsRes, exhRes, sesRes, spkRes] = await Promise.all([
      api<any>(`/events/${id}/ads`),
      api<any>(`/exhibitors?event=${id}`),
      api<any>(`/sessions?event=${id}`),
      api<any>(`/events/${id}/speakers`),
    ])
    ads.value        = adsRes.data
    exhibitors.value = exhRes.data
    sessions.value   = sesRes.data
    speakers.value   = spkRes.data
  } catch { /* */ } finally { loading.value = false }
}

// ── Drawer (create / edit) ────────────────────────────────────────────────
const drawer = reactive({ open: false, mode: 'create' as 'create' | 'edit', placement: 'main', adId: 0 })
const saving = ref(false)
const error  = ref('')

function freshForm() {
  return {
    title: '', is_active: true,
    images: [] as AdImage[],
    targeted_groups: [] as string[],
    targeted_pages: [] as string[],
  }
}
const form = reactive(freshForm())

function blankImage(): AdImage {
  return { name: '', image_url: null, redirect_type: 'none', redirect_target_id: '', redirect_target_label: '', is_active: true, _open: true }
}

function openCreate(placement: string) {
  Object.assign(form, freshForm())
  form.images.push(blankImage())
  drawer.mode = 'create'; drawer.placement = placement; drawer.adId = 0
  error.value = ''; drawer.open = true
}
function openEdit(ad: Ad) {
  Object.assign(form, {
    title: ad.title, is_active: ad.is_active,
    images: (ad.images || []).map(i => ({ ...blankImage(), ...i, _open: false })),
    targeted_groups: [...(ad.targeted_groups || [])],
    targeted_pages: [...(ad.targeted_pages || [])],
  })
  if (!form.images.length) form.images.push(blankImage())
  drawer.mode = 'edit'; drawer.placement = ad.placement; drawer.adId = ad.id
  error.value = ''; drawer.open = true
}

function addImage() { if (form.images.length < MAX_IMAGES) form.images.push(blankImage()) }
function removeImage(i: number) { form.images.splice(i, 1) }

function targetOptions(type: string): { id: string, label: string }[] {
  if (type === 'exhibitor') return exhibitors.value.map(e => ({ id: String(e.id), label: e.name }))
  if (type === 'session')   return sessions.value.map(s => ({ id: String(s.id), label: s.title }))
  if (type === 'speaker')   return speakers.value.map(s => ({ id: String(s.id), label: s.name }))
  return []
}
function onTypeChange(img: AdImage) { img.redirect_target_id = ''; img.redirect_target_label = '' }
function onTargetPick(img: AdImage) {
  const opt = targetOptions(img.redirect_type).find(o => o.id === String(img.redirect_target_id))
  img.redirect_target_label = opt?.label || ''
}

async function uploadImage(e: Event, img: AdImage) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  try { const r = await upload(file, { collection: 'ad_image' }); img.image_url = r.url }
  catch { toast.error('Could not upload image.') }
}

// Select-all helpers
function allSelected(list: [string, string][], sel: string[]) { return list.length > 0 && list.every(([k]) => sel.includes(k)) }
function toggleAll(list: [string, string][], sel: string[], field: 'targeted_groups' | 'targeted_pages') {
  form[field] = allSelected(list, sel) ? [] : list.map(([k]) => k)
}
function toggleKey(key: string, field: 'targeted_groups' | 'targeted_pages') {
  const arr = form[field]
  const i = arr.indexOf(key)
  if (i >= 0) arr.splice(i, 1); else arr.push(key)
}

async function save() {
  if (!form.title.trim()) { error.value = 'Please enter an ad title.'; return }
  error.value = ''; saving.value = true
  const body = {
    placement: drawer.placement,
    title: form.title.trim(),
    is_active: form.is_active,
    images: form.images.filter(i => i.image_url).map(({ _open, ...rest }) => rest),
    targeted_groups: form.targeted_groups,
    targeted_pages: form.targeted_pages,
  }
  try {
    if (drawer.mode === 'create') await api(`/events/${id}/ads`, { method: 'POST', body })
    else await api(`/ads/${drawer.adId}`, { method: 'PUT', body })
    await load()
    drawer.open = false
    toast.success(drawer.mode === 'create' ? 'Ad created' : 'Ad updated')
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save ad.'
    toast.error(error.value)
  } finally { saving.value = false }
}

async function toggleActive(ad: Ad) {
  try {
    const res = await api<any>(`/ads/${ad.id}`, { method: 'PATCH', body: { is_active: !ad.is_active } })
    const idx = ads.value.findIndex(a => a.id === ad.id)
    if (idx >= 0) ads.value[idx] = res.data
  } catch (e: any) { toast.error(e?.data?.message || 'Could not update status.') }
}

async function remove(ad: Ad) {
  if (!confirm(`Delete "${ad.title}"?`)) return
  try {
    await api(`/ads/${ad.id}`, { method: 'DELETE' })
    ads.value = ads.value.filter(a => a.id !== ad.id)
    toast.success('Ad deleted')
  } catch (e: any) { toast.error(e?.data?.message || 'Could not delete ad.') }
}

onMounted(load)
</script>

<template>
  <div class="max-w-[1100px]">
    <div class="card">
      <div class="mb-2">
        <div class="font-bold text-base">Event Advertising</div>
        <div class="muted text-[.85rem] mt-0.5">Manage and control your event advertisements.</div>
      </div>

      <div v-if="loading" class="muted text-center py-12">Loading ads…</div>

      <template v-else>
        <div
          v-for="(p, pi) in PLACEMENTS" :key="p.key"
          class="py-6" :class="pi ? 'border-t border-line' : ''"
        >
          <div class="flex items-start justify-between gap-6 flex-wrap">
            <!-- Left: title + desc + create -->
            <div class="max-w-[420px]">
              <div class="font-semibold text-ink text-[1.02rem]">{{ p.title }}</div>
              <p class="muted text-[.84rem] mt-1">
                {{ p.desc }} You can create up to {{ MAX_PER_PLACEMENT }} different ads with targeted groups.
              </p>
              <button
                class="btn ghost mt-3 text-[.8rem] tracking-wide"
                :disabled="(adsByPlacement[p.key]?.length || 0) >= MAX_PER_PLACEMENT"
                @click="openCreate(p.key)"
              >
                + CREATE {{ p.title.replace('Event ', '').toUpperCase() }}
              </button>
            </div>

            <!-- Right: ad cards -->
            <div class="flex-1 min-w-[320px] flex flex-col gap-3">
              <div
                v-for="ad in adsByPlacement[p.key]" :key="ad.id"
                class="flex items-center gap-3 border border-line rounded-xl p-2.5"
              >
                <div class="w-24 h-14 rounded-lg overflow-hidden bg-[#f1f1f5] shrink-0 flex items-center justify-center">
                  <img v-if="ad.images?.[0]?.image_url" :src="ad.images[0].image_url" class="w-full h-full object-cover" :alt="ad.title">
                  <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-6 h-6 text-muted"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="font-medium text-ink truncate">{{ ad.title }}</div>
                  <div class="text-[.76rem] text-muted">{{ (ad.images?.length || 0) }} image{{ (ad.images?.length || 0) !== 1 ? 's' : '' }}</div>
                </div>
                <button
                  type="button" role="switch" :aria-checked="ad.is_active"
                  class="relative w-10 h-6 rounded-full transition-colors shrink-0"
                  :class="ad.is_active ? 'bg-[#6352e7]' : 'bg-gray-300'"
                  :title="ad.is_active ? 'Active' : 'Inactive'"
                  @click="toggleActive(ad)"
                >
                  <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform" :class="ad.is_active ? 'translate-x-4' : ''" />
                </button>
                <button class="btn ghost text-[.8rem] px-3 py-1.5" @click="openEdit(ad)">Edit</button>
                <button class="text-[#dc2626] text-[.82rem] font-medium px-2 hover:underline" @click="remove(ad)">Delete</button>
              </div>

              <div v-if="!(adsByPlacement[p.key]?.length)" class="text-muted text-[.84rem] border border-dashed border-line rounded-xl p-4 text-center">
                No ads yet for this placement.
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- ── Create / Edit Drawer ─────────────────────────────────────────── -->
    <Drawer v-if="drawer.open" :title="`${drawer.mode === 'create' ? 'Create' : 'Edit'} ${drawer.placement} ad`" @close="drawer.open = false">

      <!-- Ad title -->
      <div class="mb-4">
        <label class="block mb-1.5">Ad Title <span class="text-[#dc2626]">*</span></label>
        <input v-model="form.title" placeholder="e.g. Main Ad" class="m-0">
      </div>

      <!-- Images -->
      <div class="mb-5">
        <div class="flex items-center justify-between mb-1.5">
          <label class="m-0">Ad Images <span class="text-[#dc2626]">*</span></label>
          <button
            type="button" class="text-[#6352e7] text-[.84rem] font-semibold disabled:opacity-40"
            :disabled="form.images.length >= MAX_IMAGES"
            @click="addImage"
          >+ Add Image ({{ form.images.length }}/{{ MAX_IMAGES }})</button>
        </div>

        <div v-for="(img, i) in form.images" :key="i" class="border border-line rounded-xl mb-2.5 overflow-hidden">
          <!-- header -->
          <div class="flex items-center gap-2 px-3 py-2 bg-[#fafbfc] cursor-pointer" @click="img._open = !img._open">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 text-muted transition-transform" :class="img._open ? 'rotate-180' : ''"><path d="M6 9l6 6 6-6"/></svg>
            <input v-model="img.name" placeholder="Image label" class="m-0 flex-1 bg-transparent border-0 text-[.86rem] font-medium" @click.stop>
            <span class="px-2 py-0.5 rounded-full text-[.7rem] font-semibold" :class="img.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'">
              {{ img.is_active ? 'Active' : 'Inactive' }}
            </span>
            <button type="button" class="text-[#dc2626] px-1" @click.stop="removeImage(i)">✕</button>
          </div>

          <!-- body -->
          <div v-if="img._open" class="p-3 flex flex-col gap-3">
            <div>
              <label class="block mb-1.5">Image URL <span class="text-[#dc2626]">*</span></label>
              <div class="rounded-lg overflow-hidden border border-line bg-[#f7f8fa] aspect-[2/1] flex items-center justify-center relative">
                <img v-if="img.image_url" :src="img.image_url" class="w-full h-full object-cover">
                <span v-else class="text-muted text-[.84rem]">No image</span>
              </div>
              <label class="btn ghost mt-2 text-[.8rem] inline-flex cursor-pointer">
                <input type="file" accept="image/*" class="hidden" @change="uploadImage($event, img)">
                {{ img.image_url ? 'Replace image' : 'Upload image' }}
              </label>
            </div>

            <div>
              <label class="block mb-1.5">Redirect Type</label>
              <select v-model="img.redirect_type" class="m-0 w-full" @change="onTypeChange(img)">
                <option v-for="[v, l] in REDIRECT_TYPES" :key="v" :value="v">{{ l }}</option>
              </select>
            </div>

            <div v-if="img.redirect_type === 'url'">
              <label class="block mb-1.5">External URL</label>
              <input v-model="img.redirect_target_id" placeholder="https://…" class="m-0" @input="img.redirect_target_label = img.redirect_target_id">
            </div>
            <div v-else-if="img.redirect_type !== 'none'">
              <label class="block mb-1.5 capitalize">Select {{ img.redirect_type }}</label>
              <select v-model="img.redirect_target_id" class="m-0 w-full" @change="onTargetPick(img)">
                <option value="">— Select —</option>
                <option v-for="o in targetOptions(img.redirect_type)" :key="o.id" :value="o.id">{{ o.label }}</option>
              </select>
            </div>

            <label class="flex items-center justify-between">
              <span class="text-[.9rem] font-medium text-ink">Status</span>
              <button
                type="button" role="switch" :aria-checked="img.is_active"
                class="relative w-10 h-6 rounded-full transition-colors"
                :class="img.is_active ? 'bg-[#6352e7]' : 'bg-gray-300'"
                @click="img.is_active = !img.is_active"
              >
                <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform" :class="img.is_active ? 'translate-x-4' : ''" />
              </button>
            </label>
          </div>
        </div>
      </div>

      <!-- Targeting -->
      <div class="flex gap-8 mb-4 flex-wrap">
        <div class="flex-1 min-w-[180px]">
          <div class="font-semibold text-ink text-[.92rem] mb-2">Targeted Groups</div>
          <label class="flex items-center gap-2 mb-1.5 cursor-pointer text-[.88rem] text-ink">
            <input type="checkbox" class="accent-[#6352e7] w-4 h-4" :checked="allSelected(GROUPS, form.targeted_groups)" @change="toggleAll(GROUPS, form.targeted_groups, 'targeted_groups')">
            <span class="font-medium">Select All</span>
          </label>
          <label v-for="[k, l] in GROUPS" :key="k" class="flex items-center gap-2 mb-1.5 cursor-pointer text-[.88rem] text-ink">
            <input type="checkbox" class="accent-[#6352e7] w-4 h-4" :checked="form.targeted_groups.includes(k)" @change="toggleKey(k, 'targeted_groups')">
            {{ l }}
          </label>
        </div>
        <div class="flex-1 min-w-[180px]">
          <div class="font-semibold text-ink text-[.92rem] mb-2">Targeted Pages</div>
          <label class="flex items-center gap-2 mb-1.5 cursor-pointer text-[.88rem] text-ink">
            <input type="checkbox" class="accent-[#6352e7] w-4 h-4" :checked="allSelected(PAGES, form.targeted_pages)" @change="toggleAll(PAGES, form.targeted_pages, 'targeted_pages')">
            <span class="font-medium">Select All</span>
          </label>
          <label v-for="[k, l] in PAGES" :key="k" class="flex items-center gap-2 mb-1.5 cursor-pointer text-[.88rem] text-ink">
            <input type="checkbox" class="accent-[#6352e7] w-4 h-4" :checked="form.targeted_pages.includes(k)" @change="toggleKey(k, 'targeted_pages')">
            {{ l }}
          </label>
        </div>
      </div>
      <p class="muted text-[.8rem] mb-4">Select specific user groups / pages to target. If none selected, the ad will be shown to all users on all pages.</p>

      <p v-if="error" class="error">{{ error }}</p>

      <div class="modal-actions border-t border-line pt-4 mt-2">
        <button class="btn ghost" @click="drawer.open = false">CANCEL</button>
        <button class="btn" :disabled="saving || !form.title.trim()" @click="save">
          {{ saving ? 'Saving…' : (drawer.mode === 'create' ? 'CREATE' : 'UPDATE') }}
        </button>
      </div>
    </Drawer>
  </div>
</template>
