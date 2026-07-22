<script setup lang="ts">
import { ref, computed } from 'vue'
import type { BuilderField, FormDesign } from '../../utils/profileFormTypes'

/**
 * Design preview for the profile form builder — renders the CURRENT (possibly
 * unsaved) fields the way an end user will see them: 50/100 widths, sections,
 * placeholders, options, required marks. A surface picker shows exactly what
 * each collection surface gets, since fields opt in per surface; a device
 * toggle previews the mobile stack. Inputs are disabled — it's a look, not a
 * form.
 */
const props = defineProps<{
  fields: BuilderField[]
  name: string
  audience: string
  design: FormDesign
  eventPrimary: string
  eventName?: string
}>()

type Surface = 'public' | 'onboarding' | 'registration'

const SURFACES: { key: Surface, label: string, hint: string }[] = [
  { key: 'public', label: 'Public form', hint: 'The shared link / embedded form (/f/…).' },
  { key: 'onboarding', label: 'Onboarding', hint: 'The post-login onboarding page — shown after the built-in basics (job title, company, bio, interests).' },
  { key: 'registration', label: 'Registration', hint: 'The event-site sign-up step.' },
]

const surface = ref<Surface>('public')
const device = ref<'desktop' | 'mobile'>('desktop')

// Only the attendee form feeds signup + onboarding; other audiences collect
// through the public form alone.
const surfaces = computed(() => props.audience === 'attendee' ? SURFACES : SURFACES.slice(0, 1))
const surfaceHint = computed(() => surfaces.value.find(s => s.key === surface.value)?.hint || '')

const shown = computed(() => props.fields.filter(f =>
  f.meta.visible !== false
  && (f.meta.surfaces?.[surface.value] ?? true) !== false,
))

const fieldCount = computed(() => shown.value.filter(f => !['section_break', 'recaptcha'].includes(f.type)).length)

const inputType = (t: string) =>
  t === 'email' ? 'email' : t === 'phone' ? 'tel' : t === 'number' ? 'number' : t === 'date' ? 'date' : t === 'link' || t === 'file' ? 'url' : 'text'

const placeholderFor = (f: BuilderField) =>
  f.meta.placeholder || (f.type === 'file' ? 'Paste a link to your file (Drive, Dropbox…)' : '')

// ── Design ─────────────────────────────────────────────────────────
const brand = computed(() => props.design.brand_color || props.eventPrimary)

const usingImage = computed(() =>
  props.design.background_type === 'image' && !!props.design.background_image_url)

const stageStyle = computed(() => usingImage.value
  ? { backgroundImage: `url('${props.design.background_image_url}')`, backgroundSize: 'cover', backgroundPosition: 'center' }
  : { background: props.design.background_color })

const cardStyle = computed(() => props.design.card_style === 'glass'
  ? { background: 'rgba(255,255,255,.82)', backdropFilter: 'blur(10px)', border: '1px solid rgba(255,255,255,.6)' }
  : { background: '#fff' })

// A dark page colour would swallow dark heading text, so the header block
// flips to light type over anything dark (or over an image).
const darkStage = computed(() => {
  if (usingImage.value) return true
  const hex = props.design.background_color.replace('#', '')
  if (hex.length !== 6) return false
  const [r, g, b] = [0, 2, 4].map(i => parseInt(hex.slice(i, i + 2), 16))
  return (0.299 * r! + 0.587 * g! + 0.114 * b!) < 140
})
</script>

<template>
  <div>
    <!-- ── Preview controls ───────────────────────────────────── -->
    <div class="flex items-center gap-3 mb-4 flex-wrap">
      <div v-if="surfaces.length > 1" class="flex items-center gap-1.5">
        <button
          v-for="s in surfaces" :key="s.key"
          class="px-3.5 py-1.5 rounded-full text-[.8rem] font-semibold cursor-pointer border transition-colors duration-150"
          :class="surface === s.key ? 'bg-[#6352e7] text-white border-[#6352e7]' : 'bg-white text-[#5f6b7a] border-line hover:border-[#6352e7]'"
          @click="surface = s.key"
        >{{ s.label }}</button>
      </div>

      <span class="muted text-[.8rem]">{{ surfaceHint }}</span>
      <div class="flex-1" />

      <div class="flex items-center border border-line rounded-lg overflow-hidden">
        <button
          class="px-3 py-1.5 text-[.78rem] font-semibold cursor-pointer border-none transition-colors duration-150"
          :class="device === 'desktop' ? 'bg-[#6352e7] text-white' : 'bg-white text-[#5f6b7a] hover:bg-[#f3f0ff]'"
          title="Desktop width" @click="device = 'desktop'"
        >Desktop</button>
        <button
          class="px-3 py-1.5 text-[.78rem] font-semibold cursor-pointer border-none transition-colors duration-150"
          :class="device === 'mobile' ? 'bg-[#6352e7] text-white' : 'bg-white text-[#5f6b7a] hover:bg-[#f3f0ff]'"
          title="Mobile width" @click="device = 'mobile'"
        >Mobile</button>
      </div>
    </div>

    <!-- ── Rendered form ──────────────────────────────────────── -->
    <div class="rounded-2xl py-8 px-4 flex flex-col items-center transition-[background] duration-200" :style="stageStyle">
      <!-- Event header, when the design keeps it -->
      <div
        v-if="design.show_header"
        class="text-center mb-5 w-full transition-[max-width] duration-200"
        :class="darkStage ? 'text-white' : 'text-ink'"
        :style="{ maxWidth: device === 'mobile' ? '400px' : '660px' }"
      >
        <div class="text-[1.15rem] font-extrabold leading-tight">{{ eventName || 'Your event' }}</div>
        <div class="text-[.8rem] opacity-75 mt-0.5">Event cover, logo &amp; dates appear here</div>
      </div>

      <div
        class="rounded-2xl shadow-[0_10px_34px_rgba(15,23,42,.14)] p-7 w-full transition-[max-width] duration-200"
        :style="{ maxWidth: device === 'mobile' ? '400px' : '660px', ...cardStyle }"
      >
        <div class="mb-5">
          <div class="flex items-center gap-2">
            <h3 class="m-0 text-[1.1rem] font-extrabold text-ink">{{ name }}</h3>
            <span class="text-[.66rem] font-bold uppercase tracking-wide bg-[#eef2ff] text-[#6352e7] rounded px-1.5 py-0.5">Preview</span>
          </div>
          <p class="muted text-[.78rem] mt-1 mb-0">Fields marked <span class="text-[#dc2626]">*</span> are required.</p>
        </div>

        <div
          v-if="!fieldCount"
          class="border-2 border-dashed border-[#d7dae1] rounded-xl py-12 text-center muted text-[.85rem]"
        >
          No fields on this surface yet — switch on
          "Add field to {{ surface === 'public' ? 'Public Registration' : surface === 'onboarding' ? 'user Onboarding' : 'user registration' }}"
          in a field's Properties.
        </div>

        <div v-else class="grid grid-cols-2 gap-x-4 gap-y-4">
          <template v-for="f in shown" :key="f._id">
            <!-- section break -->
            <div v-if="f.type === 'section_break'" class="col-span-2 border-t border-line pt-4 mt-1">
              <div class="font-extrabold text-[.95rem] text-ink">{{ f.label || 'Section' }}</div>
              <p v-if="f.help_text" class="muted text-[.8rem] mt-0.5 mb-0">{{ f.help_text }}</p>
            </div>

            <!-- reCAPTCHA -->
            <div v-else-if="f.type === 'recaptcha'" class="col-span-2 flex items-center justify-between gap-3 border border-line rounded-[10px] px-4 py-3.5 bg-[#f8fafc]">
              <label class="flex items-center gap-2.5 text-[.9rem] font-medium text-ink">
                <input type="checkbox" disabled class="w-4 h-4 m-0"> I'm not a robot
              </label>
              <span class="text-[.64rem] text-faint uppercase tracking-widest">protected form</span>
            </div>

            <div
              v-else
              :class="(f.meta.width === 50 && device === 'desktop') ? 'col-span-1' : 'col-span-2'"
            >
              <label class="block text-[.84rem] font-semibold text-[#334155] mb-1.5">
                {{ f.label || f.key || 'Untitled field' }} <span v-if="f.is_required" class="text-[#dc2626]">*</span>
              </label>

              <!-- radio group -->
              <div v-if="f.type === 'radio'" class="flex flex-col gap-2 py-0.5">
                <label v-for="(o, i) in f.options" :key="i" class="flex items-center gap-2.5 text-[.9rem] text-ink font-medium">
                  <input type="radio" disabled class="w-4 h-4 m-0"> {{ o.label || `Option ${i + 1}` }}
                </label>
              </div>

              <!-- checkbox / multi-select group -->
              <div v-else-if="['checkbox', 'multiselect'].includes(f.type) && f.options.length" class="flex flex-col gap-2 py-0.5">
                <label v-for="(o, i) in f.options" :key="i" class="flex items-center gap-2.5 text-[.9rem] text-ink font-medium">
                  <input type="checkbox" disabled class="w-4 h-4 m-0"> {{ o.label || `Option ${i + 1}` }}
                </label>
              </div>

              <!-- consent checkbox with no options -->
              <label v-else-if="f.type === 'checkbox'" class="flex items-center gap-2.5 text-[.9rem] text-ink font-medium">
                <input type="checkbox" disabled class="w-4 h-4 m-0"> {{ f.help_text || 'Yes' }}
              </label>

              <select v-else-if="f.type === 'select'" disabled class="w-full m-0 border border-[#d7dae1] rounded-[10px] px-3 py-2.5 text-[.9rem] bg-white text-[#94a3b8]">
                <option>{{ f.meta.placeholder || 'Select…' }}</option>
              </select>

              <textarea
                v-else-if="f.type === 'textarea'" disabled rows="3"
                class="w-full m-0 border border-[#d7dae1] rounded-[10px] px-3 py-2.5 text-[.9rem] bg-white"
                :placeholder="f.meta.placeholder || ''"
              />

              <!-- rating -->
              <div v-else-if="f.type === 'rating'" class="flex gap-1 text-[1.45rem] leading-none text-[#d7dae1] select-none">
                <span v-for="n in Number(f.validation?.max || 5)" :key="n">★</span>
              </div>

              <input
                v-else disabled :type="inputType(f.type)"
                class="w-full m-0 border border-[#d7dae1] rounded-[10px] px-3 py-2.5 text-[.9rem] bg-white"
                :placeholder="placeholderFor(f)"
              >

              <p v-if="f.help_text && f.type !== 'checkbox'" class="muted text-[.74rem] mt-1 mb-0">{{ f.help_text }}</p>
            </div>
          </template>
        </div>

        <button
          v-if="fieldCount" disabled
          class="mt-6 w-full border-none rounded-xl opacity-90 text-white text-[.93rem] font-bold py-3 cursor-default"
          :style="{ background: brand }"
        >
          Submit
        </button>
      </div>
    </div>
  </div>
</template>
