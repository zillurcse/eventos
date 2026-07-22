<script setup lang="ts">
/**
 * Reusable image library for the builder. Reads the tenant's previously
 * uploaded email imagery from /email-assets (backed by the `files` table) so a
 * logo or hero shot is uploaded once and reused across every template.
 */
interface Asset {
  id: string
  url: string
  filename: string | null
  collection: string | null
  created_at: string | null
}

const emit = defineEmits<{ (e: 'select', url: string): void, (e: 'close'): void }>()

const api = useApi()
const { upload } = useUpload()

const assets = ref<Asset[]>([])
const loading = ref(true)
const uploading = ref(false)
const error = ref('')
const search = ref('')

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return assets.value
  return assets.value.filter(a => (a.filename ?? '').toLowerCase().includes(q))
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    assets.value = (await api<{ data: Asset[] }>('/email-assets')).data
  } catch {
    error.value = 'Could not load your images.'
  } finally {
    loading.value = false
  }
}

async function onUpload(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  uploading.value = true
  error.value = ''
  try {
    const res = await upload(file, { collection: 'email' })
    emit('select', res.url)
  } catch (err: any) {
    error.value = err?.data?.message || 'Upload failed.'
  } finally {
    uploading.value = false
    input.value = ''
  }
}

function fmtDate(d: string | null) {
  return d ? new Date(d).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) : ''
}

onMounted(load)
</script>

<template>
  <div class="fixed inset-0 z-[170] bg-black/40 grid place-items-center p-4" @click.self="emit('close')">
    <div class="bg-white rounded-2xl shadow-xl w-[720px] max-w-full max-h-[80vh] flex flex-col">
      <header class="flex items-center gap-3 p-4 border-b border-line">
        <div class="flex-1">
          <h3 class="m-0 text-[1.05rem]">Image library</h3>
          <p class="muted text-[.8rem] m-0">Images you've uploaded for emails, reusable across templates.</p>
        </div>
        <label class="btn sm cursor-pointer m-0">
          {{ uploading ? 'Uploading…' : 'Upload new' }}
          <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="hidden" :disabled="uploading" @change="onUpload">
        </label>
        <button class="w-8 h-8 rounded-lg border border-line grid place-items-center cursor-pointer hover:bg-[#f5f5fa]" title="Close" @click="emit('close')">✕</button>
      </header>

      <div class="px-4 pt-3">
        <input v-model="search" class="m-0 text-[.86rem]" placeholder="Search by filename…">
        <p v-if="error" class="text-[#dc2626] text-[.82rem] mt-2 mb-0">{{ error }}</p>
      </div>

      <div class="flex-1 overflow-y-auto p-4">
        <p v-if="loading" class="muted text-center py-10 m-0">Loading images…</p>

        <div v-else-if="!filtered.length" class="text-center py-12">
          <div class="text-3xl mb-2">🖼</div>
          <p class="muted text-[.88rem] m-0">
            {{ assets.length ? 'No images match that search.' : 'No images yet — upload one to get started.' }}
          </p>
        </div>

        <div v-else class="grid grid-cols-[repeat(auto-fill,minmax(140px,1fr))] gap-3">
          <button
            v-for="a in filtered"
            :key="a.id"
            class="group border border-line rounded-xl overflow-hidden bg-white cursor-pointer p-0 text-left hover:border-[#6352e7] hover:shadow-md transition-all"
            @click="emit('select', a.url)"
          >
            <div class="h-[100px] bg-[#f7f7fb] grid place-items-center overflow-hidden">
              <img :src="a.url" :alt="a.filename || 'Uploaded image'" class="max-w-full max-h-full object-contain" loading="lazy">
            </div>
            <div class="p-2">
              <p class="m-0 text-[.76rem] truncate font-medium">{{ a.filename || 'Untitled' }}</p>
              <p class="m-0 muted text-[.68rem]">{{ fmtDate(a.created_at) }}</p>
            </div>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
