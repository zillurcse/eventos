<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

const coverUrl = ref<string | null>(null)
const coverFileId = ref<number | null>(null)
const theme = reactive({ primary: '#6352e7', accent: '#22d3ee' })
const logoUrl = ref<string | null>(null)
const banners = ref<string[]>([])
const bannerKey = ref(0)
const emailHeaderUrl = ref<string | null>(null)
const login = reactive<{ type: string, banner_url: string | null, video_url: string, website_url: string }>(
  { type: 'banner', banner_url: null, video_url: '', website_url: '' },
)
const saved = ref(false)
const error = ref('')

const LOGIN_TYPES = [
  { value: 'banner', label: 'Banner image' },
  { value: 'video', label: 'YouTube video' },
  { value: 'website', label: 'Website' },
]

async function load() {
  const [e, s] = await Promise.all([api<any>(`/events/${id}`), api<any>(`/events/${id}/settings`)])
  coverUrl.value = e.data.cover_url ?? null
  theme.primary = s.data.theme?.primary || '#6352e7'
  theme.accent = s.data.theme?.accent || '#22d3ee'
  const b = s.data.branding || {}
  logoUrl.value = b.logo_url ?? null
  banners.value = Array.isArray(b.banners) ? b.banners : []
  emailHeaderUrl.value = b.email_header_url ?? null
  if (b.login) Object.assign(login, { type: b.login.type || 'banner', banner_url: b.login.banner_url ?? null, video_url: b.login.video_url ?? '', website_url: b.login.website_url ?? '' })
}

function addBanner(v: { url: string }) { banners.value.push(v.url); bannerKey.value++ }
function removeBanner(i: number) { banners.value.splice(i, 1) }

async function save() {
  error.value = ''
  try {
    if (coverFileId.value) await api(`/events/${id}`, { method: 'PATCH', body: { cover_file_id: coverFileId.value } })
    await api(`/events/${id}/settings`, { method: 'PUT', body: {
      theme: { primary: theme.primary, accent: theme.accent },
      branding: {
        logo_url: logoUrl.value,
        banners: banners.value,
        email_header_url: emailHeaderUrl.value,
        login: { type: login.type, banner_url: login.banner_url, video_url: login.video_url, website_url: login.website_url },
      },
    } })
    saved.value = true; setTimeout(() => (saved.value = false), 1500)
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save.'
  }
}

onMounted(load)
</script>

<template>
  <div class="max-w-[760px]">
    <div class="card">
      <h2>Branding <span v-if="saved" class="badge active">saved ✓</span></h2>

      <!-- Theme color -->
      <label>Theme color</label>
      <div class="flex gap-6">
        <div>
          <div class="muted text-[.8rem]">Primary</div>
          <div class="flex items-center gap-2">
            <input v-model="theme.primary" type="color" class="w-12 h-[38px] p-[3px]">
            <input v-model="theme.primary" class="w-[110px]">
          </div>
        </div>
        <div>
          <div class="muted text-[.8rem]">Accent</div>
          <div class="flex items-center gap-2">
            <input v-model="theme.accent" type="color" class="w-12 h-[38px] p-[3px]">
            <input v-model="theme.accent" class="w-[110px]">
          </div>
        </div>
        <div class="flex-1">
          <div class="muted text-[.8rem]">Preview</div>
          <div class="h-[38px] rounded-lg mt-1.5" :style="`background:linear-gradient(135deg, ${theme.primary}, ${theme.accent})`" />
        </div>
      </div>
    </div>

    <div class="card">
      <h2>Logo &amp; cover</h2>
      <div class="flex gap-[18px] flex-wrap">
        <div class="w-[200px]">
          <label>Cover image</label>
          <UploadButton :preview="coverUrl" collection="cover" @uploaded="v => { coverFileId = v.id; coverUrl = v.url }" />
        </div>
        <div class="w-[200px]">
          <label>Logo</label>
          <UploadButton :preview="logoUrl" collection="logo" @uploaded="v => logoUrl = v.url" />
        </div>
      </div>
    </div>

    <!-- Community banners -->
    <div class="card">
      <h2>Community Banners</h2>
      <div class="flex gap-3 flex-wrap mb-2.5">
        <div v-for="(b, i) in banners" :key="b" class="relative w-[160px] h-[90px] rounded-lg overflow-hidden border border-line">
          <img :src="b" alt="banner" class="w-full h-full object-cover">
          <button class="btn sm danger absolute top-1.5 right-1.5 py-0.5 px-2" @click="removeBanner(i)">✕</button>
        </div>
      </div>
      <div class="max-w-[200px]">
        <UploadButton :key="bannerKey" collection="banner" @uploaded="addBanner" />
      </div>
    </div>

    <!-- Login page design -->
    <div class="card">
      <h2>Login Page Design</h2>
      <div class="inline-flex gap-2">
        <button
          v-for="t in LOGIN_TYPES" :key="t.value" type="button"
          class="px-4 py-[9px] border border-[1.5px] border-line rounded-lg bg-white cursor-pointer font-semibold text-[.88rem] text-muted transition-all duration-150 hover:border-[#c7c2f5] hover:text-[#6352e7]"
          :class="{ 'border-[#6352e7] bg-[#f3f0ff] text-[#6352e7]': login.type === t.value }"
          @click="login.type = t.value"
        >{{ t.label }}</button>
      </div>
      <div class="mt-3.5">
        <div v-if="login.type === 'banner'" class="max-w-[300px]">
          <label>Login banner image</label>
          <UploadButton :preview="login.banner_url" collection="banner" @uploaded="v => login.banner_url = v.url" />
        </div>
        <div v-else-if="login.type === 'video'">
          <label>YouTube video URL</label>
          <input v-model="login.video_url" placeholder="https://www.youtube.com/watch?v=…">
        </div>
        <div v-else>
          <label>Website URL</label>
          <input v-model="login.website_url" placeholder="https://yourcompany.com">
        </div>
      </div>
    </div>

    <!-- Email header -->
    <div class="card">
      <h2>Email Header</h2>
      <p class="muted text-[.84rem] -mt-1.5">Upload or update the header image shown at the top of event emails.</p>
      <div class="max-w-[360px]">
        <UploadButton :preview="emailHeaderUrl" collection="email_header" @uploaded="v => emailHeaderUrl = v.url" />
      </div>
    </div>

    <p v-if="error" class="error">{{ error }}</p>
    <div class="my-1.5 mb-[30px]"><button class="btn" @click="save">Save branding</button></div>
  </div>
</template>
