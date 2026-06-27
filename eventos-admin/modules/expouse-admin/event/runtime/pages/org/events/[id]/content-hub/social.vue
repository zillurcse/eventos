<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface Social {
  hashtag: string
  facebook: string
  twitter: string
  linkedin: string
  youtube: string
  instagram: string
}

const social = reactive<Social>({
  hashtag: '',
  facebook: '',
  twitter: '',
  linkedin: '',
  youtube: '',
  instagram: '',
})

const saved = ref(false)
const saving = ref(false)

async function load() {
  try {
    const s = (await api<any>(`/events/${id}/settings`)).data.social || {}
    social.hashtag = s.hashtag || ''
    social.facebook = s.facebook || ''
    social.twitter = s.twitter || ''
    social.linkedin = s.linkedin || ''
    social.youtube = s.youtube || ''
    social.instagram = s.instagram || ''
  } catch { /* */ }
}

async function save() {
  saving.value = true
  try {
    await api(`/events/${id}/settings`, { method: 'PUT', body: { social: JSON.parse(JSON.stringify(social)) } })
    saved.value = true; setTimeout(() => (saved.value = false), 1500)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="max-w-[820px]">
    <div class="mb-4">
      <h2 class="section-title m-0">Social Links</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Social media links shown across your event website.</p>
    </div>

    <div class="card">
      <h3 class="font-bold text-base text-[#1a1a2e] m-0 mb-1">
        Social Links
        <span v-if="saved" class="badge active ml-2">saved ✓</span>
      </h3>
      <p class="muted text-[.84rem] m-0 mb-5">Social media links shown across your event website.</p>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
        <div>
          <label class="flex items-center gap-1.5 text-[.84rem] font-semibold text-[#1a1a2e] mb-1.5">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/></svg>
            Hashtag
          </label>
          <input v-model="social.hashtag" class="m-0" placeholder="YourEventHashtag">
        </div>

        <div>
          <label class="flex items-center gap-1.5 text-[.84rem] font-semibold text-[#1a1a2e] mb-1.5">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12Z"/></svg>
            Facebook
          </label>
          <input v-model="social.facebook" class="m-0" placeholder="https://www.facebook.com/yourpage">
        </div>

        <div>
          <label class="flex items-center gap-1.5 text-[.84rem] font-semibold text-[#1a1a2e] mb-1.5">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M23 4.94a8.2 8.2 0 0 1-2.36.65 4.12 4.12 0 0 0 1.8-2.27 8.2 8.2 0 0 1-2.6 1 4.1 4.1 0 0 0-7 3.74A11.65 11.65 0 0 1 4.4 3.8a4.1 4.1 0 0 0 1.27 5.47A4.07 4.07 0 0 1 3.8 8.7v.05a4.1 4.1 0 0 0 3.29 4.02 4.1 4.1 0 0 1-1.85.07 4.1 4.1 0 0 0 3.83 2.85A8.23 8.23 0 0 1 2 17.4a11.62 11.62 0 0 0 6.29 1.84c7.55 0 11.68-6.25 11.68-11.67l-.01-.53A8.3 8.3 0 0 0 23 4.94Z"/></svg>
            Twitter
          </label>
          <input v-model="social.twitter" class="m-0" placeholder="https://x.com/yourhandle">
        </div>

        <div>
          <label class="flex items-center gap-1.5 text-[.84rem] font-semibold text-[#1a1a2e] mb-1.5">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M4.98 3.5A2.5 2.5 0 1 0 5 8.5a2.5 2.5 0 0 0-.02-5ZM3 9h4v12H3V9Zm6.5 0H13v1.64h.05A3.7 3.7 0 0 1 16.4 8.8c3.6 0 4.27 2.37 4.27 5.45V21h-4v-5.95c0-1.42-.03-3.25-1.98-3.25-1.98 0-2.28 1.55-2.28 3.15V21h-3.9V9Z"/></svg>
            Linkedin
          </label>
          <input v-model="social.linkedin" class="m-0" placeholder="https://www.linkedin.com/company/yourcompany">
        </div>

        <div>
          <label class="flex items-center gap-1.5 text-[.84rem] font-semibold text-[#1a1a2e] mb-1.5">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M23 12s0-3.6-.46-5.32a2.78 2.78 0 0 0-1.96-1.96C18.86 4.26 12 4.26 12 4.26s-6.86 0-8.58.46a2.78 2.78 0 0 0-1.96 1.96C1 8.4 1 12 1 12s0 3.6.46 5.32a2.78 2.78 0 0 0 1.96 1.96c1.72.46 8.58.46 8.58.46s6.86 0 8.58-.46a2.78 2.78 0 0 0 1.96-1.96C23 15.6 23 12 23 12ZM9.75 15.5v-7l6 3.5-6 3.5Z"/></svg>
            Youtube
          </label>
          <input v-model="social.youtube" class="m-0" placeholder="https://www.youtube.com/c/yourchannel">
        </div>

        <div>
          <label class="flex items-center gap-1.5 text-[.84rem] font-semibold text-[#1a1a2e] mb-1.5">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
            Instagram
          </label>
          <input v-model="social.instagram" class="m-0" placeholder="https://www.instagram.com/yourhandle/">
        </div>
      </div>

      <div class="mt-6">
        <button class="btn" :disabled="saving" @click="save">
          {{ saving ? 'SAVING…' : 'SAVE' }}
        </button>
      </div>
    </div>
  </div>
</template>
