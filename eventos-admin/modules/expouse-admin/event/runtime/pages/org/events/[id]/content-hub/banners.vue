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
        <div
          v-for="b in banners" :key="b.id"
          class="relative group w-[220px] h-[140px] rounded-xl overflow-hidden bg-[#f3f4f6] border border-line cursor-pointer shrink-0"
          @click="openEdit(b)"
        >
          <img v-if="b.image_url" :src="b.image_url" :alt="b.name" class="w-full h-full object-cover">
          <div v-else class="w-full h-full flex items-center justify-center text-muted text-sm">No image</div>

          <!-- hover overlay -->
          <div class="absolute inset-0 bg-[rgba(0,0,0,.45)] opacity-0 group-hover:opacity-100 transition-opacity duration-150 flex items-center justify-center gap-2">
            <button
              class="px-3 py-1.5 rounded-md bg-white text-[#1a1a2e] text-xs font-semibold hover:bg-[#f5f5f8]"
              @click.stop="openEdit(b)"
            >Edit</button>
            <button
              class="px-3 py-1.5 rounded-md bg-[#dc2626] text-white text-xs font-semibold hover:bg-[#b91c1c]"
              @click.stop="removeBanner(b)"
            >Remove</button>
          </div>

          <!-- name strip -->
          <div v-if="b.name" class="absolute bottom-0 left-0 right-0 px-2.5 py-1.5 bg-[rgba(0,0,0,.5)] text-white text-xs truncate">
            {{ b.name }}
          </div>
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
      <UploadButton
        :preview="draft.image_url ?? undefined"
        collection="banners"
        path="/events/uploads"
        @uploaded="(v: any) => { draft.image_file_id = v.id; draft.image_url = v.url }"
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
