<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface Banner {
  id: string
  name: string
  url: string
  image_file_id: number | null
  image_url: string | null
}

const banners = ref<Banner[]>([])
const drawerOpen = ref(false)
const editingId = ref<string | null>(null)
const saved = ref(false)

const draft = reactive<Banner>({ id: '', name: '', url: '', image_file_id: null, image_url: null })

async function load() {
  try { banners.value = (await api<any>(`/events/${id}/settings`)).data.banners || [] } catch { /* */ }
}

async function persist() {
  await api(`/events/${id}/settings`, { method: 'PUT', body: { banners: JSON.parse(JSON.stringify(banners.value)) } })
  saved.value = true; setTimeout(() => (saved.value = false), 1500)
}

function openAdd() {
  editingId.value = null
  Object.assign(draft, { id: 'b' + Date.now(), name: '', url: '', image_file_id: null, image_url: null })
  drawerOpen.value = true
}

function openEdit(b: Banner) {
  editingId.value = b.id
  Object.assign(draft, { ...b })
  drawerOpen.value = true
}

async function saveDraft() {
  if (!draft.image_file_id) return
  const clean: Banner = JSON.parse(JSON.stringify(draft))
  if (editingId.value) {
    const i = banners.value.findIndex(b => b.id === editingId.value)
    if (i >= 0) banners.value[i] = clean
  } else {
    banners.value.push(clean)
  }
  await persist()
  drawerOpen.value = false
}

async function removeBanner(b: Banner) {
  if (!confirm(`Remove banner "${b.name || 'this banner'}"?`)) return
  banners.value = banners.value.filter(x => x.id !== b.id)
  await persist()
}

function onImageChange(v: string | string[] | null) {
  draft.image_url = Array.isArray(v) ? v[0] ?? null : v
}

function onImageUploaded(v: { id: number, url: string }) {
  draft.image_file_id = v.id
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-4">
      <h2 class="section-title m-0">Website Banners</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Manage banners shown on your event landing page.</p>
    </div>

    <div class="card">
      <div class="flex items-center justify-between gap-4 mb-5">
        <div>
          <div class="font-bold text-base">
            Website Banners
            <span v-if="saved" class="badge active ml-2">saved ✓</span>
          </div>
          <div class="muted text-[.84rem]">Banners shown on the event landing page.</div>
        </div>
        <button class="btn" @click="openAdd">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
          ADD BANNER
        </button>
      </div>

      <!-- Banner grid -->
      <div v-if="banners.length" class="flex flex-wrap gap-4">
        <div v-for="b in banners" :key="b.id" class="w-55">
          <div class="img-card" style="aspect-ratio: 220 / 140;">
            <img v-if="b.image_url" :src="b.image_url" :alt="b.name">
            <div v-else class="w-full h-full flex items-center justify-center text-muted text-sm">No image</div>
            <div class="img-card-actions">
              <button class="img-action" title="Edit banner" @click="openEdit(b)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.85 0 114 4L7.5 20.5 2 22l1.5-5.5z"/></svg>
              </button>
              <button class="img-action danger" title="Remove banner" @click="removeBanner(b)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
              </button>
            </div>
          </div>
          <p v-if="b.name" class="text-[.82rem] text-ink font-medium mt-1.5 mb-0 truncate">{{ b.name }}</p>
        </div>
      </div>

      <p v-else class="muted text-[.86rem] py-10 text-center">
        No banners yet. Click <strong>+ ADD BANNER</strong> to get started.
      </p>
    </div>

    <!-- Add / Edit drawer -->
    <Drawer
      v-if="drawerOpen"
      :title="editingId ? 'Edit Website Banner' : 'Add Website Banner'"
      @close="drawerOpen = false"
    >
      <label>Banner Name</label>
      <input v-model="draft.name" placeholder="Enter Banner Name">

      <label>Banner URL</label>
      <input v-model="draft.url" placeholder="Enter Banner URL">
      <p class="muted text-[.82rem] -mt-2 mb-4">Banner URL which will open on clicking banner</p>

      <label>
        Banner Image
        <span class="text-[#dc2626] ml-0.5">*</span>
      </label>
      <ImageField
        :model-value="draft.image_url"
        :aspect="220 / 140"
        collection="banners"
        card-width="100%"
        hint="220×140px recommended"
        @update:model-value="onImageChange"
        @uploaded="onImageUploaded"
      />

      <div class="modal-actions">
        <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
        <button class="btn" :disabled="!draft.image_file_id" @click="saveDraft">
          {{ editingId ? 'UPDATE' : 'ADD' }}
        </button>
      </div>
    </Drawer>
  </div>
</template>
