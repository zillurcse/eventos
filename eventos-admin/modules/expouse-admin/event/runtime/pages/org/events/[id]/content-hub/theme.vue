<script setup lang="ts">
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface WebTheme {
  primary: string
  accent: string
  font_family: string
  mode: 'light' | 'dark' | 'auto'
  header_style: 'solid' | 'transparent' | 'gradient'
  button_radius: 'rounded' | 'sharp' | 'pill'
}

const theme = reactive<WebTheme>({
  primary: '#6352e7',
  accent: '#4f46e5',
  font_family: 'Inter',
  mode: 'light',
  header_style: 'solid',
  button_radius: 'rounded',
})

const saved = ref(false)
const saving = ref(false)

async function load() {
  try {
    const t = (await api<any>(`/events/${id}/settings`)).data.theme || {}
    if (t.primary)       theme.primary       = t.primary
    if (t.accent)        theme.accent        = t.accent
    if (t.font_family)   theme.font_family   = t.font_family
    if (t.mode)          theme.mode          = t.mode
    if (t.header_style)  theme.header_style  = t.header_style
    if (t.button_radius) theme.button_radius = t.button_radius
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not load the theme.')
  }
}

async function save() {
  saving.value = true
  try {
    await api(`/events/${id}/settings`, { method: 'PUT', body: { theme: JSON.parse(JSON.stringify(theme)) } })
    // The inline tick on the colour card stays — it marks *what* was saved; the
    // toast is the confirmation you get wherever you are on the page.
    saved.value = true; setTimeout(() => (saved.value = false), 1500)
    toast.success('Theme saved')
  } catch (e: any) {
    // A failed save used to vanish: no catch at all, so the rejection went
    // nowhere and the button just stopped spinning as if it had worked.
    toast.error(e?.data?.message || 'Could not save the theme.')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="max-w-[720px]">
    <div class="mb-4">
      <h2 class="section-title m-0">Website Theme</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Customize the visual appearance of your event website.</p>
    </div>

    <ThemeColorScheme
      class="mb-4"
      :primary="theme.primary"
      :accent="theme.accent"
      :saved="saved"
      @update:primary="theme.primary = $event"
      @update:accent="theme.accent = $event"
    />

    <ThemeTypography
      class="mb-4"
      :font-family="theme.font_family"
      @update:font-family="theme.font_family = $event"
    />

    <ThemeAppearance
      class="mb-5"
      :mode="theme.mode"
      :header-style="theme.header_style"
      :button-radius="theme.button_radius"
      @update:mode="theme.mode = $event"
      @update:header-style="theme.header_style = $event"
      @update:button-radius="theme.button_radius = $event"
    />

    <div class="flex justify-end">
      <button class="btn" :disabled="saving" @click="save">
        {{ saving ? 'Saving…' : 'Save Theme' }}
      </button>
    </div>
  </div>
</template>
