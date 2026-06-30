<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

const coverUrl     = ref<string | null>(null)
const coverFileId  = ref<number | null>(null)
const theme        = reactive({ primary: '#6352e7', accent: '#22d3ee' })
const logoUrl      = ref<string | null>(null)
const banners      = ref<string[]>([])
const emailHeaderUrl = ref<string | null>(null)
const login        = reactive<{ type: string; banner_url: string | null; video_url: string; website_url: string }>(
  { type: 'banner', banner_url: null, video_url: '', website_url: '' },
)

const saving = ref(false)
const saved  = ref(false)
const error  = ref('')

async function load() {
  const [e, s] = await Promise.all([api<any>(`/events/${id}`), api<any>(`/events/${id}/settings`)])
  coverUrl.value = e.data.cover_url ?? null
  theme.primary  = s.data.theme?.primary || '#6352e7'
  theme.accent   = s.data.theme?.accent  || '#22d3ee'
  const b = s.data.branding || {}
  logoUrl.value        = b.logo_url ?? null
  banners.value        = Array.isArray(b.banners) ? b.banners : []
  emailHeaderUrl.value = b.email_header_url ?? null
  if (b.login) Object.assign(login, { type: b.login.type || 'banner', banner_url: b.login.banner_url ?? null, video_url: b.login.video_url ?? '', website_url: b.login.website_url ?? '' })
}

function addBanner(v: { url: string })   { banners.value.push(v.url) }
function removeBanner(i: number)         { banners.value.splice(i, 1) }

function onCoverUploaded(v: { id: number; url: string }) { coverFileId.value = v.id; coverUrl.value = v.url }
function onLogoUploaded(v: { url: string })              { logoUrl.value = v.url }
function onEmailHeaderUploaded(v: { url: string })       { emailHeaderUrl.value = v.url }
function onLoginUpdate(v: Partial<typeof login>)         { Object.assign(login, v) }

async function save() {
  error.value  = ''
  saving.value = true
  try {
    if (coverFileId.value) await api(`/events/${id}`, { method: 'PATCH', body: { cover_file_id: coverFileId.value } })
    await api(`/events/${id}/settings`, {
      method: 'PUT',
      body: {
        theme: { primary: theme.primary, accent: theme.accent },
        branding: {
          logo_url:         logoUrl.value,
          banners:          banners.value,
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

    <BrandingThemeColor
      class="mb-4"
      :primary="theme.primary"
      :accent="theme.accent"
      @update:primary="theme.primary = $event"
      @update:accent="theme.accent = $event"
    />

    <BrandingLogoAndCover
      class="mb-4"
      :cover-url="coverUrl"
      :logo-url="logoUrl"
      @cover-uploaded="onCoverUploaded"
      @logo-uploaded="onLogoUploaded"
    />

    <BrandingCommunityBanners
      class="mb-4"
      :banners="banners"
      @add="addBanner"
      @remove="removeBanner"
    />

    <BrandingLoginPageDesign
      class="mb-4"
      :login="login"
      @update="onLoginUpdate"
    />

    <BrandingEmailHeader
      class="mb-6"
      :email-header-url="emailHeaderUrl"
      @uploaded="onEmailHeaderUploaded"
    />

    <p v-if="error" class="error mb-4">{{ error }}</p>

  </div>
</template>
