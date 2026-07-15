<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface BannerItem {
  image:   string
  title?:  string
  url?:    string
  active?: boolean
}

interface BrandingColors {
  nav_bg:         string
  nav_text:       string
  primary_button: string
  body_text:      string
  page_bg:        string
  content_bg:     string
}

const theme        = reactive({ accent: '#22d3ee' })
const colors       = reactive<BrandingColors>({
  nav_bg:         '#F7F7FB',
  nav_text:       '#212529',
  primary_button: '#6352e7',
  body_text:      '#212529',
  page_bg:        '#F7F7FB',
  content_bg:     '#F0EEFD',
})
const appearance   = ref('minimal')
const logoUrl      = ref<string | null>(null)
const banners      = ref<BannerItem[]>([])
const eventBanners = ref<BannerItem[]>([])
const emailHeaderUrl = ref<string | null>(null)
const login        = reactive<{ type: string; banner_url: string | null; video_url: string; website_url: string }>(
  { type: 'banner', banner_url: null, video_url: '', website_url: '' },
)

const saving = ref(false)
const saved  = ref(false)
const error  = ref('')

// Banners were once stored as plain URL strings; normalize to objects.
function normalizeBanners(arr: unknown): BannerItem[] {
  return (Array.isArray(arr) ? arr : [])
    .map((x: any) => (typeof x === 'string' ? { image: x, active: true } : x))
    .filter((x: any) => x?.image)
}

async function load() {
  const s = await api<any>(`/events/${id}/settings`)
  theme.accent   = s.data.theme?.accent  || '#22d3ee'
  const b = s.data.branding || {}
  logoUrl.value        = b.logo_url ?? null
  Object.assign(colors, b.colors || {}, { primary_button: s.data.theme?.primary || b.colors?.primary_button || '#6352e7' })
  appearance.value     = b.appearance || 'minimal'
  banners.value        = normalizeBanners(b.banners)
  eventBanners.value   = normalizeBanners(b.event_banners)
  emailHeaderUrl.value = b.email_header_url ?? null
  if (b.login) Object.assign(login, { type: b.login.type || 'banner', banner_url: b.login.banner_url ?? null, video_url: b.login.video_url ?? '', website_url: b.login.website_url ?? '' })
}

function onBannersUpdate(v: BannerItem[]) { banners.value = v }
function onEventBannersUpdate(v: BannerItem[]) { eventBanners.value = v }
function onColorsUpdate(v: Partial<BrandingColors>) { Object.assign(colors, v) }

function onLogoUploaded(v: { url: string | null })          { logoUrl.value = v.url }
function onEmailHeaderUploaded(v: { url: string | null })   { emailHeaderUrl.value = v.url }
function onLoginUpdate(v: Partial<typeof login>)         { Object.assign(login, v) }

async function save() {
  error.value  = ''
  saving.value = true
  try {
    await api(`/events/${id}/settings`, {
      method: 'PUT',
      body: {
        theme: { primary: colors.primary_button, accent: theme.accent },
        branding: {
          logo_url:         logoUrl.value,
          colors:           { ...colors },
          appearance:       appearance.value,
          banners:          banners.value,
          event_banners:    eventBanners.value,
          email_header_url: emailHeaderUrl.value,
          login: { type: login.type, banner_url: login.banner_url, video_url: login.video_url, website_url: login.website_url },
        },
      },
    })
    saved.value = true
    setTimeout(() => (saved.value = false), 2000)
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save.'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="max-w-180">

    <!-- Page header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-[1.35rem] font-bold text-ink mb-0.5">Branding</h1>
        <p class="text-muted text-[.88rem]">Customise colors, images, and the look of your event.</p>
      </div>
      <button class="btn" :disabled="saving" @click="save">
        <svg v-if="saving" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
          <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
        </svg>
        <svg v-else-if="saved" width="14" height="14" viewBox="0 0 24 24" fill="none">
          <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        {{ saving ? 'Saving…' : saved ? 'Saved' : 'Save branding' }}
      </button>
    </div>

    <BrandingLogo
      class="mb-4"
      :event-id="id"
      :logo-url="logoUrl"
      @logo-uploaded="onLogoUploaded"
    />

    <BrandingAppearance
      class="mb-4"
      v-model="appearance"
    />

    <BrandingColors
      class="mb-4"
      :colors="colors"
      @update="onColorsUpdate"
    />

    <BrandingBannerList
      class="mb-4"
      :event-id="id"
      :banners="banners"
      title="Community Banner"
      subtitle="Banners displayed on the event landing page."
      @update="onBannersUpdate"
    />

    <BrandingBannerList
      class="mb-4"
      :event-id="id"
      :banners="eventBanners"
      title="Event Page Banner"
      subtitle="Banners displayed inside the event app after sign-in."
      @update="onEventBannersUpdate"
    />

    <BrandingLoginPageDesign
      class="mb-4"
      :event-id="id"
      :login="login"
      @update="onLoginUpdate"
    />

    <BrandingEmailHeader
      class="mb-6"
      :event-id="id"
      :email-header-url="emailHeaderUrl"
      @uploaded="onEmailHeaderUploaded"
    />

    <p v-if="error" class="error mb-4">{{ error }}</p>

  </div>
</template>
