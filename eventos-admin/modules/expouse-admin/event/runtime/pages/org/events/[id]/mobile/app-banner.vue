<script setup lang="ts">
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface AppBanner {
  id: string
  title: string
  description: string
  image_file_id: number | null
  image_url: string | null
  link_url: string
  active: boolean
}

const banners = ref<AppBanner[]>([])
const drawerOpen = ref(false)
const editingId = ref<string | null>(null)
const formError = ref('')

const draft = reactive<AppBanner>({
  id: '',
  title: '',
  description: '',
  image_file_id: null,
  image_url: null,
  link_url: '',
  active: true,
})

async function load() {
  try {
    banners.value = (await api<any>(`/events/${id}/settings`)).data.app_banner || []
  } catch { /* */ }
}

async function persist() {
  try {
    await api(`/events/${id}/settings`, {
      method: 'PUT',
      body: { app_banner: JSON.parse(JSON.stringify(banners.value)) },
    })
    toast.success('Banners saved')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not save.')
  }
}

function openAdd() {
  editingId.value = null
  Object.assign(draft, {
    id: 'ab' + Date.now(),
    title: '',
    description: '',
    image_file_id: null,
    image_url: null,
    link_url: '',
    active: true,
  })
  formError.value = ''
  drawerOpen.value = true
}

function openEdit(b: AppBanner) {
  editingId.value = b.id
  Object.assign(draft, { ...b })
  formError.value = ''
  drawerOpen.value = true
}

async function saveDraft() {
  if (!draft.image_url) {
    formError.value = 'A banner image is required.'
    return
  }
  const clean: AppBanner = JSON.parse(JSON.stringify(draft))
  clean.title = clean.title.trim()
  clean.description = clean.description.trim()
  clean.link_url = clean.link_url.trim()
  if (editingId.value) {
    const i = banners.value.findIndex((b: AppBanner) => b.id === editingId.value)
    if (i >= 0) banners.value[i] = clean
  } else {
    banners.value.push(clean)
  }
  await persist()
  drawerOpen.value = false
}

async function removeBanner(b: AppBanner) {
  if (!confirm(`Remove banner${b.title ? ` "${b.title}"` : ''}?`)) return
  banners.value = banners.value.filter((x: AppBanner) => x.id !== b.id)
  await persist()
}

async function move(index: number, dir: -1 | 1) {
  const target = index + dir
  if (target < 0 || target >= banners.value.length) return
  const arr = banners.value
  ;[arr[index], arr[target]] = [arr[target], arr[index]]
  await persist()
}

async function toggleActive(b: AppBanner) {
  b.active = !b.active
  await persist()
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-4">
      <h2 class="section-title m-0">Add App Banner</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">
        Promotional banners shown on the mobile app home screen.
      </p>
    </div>

    <div class="card">
      <div class="flex items-center justify-between gap-4 mb-5">
        <div>
          <div class="font-bold text-base">App Banners</div>
          <div class="muted text-[.84rem]">Order matches the carousel order in the app.</div>
        </div>
        <button class="btn" @click="openAdd">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
          ADD BANNER
        </button>
      </div>

      <div v-if="banners.length" class="flex flex-col gap-3">
        <div
          v-for="(b, i) in banners" :key="b.id"
          class="flex items-center gap-4 border border-line rounded-xl p-3 bg-white"
          :class="{ 'opacity-55': !b.active }"
        >
          <div class="w-40 shrink-0 rounded-lg overflow-hidden bg-[#f3f4f6] border border-line" style="aspect-ratio: 1036 / 350">
            <img
              v-if="b.image_url"
              :src="b.image_url"
              :alt="b.title || `Banner ${i + 1}`"
              class="w-full h-full object-cover"
            >
            <div v-else class="w-full h-full grid place-items-center text-muted text-[.78rem]">No image</div>
          </div>

          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="font-semibold text-ink truncate">{{ b.title || `Banner ${i + 1}` }}</span>
              <span v-if="!b.active" class="badge draft">Hidden</span>
            </div>
            <div class="muted text-[.82rem] line-clamp-2 mt-0.5">{{ b.description || '—' }}</div>
            <div v-if="b.link_url" class="text-faint text-[.75rem] mt-0.5 truncate">{{ b.link_url }}</div>
          </div>

          <div class="flex items-center gap-1 shrink-0">
            <button class="icon-btn" title="Move up" :disabled="i === 0" @click="move(i, -1)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 15l-6-6-6 6"/></svg>
            </button>
            <button class="icon-btn" title="Move down" :disabled="i === banners.length - 1" @click="move(i, 1)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <button class="icon-btn" :title="b.active ? 'Hide' : 'Show'" @click="toggleActive(b)">
              <svg v-if="!b.active" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19M1 1l22 22"/></svg>
              <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
            <button class="icon-btn" title="Edit" @click="openEdit(b)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
            </button>
            <button class="icon-btn danger" title="Remove" @click="removeBanner(b)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
            </button>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-13 px-5">
        <div class="w-13.5 h-13.5 rounded-[14px] bg-[#f3f0ff] text-[#6352e7] grid place-items-center mx-auto mb-3.5">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="5" width="18" height="14" rx="2"/>
            <path d="M3 15l5-5 4 4 3-3 6 6"/>
          </svg>
        </div>
        <p class="muted m-0 mb-1 font-medium text-ink">No app banners yet</p>
        <p class="muted m-0 mb-3 text-[.86rem]">Add a promotional banner for the mobile app home screen.</p>
        <button class="btn" @click="openAdd">+ ADD BANNER</button>
      </div>
    </div>

    <Drawer
      v-if="drawerOpen"
      :title="editingId ? 'Edit App Banner' : 'Add App Banner'"
      @close="drawerOpen = false"
    >
      <div class="mb-5">
        <FormField label="Banner image" required hint="Wide image (~1036×350 px).">
          <ImageField
            :model-value="draft.image_url"
            :aspect="1036 / 350"
            :output-width="1036"
            :output-height="350"
            collection="app_banner"
            card-width="280px"
            :gallery-path="`/events/${id}/gallery`"
            @update:model-value="draft.image_url = (Array.isArray($event) ? $event[0] : $event) || null"
            @uploaded="(v: any) => draft.image_file_id = v.id"
          />
        </FormField>
        <p v-if="formError" class="text-[#dc2626] text-[.82rem] mt-2 mb-0">{{ formError }}</p>
      </div>

      <div class="mb-4">
        <AppInput v-model="draft.title" label="Title" placeholder="Banner title" />
      </div>

      <div class="mb-4">
        <AppTextarea v-model="draft.description" label="Description" :rows="2" placeholder="Short supporting text" />
      </div>

      <div class="mb-4">
        <AppInput v-model="draft.link_url" label="Link URL" placeholder="https://…" />
      </div>

      <AppCheckbox
        v-model="draft.active"
        label="Active"
        description="Show this banner in the mobile app"
      />

      <div class="modal-actions border-t border-line pt-4 mt-5">
        <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
        <button class="btn" @click="saveDraft">
          {{ editingId ? 'UPDATE' : 'ADD' }}
        </button>
      </div>
    </Drawer>
  </div>
</template>

<style scoped>
.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  color: var(--muted, #6b7280);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: background .15s, color .15s;
}
.icon-btn:hover:not(:disabled) {
  background: #f3f0ff;
  color: #6352e7;
}
.icon-btn.danger:hover:not(:disabled) {
  background: #fef2f2;
  color: #dc2626;
}
.icon-btn:disabled {
  opacity: .35;
  cursor: not-allowed;
}
</style>
