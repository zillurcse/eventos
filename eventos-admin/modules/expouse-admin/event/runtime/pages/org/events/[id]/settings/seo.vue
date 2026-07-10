<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

const seo = reactive({ title: '', description: '', keywords: '', og_image_url: '' as string | null, favicon_url: '' as string | null })
const eventName = ref('')
const previewHost = ref('your-event.eventos.app')
const saving = ref(false)
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
  saving.value = true
  try {
    await api(`/events/${id}/settings`, { method: 'PUT', body: { seo: { ...seo } } })
    saved.value = true; setTimeout(() => (saved.value = false), 1500)
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save.'
  } finally {
    saving.value = false
  }
}

const previewTitle = computed(() => seo.title || eventName.value || 'Your event title')
const previewDesc = computed(() => seo.description || 'Add a meta description to control how your event appears in search results and social shares.')

const TITLE_LIMIT = 60
const DESC_LIMIT = 160
function limitTone(len: number, limit: number) {
  const ratio = len / limit
  if (ratio > 1) return 'bg-[#dc2626]'
  if (ratio > 0.9) return 'bg-[#d97706]'
  return 'bg-brand'
}

onMounted(load)
</script>

<template>
  <div class="max-w-260">
    <SettingsPageHeader
      title="SEO & Meta Data" subtitle="Control how this event appears in search engines and social shares."
      :saving="saving" :saved="saved" @save="save"
    />

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-5 items-start">

      <!-- Form column -->
      <div class="flex flex-col gap-4 min-w-0">
        <SettingsSectionCard title="Meta Tags" subtitle="Title, description and keywords search engines read.">
          <template #icon>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8M16 17H8M10 9H8"/>
            </svg>
          </template>

          <label class="flex items-baseline justify-between">
            <span>Meta Title</span>
            <span class="text-muted font-normal text-[.78rem]">{{ seo.title.length }}/{{ TITLE_LIMIT }}</span>
          </label>
          <input v-model="seo.title" maxlength="70" placeholder="e.g. Tech Expo 2026 — The Future of Innovation">
         

          <label class="flex items-baseline justify-between">
            <span>Meta Description</span>
            <span class="text-muted font-normal text-[.78rem]">{{ seo.description.length }}/{{ DESC_LIMIT }}</span>
          </label>
          <textarea v-model="seo.description" rows="3" maxlength="200" placeholder="A short, compelling summary shown under the title in search results." />
       
          <label>Keywords</label>
          <input v-model="seo.keywords" placeholder="comma, separated, keywords">
        </SettingsSectionCard>

        <SettingsSectionCard title="Social & Favicon" subtitle="Shown when this event is shared or bookmarked.">
          <template #icon>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>
            </svg>
          </template>

          <div class="flex gap-[18px] flex-wrap">
            <div class="w-[240px]">
              <label>Social share image (OG)</label>
              <UploadButton :preview="seo.og_image_url" collection="banner" @uploaded="v => seo.og_image_url = v.url" />
              <div class="text-muted text-[.78rem] mt-1">Recommended 1200×630.</div>
            </div>
            <div class="w-[120px]">
              <label>Favicon</label>
              <UploadButton :preview="seo.favicon_url" collection="logo" @uploaded="v => seo.favicon_url = v.url" />
            </div>
          </div>

          <p v-if="error" class="error mt-3">{{ error }}</p>
        </SettingsSectionCard>
      </div>

      <!-- Live previews column -->
      <div class="flex flex-col gap-4 lg:sticky lg:top-[86px]">
        <div class="card">
          <p class="text-[.72rem] font-bold text-faint uppercase tracking-wider mb-3">Google preview</p>
          <div class="rounded-xl border border-line px-4 py-3.5 bg-white">
            <div class="flex items-center gap-2 text-[.8rem] text-[#202124] mb-0.5">
              <span class="w-4 h-4 rounded-full bg-[#e8e8ef] shrink-0" />
              <span class="truncate">{{ previewHost }}</span>
            </div>
            <div class="text-[#1a0dab] text-[1.1rem] leading-[1.3] mb-1 truncate">{{ previewTitle }}</div>
            <div class="text-[#4d5156] text-[.82rem] leading-[1.4] line-clamp-2">{{ previewDesc }}</div>
          </div>
        </div>

        <div class="card">
          <p class="text-[.72rem] font-bold text-faint uppercase tracking-wider mb-3">Social preview</p>
          <div class="rounded-xl border border-line overflow-hidden bg-white">
            <div class="aspect-[1200/630] bg-bg flex items-center justify-center overflow-hidden">
              <img v-if="seo.og_image_url" :src="seo.og_image_url" class="w-full h-full object-cover" alt="">
              <svg v-else width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-faint">
                <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>
              </svg>
            </div>
            <div class="px-3.5 py-3 border-t border-line">
              <div class="text-[.7rem] text-faint uppercase tracking-wide mb-0.5 truncate">{{ previewHost }}</div>
              <div class="text-[.86rem] font-semibold text-ink truncate">{{ previewTitle }}</div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>
