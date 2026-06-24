<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

const seo = reactive({ title: '', description: '', keywords: '', og_image_url: '' as string | null, favicon_url: '' as string | null })
const eventName = ref('')
const previewHost = ref('your-event.eventos.app')
const saved = ref(false)
const error = ref('')

async function load() {
  const [e, s] = await Promise.all([api<any>(`/events/${id}`), api<any>(`/events/${id}/settings`)])
  eventName.value = e.data.name
  const v = s.data.seo || {}
  seo.title = v.title || ''
  seo.description = v.description || ''
  seo.keywords = v.keywords || ''
  seo.og_image_url = v.og_image_url || null
  seo.favicon_url = v.favicon_url || null
  const d = s.data.domain || {}
  previewHost.value = d.custom_domain || (d.subdomain ? `${d.subdomain}.eventos.app` : 'your-event.eventos.app')
}

async function save() {
  error.value = ''
  try {
    await api(`/events/${id}/settings`, { method: 'PUT', body: { seo: { ...seo } } })
    saved.value = true; setTimeout(() => (saved.value = false), 1500)
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save.'
  }
}

const previewTitle = computed(() => seo.title || eventName.value || 'Your event title')
const previewDesc = computed(() => seo.description || 'Add a meta description to control how your event appears in search results and social shares.')

onMounted(load)
</script>

<template>
  <div class="max-w-[760px]">
    <div class="card">
      <h2>SEO &amp; Meta Data <span v-if="saved" class="badge active">saved ✓</span></h2>
      <p class="muted text-[.84rem] -mt-1.5">Control how this event appears in search engines and social shares.</p>

      <label>Meta Title <span class="muted font-normal">({{ seo.title.length }}/60)</span></label>
      <input v-model="seo.title" maxlength="70" placeholder="e.g. AI Expo 2026 — The Future of AI">

      <label>Meta Description <span class="muted font-normal">({{ seo.description.length }}/160)</span></label>
      <textarea v-model="seo.description" rows="3" maxlength="200" placeholder="A short, compelling summary shown under the title in search results." />

      <label>Keywords</label>
      <input v-model="seo.keywords" placeholder="comma, separated, keywords">

      <div class="flex gap-[18px] flex-wrap mt-1.5">
        <div class="w-[240px]">
          <label>Social share image (OG)</label>
          <UploadButton :preview="seo.og_image_url" collection="banner" @uploaded="v => seo.og_image_url = v.url" />
          <div class="muted text-[.78rem] mt-1">Recommended 1200×630.</div>
        </div>
        <div class="w-[120px]">
          <label>Favicon</label>
          <UploadButton :preview="seo.favicon_url" collection="logo" @uploaded="v => seo.favicon_url = v.url" />
        </div>
      </div>

      <p v-if="error" class="error">{{ error }}</p>
      <div class="mt-4"><button class="btn" @click="save">Save SEO</button></div>
    </div>

    <!-- Search result preview -->
    <div class="card">
      <h2>Search preview</h2>
      <div class="px-0.5 py-1.5">
        <div class="text-[.8rem] text-[#202124]">{{ previewHost }}</div>
        <div class="text-[#1a0dab] text-[1.25rem] leading-[1.3] my-0.5">{{ previewTitle }}</div>
        <div class="text-[#4d5156] text-[.86rem] leading-[1.45]">{{ previewDesc }}</div>
      </div>
    </div>
  </div>
</template>
