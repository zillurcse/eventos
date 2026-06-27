<script setup lang="ts">
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

const PRESETS = [
  { label: 'Indigo',   primary: '#6352e7', accent: '#4f46e5' },
  { label: 'Blue',     primary: '#2563eb', accent: '#1d4ed8' },
  { label: 'Emerald',  primary: '#059669', accent: '#047857' },
  { label: 'Rose',     primary: '#e11d48', accent: '#be123c' },
  { label: 'Amber',    primary: '#d97706', accent: '#b45309' },
  { label: 'Purple',   primary: '#9333ea', accent: '#7e22ce' },
  { label: 'Teal',     primary: '#0d9488', accent: '#0f766e' },
  { label: 'Slate',    primary: '#475569', accent: '#334155' },
]

const FONTS = ['Inter', 'Roboto', 'Poppins', 'Nunito', 'Open Sans', 'Lato', 'Montserrat', 'Raleway', 'Source Sans Pro', 'Work Sans']

async function load() {
  try {
    const t = (await api<any>(`/events/${id}/settings`)).data.theme || {}
    if (t.primary)       theme.primary       = t.primary
    if (t.accent)        theme.accent        = t.accent
    if (t.font_family)   theme.font_family   = t.font_family
    if (t.mode)          theme.mode          = t.mode
    if (t.header_style)  theme.header_style  = t.header_style
    if (t.button_radius) theme.button_radius = t.button_radius
  } catch { /* */ }
}

async function save() {
  saving.value = true
  try {
    await api(`/events/${id}/settings`, { method: 'PUT', body: { theme: JSON.parse(JSON.stringify(theme)) } })
    saved.value = true; setTimeout(() => (saved.value = false), 1500)
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

    <!-- Color Scheme -->
    <div class="card mb-4">
      <h3 class="font-bold text-base text-[#1a1a2e] m-0 mb-1">
        Color Scheme
        <span v-if="saved" class="badge active ml-2">saved ✓</span>
      </h3>
      <p class="muted text-[.84rem] m-0 mb-4">Choose a preset or set custom brand colors.</p>

      <div class="flex flex-wrap gap-2.5 mb-5">
        <button
          v-for="p in PRESETS" :key="p.label"
          class="flex items-center gap-2 px-3 py-1.5 rounded-full border text-sm font-medium transition-all duration-150"
          :class="theme.primary === p.primary
            ? 'border-[#6352e7] bg-[#f3f0ff] text-[#6352e7]'
            : 'border-line bg-white text-muted hover:border-[#6352e7] hover:text-[#6352e7]'"
          @click="theme.primary = p.primary; theme.accent = p.accent"
        >
          <span class="w-3.5 h-3.5 rounded-full shrink-0" :style="{ background: p.primary }" />
          {{ p.label }}
        </button>
      </div>

      <div class="flex gap-6 flex-wrap">
        <div>
          <label class="text-[.84rem] font-semibold text-[#1a1a2e] mb-1.5 block">Primary Color</label>
          <div class="flex items-center gap-2.5">
            <input
              v-model="theme.primary"
              type="color"
              class="w-10 h-10 rounded-lg border border-line cursor-pointer p-0.5 bg-white"
            >
            <input
              v-model="theme.primary"
              class="w-[110px] m-0 font-mono text-sm"
              placeholder="#6352e7"
              maxlength="9"
            >
          </div>
        </div>
        <div>
          <label class="text-[.84rem] font-semibold text-[#1a1a2e] mb-1.5 block">Accent Color</label>
          <div class="flex items-center gap-2.5">
            <input
              v-model="theme.accent"
              type="color"
              class="w-10 h-10 rounded-lg border border-line cursor-pointer p-0.5 bg-white"
            >
            <input
              v-model="theme.accent"
              class="w-[110px] m-0 font-mono text-sm"
              placeholder="#4f46e5"
              maxlength="9"
            >
          </div>
        </div>
      </div>
    </div>

    <!-- Typography -->
    <div class="card mb-4">
      <h3 class="font-bold text-base text-[#1a1a2e] m-0 mb-1">Typography</h3>
      <p class="muted text-[.84rem] m-0 mb-4">Select the font family used throughout your event site.</p>
      <label class="text-[.84rem] font-semibold text-[#1a1a2e] mb-1.5 block">Font Family</label>
      <select v-model="theme.font_family" class="max-w-[280px]">
        <option v-for="f in FONTS" :key="f" :value="f">{{ f }}</option>
      </select>
      <p class="muted text-[.82rem] mt-2 mb-0">Font is loaded from Google Fonts on the attendee website.</p>
    </div>

    <!-- Appearance options -->
    <div class="card mb-5">
      <h3 class="font-bold text-base text-[#1a1a2e] m-0 mb-4">Appearance</h3>

      <div class="flex flex-col gap-5">
        <div>
          <label class="text-[.84rem] font-semibold text-[#1a1a2e] mb-2 block">Color Mode</label>
          <div class="flex gap-2.5 flex-wrap">
            <button
              v-for="opt in [{ v: 'light', label: '☀ Light' }, { v: 'dark', label: '🌙 Dark' }, { v: 'auto', label: '⚙ Auto' }]"
              :key="opt.v"
              class="px-4 py-2 rounded-lg border text-sm font-semibold transition-all duration-150"
              :class="theme.mode === opt.v
                ? 'border-[#6352e7] bg-[#f3f0ff] text-[#6352e7]'
                : 'border-line bg-white text-muted hover:border-[#6352e7] hover:text-[#6352e7]'"
              @click="theme.mode = opt.v as any"
            >{{ opt.label }}</button>
          </div>
        </div>

        <div>
          <label class="text-[.84rem] font-semibold text-[#1a1a2e] mb-2 block">Header Style</label>
          <div class="flex gap-2.5 flex-wrap">
            <button
              v-for="opt in [{ v: 'solid', label: 'Solid' }, { v: 'transparent', label: 'Transparent' }, { v: 'gradient', label: 'Gradient' }]"
              :key="opt.v"
              class="px-4 py-2 rounded-lg border text-sm font-semibold transition-all duration-150"
              :class="theme.header_style === opt.v
                ? 'border-[#6352e7] bg-[#f3f0ff] text-[#6352e7]'
                : 'border-line bg-white text-muted hover:border-[#6352e7] hover:text-[#6352e7]'"
              @click="theme.header_style = opt.v as any"
            >{{ opt.label }}</button>
          </div>
        </div>

        <div>
          <label class="text-[.84rem] font-semibold text-[#1a1a2e] mb-2 block">Button Style</label>
          <div class="flex gap-2.5 flex-wrap">
            <button
              v-for="opt in [{ v: 'rounded', label: 'Rounded', cls: 'rounded-lg' }, { v: 'sharp', label: 'Sharp', cls: 'rounded-none' }, { v: 'pill', label: 'Pill', cls: 'rounded-full' }]"
              :key="opt.v"
              class="px-4 py-2 border text-sm font-semibold transition-all duration-150"
              :class="[opt.cls, theme.button_radius === opt.v
                ? 'border-[#6352e7] bg-[#f3f0ff] text-[#6352e7]'
                : 'border-line bg-white text-muted hover:border-[#6352e7] hover:text-[#6352e7]']"
              @click="theme.button_radius = opt.v as any"
            >{{ opt.label }}</button>
          </div>
        </div>
      </div>
    </div>

    <div class="flex justify-end">
      <button class="btn" :disabled="saving" @click="save">
        {{ saving ? 'Saving…' : 'Save Theme' }}
      </button>
    </div>
  </div>
</template>
